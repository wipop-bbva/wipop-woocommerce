<?php

declare(strict_types=1);

namespace WipopWC\Core\WooCommerce;

use function array_map;

defined('ABSPATH') || exit;

final class RecurringSchedule
{
	private function __construct(
		private string $period,
		private float $amount,
		private string $currency,
		private int $nextAttemptTimestamp,
		private int $currentCycleDueTimestamp,
		private int $cycleStartTimestamp,
		private int $cycleNumber,
		private int $failures,
		private bool $isActive,
		private int $tokenId,
		private string $sourceId,
		private array $itemIds,
		private array $productIds,
		private ?int $lastChargeGmt = null,
		private ?string $lastTransactionId = null
	) {
	}

	/**
	 * @param array<int> $itemIds
	 * @param array<int> $productIds
	 */
	public static function create(
		string $period,
		float $amount,
		string $currency,
		int $firstRunTimestamp,
		int $tokenId,
		string $sourceId,
		array $itemIds,
		array $productIds
	): self {
		return new self(
			$period,
			$amount,
			$currency,
			$firstRunTimestamp,
			$firstRunTimestamp,
			$firstRunTimestamp,
			1,
			0,
			true,
			$tokenId,
			$sourceId,
			array_map('intval', $itemIds),
			array_map('intval', $productIds)
		);
	}

	/**
	 * @param array{
	 *   period?: string,
	 *   amount?: float,
	 *   currency?: string,
	 *   next_attempt_timestamp?: int,
	 *   current_cycle_due_timestamp?: int,
	 *   cycle_start_timestamp?: int,
	 *   cycle_number?: int,
	 *   failures?: int,
	 *   is_active?: string,
	 *   token_id?: int,
	 *   source_id?: string,
	 *   item_ids?: array<int>,
	 *   product_ids?: array<int>,
	 *   last_charge_gmt?: int,
	 *   last_transaction_id?: string
	 * } $data
	 */
	public static function fromArray(array $data): ?self
	{
		$period = (string) ($data['period'] ?? '');
		$cycleStartTimestamp = (int) ($data['cycle_start_timestamp'] ?? 0);
		$cycleNumber = (int) ($data['cycle_number'] ?? 0);

		if ($period === '' || $cycleStartTimestamp <= 0 || $cycleNumber <= 0) {
			return null;
		}

		$currentCycleDue = (int) ($data['current_cycle_due_timestamp'] ?? $cycleStartTimestamp);
		$nextAttempt = (int) ($data['next_attempt_timestamp'] ?? $currentCycleDue);

		return new self(
			$period,
			(float) ($data['amount'] ?? 0.0),
			(string) ($data['currency'] ?? ''),
			$nextAttempt,
			$currentCycleDue,
			$cycleStartTimestamp,
			$cycleNumber,
			(int) ($data['failures'] ?? 0),
			!empty($data['is_active']),
			(int) ($data['token_id'] ?? 0),
			(string) ($data['source_id'] ?? ''),
			array_map('intval', $data['item_ids'] ?? []),
			array_map('intval', $data['product_ids'] ?? []),
			isset($data['last_charge_gmt']) ? (int) $data['last_charge_gmt'] : null,
			isset($data['last_transaction_id']) ? (string) $data['last_transaction_id'] : null
		);
	}

	public function toArray(): array
	{
		return [
			'period' => $this->period,
			'amount' => $this->amount,
			'currency' => $this->currency,
			'next_attempt_timestamp' => $this->nextAttemptTimestamp,
			'current_cycle_due_timestamp' => $this->currentCycleDueTimestamp,
			'cycle_start_timestamp' => $this->cycleStartTimestamp,
			'cycle_number' => $this->cycleNumber,
			'failures' => $this->failures,
			'is_active' => $this->isActive,
			'token_id' => $this->tokenId,
			'source_id' => $this->sourceId,
			'item_ids' => $this->itemIds,
			'product_ids' => $this->productIds,
			'last_charge_gmt' => $this->lastChargeGmt,
			'last_transaction_id' => $this->lastTransactionId,
		];
	}

	public function period(): string
	{
		return $this->period;
	}

	public function amount(): float
	{
		return $this->amount;
	}

	public function currency(): string
	{
		return $this->currency;
	}

	public function nextAttemptTimestamp(): int
	{
		return $this->nextAttemptTimestamp;
	}

	public function currentCycleDueTimestamp(): int
	{
		return $this->currentCycleDueTimestamp;
	}

	public function cycleStartTimestamp(): int
	{
		return $this->cycleStartTimestamp;
	}

	public function cycleNumber(): int
	{
		return $this->cycleNumber;
	}

	public function failures(): int
	{
		return $this->failures;
	}

	public function isActive(): bool
	{
		return $this->isActive;
	}

	public function tokenId(): int
	{
		return $this->tokenId;
	}

	public function sourceId(): string
	{
		return $this->sourceId;
	}

	/**
	 * @return array<int>
	 */
	public function itemIds(): array
	{
		return $this->itemIds;
	}

	/**
	 * @return array<int>
	 */
	public function productIds(): array
	{
		return $this->productIds;
	}

	public function lastChargeGmt(): ?int
	{
		return $this->lastChargeGmt;
	}

	public function lastTransactionId(): ?string
	{
		return $this->lastTransactionId;
	}

	public function withCurrentCycleDueTimestamp(int $timestamp): self
	{
		$clone = clone $this;
		$clone->currentCycleDueTimestamp = $timestamp;

		return $clone;
	}

	public function withNextAttemptTimestamp(int $timestamp): self
	{
		$clone = clone $this;
		$clone->nextAttemptTimestamp = $timestamp;

		return $clone;
	}

	public function deactivate(): self
	{
		$clone = clone $this;
		$clone->isActive = false;

		return $clone;
	}

	public function markSuccessfulCharge(int $nextDueTimestamp, int $chargedAt, string $transactionId): self
	{
		$clone = clone $this;
		$clone->cycleNumber = $clone->cycleNumber + 1;
		$clone->failures = 0;
		$clone->lastChargeGmt = $chargedAt;
		$clone->lastTransactionId = $transactionId;
		$clone->currentCycleDueTimestamp = $nextDueTimestamp;
		$clone->nextAttemptTimestamp = $nextDueTimestamp;

		return $clone;
	}

	public function markFailure(int $retryAt, int $dueAt): self
	{
		$clone = clone $this;
		$clone->failures = $clone->failures + 1;
		$clone->currentCycleDueTimestamp = $dueAt;
		$clone->nextAttemptTimestamp = $retryAt;

		return $clone;
	}
}
