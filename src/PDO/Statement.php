<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database\PDO;

use KoolKode\Database\AbstractStatement;

/**
 * Adapts a wrapped PDO statement to the KoolKode Database API.
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
		if($this->stmt === NULL)
		{
			$sql = $this->sql;
			
			if($this->limit > 0)
			{
				// TODO: Need more specific limit / offset support especially related to SQL 2008 standard.
				$version = $this->conn->getServerVersion();
				
				switch($this->conn->getDriverName())
				{
					case DB::DRIVER_SQLITE:
					case DB::DRIVER_MYSQL:
					case DB::DRIVER_POSTGRESQL:
						$sql .= sprintf(' LIMIT %u OFFSET %u', $this->limit, $this->offset);
						break;
					case DB::DRIVER_CUBRID:
						$sql .= sprintf(' LIMIT %u, %u', $this->offset, $this->limit);
						break;
					case DB::DRIVER_MSSQL:
						if($this->offset === 0)
						{
							$sql = preg_replace_callback("'(?:^|\W)SELECT\W'i", function(array $m) {
								return $m[0] . sprintf(' TOP %u ', $this->limit);
							}, $sql, 1);
						}
						else
						{
							throw new \RuntimeException('Limit + offset not implemented yet in MSSQL driver');
						}
						break;
					case DB::DRIVER_ORACLE:
						
						$num = 0;
						
						if(preg_match("'([0-9]+)[cg]'i", $version, $m))
						{
							$num = (int)$m[1];
						}
						if($num >= 12)
						{
							$sql .= sprintf(' OFFSET %u ROWS FETCH NEXT %u ROWS ONLY', $this->offset, $this->limit);
						}
						else
						{
							$sql = sprintf(
								'SELECT * FROM (SELECT kklq.*, ROWNUM kkrn FROM (' . $sql . ') kklq WHERE ROWNUM <= %u) WHERE kkrn > %u',
								$this->offset + $this->limit,
								$this->limit
							);
						}
						
						break;
					case DB::DRIVER_DB2:
						if($this->offset === 0)
						{
							$sql .= sprintf(' FETCH FIRST %u ROWS ONLY', $this->limit);
						}
						elseif($this->conn->getOption(DB::OPTION_DB2_LIMIT_OFFSET, false))
						{
							$sql .= sprintf(' LIMIT %u OFFSET %u', $this->limit, $this->offset);
						}
						else
						{
							throw new \RuntimeException(sprintf('Limit + offset query not implemented for DB2'));
						}
						break;
					default:
						throw new \RuntimeException(sprintf('Limit / Ofsset support not implemented for driver "%s"', $this->conn->getDriverName()));
				}
			}
			
			$this->stmt = $this->conn->getPDO()->prepare($sql);
			$this->stmt->setFetchMode(\PDO::FETCH_ASSOC);
		}
		else
		{
			$this->closeCursor();
		}
		
		$this->bindEncodedParams();
		
		$start = microtime(true);
		$this->stmt->execute();
		$time = microtime(true) - $start;
		
		return (int)$this->stmt->rowCount();
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function closeCursor()
	{
		while($this->stmt !== NULL)
		{
			$this->stmt->closeCursor();
			
			try
			{
				if($this->stmt->nextRowset())
				{
					continue;
				}
			}
			catch(\Exception $e) { }
			
			break;
		}
		
		return $this;
	}
}
