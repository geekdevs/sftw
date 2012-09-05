<?php

namespace Dws\Symfony\Component\Console\Command\Sftw;

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
				
		$this->displayCurrentSchemaVersion($output);
		
		$target = $input->getArgument('target');
		$result = $this->manager->updateTo($target);
		$version = $this->manager->getCurrentSchemaVersion();
		$this->outputResult($result, $version, $output);
	}
}
