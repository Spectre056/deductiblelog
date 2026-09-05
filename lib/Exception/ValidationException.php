<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Exception;

use OCP\AppFramework\Http;

class ValidationException extends ApiException {

    /** @param array<string,string> $errors field => message */
    public function __construct(array $errors) {
        parent::__construct(implode('; ', $errors), Http::STATUS_UNPROCESSABLE_ENTITY, ['errors' => $errors]);
    }
}
