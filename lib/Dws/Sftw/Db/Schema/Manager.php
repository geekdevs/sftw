<?php

namespace Dws\Sftw\Db\Schema;

use \PDO;

/**
 * Manages db schema version changes
 *
 * @author David Weinraub <david.weinraub@diamondwebservices.com>
 */
class Manager
{

	const RESULT_OK = 'RESULT_OK';
	const RESULT_AT_CURRENT_VERSION = 'RESULT_AT_CURRENT_VERSION';
	const RESULT_NO_MIGRATIONS_FOUND = 'RESULT_NO_MIGRATIONS_FOUND';

	/**
	 * The PDO db connection
	 * 
	 * @var PDO
	 */
	protected $pdo;

	/**
	 * The table containing the current schema version
	 * 
	 * @var string
	 */
	protected $schemaVersionTableName = 'schema_version';

	/**
	 * Directory containing migration files
	 * 
	 * @var string
	 */
	protected $dir;

	/**
	 * Namespace for the migration classes
	 * 
	 * @var string
	 */
	protected $namespace;

	/**
	 *
	 * @var Symfony\Component\Console\Output\OutputInterface
	 */
	protected $output;

	/**
	 * Constructor
	 * 
	 * Alternatively accepts an array of options as the third parameter
	 * 
	 * @param PDO $pdo
	 */
	public function __construct(PDO $pdo, $dir, $options = array())
	{
		$this->pdo = $pdo;
		$this->dir = $this->trimSlash($dir);
		
		if (!is_array($options)){
			throw new \RuntimeException('Options must be an array');
		}
		$this->namespace = array_key_exists('namespace', $options) ? str_replace('/', '\\', $options['namespace']) : '';
		$this->tablePrefix = array_key_exists('tablePrefix', $options) ? $options['tablePrefix'] : '';
		$this->output = array_key_exists('output', $options) ? $options['output'] : null;

		$this->checkMigrationDirectory();
		$this->ensureSchemaVersionTableExists();
	}

	/**
	 * Check migration directory
	 * 
	 * @throws \RuntimeException
	 */
	protected function checkMigrationDirectory()
	{
		if (!is_dir($this->dir)){
			throw new \RuntimeException('Unable to find migration directory: ' . $this->dir);
		}
	}
	
	/**
	 * Check that schema table exists
	 * 
	 * @return boolean
	 */
	protected function doesSchemaVersionTableExist()
	{
		$select = $this->getPreparedSqlSelectStatementForCurrentVersion();
		try {
			if ($select->execute() === false){
				return false;
			} else {
				return true;
			}			
		} catch (\Exception $e) {
			return false;
		}
	}
	
	/**
	 * Ensure that the schema version able exists and contains at least a single record
	 * with the version field.
	 * 
	 * @return Manager
	 */
	protected function ensureSchemaVersionTableExists()
	{
		$schemaVersionTableName = $this->getPrefixedSchemaVersionTableName();
		if (!$this->doesSchemaVersionTableExist()){

			$this->writeln('Creasting schema table');

			// means that the schema version table doesn't exist, so create it
			$createSql = 
			'
				CREATE TABLE `' . $schemaVersionTableName . '` ( 
					version bigint NOT NULL,
					PRIMARY KEY (`version`)
				)
			';
			$this->pdo->exec($createSql);
			$insertSql = 'INSERT INTO `' . $schemaVersionTableName . '` (`version`) VALUES (0)';
			$this->pdo->exec($insertSql);
		}
		return $this;
	}
	
	/**
	 * Hard set the schema version value without performing any specified migrations
	 * 
	 * This is useful for when a group of migrations are "baked-in" to an 
	 * already-deployed production system, but you still want to have earlier 
	 * migrations (including a base schema) available for a fresh deployment
	 * 
	 * @return Manager
	 */
	public function setCurrentSchemaVersion($version)
	{
		$version = (int) $version;
		if ($version < 0){
			$version = 0;
		}
		$schemaVersionTableName = $this->getPrefixedSchemaVersionTableName();
		$this->pdo->exec('UPDATE `' . $schemaVersionTableName . '` SET `version` = ' . $version);
		return $this;
	}

	/**
	 * Utility function to generate a prepared PDoStatement to query for the current 
	 * version
	 * 
	 * @return \PDOStatememt
	 */
	protected function getPreparedSqlSelectStatementForCurrentVersion()
	{
		$schemaVersionTableName = $this->getPrefixedSchemaVersionTableName();
		return $this->pdo->prepare('SELECT `version` FROM `' . $schemaVersionTableName . '`');
	}
	
	/**
	 * Get the current schema version
	 * 
	 * @return integer
	 */
	public function getCurrentSchemaVersion()
	{
		$select = $this->getPreparedSqlSelectStatementForCurrentVersion();
		$select->execute();
		return $select->fetchObject()->version;
	}

	/**
	 * Use the migrations to update the db to the specified schema version
	 * 
	 * @param int|null $version the targeted version
	 * @return int One of the class constants RESULT_AT_CURRENT_VERSION, RESULT_NO_MIGRATIONS_FOUND, or RESULT_OK
	 */
	public function updateTo($version = null)
	{
		if (is_null($version)) {
			$version = PHP_INT_MAX;
		}
		$version = (int) $version;
		$currentVersion = $this->getCurrentSchemaVersion();
		if ($currentVersion == $version) {
			return self::RESULT_AT_CURRENT_VERSION;
		}

		$migrations = $this->_getMigrationFiles($currentVersion, $version);
		if (empty($migrations)) {
			if ($version == PHP_INT_MAX) {
				return self::RESULT_AT_CURRENT_VERSION;
			}
			return self::RESULT_NO_MIGRATIONS_FOUND;
		}

		$direction = 'up';
		if ($currentVersion > $version) {
			$direction = 'down';
		}
		$this->_performMigrations($direction, $migrations);
		return self::RESULT_OK;
	}
	
