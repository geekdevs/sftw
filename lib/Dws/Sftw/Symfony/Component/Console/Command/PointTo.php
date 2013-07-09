<?php

namespace Dws\Sftw\Symfony\Component\Console\Command;

use Symfony\Component\Console;

/**
 * Updates the db to a requested schema version
 *
 * @author David Weinraub <david.weinraub@diamondwebservices.com>
 */
class PointTo extends AbstractSftw
{
    public function __construct()
    {
		parent::__construct('point-to');
		$this->setDescription('Points the stored schema version to the specified version *without* applying migrations');
		$this->setHelp('Points the stored schema version to the specified version *without* applying migrations');
		
		$this->addArgument('target', Console\Input\InputArgument::REQUIRED, 'The desired schema version');
    }
	
	public function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
	{
		parent::execute($input, $output);
	
		$this->displayCurrentSchemaVersion($output);		
		$target = $input->getArgument('target');
		$this->manager->setCurrentSchemaVersion($target);
		$output->writeln('Schema version pointer has been set to ' . $target);
		$this->displayCurrentSchemaVersion($output);
		exit(0);
	}
}
