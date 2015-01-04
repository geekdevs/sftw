<?php

namespace Dws\Sftw\Db\Schema;

abstract class AbstractChange
{

	/**
	 * @var \PDO
	 */
	protected $pdo;

	/**
	 * @var string
	 */
	protected $tablePrefix;

	function __construct(\PDO $pdo, $tablePrefix = '')
	{
		$this->pdo = $pdo;
		$this->tablePrefix = $tablePrefix;
	}

	/**
	 * Changes to be applied in this change
	 */
	abstract function up();

	/**
	 * Rollback the changes made in up()
	 */
	abstract function down();

	/**
	 * Convenience method for wrapping a query in a try/catch
	 *
	 * @param string $sql
	 * @throws \RuntimeException
	 */
    protected function querySQL($sql)
    {
        try {
            $this->pdo->query($sql);
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage() . PHP_EOL . PHP_EOL . $sql);
        }
    }
}

