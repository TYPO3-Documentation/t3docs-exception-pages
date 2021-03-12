<?php

declare(strict_types=1);

namespace Typo3\ExceptionPages\Tests;

use PHPUnit\Framework\TestCase;

abstract class AbstractTestBase extends TestCase
{
    public function callProtected($mockedInstance, $method, ...$params)
    {
        $reflectedMethod = new \ReflectionMethod(get_class($mockedInstance), $method);
        $reflectedMethod->setAccessible(true);
        return $reflectedMethod->invokeArgs($mockedInstance, $params);
    }
}
