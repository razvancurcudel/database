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

use KoolKode\Database\ConnectionInterface;
use KoolKode\Database\Schema\Column;

class MigrationManager
{
	public function handleCommand()
	{
		$projectDir = realpath(__DIR__ . '/../../../../../');
		$migrationsDir = $projectDir . '/migration';
		
		$args = array_slice($_SERVER['argv'], 1);
		
		if(is_dir($migrationsDir))
		{
			printf("MIGRATIONS:\n");
			
			foreach(glob($migrationsDir . '/*.php') as $file)
			{
				printf("- %s\n", $file);
			}
		}
		
		if(!empty($args) && $args[0] == 'generate')
		{
			$date = new \DateTime('@' . time());
			$date->setTimezone(new \DateTimeZone('UTC'));
			
			$class = 'Version' . $date->format('YmdHis');
			$file = $class . '.php';
			
			$tpl = [
				'###CLASS###' => $class,
				'###DATE###' => $date->format('Y-m-d H:i:s') . ' UTC'
			];
			$code = strtr(file_get_contents(__DIR__ . '/MigrationTemplate.txt'), $tpl);
			
			file_put_contents($migrationsDir . '/' . $file, $code);
		}
	}
	
	public function migrateDirectoryUp($dir, ConnectionInterface $conn)
	{
		$platform = $conn->getPlatform();
		$migrations = [];
		
		foreach(glob($dir . '/*.php') as $file)
		{
			$file = new \SplFileInfo($file);
			
			if(preg_match("'^(Version([0-9]{14}))\\.php$'i", $file->getFilename(), $m))
			{
				$className = $m[1];
				$version = $m[2];
				
				require_once $file->getPathname();
				
				$migration = new $className($version, $conn, $platform);
				$migrations[$version] = $migration;
			}
		}
		
		ksort($migrations);
		
		if(!$platform->hasTable('#__kk_migrations'))
		{
			$table = $platform->createTable('#__kk_migrations');
			$table->addColumn('version', Column::TYPE_CHAR, ['limit' => 14, 'primary_key' => true]);
			$table->addColumn('migrated', Column::TYPE_CHAR, ['limit' => 14]);
			$table->addIndex(['migrated']);
			$table->create();
		}
		
		foreach($migrations as $migration)
		{
			$stmt = $conn->prepare("SELECT 1 FROM `#__kk_migrations` WHERE `version` = :version");
			$stmt->bindValue('version', $migration->getVersion());
			$stmt->execute();
			
			if(!$stmt->fetchNextColumn(0))
			{
				$migration->up();
				
				$conn->insert('#__kk_migrations', [
					'version' => $migration->getVersion(),
					'migrated' => gmdate('YmdHis')
				]);
			}
		}
	}
	
	public function migrateDirectoryDown($dir, ConnectionInterface $conn)
	{
		
	}
}
