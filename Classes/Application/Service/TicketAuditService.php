<?php

declare(strict_types=1);

namespace Aistea\AisteaHelpdesk\Application\Service;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class TicketAuditService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function record(int $ticketUid, int $pid, string $action, array $payload = []): void
    {
        if ($ticketUid <= 0 || $action === '') {
            return;
        }

        $timestamp = time();
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_aisteahelpdesk_domain_model_tickethistory');

        $connection->insert('tx_aisteahelpdesk_domain_model_tickethistory', [
            'pid' => $pid,
            'tstamp' => $timestamp,
            'crdate' => $timestamp,
            'ticket' => $ticketUid,
            'action' => $action,
            'actor_type' => trim((string)($payload['actor_type'] ?? 'system')),
            'actor_name' => trim((string)($payload['actor_name'] ?? 'System')),
            'actor_fe_user' => (int)($payload['actor_fe_user'] ?? 0),
            'actor_be_user' => (int)($payload['actor_be_user'] ?? 0),
            'old_value' => trim((string)($payload['old_value'] ?? '')),
            'new_value' => trim((string)($payload['new_value'] ?? '')),
            'details' => trim((string)($payload['details'] ?? '')),
        ]);
    }
}
