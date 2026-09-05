<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Controller;

use OCA\DeductibleLog\AppInfo\Application;
use OCA\DeductibleLog\Service\MileageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class MileageController extends Controller {
    use ControllerHelpers;

    public function __construct(
        IRequest $request,
        private MileageService $service,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): JSONResponse {
        $taxYear = $this->taxYearParam();
        $logs    = $this->service->findAll($this->uid(), $taxYear);
        $totals  = $this->service->yearTotals($this->uid(), $taxYear);
        return new JSONResponse([
            'status'     => 'ok',
            'tax_year'   => $taxYear,
            'total'      => $totals['deduction'],
            'miles'      => $totals['miles'],
            'by_purpose' => $totals['by_purpose'],
            'data'       => array_map(fn($l) => $l->jsonSerialize(), $logs),
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function rates(): JSONResponse {
        return new JSONResponse(['status' => 'ok', 'data' => $this->service->allRates()]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function create(): JSONResponse {
        $log = $this->service->create($this->uid(), $this->request->getParams());
        return new JSONResponse(['status' => 'ok', 'data' => $log->jsonSerialize()], Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function update(int $id): JSONResponse {
        $log = $this->service->update($id, $this->uid(), $this->request->getParams());
        return new JSONResponse(['status' => 'ok', 'data' => $log->jsonSerialize()]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function destroy(int $id): JSONResponse {
        $this->service->delete($id, $this->uid());
        return new JSONResponse(['status' => 'ok']);
    }
}
