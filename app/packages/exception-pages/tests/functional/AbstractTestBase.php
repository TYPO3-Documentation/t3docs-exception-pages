<?php

declare(strict_types=1);

namespace Typo3\ExceptionPages\Tests\Functional;

use PHPUnit\Framework\TestCase;

abstract class AbstractTestBase extends TestCase
{
    public function callProtected($mockedInstance, $method, ...$params)
    {
        $reflectedMethod = new \ReflectionMethod(get_class($mockedInstance), $method);
        $reflectedMethod->setAccessible(true);
        return $reflectedMethod->invokeArgs($mockedInstance, $params);
    }

    public function deleteDirectory($dir): void
    {
        if (is_dir($dir)) {
            $files = glob($dir . '/*', GLOB_MARK);
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($dir);
        }
    }
}
