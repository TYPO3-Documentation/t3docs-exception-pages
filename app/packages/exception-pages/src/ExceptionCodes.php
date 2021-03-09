<?php

declare(strict_types=1);

namespace Typo3\ExceptionPages;

class ExceptionCodes
{
    protected $exceptionCodes;

    protected $binDir;
    protected $typo3Dir;
    protected $filesDir;
    protected $workingDir;
    protected $mergeFile;

    public function __construct() {
        $this->binDir = dirname(__DIR__) . '/bin';
        $this->typo3Dir = dirname(__DIR__) . '/res/typo3';
        $this->filesDir = dirname(__DIR__) . '/res/exceptions';
        $this->workingDir = dirname(__DIR__) . '/res/exceptions';
        $this->mergeFile = 'exceptions.php';
    }

    public function fetchFiles(string $typo3ReleasePattern = '', bool $force = false): void
    {
        if (!is_dir($this->typo3Dir)) {
            exec(sprintf('git clone git://git.typo3.org/Packages/TYPO3.CMS.git %s', $this->typo3Dir));
        }

        chdir($this->typo3Dir);

        exec('git checkout master');
        exec('git pull');
        exec('git tag --list', $tags);

        $durationTotal = 0;
        $numTags = 0;

        if (!empty($typo3ReleasePattern)) {
            $this->info('Fetching the exception codes of TYPO3 releases of pattern "%s".', $typo3ReleasePattern);
        } else {
            $this->info('Fetching the exception codes of all TYPO3 releases.');
        }

        $this->createFilesDirsIfNotExist();
        $files = $this->getFiles();

        foreach ($tags as $tag) {
            if (empty($typo3ReleasePattern) || preg_match($typo3ReleasePattern, $tag) === 1) {
                $fileName = sprintf('exceptions-%s.json', $tag);
                if ($force || !isset($files[$fileName])) {
                    try {
                        $exceptionCodesJson = [];

                        $start = microtime(true);

                        exec(sprintf('git -c advice.detachedHead=false checkout %s', $tag));
                        exec(sprintf('%s/duplicateExceptionCodeCheck.sh -p', $this->binDir), $exceptionCodesJson);
                        $exceptionCodes = json_decode(implode('', $exceptionCodesJson), true);
                        $filePath = $this->workingDir . DIRECTORY_SEPARATOR . $fileName;
                        file_put_contents($filePath, implode("\n", $exceptionCodesJson));

                        $duration = microtime(true) - $start;
                        $durationTotal += $duration;
                        $numTags++;

                        $this->info("Fetching %s exception codes of TYPO3 %s took %s seconds.",
                            $exceptionCodes['total'], $tag, number_format($duration, 2)
                        );
                    } catch (\Exception $e) {
                        $this->error("Fetching the exception codes of TYPO3 %s failed (%s)!",
                            $tag, $e->getMessage()
                        );
                    }
                } else {
                    $this->info('Exception codes of TYPO3 %s already fetched.', $tag);
                }
            }
        }

        $this->info('Fetching the exception codes of %s TYPO3 releases took %s seconds.',
            $numTags, number_format($durationTotal, 2)
        );
    }

    protected function createFilesDirsIfNotExist(): void
    {
        $dirs = array_unique([$this->filesDir, $this->workingDir]);

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                if (@mkdir($dir)) {
                    $this->info('Directory %s created successfully.', $dir);
                } else {
                    $this->error('Directory %s cannot be created.', $dir);
                    exit;
                }
            }
        }
    }

    protected function getFiles(): array
    {
        $dirs = array_unique([$this->filesDir, $this->workingDir]);
        $files = [];

        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                if ($handle = opendir($dir)) {
                    while (false !== ($file = readdir($handle))) {
                        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
                        if (is_file($filePath)) {
                            $pathInfo = pathinfo($filePath);
                            if ($pathInfo['extension'] === 'json') {
                                $files[$pathInfo['basename']] = $filePath;
                            }
                        }
                    }
                }
            }
        }

        return $files;
    }

    public function mergeFiles(string $typo3ReleasePattern = '', string $mergeFileName = ''): void
    {
        $exceptions = [];
        $mergeFileName = !empty($mergeFileName) ? $mergeFileName : $this->mergeFile;

        if (!empty($typo3ReleasePattern)) {
            $this->info('Merging the exception codes of TYPO3 releases of pattern "%s" to file "%s".',
                $typo3ReleasePattern, $mergeFileName
            );
        } else {
            $this->info('Merging the exception codes of all TYPO3 releases to file "%s".',
                $mergeFileName
            );
        }

        $this->createFilesDirsIfNotExist();
        $files = $this->getFiles();

        foreach ($files as $fileName => $filePath) {
            if (empty($typo3ReleasePattern) || preg_match($typo3ReleasePattern, $fileName) === 1) {
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
                    $this->info("File %s contains %d exception codes.", $filePath, $numExceptionsOfFile);
                } catch (\Exception $e) {
                    $this->error("File %s could not be parsed (%s)!", $filePath, $e->getMessage());
                }
            }
        }

        ksort($exceptions);

        $mergeFilePath = $this->workingDir . DIRECTORY_SEPARATOR . $mergeFileName;
        $pathInfo = pathinfo($mergeFilePath);

        if ($pathInfo['extension'] === 'json') {
            file_put_contents(
                $mergeFilePath,
                json_encode([
                    'exceptions' => $exceptions,
                    'total' => count($exceptions),
                ], JSON_PRETTY_PRINT)
            );
        } else {
            file_put_contents(
                $mergeFilePath,
                sprintf("<?php\nreturn %s;", var_export([
                    'exceptions' => $exceptions,
                    'total' => count($exceptions),
                ], true))
            );
        }

        $this->info(
            "File %s contains %d exception codes in total.",
            $mergeFilePath,
            count($exceptions)
        );
    }

    public function isValid(string $exceptionCode): bool
    {
        $this->loadFile();
        return isset($this->exceptionCodes[$exceptionCode]);
    }

    protected function loadFile(): void
    {
        if (empty($this->exceptionCodes)) {
            if (is_file($this->workingDir . DIRECTORY_SEPARATOR . $this->mergeFile)) {
                $data = include $this->workingDir . DIRECTORY_SEPARATOR . $this->mergeFile;
                $this->exceptionCodes = $data['exceptions'];
            }
        }
    }

    protected function info(string $message, ...$args): void
    {
        $this->log(LOG_INFO, $message, ...$args);
    }

    protected function warn(string $message, ...$args): void
    {
        $this->log(LOG_WARNING, $message, ...$args);
    }

    protected function error(string $message, ...$args): void
    {
        $this->log(LOG_ERR, $message, ...$args);
    }

    protected function log(int $level, string $message, ...$args): void
    {
        $levelPrefix = [
            LOG_INFO => '[I] ',
            LOG_WARNING => '[W] ',
            LOG_ERR => '[E] ',
        ];

        printf($levelPrefix[$level] . $message . "\n", ...$args);
    }

    public function setTypo3Dir(string $typo3Dir): void
    {
        $this->typo3Dir = $typo3Dir;
    }

    public function getWorkingDir(): string
    {
        return $this->workingDir;
    }

    public function setWorkingDir(string $workingDir): void
    {
        $this->workingDir = $workingDir;
    }

    public function setMergeFile(string $mergeFile): void
    {
        $this->mergeFile = $mergeFile;
    }
}
