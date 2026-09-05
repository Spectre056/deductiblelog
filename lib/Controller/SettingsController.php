<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Controller;

use OCA\DeductibleLog\AppInfo\Application;
use OCA\DeductibleLog\Exception\ValidationException;
use OCA\DeductibleLog\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class SettingsController extends Controller {
    use ControllerHelpers;

    public function __construct(
        IRequest $request,
        private SettingsService $service,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): JSONResponse {
        return new JSONResponse(['status' => 'ok', 'settings' => $this->service->get($this->uid())]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function update(): JSONResponse {
        return new JSONResponse(['status' => 'ok', 'settings' => $this->service->save($this->uid(), $this->request->getParams())]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function checkUpdates(): JSONResponse {
        $result = $this->service->checkUpdates($this->uid());
        if (isset($result['error'])) {
            return new JSONResponse(['status' => 'error', 'message' => $result['error']], Http::STATUS_BAD_GATEWAY);
        }
        return new JSONResponse(['status' => 'ok'] + $result);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function applyUpdates(): JSONResponse {
        $data = $this->request->getParams();
        if (empty($data['updates']) || !is_array($data['updates'])) {
            throw new ValidationException(['updates' => 'updates array required']);
        }
        $this->service->applyUpdates($this->uid(), $data['updates']);
        return new JSONResponse(['status' => 'ok']);
    }
}
