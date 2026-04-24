<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Controller;

use OCA\DeductibleLog\AppInfo\Application;
use OCA\DeductibleLog\Service\MileageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class MileageController extends Controller {

    public function __construct(
        IRequest $request,
        private MileageService $service,
        private string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): JSONResponse {
        $taxYear = (int) ($this->request->getParam('tax_year') ?: date('Y'));
        $logs    = $this->service->findAll($this->userId, $taxYear);
        $totals  = $this->service->yearTotals($this->userId, $taxYear);

        return new JSONResponse([
            'status'   => 'ok',
            'tax_year' => $taxYear,
            'total'    => $totals['deduction'],
            'miles'    => $totals['miles'],
            'data'     => array_map(fn($l) => $l->jsonSerialize(), $logs),
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function rates(): JSONResponse {
        return new JSONResponse([
            'status' => 'ok',
            'data'   => $this->service->allRates(),
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function create(): JSONResponse {
        $data = $this->request->getParams();

        if (empty($data['date'])) {
            return new JSONResponse(['status' => 'error', 'message' => 'date is required'], Http::STATUS_BAD_REQUEST);
        }
        if (empty($data['purpose_type'])) {
            return new JSONResponse(['status' => 'error', 'message' => 'purpose_type is required'], Http::STATUS_BAD_REQUEST);
        }
        if (!isset($data['miles']) || (float) $data['miles'] <= 0) {
            return new JSONResponse(['status' => 'error', 'message' => 'miles must be greater than 0'], Http::STATUS_BAD_REQUEST);
        }
        if (empty($data['tax_year'])) {
            $data['tax_year'] = (int) substr($data['date'], 0, 4);
        }

        $log = $this->service->create($this->userId, $data);
        return new JSONResponse(['status' => 'ok', 'data' => $log->jsonSerialize()], Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function update(int $id): JSONResponse {
        try {
            $data = $this->request->getParams();
            $log  = $this->service->update($id, $this->userId, $data);
            return new JSONResponse(['status' => 'ok', 'data' => $log->jsonSerialize()]);
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
