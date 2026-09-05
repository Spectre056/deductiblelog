<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Middleware;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\IRequest;

/**
 * The API routes carry #[NoCSRFRequired] so that app-password (Basic auth)
 * clients can reach them, but that attribute also disables Nextcloud's
 * strict-cookie and request-token checks for cookie sessions. This middleware
 * restores those checks for state-changing requests that arrive with a
 * browser session, while leaving cookie-less token-authenticated requests alone.
 */
class CsrfMiddleware extends Middleware {

    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private IRequest $request) {}

    public function beforeController(Controller $controller, string $methodName): void {
        if (in_array($this->request->getMethod(), self::SAFE_METHODS, true)) {
            return;
        }
        if (!$this->hasSessionCookie()) {
            return;
        }
        if (!$this->request->passesStrictCookieCheck() || !$this->request->passesCSRFCheck()) {
            throw new CsrfException();
        }
    }

    public function afterException(Controller $controller, string $methodName, \Exception $exception): Response {
        if ($exception instanceof CsrfException) {
            return new JSONResponse(
                ['status' => 'error', 'message' => 'CSRF check failed'],
                Http::STATUS_PRECONDITION_FAILED,
            );
        }
        throw $exception;
    }

    private function hasSessionCookie(): bool {
        $name = session_name();
        return ($name !== false && $this->request->getCookie($name) !== null)
            || $this->request->getCookie('nc_token') !== null;
    }
}
