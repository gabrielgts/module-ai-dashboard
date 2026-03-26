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

    /**
     * @param Context $context
     * @param AuthSession $authSession
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly AuthSession $authSession,
        private readonly StoreManagerInterface $storeManager,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get AJAX URL for fetching dashboard data.
     *
     * @return string
     */
    public function getDataUrl(): string
    {
        return $this->getUrl('aidashboard/ajax/getdata');
    }

    /**
     * Get AJAX URL for fetching AI insights.
     *
     * @return string
     */
    public function getInsightsUrl(): string
    {
        return $this->getUrl('aidashboard/ajax/getinsights');
    }

    /**
     * Get AJAX URL for the conversational chat endpoint.
     *
     * @return string
     */
    public function getChatUrl(): string
    {
        return $this->getUrl('aidashboard/ajax/chat');
    }

    /**
     * Get AJAX URL for forced snapshot refresh.
     *
     * @return string
     */
    public function getRefreshUrl(): string
    {
        return $this->getUrl('aidashboard/ajax/getdata', ['force' => 1]);
    }

    /**
     * Get the agent code used for dashboard chat.
     *
     * @return string
     */
    public function getAgentCode(): string
    {
        return self::AGENT_CODE;
    }

    /**
     * Get AJAX URL for saving section order preferences.
     *
     * @return string
     */
    public function getPreferencesUrl(): string
    {
        return $this->getUrl('aidashboard/ajax/savepreferences');
    }

    /**
     * Get saved section order for the current admin user.
     *
     * @return list<string>
     */
    public function getSectionsOrder(): array
    {
        $user  = $this->authSession->getUser();
        $extra = ($user ? $user->getExtra() : null) ?: [];

        return is_array($extra[self::EXTRA_PREF_KEY] ?? null)
            ? $extra[self::EXTRA_PREF_KEY]
            : [];
    }

    /**
     * Get store views available for the dashboard scope switcher.
     *
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

    /**
     * Check whether the current admin user can trigger a snapshot refresh.
     *
     * @return bool
     */
    public function canRefresh(): bool
    {
        return $this->_authorization->isAllowed('Gtstudio_AiDashboard::refresh');
    }

    /**
     * Check whether the current admin user has the chat/assistant ACL permission.
     *
     * @return bool
     */
    public function canUseAssistant(): bool
    {
        return $this->_authorization->isAllowed('Gtstudio_AiDashboard::chat');
    }
}
