<?php

declare(strict_types=1);

namespace Typo3\ExceptionPages\Tests;

use PHPUnit\Framework\TestCase;
use Typo3\ExceptionPages\ExceptionCodes;

class ExceptionCodesTest extends TestCase
{
    protected static $workingDir;
    protected static $typo3Dir;
    protected static $tag;
    protected static $fileName;
    protected static $mergeFile;

    public static function setUpBeforeClass(): void
    {
        self::$workingDir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tmp/working_dir';
        self::$typo3Dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tmp/typo3';
        self::$tag = 'v10.4.9';
        self::$fileName = sprintf('exceptions-%s.json', self::$tag);
        self::$mergeFile = 'exceptions.php';
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
        $this->assertFileNotExists(self::$workingDir . DIRECTORY_SEPARATOR . self::$fileName);

        $exceptionCodes->fetchFiles(sprintf('|%s|', self::$tag));
        $this->assertFileExists(self::$workingDir . DIRECTORY_SEPARATOR . self::$fileName);
    }

    /**
     * @test
     *
     * @depends fetchFilesCreatesExceptionCodesFile
     */
    public function fetchFilesCreatesProperExceptionCodesFile(): void
    {
        $exceptionsOfFile = json_decode(file_get_contents(self::$workingDir . DIRECTORY_SEPARATOR . self::$fileName), true);

        $this->assertIsArray($exceptionsOfFile['exceptions']);
        $this->assertIsInt($exceptionsOfFile['total']);
        $this->assertEquals(count($exceptionsOfFile['exceptions']), $exceptionsOfFile['total']);
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

        $this->assertFileNotExists(self::$workingDir . DIRECTORY_SEPARATOR . self::$mergeFile);

        $exceptionCodes->mergeFiles('', self::$mergeFile);

        $this->assertFileExists(self::$workingDir . DIRECTORY_SEPARATOR . self::$mergeFile);
    }

    /**
     * @test
     *
     * @depends mergeFilesCreatesMergeFile
     */
    public function mergeFilesCreatesProperMergeFile(): void
    {
        $exceptionsOfFile = include self::$workingDir . DIRECTORY_SEPARATOR . self::$mergeFile;

        $this->assertIsArray($exceptionsOfFile['exceptions']);
        $this->assertIsInt($exceptionsOfFile['total']);
        $this->assertEquals(count($exceptionsOfFile['exceptions']), $exceptionsOfFile['total']);
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
