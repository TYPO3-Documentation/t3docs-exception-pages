<?php

declare(strict_types=1);

namespace Typo3\ExceptionPages\Tests;

use Typo3\ExceptionPages\ExceptionPage;

class ExceptionPageTest extends AbstractTestBase
{
    /**
     * @test
     */
    public function replaceBothTemplateFilesIfOutdated(): void
    {
        $exceptionPage = new ExceptionPage('1166543253');
        $exceptionPage->setTemplateLifetime(0);

        $pageDefaultModificationTime = filemtime($exceptionPage->getTemplateDir() . '/pageDefault.html');
        $pageErrorModificationTime = filemtime($exceptionPage->getTemplateDir() . '/pageError.html');

        $this->callProtected($exceptionPage, 'refreshTemplatesIfOutdated');

        $this->assertGreaterThan($pageDefaultModificationTime, filemtime($exceptionPage->getTemplateDir() . '/pageDefault.html'));
        $this->assertGreaterThan($pageErrorModificationTime, filemtime($exceptionPage->getTemplateDir() . '/pageError.html'));
    }

    /**
     * @test
     *
     * @depends replaceBothTemplateFilesIfOutdated
     */
    public function replaceBothTemplateFilesProperly(): void
    {
        $exceptionPage = new ExceptionPage('1166543253');

        $pageDefaultContent = file_get_contents($exceptionPage->getTemplateDir() . '/pageDefault.html');
        $pageErrorContent = file_get_contents($exceptionPage->getTemplateDir() . '/pageError.html');

        $this->assertStringContainsString('<h1>TYPO3 Exception [[[Exception]]]', $pageDefaultContent);
        $this->assertStringContainsString('href="?action=edit"', $pageDefaultContent);
        $this->assertStringContainsString('href="?action=source"', $pageDefaultContent);
        $this->assertStringContainsString('<h1>TYPO3 Exception [[[Exception]]]', $pageErrorContent);
        $this->assertStringNotContainsString('href="?action=edit"', $pageErrorContent);
        $this->assertStringNotContainsString('href="?action=source"', $pageErrorContent);
    }
}

