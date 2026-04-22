<?php

declare(strict_types=1);

use OCA\DeductibleLog\AppInfo\Application;

\OCP\Util::addScript(Application::APP_ID, 'deductiblelog-main');
\OCP\Util::addStyle(Application::APP_ID, 'deductiblelog-main');
?>

<div id="app"></div>
