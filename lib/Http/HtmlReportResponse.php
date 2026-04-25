<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Http;

use OCP\AppFramework\Http\Response;

class HtmlReportResponse extends Response {
    public function __construct(private string $html) {
        parent::__construct();
        $this->addHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function render(): string {
        return $this->html;
    }
}
