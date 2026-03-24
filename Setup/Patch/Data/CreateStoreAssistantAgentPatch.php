<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Setup\Patch\Data;

use Gtstudio\AiAgents\Api\GetAiAgentByCodeInterface;
use Gtstudio\AiAgents\Api\SaveAiAgentInterface;
use Gtstudio\AiAgents\Model\Data\AiAgentData;
use Gtstudio\AiAgents\Model\Data\AiAgentDataFactory;
use Gtstudio\AiDataQuery\Setup\Patch\Data\BootstrapPhase2SpecializedTools;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/** Seeds the store_assistant agent with analytics tools for the AI Dashboard. */
class CreateStoreAssistantAgentPatch implements DataPatchInterface
{
    private const AGENT_CODE = 'store_assistant';

    private const TOOL_CODES = [
        'order_analytics',
        'customer_lifetime_value',
        'product_performance',
        'query_entity',
    ];

    public function __construct(
        private readonly GetAiAgentByCodeInterface $getAiAgentByCode,
        private readonly SaveAiAgentInterface $saveAiAgent,
        private readonly AiAgentDataFactory $agentFactory,
        private readonly ResourceConnection $resourceConnection,
    ) {
    }

    public function apply(): self
    {
        if ($this->agentExists()) {
            return $this;
        }

        /** @var AiAgentData $agent */
        $agent = $this->agentFactory->create();
        $agent->setCode(self::AGENT_CODE);
        $agent->setDescription(
            'AI store analyst embedded in the admin dashboard. ' .
            'Answers natural-language questions about sales, customers, products, and inventory.'
        );
        $agent->setBackground(
            "You are an expert Magento eCommerce analyst embedded in the store admin dashboard.\n" .
            "You have access to real-time store data through tools that query orders, customers, products, and inventory.\n" .
            "Always use tools to fetch data — never invent or estimate numbers.\n" .
            "Be concise. Prefer bullet points and tables over paragraphs.\n" .
            "When comparing periods include both absolute and percentage change."
        );
        $agent->setSteps(
            "1. Identify what domain the question covers (orders, customers, products, stock, revenue).\n" .
            "2. Call the most appropriate tool — or multiple tools if needed.\n" .
            "3. Analyse the result: headline figure, trend direction, outliers.\n" .
            "4. Compose a clear, data-driven response."
        );
        $agent->setOutput(
            "1. **Headline** — direct answer in one sentence.\n" .
            "2. **Detail** — supporting figures as a markdown table or bullet list.\n" .
            "3. **Trend** — comparison vs prior period where relevant.\n" .
            "4. **Action** — one concrete recommendation if applicable.\n" .
            "Keep total response under 300 words unless more detail is explicitly requested."
        );

        $toolIds = $this->resolveToolIds();
        if (!empty($toolIds)) {
            $agent->setTools(implode(',', $toolIds));
        }

        $agent->setCronEnabled(false);

        $this->saveAiAgent->execute($agent);

        return $this;
    }

    public static function getDependencies(): array
    {
        return [BootstrapPhase2SpecializedTools::class];
    }

    public function getAliases(): array
    {
        return [];
    }

    private function agentExists(): bool
    {
        try {
            $this->getAiAgentByCode->execute(self::AGENT_CODE);
            return true;
        } catch (NoSuchEntityException) {
            return false;
        }
    }

    private function resolveToolIds(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName('gtstudio_ai_tools');

        return $connection->fetchCol(
            $connection->select()->from($table, ['entity_id'])->where('code IN (?)', self::TOOL_CODES)
        );
    }
}
