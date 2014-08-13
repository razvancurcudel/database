<?php

/*
 * This file is part of KoolKode Database.
*
* (c) Martin Schröder <m.schroeder2007@gmail.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace KoolKode\Database;

use KoolKode\Config\Configuration;
use KoolKode\Transaction\TransactionManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Connection manager that can be connected to an external transaction manager.
 * 
 * @author Martin Schröder
 */
class ConnectionManager implements ConnectionManagerInterface
{
	const NAME_DEFAULT = 'default';
	
	protected $config;
	protected $logger;
	protected $manager;
	
	protected $connections = [];
	
	public function __construct(Configuration $config, LoggerInterface $logger = NULL, TransactionManagerInterface $manager = NULL)
	{
		$this->config = $config;
		$this->logger = $logger;
		$this->manager = $manager;
	}
	
	public function getConnection($name)
	{
		if(isset($this->connections[$name]))
		{
			return $this->connections[$name];
		}
		
		$config = $this->config->getConfig('connection');
		
		if(!$config->has($name))
		{
			if($config->has(self::NAME_DEFAULT))
			{
				return $this->getConnection(self::NAME_DEFAULT);
			}
			
			throw new \OutOfBoundsException(sprintf('PDO connection not found: "%s"', $name));
		}
		
		$config = $config->getConfig($name);
		
		if($config->has('alias'))
		{
			return $this->getConnection($config->getString('alias'));
		}
		
		$dsn = $config->getString('dsn');
		
		if($this->manager !== NULL && $config->getBoolean('managed', true))
		{
			$pdo = new ManagedConnection($this->manager, $dsn, $config->get('username', NULL), $config->get('password', NULL));
		}
		else
		{
			$pdo = new Connection($dsn, $config->get('username', NULL), $config->get('password', NULL));
		}
		
		if($this->logger !== NULL)
		{
			$this->logger->info('Established database connection <{dsn}>', [
				'dsn' => $dsn
			]);
		}
		
		$pdo->setLogger($this->logger);
		$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$pdo->setDebug($config->getBoolean('debug', false));
		
		switch($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME))
		{
			case 'mysql':
				$sql = sprintf('SET NAMES %s', $pdo->quote($config->getString('encoding', 'utf8')));
				$sql .= sprintf(', SESSION time_zone = %s', $pdo->quote(gmdate('P')));
				$pdo->exec($sql);
				break;
			case 'sqlite':
				$pdo->exec("PRAGMA journal_mode = WAL");
				$pdo->exec("PRAGMA locking_mode = EXCLUSIVE");
				$pdo->exec("PRAGMA synchronous = NORMAL");
				break;
		}
		
		if($config->getBoolean('shared', true))
		{
			return $this->connections[$name] = $pdo;
		}
		
		return $pdo;
	}
}
