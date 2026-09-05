<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Exception;

use OCP\AppFramework\Http;

class ApiException extends \RuntimeException {

    public function __construct(
        string $message,
        private int $status = Http::STATUS_BAD_REQUEST,
        private array $details = [],
    ) {
        parent::__construct($message);
    }

    public function getStatus(): int {
        return $this->status;
    }

    public function getDetails(): array {
        return $this->details;
    }
}
