<?php

declare(strict_types=1);

$languageFile = 'LLL:EXT:aistea_helpdesk/Resources/Private/Language/locallang_db.xlf:';

return [
    'ctrl' => [
        'title' => $languageFile . 'ticket',
        'label' => 'ticket_number',
        'label_alt' => 'subject',
        'label_alt_force' => true,
        'security' => ['ignorePageTypeRestriction' => true],
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'sortby' => 'sorting',
        'delete' => 'deleted',
        'enablecolumns' => ['disabled' => 'hidden'],
        'searchFields' => 'ticket_number,subject,customer_name,customer_email',
        'iconfile' => 'EXT:aistea_helpdesk/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'hidden, ticket_number, subject, description, customer_name, customer_email, customer_fe_user, assigned_backend_user, category, priority, status, due_at, first_response_at, resolved_at, closed_at, site_root_page_id, site_language'],
    ],
    'columns' => [
        'hidden' => ['config' => ['type' => 'check', 'renderType' => 'checkboxToggle']],
        'ticket_number' => ['label' => $languageFile . 'field.ticketNumber', 'config' => ['type' => 'input', 'readOnly' => true]],
        'subject' => ['label' => $languageFile . 'field.subject', 'config' => ['type' => 'input', 'required' => true, 'eval' => 'trim']],
        'description' => ['label' => $languageFile . 'field.description', 'config' => ['type' => 'text', 'rows' => 6]],
        'customer_name' => ['label' => $languageFile . 'field.customerName', 'config' => ['type' => 'input', 'eval' => 'trim']],
        'customer_email' => ['label' => $languageFile . 'field.customerEmail', 'config' => ['type' => 'input', 'eval' => 'trim,email']],
        'customer_fe_user' => ['label' => $languageFile . 'field.customerFeUser', 'config' => ['type' => 'group', 'allowed' => 'fe_users', 'size' => 1, 'maxitems' => 1]],
        'assigned_backend_user' => ['label' => $languageFile . 'field.assignedBackendUser', 'config' => ['type' => 'group', 'allowed' => 'be_users', 'size' => 1, 'maxitems' => 1]],
        'category' => ['label' => $languageFile . 'field.category', 'config' => ['type' => 'select', 'renderType' => 'selectSingle', 'foreign_table' => 'tx_aisteahelpdesk_domain_model_ticketcategory']],
        'priority' => ['label' => $languageFile . 'field.priority', 'config' => ['type' => 'select', 'renderType' => 'selectSingle', 'foreign_table' => 'tx_aisteahelpdesk_domain_model_ticketpriority']],
        'status' => ['label' => $languageFile . 'field.status', 'config' => ['type' => 'select', 'renderType' => 'selectSingle', 'foreign_table' => 'tx_aisteahelpdesk_domain_model_ticketstatus']],
        'due_at' => ['label' => $languageFile . 'field.dueAt', 'config' => ['type' => 'datetime', 'default' => 0]],
        'first_response_at' => ['label' => $languageFile . 'field.firstResponseAt', 'config' => ['type' => 'datetime', 'default' => 0, 'readOnly' => true]],
        'resolved_at' => ['label' => $languageFile . 'field.resolvedAt', 'config' => ['type' => 'datetime', 'default' => 0, 'readOnly' => true]],
        'closed_at' => ['label' => $languageFile . 'field.closedAt', 'config' => ['type' => 'datetime', 'default' => 0, 'readOnly' => true]],
        'site_root_page_id' => ['label' => $languageFile . 'field.siteRootPageId', 'config' => ['type' => 'number', 'readOnly' => true, 'default' => 0]],
        'site_language' => ['label' => $languageFile . 'field.siteLanguage', 'config' => ['type' => 'number', 'readOnly' => true, 'default' => 0]],
    ],
];
