<?php

declare(strict_types=1);

namespace Aistea\AisteaHelpdesk\Application\Service;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class TicketWriteService
{
    public function __construct(
        private readonly TicketNumberService $ticketNumberService,
        private readonly ?NotificationService $notificationService = null,
        private readonly ?TicketQueryService $ticketQueryService = null,
        private readonly ?AttachmentService $attachmentService = null,
        private readonly ?TicketAuditService $ticketAuditService = null
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createTicket(array $payload, int $pid, int $frontendUserId = 0): array
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tx_aisteahelpdesk_domain_model_ticket');
        $ticketNumber = $this->ticketNumberService->generate();
        $token = bin2hex(random_bytes(20));
        $timestamp = time();

        $statusUid = $this->resolveStatusUid('new');
        $priorityUid = (int)($payload['priority'] ?? 0) ?: $this->resolveFirstUid('tx_aisteahelpdesk_domain_model_ticketpriority');
        $categoryUid = (int)($payload['category'] ?? 0);
        $dueAt = $this->calculateDueAt($priorityUid, $timestamp);

        $connection->insert('tx_aisteahelpdesk_domain_model_ticket', [
            'pid' => $pid,
            'tstamp' => $timestamp,
            'crdate' => $timestamp,
            'ticket_number' => $ticketNumber,
            'subject' => trim((string)($payload['subject'] ?? '')),
            'description' => trim((string)($payload['description'] ?? '')),
            'customer_name' => trim((string)($payload['customerName'] ?? '')),
            'customer_email' => trim((string)($payload['customerEmail'] ?? '')),
            'customer_fe_user' => $frontendUserId,
            'visibility_token' => $token,
            'site_root_page_id' => (int)($payload['siteRootPageId'] ?? 0),
            'site_language' => (int)($payload['siteLanguage'] ?? 0),
            'category' => $categoryUid,
            'priority' => $priorityUid,
            'status' => $statusUid,
            'due_at' => $dueAt,
        ]);

        $ticketUid = (int)$connection->lastInsertId('tx_aisteahelpdesk_domain_model_ticket');

        $messageUid = $this->addMessage($ticketUid, $pid, [
            'author_type' => 'customer',
            'author_name' => trim((string)($payload['customerName'] ?? '')),
            'author_email' => trim((string)($payload['customerEmail'] ?? '')),
            'author_fe_user' => $frontendUserId,
            'message' => trim((string)($payload['description'] ?? '')),
        ]);
        $this->getAttachmentService()->attachUploadsToMessage((array)($payload['attachments'] ?? []), $messageUid, $pid);
        $this->getTicketAuditService()->record($ticketUid, $pid, 'ticket_created', [
            'actor_type' => 'customer',
            'actor_name' => trim((string)($payload['customerName'] ?? '')),
            'actor_fe_user' => $frontendUserId,
            'new_value' => $ticketNumber,
            'details' => trim((string)($payload['subject'] ?? '')),
        ]);

        $ticketRow = $this->getTicketQueryService()->findTicketForBackend($ticketUid);
        if (is_array($ticketRow)) {
            $this->getNotificationService()->notifyTicketCreated($ticketRow);
        }

        return [
            'uid' => $ticketUid,
            'ticketNumber' => $ticketNumber,
            'visibilityToken' => $token,
        ];
    }

