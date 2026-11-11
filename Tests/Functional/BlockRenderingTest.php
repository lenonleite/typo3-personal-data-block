<?php

declare(strict_types=1);

namespace Study\PersonalDataBlock\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class BlockRenderingTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['fluid_styled_content'];
    protected array $testExtensionsToLoad = ['study/personal-data-block'];
    protected array $configurationToUseInTestInstance = [
        'SYS' => ['caching' => ['cacheConfigurations' => ['pages' => [
            'backend' => \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
        ]]]],
    ];

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
    public function anonymousVisitorsSeeNoBlock(): void
    {
        self::assertSame(0.0, $this->renderPage()->evaluate('count(//div[@class="personal-data-block"])'));
    }

    #[Test]
    public function frontendUserSeesTheirDatabaseProfile(): void
    {
        $xpath = $this->renderPage((new InternalRequestContext())->withFrontendUserId(1));
        self::assertSame('Ada Lovelace', trim($this->textAt($xpath, '//main//p[@class="personal-data-block__name"]')));
        self::assertSame('ada@example.test', $this->textAt($xpath, '//main//a[@href="mailto:ada@example.test"]'));
        self::assertSame(1.0, $xpath->evaluate('count(//main//div[@class="personal-data-block"])'));
        self::assertSame(1.0, $xpath->evaluate('count(//aside//div[@class="personal-data-block"])'));
    }

    #[Test]
    public function backendUserSeesTheirRealNameAndEmail(): void
    {
        $xpath = $this->renderPage((new InternalRequestContext())->withBackendUserId(1));
        self::assertSame(
            'Backend Editor',
            trim($this->textAt($xpath, '(//main//p[@class="personal-data-block__name"])[1]')),
        );
        self::assertSame(
            'editor@example.test',
            $this->textAt($xpath, '(//main//a[@href="mailto:editor@example.test"])[1]'),
        );
    }

    #[Test]
    public function frontendUserTakesPriorityWhenBothAreLoggedIn(): void
    {
        $xpath = $this->renderPage((new InternalRequestContext())->withFrontendUserId(1)->withBackendUserId(1));
        self::assertStringContainsString('Ada Lovelace', $this->textAt($xpath, '//body'));
        self::assertStringNotContainsString('Backend Editor', $this->textAt($xpath, '//body'));
    }

    #[Test]
    public function cachedPageDoesNotSharePersonalDetailsBetweenVisitors(): void
    {
        $this->renderPage();
        self::assertGreaterThan(0, $this->getConnectionPool()->getConnectionForTable('cache_pages')->count(
            '*',
            'cache_pages',
            [],
        ));
        $ada = $this->renderPage((new InternalRequestContext())->withFrontendUserId(1));
        self::assertStringContainsString('Ada Lovelace', $this->textAt($ada, '//body'));
        $grace = $this->renderPage((new InternalRequestContext())->withFrontendUserId(2));
        self::assertStringContainsString('Grace Hopper', $this->textAt($grace, '//body'));
        self::assertStringNotContainsString('Ada', $this->textAt($grace, '//body'));
        self::assertStringNotContainsString('ada@example.test', $this->textAt($grace, '//body'));
        self::assertSame(0.0, $this->renderPage()->evaluate('count(//div[@class="personal-data-block"])'));
    }

    #[Test]
    public function escapesNamesInsteadOfRenderingMarkup(): void
    {
        $this->getConnectionPool()->getConnectionForTable('fe_users')->update('fe_users', [
            'first_name' => '<script>alert(1)</script>',
        ], ['uid' => 1]);
        $xpath = $this->renderPage((new InternalRequestContext())->withFrontendUserId(1));
        self::assertStringContainsString('<script>alert(1)</script>', $this->textAt($xpath, '//main'));
        self::assertSame(0.0, $xpath->evaluate('count(//main//script)'));
    }

    private function renderPage(?InternalRequestContext $context = null): \DOMXPath
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('https://study.example.test/'), $context);
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
