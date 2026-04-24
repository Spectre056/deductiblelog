<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Controller;

use OCA\DeductibleLog\AppInfo\Application;
use OCA\DeductibleLog\Service\MedicalExpenseService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class MedicalExpenseController extends Controller {

    public function __construct(
        IRequest $request,
        private MedicalExpenseService $service,
        private string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): JSONResponse {
        $taxYear  = (int) ($this->request->getParam('tax_year') ?: date('Y'));
        $expenses = $this->service->findAll($this->userId, $taxYear);
        $total    = $this->service->yearTotal($this->userId, $taxYear);

        return new JSONResponse([
            'status'   => 'ok',
            'tax_year' => $taxYear,
            'total'    => $total,
            'data'     => array_map(fn($e) => $e->jsonSerialize(), $expenses),
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function create(): JSONResponse {
        $data = $this->request->getParams();

        if (empty($data['date'])) {
            return new JSONResponse(['status' => 'error', 'message' => 'date is required'], Http::STATUS_BAD_REQUEST);
        }
        if (!isset($data['amount']) || $data['amount'] === '') {
            return new JSONResponse(['status' => 'error', 'message' => 'amount is required'], Http::STATUS_BAD_REQUEST);
        }
        if (empty($data['tax_year'])) {
            $data['tax_year'] = (int) substr($data['date'], 0, 4);
        }

        $expense = $this->service->create($this->userId, $data);
        return new JSONResponse(['status' => 'ok', 'data' => $expense->jsonSerialize()], Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function update(int $id): JSONResponse {
        try {
            $data    = $this->request->getParams();
            $expense = $this->service->update($id, $this->userId, $data);
            return new JSONResponse(['status' => 'ok', 'data' => $expense->jsonSerialize()]);
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
