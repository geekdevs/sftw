South For the Winter (sftw)
===========================

*South for the Winter* (`sftw`) is a db migration tool, a port of the
[Akrabat](https://github.com/akrabat/Akrabat) db migration tool. In fact, it's as close
to a straight rip-off as can be with one primary difference: `Akrabat` uses a 
`Zend\Db` database adapter and is largely targeted towards 
[Zend Framework](http://framework.zend.com/) apps, while `sftw` uses a vanilla 
[PDO](http://www.php.net/manual/en/book.pdo.php) adapter. Because of this, `sftw` 
should be usable in circumstances where the ZF components are unavailable or 
merely considered too heavywight.

Install
=======

As a standalone component
-------------------------

```
$ git clone git@github.com:startupdevs/sftw.git
```

In another project via Composer
-------------------------------

Add to your project's `composer.json` as follows:
```
{
	"repositories" : [
		{
			"type" : "vcs",
			"url" : "https://github.com/startupdevs/sftw.git"
		}
	]
	"require": {
		"startupdevs/sftw" : "dev-master"
	}
}
```

Optionally, you can add a `bin-dir` entry into the `config` section of your 
project's `composer.json` to specify where the `sftw` CLI scripts are symlinked.

```
{
    "config": {
        "bin-dir": "scripts"
    }	
}
```

Then in your pject root:

	$ php composer.phar update

Usage
=====

Assumes you have installed SFTW via Composer in your project `myproject` with a `bin-dir`
value of `scripts`.

Define one migration class - extending Dws\Db\Schema\AbstractChange for each schema 
change you wish to implement. For example:

```
/*
* Adding your own namespace to the migration classes is optional. If you do,
* then you will be required to specify it during the invocation of the migration
* script.
*/
namespace Ooga\Db\Migration;

use Dws\Db\Schema\AbstractChange as SchemaChange;

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
```

Save this file as:

	/path/to/myproject/scripts/migrations/001-AddUserTable.php

Invocation, starting in the project root, is as follows:

To display the current schema version:

    $ php ./scripts/console.php sftw --host myhost --user myuser --pass mypass --db mydb

To upgrade to latest schema version:

    $ php ./scripts/console.php sftw --host myhost --user myuser --pass mypass --db mydb --path ./scripts/migrations --namespace Ooga/Db/Migrations latest

Note that for convenience, you can use forward slashes (/) in the namespace. They will be reversed before use.

To target a specific schema version (in this case 1):

    $ php ./scripts/console.php sftw --host myhost --user myuser --pass mypass --db mydb --path ./scripts/migrations --namespace Ooga/Db/Migrations 1

To roll all the way back to the state before the first migration file:

    $ php ./scripts/console.php sftw --host myhost --user myuser --pass mypass --db mydb --path ./scripts/migrations --namespace Ooga/Db/Migrations 0

Next Steps
==========

1. Allow a local config file (with some default name like `sftw.ini`) to contain 
connection/namespace/path params, similar to how `phpunit` employs `phpunit.xml` 
by default.
2. Use some clever shell madness to hide the `console.php` name and invoke simply as: `$ sftw <params> <args>`
