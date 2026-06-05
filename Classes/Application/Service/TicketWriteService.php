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
        private readonly ?AttachmentService $attachmentService = null
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

        $connection = $this->getConnectionPool()->getConnectionForTable('tx_aisteahelpdesk_domain_model_ticket');
        $connection->update(
            'tx_aisteahelpdesk_domain_model_ticket',
            [
                'tstamp' => time(),
                'status' => $this->resolveStatusUid('open'),
            ],
            ['uid' => $ticketUid],
            ['status' => ParameterType::INTEGER]
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

        $connection = $this->getConnectionPool()->getConnectionForTable('tx_aisteahelpdesk_domain_model_ticket');
        $connection->update(
            'tx_aisteahelpdesk_domain_model_ticket',
            [
                'tstamp' => time(),
                'status' => $this->resolveStatusUid('open'),
            ],
            ['uid' => $ticketUid],
            ['status' => ParameterType::INTEGER]
        );

        $ticketRow = $this->getTicketQueryService()->findTicketForBackend($ticketUid);
        if (is_array($ticketRow)) {
            $this->getNotificationService()->notifyAgentReply($ticketRow, trim($message));
        }
    }

    public function updateStatus(int $ticketUid, int $statusUid): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tx_aisteahelpdesk_domain_model_ticket');
        $connection->update(
            'tx_aisteahelpdesk_domain_model_ticket',
            [
                'tstamp' => time(),
                'status' => $statusUid,
            ],
            ['uid' => $ticketUid],
            ['status' => ParameterType::INTEGER]
        );
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
}
