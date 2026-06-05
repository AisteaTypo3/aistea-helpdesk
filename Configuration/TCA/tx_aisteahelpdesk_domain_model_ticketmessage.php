<?php

declare(strict_types=1);

$languageFile = 'LLL:EXT:aistea_helpdesk/Resources/Private/Language/locallang_db.xlf:';

return [
    'ctrl' => [
        'title' => $languageFile . 'ticketMessage',
        'label' => 'author_name',
        'security' => ['ignorePageTypeRestriction' => true],
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'sortby' => 'sorting',
        'delete' => 'deleted',
        'enablecolumns' => ['disabled' => 'hidden'],
        'searchFields' => 'author_name,author_email,message',
        'iconfile' => 'EXT:aistea_helpdesk/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'hidden, ticket, author_type, author_name, author_email, message, attachments, is_internal'],
    ],
    'columns' => [
        'hidden' => ['config' => ['type' => 'check', 'renderType' => 'checkboxToggle']],
        'ticket' => ['label' => $languageFile . 'field.ticket', 'config' => ['type' => 'select', 'renderType' => 'selectSingle', 'foreign_table' => 'tx_aisteahelpdesk_domain_model_ticket']],
        'author_type' => ['label' => $languageFile . 'field.authorType', 'config' => ['type' => 'input', 'eval' => 'trim']],
        'author_name' => ['label' => $languageFile . 'field.authorName', 'config' => ['type' => 'input', 'eval' => 'trim']],
        'author_email' => ['label' => $languageFile . 'field.authorEmail', 'config' => ['type' => 'input', 'eval' => 'trim,email']],
        'message' => ['label' => $languageFile . 'field.message', 'config' => ['type' => 'text', 'rows' => 8]],
        'attachments' => [
            'label' => $languageFile . 'field.attachments',
            'config' => [
                'type' => 'file',
                'allowed' => 'common-media-types',
                'maxitems' => 10,
                'appearance' => [
                    'createNewRelationLinkTitle' => $languageFile . 'field.attachments.add',
                ],
            ],
        ],
        'is_internal' => ['label' => $languageFile . 'field.isInternal', 'config' => ['type' => 'check', 'renderType' => 'checkboxToggle']],
    ],
];
