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
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class TicketModuleController extends ActionController
{
    public function __construct(
        private readonly TicketQueryService $ticketQueryService,
        private readonly TicketWriteService $ticketWriteService,
        private readonly NotificationService $notificationService,
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly IconFactory $iconFactory
    ) {}

    public function indexAction(string $status = '', string $q = '', int $assignedBackendUser = -1): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('Helpdesk');

        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $shortcutButton = $buttonBar->makeShortcutButton()
            ->setRouteIdentifier('aistea_helpdesk')
            ->setDisplayName('Helpdesk');
        $buttonBar->addButton($shortcutButton, ButtonBar::BUTTON_POSITION_RIGHT);

        $filters = [
            'status' => trim($status),
            'q' => trim($q),
            'assignedBackendUser' => $assignedBackendUser,
        ];
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

        $moduleTemplate->assignMultiple([
            'tickets' => $this->ticketQueryService->findAllTicketsForBackend($filters),
            'statusOptions' => $this->ticketQueryService->findAllStatuses(),
            'statusFilterItems' => $statusFilterItems,
            'backendUsers' => $this->ticketQueryService->findAssignableBackendUsers(),
            'filters' => $filters,
        ]);

        return $moduleTemplate->renderResponse('Backend/Ticket/Index');
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

    public function updateStatusAction(int $ticket = 0, int $status = 0): ResponseInterface
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
}
