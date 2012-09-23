<?php

namespace Dws\Symfony\Component\Console\Command\Sftw;

use Dws\Db\Schema\Manager as SchemaManager;
use PDO;
use Symfony\Component\Console;

/**
 * An abstract base for SFTW commands
 *
 * @author David Weinraub <david.weinraub@diamondwebservices.com>
 */
abstract class AbstractSftw extends Console\Command\Command
{

	/**
	 * The schema manager
	 * 
	 * @var \Dws\Db\Schema\Manager
	 */
	protected $manager;
	
	/**
	 *
	 * @var type 
	 */
	protected $driver;
	protected $host;
	protected $user;
	protected $pass;
	protected $db;
	protected $path;
	protected $namespace;
	protected $tablePrefix;
	protected $useTransaction;
		
	protected $errors = array();
	
	/**
	 * Construct
	 */
	public function __construct($name = null)
	{
		parent::__construct($name);
		
        $this->addOption('host', null, Console\Input\InputOption::VALUE_REQUIRED, 'DB host');
        $this->addOption('user', null, Console\Input\InputOption::VALUE_REQUIRED, 'DB user');
        $this->addOption('pass', null, Console\Input\InputOption::VALUE_REQUIRED, 'DB password');
        $this->addOption('db', null, Console\Input\InputOption::VALUE_REQUIRED, 'DB name');
        $this->addOption('namespace', null, Console\Input\InputOption::VALUE_REQUIRED, 'Namespace for the migration classes (forward slashes ok, will be transformed)');
        $this->addOption('path', null, Console\Input\InputOption::VALUE_REQUIRED, 'Path for migration files. Default: ./scripts/migrations', './scripts/migrations');
        $this->addOption('driver', null, Console\Input\InputOption::VALUE_REQUIRED, 'DB driver. Default: mysql', 'mysql');
        $this->addOption('prefix', null, Console\Input\InputOption::VALUE_REQUIRED, 'DB table prefix. Default: ""', '');
        $this->addOption('useTransaction', null, Console\Input\InputOption::VALUE_NONE, 'Wrap the entire migration in a transaction. Default: false');
	}
	
	protected function outputErrorsAndExit(Console\Output\OutputInterface $output, $code = 1)
	{
		$output->writeln('Errors occurred. See details below.');
		$output->writeln($this->errors);
		if ($this->manager && $this->manager->isRollback()){
			$output->writeln('Rollback invoked. No changes applied.');
		}
		$output->writeln('');
		$output->writeln('Usage: ' . $this->getSynopsis());
		exit($code);
	}
	
	protected function outputResult($result, $version, Console\Output\OutputInterface $output)
	{
		if (SchemaManager::RESULT_AT_CURRENT_VERSION == $result){
			$output->writeln('Schema is already at requested version ' . $version);
		} else if (SchemaManager::RESULT_OK == $result){
			$output->writeln('Schema migrated to version ' . $version);
		} else if (SchemaManager::RESULT_NO_MIGRATIONS_FOUND == $result){
			$output->writeln('Unable to find migrations');
		} else {
			throw new \RuntimeException('Unknown migration result');
		}		
	}

	/**
	 * Builds a schema manager from input streams
	 * 
	 * @param \Symfony\Component\Console\Input $input
	 * @param \Symfony\Component\Console\Output $output
	 * @throws \PDOException
	 * @return \Dws\Db\Schema\Manager
	 */
	protected function buildManager(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
	{
		$this->populateCommonParams($input, $output);
		$dsn = self::buildDSN($this->driver, $this->db, $this->host);
		$pdo = new PDO($dsn, $this->user, $this->pass);
		$manager = new SchemaManager($pdo, $this->path, array(
			'namespace' => $this->namespace,
			'tablePrefix' => $this->tablePrefix,
			'useTransaction' => $this->useTransaction,
		));
		return $manager;
	}
	
	protected static function buildDSN($driver, $db, $host)
	{
		return $driver . ':dbname=' . $db . ';host=' . $host;
	}
	
	protected function populateCommonParams(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
	{
		$this->driver = $input->getOption('driver');
		if (!$this->driver){
			$this->errors[] = 'DB driver is required';
		}
		
		$this->host = trim($input->getOption('host'));
		if (!$this->host){
			$this->errors[] = 'DB host is required';
		}

		$this->user = $input->getOption('user');
		if (!$this->user){
			$this->errors[] = 'DB user is required';
		}
		
		$this->pass = $input->getOption('pass');
		if (!$this->pass){
			$this->errors[] = 'DB password is required';
		}
		
		$this->db = $input->getOption('db');
		if (!$this->db){
			$this->errors[] = 'DB name is required';
		}
		
		if (count($this->errors) > 0){
			$this->outputErrorsAndExit($output);
		}
		
		$this->namespace = $input->getOption('namespace');
		$this->path = $input->getOption('path');
		$this->prefix = $input->getOption('prefix');
		$this->useTransaction = $input->getOption('useTransaction');
	}
	
	public function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
	{
		try {
			$this->manager = $this->buildManager($input, $output);
		} catch (\Exception $e) {
			$this->errors[] = $e->getMessage();
			$this->outputErrorsAndExit($output, 1);
		}
	}
	
	protected function displayCurrentSchemaVersion(Console\Output\OutputInterface $output)
	{
		$version = $this->manager->getCurrentSchemaVersion();
		$output->writeln('Current schema version is ' . $version);
	}
}
