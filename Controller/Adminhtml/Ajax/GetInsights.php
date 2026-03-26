<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Controller\Adminhtml\Ajax;

use Gtstudio\AiAgents\Api\AgentRunInterface;
use Gtstudio\AiDashboard\Model\Service\CacheManager;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Calls the store_assistant agent with an insights prompt.
 * Results are cached by question+date hash to avoid redundant AI calls.
 */
class GetInsights extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Gtstudio_AiDashboard::chat';

    private const AGENT_CODE = 'store_assistant';

    private const DEFAULT_PROMPT =
        'Provide a brief store performance summary for today. Include revenue and order highlights, ' .
        'top selling products, customer activity, and any stock alerts. Keep it under 200 words.';

    /**
     * @param Context $context
     * @param AgentRunInterface $agentRunner
     * @param CacheManager $cacheManager
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        private readonly AgentRunInterface $agentRunner,
        private readonly CacheManager $cacheManager,
        private readonly JsonFactory $resultJsonFactory,
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $result   = $this->resultJsonFactory->create();
        $question = trim((string) $this->getRequest()->getParam('question', self::DEFAULT_PROMPT));
        $fresh    = (bool) $this->getRequest()->getParam('fresh', 0);
        $hash     = hash('sha256', $question . date('Ymd'));

        try {
            if (!$fresh) {
                $cached = $this->cacheManager->loadInsights($hash);
                if ($cached !== null) {
                    return $result->setData(['success' => true, 'content' => $cached, 'cached' => true]);
                }
            }

            $response = $this->agentRunner->run(self::AGENT_CODE, $question);
            $content  = $response['content'] ?? '';

            $this->cacheManager->saveInsights($hash, $content);

            $result->setData([
                'success' => true,
                'content' => $content,
                'cached'  => false,
                'tokens'  => [
                    'input'  => $response['input_tokens'] ?? 0,
                    'output' => $response['output_tokens'] ?? 0,
                ],
            ]);
        } catch (\Throwable $e) {
            $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }

        return $result;
    }
}
