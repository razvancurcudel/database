<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database\Migration;

class MigrationConfig
{
	protected $migrations = [];
	
	public function addMigration(AbstractMigration $migration)
	{
		$this->migrations[$migration->getVersion()] = $migration;
		
		ksort($this->migrations);
	}
	
	public function loadMigrationsFromDirectory($dir)
	{
		foreach(glob($dir . DIRECTORY_SEPARATOR . '*.php') as $file)
		{
			$file = new \SplFileInfo($file);
		
			if($file->isFile() && preg_match("'^(Version([0-9]{14}))\\.php$'i", $file->getFilename(), $m))
			{
				$this->migrations[$m[2]] = realpath($file->getPathname());
			}
		}
		
		ksort($this->migrations);
	}
	
	public function getMigrations()
	{
		return $this->migrations;
	}
}
