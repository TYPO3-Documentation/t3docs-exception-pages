<?php

declare(strict_types=1);

namespace Typo3\ExceptionPages;

use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\HttpClient;

class ExceptionPage
{
    protected $exceptionCode;
    protected $action;

    protected $gitHubUser;
    protected $gitHubToken;
    protected $gitHubOwner;
    protected $gitHubRepository;
    protected $gitHubExceptionPath;
    protected $gitHubBranch;

    protected $exceptionCodes;
    protected $exceptionTemplates;

    public function __construct(string $exceptionCode)
    {
        $this->exceptionCode = $exceptionCode;

        $this->gitHubOwner = 'TYPO3-Documentation';
        $this->gitHubRepository = 'TYPO3CMS-Exceptions';
        $this->gitHubExceptionPath = 'Documentation/Exceptions/%s.rst';
        $this->gitHubBranch = 'master';

        $this->exceptionCodes = new ExceptionCodes();
        $this->exceptionTemplates = new ExceptionTemplates();
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
        $rst = $this->exceptionTemplates->renderDefaultPageRst($this->exceptionCode);

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
        echo $this->exceptionTemplates->renderDefaultPageRst($this->exceptionCode);
        exit;
    }

    protected function showDefaultPage(): void
    {
        $this->exceptionTemplates->refreshTemplatesIfOutdated();
        header('Content-Type: text/html');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $this->exceptionTemplates->renderDefaultPage($this->exceptionCode);
        exit;
    }

    protected function logError(string $message, ...$args): void
    {
        error_log(sprintf($message, ...$args));
    }

    protected function showError(): void
    {
        $this->exceptionTemplates->refreshTemplatesIfOutdated();
        header('Content-Type: text/html');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $this->exceptionTemplates->renderErrorPage($this->exceptionCode);
        exit;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    public function setTemplateLifetime(int $templateLifetime): void
    {
        $this->exceptionTemplates->setLifetime($templateLifetime);
    }

    public function setWorkingDir(string $workingDir): void
    {
        $this->exceptionCodes->setWorkingDir($workingDir);
        $this->exceptionTemplates->setWorkingDir($workingDir);
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
