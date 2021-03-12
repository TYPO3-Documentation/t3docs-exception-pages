<?php

declare(strict_types=1);

namespace Typo3\ExceptionPages\Tests\Functional;

use Typo3\ExceptionPages\ExceptionPage;

class ExceptionPageTest extends AbstractTestBase
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
        $exceptionPage = new ExceptionPage('1166543253');
        $exceptionPage->setWorkingDir(self::$workingDir);
        $exceptionPage->setTemplateLifetime(0);

        $pageDefaultPath = $exceptionPage->getTemplatesWorkingDir() . DIRECTORY_SEPARATOR . 'pageDefault.html';
        $pageErrorPath = $exceptionPage->getTemplatesWorkingDir() . DIRECTORY_SEPARATOR . 'pageError.html';

        $this->deleteDirectory($exceptionPage->getTemplatesWorkingDir());

        $this->assertFileNotExists($pageDefaultPath);
        $this->assertFileNotExists($pageErrorPath);

        $this->callProtected($exceptionPage, 'refreshTemplatesIfOutdated');

        $this->assertFileExists($pageDefaultPath);
        $this->assertFileExists($pageErrorPath);
    }

    /**
     * @test
     *
     * @depends replaceBothTemplateFilesIfOutdated
     */
    public function replaceBothTemplateFilesProperly(): void
    {
        $exceptionPage = new ExceptionPage('1166543253');
        $exceptionPage->setWorkingDir(self::$workingDir);

        $pageDefaultContent = file_get_contents($exceptionPage->getTemplatesWorkingDir() . DIRECTORY_SEPARATOR . 'pageDefault.html');
        $pageErrorContent = file_get_contents($exceptionPage->getTemplatesWorkingDir() . DIRECTORY_SEPARATOR . 'pageError.html');

        $this->assertStringContainsString('<h1>TYPO3 Exception [[[Exception]]]', $pageDefaultContent);
        $this->assertStringContainsString('href="?action=edit"', $pageDefaultContent);
        $this->assertStringContainsString('href="?action=source"', $pageDefaultContent);
        $this->assertStringContainsString('<h1>TYPO3 Exception [[[Exception]]]', $pageErrorContent);
        $this->assertStringNotContainsString('href="?action=edit"', $pageErrorContent);
        $this->assertStringNotContainsString('href="?action=source"', $pageErrorContent);
    }
}

