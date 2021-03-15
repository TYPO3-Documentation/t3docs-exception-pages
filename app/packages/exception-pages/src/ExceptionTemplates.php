<?php

declare(strict_types=1);

namespace Typo3\ExceptionPages;

use Symfony\Component\DomCrawler\Crawler;

class ExceptionTemplates
{
    protected $exceptionUrl;
    protected $exceptionCode;
    protected $lifetime;

    protected $resourcesDir;
    protected $workingDir;

    public function __construct()
    {
        $this->exceptionUrl = 'https://docs.typo3.org/typo3cms/exceptions/master/en-us/Exceptions/%s.html';
        $this->exceptionCode = 1166546734;
        $this->lifetime = 8 * 24 * 3600;

        $this->resourcesDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'res';
        $this->workingDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'res';
    }

    public function refreshTemplatesIfOutdated(): void
    {
        if (!is_file($this->getDefaultPagePath())) {
            $this->createWorkingDirsIfNotExist();
            copy($this->getTemplatesResourcesDir() . DIRECTORY_SEPARATOR . 'pageDefault.html', $this->getDefaultPagePath());
        }
        if (!is_file($this->getErrorPagePath())) {
            $this->createWorkingDirsIfNotExist();
            copy($this->getTemplatesResourcesDir() . DIRECTORY_SEPARATOR . 'pageError.html', $this->getErrorPagePath());
        }

        $lastModificationTime = max(filemtime($this->getDefaultPagePath()), filemtime($this->getErrorPagePath()));
        if ($lastModificationTime + $this->lifetime < time()) {
            try {
                $content = file_get_contents(sprintf($this->exceptionUrl, $this->exceptionCode));
                file_put_contents($this->getDefaultPagePath(), $this->parseDefaultPage($content));
                file_put_contents($this->getErrorPagePath(), $this->parseErrorPage($content));
            } catch (\Exception $e) {
                $this->logError('%s (%s)', $e->getMessage(), $e->getCode());
            }
        }
    }

