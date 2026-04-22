<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\AppInfo;

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
        // Services and middleware registered here in future phases
    }

    public function boot(IBootContext $context): void {
        // Boot logic here in future phases
    }
}
