<?php

namespace Dws\Sftw\Symfony\Component\Console;

use Dws\Sftw\Symfony\Component\Console\Command;
use Symfony\Component\Console\Application as BaseApplication;

/**
 * The main SFTW application
 *
 * @author David Weinraub <david.weinraub@dws.la>
 */
class Application extends BaseApplication
{
    public function __construct()
    {
		parent::__construct('South For the Winter - a db migration tool', '0.1.0');
		$this->add(new Command\Current());
		$this->add(new Command\Latest());
		$this->add(new Command\Migrate());
		$this->add(new Command\PointTo());
    }
}
