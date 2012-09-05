<?php

namespace Dws\Console\Command;

use Dws\Db\Schema\Manager as SchemaManager;
use Symfony\Component\Console;

/**
 * Console command for the Stfw system
 *
 * @author David Weinraub <david.weinraub@diamondwebservices.com>
 */
class Sftw extends Console\Command\Command
{
    public function __construct($name = null)
    {
        parent::__construct($name);

        $this->setDescription('Performs DB migrations');
        $this->setHelp('Performs DB migrations');
		
        $this->addArgument('target', Console\Input\InputArgument::OPTIONAL, 'The target: "current", "latest", or a specific verson number. Default: current', 'current');
		
        $this->addOption('host', null, Console\Input\InputOption::VALUE_REQUIRED, 'DB host');
        $this->addOption('user', null, Console\Input\InputOption::VALUE_REQUIRED, 'DB user');
        $this->addOption('pass', null, Console\Input\InputOption::VALUE_REQUIRED, 'DB password');
        $this->addOption('db', null, Console\Input\InputOption::VALUE_REQUIRED, 'DB name');
        $this->addOption('namespace', null, Console\Input\InputOption::VALUE_REQUIRED, 'Namespace for the migration classes (forward slashes ok, will be transformed)');
        $this->addOption('path', null, Console\Input\InputOption::VALUE_REQUIRED, 'Path for migration files. Default: ./scripts/migrations', './scripts/migrations');
        $this->addOption('driver', null, Console\Input\InputOption::VALUE_REQUIRED, 'DB driver. Default: mysql', 'mysql');
        $this->addOption('prefix', null, Console\Input\InputOption::VALUE_REQUIRED, 'DB table prefix. Default: ""', '');
        $this->addOption('config', null, Console\Input\InputOption::VALUE_REQUIRED, 'Path to config file. Default: "./stfw.ini"', './stfw.ini');
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
		$errors = array();
		
		$driver = $input->getOption('driver');
		if (!$driver){
			$errors[] = 'DB driver is required';
		}
		
		$host = $input->getOption('host');
		if (!$host){
			$errors[] = 'DB host is required';
		}
		
		$user = $input->getOption('user');
		if (!$user){
			$errors[] = 'DB user is required';
		}
		
		$pass = $input->getOption('pass');
		if (!$pass){
			$errors[] = 'DB password is required';
		}
		
		$db = $input->getOption('db');
		if (!$db){
			$errors[] = 'DB name is required';
		}
		
		$target = $input->getArgument('target');
		if ('' == $target){
			$errors[] = 'Target is required';
		}
		
		if (count($errors) > 0){
			$this->outputErrorsAndExit($errors, $output);
		}
		
		$namespace = $input->getOption('namespace');
		$path = $input->getOption('path');
		$prefix = $input->getOption('prefix');

		$dsn = "$driver:dbname=$db;host=$host";
		try {
			$pdo = new \PDO($dsn, $user, $pass);
		} catch (\PDOException $e) {
			$errors[] = $e->getMessage();
			$this->outputErrorsAndExit($errors, $output);
		}

		$manager = new SchemaManager($pdo, $path, $namespace, $prefix);
		
		$output->writeln('Current schema version is ' . $manager->getCurrentSchemaVersion());
		
		switch ($target){
			case 'current':
				break;
			case 'latest':
				$result = $manager->updateTo();
				$version = $manager->getCurrentSchemaVersion();
				$this->outputResult($result, $version, $output);
				break;
			default:
				if (is_numeric($target)){
					$result = $manager->updateTo($target);
					$version = $manager->getCurrentSchemaVersion();
					$this->outputResult($result, $version, $output);
				}
				break;
		}
    }
	
}
