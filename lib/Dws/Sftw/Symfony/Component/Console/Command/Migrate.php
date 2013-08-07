<?php

namespace Dws\Sftw\Symfony\Component\Console\Command;

use Dws\Sftw\Db\Schema\MigrateException;
use Symfony\Component\Console;

/**
 * Updates the db to a requested schema version
 *
 * @author David Weinraub <david.weinraub@diamondwebservices.com>
 */
class Migrate extends AbstractSftw
{
    public function __construct()
    {
		parent::__construct('migrate');
		$this->setDescription('Migrates the schema to the specified version');
		$this->setHelp('Migrate the schema to the specified version');
		
		$this->addArgument('target', Console\Input\InputArgument::REQUIRED, 'The desired schema version', null);
    }
	
	public function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
	{
		parent::execute($input, $output);
				
		// $this->displayCurrentSchemaVersion($output);
		
		$target = $input->getArgument('target');
		try {
			$result = $this->manager->updateTo($target);		
		} catch (MigrateException $e) {
			$this->errors[] = $e->getMessage();
			$this->outputErrorsAndExit($output, 1);
		}

		$version = $this->manager->getCurrentSchemaVersion();
		$this->outputResult($result, $version, $output);
		exit(0);
	}
}
