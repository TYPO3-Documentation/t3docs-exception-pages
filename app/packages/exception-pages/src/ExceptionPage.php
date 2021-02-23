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
        } catch (\Exception $e) {
            $this->logError('%s (%s)', $e->getMessage(), $e->getCode());
            $this->showError();
        }
    }

    protected function checkExceptionCode(): void
    {
        if (!$this->exceptionCodes->isValid($this->exceptionCode)) {
            throw new \Exception(sprintf('This is not a valid exception number: %s.', $this->exceptionCode), 4001);
        };
    }

    protected function createPage(): void
    {
        $rst = file_get_contents(dirname(__DIR__) . '/res/default.rst');
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
        $rst = file_get_contents(dirname(__DIR__) . '/res/default.rst');
        $rst = str_replace(['[[[Exception]]]'], [$this->exceptionCode], $rst);
        echo $rst;
        exit;
    }

    protected function showDefaultPage(): void
    {
        $this->refreshDefaultPageIfOutdated();
        header('Content-Type: text/html');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        $page = file_get_contents(dirname(__DIR__) . '/res/page.html');
        $body = file_get_contents(dirname(__DIR__) . '/res/default.html');
        $page = str_replace(['[[[Body]]]', '[[[Exception]]]'], [$body, $this->exceptionCode], $page);
        echo $page;
        exit;
    }

    protected function refreshDefaultPageIfOutdated(): void
    {
        $pagePath = dirname(__DIR__) . '/res/page.html';
        $pageModificationTime = filemtime($pagePath);
        if ($pageModificationTime + $this->templateLifetime < time()) {
            try {
                $crawler = new Crawler(file_get_contents(sprintf($this->exceptionUrl, $this->templateExceptionCode)));
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
                $crawler->filterXPath('//div[@itemprop="articleBody"]')
                    ->getNode(0)->nodeValue = '[[[Body]]]';
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
                $crawler->filterXPath('//script[contains(@src, "piwik.js")]')
                    ->each(function(Crawler $crawler){
                        $node = $crawler->getNode(0);
                        $node->parentNode->removeChild($node);
                    });

                $content = "<!DOCTYPE html>\n" . $crawler->outerHtml();
                $content = str_replace([$this->templateExceptionCode], ['[[[Exception]]]'], $content);
                file_put_contents(dirname(__DIR__) . '/res/page.html', $content);
            } catch (\Exception $e) {
                $this->logError('%s (%s)', $e->getMessage(), $e->getCode());
            }
        }
    }

    protected function logError(string $message, ...$args): void
    {
        error_log(sprintf($message, ...$args));
    }

    protected function showError(): void
    {
        $this->refreshDefaultPageIfOutdated();
        header('Content-Type: text/html');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        $page = file_get_contents(dirname(__DIR__) . '/res/page.html');
        $body = file_get_contents(dirname(__DIR__) . '/res/error.html');
        $page = str_replace(['[[[Body]]]', '[[[Exception]]]'], [$body, $this->exceptionCode], $page);
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

    public function setTemplateLifetime(int $templateLifetime): void
    {
        $this->templateLifetime = $templateLifetime;
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
