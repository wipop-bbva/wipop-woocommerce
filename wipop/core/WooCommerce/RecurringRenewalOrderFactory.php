<?php

declare(strict_types=1);

namespace WipopWC\Core\WooCommerce;

use WC_Order;
use WC_Order_Item_Product;
use WipopWC\Core\Logger;
use WipopWC\Gateways\Card\Gateway as CardGateway;

use function __;
use function abs;
use function sprintf;
use function wc_create_order;
use function wc_get_orders;
use function wc_get_product_variation_attributes;

defined('ABSPATH') || exit;

final class RecurringRenewalOrderFactory
{
	public static function findOrCreate(
		WC_Order $parentOrder,
		RecurringSchedule $periodSchedule,
		float $chargedAmount,
		string $period,
		int $sequence,
		string $gatewayOrderId
	): ?WC_Order {
		$existing = self::findExisting($parentOrder, $period, $sequence);
		if ($existing instanceof WC_Order) {
			if ($gatewayOrderId !== '') {
				$existing->update_meta_data(OrderMetaManager::META_GATEWAY_ORDER_ID, $gatewayOrderId);
				OrderMetaManager::addGatewayOrderIdLookup($existing, $gatewayOrderId);
			}
			self::syncWipopCustomerIdFromParent($existing, $parentOrder);
			$existing->update_meta_data(OrderMetaManager::META_RECURRING_PARENT_ORDER_ID, $parentOrder->get_id());
			$existing->update_meta_data(RecurringPayments::META_PERIOD, $period);
			$existing->update_meta_data(RecurringPayments::ORDER_META_SEQUENCE, $sequence);
			$existing->save();

			return $existing;
		}

		$newOrder = wc_create_order([
			'customer_id' => $parentOrder->get_user_id(),
			'parent' => $parentOrder->get_id(),
		]);

		if (!$newOrder instanceof WC_Order) {
			$parentOrder->add_order_note(__('Wipop: no pudimos crear el pedido recurrente.', 'wipop'));
			Logger::log(
				sprintf('No pudimos crear el pedido recurrente para el pedido %s.', $parentOrder->get_id()),
				'error'
			);

			return null;
		}

		$newOrder->set_currency($parentOrder->get_currency());
		$newOrder->set_address($parentOrder->get_address('billing'), 'billing');
		$newOrder->set_address($parentOrder->get_address('shipping'), 'shipping');
		$newOrder->set_created_via('wipop_recurring');
		$newOrder->set_payment_method(CardGateway::ID);
		$newOrder->update_meta_data('_wipop_use_cof', 'yes');
		self::syncWipopCustomerIdFromParent($newOrder, $parentOrder);

		if ($gatewayOrderId !== '') {
			$newOrder->update_meta_data(OrderMetaManager::META_GATEWAY_ORDER_ID, $gatewayOrderId);
			OrderMetaManager::addGatewayOrderIdLookup($newOrder, $gatewayOrderId);
		}

		foreach ($periodSchedule->itemIds() as $itemId) {
			$item = $parentOrder->get_item($itemId);
			if (!$item instanceof WC_Order_Item_Product) {
				continue;
			}

			$product = $item->get_product();
			if (!$product) {
				continue;
			}

			$newItem = new WC_Order_Item_Product();
			$newItem->set_product($product);
			$newItem->set_name($item->get_name());
			$newItem->set_quantity($item->get_quantity());
			$variationId = $item->get_variation_id();
			$newItem->set_variation_id($variationId);
			if ($variationId > 0) {
				$variationAttributes = wc_get_product_variation_attributes($variationId);
				if (!empty($variationAttributes)) {
					$newItem->set_variation($variationAttributes);
				}
			}
			$newItem->set_subtotal($item->get_subtotal());
			$newItem->set_total($item->get_total());
			$newItem->set_subtotal_tax($item->get_subtotal_tax());
			$newItem->set_total_tax($item->get_total_tax());
			$newItem->set_taxes($item->get_taxes());

			$newOrder->add_item($newItem);
		}

		$newOrder->calculate_totals(false);
		if (abs($newOrder->get_total() - $chargedAmount) > 0.01) {
			$newOrder->set_total($chargedAmount);
		}

		$newOrder->update_meta_data(OrderMetaManager::META_RECURRING_PARENT_ORDER_ID, $parentOrder->get_id());
		$newOrder->update_meta_data(RecurringPayments::META_PERIOD, $period);
		$newOrder->update_meta_data(RecurringPayments::ORDER_META_SEQUENCE, $sequence);
		$newOrder->save();

			$newOrder->add_order_note(sprintf(
				// translators: 1: parent order ID, 2: recurring cycle number.
				__('Wipop: pedido recurrente creado desde el pedido %1$s (ciclo nº%2$d).', 'wipop'),
				$parentOrder->get_id(),
				$sequence
			));

		return $newOrder;
	}

	private static function findExisting(WC_Order $parentOrder, string $period, int $sequence): ?WC_Order
	{
		$orders = wc_get_orders([
			'limit' => 1,
			'orderby' => 'date',
			'order' => 'DESC',
			'return' => 'objects',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Internal one-row renewal lookup; replacing it would add unnecessary storage.
			'meta_query' => [
				[
					'key' => OrderMetaManager::META_RECURRING_PARENT_ORDER_ID,
					'value' => (string) $parentOrder->get_id(),
				],
				[
					'key' => RecurringPayments::META_PERIOD,
					'value' => $period,
				],
				[
					'key' => RecurringPayments::ORDER_META_SEQUENCE,
					'value' => (string) $sequence,
				],
			],
		]);

		return !empty($orders) && $orders[0] instanceof WC_Order ? $orders[0] : null;
	}

	private static function syncWipopCustomerIdFromParent(WC_Order $order, WC_Order $parentOrder): void
	{
		$customerId = (string) $parentOrder->get_meta('_wipop_customer_id', true);
		if ($customerId === '') {
			return;
		}

		$order->update_meta_data('_wipop_customer_id', $customerId);
	}
}
