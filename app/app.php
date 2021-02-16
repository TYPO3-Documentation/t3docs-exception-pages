<?php

require 'vendor/autoload.php';

$exception = ctype_digit($_GET['exception']) ? $_GET['exception'] : '';
$action = ctype_alpha($_GET['action']) ? $_GET['action'] : '';

$config = include 'config.php';

$exceptionPage = new \Typo3\ExceptionPages\ExceptionPage($exception);
$exceptionPage->setAction($action);
$exceptionPage->setGitHubUser($config['gitHub']['user']);
$exceptionPage->setGitHubToken($config['gitHub']['token']);
$exceptionPage->run();
