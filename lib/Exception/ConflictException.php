<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Exception;

use OCP\AppFramework\Http;

class ConflictException extends ApiException {

    public function __construct(string $message, array $details = []) {
        parent::__construct($message, Http::STATUS_CONFLICT, $details);
    }
}
