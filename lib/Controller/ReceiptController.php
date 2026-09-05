<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Controller;

use OCA\DeductibleLog\AppInfo\Application;
use OCA\DeductibleLog\Exception\ValidationException;
use OCA\DeductibleLog\Service\ReceiptService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ReceiptController extends Controller {
    use ControllerHelpers;

    public function __construct(
        IRequest $request,
        private ReceiptService $service,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): JSONResponse {
        $entityType = (string) ($this->request->getParam('entity_type') ?? '');
        $entityId   = (int) ($this->request->getParam('entity_id') ?? 0);
        $receipts   = $this->service->findByEntity($entityType, $entityId, $this->uid());
        return new JSONResponse(['status' => 'ok', 'data' => array_map(fn($r) => $r->jsonSerialize(), $receipts)]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function upload(): JSONResponse {
        $entityType = (string) ($this->request->getParam('entity_type') ?? '');
        $entityId   = (int) ($this->request->getParam('entity_id') ?? 0);
        $taxYear    = $this->taxYearParam();
        $file       = $this->request->getUploadedFile('file');

        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException(['file' => 'File upload failed']);
        }
        if (($file['size'] ?? 0) > ReceiptService::MAX_BYTES) {
            throw new ValidationException(['file' => 'File exceeds 10 MB limit']);
        }

        $content = (string) file_get_contents($file['tmp_name']);
        $receipt = $this->service->upload($this->uid(), $entityType, $entityId, (string) $file['name'], $content, $taxYear);
        return new JSONResponse(['status' => 'ok', 'data' => $receipt->jsonSerialize()], Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function show(int $id): DataDownloadResponse {
        [$content, $mimeType, $filename] = $this->service->download($id, $this->uid());
        return new DataDownloadResponse($content, $filename, $mimeType);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function destroy(int $id): JSONResponse {
        $this->service->delete($id, $this->uid());
        return new JSONResponse(['status' => 'ok']);
    }
}
