<?php

declare(strict_types=1);

$languageFile = 'LLL:EXT:aistea_helpdesk/Resources/Private/Language/locallang_db.xlf:';

return [
    'ctrl' => [
        'title' => $languageFile . 'ticketPriority',
        'label' => 'title',
        'security' => ['ignorePageTypeRestriction' => true],
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'sortby' => 'sorting',
        'delete' => 'deleted',
        'enablecolumns' => ['disabled' => 'hidden'],
        'iconfile' => 'EXT:aistea_helpdesk/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'hidden, title, code, response_hours, resolve_hours'],
    ],
    'columns' => [
        'hidden' => ['config' => ['type' => 'check', 'renderType' => 'checkboxToggle']],
        'title' => ['label' => $languageFile . 'field.title', 'config' => ['type' => 'input', 'required' => true, 'eval' => 'trim']],
        'code' => ['label' => $languageFile . 'field.code', 'config' => ['type' => 'input', 'eval' => 'trim']],
        'response_hours' => ['label' => $languageFile . 'field.responseHours', 'config' => ['type' => 'number', 'format' => 'integer', 'default' => 0]],
        'resolve_hours' => ['label' => $languageFile . 'field.resolveHours', 'config' => ['type' => 'number', 'format' => 'integer', 'default' => 0]],
    ],
];