    protected function createWorkingDirsIfNotExist(): void
    {
        $dirs = array_unique([$this->getTemplatesWorkingDir()]);

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                if (@mkdir($dir, 0777, true) === false) {
                    throw new \Exception(sprintf('Directory %s cannot be created.', $dir), 4002);
                }
            }
        }
    }

    protected function parseDefaultPage(string $content): string
    {
        $crawler = new Crawler($this->parsePage($content));
        $crawler->filterXPath('//div[@class="breadcrumb-additions"]/a')
            ->each(function(Crawler $crawler, int $index){
                $node = $crawler->getNode(0);
                if ($index === 0) {
                    $node->setAttribute('href', '?action=edit');
                } else {
                    $node->setAttribute('href', '?action=source');
                }
            });
        $crawler->filterXPath('//div[@itemprop="articleBody"]/div')
            ->each(function(Crawler $crawler){
                // Keep headline + contribution note and replace the remainder
                // -
                // Index 0: Headline
                // Index 1: Contribution note
                // Index 2..n: Body
                foreach ($crawler->children() as $index => $child) {
                    if ($index >= 2) {
                        $child->parentNode->removeChild($child);
                    }
                }
                $node = $crawler->getNode(0);
                $node->appendChild($node->ownerDocument->createTextNode('[[[Body]]]'));
            });

        $body = file_get_contents($this->getTemplatesResourcesDir() . DIRECTORY_SEPARATOR . 'default.html');
        $content = "<!DOCTYPE html>\n" . $crawler->outerHtml();
        $content = str_replace(['[[[Body]]]'], [$body], $content);
        return $content;
    }

    protected function parseErrorPage(string $content): string
    {
        $crawler = new Crawler($this->parsePage($content));
        $crawler->filterXPath('//div[@class="breadcrumb-additions"]')
            ->each(function(Crawler $crawler){
                $node = $crawler->getNode(0);
                $node->parentNode->removeChild($node);
            });
        $crawler->filterXPath('//div[@itemprop="articleBody"]/div')
            ->each(function(Crawler $crawler){
                // Keep headline and replace the remainder by placeholder "[[[Body]]]"
                // -
                // Index 0: Headline
                // Index 1: Contribution note
                // Index 2..n: Body
                foreach ($crawler->children() as $index => $child) {
                    if ($index >= 1) {
                        $child->parentNode->removeChild($child);
                    }
                }
                $node = $crawler->getNode(0);
                $node->appendChild($node->ownerDocument->createTextNode('[[[Body]]]'));
            });

        $body = file_get_contents($this->getTemplatesResourcesDir() . DIRECTORY_SEPARATOR . 'error.html');
        $content = "<!DOCTYPE html>\n" . $crawler->outerHtml();
        $content = str_replace(['[[[Body]]]'], [$body], $content);
        return $content;
    }

    protected function parsePage(string $content): string
    {
        $crawler = new Crawler($content);
        $crawler->filterXPath('//link[@rel="prev" or @rel="next"]')
            ->each(function(Crawler $crawler){
                $node = $crawler->getNode(0);
                $node->parentNode->removeChild($node);
            });
        $crawler->filterXPath('//div[@class="toc-collapse"]/div[@class="toc"]')
            ->each(function(Crawler $crawler){
                $node = $crawler->getNode(0);
                $node->parentNode->removeChild($node);
            });
        $crawler->filterXPath('//div[@class="breadcrumb-additions"]/a')
            ->each(function(Crawler $crawler, int $index){
                $node = $crawler->getNode(0);
                if ($index === 0) {
                    $node->setAttribute('href', '?action=edit');
                } else {
                    $node->setAttribute('href', '?action=source');
                }
            });
        $crawler->filterXPath('//div[@class="page-main-content"]/div[@class="rst-content"]/a[@accesskey="p" or @accesskey="n"]')
            ->each(function(Crawler $crawler){
                $node = $crawler->getNode(0);
                $node->parentNode->removeChild($node);
            });
        $crawler->filterXPath('//div[@class="page-main-content"]/div[@class="rst-content"]/nav')
            ->each(function(Crawler $crawler){
                $node = $crawler->getNode(0);
                $node->parentNode->removeChild($node);
            });
        $crawler->filterXPath('//div[@class="footer-additional"]/p[contains(text(), "Last updated") or contains(text(), "Last rendered")]')
            ->each(function(Crawler $crawler){
                $node = $crawler->getNode(0);
                $node->parentNode->removeChild($node);
            });

        $content = $crawler->outerHtml();
        $content = str_replace([$this->exceptionCode], ['[[[Exception]]]'], $content);
        return $content;
    }

    public function renderDefaultPageRst(string $exceptionCode): string
    {
        $rst = file_get_contents($this->getTemplatesResourcesDir() . DIRECTORY_SEPARATOR . 'default.rst');
        $rst = str_replace(['[[[Exception]]]'], [$exceptionCode], $rst);
        return $rst;
    }

    public function renderDefaultPage(string $exceptionCode): string
    {
        $html = file_get_contents($this->getDefaultPagePath());
        $html = str_replace(['[[[Exception]]]'], [$exceptionCode], $html);
        return $html;
    }

    public function renderErrorPage(string $exceptionCode): string
    {
        $html = file_get_contents($this->getErrorPagePath());
        $html = str_replace(['[[[Exception]]]'], [$exceptionCode], $html);
        return $html;
    }

    protected function logError(string $message, ...$args): void
    {
        error_log(sprintf($message, ...$args));
    }

    public function setLifetime(int $lifetime): void
    {
        $this->lifetime = $lifetime;
    }

    public function setWorkingDir(string $workingDir): void
    {
        $this->workingDir = $workingDir;
    }

    public function getTemplatesResourcesDir(): string
    {
        return $this->resourcesDir . DIRECTORY_SEPARATOR . 'templates';
    }

    public function getTemplatesWorkingDir(): string
    {
        return $this->workingDir . DIRECTORY_SEPARATOR . 'templates';
    }

    public function getDefaultPagePath(): string
    {
        return $this->getTemplatesWorkingDir() . DIRECTORY_SEPARATOR . 'pageDefault.html';
    }

    public function getErrorPagePath(): string
    {
        return $this->getTemplatesWorkingDir() . DIRECTORY_SEPARATOR . 'pageError.html';
    }
}
