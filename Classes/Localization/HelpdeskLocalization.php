<?php

declare(strict_types=1);

namespace Aistea\AisteaHelpdesk\Localization;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class HelpdeskLocalization
{
    private const FRONTEND_FILE = 'LLL:EXT:aistea_helpdesk/Resources/Private/Language/locallang_frontend.xlf:';

    public function translate(string $label, ?SiteLanguage $siteLanguage = null, int $siteRootPageId = 0, int $siteLanguageId = 0): string
    {
        if (!str_starts_with($label, 'LLL:')) {
            return $label;
        }

        return $this->getLanguageService($siteLanguage, $siteRootPageId, $siteLanguageId)->sL($label) ?: $label;
    }

    /**
     * @return array<string, string>
     */
    public function getFrontendLabels(?SiteLanguage $siteLanguage = null, int $siteRootPageId = 0, int $siteLanguageId = 0): array
    {
        $prefix = self::FRONTEND_FILE;

        return [
            'kicker' => $this->translate($prefix . 'portal.kicker', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'portalTitle' => $this->translate($prefix . 'portal.title', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'portalLead' => $this->translate($prefix . 'portal.lead', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'createdLabel' => $this->translate($prefix . 'portal.createdLabel', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'createdLink' => $this->translate($prefix . 'portal.createdLink', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'tokenLabel' => $this->translate($prefix . 'portal.tokenLabel', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'newTicket' => $this->translate($prefix . 'portal.newTicket', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'subject' => $this->translate($prefix . 'field.subject', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'name' => $this->translate($prefix . 'field.name', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'email' => $this->translate($prefix . 'field.email', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'category' => $this->translate($prefix . 'field.category', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'priority' => $this->translate($prefix . 'field.priority', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'description' => $this->translate($prefix . 'field.description', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'attachments' => $this->translate($prefix . 'field.attachments', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'selectOption' => $this->translate($prefix . 'field.selectOption', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'submitTicket' => $this->translate($prefix . 'portal.submitTicket', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'myTickets' => $this->translate($prefix . 'portal.myTickets', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'viewTicket' => $this->translate($prefix . 'portal.viewTicket', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'missingDetailPid' => $this->translate($prefix . 'portal.missingDetailPid', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'noTickets' => $this->translate($prefix . 'portal.noTickets', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'loginRequiredList' => $this->translate($prefix . 'portal.loginRequiredList', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'frontendLoginRequiredCreate' => $this->translate($prefix . 'portal.frontendLoginRequiredCreate', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'timeline' => $this->translate($prefix . 'ticket.timeline', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'sendReply' => $this->translate($prefix . 'ticket.sendReply', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'replyMessage' => $this->translate($prefix . 'ticket.replyMessage', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'replyButton' => $this->translate($prefix . 'ticket.replyButton', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'ticketUnavailableTitle' => $this->translate($prefix . 'ticket.unavailableTitle', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'ticketUnavailableText' => $this->translate($prefix . 'ticket.unavailableText', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'attachmentsHeading' => $this->translate($prefix . 'ticket.attachmentsHeading', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'downloadAttachment' => $this->translate($prefix . 'ticket.downloadAttachment', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'flashFillRequired' => $this->translate($prefix . 'flash.fillRequired', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'flashTicketNotFound' => $this->translate($prefix . 'flash.ticketNotFound', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'flashReplyEmpty' => $this->translate($prefix . 'flash.replyEmpty', $siteLanguage, $siteRootPageId, $siteLanguageId),
            'mail' => [
                'brand' => $this->translate($prefix . 'mail.brand', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'autoNotice' => $this->translate($prefix . 'mail.autoNotice', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'customerTicketCreatedSubject' => $this->translate($prefix . 'mail.customerTicketCreatedSubject', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'supportTicketCreatedSubject' => $this->translate($prefix . 'mail.supportTicketCreatedSubject', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'supportCustomerReplySubject' => $this->translate($prefix . 'mail.supportCustomerReplySubject', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'customerAgentReplySubject' => $this->translate($prefix . 'mail.customerAgentReplySubject', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'customerStatusChangedSubject' => $this->translate($prefix . 'mail.customerStatusChangedSubject', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'customerTicketCreatedHeadline' => $this->translate($prefix . 'mail.customerTicketCreatedHeadline', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'supportTicketCreatedHeadline' => $this->translate($prefix . 'mail.supportTicketCreatedHeadline', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'supportCustomerReplyHeadline' => $this->translate($prefix . 'mail.supportCustomerReplyHeadline', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'customerAgentReplyHeadline' => $this->translate($prefix . 'mail.customerAgentReplyHeadline', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'customerStatusChangedHeadline' => $this->translate($prefix . 'mail.customerStatusChangedHeadline', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'hello' => $this->translate($prefix . 'mail.hello', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'customerTicketCreatedBody' => $this->translate($prefix . 'mail.customerTicketCreatedBody', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'supportTicketCreatedIntro' => $this->translate($prefix . 'mail.supportTicketCreatedIntro', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'supportCustomerReplyIntro' => $this->translate($prefix . 'mail.supportCustomerReplyIntro', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'customerAgentReplyBody' => $this->translate($prefix . 'mail.customerAgentReplyBody', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'customerStatusChangedBody' => $this->translate($prefix . 'mail.customerStatusChangedBody', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'portalLinkIntro' => $this->translate($prefix . 'mail.portalLinkIntro', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'portalLinkButton' => $this->translate($prefix . 'mail.portalLinkButton', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'portalLinkFallback' => $this->translate($prefix . 'mail.portalLinkFallback', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'portalReplyHint' => $this->translate($prefix . 'mail.portalReplyHint', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'detailsTicketNumber' => $this->translate($prefix . 'mail.details.ticketNumber', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'detailsSubject' => $this->translate($prefix . 'mail.details.subject', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'detailsStatus' => $this->translate($prefix . 'mail.details.status', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'detailsCustomer' => $this->translate($prefix . 'mail.details.customer', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'detailsPriority' => $this->translate($prefix . 'mail.details.priority', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'detailsCategory' => $this->translate($prefix . 'mail.details.category', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'detailsFrom' => $this->translate($prefix . 'mail.details.from', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'detailsOldStatus' => $this->translate($prefix . 'mail.details.oldStatus', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'detailsNewStatus' => $this->translate($prefix . 'mail.details.newStatus', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'detailsMessage' => $this->translate($prefix . 'mail.details.message', $siteLanguage, $siteRootPageId, $siteLanguageId),
                'detailsDescription' => $this->translate($prefix . 'mail.details.description', $siteLanguage, $siteRootPageId, $siteLanguageId),
            ],
        ];
    }

    private function getLanguageService(?SiteLanguage $siteLanguage = null, int $siteRootPageId = 0, int $siteLanguageId = 0)
    {
        $factory = GeneralUtility::makeInstance(LanguageServiceFactory::class);

        if ($siteLanguage instanceof SiteLanguage) {
            return $factory->createFromSiteLanguage($siteLanguage);
        }

        if ($siteRootPageId > 0) {
            try {
                $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByRootPageId($siteRootPageId);
                return $factory->createFromSiteLanguage($site->getLanguageById($siteLanguageId));
            } catch (\Throwable) {
            }
        }

        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request instanceof ServerRequestInterface) {
            $requestLanguage = $request->getAttribute('language');
            if ($requestLanguage instanceof SiteLanguage) {
                return $factory->createFromSiteLanguage($requestLanguage);
            }

            $site = $request->getAttribute('site');
            if ($site instanceof Site) {
                return $factory->createFromSiteLanguage($site->getDefaultLanguage());
            }
        }

        return $factory->create('en');
    }
}
