<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Controller;

use OCA\DeductibleLog\AppInfo\Application;
use OCA\DeductibleLog\Db\ItemCategoryMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ItemCategoryController extends Controller {

    public function __construct(
        IRequest $request,
        private ItemCategoryMapper $mapper,
        private string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): JSONResponse {
        $categories = $this->mapper->findAll();
        return new JSONResponse([
            'status' => 'ok',
            'data'   => array_map(fn($c) => $c->jsonSerialize(), $categories),
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function search(): JSONResponse {
        $q     = trim((string) ($this->request->getParam('q') ?? ''));
        $limit = min(50, max(5, (int) ($this->request->getParam('limit') ?? 20)));

        if (strlen($q) < 2) {
            return new JSONResponse(['status' => 'ok', 'data' => []]);
        }

        $results = $this->mapper->search($q, $limit);
        return new JSONResponse([
            'status' => 'ok',
            'data'   => array_map(fn($c) => $c->jsonSerialize(), $results),
        ]);
    }
}
