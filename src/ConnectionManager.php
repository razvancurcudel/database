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
use KoolKode\Event\EventDispatcherInterface;
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
	
	protected $eventDispatcher;
	
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
	
	/**
	 * Creates a connection manager from a <code>.kkdb.php</code> config file.
	 * 
	 * @param string $file
	 * @return ConnectionManager
	 * 
	 * @throws \RuntimeException When the config file could not be found.
	 */
	public static function fromConfigFile($file)
	{
		if(!is_file($file))
		{
			throw new \RuntimeException(sprintf('Config file "%s" does not exist', $file));
		}
		
		$config = new Configuration(static::processConfigFileData(require $file));
		
		return new ConnectionManager($config->getConfig('ConnectionManager'));
	}
	
	protected static function processConfigFileData(array $data)
	{
		$result = array_change_key_case($data, CASE_LOWER);
	
		foreach($result as & $val)
		{
			if(is_array($val))
			{
				$val = static::processConfigFileData($val);
			}
		}
	
		return $result;
	}
	
	public function setEventDispatcher(EventDispatcherInterface $dispatcher = NULL)
	{
		$this->eventDispatcher = $dispatcher;
	}
	
	public function setTransactionManager(TransactionManagerInterface $manager = NULL)
	{
		$this->manager = $manager;
	}
	
	public function setLogger(LoggerInterface $logger = NULL)
	{
		$this->logger = $logger;
	}
	
	/**
	 * {@inheritdoc}
	 */
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
			$conn->addDecorator(new PrefixConnectionDecorator($config->getString('prefix')));
		}
		
		return $this->connections[$name] = $conn;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function getRegisteredConnectionNames()
	{
		return array_keys($this->config->getConfig('connection')->toArray());
	}
	
	public function getAdapter($name)
	{
		if(isset($this->adapters[$name]))
		{
			return clone $this->adapters[$name];
		}
		
		$config = $this->config->getConfig('adapter');
		
		if(!$config->has($name))
		{
			throw new \OutOfBoundsException(sprintf('Database adapter not registered: "%s"', $name));
		}
		
		$config = $config->getConfig($name);
		
		if($config->has('dsn'))
		{
			$conn = $this->createPDOConnection(
				$config->getString('dsn'),
				$config->get('username', NULL),
				$config->get('password', NULL),
				$config->toArray()
			);
		}
		else
		{
			$conn = $this->createDoctrineConnection($config->toArray());
		}
		
		$this->adapters[$name] = $conn;
		
		return clone $conn;
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
		
		$conn->setEventDispatcher($this->eventDispatcher);
		
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
// 		$managed = isset($params['managed']);
		$options = !empty($params['options']) ? (array)$params['options'] : [];
		
		if(array_key_exists('encoding', $params))
		{
			$params['charset'] = $params['encoding'];
			unset($params['encoding']);
		}
		
		unset($params['managed']);
		unset($params['options']);
		
		$params = array_merge([
			'charset' => 'utf8'
		], $params);
		
		if(array_key_exists('username', $params))
		{
			if(!array_key_exists('user', $params))
			{
				$params['user'] = $params['username'];
				unset($params['username']);
			}
		}
		
		if(isset($params['dsn']))
		{
			list($type, $tmp) = explode(':', $params['dsn'], 2);
			unset($params['dsn']);
			
			$cfg = [
				'driver' => 'pdo_' . $type
			];
			
			if($cfg['driver'] == 'pdo_sqlite')
			{
				if($tmp == ':memory:')
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
		
		$conn = new Doctrine\Connection(DriverManager::getConnection($params), $options);
		$conn->setEventDispatcher($this->eventDispatcher);
		
		return $conn;
	}
}
