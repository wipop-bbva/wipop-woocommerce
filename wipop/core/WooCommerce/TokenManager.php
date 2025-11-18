<?php

declare(strict_types=1);

namespace WipopWC\Core\WooCommerce;

use WC_Order;
use WC_Payment_Token_CC;
use WC_Payment_Tokens;
use Wipop\Charge\ChargeMethod;
use Wipop\Domain\Card;
use Wipop\Domain\Transaction;
use WipopWC\Gateways\Card\Gateway as CardGateway;

use function strtolower;
use function substr;
use function trim;

defined('ABSPATH') || exit;

final class TokenManager
{
	public static function tryStoreCardToken(WC_Order $order, Transaction $transaction): void
	{
		$userId = $order->get_user_id();
		if ($userId <= 0) {
			return;
		}

		if (($transaction->method ?? '') !== ChargeMethod::CARD) {
			return;
		}

		$card = $transaction->card;
		if ($card === null || empty($card->id)) {
			return;
		}

		$token = self::findExistingToken($userId, $card->id);

		if (!$token instanceof WC_Payment_Token_CC) {
			$token = new WC_Payment_Token_CC();
			$token->set_token($card->id);
			$token->set_gateway_id(CardGateway::ID);
			$token->set_user_id($userId);
		}

		self::populateTokenMeta($token, $card);

		$token->save();

		$order->add_payment_token($token);
	}

	private static function findExistingToken(int $userId, string $cardId): ?WC_Payment_Token_CC
	{
		$tokens = WC_Payment_Tokens::get_customer_tokens($userId, CardGateway::ID);

		foreach ($tokens as $token) {
			if (!$token instanceof WC_Payment_Token_CC) {
				continue;
			}

			if ($token->get_token() === $cardId) {
				return $token;
			}
		}

		return null;
	}

	private static function populateTokenMeta(WC_Payment_Token_CC $token, Card $card): void
	{
		if (!empty($card->brand)) {
			$token->set_card_type(strtolower($card->brand));
		}

		$masked = trim($card->cardNumber ?? $card->number ?? '');
		if ($masked !== '') {
			$token->set_last4(substr($masked, -4));
		}

		if (!empty($card->expirationMonth)) {
			$token->set_expiry_month($card->expirationMonth);
		}

		if (!empty($card->expirationYear)) {
			$token->set_expiry_year($card->expirationYear);
		}
	}
}
