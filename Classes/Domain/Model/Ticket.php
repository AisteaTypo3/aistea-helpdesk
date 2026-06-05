<?php

declare(strict_types=1);

namespace Aistea\AisteaHelpdesk\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

final class Ticket extends AbstractEntity
{
    protected string $ticketNumber = '';
    protected string $subject = '';
    protected string $description = '';
}
