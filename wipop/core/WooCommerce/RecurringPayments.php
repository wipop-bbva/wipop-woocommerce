<?php

declare(strict_types=1);

namespace WipopWC\Core\WooCommerce;

use DateInterval;
use DateTimeImmutable;
use Throwable;
use WC_DateTime;
use WC_Order;
use WC_Order_Item_Product;
use WC_Payment_Token_CC;
use WC_Payment_Tokens;
use Wipop\Charge\ChargeMethod;
use Wipop\Charge\OriginChannel;
use Wipop\Charge\PostType;
use Wipop\Charge\PostTypeMode;
use Wipop\Domain\Transaction;
use Wipop\Domain\TransactionStatus;
use WipopWC\Core\Api\ClientFactory;
use WipopWC\Core\Api\SdkCaller;
use WipopWC\Core\Exception\ClientConfigurationException;
use WipopWC\Core\Logger;
use WipopWC\Gateways\Card\Gateway as CardGateway;
use WipopWC\Gateways\Support\ChargeRequestFactory;
use WipopWC\Gateways\Support\OrderIdFactory;

use function __;
use function add_action;
use function array_keys;
use function get_post_meta;
use function home_url;
use function implode;
use function in_array;
use function method_exists;
use function reset;
use function sprintf;
use function time;
use function wc_get_order;
use function wc_price;
use function wp_next_scheduled;
use function wp_schedule_single_event;
use function wp_timezone;
use function wp_unschedule_event;

final class RecurringPayments
{
	public const META_ENABLED = '_wipop_recurring_enabled';
	public const META_PERIOD = '_wipop_recurring_period';
	public const META_ENABLED_YES = 'yes';
	public const ORDER_META_SCHEDULE = '_wipop_recurring_schedule';
	public const ORDER_META_SEQUENCE = '_wipop_recurring_sequence';
	public const PERIOD_MONTHLY = 'monthly';
	public const PERIOD_YEARLY = 'yearly';
	private const CRON_HOOK = 'wipop_process_recurring_payment';
	private const MINUTE_IN_SECONDS = 60;

