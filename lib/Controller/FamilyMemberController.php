<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Controller;

use OCA\DeductibleLog\AppInfo\Application;
use OCA\DeductibleLog\Service\FamilyMemberService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class FamilyMemberController extends Controller {

    public function __construct(
        IRequest $request,
        private FamilyMemberService $service,
        private string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): JSONResponse {
        $members = $this->service->findAll($this->userId);
        return new JSONResponse([
            'status' => 'ok',
            'data'   => array_map(fn($m) => $m->jsonSerialize(), $members),
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function create(): JSONResponse {
        $data = $this->request->getParams();

        if (empty($data['name'])) {
            return new JSONResponse(['status' => 'error', 'message' => 'name is required'], Http::STATUS_BAD_REQUEST);
        }
        if (empty($data['relationship'])) {
            return new JSONResponse(['status' => 'error', 'message' => 'relationship is required'], Http::STATUS_BAD_REQUEST);
        }

        $member = $this->service->create($this->userId, $data);
        return new JSONResponse(['status' => 'ok', 'data' => $member->jsonSerialize()], Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function update(int $id): JSONResponse {
        try {
            $data   = $this->request->getParams();
            $member = $this->service->update($id, $this->userId, $data);
            return new JSONResponse(['status' => 'ok', 'data' => $member->jsonSerialize()]);
        } catch (DoesNotExistException) {
            return new JSONResponse(['status' => 'error', 'message' => 'Not found'], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function destroy(int $id): JSONResponse {
        try {
            $this->service->delete($id, $this->userId);
            return new JSONResponse(['status' => 'ok']);
        } catch (DoesNotExistException) {
            return new JSONResponse(['status' => 'error', 'message' => 'Not found'], Http::STATUS_NOT_FOUND);
        }
    }
}
