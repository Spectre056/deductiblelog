<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Middleware;

use OCA\DeductibleLog\Exception\ApiException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use Psr\Log\LoggerInterface;

/**
 * Turns the app's own exceptions into JSON error responses with the right
 * status, and logs anything else with context before Nextcloud renders it as a
 * bare 500. Controllers stay free of try/catch boilerplate.
 */
class ApiErrorMiddleware extends Middleware {

    public function __construct(private LoggerInterface $logger) {}

    public function afterException(Controller $controller, string $methodName, \Exception $exception): Response {
        if ($exception instanceof ApiException) {
            return new JSONResponse(
                ['status' => 'error', 'message' => $exception->getMessage()] + $exception->getDetails(),
                $exception->getStatus(),
            );
        }
        if ($exception instanceof DoesNotExistException) {
            return new JSONResponse(['status' => 'error', 'message' => 'Not found'], Http::STATUS_NOT_FOUND);
        }
        $this->logger->error('DeductibleLog {controller}::{method} failed: {message}', [
            'app'        => 'deductiblelog',
            'controller' => get_class($controller),
            'method'     => $methodName,
            'message'    => $exception->getMessage(),
            'exception'  => $exception,
        ]);
        throw $exception;
    }
}
