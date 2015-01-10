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
	
	protected $date;
	
	public function __construct(ConnectionInterface $conn, \DateTimeInterface $date = NULL)
	{
		$this->conn = $conn;
		$this->platform = $conn->getPlatform();
		
		$this->date = ($date === NULL) ? new \DateTime('@0') : clone $date;
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
	
	/**
	 * Execute all up-migrations loaded from the given config.
	 *
	 * DB will be flushed if a migration must be applied, will simply flush all data if no migration is needed.
	 *
	 * @param MigrationConfig $config All configured migrations to be applied.
	 * @param boolean $flushDatabase Flush database before any migration is applied?
	 */
	public function migrateUp(MigrationConfig $config, $flushDatabase = false)
	{
		$migrations = $this->instantiateMigrations($config);
		
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
		
				$mtime = clone $this->date;
		
				foreach(array_values($migrations) as $i => $migration)
				{
					$in[] = ':v' . $i;
					$params['v' . $i] = $migration->getVersion();
					$date = new \DateTime('@' . filemtime((new \ReflectionClass(get_class($migration)))->getFileName()));
						
					if($date > $mtime)
					{
						$mtime = $date;
					}
				}
		
				$stmt = $this->conn->prepare(sprintf("SELECT COUNT(*) AS cnt, MAX(`migrated`) AS mtime FROM `#__kk_migrations` WHERE `version` IN (%s)", implode(', ', $in)));
				$stmt->bindAll($params);
				$stmt->execute();
		
				// Only skip migrations if all of them are alreay migrated up.
				$row = $stmt->fetchNextRow();
				$dbcnt = (int)$row['cnt'];
				$dbmtime = \DateTime::createFromFormat('YmdHis', $row['mtime']);
		
				$skip = (count($params) === $dbcnt) && ($dbmtime > $mtime);
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
				$this->executeMigrationUp($migration);
			}
		}
		
		if($skip && $flushDatabase)
		{
			$this->platform->flushData();
		}
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
		$config = new MigrationConfig();
		$config->loadMigrationsFromDirectory($dir);
		
		$this->migrateUp($config, $flushDatabase);
	}
	
	protected function instantiateMigrations(MigrationConfig $config)
	{
		$migrations = [];
	
		foreach($config->getMigrations() as $version => $file)
		{
			if(!is_file($file))
			{
				throw new \RuntimeException(sprintf('Migration file not found: "%s"', $file));
			}
				
			require_once $file;
				
			$className = 'Version' . $version;
				
			if(!class_exists($className, false))
			{
				throw new \RuntimeException(sprintf('Migration class not found: %s', $className));
			}
				
			if(!is_subclass_of($className, AbstractMigration::class))
			{
				throw new \RuntimeException(sprintf('Migration class %s must extend %s', $className, AbstractMigration::class));
			}
				
			$migrations[$version] = new $className($version, $this->conn, $this->platform);
		}
		
		ksort($migrations);
	
		return $migrations;
	}
	
	protected function executeMigrationUp(AbstractMigration $migration)
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
			$table->addColumn('version', 'char', ['limit' => 14, 'primary_key' => true]);
			$table->addColumn('migrated', 'bigint', ['unsigned' => true]);
			$table->addIndex(['migrated']);
			$table->create();
		}
	}
}
