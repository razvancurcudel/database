<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database\Schema;

class Index
{
	protected $columns;
	
	protected $options;
	
	public function __construct(array $columns, array $options = [])
	{
		$this->columns = $columns;
		$this->options = $options;
	}
	
	public function getName($tableName)
	{
		return 'idx_' . md5($tableName . '||' . \implode(',', $this->columns));
	}
	
	public function getColumns()
	{
		return $this->columns;
	}
	
	public function isUnique()
	{
		return !empty($this->options['unique']);
	}
}
