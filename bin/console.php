#!/usr/bin/php
<?php

use Symfony\Component\Console;
use Dws\Console\Command\Stfw as StfwCommand;

require_once __DIR__ . '/../vendor/autoload.php';

$application = new Console\Application('Demo', '1.0.0');
$application->add(new StfwCommand('sftw'));
$application->run();
