<?php

declare(strict_types=1);

namespace Study\PersonalDataBlock\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class BlockRenderingTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['fluid_styled_content'];
    protected array $testExtensionsToLoad = ['study/personal-data-block'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/content.csv');
        $sitePath = Environment::getConfigPath() . '/sites/study';
        GeneralUtility::mkdir_deep($sitePath);
        copy(__DIR__ . '/Fixtures/setup.typoscript', $sitePath . '/setup.typoscript');
        file_put_contents($sitePath . '/config.yaml', Yaml::dump([
            'rootPageId' => 1,
            'base' => 'https://study.example.test/',
            'dependencies' => ['study/personal-data-block'],
            'languages' => [[
                'title' => 'English', 'enabled' => true, 'languageId' => 0,
                'base' => '/', 'locale' => 'en-US', 'hreflang' => 'en-US',
                'navigationTitle' => 'English', 'flag' => 'global',
            ]],
        ], 5));
    }

    #[Test]
    public function rendersNameSurnameAndEmailLink(): void
    {
        $xpath = $this->renderPage();
        self::assertSame('Ada Lovelace', trim($this->textAt($xpath, '//main//p[@class="personal-data-block__name"]')));
        self::assertSame('ada@example.test', $this->textAt($xpath, '//main//a[@href="mailto:ada@example.test"]'));
    }

    #[Test]
    public function keepsColumnsSeparateAndExcludesHiddenAndOtherPageRecords(): void
    {
        $xpath = $this->renderPage();
        self::assertStringNotContainsString('Grace', $this->textAt($xpath, '//main'));
        self::assertStringContainsString('Grace Hopper', $this->textAt($xpath, '//aside'));
        self::assertStringNotContainsString('Ada', $this->textAt($xpath, '//aside'));
        $body = $this->textAt($xpath, '//body');
        self::assertStringNotContainsString('Hidden', $body);
        self::assertStringNotContainsString('Other', $body);
    }

    #[Test]
    public function escapesNamesInsteadOfRenderingMarkup(): void
    {
        $this->getConnectionPool()->getConnectionForTable('tt_content')->update('tt_content', [
            'tx_personaldatablock_name' => '<script>alert(1)</script>',
        ], ['uid' => 1]);
        $xpath = $this->renderPage();
        self::assertStringContainsString('<script>alert(1)</script>', $this->textAt($xpath, '//main'));
        self::assertSame(0.0, $xpath->evaluate('count(//main//script)'));
    }

    #[Test]
    public function backendDataHandlerSavesTheThreeFields(): void
    {
        $this->setUpBackendUser(1);
        $handler = GeneralUtility::makeInstance(DataHandler::class);
        $handler->start(['tt_content' => ['NEWperson' => [
            'pid' => 1,
            'CType' => 'personal_data_block',
            'tx_personaldatablock_name' => ' Alan ',
            'tx_personaldatablock_surname' => 'Turing',
            'tx_personaldatablock_email' => 'alan@example.test',
        ]]], []);
        $handler->process_datamap();
        self::assertSame([], $handler->errorLog);
        $uid = $handler->substNEWwithIDs['NEWperson'];
        $record = $this->getConnectionPool()->getConnectionForTable('tt_content')->select(
            ['tx_personaldatablock_name', 'tx_personaldatablock_surname', 'tx_personaldatablock_email'],
            'tt_content',
            ['uid' => $uid],
        )->fetchAssociative();
        self::assertSame([
            'tx_personaldatablock_name' => 'Alan',
            'tx_personaldatablock_surname' => 'Turing',
            'tx_personaldatablock_email' => 'alan@example.test',
        ], $record);
    }

    private function renderPage(string $path = '/'): \DOMXPath
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('https://study.example.test' . $path));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
        $html = (string)$response->getBody();
        self::assertStringNotContainsString('Oops, an error occurred', $html);

        $document = new \DOMDocument();
        // DOMDocument's HTML4 parser reports valid HTML5 elements as unknown tags.
        $previous = libxml_use_internal_errors(true);
        try {
            self::assertTrue($document->loadHTML($html));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        return new \DOMXPath($document);
    }

    private function textAt(\DOMXPath $xpath, string $expression): string
    {
        $nodes = $xpath->query($expression);
        self::assertNotFalse($nodes);
        self::assertCount(1, $nodes);
        $node = $nodes->item(0);
        self::assertInstanceOf(\DOMElement::class, $node);
        return $node->textContent;
    }
}