	/**
	 * 
	 * @param string $direction
	 * @param array $migrations
	 * @return void
	 * @throws \Dws\Db\Schema\Exception
	 */
	protected function _performMigrations($direction, $migrations)
	{
		foreach ($migrations as $migration) {
			$this->_processFile($migration, $direction);
		}
	}
	
	/**
	 * 
	 * @param int $currentVersion
	 * @param int $stopVersion
	 * @param string $dir
	 * @return array an array containing migration-file data to use in applying the requested migrations
	 */
	protected function _getMigrationFiles($currentVersion, $stopVersion, $dir = null)
	{
		if ($dir === null) {
			$dir = $this->dir;
		}

		$direction = 'up';
		$from = $currentVersion;
		$to = $stopVersion;
		if ($stopVersion < $currentVersion) {
			$direction = 'down';
			$from = $stopVersion;
			$to = $currentVersion;
		}

		$this->writeln('Direction: ' . $direction);

		$files = array();
		if (!is_dir($dir) || !is_readable($dir)) {
			return $files;
		}

		$d = dir($dir);
		while (false !== ($entry = $d->read())) {
			if (preg_match('/^([0-9]+)\-(.*)\.php/i', $entry, $matches)) {
				$versionNumber = (int) $matches[1];
				$className = $matches[2];
				if ($versionNumber > $from && $versionNumber <= $to) {
					$path = $this->_relativePath($this->dir, $dir);
					$files["v{$matches[1]}"] = array(
						'path' => $path,
						'filename' => $entry,
						'version' => $versionNumber,
						'classname' => $className);
				}
			} elseif ($entry != '.' && $entry != '..') {
				$subdir = $dir . '/' . $entry;
				if (is_dir($subdir) && is_readable($subdir)) {
					$files = array_merge(
							$files, $this->_getMigrationFiles(
									$currentVersion, $stopVersion, $subdir
							)
					);
				}
			}
		}
		$d->close();

		if ($direction == 'up') {
			ksort($files);
		} else {
			krsort($files);
		}

		return $files;
	}

	/**
	 * Actually perform a migration as specified in the $migration data
	 * 
	 * @param array $migration an array of data required to perform the migration
	 * @param string $direction 'up' or 'down'
	 * @throws \Exception
	 */
	protected function _processFile($migration, $direction)
	{
		$path = $this->trimSlash($migration['path']);
		$version = $migration['version'];
		$filename = $this->trimSlash($migration['filename'], 'left');
		$classname = $this->namespace  . '\\' . $migration['classname'];

		$file = $path 
				? $this->dir . '/' . $path . '/' . $filename
				: $this->dir . '/' . $filename;
		$this->writeln('Processing file: ' . $file);
		require_once $file;
		if (!class_exists($classname, false)) {
			throw new \Exception("Could not find class '$classname' in file '$filename'");
		}
		$class = new $classname($this->pdo, $this->tablePrefix);
		$class->$direction();

		if ($direction == 'down') {
			// current version is actually one lower than this version now
			$version--;
		}
		$this->_updateSchemaVersion($version);
	}

	/**
	 * Hard update the stored schema version
	 * 
	 * @param type $version
	 */
	protected function _updateSchemaVersion($version)
	{
		$version = (int) $version;
		$schemaVersionTableName = $this->getPrefixedSchemaVersionTableName();
		$sql = 'UPDATE  `' . $schemaVersionTableName . '` SET `version` = ' . $version;
		$this->pdo->exec($sql);
	}

	/**
	 * Utility function to get a relative path
	 * 
	 * @param string $from
	 * @param string $to
	 * @param string $ps path separator
	 * @return string
	 */
	protected function _relativePath($from, $to, $ps = DIRECTORY_SEPARATOR)
	{
		$arFrom = explode($ps, rtrim($from, $ps));
		$arTo = explode($ps, rtrim($to, $ps));
		while (count($arFrom) && count($arTo) && ($arFrom[0] == $arTo[0])) {
			array_shift($arFrom);
			array_shift($arTo);
		}
		return str_pad("", count($arFrom) * 3, '..' . $ps) . implode($ps, $arTo);
	}

	/**
	 * Get the prefixed schema-version table name
	 * 
	 * @return string
	 */
	public function getPrefixedSchemaVersionTableName()
	{
		return $this->tablePrefix . $this->schemaVersionTableName;
	}
	
	/**
	 * Write to output
	 *
	 * @param array $messages
	 * @param boolean $newline
	 * @param int $type
	 */
    public function write($messages, $newline = false, $type = 0)
	{
		if ($this->output) {
			$this->output->write($messages, $newline, $type);
		}
	}

	/**
	 * Writeln to output
	 * 
	 * @param array $messages
	 * @param int $type
	 */
    public function writeln($messages, $type = 0)
	{
		$this->write($messages, true, $type);
	}

	/**
	 * Callback to prefix message
	 * 
	 * @param string $msg
	 */
	protected function cbPrefix()
	{
		$prefix = $this->outputPrefix;
		return function($msg) use ($prefix) {
			return $prefix . $msg;
		};
	}

	protected function trimSlash($path, $side = 'right')
	{
		$method = ('left' == $side) ? 'ltrim' : 'rtrim';
		foreach (array('/', '\\') as $slash) {
			$path = $method($path, $slash);
		}
		return $path;
	}
}
