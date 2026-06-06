<?php

declare(strict_types=1);

use Aistea\AisteaHelpdesk\Controller\Backend\TicketModuleController;

return [
    'aistea_helpdesk' => [
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'user',
        'path' => '/module/aistea/helpdesk',
        'iconIdentifier' => 'aistea-helpdesk-plugin',
        'labels' => [
            'title' => 'LLL:EXT:aistea_helpdesk/Resources/Private/Language/locallang_module.xlf:module.title',
            'description' => 'LLL:EXT:aistea_helpdesk/Resources/Private/Language/locallang_module.xlf:module.description',
            'shortDescription' => 'LLL:EXT:aistea_helpdesk/Resources/Private/Language/locallang_module.xlf:module.shortDescription',
        ],
        'extensionName' => 'AisteaHelpdesk',
        'controllerActions' => [
            TicketModuleController::class => ['index', 'board', 'show', 'updateStatus', 'updateAssignment', 'addInternalNote', 'addPublicReply'],
        ],
    ],
];
