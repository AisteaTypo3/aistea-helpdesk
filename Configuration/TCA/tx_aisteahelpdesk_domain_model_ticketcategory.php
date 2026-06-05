<?php

declare(strict_types=1);

$languageFile = 'LLL:EXT:aistea_helpdesk/Resources/Private/Language/locallang_db.xlf:';

return [
    'ctrl' => [
        'title' => $languageFile . 'ticketCategory',
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
        '1' => ['showitem' => 'hidden, title, slug, is_active'],
    ],
    'columns' => [
        'hidden' => ['config' => ['type' => 'check', 'renderType' => 'checkboxToggle']],
        'title' => ['label' => $languageFile . 'field.title', 'config' => ['type' => 'input', 'required' => true, 'eval' => 'trim']],
        'slug' => ['label' => $languageFile . 'field.slug', 'config' => ['type' => 'input', 'eval' => 'trim']],
        'is_active' => ['label' => $languageFile . 'field.isActive', 'config' => ['type' => 'check', 'renderType' => 'checkboxToggle', 'default' => 1]],
    ],
];
