<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Controller;

use OCA\DeductibleLog\AppInfo\Application;
use OCA\DeductibleLog\Service\ReceiptService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ReceiptController extends Controller {

    public function __construct(
        IRequest $request,
        private ReceiptService $service,
        private string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): JSONResponse {
        $entityType = (string) ($this->request->getParam('entity_type') ?? '');
        $entityId   = (int) ($this->request->getParam('entity_id') ?? 0);

        if ($entityType === '' || $entityId === 0) {
            return new JSONResponse(['status' => 'error', 'message' => 'entity_type and entity_id are required'], Http::STATUS_BAD_REQUEST);
        }

        $receipts = $this->service->findByEntity($entityType, $entityId, $this->userId);
        return new JSONResponse([
            'status' => 'ok',
            'data'   => array_map(fn($r) => $r->jsonSerialize(), $receipts),
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function upload(): JSONResponse {
        $entityType = (string) ($this->request->getParam('entity_type') ?? '');
        $entityId   = (int) ($this->request->getParam('entity_id') ?? 0);
        $taxYear    = (int) ($this->request->getParam('tax_year') ?: date('Y'));
        $file       = $this->request->getUploadedFile('file');

        if ($entityType === '' || $entityId === 0) {
            return new JSONResponse(['status' => 'error', 'message' => 'entity_type and entity_id are required'], Http::STATUS_BAD_REQUEST);
        }
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return new JSONResponse(['status' => 'error', 'message' => 'File upload failed'], Http::STATUS_BAD_REQUEST);
        }
        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            return new JSONResponse(['status' => 'error', 'message' => 'File exceeds 10 MB limit'], Http::STATUS_BAD_REQUEST);
        }

        $content = file_get_contents($file['tmp_name']);
        $receipt = $this->service->upload($this->userId, $entityType, $entityId, $file['name'], $content, $taxYear);

        return new JSONResponse(['status' => 'ok', 'data' => $receipt->jsonSerialize()], Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function show(int $id): Http\Response {
        try {
            [$content, $mimeType, $filename] = $this->service->download($id, $this->userId);
            return new DataDownloadResponse($content, $filename, $mimeType);
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
