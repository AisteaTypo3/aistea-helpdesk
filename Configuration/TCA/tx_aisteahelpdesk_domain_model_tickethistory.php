<?php

declare(strict_types=1);

$languageFile = 'LLL:EXT:aistea_helpdesk/Resources/Private/Language/locallang_db.xlf:';

return [
    'ctrl' => [
        'title' => $languageFile . 'ticketHistory',
        'label' => 'action',
        'label_alt' => 'actor_name,crdate',
        'label_alt_force' => true,
        'security' => ['ignorePageTypeRestriction' => true],
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'sortby' => 'sorting',
        'delete' => 'deleted',
        'enablecolumns' => ['disabled' => 'hidden'],
        'searchFields' => 'action,actor_name,old_value,new_value,details',
        'iconfile' => 'EXT:aistea_helpdesk/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'hidden, ticket, action, actor_type, actor_name, actor_fe_user, actor_be_user, old_value, new_value, details'],
    ],
    'columns' => [
        'hidden' => ['config' => ['type' => 'check', 'renderType' => 'checkboxToggle']],
        'ticket' => ['label' => $languageFile . 'field.ticket', 'config' => ['type' => 'group', 'allowed' => 'tx_aisteahelpdesk_domain_model_ticket', 'size' => 1, 'maxitems' => 1]],
        'action' => ['label' => $languageFile . 'field.action', 'config' => ['type' => 'input', 'readOnly' => true]],
        'actor_type' => ['label' => $languageFile . 'field.actorType', 'config' => ['type' => 'input', 'readOnly' => true]],
        'actor_name' => ['label' => $languageFile . 'field.actorName', 'config' => ['type' => 'input', 'readOnly' => true]],
        'actor_fe_user' => ['label' => $languageFile . 'field.actorFeUser', 'config' => ['type' => 'group', 'allowed' => 'fe_users', 'size' => 1, 'maxitems' => 1, 'readOnly' => true]],
        'actor_be_user' => ['label' => $languageFile . 'field.actorBeUser', 'config' => ['type' => 'group', 'allowed' => 'be_users', 'size' => 1, 'maxitems' => 1, 'readOnly' => true]],
        'old_value' => ['label' => $languageFile . 'field.oldValue', 'config' => ['type' => 'input', 'readOnly' => true]],
        'new_value' => ['label' => $languageFile . 'field.newValue', 'config' => ['type' => 'input', 'readOnly' => true]],
        'details' => ['label' => $languageFile . 'field.details', 'config' => ['type' => 'text', 'rows' => 4, 'readOnly' => true]],
    ],
];
