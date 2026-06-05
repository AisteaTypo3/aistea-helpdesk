<?php

declare(strict_types=1);

namespace Aistea\AisteaHelpdesk\Controller\Frontend;

use Aistea\AisteaHelpdesk\Application\Service\TicketQueryService;
use Aistea\AisteaHelpdesk\Application\Service\TicketWriteService;
use Aistea\AisteaHelpdesk\Localization\HelpdeskLocalization;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class PortalController extends ActionController
{
    public function __construct(
        private readonly TicketQueryService $ticketQueryService,
        private readonly TicketWriteService $ticketWriteService
    ) {}

    public function listAction(): ResponseInterface
    {
        $frontendUser = $this->getFrontendUser();
        $frontendUserId = (int)($frontendUser['uid'] ?? 0);
        $useFrontendUsers = (bool)($this->settings['portal']['useFrontendUsers'] ?? false);
        $showTicketList = $useFrontendUsers && ($frontendUserId > 0 || !(bool)($this->settings['portal']['requireLoginForTicketList'] ?? true));
        $identityLocked = $useFrontendUsers && $frontendUserId > 0;

        $tickets = $showTicketList && $frontendUserId > 0 ? $this->ticketQueryService->findTicketsForFrontendUser($frontendUserId) : [];
        foreach ($tickets as &$ticket) {
            $ticket['detailLink'] = $this->buildTicketLink((int)$ticket['uid'], '');
        }
        unset($ticket);
        $createdTicket = $this->request->hasArgument('createdTicketNumber')
            ? [
                'ticketNumber' => (string)$this->request->getArgument('createdTicketNumber'),
                'ticketLink' => (string)($this->request->getArgument('ticketLink') ?? ''),
                'visibilityToken' => (string)($this->request->getArgument('visibilityToken') ?? ''),
            ]
            : null;
        $labels = $this->getLocalization()->getFrontendLabels($this->getSiteLanguage());

        $this->view->assignMultiple([
            'categories' => $this->ticketQueryService->findActiveCategories(),
            'priorities' => $this->ticketQueryService->findActivePriorities(),
            'tickets' => $tickets,
            'showTicketList' => $showTicketList,
            'createdTicket' => $createdTicket,
            'frontendUser' => $frontendUser,
            'labels' => $labels,
            'useFrontendUsers' => $useFrontendUsers,
            'identityLocked' => $identityLocked,
        ]);

        return $this->htmlResponse();
    }

    public function createAction(): ResponseInterface
    {
        $payload = [
            'subject' => trim((string)($this->request->getArgument('subject') ?? '')),
            'description' => trim((string)($this->request->getArgument('description') ?? '')),
            'customerName' => trim((string)($this->request->getArgument('customerName') ?? '')),
            'customerEmail' => trim((string)($this->request->getArgument('customerEmail') ?? '')),
            'category' => (int)($this->request->getArgument('category') ?? 0),
            'priority' => (int)($this->request->getArgument('priority') ?? 0),
            'attachments' => $this->getUploadedFilesByField('attachments'),
            'siteRootPageId' => $this->getSiteRootPageId(),
            'siteLanguage' => $this->getSiteLanguageId(),
        ];

        $frontendUser = $this->getFrontendUser();
        $frontendUserId = (int)($frontendUser['uid'] ?? 0);
        $useFrontendUsers = (bool)($this->settings['portal']['useFrontendUsers'] ?? false);
        $labels = $this->getLocalization()->getFrontendLabels($this->getSiteLanguage());
        if ($useFrontendUsers) {
            if ($frontendUserId <= 0) {
                $this->addFlashMessage($labels['frontendLoginRequiredCreate'], '', \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::ERROR);
                return $this->redirect('list');
            }

            $payload['customerName'] = (string)($frontendUser['name'] ?? '');
            $payload['customerEmail'] = (string)($frontendUser['email'] ?? '');
        } elseif ($frontendUserId > 0) {
            $payload['customerName'] = $payload['customerName'] !== '' ? $payload['customerName'] : (string)($frontendUser['name'] ?? '');
            $payload['customerEmail'] = $payload['customerEmail'] !== '' ? $payload['customerEmail'] : (string)($frontendUser['email'] ?? '');
        }

        if ($payload['subject'] === '' || $payload['description'] === '' || $payload['customerName'] === '' || $payload['customerEmail'] === '') {
            $this->addFlashMessage($labels['flashFillRequired'], '', \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::ERROR);
            return $this->redirect('list');
        }

        $record = $this->ticketWriteService->createTicket($payload, $this->resolveStoragePid(), $frontendUserId);
        $ticketLink = $this->buildTicketLink((int)$record['uid'], (string)$record['visibilityToken']);
        if ($ticketLink !== '') {
            return $this->redirectToUri($ticketLink);
        }

        return $this->redirect(
            'list',
            null,
            null,
            [
                'createdTicketNumber' => (string)$record['ticketNumber'],
                'visibilityToken' => (string)$record['visibilityToken'],
                'ticketLink' => $ticketLink,
            ]
        );
    }

    private function resolveStoragePid(): int
    {
        $contentObject = $this->request->getAttribute('currentContentObject');
        if ($contentObject && method_exists($contentObject, 'getCurrentRecord')) {
            $currentRecord = (string)$contentObject->getCurrentRecord();
            if ($currentRecord !== '' && preg_match('/^tt_content:(\d+)$/', $currentRecord, $matches) === 1) {
                return (int)$contentObject->data['pid'];
            }
        }

        return (int)($GLOBALS['TSFE']->id ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function getFrontendUser(): array
    {
        $frontendUser = $GLOBALS['TSFE']->fe_user->user ?? null;
        return is_array($frontendUser) ? $frontendUser : [];
    }

    private function buildTicketLink(int $ticketUid, string $visibilityToken): string
    {
        $detailPid = $this->resolveConfiguredDetailPid();
        if ($detailPid <= 0) {
            $detailPid = $this->resolveAutomaticDetailPid();
        }

        if ($detailPid <= 0) {
            return '';
        }

        $uriBuilder = $this->uriBuilder;
        $uriBuilder->reset();
        return $uriBuilder
            ->setTargetPageUid($detailPid)
            ->uriFor('show', ['ticket' => $ticketUid, 'token' => $visibilityToken], 'Frontend\\Ticket', 'AisteaHelpdesk', 'Ticket');
    }

    private function resolveConfiguredDetailPid(): int
    {
        $detailPid = (int)($this->settings['portal']['ticketDetailPid'] ?? 0);
        if ($detailPid > 0) {
            return $detailPid;
        }

        $site = $this->request->getAttribute('site');
        if ($site instanceof \TYPO3\CMS\Core\Site\Entity\Site) {
            return (int)($site->getConfiguration()['helpdesk']['ticketDetailPid'] ?? 0);
        }

        return 0;
    }

    private function resolveAutomaticDetailPid(): int
    {
        $currentPageId = (int)($GLOBALS['TSFE']->id ?? 0);
        if ($currentPageId <= 0) {
            return 0;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();

        $pageRow = $queryBuilder
            ->select('pages.uid')
            ->from('pages')
            ->innerJoin(
                'pages',
                'tt_content',
                'tt_content',
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq('tt_content.pid', $queryBuilder->quoteIdentifier('pages.uid')),
                    $queryBuilder->expr()->eq(
                        'tt_content.CType',
                        $queryBuilder->createNamedParameter('aisteahelpdesk_ticket')
                    ),
                    $queryBuilder->expr()->eq('tt_content.deleted', $queryBuilder->createNamedParameter(0)),
                    $queryBuilder->expr()->eq('tt_content.hidden', $queryBuilder->createNamedParameter(0))
                )
            )
            ->where(
                $queryBuilder->expr()->eq('pages.pid', $queryBuilder->createNamedParameter($currentPageId)),
                $queryBuilder->expr()->eq('pages.deleted', $queryBuilder->createNamedParameter(0)),
                $queryBuilder->expr()->eq('pages.hidden', $queryBuilder->createNamedParameter(0))
            )
            ->orderBy('pages.sorting')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return (int)($pageRow['uid'] ?? 0);
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

    private function getSiteRootPageId(): int
    {
        $site = $this->request->getAttribute('site');
        return $site instanceof \TYPO3\CMS\Core\Site\Entity\Site ? (int)$site->getRootPageId() : 0;
    }

    private function getSiteLanguageId(): int
    {
        return (int)($this->getSiteLanguage()?->getLanguageId() ?? 0);
    }
}
