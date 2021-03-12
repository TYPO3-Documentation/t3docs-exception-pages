<?php

declare(strict_types=1);

namespace Typo3\ExceptionPages\Tests;

use Typo3\ExceptionPages\ExceptionCodes;

class ExceptionCodesTest extends AbstractTestBase
{
    protected static $workingDir;
    protected static $typo3Dir;
    protected static $tags;

    public static function setUpBeforeClass(): void
    {
        self::$workingDir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tmp/working_dir';
        self::$typo3Dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tmp/typo3';
        self::$tags = ['v10.4.8', 'v10.4.9'];
    }

    /**
     * @test
     */
    public function fetchFilesCreatesExceptionCodesFile(): void
    {
        $exceptionCodes = new ExceptionCodes();
        $exceptionCodes->setTypo3Dir(self::$typo3Dir);
        $exceptionCodes->setWorkingDir(self::$workingDir);
        // ignore existing exception codes files
        $exceptionCodes->setFilesDir(self::$workingDir);

        $exceptionCodes->deleteFilesDirs();
        foreach (self::$tags as $tag) {
            $this->assertFileNotExists(self::$workingDir . DIRECTORY_SEPARATOR . sprintf('exceptions-%s.json', $tag));
        }

        $exceptionCodes->fetchFiles(sprintf('/%s/', implode('|', self::$tags)));
        foreach (self::$tags as $tag) {
            $this->assertFileExists(self::$workingDir . DIRECTORY_SEPARATOR . sprintf('exceptions-%s.json', $tag));
        }
    }

    /**
     * @test
     *
     * @depends fetchFilesCreatesExceptionCodesFile
     */
    public function fetchFilesCreatesProperExceptionCodesFile(): void
    {
        foreach (self::$tags as $tag) {
            $exceptionsOfFile = json_decode(
                file_get_contents(self::$workingDir . DIRECTORY_SEPARATOR . sprintf('exceptions-%s.json', $tag)),
                true
            );

            $this->assertIsArray($exceptionsOfFile['exceptions']);
            $this->assertIsInt($exceptionsOfFile['total']);
            $this->assertEquals(count($exceptionsOfFile['exceptions']), $exceptionsOfFile['total']);
        }
    }

    /**
     * @test
     *
     * @depends fetchFilesCreatesProperExceptionCodesFile
     */
    public function mergeFilesCreatesMergeFile(): void
    {
        $exceptionCodes = new ExceptionCodes();
        $exceptionCodes->setTypo3Dir(self::$typo3Dir);
        $exceptionCodes->setWorkingDir(self::$workingDir);
        // ignore existing exception codes files
        $exceptionCodes->setFilesDir(self::$workingDir);

        $this->assertFileNotExists(self::$workingDir . DIRECTORY_SEPARATOR . 'exceptions.php');

        $exceptionCodes->mergeFiles();

        $this->assertFileExists(self::$workingDir . DIRECTORY_SEPARATOR . 'exceptions.php');
    }

    /**
     * @test
     *
     * @depends mergeFilesCreatesMergeFile
     */
    public function mergeFilesCreatesProperMergeFile(): void
    {
        $exceptionsOfMergeFile = include self::$workingDir . DIRECTORY_SEPARATOR . 'exceptions.php';

        $this->assertIsArray($exceptionsOfMergeFile['exceptions']);
        $this->assertIsInt($exceptionsOfMergeFile['total']);
        $this->assertEquals(count($exceptionsOfMergeFile['exceptions']), $exceptionsOfMergeFile['total']);

        foreach (self::$tags as $tag) {
            $exceptionsOfFile = json_decode(
                file_get_contents(self::$workingDir . DIRECTORY_SEPARATOR . sprintf('exceptions-%s.json', $tag)),
                true
            );

            $this->assertGreaterThanOrEqual($exceptionsOfFile['total'], $exceptionsOfMergeFile['total']);
        }
    }

    /**
     * @test
     *
     * @depends mergeFilesCreatesProperMergeFile
     */
    public function isValid(): void
    {
        $exceptionCodes = new ExceptionCodes();
        $this->assertTrue($exceptionCodes->isValid('1166543253'));
        $this->assertFalse($exceptionCodes->isValid('0123456789'));
    }
}
