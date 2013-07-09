<?php

namespace Ooga\Db\Migration;

use Dws\Sftw\Db\Schema\AbstractChange as SchemaChange;

/**
 * @author David Weinraub <david@papayasoft.com>
 */
class DoSomethingWrong extends SchemaChange
{

	public function up()
	{
		$sql = <<< EOT
			ALTER TABLE `users`
				ADD COLUMN `mycol` AFTER `name`,
				ADD COLUMN `mycol` AFTER `name`;
EOT;
		$this->querySQL($sql);	
	}

	public function down()
	{
		$sql = <<< EOT
			ALTER TABLE `users`
				DROP COLUMN `mycol`;
EOT;
		$this->querySQL($sql);
	}

}