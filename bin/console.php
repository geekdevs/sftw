#!/usr/bin/php
<?php

use Symfony\Component\Console;
use Dws\Console\Command\Sftw as SftwCommand;

require_once __DIR__ . '/../vendor/autoload.php';

$application = new Console\Application('Demo', '1.0.0');
$application->add(new SftwCommand('sftw'));
$application->run();
