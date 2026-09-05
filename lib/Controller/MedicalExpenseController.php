<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Controller;

use OCA\DeductibleLog\AppInfo\Application;
use OCA\DeductibleLog\Service\MedicalExpenseService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class MedicalExpenseController extends Controller {
    use ControllerHelpers;

    public function __construct(
        IRequest $request,
        private MedicalExpenseService $service,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): JSONResponse {
        $taxYear = $this->taxYearParam();
        $rows    = $this->service->findAll($this->uid(), $taxYear);
        return new JSONResponse([
            'status'   => 'ok',
            'tax_year' => $taxYear,
            'total'    => $this->service->yearTotal($this->uid(), $taxYear),
            'data'     => array_map(fn($r) => $r->jsonSerialize(), $rows),
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function create(): JSONResponse {
        $row = $this->service->create($this->uid(), $this->request->getParams());
        return new JSONResponse(['status' => 'ok', 'data' => $row->jsonSerialize()], Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function update(int $id): JSONResponse {
        $row = $this->service->update($id, $this->uid(), $this->request->getParams());
        return new JSONResponse(['status' => 'ok', 'data' => $row->jsonSerialize()]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function destroy(int $id): JSONResponse {
        $this->service->delete($id, $this->uid());
        return new JSONResponse(['status' => 'ok']);
    }
}
