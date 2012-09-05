<?php

namespace Dws\Symfony\Component\Console\Command\Sftw;

use Symfony\Component\Console;

/**
 * Updates the db to the latest schema version
 *
 * @author David Weinraub <david.weinraub@diamondwebservices.com>
 */
class Latest extends AbstractSftw
{
    public function __construct()
    {
		parent::__construct('latest');
		$this->setDescription('Migrates the schema to the latest version');
		$this->setHelp('Migrates the schema to the latest version');		
    }
	
	public function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
	{
		parent::execute($input, $output);
		
		$this->displayCurrentSchemaVersion($output);
		$result = $this->manager->updateTo();
		$version = $this->manager->getCurrentSchemaVersion();
		$this->outputResult($result, $version, $output);
	}
}
