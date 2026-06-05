<?php

declare(strict_types=1);

namespace Aistea\AisteaHelpdesk\Application\Service;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class TicketQueryService
{
    public function __construct(
        private readonly ?AttachmentService $attachmentService = null
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findTicketsForFrontendUser(int $frontendUserId): array
    {
        if ($frontendUserId <= 0) {
            return [];
        }

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_aisteahelpdesk_domain_model_ticket');

        return $queryBuilder
            ->select('t.*', 's.title AS status_title', 'p.title AS priority_title')
            ->addSelectLiteral('(SELECT COALESCE(SUM(m.attachments), 0) FROM tx_aisteahelpdesk_domain_model_ticketmessage m WHERE m.ticket = t.uid AND m.deleted = 0 AND m.hidden = 0) AS attachment_count')
            ->from('tx_aisteahelpdesk_domain_model_ticket', 't')
            ->leftJoin('t', 'tx_aisteahelpdesk_domain_model_ticketstatus', 's', 's.uid = t.status')
            ->leftJoin('t', 'tx_aisteahelpdesk_domain_model_ticketpriority', 'p', 'p.uid = t.priority')
            ->where(
                $queryBuilder->expr()->eq(
                    't.customer_fe_user',
                    $queryBuilder->createNamedParameter($frontendUserId, ParameterType::INTEGER)
                ),
                $queryBuilder->expr()->eq('t.deleted', 0),
                $queryBuilder->expr()->eq('t.hidden', 0)
            )
            ->orderBy('t.tstamp', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAccessibleTicket(int $ticketUid, int $frontendUserId = 0, string $token = ''): ?array
    {
        if ($ticketUid <= 0) {
            return null;
        }

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_aisteahelpdesk_domain_model_ticket');
        $constraints = [
            $queryBuilder->expr()->eq('t.uid', $queryBuilder->createNamedParameter($ticketUid, ParameterType::INTEGER)),
            $queryBuilder->expr()->eq('t.deleted', 0),
            $queryBuilder->expr()->eq('t.hidden', 0),
        ];

        if ($frontendUserId > 0) {
            $constraints[] = $queryBuilder->expr()->eq(
                't.customer_fe_user',
                $queryBuilder->createNamedParameter($frontendUserId, ParameterType::INTEGER)
            );
        } elseif ($token !== '') {
            $constraints[] = $queryBuilder->expr()->eq(
                't.visibility_token',
                $queryBuilder->createNamedParameter($token)
            );
        } else {
            return null;
        }

        $row = $queryBuilder
            ->select('t.*', 's.title AS status_title', 's.code AS status_code', 'p.title AS priority_title', 'c.title AS category_title')
            ->addSelectLiteral('(SELECT COALESCE(SUM(m.attachments), 0) FROM tx_aisteahelpdesk_domain_model_ticketmessage m WHERE m.ticket = t.uid AND m.deleted = 0 AND m.hidden = 0) AS attachment_count')
            ->from('tx_aisteahelpdesk_domain_model_ticket', 't')
            ->leftJoin('t', 'tx_aisteahelpdesk_domain_model_ticketstatus', 's', 's.uid = t.status')
            ->leftJoin('t', 'tx_aisteahelpdesk_domain_model_ticketpriority', 'p', 'p.uid = t.priority')
            ->leftJoin('t', 'tx_aisteahelpdesk_domain_model_ticketcategory', 'c', 'c.uid = t.category')
            ->where(...$constraints)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findMessagesForTicket(int $ticketUid): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_aisteahelpdesk_domain_model_ticketmessage');

        $messages = $queryBuilder
            ->select('*')
            ->from('tx_aisteahelpdesk_domain_model_ticketmessage')
            ->where(
                $queryBuilder->expr()->eq('ticket', $queryBuilder->createNamedParameter($ticketUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('hidden', 0),
                $queryBuilder->expr()->eq('is_internal', 0)
            )
            ->orderBy('crdate', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return $this->enrichMessagesWithAttachments($messages);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findMessagesForTicketBackend(int $ticketUid): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_aisteahelpdesk_domain_model_ticketmessage');

        $messages = $queryBuilder
            ->select('*')
            ->from('tx_aisteahelpdesk_domain_model_ticketmessage')
            ->where(
                $queryBuilder->expr()->eq('ticket', $queryBuilder->createNamedParameter($ticketUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('hidden', 0)
            )
            ->orderBy('crdate', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return $this->enrichMessagesWithAttachments($messages);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllTicketsForBackend(array $filters = []): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_aisteahelpdesk_domain_model_ticket');
        $constraints = [
            $queryBuilder->expr()->eq('t.deleted', 0),
            $queryBuilder->expr()->eq('t.hidden', 0),
        ];
        $searchTerm = trim((string)($filters['q'] ?? ''));
        $statusCode = trim((string)($filters['status'] ?? ''));

        if ($statusCode !== '') {
            $constraints[] = $queryBuilder->expr()->eq('s.code', $queryBuilder->createNamedParameter($statusCode));
        }

        if ($searchTerm !== '') {
            $likeTerm = '%' . $queryBuilder->escapeLikeWildcards($searchTerm) . '%';
            $constraints[] = $queryBuilder->expr()->or(
                $queryBuilder->expr()->like('t.ticket_number', $queryBuilder->createNamedParameter($likeTerm)),
                $queryBuilder->expr()->like('t.subject', $queryBuilder->createNamedParameter($likeTerm)),
                $queryBuilder->expr()->like('t.customer_name', $queryBuilder->createNamedParameter($likeTerm)),
                $queryBuilder->expr()->like('t.customer_email', $queryBuilder->createNamedParameter($likeTerm))
            );
        }

        return $queryBuilder
            ->select('t.*', 's.title AS status_title', 's.code AS status_code', 'p.title AS priority_title')
            ->addSelectLiteral('(SELECT COALESCE(SUM(m.attachments), 0) FROM tx_aisteahelpdesk_domain_model_ticketmessage m WHERE m.ticket = t.uid AND m.deleted = 0 AND m.hidden = 0) AS attachment_count')
            ->from('tx_aisteahelpdesk_domain_model_ticket', 't')
            ->leftJoin('t', 'tx_aisteahelpdesk_domain_model_ticketstatus', 's', 's.uid = t.status')
            ->leftJoin('t', 'tx_aisteahelpdesk_domain_model_ticketpriority', 'p', 'p.uid = t.priority')
            ->where(...$constraints)
            ->orderBy('t.tstamp', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findTicketForBackend(int $ticketUid): ?array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_aisteahelpdesk_domain_model_ticket');
        $row = $queryBuilder
            ->select('t.*', 's.title AS status_title', 's.code AS status_code', 'p.title AS priority_title', 'c.title AS category_title')
            ->addSelectLiteral('(SELECT COALESCE(SUM(m.attachments), 0) FROM tx_aisteahelpdesk_domain_model_ticketmessage m WHERE m.ticket = t.uid AND m.deleted = 0 AND m.hidden = 0) AS attachment_count')
            ->from('tx_aisteahelpdesk_domain_model_ticket', 't')
            ->leftJoin('t', 'tx_aisteahelpdesk_domain_model_ticketstatus', 's', 's.uid = t.status')
            ->leftJoin('t', 'tx_aisteahelpdesk_domain_model_ticketpriority', 'p', 'p.uid = t.priority')
            ->leftJoin('t', 'tx_aisteahelpdesk_domain_model_ticketcategory', 'c', 'c.uid = t.category')
            ->where(
                $queryBuilder->expr()->eq('t.uid', $queryBuilder->createNamedParameter($ticketUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('t.deleted', 0),
                $queryBuilder->expr()->eq('t.hidden', 0)
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, int>
     */
    public function countTicketsByStatusCode(): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_aisteahelpdesk_domain_model_ticket');
        $rows = $queryBuilder
            ->selectLiteral('COUNT(t.uid) AS ticket_count')
            ->addSelect('s.code')
            ->from('tx_aisteahelpdesk_domain_model_ticket', 't')
            ->leftJoin('t', 'tx_aisteahelpdesk_domain_model_ticketstatus', 's', 's.uid = t.status')
            ->where(
                $queryBuilder->expr()->eq('t.deleted', 0),
                $queryBuilder->expr()->eq('t.hidden', 0)
            )
            ->groupBy('s.code')
            ->executeQuery()
            ->fetchAllAssociative();

        $counts = ['all' => 0];
        foreach ($rows as $row) {
            $count = (int)($row['ticket_count'] ?? 0);
            $code = (string)($row['code'] ?? '');
            $counts['all'] += $count;
            if ($code !== '') {
                $counts[$code] = $count;
            }
        }

        return $counts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllStatuses(): array
    {
        return $this->findLookupRows('tx_aisteahelpdesk_domain_model_ticketstatus');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findActiveCategories(): array
    {
        return $this->findLookupRows('tx_aisteahelpdesk_domain_model_ticketcategory', 'is_active');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findActivePriorities(): array
    {
        return $this->findLookupRows('tx_aisteahelpdesk_domain_model_ticketpriority');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findLookupRows(string $tableName, string $activeField = ''): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($tableName);
        $constraints = [
            $queryBuilder->expr()->eq('deleted', 0),
            $queryBuilder->expr()->eq('hidden', 0),
        ];

        if ($activeField !== '') {
            $constraints[] = $queryBuilder->expr()->eq($activeField, 1);
        }

        return $queryBuilder
            ->select('*')
            ->from($tableName)
            ->where(...$constraints)
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function enrichMessagesWithAttachments(array $messages): array
    {
        foreach ($messages as &$message) {
            $message['attachments'] = $this->getAttachmentService()->getAttachmentsForMessage((int)($message['uid'] ?? 0));
        }
        unset($message);

        return $messages;
    }

    private function getAttachmentService(): AttachmentService
    {
        return $this->attachmentService ?? GeneralUtility::makeInstance(AttachmentService::class);
    }

    private function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }
}
