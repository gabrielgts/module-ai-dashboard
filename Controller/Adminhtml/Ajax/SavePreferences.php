<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Controller\Adminhtml\Ajax;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\User\Model\ResourceModel\User as UserResource;
use Psr\Log\LoggerInterface;

/** Persists the admin user's dashboard section order in admin_user.extra. */
class SavePreferences extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE  = 'Gtstudio_AiDashboard::view';
    private const EXTRA_PREF_KEY = 'aid_sections_order';

    /** Maximum sections we'll accept to guard against oversized payloads. */
    private const MAX_SECTIONS = 20;

    /**
     * @param Context $context
     * @param AuthSession $authSession
     * @param UserResource $userResource
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly AuthSession $authSession,
        private readonly UserResource $userResource,
        private readonly JsonFactory $jsonFactory,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute(): \Magento\Framework\Controller\Result\Json
    {
        $result = $this->jsonFactory->create();

        try {
            $user = $this->authSession->getUser();
            if (!$user) {
                return $result->setData(['success' => false, 'message' => 'Not authenticated']);
            }

            $raw   = $this->getRequest()->getParam('sections_order');
            $order = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
            $order = array_values(array_filter(array_map('strval', $order)));

            if (count($order) > self::MAX_SECTIONS) {
                return $result->setData(['success' => false, 'message' => 'Invalid payload']);
            }

            $extra = ($user->getExtra() ?: []);
            $extra[self::EXTRA_PREF_KEY] = $order;
            $user->setExtra($extra);

            $this->userResource->save($user);

            $result->setData(['success' => true]);
        } catch (\Throwable $e) {
            $this->logger->error('AiDashboard SavePreferences: ' . $e->getMessage());
            $result->setData(['success' => false, 'message' => 'Could not save preferences']);
        }

        return $result;
    }
}
