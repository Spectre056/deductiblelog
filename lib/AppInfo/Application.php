<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\AppInfo;

use OCA\DeductibleLog\Middleware\ApiErrorMiddleware;
use OCA\DeductibleLog\Middleware\CsrfMiddleware;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
    public const APP_ID = 'deductiblelog';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerMiddleware(CsrfMiddleware::class);
        $context->registerMiddleware(ApiErrorMiddleware::class);
    }

    public function boot(IBootContext $context): void {
    }
}
