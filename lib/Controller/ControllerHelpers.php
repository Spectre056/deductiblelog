<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Controller;

use OCA\DeductibleLog\Exception\ApiException;
use OCA\DeductibleLog\Service\Validator;
use OCP\AppFramework\Http;

trait ControllerHelpers {

    private function uid(): string {
        if ($this->userId === null || $this->userId === '') {
            throw new ApiException('Not logged in', Http::STATUS_UNAUTHORIZED);
        }
        return $this->userId;
    }

    /** ?tax_year=YYYY, defaulting to the current year; garbage is a 422 rather than year 0. */
    private function taxYearParam(): int {
        $raw = $this->request->getParam('tax_year');
        if ($raw === null || $raw === '') {
            return (int) date('Y');
        }
        if (!preg_match('/^\d{4}$/', (string) $raw) || (int) $raw < Validator::MIN_YEAR) {
            throw new ApiException('tax_year must be a four-digit year', Http::STATUS_UNPROCESSABLE_ENTITY);
        }
        return (int) $raw;
    }
}
