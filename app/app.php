<?php

require 'vendor/autoload.php';

$exception = ctype_digit($_GET['exception']) ? $_GET['exception'] : '';
$action = ctype_alpha($_GET['action']) ? $_GET['action'] : '';

$config = include 'config.php';

$exceptionPages = new \Typo3\ExceptionPages\ExceptionPage($exception);
$exceptionPages->setAction($action);
$exceptionPages->setGitHubUser($config['gitHub']['user']);
$exceptionPages->setGitHubToken($config['gitHub']['token']);
$exceptionPages->run();
