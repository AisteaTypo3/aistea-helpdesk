<?php

declare(strict_types=1);

namespace Aistea\AisteaHelpdesk\Application\Service;

use Aistea\AisteaHelpdesk\Localization\HelpdeskLocalization;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
                $this->buildCustomerTicketCreatedBody($ticket, $labels)
            );
        }

        $supportEmail = $this->getSupportEmail();
        if ($supportEmail !== '') {
            $this->sendMail(
                $supportEmail,
                $this->interpolate($labels['supportTicketCreatedSubject'], $ticket),
                $this->buildSupportTicketCreatedBody($ticket, $labels)
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
            $this->buildSupportCustomerReplyBody($ticket, $message, $labels)
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
            $this->buildCustomerAgentReplyBody($ticket, $message, $labels)
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
            $this->buildCustomerStatusChangedBody($ticket, $oldStatusTitle, $newStatusTitle, $labels)
        );
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function buildCustomerTicketCreatedBody(array $ticket, array $labels): string
    {
        $ticketLink = $this->buildTicketLink($ticket);
        return $this->wrapMail(
            $labels['customerTicketCreatedHeadline'],
            sprintf(
                '<p>%s %s,</p><p>%s</p>',
                $this->escape($labels['hello']),
                $this->escape((string)($ticket['customer_name'] ?? '')),
                $this->escape($this->interpolate($labels['customerTicketCreatedBody'], $ticket))
            ),
            [
                $labels['detailsTicketNumber'] => (string)($ticket['ticket_number'] ?? ''),
                $labels['detailsSubject'] => (string)($ticket['subject'] ?? ''),
                $labels['detailsStatus'] => (string)($ticket['status_title'] ?? ''),
            ],
            $labels,
            $ticketLink
        );
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function buildSupportTicketCreatedBody(array $ticket, array $labels): string
    {
        return $this->wrapMail(
            $labels['supportTicketCreatedHeadline'],
            sprintf(
                '<p>%s</p><p><strong>%s</strong></p><p>%s</p>',
                $this->escape($labels['supportTicketCreatedIntro']),
                $this->escape($labels['detailsDescription']),
                nl2br($this->escape((string)($ticket['description'] ?? '')))
            ),
            [
                $labels['detailsTicketNumber'] => (string)($ticket['ticket_number'] ?? ''),
                $labels['detailsSubject'] => (string)($ticket['subject'] ?? ''),
                $labels['detailsCustomer'] => trim((string)($ticket['customer_name'] ?? '') . ' <' . (string)($ticket['customer_email'] ?? '') . '>'),
                $labels['detailsPriority'] => (string)($ticket['priority_title'] ?? ''),
                $labels['detailsCategory'] => (string)($ticket['category_title'] ?? ''),
            ],
            $labels
        );
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function buildSupportCustomerReplyBody(array $ticket, string $message, array $labels): string
    {
        return $this->wrapMail(
            $labels['supportCustomerReplyHeadline'],
            sprintf(
                '<p>%s</p><p><strong>%s</strong></p><p>%s</p>',
                $this->escape($labels['supportCustomerReplyIntro']),
                $this->escape($labels['detailsMessage']),
                nl2br($this->escape($message))
            ),
            [
                $labels['detailsTicketNumber'] => (string)($ticket['ticket_number'] ?? ''),
                $labels['detailsSubject'] => (string)($ticket['subject'] ?? ''),
                $labels['detailsFrom'] => trim((string)($ticket['customer_name'] ?? '') . ' <' . (string)($ticket['customer_email'] ?? '') . '>'),
            ],
            $labels
        );
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function buildCustomerAgentReplyBody(array $ticket, string $message, array $labels): string
    {
        $ticketLink = $this->buildTicketLink($ticket);
        return $this->wrapMail(
            $labels['customerAgentReplyHeadline'],
            sprintf(
                '<p>%s %s,</p><p>%s</p><p><strong>%s</strong></p><p>%s</p>',
                $this->escape($labels['hello']),
                $this->escape((string)($ticket['customer_name'] ?? '')),
                $this->escape($this->interpolate($labels['customerAgentReplyBody'], $ticket)),
                $this->escape($labels['detailsMessage']),
                nl2br($this->escape($message))
            ),
            [
                $labels['detailsTicketNumber'] => (string)($ticket['ticket_number'] ?? ''),
                $labels['detailsSubject'] => (string)($ticket['subject'] ?? ''),
            ],
            $labels,
            $ticketLink
        );
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function buildCustomerStatusChangedBody(array $ticket, string $oldStatusTitle, string $newStatusTitle, array $labels): string
    {
        return $this->wrapMail(
            $labels['customerStatusChangedHeadline'],
            sprintf(
                '<p>%s %s,</p><p>%s</p>',
                $this->escape($labels['hello']),
                $this->escape((string)($ticket['customer_name'] ?? '')),
                $this->escape($this->interpolate($labels['customerStatusChangedBody'], $ticket))
            ),
            [
                $labels['detailsTicketNumber'] => (string)($ticket['ticket_number'] ?? ''),
                $labels['detailsSubject'] => (string)($ticket['subject'] ?? ''),
                $labels['detailsOldStatus'] => $oldStatusTitle,
                $labels['detailsNewStatus'] => $newStatusTitle,
            ],
            $labels
        );
    }

    private function sendMail(string $recipientEmail, string $subject, string $html): void
    {
        $senderEmail = $this->getSenderEmail();
        $mail = GeneralUtility::makeInstance(MailMessage::class);
        $mail->to($recipientEmail)
            ->subject($subject)
            ->html($html);

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

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<string, string> $facts
     * @param array<string, string> $mailLabels
     */
    private function wrapMail(string $headline, string $body, array $facts = [], array $mailLabels = [], string $ticketLink = ''): string
    {
        $factsMarkup = '';
        foreach ($facts as $label => $value) {
            $normalizedValue = trim($value);
            if ($normalizedValue === '') {
                continue;
            }

            $factsMarkup .= sprintf(
                '<tr><td style="padding:8px 0;color:#6b7280;font-size:13px;vertical-align:top;width:140px;">%s</td><td style="padding:8px 0;color:#111827;font-size:14px;font-weight:600;">%s</td></tr>',
                $this->escape($label),
                $this->escape($normalizedValue)
            );
        }

        $detailsBlock = $factsMarkup !== ''
            ? '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:0 0 24px;border-collapse:collapse;">' . $factsMarkup . '</table>'
            : '';
        $ticketLinkBlock = $ticketLink !== ''
            ? sprintf(
                '<div style="margin:24px 0 0;">'
                . '<p style="margin:0 0 14px;font-size:14px;line-height:1.6;color:#334155;">%s</p>'
                . '<p style="margin:0 0 16px;"><a href="%s" style="display:inline-block;padding:14px 20px;border-radius:999px;background:#111827;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;">%s</a></p>'
                . '<p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:#64748b;">%s</p>'
                . '<p style="margin:0;font-size:13px;line-height:1.7;word-break:break-all;"><a href="%s" style="color:#2563eb;text-decoration:none;">%s</a></p>'
                . '<p style="margin:12px 0 0;font-size:13px;line-height:1.6;color:#475569;">%s</p>'
                . '</div>',
                $this->escape($mailLabels['portalLinkIntro'] ?? ''),
                $this->escape($ticketLink),
                $this->escape($mailLabels['portalLinkButton'] ?? 'Open ticket'),
                $this->escape($mailLabels['portalLinkFallback'] ?? ''),
                $this->escape($ticketLink),
                $this->escape($ticketLink),
                $this->escape($mailLabels['portalReplyHint'] ?? '')
            )
            : '';

        return sprintf(
            '<!DOCTYPE html><html><body style="margin:0;padding:32px 18px;background:#eef2f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif;color:#111827;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%%;max-width:680px;margin:0 auto;border-collapse:collapse;">'
            . '<tr><td style="padding:0 0 18px;color:#6b7280;font-size:12px;letter-spacing:.12em;text-transform:uppercase;">%s</td></tr>'
            . '<tr><td style="background:#ffffff;border:1px solid #d7dde4;border-radius:24px;padding:32px 32px 24px;box-shadow:0 10px 30px rgba(17,24,39,.06);">'
            . '<h1 style="margin:0 0 18px;font-size:28px;line-height:1.15;color:#111827;">%s</h1>'
            . '%s'
            . '<div style="font-size:15px;line-height:1.7;color:#334155;">%s</div>%s'
            . '</td></tr>'
            . '<tr><td style="padding:16px 4px 0;color:#6b7280;font-size:12px;line-height:1.6;">%s</td></tr>'
            . '</table></body></html>',
            $this->escape($mailLabels['brand'] ?? $this->getFallbackMailBrand()),
            $this->escape($headline),
            $detailsBlock,
            $body,
            $ticketLinkBlock,
            $this->escape($mailLabels['autoNotice'] ?? $this->getFallbackMailNotice())
        );
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
