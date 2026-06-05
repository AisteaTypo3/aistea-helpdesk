<?php

declare(strict_types=1);

namespace Aistea\AisteaHelpdesk\Application\Service;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class TicketNumberService
{
    public function generate(): string
    {
        $year = date('Y');
        $prefix = 'HD-' . $year . '-';

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_aisteahelpdesk_domain_model_ticket');

        $row = $queryBuilder
            ->select('ticket_number')
            ->from('tx_aisteahelpdesk_domain_model_ticket')
            ->where(
                $queryBuilder->expr()->like(
                    'ticket_number',
                    $queryBuilder->createNamedParameter($prefix . '%', ParameterType::STRING)
                )
            )
            ->orderBy('ticket_number', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        $nextNumber = 1;
        if (is_array($row) && is_string($row['ticket_number'] ?? null)) {
            $parts = explode('-', (string)$row['ticket_number']);
            $lastPart = end($parts);
            if (is_string($lastPart) && ctype_digit($lastPart)) {
                $nextNumber = (int)$lastPart + 1;
            }
        }

        return $prefix . str_pad((string)$nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
