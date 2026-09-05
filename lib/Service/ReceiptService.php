<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Db\Receipt;
use OCA\DeductibleLog\Db\ReceiptMapper;
use OCA\DeductibleLog\Exception\ApiException;
use OCP\AppFramework\Http;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;

class ReceiptService {

    public const MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private ReceiptMapper $mapper,
        private IRootFolder $rootFolder,
    ) {}

    /** @return Receipt[] */
    public function findByEntity(string $entityType, int $entityId, string $userId): array {
        $this->assertEntityRef($entityType, $entityId);
        return $this->mapper->findByEntity($entityType, $entityId, $userId);
    }

    public function upload(
        string $userId,
        string $entityType,
        int $entityId,
        string $originalFilename,
        string $content,
        int $taxYear,
    ): Receipt {
        $this->assertEntityRef($entityType, $entityId);
        $v = new Validator();
        if ($taxYear < Validator::MIN_YEAR || $taxYear > (int) date('Y') + 1) {
            $v->fail('tax_year', 'tax_year is out of range');
        }
        if (strlen($content) === 0) {
            $v->fail('file', 'File is empty');
        }
        if (strlen($content) > self::MAX_BYTES) {
            $v->fail('file', 'File exceeds 10 MB limit');
        }
        $v->throwIfInvalid();

        $userFolder = $this->rootFolder->getUserFolder($userId);
        $folder     = $this->ensureFolder($userFolder, 'DeductibleLog/Receipts/' . $taxYear);
        $filename   = self::safeFilename($originalFilename);

        $ncFile = $folder->newFile($filename, $content);

        $receipt = new Receipt();
        $receipt->setUserId($userId);
        $receipt->setEntityType($entityType);
        $receipt->setEntityId($entityId);
        $receipt->setFileId($ncFile->getId());
        $receipt->setNcFilePath($ncFile->getPath());
        $receipt->setOriginalFilename(mb_substr($originalFilename, 0, 256));
        $receipt->setCreatedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));

        return $this->mapper->insert($receipt);
    }

    /** @return array{0: string, 1: string, 2: string} [content, mimeType, filename] */
    public function download(int $id, string $userId): array {
        $receipt = $this->mapper->findById($id, $userId);
        $node    = $this->resolve($receipt, $userId);
        if (!$node instanceof File) {
            throw new ApiException('Receipt file is missing from Files', Http::STATUS_NOT_FOUND);
        }
        return [$node->getContent(), $node->getMimeType(), $receipt->getOriginalFilename()];
    }

    public function delete(int $id, string $userId): void {
        $receipt = $this->mapper->findById($id, $userId);
        $node    = $this->resolve($receipt, $userId);
        if ($node !== null) {
            $node->delete();
        }
        $this->mapper->delete($receipt);
    }

    /**
     * Prefer the file id (survives rename/move in Files); fall back to the
     * stored path for rows written before file_id existed.
     */
    private function resolve(Receipt $receipt, string $userId): ?Node {
        $userFolder = $this->rootFolder->getUserFolder($userId);
        if ($receipt->getFileId() !== null) {
            $nodes = $userFolder->getById($receipt->getFileId());
            if ($nodes !== []) {
                return $nodes[0];
            }
        }
        try {
            return $this->rootFolder->get($receipt->getNcFilePath());
        } catch (NotFoundException) {
            return null;
        }
    }

    private function assertEntityRef(string $entityType, int $entityId): void {
        $v = new Validator();
        $v->enum($entityType, Validator::RECEIPT_ENTITY_TYPES, 'entity_type');
        $v->positiveInt($entityId, 'entity_id');
        $v->throwIfInvalid();
    }

    /** Strip path separators and characters Nextcloud rejects; keep it short; make it unique. */
    public static function safeFilename(string $original): string {
        $base = pathinfo($original, PATHINFO_FILENAME);
        $ext  = pathinfo($original, PATHINFO_EXTENSION);
        $base = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/', '_', $base) ?? '';
        $base = trim($base, " .\t");
        $base = mb_substr($base !== '' ? $base : 'receipt', 0, 100);
        $ext  = mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $ext) ?? '', 0, 10);
        return $base . '_' . substr(bin2hex(random_bytes(4)), 0, 6) . ($ext !== '' ? '.' . $ext : '');
    }

    private function ensureFolder(Folder $base, string $path): Folder {
        $current = $base;
        foreach (explode('/', $path) as $part) {
            $current = $current->nodeExists($part) ? $current->get($part) : $current->newFolder($part);
        }
        return $current;
    }
}
