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

use Doctrine\DBAL\DriverManager;
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
	protected $adapters = [];
	
	protected $connections = [];
	
	protected $config;
	
	protected $logger;
	
	protected $manager;
	
	public function __construct(Configuration $config = NULL)
	{
		$this->config = $config ?: new Configuration();
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
	
	public function setTransactionManager(TransactionManagerInterface $manager = NULL)
	{
		$this->manager = $manager;
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
		
		$conn = static::createDoctrineConnection($config->getConfig($name)->toArray());
		
// 		$config = $config->getConfig($name);
		
// 		if($config->has('dsn'))
// 		{
// 			$conn = $this->createPDOConnection(
// 				$config->getString('dsn'),
// 				$config->get('username', NULL),
// 				$config->get('password', NULL),
// 				$config->toArray()
// 			);
// 		}
// 		else
// 		{
// 			$conn = static::createDoctrineConnection($config->toArray());
// 		}
		
		return $this->adapters[$name] = $conn;
	}
	
	public function createPDOConnection($dsn, $username = NULL, $password = NULL, array $params = [])
	{
		$params = array_merge([
			'managed' => true,
			'options' => []
		], $params);
		
		if('' == trim($username))
		{
			$username = NULL;
		}
		
		if('' == trim($password))
		{
			$password = NULL;
		}
		
		$pdo = new \PDO($dsn, $username, $password);
		$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		
		if($this->manager !== NULL && isset($params['managed']))
		{
			$conn = new PDO\ManagedConnection($this->manager, $pdo, $params['options']);
		}
		else
		{
			$conn = new PDO\Connection($pdo, $params['options']);
		}
		
		return $conn;
	}
	
	/**
	 * Create a connection backed by a Doctrine DBAL connection.
	 * 
	 * This method will accept a param named "dsn" and extract contained data into the DBAL params array.
	 * 
	 * @param array<string, mixed> $params
	 * @return \KoolKode\Database\Doctrine\Connection
	 */
	public function createDoctrineConnection(array $params)
	{
		$managed = isset($params['managed']);
		$options = !empty($params['options']) ? (array)$params['options'] : [];
		
		unset($params['managed']);
		unset($params['options']);
		
		if(isset($params['dsn']))
		{
			list($type, $tmp) = explode(':', $params['dsn'], 2);
			unset($params['dsn']);
			
			$cfg = [
				'driver' => 'pdo_' . $type
			];
			
			if($cfg['driver'] == 'pdo_sqlite')
			{
				if($tmp[1] == ':memory;')
				{
					$cfg['memory'] = true;
				}
				else
				{
					$cfg['path'] = $tmp[1];
				}
			}
			else
			{
				foreach(explode(';', $tmp) as $conf)
				{
					$parts = array_map('trim', explode('=', $conf, 2));
					$cfg[$parts[0]] = $parts[1];
				}
			}
			
			$params = array_merge($cfg, $params);
		}
		
		return new Doctrine\Connection(DriverManager::getConnection($params), $options);
	}
}
