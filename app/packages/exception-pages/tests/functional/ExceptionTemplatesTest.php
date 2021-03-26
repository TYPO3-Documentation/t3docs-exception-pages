<?php

declare(strict_types=1);

namespace Typo3\ExceptionPages\Tests\Functional;

use Symfony\Component\DomCrawler\Crawler;
use Typo3\ExceptionPages\ExceptionTemplates;

class ExceptionTemplatesTest extends AbstractTestBase
{
    protected static $workingDir;

    public static function setUpBeforeClass(): void
    {
        self::$workingDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp';
    }

    /**
     * @test
     */
    public function replaceBothTemplateFilesIfOutdated(): void
    {
        $exceptionTemplates = new ExceptionTemplates();
        $exceptionTemplates->setWorkingDir(self::$workingDir);
        $exceptionTemplates->setLifetime(0);

        $this->deleteDirectory($exceptionTemplates->getTemplatesWorkingDir());

        $this->assertFileNotExists($exceptionTemplates->getDefaultPagePath());
        $this->assertFileNotExists($exceptionTemplates->getErrorPagePath());

        $exceptionTemplates->refreshTemplatesIfOutdated();

        $this->assertFileExists($exceptionTemplates->getDefaultPagePath());
        $this->assertFileExists($exceptionTemplates->getErrorPagePath());
    }

    /**
     * @test
     *
     * @depends replaceBothTemplateFilesIfOutdated
     */
    public function replaceBothTemplateFilesProperly(): void
    {
        $exceptionTemplates = new ExceptionTemplates();
        $exceptionTemplates->setWorkingDir(self::$workingDir);

        $pageDefaultContent = $exceptionTemplates->renderDefaultPage('1234567890');
        $pageErrorContent = $exceptionTemplates->renderErrorPage('1234567890');

        $crawlerDefault = new Crawler($pageDefaultContent);
        $this->assertSame(1, $crawlerDefault->filterXPath('//h1[starts-with(., "TYPO3 Exception 1234567890")]')->count());
        $this->assertSame(1, $crawlerDefault->filterXPath('//a[@href = "?action=edit"]')->count());
        $this->assertSame(0, $crawlerDefault->filterXPath('//a[contains(@href, "/_sources/")]')->count());
        $this->assertSame(1, $crawlerDefault->filterXPath('//div[@class="toc-collapse"]/div[@class="toc"]')->count());
        $this->assertSame(0, $crawlerDefault->filterXPath('//div[@class="toc-collapse"]//p[span/text() = "PAGE CONTENTS"]')->count());

        $crawlerError = new Crawler($pageErrorContent);
        $this->assertSame(1, $crawlerError->filterXPath('//h1[starts-with(., "TYPO3 Exception 1234567890")]')->count());
        $this->assertSame(0, $crawlerError->filterXPath('//a[@href = "?action=edit"]')->count());
        $this->assertSame(0, $crawlerDefault->filterXPath('//a[contains(@href, "/_sources/")]')->count());
        $this->assertSame(1, $crawlerError->filterXPath('//div[@class="toc-collapse"]/div[@class="toc"]')->count());
        $this->assertSame(0, $crawlerError->filterXPath('//div[@class="toc-collapse"]//p[span/text() = "PAGE CONTENTS"]')->count());
    }
}

