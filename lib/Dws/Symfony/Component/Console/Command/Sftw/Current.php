<?php

namespace Dws\Symfony\Component\Console\Command\Sftw;

use Symfony\Component\Console;

/**
 * Displays the current schema version
 *
 * @author David Weinraub <david.weinraub@diamondwebservices.com>
 */
class Current extends AbstractSftw
{
    public function __construct()
    {
		parent::__construct('current');
		$this->setDescription('Displays the current schema version');
		$this->setHelp('Displays the current schema version');		
    }
	
	public function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
	{
		parent::execute($input, $output);
		
		$this->displayCurrentSchemaVersion($output);
		exit(0);
	}
}
