<?php

namespace Dws\Db\Schema;

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
	 * Table prefix string for use by change classes
	 * 
	 * @var string
	 */
	protected $tablePrefix;

	/**
	 * Constructor
	 * 
	 * @param PDO $pdo
	 */
	public function __construct(PDO $pdo, $dir, $namespace = '', $tablePrefix = '')
	{
		$this->pdo = $pdo;
		$this->dir = $dir;
		$this->namespace = str_replace('/', '\\', $namespace);
		$this->tablePrefix = $tablePrefix;
		
		$this->ensureSchemaVersionTableExists();
	}
	
	public function doesSchemaVersionTableExist()
	{
		$select = $this->getPreparedSqlSelectStatementForCurrentVersion();
		if ($select->execute() === false){
			return false;
		} else {
			return true;
		}
	}
	
	/**
	 * Ensure that the schema version able exists and contains at least a single record
	 * with the version field.
	 * 
	 * @return Manager
	 */
	public function ensureSchemaVersionTableExists()
	{
		$schemaVersionTableName = $this->getPrefixedSchemaVersionTableName();
		if (!$this->doesSchemaVersionTableExist()){
			// means that the schema version table doesn't exist, so create it
			$createSql = 
			'
				CREATE TABLE `' . $schemaVersionTableName . '` ( 
					version bigint NOT NULL,
					PRIMARY KEY (`version`)
				)
			';
			$this->pdo->exec($createSql);
			$insertSql = "INSERT INTO `' . $schemaVersionTableName` . ' (`version`) VALUES (0)";
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
		foreach ($migrations as $migration) {
			$this->_processFile($migration, $direction);
		}

		return self::RESULT_OK;
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
		$path = $migration['path'];
		$version = $migration['version'];
		$filename = $migration['filename'];
		$classname = $this->namespace  . '\\' . $migration['classname'];
		require_once $this->dir . '/' . $path . '/' . $filename;
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
}
