<?php

namespace Dws\Db\Schema;

use \PDO;

/**
 * 
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
	}

	function getCurrentSchemaVersion()
	{
		$schemaVersionTableName = $this->getPrefixedSchemaVersionTableName();

		$selectSql = "SELECT version FROM " . $schemaVersionTableName;
		$select = $this->pdo->prepare($selectSql);
		try {
			if ($select->execute()){
				$version = $select->fetchObject()->version;				
			} else {
				// means that the schema version table doesn't exist, so create it
				$createSql = "CREATE TABLE $schemaVersionTableName ( 
					version bigint NOT NULL,
					PRIMARY KEY (version)
				)";
				$this->pdo->exec($createSql);
				$insertSql = "INSERT INTO $schemaVersionTableName (version) VALUES (0)";
				$this->pdo->exec($insertSql);
				$select->execute();
				$version = $select->fetchObject()->version;				
			}
		
		} catch (\Exception $e) {
		}

		return $version;
	}

	function updateTo($version = null)
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

	protected function _updateSchemaVersion($version)
	{
		$schemaVersionTableName = $this->getPrefixedSchemaVersionTableName();
		$sql = "UPDATE  $schemaVersionTableName SET version = " . (int) $version;
		$this->pdo->exec($sql);
	}

	public function getPrefixedSchemaVersionTableName()
	{
		return $this->tablePrefix . $this->schemaVersionTableName;
	}

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

}
