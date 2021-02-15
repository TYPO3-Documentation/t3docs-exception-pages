<?php

declare(strict_types=1);

namespace Typo3\ExceptionPages;

class ExceptionPages
{
    protected $exceptionCodes;
    protected $exceptionCodesDir;
    protected $exceptionCodesFile;

    public function __construct() {
        $this->exceptionCodesDir = dirname(__DIR__) . '/res/exceptions';
        $this->exceptionCodesFile = 'exceptions.php';
    }

    public function mergeExceptionCodes(): void
    {
        $exceptions = [];

        if ($handle = opendir($this->exceptionCodesDir)) {
            while (false !== ($file = readdir($handle))) {
                $filePath = $this->exceptionCodesDir . DIRECTORY_SEPARATOR . $file;
                if (is_file($filePath)) {
                    $pathInfo = pathinfo($filePath);
                    if ($pathInfo['extension'] === 'json') {
                        try {
                            $exceptionsOfFile = json_decode(file_get_contents($filePath), true);
                            $numExceptionsOfFile = 0;
                            if (is_array($exceptionsOfFile['exceptions'])) {
                                $numExceptionsOfFile = count($exceptionsOfFile['exceptions']);
                                if (empty($exceptions)) {
                                    $exceptions = $exceptionsOfFile['exceptions'];
                                } else {
                                    foreach ($exceptionsOfFile['exceptions'] as &$code) {
                                        if (!isset($exceptions[$code])) {
                                            $exceptions[$code] = $code;
                                        }
                                    }
                                }
                            }
                            $this->logInfo("File %s contains %d exception codes.", $filePath, $numExceptionsOfFile);
                        } catch (Exception $e) {
                            $this->logInfo("File %s could not be parsed (%s)!", $filePath, $e->getMessage());
                        }
                    }
                }
            }
            closedir($handle);
        }

        ksort($exceptions);
        file_put_contents(
            $this->exceptionCodesDir . DIRECTORY_SEPARATOR . $this->exceptionCodesFile,
            sprintf("<?php\nreturn %s;", var_export($exceptions, true))
        );

        $this->logInfo(
            "File %s contains %d exception codes in total.",
            $this->exceptionCodesDir . DIRECTORY_SEPARATOR . $this->exceptionCodesFile,
            count($exceptions)
        );
    }

    public function isValidExceptionCode(string $exceptionCode): bool
    {
        $this->loadExceptionCodes();
        return isset($this->exceptionCodes[$exceptionCode]);
    }

    protected function loadExceptionCodes(): void
    {
        if (empty($this->exceptionCodes)) {
            if (is_file($this->exceptionCodesDir . DIRECTORY_SEPARATOR . $this->exceptionCodesFile)) {
                $this->exceptionCodes = include $this->exceptionCodesDir . DIRECTORY_SEPARATOR . $this->exceptionCodesFile;
            }
        }
    }

    protected function logInfo(string $message, ...$args): void
    {
        syslog(LOG_INFO, sprintf($message, ...$args));
    }

    protected function logError(string $message, ...$args): void
    {
        error_log(sprintf($message, ...$args));
    }

    public function setExceptionCodesDir(string $exceptionCodesDir): void
    {
        $this->exceptionCodesDir = $exceptionCodesDir;
    }

    public function setExceptionCodesFile(string $exceptionCodesFile): void
    {
        $this->exceptionCodesFile = $exceptionCodesFile;
    }
}
