<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Store\Model\StoreManagerInterface;

/** Provides URL helpers and ACL checks to the dashboard template. */
class Dashboard extends Template
{
    private const AGENT_CODE    = 'store_assistant';
    private const EXTRA_PREF_KEY = 'aid_sections_order';

    public function __construct(
        Context $context,
        private readonly AuthSession $authSession,
        private readonly StoreManagerInterface $storeManager,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getDataUrl(): string
    {
        return $this->getUrl('aidashboard/ajax/getdata');
    }

    public function getInsightsUrl(): string
    {
        return $this->getUrl('aidashboard/ajax/getinsights');
    }

    public function getChatUrl(): string
    {
        return $this->getUrl('aidashboard/ajax/chat');
    }

    public function getRefreshUrl(): string
    {
        return $this->getUrl('aidashboard/ajax/getdata', ['force' => 1]);
    }

    public function getAgentCode(): string
    {
        return self::AGENT_CODE;
    }

    public function getPreferencesUrl(): string
    {
        return $this->getUrl('aidashboard/ajax/savepreferences');
    }

    /** @return list<string> Saved section order for the current admin user. */
    public function getSectionsOrder(): array
    {
        $user  = $this->authSession->getUser();
        $extra = ($user ? $user->getExtra() : null) ?: [];

        return is_array($extra[self::EXTRA_PREF_KEY] ?? null)
            ? $extra[self::EXTRA_PREF_KEY]
            : [];
    }

    /**
     * Returns store views available for the dashboard scope switcher.
     * Returns an empty array when running in single-store mode.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public function getStoreList(): array
    {
        if ($this->storeManager->isSingleStoreMode()) {
            return [];
        }

        $stores = [];
        foreach ($this->storeManager->getStores() as $store) {
            $stores[] = [
                'value' => (int) $store->getId(),
                'label' => $store->getName(),
            ];
        }

        return $stores;
    }

    public function canRefresh(): bool
    {
        return $this->_authorization->isAllowed('Gtstudio_AiDashboard::refresh');
    }

    public function canUseAssistant(): bool
    {
        return $this->_authorization->isAllowed('Gtstudio_AiDashboard::chat');
    }
}
