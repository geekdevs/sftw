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
		
		/**
		 * Define CLI options.
		 * 
		 * Symfony reserves -h and -n options, so there are some counter-intuitive ones 
		 * in here, like -c for host ("connection'), -m for namespace, etc.
		 */
        $this->addOption('host', 'c', Console\Input\InputOption::VALUE_REQUIRED, 'DB host/connection');
        $this->addOption('user', 'u', Console\Input\InputOption::VALUE_REQUIRED, 'DB user');
        $this->addOption('pass', 'p', Console\Input\InputOption::VALUE_REQUIRED, 'DB password');
        $this->addOption('db', 'd', Console\Input\InputOption::VALUE_REQUIRED, 'DB name');
        $this->addOption('dsn', 's', Console\Input\InputOption::VALUE_REQUIRED, 'Complete DSN string. Overrides individual host/user/pass/db values. Format: mysql://john:pass@localhost:port/my_db', '');
        $this->addOption('namespace', 'm', Console\Input\InputOption::VALUE_REQUIRED, 'Namespace for the migration classes (forward slashes ok, will be transformed)');
        $this->addOption('path', 'f', Console\Input\InputOption::VALUE_REQUIRED, 'Path for migration files. Default: ./scripts/migrations', './scripts/migrations');
        $this->addOption('driver', 'r', Console\Input\InputOption::VALUE_REQUIRED, 'DB driver. Default: mysql', 'mysql');
        $this->addOption('prefix', 'x', Console\Input\InputOption::VALUE_REQUIRED, 'DB table prefix. Default: ""', '');
        $this->addOption('useTransaction', 't', Console\Input\InputOption::VALUE_NONE, 'Wrap the entire migration in a transaction. Default: false');
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
		$this->host = trim($input->getOption('host'));
		$this->user = $input->getOption('user');
		$this->pass = $input->getOption('pass');
		$this->db = $input->getOption('db');
		$dsn = $input->getOption('dsn');
		if ($dsn){
			$dsnComponents = self::parseDSN($dsn);
			$this->driver = $dsnComponents['phptype'];
			$this->host = $dsnComponents['hostspec'];
			$this->user = $dsnComponents['username'];
			$this->pass = $dsnComponents['password'];
			$this->db = $dsnComponents['database'];
		}

		if (!$this->driver){
			$this->errors[] = 'DB driver is required';
		}
		if (!$this->db){
			$this->errors[] = 'DB name is required';
		}
		
//		if (!$this->host){
//			$this->errors[] = 'DB host is required';
//		}
//		if (!$this->user){
//			$this->errors[] = 'DB user is required';
//		}
//		if (!$this->pass){
//			$this->errors[] = 'DB password is required';
//		}

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
	
	/**
	 * Parse a DSN string into components.
	 * 
	 * @see http://pear.php.net/reference/DB-latest/DB/DB.html#methodparseDSN
	 * @license http://www.php.net/license/3_0.txt via PEAR::DB
	 * 
	 * @param string $dsn
	 * @return array
	 */
	protected static function parseDSN($dsn)
	{
		$parsed = array(
			'phptype' => false,
			'dbsyntax' => false,
			'username' => false,
			'password' => false,
			'protocol' => false,
			'hostspec' => false,
			'port' => false,
			'socket' => false,
			'database' => false,
		);

		if (is_array($dsn)) {
			$dsn = array_merge($parsed, $dsn);
			if (!$dsn['dbsyntax']) {
				$dsn['dbsyntax'] = $dsn['phptype'];
			}
			return $dsn;
		}

		// Find phptype and dbsyntax
		if (($pos = strpos($dsn, '://')) !== false) {
			$str = substr($dsn, 0, $pos);
			$dsn = substr($dsn, $pos + 3);
		} else {
			$str = $dsn;
			$dsn = null;
		}

		// Get phptype and dbsyntax
		// $str => phptype(dbsyntax)
		if (preg_match('|^(.+?)\((.*?)\)$|', $str, $arr)) {
			$parsed['phptype'] = $arr[1];
			$parsed['dbsyntax'] = !$arr[2] ? $arr[1] : $arr[2];
		} else {
			$parsed['phptype'] = $str;
			$parsed['dbsyntax'] = $str;
		}

		if (!count($dsn)) {
			return $parsed;
		}

		// Get (if found): username and password
		// $dsn => username:password@protocol+hostspec/database
		if (($at = strrpos($dsn, '@')) !== false) {
			$str = substr($dsn, 0, $at);
			$dsn = substr($dsn, $at + 1);
			if (($pos = strpos($str, ':')) !== false) {
				$parsed['username'] = rawurldecode(substr($str, 0, $pos));
				$parsed['password'] = rawurldecode(substr($str, $pos + 1));
			} else {
				$parsed['username'] = rawurldecode($str);
			}
		}

		// Find protocol and hostspec

		if (preg_match('|^([^(]+)\((.*?)\)/?(.*?)$|', $dsn, $match)) {
			// $dsn => proto(proto_opts)/database
			$proto = $match[1];
			$proto_opts = $match[2] ? $match[2] : false;
			$dsn = $match[3];
		} else {
			// $dsn => protocol+hostspec/database (old format)
			if (strpos($dsn, '+') !== false) {
				list($proto, $dsn) = explode('+', $dsn, 2);
			}
			if (strpos($dsn, '/') !== false) {
				list($proto_opts, $dsn) = explode('/', $dsn, 2);
			} else {
				$proto_opts = $dsn;
				$dsn = null;
			}
		}

		// process the different protocol options
		$parsed['protocol'] = (!empty($proto)) ? $proto : 'tcp';
		$proto_opts = rawurldecode($proto_opts);
		if ($parsed['protocol'] == 'tcp') {
			if (strpos($proto_opts, ':') !== false) {
				list($parsed['hostspec'],
						$parsed['port']) = explode(':', $proto_opts);
			} else {
				$parsed['hostspec'] = $proto_opts;
			}
		} elseif ($parsed['protocol'] == 'unix') {
			$parsed['socket'] = $proto_opts;
		}

		// Get dabase if any
		// $dsn => database
		if ($dsn) {
			if (($pos = strpos($dsn, '?')) === false) {
				// /database
				$parsed['database'] = rawurldecode($dsn);
			} else {
				// /database?param1=value1&param2=value2
				$parsed['database'] = rawurldecode(substr($dsn, 0, $pos));
				$dsn = substr($dsn, $pos + 1);
				if (strpos($dsn, '&') !== false) {
					$opts = explode('&', $dsn);
				} else { // database?param1=value1
					$opts = array($dsn);
				}
				foreach ($opts as $opt) {
					list($key, $value) = explode('=', $opt);
					if (!isset($parsed[$key])) {
						// don't allow params overwrite
						$parsed[$key] = rawurldecode($value);
					}
				}
			}
		}

		return $parsed;
	}
}
