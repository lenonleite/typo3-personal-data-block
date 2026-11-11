<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

$label = 'LLL:EXT:personal_data_block/Resources/Private/Language/locallang_db.xlf:';
ExtensionManagementUtility::addTCAcolumns('tt_content', [
    'tx_personaldatablock_name' => [
        'label' => $label . 'name',
        'config' => ['type' => 'input', 'required' => true, 'eval' => 'trim', 'max' => 100],
    ],
    'tx_personaldatablock_surname' => [
        'label' => $label . 'surname',
        'config' => ['type' => 'input', 'required' => true, 'eval' => 'trim', 'max' => 100],
    ],
    'tx_personaldatablock_email' => [
        'label' => $label . 'email',
        'config' => ['type' => 'email', 'required' => true],
    ],
]);
ExtensionManagementUtility::addRecordType(
    [
        'label' => $label . 'title',
        'value' => 'personal_data_block',
        'icon' => 'content-text',
        'group' => 'default',
        'description' => $label . 'description',
    ],
    '--palette--;;headers,tx_personaldatablock_name,tx_personaldatablock_surname,tx_personaldatablock_email',
);