    public function addReply(int $ticketUid, int $pid, string $message, int $frontendUserId = 0, string $authorName = '', string $authorEmail = '', array $attachments = []): void
    {
        $messageUid = $this->addMessage($ticketUid, $pid, [
            'author_type' => 'customer',
            'author_name' => trim($authorName),
            'author_email' => trim($authorEmail),
            'author_fe_user' => $frontendUserId,
            'message' => trim($message),
        ]);
        $this->getAttachmentService()->attachUploadsToMessage($attachments, $messageUid, $pid);
        $this->getTicketAuditService()->record($ticketUid, $pid, 'customer_reply_added', [
            'actor_type' => 'customer',
            'actor_name' => trim($authorName),
            'actor_fe_user' => $frontendUserId,
            'details' => $this->summarizeText($message),
        ]);

        $connection = $this->getConnectionPool()->getConnectionForTable('tx_aisteahelpdesk_domain_model_ticket');
        $connection->update(
            'tx_aisteahelpdesk_domain_model_ticket',
            [
                'tstamp' => time(),
                'status' => $this->resolveStatusUid('open'),
                'resolved_at' => 0,
                'closed_at' => 0,
            ],
            ['uid' => $ticketUid],
            [
                'status' => ParameterType::INTEGER,
                'resolved_at' => ParameterType::INTEGER,
                'closed_at' => ParameterType::INTEGER,
            ]
        );

        $ticketRow = $this->getTicketQueryService()->findTicketForBackend($ticketUid);
        if (is_array($ticketRow)) {
            $this->getNotificationService()->notifyCustomerReply($ticketRow, trim($message));
        }
    }

    public function addInternalNote(int $ticketUid, int $pid, string $message, int $backendUserId = 0, string $authorName = 'Agent', array $attachments = []): void
    {
        $messageUid = $this->addMessage($ticketUid, $pid, [
            'author_type' => 'agent',
            'author_name' => trim($authorName),
            'author_email' => '',
            'author_fe_user' => 0,
            'author_be_user' => $backendUserId,
            'message' => trim($message),
            'is_internal' => 1,
        ]);
        $this->getAttachmentService()->attachUploadsToMessage($attachments, $messageUid, $pid);
        $this->getTicketAuditService()->record($ticketUid, $pid, 'internal_note_added', [
            'actor_type' => 'agent',
            'actor_name' => trim($authorName),
            'actor_be_user' => $backendUserId,
            'details' => $this->summarizeText($message),
        ]);

        $this->touchTicket($ticketUid);
    }

    public function addAgentReply(int $ticketUid, int $pid, string $message, int $backendUserId = 0, string $authorName = 'Agent', array $attachments = []): void
    {
        $messageUid = $this->addMessage($ticketUid, $pid, [
            'author_type' => 'agent',
            'author_name' => trim($authorName),
            'author_email' => '',
            'author_fe_user' => 0,
            'author_be_user' => $backendUserId,
            'message' => trim($message),
            'is_internal' => 0,
        ]);
        $this->getAttachmentService()->attachUploadsToMessage($attachments, $messageUid, $pid);
        $this->getTicketAuditService()->record($ticketUid, $pid, 'agent_reply_added', [
            'actor_type' => 'agent',
            'actor_name' => trim($authorName),
            'actor_be_user' => $backendUserId,
            'details' => $this->summarizeText($message),
        ]);

        $connection = $this->getConnectionPool()->getConnectionForTable('tx_aisteahelpdesk_domain_model_ticket');
        $ticketRow = $this->getTicketQueryService()->findTicketForBackend($ticketUid);
        $firstResponseAt = (int)($ticketRow['first_response_at'] ?? 0);
        $timestamp = time();
        $updateFields = [
            'tstamp' => $timestamp,
            'status' => $this->resolveStatusUid('open'),
            'resolved_at' => 0,
            'closed_at' => 0,
        ];
        $types = [
            'status' => ParameterType::INTEGER,
            'resolved_at' => ParameterType::INTEGER,
            'closed_at' => ParameterType::INTEGER,
        ];
        if ($firstResponseAt <= 0) {
            $updateFields['first_response_at'] = $timestamp;
            $types['first_response_at'] = ParameterType::INTEGER;
        }

        $connection->update(
            'tx_aisteahelpdesk_domain_model_ticket',
            $updateFields,
            ['uid' => $ticketUid],
            $types
        );

        $updatedTicketRow = $this->getTicketQueryService()->findTicketForBackend($ticketUid);
        if (is_array($updatedTicketRow)) {
            $this->getNotificationService()->notifyAgentReply($updatedTicketRow, trim($message));
        }
    }