	public static function init(): void
	{
		add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'markRecurringItem'], 10, 4);
		add_action('woocommerce_payment_complete', [__CLASS__, 'maybeScheduleOrder']);
		add_action(self::CRON_HOOK, [__CLASS__, 'handleScheduledCharge'], 10, 2);
		add_action('woocommerce_order_status_cancelled', [__CLASS__, 'cancelOrderSchedulesOnStateChange']);
		add_action('woocommerce_order_status_refunded', [__CLASS__, 'cancelOrderSchedulesOnStateChange']);
		add_action('woocommerce_order_status_failed', [__CLASS__, 'cancelOrderSchedulesOnStateChange']);
		add_action('before_delete_post', [__CLASS__, 'handleOrderDeletion']);
		add_filter('woocommerce_order_item_display_meta_key', [__CLASS__, 'displayMetaKey'], 10, 3);
		add_filter('woocommerce_order_item_display_meta_value', [__CLASS__, 'displayMetaValue'], 10, 3);
	}

	public static function markRecurringItem(
		WC_Order_Item_Product $item,
		string $cartItemKey,
		array $values,
		WC_Order $order
	): void {
		$product = $item->get_product();
		if (!$product) {
			return;
		}

		$productId = $product->get_id();
		if ($productId <= 0) {
			return;
		}

		$enabled = get_post_meta($productId, self::META_ENABLED, true);
		$period = (string) get_post_meta($productId, self::META_PERIOD, true);

		if ($enabled !== self::META_ENABLED_YES || !self::isValidPeriod($period)) {
			$parentId = method_exists($product, 'get_parent_id') ? (int) $product->get_parent_id() : 0;
			if ($parentId > 0) {
				$enabled = get_post_meta($parentId, self::META_ENABLED, true);
				$period = (string) get_post_meta($parentId, self::META_PERIOD, true);
			}
		}

		if ($enabled !== self::META_ENABLED_YES || !self::isValidPeriod($period)) {
			return;
		}

		$item->add_meta_data(self::META_ENABLED, self::META_ENABLED_YES, true);
		$item->add_meta_data(self::META_PERIOD, $period, true);
	}

	/**
	 * Executed only on first payment (CIT)
	 */
	public static function maybeScheduleOrder(int $orderId): void
	{
		$order = wc_get_order($orderId);
		if (!$order instanceof WC_Order) {
			return;
		}

		if ($order->get_payment_method() !== CardGateway::ID) {
			return;
		}

		// Avoid creating more schedules
		if ((int) $order->get_meta(OrderMetaManager::META_RECURRING_PARENT_ORDER_ID, true) > 0) {
			return;
		}

		if (!empty(self::getSchedules($order))) {
			return;
		}

		$groups = self::groupRecurringItems($order);
		if (empty($groups)) {
			return;
		}

		$token = self::findRecurringToken($order);
		$sourceId = '';
		$tokenId = 0;

		if ($token instanceof WC_Payment_Token_CC) {
			$sourceId = $token->get_token();
			$tokenId = $token->get_id();
		} else {
			$useCof = (string) $order->get_meta('_wipop_use_cof', true);
			if ($useCof === 'yes') {
				$sourceId = (string) $order->get_meta('_wipop_card_id', true);
			}
		}

		if ($sourceId === '') {
			$order->add_order_note(__('Wipop: no se programaron cobros recurrentes porque el cliente no tiene tarjeta guardada.', 'wipop'));
			Logger::log(
				sprintf('Recurring payments skipped on order %s: missing token or source id', $order->get_id()),
				'warning'
			);

			return;
		}

		$schedules = [];
		$paidAt = $order->get_date_paid();
		$timestamp = $paidAt instanceof WC_DateTime ? $paidAt->getTimestamp() : time();

		foreach ($groups as $period => $data) {
			$totalPeriodAmount = (float) $data['amount'];

			if ($totalPeriodAmount <= 0) {
				continue;
			}

			$firstRunTimestamp = self::calculateFirstMITChargeTimestamp($timestamp, $period);

			$schedules[$period] = RecurringSchedule::create(
				$period,
				$totalPeriodAmount,
				$order->get_currency(),
				$firstRunTimestamp,
				$tokenId,
				$sourceId,
				$data['item_ids'],
				$data['product_ids']
			);

			self::queueEvent($order->get_id(), $period, $firstRunTimestamp);
		}

		if (empty($schedules)) {
			return;
		}

		self::persistSchedules($order, $schedules);

		$labels = [];

		foreach (array_keys($schedules) as $periodUsed) {
			$labels[] = self::formatPeriodLabel($periodUsed);
		}

		$order->add_order_note(sprintf(
			__('Wipop: programados cobros recurrentes (%s).', 'wipop'),
			implode(', ', $labels)
		));
	}

	/**
	 * Executed on scheduled cron
	 */
	public static function handleScheduledCharge(int $orderId, string $period): void
	{
		$order = wc_get_order($orderId);
		if (!$order instanceof WC_Order) {
			return;
		}

		$schedules = self::getSchedules($order);
		$periodSchedule = $schedules[$period] ?? null;

		if (!$periodSchedule || !$periodSchedule->isActive()) {
			self::unscheduleEvent($orderId, $period);

			return;
		}

		if ($order->has_status([WCOrderStatus::CANCELLED, WCOrderStatus::REFUNDED, WCOrderStatus::FAILED])) {
			self::cancelScheduleEntryForPeriodAndOrder(
				$order,
				$period,
				__('Wipop: detuvimos los cobros recurrentes porque el pedido ya no está activo.', 'wipop')
			);

			return;
		}

		$sequence = $periodSchedule->cycleNumber();
		$anchor = $periodSchedule->cycleStartTimestamp();

		if ($anchor <= 0 || $sequence <= 0) {
			self::cancelScheduleEntryForPeriodAndOrder(
				$order,
				$period,
				__('Wipop: detuvimos los cobros recurrentes porque el fallo en la programación.', 'wipop')
			);

			return;
		}

		$officialDueDateTimestamp = self::calculateChargeTimestampFromPaymentDateAnchor($anchor, $period, $sequence);
		$periodSchedule = $periodSchedule->withCurrentCycleDueTimestamp($officialDueDateTimestamp);

		// In case original due date wasn't meet (e.g. retry attempts)
		$nextCharge = $periodSchedule->nextAttemptTimestamp();

		if ($nextCharge <= 0) {
			$nextCharge = $officialDueDateTimestamp;
			$periodSchedule = $periodSchedule->withNextAttemptTimestamp($nextCharge);
		}

		$now = time();

		// Secure check to avoid making payments from outdated cron
		if ($nextCharge - $now > self::MINUTE_IN_SECONDS) {
			self::saveScheduleEntry($order, $period, $periodSchedule);
			self::queueEvent($orderId, $period, $nextCharge);

			return;
		}

		$sourceId = self::resolveSourceIdFromSavedSchedule($periodSchedule, $order);
		if ($sourceId === '') {
			self::cancelScheduleEntryForPeriodAndOrder(
				$order,
				$period,
				__('Wipop: no encontramos la tarjeta guardada del cliente para continuar con los cobros recurrentes.', 'wipop')
			);

			return;
		}

		$chargedAmount = $periodSchedule->amount();
		if ($chargedAmount <= 0.0) {
			self::cancelScheduleEntryForPeriodAndOrder(
				$order,
				$period,
				__('Wipop: detuvimos los cobros recurrentes porque el importe configurado es 0.', 'wipop')
			);

			return;
		}

		self::startScheduledCharge(
			$order,
			$period,
			$periodSchedule,
			$sequence,
			$sourceId,
			$chargedAmount
		);
	}

	public static function maybeHandleRecurringWebhookFromRenewalOrder(
		WC_Order $parentOrder,
		WC_Order $renewalOrder,
		Transaction $transaction
	): bool {
		$period = (string) $renewalOrder->get_meta(self::META_PERIOD, true);
		if (!self::isValidPeriod($period)) {
			return false;
		}

		$sequence = (int) $renewalOrder->get_meta(self::ORDER_META_SEQUENCE, true);
		if ($sequence <= 0) {
			return false;
		}

		$schedules = self::getSchedules($parentOrder);
		$periodSchedule = $schedules[$period] ?? null;
		if (!$periodSchedule instanceof RecurringSchedule || !$periodSchedule->isActive()) {
			return false;
		}

		// Ignore old/lost cycles
		if ($periodSchedule->cycleNumber() !== $sequence) {
			return true;
		}

		$status = $transaction->status;
		if (!$status instanceof TransactionStatus) {
			return true;
		}
		Logger::log(
			sprintf(
				'Webhook recurring status %s.',
				$status->value
			)
		);
		switch ($status) {
			case TransactionStatus::COMPLETED:
				self::handleRecurringWebhookSuccess($parentOrder, $period, $periodSchedule, $transaction);

				return true;
			case TransactionStatus::FAILED:
			case TransactionStatus::ERROR:
				self::handleRecurringWebhookFailure($parentOrder, $period, $transaction);

				return true;
			case TransactionStatus::IN_PROGRESS:
			case TransactionStatus::CHARGE_PENDING:
				return true;
		}
	}

	public static function maybeHandleRecurringWebhook(WC_Order $order, Transaction $transaction): bool
	{
		$transactionId = (string) ($transaction->id ?? '');
		$gatewayOrderId = (string) ($transaction->orderId ?? '');

		if ($transactionId === '' && $gatewayOrderId === '') {
			return false;
		}

		$schedules = self::getSchedules($order);
		if (empty($schedules)) {
			return false;
		}

		foreach ($schedules as $period => $periodSchedule) {
			if (!$periodSchedule->isActive()) {
				continue;
			}

			$matchesExpectedGatewayOrder = false;

			if ($gatewayOrderId !== '') {
				$expectedGatewayOrderId = (string) OrderIdFactory::forRecurring(
					$order,
					(string) $period,
					$periodSchedule->cycleNumber()
				);
				$matchesExpectedGatewayOrder = $gatewayOrderId === $expectedGatewayOrderId;
			}

			if (!$matchesExpectedGatewayOrder) {
				continue;
			}

			$status = $transaction->status;
			if (!$status instanceof TransactionStatus) {
				Logger::log(
					sprintf(
						'Recurring webhook received with unknown status for order %s',
						$order->get_id()
					),
					'warning',
					[
						'wc_order_id' => $order->get_id(),
						'period' => (string) $period,
						'transaction_id' => $transactionId,
						'gateway_order_id' => $gatewayOrderId,
					]
				);

				return true;
			}

			switch ($status) {
				case TransactionStatus::COMPLETED:
					self::handleRecurringWebhookSuccess($order, (string) $period, $periodSchedule, $transaction);

					return true;
				case TransactionStatus::FAILED:
				case TransactionStatus::ERROR:
					self::handleRecurringWebhookFailure($order, (string) $period, $transaction);

					return true;
				case TransactionStatus::IN_PROGRESS:
				case TransactionStatus::CHARGE_PENDING:
					// Webhook should bring final states.
					return true;
			}
		}

		return false;
	}

	public static function cancelOrderSchedulesOnStateChange(int $orderId): void
	{
		$order = wc_get_order($orderId);
		if (!$order instanceof WC_Order) {
			return;
		}

		$schedules = self::getSchedules($order);
		if (empty($schedules)) {
			return;
		}

		foreach (array_keys($schedules) as $period) {
			self::unscheduleEvent($order->get_id(), (string) $period);
		}

		$order->delete_meta_data(self::ORDER_META_SCHEDULE);
		$order->save();

		$order->add_order_note(__('Wipop: cancelamos los cobros recurrentes porque el pedido cambió de estado.', 'wipop'));
	}

	public static function handleOrderDeletion(int $postId): void
	{
		$order = wc_get_order($postId);
		if (!$order instanceof WC_Order) {
			return;
		}

		$schedules = self::getSchedules($order);
		if (empty($schedules)) {
			return;
		}

		foreach (array_keys($schedules) as $period) {
			self::unscheduleEvent($order->get_id(), (string) $period);
		}
	}

	public static function displayMetaKey(string $displayKey, $meta, $item): string
	{
		$key = (is_object($meta) && isset($meta->key) && is_string($meta->key))
			? $meta->key
			: '';

		return match ($key) {
			self::META_ENABLED => __('Recurrencia', 'wipop'),
			self::META_PERIOD => __('Recurrencia de pagos', 'wipop'),
			default => $displayKey,
		};
	}

	public static function displayMetaValue($displayValue, $meta, $item)
	{
		$key = (is_object($meta) && isset($meta->key) && is_string($meta->key))
			? $meta->key
			: '';
		$value = (is_object($meta) && isset($meta->value) && is_string($meta->value))
			? $meta->value
			: '';

		if ($key === self::META_ENABLED) {
			return $value === self::META_ENABLED_YES ? __('Sí', 'wipop') : __('No', 'wipop');
		}

		if ($key === self::META_PERIOD) {
			return match ($value) {
				self::PERIOD_MONTHLY => __('mensual', 'wipop'),
				self::PERIOD_YEARLY => __('anual', 'wipop'),
				default => $displayValue,
			};
		}

		return $displayValue;
	}

	private static function startScheduledCharge(
		WC_Order $order,
		string $period,
		RecurringSchedule $periodSchedule,
		int $sequence,
		string $sourceId,
		float $chargedAmount
	): void {
		try {
			$client = ClientFactory::create();
		} catch (ClientConfigurationException $exception) {
			Logger::log(
				sprintf(
					'Configuration error: recurring charge cancelled on order %s: %s',
					$order->get_id(),
					$exception->getMessage()
				),
				'error'
			);

			self::handleChargeFailure($order, $period, $exception->getMessage());

			return;
		}

		$wipopOrderId = OrderIdFactory::forRecurring($order, $period, $sequence);
		$wipopGatewayOrderId = (string) $wipopOrderId;

		$renewalOrder = RecurringRenewalOrderFactory::findOrCreate(
			$order,
			$periodSchedule,
			$chargedAmount,
			$period,
			$sequence,
			$wipopGatewayOrderId
		);
		if (!$renewalOrder instanceof WC_Order) {
			self::handleChargeFailure($order, $period, __('No se pudo crear el pedido recurrente.', 'wipop'));

			return;
		}

		$existingTransactionId = (string) $renewalOrder->get_meta(OrderMetaManager::META_TRANSACTION_ID, true);
		if ($existingTransactionId !== '' && !$renewalOrder->has_status([WCOrderStatus::FAILED])) {
			Logger::log(
				sprintf(
					'Recurring charge already started for order %s (%s #%d), waiting for webhook payment notice (transaction %s).',
					$order->get_id(),
					$period,
					$sequence,
					$existingTransactionId
				),
				'info',
				[
					'wc_order_id' => $order->get_id(),
					'period' => $period,
					'sequence' => $sequence,
					'transaction_id' => $existingTransactionId,
				]
			);

			return;
		}

		$params = ChargeRequestFactory::build(
			$renewalOrder,
			ChargeMethod::CARD,
			home_url('/'),
			true,
			$wipopOrderId
		);

		$params
			->amount($chargedAmount)
			->originChannel(OriginChannel::API)
			->sendEmail(false)
			->sourceId($sourceId)
			->useCof(true)
			->postType(new PostType(PostTypeMode::RECURRENT))
		;

		$order->update_meta_data('_wipop_use_cof', 'yes');

		$customerId = ChargeRequestFactory::resolveWipopCustomerId($renewalOrder, (int) $renewalOrder->get_user_id());

		try {
			$charge = SdkCaller::call(
				'charge.create',
				static fn () => $client->chargeOperation()->create($params, $customerId)
			);
		} catch (Throwable $throwable) {
			self::failScheduledCharge($order, $period, $throwable->getMessage(), $renewalOrder);

			return;
		}

		$status = $charge->status;
		if ($status instanceof TransactionStatus && in_array($status, [TransactionStatus::FAILED, TransactionStatus::ERROR], true)) {
			$detail = trim((string) ($charge->errorMessage ?? ''));

			self::failScheduledCharge(
				$order,
				$period,
				$detail !== '' ? $detail : __('Cargo fallido.', 'wipop'),
				$renewalOrder
			);

			return;
		}

		$transactionId = (string) ($charge->id ?? '');
		$gatewayOrderId = (string) ($charge->orderId ?? '');

		if ($transactionId === '' && $gatewayOrderId === '') {
			self::failScheduledCharge(
				$order,
				$period,
				__('Cargo fallido.', 'wipop'),
				$renewalOrder
			);

			return;
		}

		$gatewayOrderId = $gatewayOrderId !== '' ? $gatewayOrderId : $wipopGatewayOrderId;
		$renewalOrder->update_meta_data(OrderMetaManager::META_GATEWAY_ORDER_ID, $gatewayOrderId);

		if ($transactionId !== '') {
			$renewalOrder->set_transaction_id($transactionId);
			$renewalOrder->update_meta_data(OrderMetaManager::META_TRANSACTION_ID, $transactionId);
		}

		$renewalOrder->save();

		// persist schedule timestamps: schedule will advance only after webhook payment confirmation
		self::saveScheduleEntry($order, $period, $periodSchedule);

		$reference = $transactionId !== '' ? $transactionId : $gatewayOrderId;
		$note = sprintf(
			__('Wipop: cargo recurrente %1$s #%2$d enviado. Referencia: %3$s. Importe: %4$s. Esperando confirmación del pago.', 'wipop'),
			self::formatPeriodLabel($period),
			$sequence,
			$reference,
			wc_price($chargedAmount, ['currency' => $order->get_currency()])
		);

		$renewalOrder->add_order_note($note);
		$order->add_order_note($note);
	}

	private static function failScheduledCharge(WC_Order $order, string $period, string $message, ?WC_Order $renewalOrder = null): void
	{
		if ($renewalOrder instanceof WC_Order) {
			$renewalOrder->update_status(WCOrderStatus::FAILED, $message);
		}

		self::handleChargeFailure($order, $period, $message);
	}

	private static function handleRecurringWebhookSuccess(
		WC_Order $order,
		string $period,
		RecurringSchedule $periodSchedule,
		Transaction $transaction
	): void {
		$sequence = $periodSchedule->cycleNumber();
		$anchor = $periodSchedule->cycleStartTimestamp();

		if ($anchor <= 0 || $sequence <= 0) {
			self::cancelScheduleEntryForPeriodAndOrder(
				$order,
				$period,
				__('Wipop: detuvimos los cobros recurrentes porque el fallo en la programación.', 'wipop')
			);

			return;
		}

		$chargedAmount = $transaction->amount !== null ? (float) $transaction->amount : $periodSchedule->amount();
		if ($chargedAmount <= 0.0) {
			self::cancelScheduleEntryForPeriodAndOrder(
				$order,
				$period,
				__('Wipop: detuvimos los cobros recurrentes porque el importe configurado es 0.', 'wipop')
			);

			return;
		}

		$transactionId = (string) ($transaction->id ?? '');
		$now = time();

		$nextDue = self::calculateChargeTimestampFromPaymentDateAnchor($anchor, $period, $sequence + 1);
		$periodSchedule = $periodSchedule->markSuccessfulCharge($nextDue, $now, $transactionId);

		self::saveScheduleEntry($order, $period, $periodSchedule);
		self::queueEvent($order->get_id(), $period, $periodSchedule->nextAttemptTimestamp());

		$order->add_order_note(sprintf(
			__('Wipop: cargo recurrente %1$s #%2$d confirmado. Transacción: %3$s. Importe: %4$s.', 'wipop'),
			self::formatPeriodLabel($period),
			$sequence,
			$transactionId,
			wc_price($chargedAmount, ['currency' => $order->get_currency()])
		));
	}

	private static function handleRecurringWebhookFailure(
		WC_Order $order,
		string $period,
		Transaction $transaction
	): void {
		$details = trim((string) ($transaction->errorMessage ?? ''));
		if (!empty($transaction->errorCode)) {
			$details = trim(sprintf('%s: %s', $details, $transaction->errorCode));
		}

		self::handleChargeFailure($order, $period, $details !== '' ? $details : __('Cargo fallido.', 'wipop'));
	}

	/**
	 * @return array<string, array{amount: float, item_ids: array<int>, product_ids: array<int>}>
	 */
	private static function groupRecurringItems(WC_Order $order): array
	{
		$groups = [];

		/** @var WC_Order_Item_Product $item */
		foreach ($order->get_items('line_item') as $item) {
			$enabled = (string) $item->get_meta(self::META_ENABLED, true);
			$period = (string) $item->get_meta(self::META_PERIOD, true);

			if ($enabled !== self::META_ENABLED_YES || !self::isValidPeriod($period)) {
				continue;
			}

			$chargedAmount = (float) $item->get_total() + (float) $item->get_total_tax();
			if ($chargedAmount <= 0) {
				continue;
			}

			if (!isset($groups[$period])) {
				$groups[$period] = [
					'amount' => 0.0,
					'item_ids' => [],
					'product_ids' => [],
				];
			}

			$groups[$period]['amount'] += $chargedAmount;
			$groups[$period]['item_ids'][] = $item->get_id();
			$groups[$period]['product_ids'][] = $item->get_product_id();
		}

		return $groups;
	}

	/**
	 * Anchor -> CIT charge date
	 */
	private static function calculateFirstMITChargeTimestamp(int $anchorTimestamp, string $period): int
	{
		return self::calculateChargeTimestampFromPaymentDateAnchor($anchorTimestamp, $period, 2);
	}

	private static function isValidPeriod(string $period): bool
	{
		return in_array($period, [self::PERIOD_MONTHLY, self::PERIOD_YEARLY], true);
	}

	private static function findRecurringToken(WC_Order $order): ?WC_Payment_Token_CC
	{
		$userId = $order->get_user_id();

		$tokens = WC_Payment_Tokens::get_customer_tokens($userId, CardGateway::ID);

		if (empty($tokens)) {
			return null;
		}

		$usedCard = (string) $order->get_meta('_wipop_card_id', true);

		if ($usedCard !== '') {
			foreach ($tokens as $token) {
				if ($token instanceof WC_Payment_Token_CC && $token->get_token() === $usedCard) {
					return $token;
				}
			}
		}

		$token = reset($tokens);

		return $token instanceof WC_Payment_Token_CC ? $token : null;
	}

	private static function resolveSourceIdFromSavedSchedule(RecurringSchedule $periodSchedule, WC_Order $order): string
	{
		$sourceId = $periodSchedule->sourceId();
		if ($sourceId !== '') {
			return $sourceId;
		}

		$tokenId = $periodSchedule->tokenId();
		if ($tokenId > 0) {
			$token = WC_Payment_Tokens::get($tokenId);
			if ($token instanceof WC_Payment_Token_CC) {
				return $token->get_token();
			}
		}

		$fallback = self::findRecurringToken($order);

		return $fallback instanceof WC_Payment_Token_CC ? $fallback->get_token() : '';
	}

	private static function formatPeriodLabel(string $period): string
	{
		return match ($period) {
			self::PERIOD_YEARLY => __('anual', 'wipop'),
			self::PERIOD_MONTHLY => __('mensual', 'wipop'),
			default => __('desconocido', 'wipop'),
		};
	}

	private static function queueEvent(int $orderId, string $period, int $timestamp): void
	{
		if ($timestamp <= time()) {
			$timestamp = time() + self::MINUTE_IN_SECONDS;
		}

		$existing = wp_next_scheduled(self::CRON_HOOK, [$orderId, $period]);
		if ($existing !== false) {
			wp_unschedule_event($existing, self::CRON_HOOK, [$orderId, $period]);
		}

		wp_schedule_single_event($timestamp, self::CRON_HOOK, [$orderId, $period]);
	}

	private static function unscheduleEvent(int $orderId, string $period): void
	{
		$existing = wp_next_scheduled(self::CRON_HOOK, [$orderId, $period]);

		if ($existing !== false) {
			wp_unschedule_event($existing, self::CRON_HOOK, [$orderId, $period]);
		}
	}

	private static function persistSchedules(WC_Order $order, array $schedules): void
	{
		if (empty($schedules)) {
			$order->delete_meta_data(self::ORDER_META_SCHEDULE);
		} else {
			$data = [];
			/** @var array<string, RecurringSchedule> $schedules */
			foreach ($schedules as $period => $schedule) {
				$data[$period] = $schedule->toArray();
			}

			$order->update_meta_data(self::ORDER_META_SCHEDULE, $data);
		}

		$order->save();
	}

	private static function saveScheduleEntry(WC_Order $order, string $period, RecurringSchedule $periodSchedule): void
	{
		$schedules = self::getSchedules($order);
		$schedules[$period] = $periodSchedule;
		self::persistSchedules($order, $schedules);
	}

	/**
	 * @return array<string, RecurringSchedule>
	 */
	private static function getSchedules(WC_Order $order): array
	{
		$meta = $order->get_meta(self::ORDER_META_SCHEDULE, true);

		if (!is_array($meta)) {
			return [];
		}

		$schedules = [];

		foreach ($meta as $period => $data) {
			if (!is_array($data)) {
				continue;
			}

			$schedule = RecurringSchedule::fromArray($data);
			if ($schedule instanceof RecurringSchedule) {
				$schedules[(string) $period] = $schedule;
			}
		}

		return $schedules;
	}

	private static function cancelScheduleEntryForPeriodAndOrder(WC_Order $order, string $period, string $note = ''): void
	{
		$schedules = self::getSchedules($order);

		self::unscheduleEvent($order->get_id(), $period);
		if (isset($schedules[$period])) {
			unset($schedules[$period]);
			self::persistSchedules($order, $schedules);
		}

		if (!empty($note)) {
			$order->add_order_note($note);
		}
	}

	private static function handleChargeFailure(WC_Order $order, string $period, string $message): void
	{
		self::cancelScheduleEntryForPeriodAndOrder(
			$order,
			$period,
			sprintf(
				__('Wipop: detuvimos los cobros recurrentes (%1$s) error: %2$s.', 'wipop'),
				self::formatPeriodLabel($period),
				$message
			)
		);
	}

	private static function calculateChargeTimestampFromPaymentDateAnchor(int $anchor, string $period, int $sequence): int
	{
		$sequence = max(1, $sequence);
		$offset = $sequence - 1;
		if ($offset === 0) {
			return $anchor;
		}

		if (!self::isValidPeriod($period)) {
			return $anchor;
		}

		$timezone = wp_timezone();
		$date = (new DateTimeImmutable('@' . $anchor))->setTimezone($timezone);
		$interval = match ($period) {
			self::PERIOD_YEARLY => new DateInterval('P' . $offset . 'Y'),
			self::PERIOD_MONTHLY => new DateInterval('P' . $offset . 'M'),
			default => new DateInterval('P' . $offset . 'Y')
		};

		return $date->add($interval)->getTimestamp();
	}
}
