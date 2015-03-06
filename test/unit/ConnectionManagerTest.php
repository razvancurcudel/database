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
use KoolKode\Config\YamlConfigurationLoader;
use KoolKode\Database\ConnectionInterface;
use KoolKode\Database\Test\DatabaseTestTrait;

class ConnectionManagerTest extends \PHPUnit_Framework_TestCase
{
	use DatabaseTestTrait;
	
	public function testCanCreateManager()
	{
		$params = [
			'dsn' => self::getEnvParam('DB_DSN', 'sqlite::memory:'),
			'username' => self::getEnvParam('DB_USERNAME', NULL),
			'password' => self::getEnvParam('DB_PASSWORD', NULL)
		];
		
		$loader = new YamlConfigurationLoader();
		$file = new \SplFileInfo(__DIR__ . DIRECTORY_SEPARATOR . 'ConnectionManagerTest.yml');
		
		$manager = new ConnectionManager(new Configuration($loader->load($file, $params)));
		
		$adapter = $manager->getAdapter('default');
		$this->assertInstanceOf(ConnectionInterface::class, $adapter);
		
		$conn = $manager->getConnection('default');
		$this->assertNotSame($adapter, $conn);
		
		// Assert prefix decorator is enabled.
		$this->assertEquals('foo', $adapter->applyPrefix('#__foo'));
		$this->assertEquals('test_foo', $conn->applyPrefix('#__foo'));
	}
	
	/**
	 * @expectedException \OutOfBoundsException
	 */
	public function testWillThrowExceptionOnMissingAdapter()
	{
		(new ConnectionManager(new Configuration()))->getAdapter('foo');
	}
	
	/**
	 * @expectedException \OutOfBoundsException
	 */
	public function testWillThrowExceptionOnMissingConnection()
	{
		(new ConnectionManager(new Configuration()))->getConnection('foo');
	}
}