    public function updateStatus(int $ticketUid, int $statusUid): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tx_aisteahelpdesk_domain_model_ticket');
        $statusRow = $this->findStatusRow($statusUid);
        $statusCode = (string)($statusRow['code'] ?? '');
        $ticketRow = $this->getTicketQueryService()->findTicketForBackend($ticketUid);
        $timestamp = time();
        $updateFields = [
            'tstamp' => $timestamp,
            'status' => $statusUid,
        ];
        $types = ['status' => ParameterType::INTEGER];

        if ($statusCode === 'resolved' && (int)($ticketRow['resolved_at'] ?? 0) <= 0) {
            $updateFields['resolved_at'] = $timestamp;
            $types['resolved_at'] = ParameterType::INTEGER;
        }
        if ($statusCode === 'closed') {
            if ((int)($ticketRow['closed_at'] ?? 0) <= 0) {
                $updateFields['closed_at'] = $timestamp;
                $types['closed_at'] = ParameterType::INTEGER;
            }
            if ((int)($ticketRow['resolved_at'] ?? 0) <= 0) {
                $updateFields['resolved_at'] = $timestamp;
                $types['resolved_at'] = ParameterType::INTEGER;
            }
        }
        if (($statusCode === 'new' || $statusCode === 'open') && (int)($statusRow['is_closed'] ?? 0) === 0) {
            $updateFields['resolved_at'] = 0;
            $updateFields['closed_at'] = 0;
            $types['resolved_at'] = ParameterType::INTEGER;
            $types['closed_at'] = ParameterType::INTEGER;
        }

        $connection->update(
            'tx_aisteahelpdesk_domain_model_ticket',
            $updateFields,
            ['uid' => $ticketUid],
            $types
        );

