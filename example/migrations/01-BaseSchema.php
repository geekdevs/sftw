<?php

namespace Ooga\Db\Migration;

use Dws\Sftw\Db\Schema\AbstractChange as SchemaChange;

/**
 * @author David Weinraub <david@papayasoft.com>
 */
class BaseSchema extends SchemaChange
{

	public function up()
	{

		$sql = '
			CREATE TABLE `test_migrate_1` (
				`id` INT(11) UNSIGNED NOT NULL,
				`name` VARCHAR(255)
			)
		';
		$this->querySQL($sql);
	}

	public function down()
	{
		$sql = 'DROP TABLE `test_migrate_1`';
		$this->querySQL($sql);
	}

}