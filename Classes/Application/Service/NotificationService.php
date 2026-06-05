<?php

declare(strict_types=1);

namespace Aistea\AisteaHelpdesk\Application\Service;

use Aistea\AisteaHelpdesk\Localization\HelpdeskLocalization;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\TemplatePaths;

final class NotificationService
{
    public function __construct(
        private readonly ?MailerInterface $mailer = null,
        private readonly ?HelpdeskLocalization $localization = null
    ) {}

    /**
     * @param array<string, mixed> $ticket
     */
    public function notifyTicketCreated(array $ticket): void
    {
        $labels = $this->getMailLabels($ticket);
        $customerEmail = $this->normalizeEmail((string)($ticket['customer_email'] ?? ''));
        if ($customerEmail !== '') {
            $this->sendMail(
                $customerEmail,
                $this->interpolate($labels['customerTicketCreatedSubject'], $ticket),
                $this->buildCustomerTicketCreatedMail($ticket, $labels)
            );
        }

        $supportEmail = $this->getSupportEmail();
        if ($supportEmail !== '') {
            $this->sendMail(
                $supportEmail,
                $this->interpolate($labels['supportTicketCreatedSubject'], $ticket),
                $this->buildSupportTicketCreatedMail($ticket, $labels)
            );
        }
    }

    /**
     * @param array<string, mixed> $ticket
     */
    public function notifyCustomerReply(array $ticket, string $message): void
    {
        $supportEmail = $this->getSupportEmail();
        if ($supportEmail === '') {
            return;
        }
        $labels = $this->getMailLabels($ticket);

        $this->sendMail(
            $supportEmail,
            $this->interpolate($labels['supportCustomerReplySubject'], $ticket),
            $this->buildSupportCustomerReplyMail($ticket, $message, $labels)
        );
    }

    /**
     * @param array<string, mixed> $ticket
     */
    public function notifyAgentReply(array $ticket, string $message): void
    {
        $customerEmail = $this->normalizeEmail((string)($ticket['customer_email'] ?? ''));
        if ($customerEmail === '') {
            return;
        }
        $labels = $this->getMailLabels($ticket);

        $this->sendMail(
            $customerEmail,
            $this->interpolate($labels['customerAgentReplySubject'], $ticket),
            $this->buildCustomerAgentReplyMail($ticket, $message, $labels)
        );
    }

