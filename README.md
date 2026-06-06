# Aistea Helpdesk

TYPO3 v14 helpdesk extension with:
- frontend ticket portal
- frontend ticket detail with reply flow
- backend ticket module with list and board views
- ticket, message, category, priority, status and audit history records
- Fluid-based mail templates

## Current scope

- create tickets in the frontend portal
- list own tickets for logged-in frontend users
- access ticket detail by frontend user or token
- reply to existing tickets in the frontend
- manage tickets in the TYPO3 backend
- assign backend users to tickets
- update ticket status with SLA timestamps
- add public replies and internal notes in the backend
- send notification mails for ticket events
- track audit history for key ticket changes
- run bulk actions in the backend list
- work with a board/kanban-style backend view

## Backend module

The backend module currently includes:
- list view with status filters, search and assignee filter
- bulk actions for status and assignee changes
- board view grouped by ticket status
- drag and drop between board columns to change ticket status
- ticket detail view with assignment, status change, replies, notes and audit history

## SLA and workflow

- `due_at` is derived from ticket priority `resolve_hours`
- first public agent reply sets `first_response_at`
- resolved and closed states maintain `resolved_at` and `closed_at`
- customer replies can reopen resolved or closed tickets

## Notifications

Notification mails use Fluid templates:
- `Resources/Private/Templates/Email/TicketNotification.html`
- `Resources/Private/Templates/Email/TicketNotification.txt`

## Public ticket links

Ticket detail links can be opened by:
- logged-in frontend users
- token-based access via `visibility_token`

For route enhancer setups with speaking URLs, `ticket` and `token` should be configured as static route arguments so TYPO3 does not reject the link because of missing `cHash`.
