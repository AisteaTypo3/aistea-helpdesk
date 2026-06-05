<?php

declare(strict_types=1);

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::registerPlugin(
    'AisteaHelpdesk',
    'Portal',
    'Helpdesk Portal',
    'aistea-helpdesk-plugin',
    'plugins'
);

ExtensionUtility::registerPlugin(
    'AisteaHelpdesk',
    'Ticket',
    'Helpdesk Ticket',
    'aistea-helpdesk-plugin',
    'plugins'
);
