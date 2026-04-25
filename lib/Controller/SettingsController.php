<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Controller;

use OCA\DeductibleLog\AppInfo\Application;
use OCA\DeductibleLog\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class SettingsController extends Controller {

    public function __construct(
        IRequest $request,
        private SettingsService $service,
        private string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): JSONResponse {
        return new JSONResponse([
            'status'   => 'ok',
            'settings' => $this->service->get($this->userId),
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function update(): JSONResponse {
        $data     = $this->request->getParams();
        $settings = $this->service->save($this->userId, $data);
        return new JSONResponse(['status' => 'ok', 'settings' => $settings]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function checkUpdates(): JSONResponse {
        $result = $this->service->checkUpdates($this->userId);
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
            return new JSONResponse(['status' => 'error', 'message' => 'updates array required'], Http::STATUS_BAD_REQUEST);
        }
        $this->service->applyUpdates($this->userId, $data['updates']);
        return new JSONResponse(['status' => 'ok']);
    }
}
