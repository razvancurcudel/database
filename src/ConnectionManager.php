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
use KoolKode\Database\PDO\Connection;
use KoolKode\Database\PDO\ManagedConnection;
use KoolKode\Transaction\TransactionManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Connection manager that can be connected to an external transaction manager.
 * 
 * @author Martin Schröder
 */
class ConnectionManager implements ConnectionManagerInterface
{
	protected $adapters = [];
	
	protected $connections = [];
	
	protected $config;
	
	protected $logger;
	
	protected $manager;
	
	public function __construct(Configuration $config, TransactionManagerInterface $manager = NULL)
	{
		$this->config = $config;
		$this->manager = $manager;
	}
	
	public function __debugInfo()
	{
		return [
			'adapters' => array_keys($this->adapters),
			'connections' => array_keys($this->connections),
			'config' => $this->config,
			'manager' => $this->manager
		];
	}
	
	public function setLogger(LoggerInterface $logger = NULL)
	{
		$this->logger = $logger;
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
			throw new \OutOfBoundsException(sprintf('Database connection not registered: "%s"', $name));
		}
		
		$config = $config->getConfig($name);
		$conn = $this->getAdapter($config->getString('adapter'));
		
		if($config->has('prefix'))
		{
			$conn = new PrefixConnectionDecorator($conn, $config->getString('prefix'));
		}
		
		return $this->connections[$name] = $conn;
	}
	
	public function getAdapter($name)
	{
		if(isset($this->adapters[$name]))
		{
			return $this->adapters[$name];
		}
		
		$config = $this->config->getConfig('adapter');
		
		if(!$config->has($name))
		{
			throw new \OutOfBoundsException(sprintf('Database adapter not registered: "%s"', $name));
		}
		
		$config = $config->getConfig($name);
		
		$dsn = $config->getString('dsn');
		
		$pdo = $this->createPDO($dsn, $config->get('username', NULL), $config->get('password', NULL));
		$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		
		if($this->logger !== NULL)
		{
			$this->logger->info('Database adapter connected to <{dsn}>', [
				'dsn' => $dsn
			]);
		}
		
		if($this->manager !== NULL && $config->getBoolean('managed', true))
		{
			$conn = new ManagedConnection($this->manager, $pdo, $config->getConfig('options')->toArray());
		}
		else
		{
			$conn = new Connection($pdo, $config->getConfig('options')->toArray());
		}
		
		return $this->adapters[$name] = $conn;
	}
	
	public function createPDO($dsn, $username = NULL, $password = NULL)
	{
		if('' == trim($username))
		{
			$username = NULL;
		}
		
		if('' == trim($password))
		{
			$password = NULL;
		}
		
		return new \PDO($dsn, $username, $password);
	}
}
