<?php

namespace Ooga\Db\Migration;

use Dws\Db\Schema\AbstractChange as SchemaChange;

/**
 * @author David Weinraub <david@papayasoft.com>
 */
class AddUserTable extends SchemaChange
{

	public function up()
	{
		$sql = '
			CREATE TABLE `user` (
				`id` INT(11) UNSIGNED NOT NULL,
				`name` VARCHAR(255)
			)
		';
		$this->pdo->exec($sql);	
	}

	public function down()
	{
		$sql = 'DROP TABLE `user`';
		$this->pdo->exec($sql);
	}

}