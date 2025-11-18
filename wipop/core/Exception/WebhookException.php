<?php

declare(strict_types=1);

namespace WipopWC\Core\Exception;

use RuntimeException;
use Throwable;

defined('ABSPATH') || exit;

class WebhookException extends RuntimeException
{
	private int $statusCode;

	public function __construct(string $message, int $statusCode, ?Throwable $previous = null)
	{
		parent::__construct($message, 0, $previous);
		$this->statusCode = $statusCode;
	}

	public function getStatusCode(): int
	{
		return $this->statusCode;
	}
}
