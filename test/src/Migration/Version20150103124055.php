<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use KoolKode\Database\Migration\AbstractMigration;

class Version20150103124055 extends AbstractMigration
{
	public function up()
	{
		$test1 = $this->table('#__test1');
		$test1->addColumn('id', 'int', ['identity' => true]);
		$test1->addColumn('title', 'varchar');
		$test1->create();
	}
	
	public function down()
	{
		$this->dropTable('#__test1');
	}
}
