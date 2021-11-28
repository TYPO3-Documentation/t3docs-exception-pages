<?php

declare(strict_types=1);

namespace Typo3\ExceptionPages;

use Symfony\Contracts\HttpClient\ResponseInterface;

class GitHubException extends \RuntimeException
{
    public function __construct(ResponseInterface $response)
    {
        $code = $response->getStatusCode();
        $url = $response->getInfo('url');
        $protocol = 'HTTP';

        foreach (array_reverse($response->getInfo('response_headers')) as $header) {
            if (str_starts_with($header, 'HTTP/')) {
                $protocol = substr($header, 0, strpos($header, ' '));
                break;
            }
        }

        $body = $response->getContent(false);

        $message = sprintf('%s %d returned for "%s".', $protocol, $code, $url);
        $message .= !empty($body) ? "\n\n" . $body : "";

        parent::__construct($message, $code);
    }
}
