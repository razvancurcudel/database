<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database\Doctrine;

use Doctrine\DBAL\Driver\Statement as DoctrineStatement;
use KoolKode\Database\AbstractStatement;

/**
 * Adapts a wrapped Doctrine statement to the KoolKode Database API.
 * 
 * @author Martin Schröder
 */
class Statement extends AbstractStatement
{
	public function __construct(Connection $conn, $sql)
	{
		$this->conn = $conn;
		$this->sql = (string)$sql;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function execute()
	{
		try
		{
			if($this->stmt === NULL)
			{
				$dconn = $this->conn->getDoctrineConnection();
				$sql = $this->sql;
				
				if($this->limit > 0)
				{
					$dconn->getDatabasePlatform()->modifyLimitQuery($sql, $this->limit, ($this->offset > 0) ? $this->offset : NULL);
				}
				
				$this->stmt = $dconn->prepare($sql);
				$this->stmt->setFetchMode(\PDO::FETCH_ASSOC);
			}
			else
			{
				$this->closeCursor();
			}
			
			$this->bindEncodedParams();
			
	// 		$start = microtime(true);
			$this->stmt->execute();
	// 		$time = microtime(true) - $start;
			
			return (int)$this->stmt->rowCount();
		}
		catch(\Exception $e)
		{
			throw $this->conn->convertException($e);
		}
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function closeCursor()
	{
		if($this->stmt !== NULL)
		{
			try
			{
				$this->stmt->closeCursor();
			}
			catch(\Exception $e) { }
		}
		
		return $this;
	}
}
