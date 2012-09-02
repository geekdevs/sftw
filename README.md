South For the Winter (stfw)
===========================

*South for the Winter* ("sftw") is a db migration tool, a port of the
[Akrabat](https://github.com/akrabat/Akrabat) db migration tool. In fact, it's as close
to a straight rip-off as can be with one primary difference: `Akrabat` uses a 
`Zend\Db` database adapter and is largely targeted towards 
[Zend Framework](http://framework.zend.com/) apps, while `sftw` uses a vanilla 
[PDO](http://www.php.net/manual/en/book.pdo.php) adapter. Because of this, `stfw` 
should be usable in circumstances where the ZF components are unavailable or 
merely considered too heavywight.

Install
=======

```
$ git clone git@github.com:startupdevs/sftw.git
$ cd stfw
$ php composer.phar install
$ cd bin
$ chmod +x ./stfw.php
$ alias sftw="php `pwd`/bin/console.php stfw"
```

Usage
=====

@ TODO

Invocation with the example migrations provided, start in the project root:

To display the current schema version:

    $ stfw --host localhost --user myuser --pass mypass --db mydb --namespace Ooga/Db/Migration --path ./example/migrations

To upgrade to latest schema version:

    $ stfw --host localhost --user myuser --pass mypass --db mydb --namespace Ooga/Db/Migration --path ./example/migrations latest

To target a specifi schema version (in this case 1):

    $ stfw --host localhost --user myuser --pass mypass --db mydb --namespace Ooga/Db/Migration --path ./example/migrations 1

To roll all the way back to the state before the first migration file:

    $ stfw --host localhost --user myuser --pass mypass --db mydb --namespace Ooga/Db/Migration --path ./example/migrations 0

Next Steps
==========

* Make `stfw` a complete composer package so that it cam be used in other projects 
simply by adding the relevant entries to the project's `composer.json` file

* Open-source it?

* Shorten the command-line invocation, probably by allowing a local config file 
(with some default name like `sftw.ini`) that contains the params, similar to how 
`phpunit` employs `phpunit.xml`.