        if (is_array($ticketRow) && (int)($ticketRow['status'] ?? 0) !== $statusUid) {
            $this->getTicketAuditService()->record($ticketUid, (int)($ticketRow['pid'] ?? 0), 'status_changed', [
                'actor_type' => 'agent',
                'actor_name' => $this->getCurrentBackendUserName(),
                'actor_be_user' => $this->getCurrentBackendUserId(),
                'old_value' => (string)($ticketRow['status_title'] ?? ''),
                'new_value' => (string)($statusRow['title'] ?? $statusCode),
            ]);
        }
    }

    public function assignBackendUser(int $ticketUid, int $backendUserId): void
    {
        if ($ticketUid <= 0) {
            return;
        }

        $connection = $this->getConnectionPool()->getConnectionForTable('tx_aisteahelpdesk_domain_model_ticket');
        $ticketRow = $this->getTicketQueryService()->findTicketForBackend($ticketUid);
        $oldAssignee = (string)($ticketRow['assigned_backend_user_name'] ?? '');
        $newAssignee = $this->resolveBackendUserName($backendUserId);

        $connection->update(
            'tx_aisteahelpdesk_domain_model_ticket',
            [
                'tstamp' => time(),
                'assigned_backend_user' => max(0, $backendUserId),
            ],
            ['uid' => $ticketUid],
            ['assigned_backend_user' => ParameterType::INTEGER]
        );

        if (is_array($ticketRow) && (int)($ticketRow['assigned_backend_user'] ?? 0) !== max(0, $backendUserId)) {
            $this->getTicketAuditService()->record($ticketUid, (int)($ticketRow['pid'] ?? 0), 'assignment_changed', [
                'actor_type' => 'agent',
                'actor_name' => $this->getCurrentBackendUserName(),
                'actor_be_user' => $this->getCurrentBackendUserId(),
                'old_value' => $oldAssignee !== '' ? $oldAssignee : 'Nicht zugewiesen',
                'new_value' => $newAssignee !== '' ? $newAssignee : 'Nicht zugewiesen',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function addMessage(int $ticketUid, int $pid, array $payload): int
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tx_aisteahelpdesk_domain_model_ticketmessage');
        $timestamp = time();

        $connection->insert('tx_aisteahelpdesk_domain_model_ticketmessage', [
            'pid' => $pid,
            'tstamp' => $timestamp,
            'crdate' => $timestamp,
            'ticket' => $ticketUid,
            'author_type' => (string)($payload['author_type'] ?? 'customer'),
            'author_name' => trim((string)($payload['author_name'] ?? '')),
            'author_email' => trim((string)($payload['author_email'] ?? '')),
            'author_fe_user' => (int)($payload['author_fe_user'] ?? 0),
            'author_be_user' => (int)($payload['author_be_user'] ?? 0),
            'message' => trim((string)($payload['message'] ?? '')),
            'is_internal' => (int)($payload['is_internal'] ?? 0),
        ]);

        return (int)$connection->lastInsertId('tx_aisteahelpdesk_domain_model_ticketmessage');
    }

    private function touchTicket(int $ticketUid): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tx_aisteahelpdesk_domain_model_ticket');
        $connection->update(
            'tx_aisteahelpdesk_domain_model_ticket',
            ['tstamp' => time()],
            ['uid' => $ticketUid]
        );
    }

    private function resolveStatusUid(string $code): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_aisteahelpdesk_domain_model_ticketstatus');
        $row = $queryBuilder
            ->select('uid')
            ->from('tx_aisteahelpdesk_domain_model_ticketstatus')
            ->where(
                $queryBuilder->expr()->eq('code', $queryBuilder->createNamedParameter($code)),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('hidden', 0)
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? (int)$row['uid'] : $this->resolveFirstUid('tx_aisteahelpdesk_domain_model_ticketstatus');
    }

    private function resolveFirstUid(string $tableName): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($tableName);
        $row = $queryBuilder
            ->select('uid')
            ->from($tableName)
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('hidden', 0)
            )
            ->orderBy('sorting')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? (int)$row['uid'] : 0;
    }

    private function calculateDueAt(int $priorityUid, int $timestamp): int
    {
        if ($priorityUid <= 0) {
            return 0;
        }

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_aisteahelpdesk_domain_model_ticketpriority');
        $row = $queryBuilder
            ->select('resolve_hours')
            ->from('tx_aisteahelpdesk_domain_model_ticketpriority')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($priorityUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('hidden', 0)
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        $resolveHours = is_array($row) ? (int)($row['resolve_hours'] ?? 0) : 0;
        return $resolveHours > 0 ? $timestamp + ($resolveHours * 3600) : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function findStatusRow(int $statusUid): array
    {
        if ($statusUid <= 0) {
            return [];
        }

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_aisteahelpdesk_domain_model_ticketstatus');
        $row = $queryBuilder
            ->select('uid', 'title', 'code', 'is_closed')
            ->from('tx_aisteahelpdesk_domain_model_ticketstatus')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($statusUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('hidden', 0)
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : [];
    }

    private function summarizeText(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        return mb_strlen($text) > 180 ? mb_substr($text, 0, 177) . '...' : $text;
    }

    private function getCurrentBackendUserId(): int
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        return (int)($backendUser->user['uid'] ?? 0);
    }

    private function getCurrentBackendUserName(): string
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        $name = trim((string)($backendUser->user['realName'] ?? $backendUser->user['username'] ?? ''));
        return $name !== '' ? $name : 'Agent';
    }

    private function resolveBackendUserName(int $backendUserId): string
    {
        if ($backendUserId <= 0) {
            return '';
        }

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('be_users');
        $row = $queryBuilder
            ->select('username', 'realName')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($backendUserId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (!is_array($row)) {
            return '';
        }

        $realName = trim((string)($row['realName'] ?? ''));
        return $realName !== '' ? $realName : trim((string)($row['username'] ?? ''));
    }

    private function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }

    private function getNotificationService(): NotificationService
    {
        return $this->notificationService ?? GeneralUtility::makeInstance(NotificationService::class);
    }

    private function getTicketQueryService(): TicketQueryService
    {
        return $this->ticketQueryService ?? GeneralUtility::makeInstance(TicketQueryService::class);
    }

    private function getAttachmentService(): AttachmentService
    {
        return $this->attachmentService ?? GeneralUtility::makeInstance(AttachmentService::class);
    }

    private function getTicketAuditService(): TicketAuditService
    {
        return $this->ticketAuditService ?? GeneralUtility::makeInstance(TicketAuditService::class);
    }
}
