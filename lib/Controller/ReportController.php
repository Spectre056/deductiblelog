<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Controller;

use OCA\DeductibleLog\AppInfo\Application;
use OCA\DeductibleLog\Http\HtmlReportResponse;
use OCA\DeductibleLog\Service\ReportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ReportController extends Controller {

    public function __construct(
        IRequest $request,
        private ReportService $service,
        private string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function summary(): JSONResponse {
        $year    = (int) ($this->request->getParam('tax_year') ?: date('Y'));
        $summary = $this->service->summarize($this->userId, $year);
        return new JSONResponse(['status' => 'ok', 'data' => $summary]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function csv(): DataDownloadResponse {
        $year    = (int) ($this->request->getParam('tax_year') ?: date('Y'));
        $content = $this->service->csv($this->userId, $year);
        return new DataDownloadResponse($content, "deductions_{$year}.csv", 'text/csv');
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function txf(): DataDownloadResponse {
        $year    = (int) ($this->request->getParam('tax_year') ?: date('Y'));
        $content = $this->service->txf($this->userId, $year);
        return new DataDownloadResponse($content, "deductions_{$year}.txf", 'application/octet-stream');
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function html(): HtmlReportResponse {
        $year    = (int) ($this->request->getParam('tax_year') ?: date('Y'));
        $content = $this->service->html($this->userId, $year);
        return new HtmlReportResponse($content);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function pdf(): JSONResponse {
        return new JSONResponse(
            ['status' => 'error', 'message' => 'Use /api/reports/html instead — open in browser and print to PDF.'],
            Http::STATUS_NOT_IMPLEMENTED,
        );
    }
}
