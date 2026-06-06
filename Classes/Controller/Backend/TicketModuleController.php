<?php

declare(strict_types=1);

namespace Aistea\AisteaHelpdesk\Controller\Backend;

use Aistea\AisteaHelpdesk\Application\Service\TicketQueryService;
use Aistea\AisteaHelpdesk\Application\Service\TicketWriteService;
use Aistea\AisteaHelpdesk\Application\Service\NotificationService;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class TicketModuleController extends ActionController
{
    public function __construct(
        private readonly TicketQueryService $ticketQueryService,
        private readonly TicketWriteService $ticketWriteService,
        private readonly NotificationService $notificationService,
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly IconFactory $iconFactory,
        private readonly PageRenderer $pageRenderer
    ) {}

    public function indexAction(
        string $status = '',
        string $q = '',
        int $assignedBackendUser = -1,
        array $selectedTickets = [],
        string $bulkAction = '',
        int $bulkStatus = 0,
        int $bulkAssignedBackendUser = -1
    ): ResponseInterface
    {
        $filters = [
            'status' => trim($status),
            'q' => trim($q),
            'assignedBackendUser' => $assignedBackendUser,
        ];

        if ($bulkAction === 'apply') {
            $bulkResult = $this->applyBulkActions($selectedTickets, $bulkStatus, $bulkAssignedBackendUser);
            if ($bulkResult['selected'] <= 0) {
                $this->addFlashMessage('Bitte waehle mindestens ein Ticket aus.', '', ContextualFeedbackSeverity::ERROR);
            } elseif (!$bulkResult['statusChanged'] && !$bulkResult['assignmentChanged']) {
                $this->addFlashMessage('Bitte waehle eine Bulk-Aktion aus.', '', ContextualFeedbackSeverity::ERROR);
            } else {
                $messageParts = [];
                if ($bulkResult['statusChanged'] > 0) {
                    $messageParts[] = $bulkResult['statusChanged'] . ' Statusaenderung' . ($bulkResult['statusChanged'] === 1 ? '' : 'en');
                }
                if ($bulkResult['assignmentChanged'] > 0) {
                    $messageParts[] = $bulkResult['assignmentChanged'] . ' Zuweisung' . ($bulkResult['assignmentChanged'] === 1 ? '' : 'en');
                }
                $summary = implode(', ', $messageParts);
                $flashMessage = $summary !== ''
                    ? 'Bulk-Aktion abgeschlossen: ' . $summary . '.'
                    : 'Bulk-Aktion abgeschlossen.';
                if ($bulkResult['skipped'] > 0) {
                    $flashMessage .= ' ' . $bulkResult['skipped'] . ' Ticket' . ($bulkResult['skipped'] === 1 ? '' : 's') . ' uebersprungen.';
                }
                $this->addFlashMessage($flashMessage, '', ContextualFeedbackSeverity::OK);
            }

            return $this->redirect('index', null, null, $filters);
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('Helpdesk');

        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $shortcutButton = $buttonBar->makeShortcutButton()
            ->setRouteIdentifier('aistea_helpdesk')
            ->setDisplayName('Helpdesk');
        $buttonBar->addButton($shortcutButton, ButtonBar::BUTTON_POSITION_RIGHT);

        $statusCounts = $this->ticketQueryService->countTicketsByStatusCode();
        $statusFilterItems = [[
            'code' => '',
            'title' => 'Alle',
            'count' => $statusCounts['all'] ?? 0,
            'isActive' => $filters['status'] === '',
        ]];
        foreach ($this->ticketQueryService->findAllStatuses() as $statusOption) {
            $code = (string)($statusOption['code'] ?? '');
            $statusFilterItems[] = [
                'code' => $code,
                'title' => (string)($statusOption['title'] ?? ''),
                'count' => $statusCounts[$code] ?? 0,
                'isActive' => $filters['status'] === $code,
            ];
        }

        $backendUsers = $this->ticketQueryService->findAssignableBackendUsers();
        $bulkBackendUsers = array_merge([
            [
                'uid' => 0,
                'display_label' => 'Nicht zugewiesen',
            ],
        ], $backendUsers);

        $moduleTemplate->assignMultiple([
            'tickets' => $this->ticketQueryService->findAllTicketsForBackend($filters),
            'statusOptions' => $this->ticketQueryService->findAllStatuses(),
            'statusFilterItems' => $statusFilterItems,
            'backendUsers' => $backendUsers,
            'bulkBackendUsers' => $bulkBackendUsers,
            'filters' => $filters,
            'viewMode' => 'list',
            'viewSwitchItems' => $this->buildViewSwitchItems('list', $filters),
        ]);

        return $moduleTemplate->renderResponse('Backend/Ticket/Index');
    }

    public function boardAction(string $q = '', int $assignedBackendUser = -1): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('Helpdesk Board');
        $this->pageRenderer->loadJavaScriptModule('@aistea/aistea-helpdesk/helpdesk-board.js');

        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $shortcutButton = $buttonBar->makeShortcutButton()
            ->setRouteIdentifier('aistea_helpdesk')
            ->setDisplayName('Helpdesk');
        $buttonBar->addButton($shortcutButton, ButtonBar::BUTTON_POSITION_RIGHT);

        $filters = [
            'q' => trim($q),
            'assignedBackendUser' => $assignedBackendUser,
        ];
        $statusOptions = $this->ticketQueryService->findAllStatuses();
        $tickets = $this->ticketQueryService->findAllTicketsForBackend([
            'q' => $filters['q'],
            'assignedBackendUser' => $filters['assignedBackendUser'],
        ]);

        $moduleTemplate->assignMultiple([
            'boardColumns' => $this->buildBoardColumns($statusOptions, $tickets),
            'backendUsers' => $this->ticketQueryService->findAssignableBackendUsers(),
            'filters' => $filters,
            'viewMode' => 'board',
            'viewSwitchItems' => $this->buildViewSwitchItems('board', $filters),
        ]);

        return $moduleTemplate->renderResponse('Backend/Ticket/Board');
    }

    public function showAction(int $ticket = 0): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $ticketRow = $this->ticketQueryService->findTicketForBackend($ticket);
        if (!is_array($ticketRow)) {
            $this->addFlashMessage('Ticket not found.', '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('index');
        }

        $moduleTemplate->setTitle((string)$ticketRow['ticket_number']);
        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $backButton = $buttonBar->makeLinkButton()
            ->setHref($this->uriBuilder->uriFor('index'))
            ->setTitle('All tickets')
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon('actions-arrow-left', IconSize::SMALL));
        $buttonBar->addButton($backButton, ButtonBar::BUTTON_POSITION_LEFT, 1);

        $moduleTemplate->assignMultiple([
            'ticket' => $ticketRow,
            'messages' => $this->ticketQueryService->findMessagesForTicketBackend((int)$ticketRow['uid']),
            'history' => $this->ticketQueryService->findHistoryForTicketBackend((int)$ticketRow['uid']),
            'statusOptions' => $this->ticketQueryService->findAllStatuses(),
            'backendUsers' => $this->ticketQueryService->findAssignableBackendUsers(),
        ]);

        return $moduleTemplate->renderResponse('Backend/Ticket/Show');
    }

    public function updateStatusAction(
        int $ticket = 0,
        int $status = 0,
        string $returnView = 'show',
        string $q = '',
        int $assignedBackendUser = -1
    ): ResponseInterface
    {
        $ticketRow = $this->ticketQueryService->findTicketForBackend($ticket);
        if ($ticket > 0 && $status > 0) {
            $this->ticketWriteService->updateStatus($ticket, $status);
        }

        if (is_array($ticketRow) && (int)($ticketRow['status'] ?? 0) !== $status) {
            $updatedTicketRow = $this->ticketQueryService->findTicketForBackend($ticket);
            if (is_array($updatedTicketRow)) {
                $this->notificationService->notifyStatusChanged(
                    $updatedTicketRow,
                    (string)($ticketRow['status_title'] ?? ''),
                    (string)($updatedTicketRow['status_title'] ?? '')
                );
            }
        }

        if ($returnView === 'board') {
            return $this->redirect('board', null, null, [
                'q' => trim($q),
                'assignedBackendUser' => $assignedBackendUser,
            ]);
        }

        return $this->redirect('show', null, null, ['ticket' => $ticket]);
    }

    public function updateAssignmentAction(int $ticket = 0, int $assignedBackendUser = 0): ResponseInterface
    {
        if ($ticket <= 0) {
            $this->addFlashMessage('Ticket not found.', '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('index');
        }

        $ticketRow = $this->ticketQueryService->findTicketForBackend($ticket);
        if (!is_array($ticketRow)) {
            $this->addFlashMessage('Ticket not found.', '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('index');
        }

        $this->ticketWriteService->assignBackendUser($ticket, $assignedBackendUser);

        return $this->redirect('show', null, null, ['ticket' => $ticket]);
    }

    public function addInternalNoteAction(int $ticket = 0, string $message = ''): ResponseInterface
    {
        $note = trim($message);
        if ($ticket <= 0 || $note === '') {
            $this->addFlashMessage('Please enter an internal note.', '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('show', null, null, ['ticket' => $ticket]);
        }

        $backendUser = $GLOBALS['BE_USER'] ?? null;
        $backendUserId = (int)($backendUser->user['uid'] ?? 0);
        $backendUserName = trim((string)($backendUser->user['realName'] ?? $backendUser->user['username'] ?? 'Agent'));

        $ticketRow = $this->ticketQueryService->findTicketForBackend($ticket);
        if (!is_array($ticketRow)) {
            $this->addFlashMessage('Ticket not found.', '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('index');
        }

        $this->ticketWriteService->addInternalNote(
            $ticket,
            (int)$ticketRow['pid'],
            $note,
            $backendUserId,
            $backendUserName,
            $this->getUploadedFilesByField('attachments')
        );

        return $this->redirect('show', null, null, ['ticket' => $ticket]);
    }

    public function addPublicReplyAction(int $ticket = 0, string $message = ''): ResponseInterface
    {
        $reply = trim($message);
        if ($ticket <= 0 || $reply === '') {
            $this->addFlashMessage('Please enter a public reply.', '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('show', null, null, ['ticket' => $ticket]);
        }

        $backendUser = $GLOBALS['BE_USER'] ?? null;
        $backendUserId = (int)($backendUser->user['uid'] ?? 0);
        $backendUserName = trim((string)($backendUser->user['realName'] ?? $backendUser->user['username'] ?? 'Agent'));

        $ticketRow = $this->ticketQueryService->findTicketForBackend($ticket);
        if (!is_array($ticketRow)) {
            $this->addFlashMessage('Ticket not found.', '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('index');
        }

        $this->ticketWriteService->addAgentReply(
            $ticket,
            (int)$ticketRow['pid'],
            $reply,
            $backendUserId,
            $backendUserName,
            $this->getUploadedFilesByField('attachments')
        );

        return $this->redirect('show', null, null, ['ticket' => $ticket]);
    }

    /**
     * @return array<mixed>
     */
    private function getUploadedFilesByField(string $fieldName): array
    {
        return $this->normalizeUploadedFiles($this->request->getUploadedFiles()[$fieldName] ?? []);
    }

    /**
     * @param mixed $uploadedFiles
     * @return array<mixed>
     */
    private function normalizeUploadedFiles(mixed $uploadedFiles): array
    {
        if ($uploadedFiles instanceof UploadedFileInterface) {
            return [$uploadedFiles];
        }

        if (!is_array($uploadedFiles)) {
            return [];
        }

        $result = [];
        foreach ($uploadedFiles as $uploadedFile) {
            array_push($result, ...$this->normalizeUploadedFiles($uploadedFile));
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $selectedTickets
     * @return array{selected:int,statusChanged:int,assignmentChanged:int,skipped:int}
     */
    private function applyBulkActions(array $selectedTickets, int $bulkStatus, int $bulkAssignedBackendUser): array
    {
        $ticketUids = array_values(array_unique(array_filter(array_map('intval', $selectedTickets))));
        $summary = [
            'selected' => count($ticketUids),
            'statusChanged' => 0,
            'assignmentChanged' => 0,
            'skipped' => 0,
        ];

        foreach ($ticketUids as $ticketUid) {
            $ticketRow = $this->ticketQueryService->findTicketForBackend($ticketUid);
            if (!is_array($ticketRow)) {
                $summary['skipped']++;
                continue;
            }

            $ticketChanged = false;

            if ($bulkStatus > 0 && (int)($ticketRow['status'] ?? 0) !== $bulkStatus) {
                $oldStatusTitle = (string)($ticketRow['status_title'] ?? '');
                $this->ticketWriteService->updateStatus($ticketUid, $bulkStatus);
                $updatedTicketRow = $this->ticketQueryService->findTicketForBackend($ticketUid);
                if (is_array($updatedTicketRow)) {
                    $this->notifyStatusChange($ticketRow, $updatedTicketRow);
                } else {
                    $this->notifyStatusChange($ticketRow, null, $oldStatusTitle);
                }
                $summary['statusChanged']++;
                $ticketChanged = true;
            }

            if ($bulkAssignedBackendUser >= 0 && (int)($ticketRow['assigned_backend_user'] ?? 0) !== $bulkAssignedBackendUser) {
                $this->ticketWriteService->assignBackendUser($ticketUid, $bulkAssignedBackendUser);
                $summary['assignmentChanged']++;
                $ticketChanged = true;
            }

            if (!$ticketChanged) {
                $summary['skipped']++;
            }
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $oldTicketRow
     * @param array<string, mixed>|null $newTicketRow
     */
    private function notifyStatusChange(array $oldTicketRow, ?array $newTicketRow, string $fallbackStatusTitle = ''): void
    {
        if (!is_array($newTicketRow)) {
            return;
        }

        $this->notificationService->notifyStatusChanged(
            $newTicketRow,
            (string)($oldTicketRow['status_title'] ?? $fallbackStatusTitle),
            (string)($newTicketRow['status_title'] ?? '')
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function buildViewSwitchItems(string $activeView, array $filters): array
    {
        return [
            [
                'label' => 'Liste',
                'action' => 'index',
                'arguments' => [
                    'q' => trim((string)($filters['q'] ?? '')),
                    'assignedBackendUser' => (int)($filters['assignedBackendUser'] ?? -1),
                ],
                'isActive' => $activeView === 'list',
            ],
            [
                'label' => 'Board',
                'action' => 'board',
                'arguments' => [
                    'q' => trim((string)($filters['q'] ?? '')),
                    'assignedBackendUser' => (int)($filters['assignedBackendUser'] ?? -1),
                ],
                'isActive' => $activeView === 'board',
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $statusOptions
     * @param array<int, array<string, mixed>> $tickets
     * @return array<int, array<string, mixed>>
     */
    private function buildBoardColumns(array $statusOptions, array $tickets): array
    {
        $columns = [];
        foreach ($statusOptions as $index => $statusOption) {
            $statusUid = (int)($statusOption['uid'] ?? 0);
            $columns[$statusUid] = [
                'uid' => $statusUid,
                'title' => (string)($statusOption['title'] ?? ''),
                'code' => (string)($statusOption['code'] ?? ''),
                'tickets' => [],
                'previousStatusUid' => isset($statusOptions[$index - 1]) ? (int)($statusOptions[$index - 1]['uid'] ?? 0) : 0,
                'nextStatusUid' => isset($statusOptions[$index + 1]) ? (int)($statusOptions[$index + 1]['uid'] ?? 0) : 0,
                'previousStatusTitle' => isset($statusOptions[$index - 1]) ? (string)($statusOptions[$index - 1]['title'] ?? '') : '',
                'nextStatusTitle' => isset($statusOptions[$index + 1]) ? (string)($statusOptions[$index + 1]['title'] ?? '') : '',
            ];
        }

        foreach ($tickets as $ticket) {
            $statusUid = (int)($ticket['status'] ?? 0);
            if (!isset($columns[$statusUid])) {
                continue;
            }
            $columns[$statusUid]['tickets'][] = $ticket;
        }

        return array_values($columns);
    }
}
