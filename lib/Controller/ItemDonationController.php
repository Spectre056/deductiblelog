<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Controller;

use OCA\DeductibleLog\AppInfo\Application;
use OCA\DeductibleLog\Service\ItemDonationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ItemDonationController extends Controller {

    public function __construct(
        IRequest $request,
        private ItemDonationService $service,
        private string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): JSONResponse {
        $taxYear   = (int) ($this->request->getParam('tax_year') ?: date('Y'));
        $donations = $this->service->findAll($this->userId, $taxYear);
        $total     = $this->service->yearTotal($this->userId, $taxYear);

        return new JSONResponse([
            'status'   => 'ok',
            'tax_year' => $taxYear,
            'total'    => $total,
            'data'     => $donations,
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function create(): JSONResponse {
        $data = $this->request->getParams();

        if (empty($data['charity_id'])) {
            return new JSONResponse(['status' => 'error', 'message' => 'charity_id is required'], Http::STATUS_BAD_REQUEST);
        }
        if (empty($data['date'])) {
            return new JSONResponse(['status' => 'error', 'message' => 'date is required'], Http::STATUS_BAD_REQUEST);
        }
        if (empty($data['lines']) || !is_array($data['lines'])) {
            return new JSONResponse(['status' => 'error', 'message' => 'At least one line item is required'], Http::STATUS_BAD_REQUEST);
        }
        if (empty($data['tax_year'])) {
            $data['tax_year'] = (int) substr($data['date'], 0, 4);
        }

        $donation = $this->service->create($this->userId, $data);
        return new JSONResponse(['status' => 'ok', 'data' => $donation], Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function update(int $id): JSONResponse {
        try {
            $data     = $this->request->getParams();
            $donation = $this->service->update($id, $this->userId, $data);
            return new JSONResponse(['status' => 'ok', 'data' => $donation]);
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
