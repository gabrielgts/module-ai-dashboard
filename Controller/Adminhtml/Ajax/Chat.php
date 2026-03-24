<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Controller\Adminhtml\Ajax;

use Gtstudio\AiAgents\Api\AgentRunInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

/** Conversational AI chat endpoint — proxies to the store_assistant agent. */
class Chat extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Gtstudio_AiDashboard::chat';

    private const AGENT_CODE = 'store_assistant';

    public function __construct(
        Context $context,
        private readonly AgentRunInterface $agentRunner,
        private readonly JsonFactory $resultJsonFactory,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result  = $this->resultJsonFactory->create();
        $message = trim((string) $this->getRequest()->getParam('message', ''));

        if ($message === '') {
            return $result->setData(['success' => false, 'message' => 'Empty message.']);
        }

        try {
            $response = $this->agentRunner->run(self::AGENT_CODE, $message);

            $result->setData([
                'success' => true,
                'content' => $response['content'] ?? '',
                'tokens'  => [
                    'input'    => $response['input_tokens'] ?? 0,
                    'output'   => $response['output_tokens'] ?? 0,
                    'model'    => $response['model'] ?? '',
                    'provider' => $response['provider'] ?? '',
                ],
            ]);
        } catch (\Throwable $e) {
            $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }

        return $result;
    }
}
