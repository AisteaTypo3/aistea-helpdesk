<?php

declare(strict_types=1);

namespace Aistea\AisteaHelpdesk\Controller\Frontend;

use Aistea\AisteaHelpdesk\Application\Service\TicketQueryService;
use Aistea\AisteaHelpdesk\Application\Service\TicketWriteService;
use Aistea\AisteaHelpdesk\Localization\HelpdeskLocalization;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class TicketController extends ActionController
{
    public function __construct(
        private readonly TicketQueryService $ticketQueryService,
        private readonly TicketWriteService $ticketWriteService
    ) {}

    public function showAction(int $ticket = 0, string $token = ''): ResponseInterface
    {
        $frontendUser = $this->getFrontendUser();
        $frontendUserId = (int)($frontendUser['uid'] ?? 0);
        $labels = $this->getLocalization()->getFrontendLabels($this->getSiteLanguage());
        $ticketRow = $this->ticketQueryService->findAccessibleTicket($ticket, $frontendUserId, $token);

        if (!is_array($ticketRow)) {
            $this->addFlashMessage($labels['flashTicketNotFound'], '', ContextualFeedbackSeverity::ERROR);
            $this->view->assign('labels', $labels);
            return $this->htmlResponse();
        }

        $this->view->assignMultiple([
            'ticket' => $ticketRow,
            'messages' => $this->ticketQueryService->findMessagesForTicket((int)$ticketRow['uid']),
            'frontendUser' => $frontendUser,
            'token' => $token,
            'labels' => $labels,
        ]);

        return $this->htmlResponse();
    }

    public function replyAction(int $ticket = 0, string $token = ''): ResponseInterface
    {
        $frontendUser = $this->getFrontendUser();
        $frontendUserId = (int)($frontendUser['uid'] ?? 0);
        $ticketRow = $this->ticketQueryService->findAccessibleTicket($ticket, $frontendUserId, $token);
        $labels = $this->getLocalization()->getFrontendLabels($this->getSiteLanguage());

        if (!is_array($ticketRow)) {
            $this->addFlashMessage($labels['flashTicketNotFound'], '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('show', null, null, ['ticket' => $ticket, 'token' => $token]);
        }

        $message = trim((string)($this->request->getArgument('message') ?? ''));
        if ($message === '') {
            $this->addFlashMessage($labels['flashReplyEmpty'], '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('show', null, null, ['ticket' => $ticket, 'token' => $token]);
        }

        $this->ticketWriteService->addReply(
            (int)$ticketRow['uid'],
            (int)$ticketRow['pid'],
            $message,
            $frontendUserId,
            (string)($ticketRow['customer_name'] ?? ''),
            (string)($ticketRow['customer_email'] ?? ''),
            $this->getUploadedFilesByField('attachments')
        );

        return $this->redirect('show', null, null, ['ticket' => $ticket, 'token' => $token]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getFrontendUser(): array
    {
        $frontendUser = $GLOBALS['TSFE']->fe_user->user ?? null;
        return is_array($frontendUser) ? $frontendUser : [];
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

    private function getLocalization(): HelpdeskLocalization
    {
        return GeneralUtility::makeInstance(HelpdeskLocalization::class);
    }

    private function getSiteLanguage(): ?\TYPO3\CMS\Core\Site\Entity\SiteLanguage
    {
        $language = $this->request->getAttribute('language');
        return $language instanceof \TYPO3\CMS\Core\Site\Entity\SiteLanguage ? $language : null;
    }
}
