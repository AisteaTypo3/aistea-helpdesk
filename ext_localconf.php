<?php

defined('TYPO3') or die('Access denied.');

use Aistea\AisteaHelpdesk\Controller\Frontend\PortalController;
use Aistea\AisteaHelpdesk\Controller\Frontend\TicketController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::configurePlugin(
    'AisteaHelpdesk',
    'Portal',
    [PortalController::class => 'list, create'],
    [PortalController::class => 'create'],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'AisteaHelpdesk',
    'Ticket',
    [TicketController::class => 'show, reply'],
    [TicketController::class => 'reply'],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);
