<?php

declare(strict_types=1);

namespace Aistea\AisteaHelpdesk\Application\Service;

use Psr\Http\Message\UploadedFileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class AttachmentService
{
    private const ALLOWED_EXTENSIONS = [
        'pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'zip',
        'jpg', 'jpeg', 'png', 'webp', 'gif',
    ];

    /**
     * @param array<mixed> $uploadedFiles
     */
    public function attachUploadsToMessage(array $uploadedFiles, int $messageUid, int $pid): void
    {
        $files = $this->flattenUploadedFiles($uploadedFiles);
        if ($files === []) {
            return;
        }

        $storage = $this->getStorageRepository()->getDefaultStorage();
        if ($storage === null) {
            return;
        }

        try {
            $folder = $this->ensureFolder($storage->getRootLevelFolder(), 'user_upload/helpdesk');
        } catch (\Throwable) {
            return;
        }

        $referenceConnection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_file_reference');
        $sorting = $this->countExistingReferences($messageUid);
        $referencePid = $this->resolveReferencePid($messageUid, $pid);

        foreach ($files as $file) {
            if ($file->getError() !== \UPLOAD_ERR_OK || $file->getSize() <= 0) {
                continue;
            }

            $originalName = $file->getClientFilename() ?: 'attachment';
            $extension = strtolower(pathinfo($originalName, \PATHINFO_EXTENSION));
            if ($extension === '' || !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }

            $temporaryPath = tempnam(sys_get_temp_dir(), 'ahd_');
            if ($temporaryPath === false) {
                continue;
            }

            $stream = $file->getStream();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            file_put_contents($temporaryPath, $stream->getContents());

            try {
                $safeName = $this->sanitizeFileName($originalName);
                $storedFile = $storage->addFile(
                    $temporaryPath,
                    $folder,
                    $safeName,
                    DuplicationBehavior::RENAME
                );

                $sorting++;
                $timestamp = time();
                $referenceConnection->insert('sys_file_reference', [
                    'pid' => $referencePid,
                    'tstamp' => $timestamp,
                    'crdate' => $timestamp,
                    'deleted' => 0,
                    'hidden' => 0,
                    'sys_language_uid' => 0,
                    'l10n_parent' => 0,
                    't3ver_oid' => 0,
                    't3ver_wsid' => 0,
                    't3ver_state' => 0,
                    't3ver_stage' => 0,
                    'tablenames' => 'tx_aisteahelpdesk_domain_model_ticketmessage',
                    'uid_local' => $storedFile->getUid(),
                    'uid_foreign' => $messageUid,
                    'fieldname' => 'attachments',
                    'sorting_foreign' => $sorting,
                    'link' => '',
                ]);
            } catch (\Throwable) {
            } finally {
                @unlink($temporaryPath);
            }
        }

        $this->updateAttachmentCount($messageUid);
    }

    /**
     * @param array<mixed> $uploadedFiles
     * @return list<UploadedFileInterface>
     */
    public function flattenUploadedFiles(array $uploadedFiles): array
    {
        $result = [];

        foreach ($uploadedFiles as $uploadedFile) {
            if (is_array($uploadedFile)) {
                array_push($result, ...$this->flattenUploadedFiles($uploadedFile));
                continue;
            }

            if ($uploadedFile instanceof UploadedFileInterface) {
                $result[] = $uploadedFile;
            }
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAttachmentsForMessage(int $messageUid): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        $rows = $queryBuilder
            ->select('uid')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($messageUid)),
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter('tx_aisteahelpdesk_domain_model_ticketmessage')),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter('attachments')),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0))
            )
            ->orderBy('sorting_foreign')
            ->executeQuery()
            ->fetchFirstColumn();

        $attachments = [];
        foreach ($rows as $referenceUid) {
            try {
                $reference = $this->getResourceFactory()->getFileReferenceObject((int)$referenceUid);
                $original = $reference->getOriginalFile();
                $attachments[] = [
                    'uid' => (int)$referenceUid,
                    'name' => $original->getName(),
                    'size' => $original->getSize(),
                    'publicUrl' => $reference->getPublicUrl() ?? $original->getPublicUrl(),
                    'extension' => $original->getExtension(),
                ];
            } catch (\Throwable) {
            }
        }

        return $attachments;
    }

    private function countExistingReferences(int $messageUid): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        return (int)$queryBuilder
            ->count('uid')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($messageUid)),
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter('tx_aisteahelpdesk_domain_model_ticketmessage')),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter('attachments')),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0))
            )
            ->executeQuery()
            ->fetchOne();
    }

    private function updateAttachmentCount(int $messageUid): void
    {
        $count = count($this->getAttachmentsForMessage($messageUid));
        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_aisteahelpdesk_domain_model_ticketmessage')
            ->update(
                'tx_aisteahelpdesk_domain_model_ticketmessage',
                ['attachments' => $count, 'tstamp' => time()],
                ['uid' => $messageUid]
            );
    }

    private function resolveReferencePid(int $messageUid, int $fallbackPid): int
    {
        if ($fallbackPid > 0) {
            return $fallbackPid;
        }

        $row = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_aisteahelpdesk_domain_model_ticketmessage')
            ->select(['pid'], 'tx_aisteahelpdesk_domain_model_ticketmessage', ['uid' => $messageUid])
            ->fetchAssociative();

        return (int)($row['pid'] ?? 0);
    }

    private function sanitizeFileName(string $fileName): string
    {
        $baseName = pathinfo($fileName, \PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($fileName, \PATHINFO_EXTENSION));
        $baseName = mb_strtolower($baseName, 'UTF-8');
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $baseName);
        $baseName = $transliterated !== false ? $transliterated : $baseName;
        $baseName = preg_replace('/[^a-z0-9._-]+/', '-', $baseName);
        $baseName = trim((string)$baseName, '-_.');
        $baseName = $baseName !== '' ? $baseName : 'attachment';

        return $baseName . '.' . $extension;
    }

    private function ensureFolder(Folder $baseFolder, string $relativePath): Folder
    {
        $storage = $baseFolder->getStorage();
        $current = $baseFolder;

        foreach (array_filter(explode('/', trim($relativePath, '/'))) as $segment) {
            if (!$storage->hasFolderInFolder($segment, $current)) {
                $current = $storage->createFolder($segment, $current);
            } else {
                $current = $storage->getFolderInFolder($segment, $current);
            }
        }

        return $current;
    }

    private function getResourceFactory(): ResourceFactory
    {
        return GeneralUtility::makeInstance(ResourceFactory::class);
    }

    private function getStorageRepository(): StorageRepository
    {
        return GeneralUtility::makeInstance(StorageRepository::class);
    }
}
