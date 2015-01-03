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
use KoolKode\Database\Schema\Table;

class MigrationManager
{
	protected $conn;
	
	protected $platform;
	
	public function __construct(ConnectionInterface $conn)
	{
		$this->conn = $conn;
		$this->platform = $conn->getPlatform();
	}
	
	public function getConnection()
	{
		return $this->conn;
	}
	
	public function getPlatform()
	{
		return $this->platform;
	}
	
	public function flushDatabase()
	{
		$this->platform->flushDatabase();
	}
	
	public function loadMigrations($dir)
	{
		$migrations = [];
		
		foreach(glob($dir . '/*.php') as $file)
		{
			$file = new \SplFileInfo($file);
				
			if(preg_match("'^(Version([0-9]{14}))\\.php$'i", $file->getFilename(), $m))
			{
				$className = $m[1];
				$version = $m[2];
		
				require_once $file->getPathname();
				
				$migrations[$version] = new $className($version, $this->conn, $this->platform);
			}
		}
		
		ksort($migrations);
		
		return $migrations;
	}
	
	/**
	 * Execute all up-migrations found in the given directory.
	 * 
	 * DB will be flushed if a migration must be applied, will simply flush all data if no migration is needed.
	 * 
	 * @param string $dir Directory where migration PHP-files are placed.
	 * @param boolean $flushDatabase Flush database before any migration is applied?
	 */
	public function migrateDirectoryUp($dir, $flushDatabase = false)
	{
		$migrations = $this->loadMigrations($dir);
		
		if(empty($migrations))
		{
			$skip = false;
		}
		else
		{
			$skip = $this->platform->hasTable('#__kk_migrations');
			
			if($skip)
			{
				$params = [];
				$in = [];
				
				foreach(array_values($migrations) as $i => $migration)
				{
					$in[] = ':v' . $i;
					$params['v' . $i] = $migration->getVersion();
				}
				
				$stmt = $this->conn->prepare(sprintf("SELECT COUNT(*) FROM `#__kk_migrations` WHERE `version` IN (%s)", implode(', ', $in)));
				$stmt->bindAll($params);
				$stmt->execute();
	
				// Only skip migrations if all of them are alreay migrated up.
				$skip = (count($params) === (int)$stmt->fetchNextColumn(0));
			}
		}
		
		if(!$skip)
		{
			if($flushDatabase)
			{
				$this->platform->flushDatabase();
			}
			
			foreach($migrations as $migration)
			{
				$this->migrateUp($migration);
			}
		}
		
		if($skip && $flushDatabase)
		{
			$this->platform->flushData();
		}
	}
	
	public function migrateUp(AbstractMigration $migration)
	{
		$this->ensureMigrationTableExists();
		
		$stmt = $this->conn->prepare("SELECT 1 FROM `#__kk_migrations` WHERE `version` = :version");
		$stmt->bindValue('version', $migration->getVersion());
		$stmt->execute();
			
		if(!$stmt->fetchNextColumn(0))
		{
			$migration->up();
		
			$this->conn->insert('#__kk_migrations', [
				'version' => $migration->getVersion(),
				'migrated' => gmdate('YmdHis')
			]);
			
			return true;
		}
		
		return false;
	}
	
	public function migrateDirectoryDown($dir, ConnectionInterface $conn)
	{
		foreach(array_reverse($this->loadMigrations($dir)) as $migration)
		{
			$this->migrateDown($migration);
		}
	}
	
	public function migrateDown(AbstractMigration $migration)
	{
		throw new \RuntimeException('Migrating down is not supported yet');
	}
	
	protected function ensureMigrationTableExists()
	{
		if(!$this->platform->hasTable('#__kk_migrations'))
		{
			$table = new Table('#__kk_migrations', $this->platform);
			$table->addColumn('version', 'bigint', ['limit' => 14, 'primary_key' => true]);
			$table->addColumn('migrated', 'char', ['limit' => 14]);
			$table->addIndex(['migrated']);
			$table->create();
		}
	}
	
	public static function handleCommand()
	{
		$projectDir = realpath(__DIR__ . '/../../../../../');
		$migrationsDir = $projectDir . '/migration';
	
		$args = array_slice($_SERVER['argv'], 1);
	
		if(empty($args))
		{
			printf("KoolKode DB Migration System\n\n");
			printf("USAGE:\n");
				
			static::showCommandHelp();
		}
		else
		{
			switch(strtolower($args[0]))
			{
				case 'generate':
					$date = new \DateTime('@' . time());
					$date->setTimezone(new \DateTimeZone('UTC'));
	
					$class = 'Version' . $date->format('YmdHis');
					$file = $class . '.php';
	
					$tpl = [
						'###CLASS###' => $class,
						'###DATE###' => $date->format('Y-m-d H:i:s') . ' UTC'
					];
					$code = strtr(file_get_contents(__DIR__ . '/MigrationTemplate.txt'), $tpl);
					$target = str_replace('\\/', DIRECTORY_SEPARATOR, $migrationsDir . '/' . $file);
	
					printf("[%s]\n", $target);
						
					while(true)
					{
						printf("Generate migration file? [n] ");
						$input = trim(fgets(STDIN));
	
						switch(strtolower($input))
						{
							case '':
							case 'n':
							case 'no':
								return;
							case 'y':
							case 'yes':
								file_put_contents($target, $code);
								printf("[created] Migration file generated.\n");
								break 2;
							default:
								printf("Invalid input, accepted inputs are [y] [yes] [n] [no]\n");
						}
					}
					break;
				default:
					printf("Unsupported command: \"%s\"\n", $args[0]);
						
					static::showCommandHelp();
			}
		}
	}
	
	protected static function showCommandHelp()
	{
		printf(" migration generate - Create a new Migration PHP file in the \"migration\" directory of the project.\n");
	}
}
