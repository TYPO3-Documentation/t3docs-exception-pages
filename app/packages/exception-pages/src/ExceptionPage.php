<?php

declare(strict_types=1);

namespace Typo3\ExceptionPages;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\HttpClient;

class ExceptionPage
{
    protected $exceptionCode;
    protected $action;

    protected $exceptionUrl;
    protected $templateExceptionCode;
    protected $templateLifetime;
    protected $templateDir;

    protected $gitHubUser;
    protected $gitHubToken;
    protected $gitHubOwner;
    protected $gitHubRepository;
    protected $gitHubExceptionPath;
    protected $gitHubBranch;

    protected $exceptionCodes;

    public function __construct(string $exceptionCode)
    {
        $this->exceptionCode = $exceptionCode;

        $this->exceptionUrl = 'https://docs.typo3.org/typo3cms/exceptions/master/en-us/Exceptions/%s.html';
        $this->templateExceptionCode = 1166546734;
        $this->templateLifetime = 24 * 3600;
        $this->templateDir = dirname(__DIR__) . '/res';

        $this->gitHubOwner = 'TYPO3-Documentation';
        $this->gitHubRepository = 'TYPO3CMS-Exceptions';
        $this->gitHubExceptionPath = 'Documentation/Exceptions/%s.rst';
        $this->gitHubBranch = 'master';

        $this->exceptionCodes = new ExceptionCodes();
    }

    public function run(): void
    {
        try {
            $this->checkExceptionCode();
            if ($this->action === 'edit') {
                $this->createPage();
                $this->redirectToEditPage();
            } elseif ($this->action === 'source') {
                $this->showSourceOfDefaultPage();
            } else {
                $this->showDefaultPage();
            }
        } catch (\InvalidArgumentException $e) {
            // do not log invalid exception codes to avoid log flooding
            $this->showError();
        } catch (\Exception $e) {
            $this->logError('%s (%s)', $e->getMessage(), $e->getCode());
            $this->showError();
        }
    }

    protected function checkExceptionCode(): void
    {
        if (!$this->exceptionCodes->isValid($this->exceptionCode)) {
            throw new \InvalidArgumentException(sprintf('This is not a valid exception number: %s.', $this->exceptionCode), 4001);
        };
    }

    protected function createPage(): void
    {
        $rst = file_get_contents($this->templateDir . '/default.rst');
        $rst = str_replace(['[[[Exception]]]'], [$this->exceptionCode], $rst);

        try {
            $client = HttpClient::create();
            $client->request('PUT',
                sprintf(
                    'https://api.github.com/repos/%s/%s/contents/%s',
                    $this->gitHubOwner,
                    $this->gitHubRepository,
                    sprintf($this->gitHubExceptionPath, $this->exceptionCode)
                ),
                ['headers' => [
                    'accept' => 'application/vnd.github.v3+json',
                ], 'auth_basic' => [
                    $this->gitHubUser, $this->gitHubToken
                ], 'json' => [
                    'message' => sprintf('[TASK] Create page for exception %s', $this->exceptionCode),
                    'content' => base64_encode($rst),
                    'branch' => $this->gitHubBranch
                ]]
            );
        } catch (ClientException $e) {
            // GitHub API error #422:
            // Exception page reST file already exists but is not yet converted and deployed as HTML
            // which means the user can continue to edit the page on GitHub.
            if ($e->getCode() !== 422) {
                throw $e;
            }
        }
    }

    protected function redirectToEditPage(): void
    {
        header(sprintf("Location: %s", sprintf(
            'https://github.com/%s/%s/edit/%s/%s',
            $this->gitHubOwner,
            $this->gitHubRepository,
            $this->gitHubBranch,
            sprintf($this->gitHubExceptionPath, $this->exceptionCode)
        )));
        exit;
    }

    protected function showSourceOfDefaultPage(): void
    {
        header('Content-Type: text/plain');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        $rst = file_get_contents($this->templateDir . '/default.rst');
        $rst = str_replace(['[[[Exception]]]'], [$this->exceptionCode], $rst);
        echo $rst;
        exit;
    }

    protected function showDefaultPage(): void
    {
        $this->refreshTemplatesIfOutdated();
        header('Content-Type: text/html');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        $page = file_get_contents($this->templateDir . '/pageDefault.html');
        $page = str_replace(['[[[Exception]]]'], [$this->exceptionCode], $page);
        echo $page;
        exit;
    }

    protected function refreshTemplatesIfOutdated(): void
    {
        $pageDefaultPath = $this->templateDir . '/pageDefault.html';
        $pageErrorPath = $this->templateDir . '/pageError.html';
        $lastModificationTime = max(filemtime($pageDefaultPath), filemtime($pageErrorPath));
        if ($lastModificationTime + $this->templateLifetime < time()) {
            try {
                $content = file_get_contents(sprintf($this->exceptionUrl, $this->templateExceptionCode));
                file_put_contents($pageDefaultPath, $this->parseDefaultPage($content));
                file_put_contents($pageErrorPath, $this->parseErrorPage($content));
            } catch (\Exception $e) {
                $this->logError('%s (%s)', $e->getMessage(), $e->getCode());
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

        $body = file_get_contents($this->templateDir . '/default.html');
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

        $body = file_get_contents($this->templateDir . '/error.html');
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
        $content = str_replace([$this->templateExceptionCode], ['[[[Exception]]]'], $content);
        return $content;
    }

    protected function logError(string $message, ...$args): void
    {
        error_log(sprintf($message, ...$args));
    }

    protected function showError(): void
    {
        $this->refreshTemplatesIfOutdated();
        header('Content-Type: text/html');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        $page = file_get_contents($this->templateDir . '/pageError.html');
        $page = str_replace(['[[[Exception]]]'], [$this->exceptionCode], $page);
        echo $page;
        exit;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    public function setExceptionUrl(string $exceptionUrl): void
    {
        $this->exceptionUrl = $exceptionUrl;
    }

    public function setTemplateExceptionCode(int $templateExceptionCode): void
    {
        $this->templateExceptionCode = $templateExceptionCode;
    }

    public function getTemplateLifetime(): int
    {
        return $this->templateLifetime;
    }

    public function setTemplateLifetime(int $templateLifetime): void
    {
        $this->templateLifetime = $templateLifetime;
    }

    public function getTemplateDir(): string
    {
        return $this->templateDir;
    }

    public function setTemplateDir(string $templateDir): void
    {
        $this->templateDir = $templateDir;
    }

    public function setGitHubUser(string $user): void
    {
        $this->gitHubUser = $user;
    }

    public function setGitHubToken(string $token): void
    {
        $this->gitHubToken = $token;
    }

    public function setGitHubOwner(string $owner): void
    {
        $this->gitHubOwner = $owner;
    }

    public function setGitHubRepository(string $repository): void
    {
        $this->gitHubRepository = $repository;
    }

    public function setGitHubExceptionPath(string $path): void
    {
        $this->gitHubExceptionPath = $path;
    }

    public function setGitHubBranch(string $branch): void
    {
        $this->gitHubBranch = $branch;
    }
}