    /**
     * @param array<string, mixed> $ticket
     */
    public function notifyStatusChanged(array $ticket, string $oldStatusTitle, string $newStatusTitle): void
    {
        $customerEmail = $this->normalizeEmail((string)($ticket['customer_email'] ?? ''));
        if ($customerEmail === '' || $oldStatusTitle === $newStatusTitle) {
            return;
        }
        $labels = $this->getMailLabels($ticket);

        $this->sendMail(
            $customerEmail,
            $this->interpolate($labels['customerStatusChangedSubject'], $ticket),
            $this->buildCustomerStatusChangedMail($ticket, $oldStatusTitle, $newStatusTitle, $labels)
        );
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function buildCustomerTicketCreatedMail(array $ticket, array $labels): array
    {
        return $this->buildMailContext($ticket, $labels, [
            'headline' => $labels['customerTicketCreatedHeadline'],
            'greetingName' => (string)($ticket['customer_name'] ?? ''),
            'intro' => $this->interpolate($labels['customerTicketCreatedBody'], $ticket),
            'facts' => [
                ['label' => $labels['detailsTicketNumber'], 'value' => (string)($ticket['ticket_number'] ?? '')],
                ['label' => $labels['detailsSubject'], 'value' => (string)($ticket['subject'] ?? '')],
                ['label' => $labels['detailsStatus'], 'value' => (string)($ticket['status_title'] ?? '')],
            ],
            'ticketLink' => $this->buildTicketLink($ticket),
        ]);
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function buildSupportTicketCreatedMail(array $ticket, array $labels): array
    {
        return $this->buildMailContext($ticket, $labels, [
            'headline' => $labels['supportTicketCreatedHeadline'],
            'intro' => $labels['supportTicketCreatedIntro'],
            'messageLabel' => $labels['detailsDescription'],
            'message' => (string)($ticket['description'] ?? ''),
            'facts' => [
                ['label' => $labels['detailsTicketNumber'], 'value' => (string)($ticket['ticket_number'] ?? '')],
                ['label' => $labels['detailsSubject'], 'value' => (string)($ticket['subject'] ?? '')],
                ['label' => $labels['detailsCustomer'], 'value' => trim((string)($ticket['customer_name'] ?? '') . ' <' . (string)($ticket['customer_email'] ?? '') . '>')],
                ['label' => $labels['detailsPriority'], 'value' => (string)($ticket['priority_title'] ?? '')],
                ['label' => $labels['detailsCategory'], 'value' => (string)($ticket['category_title'] ?? '')],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function buildSupportCustomerReplyMail(array $ticket, string $message, array $labels): array
    {
        return $this->buildMailContext($ticket, $labels, [
            'headline' => $labels['supportCustomerReplyHeadline'],
            'intro' => $labels['supportCustomerReplyIntro'],
            'messageLabel' => $labels['detailsMessage'],
            'message' => $message,
            'facts' => [
                ['label' => $labels['detailsTicketNumber'], 'value' => (string)($ticket['ticket_number'] ?? '')],
                ['label' => $labels['detailsSubject'], 'value' => (string)($ticket['subject'] ?? '')],
                ['label' => $labels['detailsFrom'], 'value' => trim((string)($ticket['customer_name'] ?? '') . ' <' . (string)($ticket['customer_email'] ?? '') . '>')],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function buildCustomerAgentReplyMail(array $ticket, string $message, array $labels): array
    {
        return $this->buildMailContext($ticket, $labels, [
            'headline' => $labels['customerAgentReplyHeadline'],
            'greetingName' => (string)($ticket['customer_name'] ?? ''),
            'intro' => $this->interpolate($labels['customerAgentReplyBody'], $ticket),
            'messageLabel' => $labels['detailsMessage'],
            'message' => $message,
            'facts' => [
                ['label' => $labels['detailsTicketNumber'], 'value' => (string)($ticket['ticket_number'] ?? '')],
                ['label' => $labels['detailsSubject'], 'value' => (string)($ticket['subject'] ?? '')],
            ],
            'ticketLink' => $this->buildTicketLink($ticket),
        ]);
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function buildCustomerStatusChangedMail(array $ticket, string $oldStatusTitle, string $newStatusTitle, array $labels): array
    {
        return $this->buildMailContext($ticket, $labels, [
            'headline' => $labels['customerStatusChangedHeadline'],
            'greetingName' => (string)($ticket['customer_name'] ?? ''),
            'intro' => $this->interpolate($labels['customerStatusChangedBody'], $ticket),
            'facts' => [
                ['label' => $labels['detailsTicketNumber'], 'value' => (string)($ticket['ticket_number'] ?? '')],
                ['label' => $labels['detailsSubject'], 'value' => (string)($ticket['subject'] ?? '')],
                ['label' => $labels['detailsOldStatus'], 'value' => $oldStatusTitle],
                ['label' => $labels['detailsNewStatus'], 'value' => $newStatusTitle],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $templateData
     */
    private function sendMail(string $recipientEmail, string $subject, array $templateData): void
    {
        $senderEmail = $this->getSenderEmail();
        $mail = GeneralUtility::makeInstance(FluidEmail::class, $this->createTemplatePaths());
        $mail->to($recipientEmail)
            ->subject($subject)
            ->format(FluidEmail::FORMAT_BOTH)
            ->setTemplate('TicketNotification')
            ->assignMultiple($templateData);

        if ($senderEmail !== '') {
            $mail->from(new Address($senderEmail, $this->getSenderName()));
        }

        $this->getMailer()->send($mail);
    }

    private function getSupportEmail(): string
    {
        return $this->normalizeEmail((string)($GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] ?? ''));
    }

    private function getSenderEmail(): string
    {
        return $this->normalizeEmail((string)($GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] ?? ''));
    }

    private function getSenderName(): string
    {
        $senderName = trim((string)($GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] ?? ''));
        return $senderName !== '' ? $senderName : 'Helpdesk';
    }

    private function normalizeEmail(string $email): string
    {
        $email = trim($email);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    /**
     * @param array<string, mixed> $ticket
     * @param array<string, string> $labels
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildMailContext(array $ticket, array $labels, array $context): array
    {
        $facts = [];
        foreach ((array)($context['facts'] ?? []) as $fact) {
            $value = trim((string)($fact['value'] ?? ''));
            if ($value !== '') {
                $facts[] = [
                    'label' => (string)($fact['label'] ?? ''),
                    'value' => $value,
                ];
            }
        }

        return [
            'ticket' => $ticket,
            'labels' => $labels,
            'brand' => $labels['brand'] ?? $this->getFallbackMailBrand(),
            'autoNotice' => $labels['autoNotice'] ?? $this->getFallbackMailNotice(),
            'hello' => $labels['hello'] ?? 'Hello',
            'headline' => (string)($context['headline'] ?? ''),
            'greetingName' => (string)($context['greetingName'] ?? ''),
            'intro' => (string)($context['intro'] ?? ''),
            'messageLabel' => (string)($context['messageLabel'] ?? ''),
            'message' => (string)($context['message'] ?? ''),
            'facts' => $facts,
            'ticketLink' => (string)($context['ticketLink'] ?? ''),
        ];
    }

    private function createTemplatePaths(): TemplatePaths
    {
        $templatePaths = GeneralUtility::makeInstance(TemplatePaths::class);
        $basePath = ExtensionManagementUtility::extPath('aistea_helpdesk') . 'Resources/Private/';
        $templatePaths->setTemplateRootPaths([$basePath . 'Templates/Email/']);
        $templatePaths->setLayoutRootPaths([$basePath . 'Layouts/Email/']);
        $templatePaths->setPartialRootPaths([$basePath . 'Partials/Email/']);

        return $templatePaths;
    }

    private function getMailer(): MailerInterface
    {
        return $this->mailer ?? GeneralUtility::makeInstance(MailerInterface::class);
    }

    /**
     * @param array<string, mixed> $ticket
     * @return array<string, string>
     */
    private function getMailLabels(array $ticket): array
    {
        return $this->getLocalization()->getFrontendLabels(
            null,
            (int)($ticket['site_root_page_id'] ?? 0),
            (int)($ticket['site_language'] ?? 0)
        )['mail'];
    }

    private function getLocalization(): HelpdeskLocalization
    {
        return $this->localization ?? GeneralUtility::makeInstance(HelpdeskLocalization::class);
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function interpolate(string $text, array $ticket): string
    {
        return strtr($text, [
            '{ticketNumber}' => (string)($ticket['ticket_number'] ?? ''),
            '{subject}' => (string)($ticket['subject'] ?? ''),
            '{status}' => (string)($ticket['status_title'] ?? ''),
        ]);
    }

    private function getFallbackMailBrand(): string
    {
        $labels = $this->getLocalization()->getFrontendLabels();
        return (string)($labels['mail']['brand'] ?? 'Aistea Helpdesk');
    }

    private function getFallbackMailNotice(): string
    {
        $labels = $this->getLocalization()->getFrontendLabels();
        return (string)($labels['mail']['autoNotice'] ?? 'This message was sent automatically by the helpdesk.');
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function buildTicketLink(array $ticket): string
    {
        $siteRootPageId = (int)($ticket['site_root_page_id'] ?? 0);
        $ticketUid = (int)($ticket['uid'] ?? 0);
        $visibilityToken = trim((string)($ticket['visibility_token'] ?? ''));

        if ($siteRootPageId <= 0 || $ticketUid <= 0 || $visibilityToken === '') {
            return '';
        }

        try {
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByRootPageId($siteRootPageId);
            $detailPage = $this->resolveTicketDetailPage($site);
            $detailPid = (int)($detailPage['uid'] ?? 0);
            $detailSlug = trim((string)($detailPage['slug'] ?? ''));
            if ($detailPid <= 0 || $detailSlug === '') {
                return '';
            }

            try {
                $siteBase = (string)$site->getLanguageById((int)($ticket['site_language'] ?? 0))->getBase();
            } catch (\Throwable) {
                $siteBase = (string)$site->getBase();
            }

            return rtrim($siteBase, '/') . '/' . ltrim($detailSlug, '/') . '/' . rawurlencode((string)$ticketUid) . '/' . rawurlencode($visibilityToken);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @return array{uid?: int|string, slug?: string}|array{}
     */
    private function resolveTicketDetailPage(\TYPO3\CMS\Core\Site\Entity\Site $site): array
    {
        $configuredPageId = (int)($site->getConfiguration()['helpdesk']['ticketDetailPid'] ?? 0);
        if ($configuredPageId > 0) {
            $pageRow = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)
                ->getConnectionForTable('pages')
                ->select(['uid', 'slug'], 'pages', ['uid' => $configuredPageId])
                ->fetchAssociative();
            if (is_array($pageRow)) {
                return $pageRow;
            }
        }

        $siteRootPageId = (int)$site->getRootPageId();
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $queryBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();

        $pageRows = $queryBuilder
            ->select('pid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('aisteahelpdesk_ticket')),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0)),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0))
            )
            ->executeQuery()
            ->fetchFirstColumn();

        foreach ($pageRows as $pageId) {
            try {
                $site = $siteFinder->getSiteByPageId((int)$pageId);
                if ((int)$site->getRootPageId() === $siteRootPageId) {
                    $pageRow = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)
                        ->getConnectionForTable('pages')
                        ->select(['uid', 'slug'], 'pages', ['uid' => (int)$pageId])
                        ->fetchAssociative();

                    return is_array($pageRow) ? $pageRow : [];
                }
            } catch (\Throwable) {
            }
        }

        return [];
    }
}
