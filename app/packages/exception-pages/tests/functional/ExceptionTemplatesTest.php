<?php

declare(strict_types=1);

namespace Typo3\ExceptionPages\Tests\Functional;

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

        $this->assertStringContainsString('<h1>TYPO3 Exception 1234567890', $pageDefaultContent);
        $this->assertStringContainsString('href="?action=edit"', $pageDefaultContent);
        $this->assertStringContainsString('href="?action=source"', $pageDefaultContent);
        $this->assertStringContainsString('<h1>TYPO3 Exception 1234567890', $pageErrorContent);
        $this->assertStringNotContainsString('href="?action=edit"', $pageErrorContent);
        $this->assertStringNotContainsString('href="?action=source"', $pageErrorContent);
    }
}

