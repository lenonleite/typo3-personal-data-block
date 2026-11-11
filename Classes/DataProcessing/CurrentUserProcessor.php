<?php

declare(strict_types=1);

namespace Study\PersonalDataBlock\DataProcessing;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

final readonly class CurrentUserProcessor implements DataProcessorInterface
{
    private Context $context;
    private ConnectionPool $connectionPool;

    public function __construct(Context $context, ConnectionPool $connectionPool)
    {
        $this->context = $context;
        $this->connectionPool = $connectionPool;
    }

    /**
     * @param array<string, mixed> $contentObjectConfiguration
     * @param array<string, mixed> $processorConfiguration
     * @param array<string, mixed> $processedData
     * @return array<string, mixed>
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData,
    ): array {
        $processedData['person'] = null;
        $frontendId = (int)$this->context->getPropertyFromAspect('frontend.user', 'id', 0);
        $backendId = (int)$this->context->getPropertyFromAspect('backend.user', 'id', 0);
        if ($frontendId <= 0 && $backendId <= 0) {
            return $processedData;
        }

        $isFrontend = $frontendId > 0;
        $table = $isFrontend ? 'fe_users' : 'be_users';
        $fields = $isFrontend ? ['first_name', 'last_name', 'email'] : ['realName', 'email'];
        $row = $this->connectionPool->getConnectionForTable($table)->select(
            $fields,
            $table,
            ['uid' => $isFrontend ? $frontendId : $backendId, 'deleted' => 0, 'disable' => 0],
        )->fetchAssociative();
        if ($row !== false) {
            $processedData['person'] = [
                'name' => $isFrontend ? trim($row['first_name'] . ' ' . $row['last_name']) : $row['realName'],
                'email' => $row['email'],
            ];
        }
        return $processedData;
    }
}
