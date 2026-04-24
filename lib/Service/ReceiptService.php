<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Db\Receipt;
use OCA\DeductibleLog\Db\ReceiptMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;

class ReceiptService {

    public function __construct(
        private ReceiptMapper $mapper,
        private IRootFolder $rootFolder,
    ) {}

    /** @return Receipt[] */
    public function findByEntity(string $entityType, int $entityId, string $userId): array {
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
        $userFolder = $this->rootFolder->getUserFolder($userId);
        $this->ensureFolder($userFolder, 'DeductibleLog/Receipts/' . $taxYear);

        $folder   = $userFolder->get('DeductibleLog/Receipts/' . $taxYear);
        $basename = pathinfo($originalFilename, PATHINFO_FILENAME);
        $ext      = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $filename = $basename . '_' . substr(uniqid(), -6) . ($ext ? '.' . $ext : '');

        $ncFile = $folder->newFile($filename, $content);

        $now     = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $receipt = new Receipt();
        $receipt->setUserId($userId);
        $receipt->setEntityType($entityType);
        $receipt->setEntityId($entityId);
        $receipt->setNcFilePath($ncFile->getPath());
        $receipt->setOriginalFilename($originalFilename);
        $receipt->setCreatedAt($now);

        return $this->mapper->insert($receipt);
    }

    /** @return array{0: string, 1: string, 2: string} [content, mimeType, filename] */
    public function download(int $id, string $userId): array {
        $receipt = $this->mapper->findById($id, $userId);
        $node    = $this->rootFolder->get($receipt->getNcFilePath());
        return [$node->getContent(), $node->getMimeType(), $receipt->getOriginalFilename()];
    }

    public function delete(int $id, string $userId): void {
        $receipt = $this->mapper->findById($id, $userId);
        try {
            $this->rootFolder->get($receipt->getNcFilePath())->delete();
        } catch (NotFoundException) {
            // file already removed from NC Files
        }
        $this->mapper->delete($receipt);
    }

    private function ensureFolder(Folder $base, string $path): void {
        $parts   = explode('/', $path);
        $current = $base;
        foreach ($parts as $part) {
            if (!$current->nodeExists($part)) {
                $current = $current->newFolder($part);
            } else {
                $current = $current->get($part);
            }
        }
    }
}
