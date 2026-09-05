<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Controller;

use OCA\DeductibleLog\AppInfo\Application;
use OCA\DeductibleLog\Http\HtmlReportResponse;
use OCA\DeductibleLog\Service\CpaReportService;
use OCA\DeductibleLog\Service\ReportService;
use OCA\DeductibleLog\Service\SettingsService;
use OCA\DeductibleLog\Service\YearService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ReportController extends Controller {
    use ControllerHelpers;

    public function __construct(
        IRequest $request,
        private ReportService $service,
        private CpaReportService $cpaService,
        private YearService $yearService,
        private SettingsService $settingsService,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function years(): JSONResponse {
        $settings = $this->settingsService->get($this->uid());
        return new JSONResponse([
            'status' => 'ok',
            'data'   => [
                'years'        => $this->yearService->years($this->uid()),
                'default_year' => (int) $settings['default_tax_year'],
            ],
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function summary(): JSONResponse {
        return new JSONResponse(['status' => 'ok', 'data' => $this->service->summarize($this->uid(), $this->taxYearParam())]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function csv(): DataDownloadResponse {
        $year = $this->taxYearParam();
        return new DataDownloadResponse($this->service->csv($this->uid(), $year), "deductions_{$year}.csv", 'text/csv');
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function txf(): DataDownloadResponse {
        $year = $this->taxYearParam();
        return new DataDownloadResponse($this->service->txf($this->uid(), $year), "deductions_{$year}.txf", 'application/octet-stream');
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function cpa(): HtmlReportResponse {
        return new HtmlReportResponse($this->cpaService->html($this->uid(), $this->taxYearParam()));
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function html(): HtmlReportResponse {
        return new HtmlReportResponse($this->service->html($this->uid(), $this->taxYearParam()));
    }
}
