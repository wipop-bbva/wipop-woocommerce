<?php

declare(strict_types=1);

namespace WipopWC\Core\WooCommerce;

use WC_Order;
use WC_Payment_Token_CC;
use WC_Payment_Tokens;
use Wipop\Domain\Card;
use WipopWC\Gateways\Card\Gateway as CardGateway;

use function str_pad;
use function strlen;
use function strtolower;
use function substr;
use function trim;

defined('ABSPATH') || exit;

final class TokenManager
{
	public static function tryStoreCardToken(WC_Order $order, Card $card): void
	{
		$userId = $order->get_user_id();
		if ($userId <= 0) {
			return;
		}

		if (empty($card->id)) {
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
		} else {
			$token->set_card_type('CC');
		}

		$masked = trim($card->cardNumber ?? $card->number ?? '');
		if ($masked !== '') {
			$token->set_last4(substr($masked, -4));
		}

		if (!empty($card->expirationMonth)) {
			$month = str_pad($card->expirationMonth, 2, '0', STR_PAD_LEFT);
			$token->set_expiry_month($month);
		}

		if (!empty($card->expirationYear)) {
			$year = $card->expirationYear;
			if (strlen($year) === 2) {
				$year = '20' . $year;
			}
			$token->set_expiry_year($year);
		}
	}
}
