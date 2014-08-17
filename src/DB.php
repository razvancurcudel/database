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

/**
 * Utility class for database access and connection management.
 * 
 * @author Martin Schröder
 */
abstract class DB
{
	const OBJECT_NAME_PREFIX = '#__';
	
	const DRIVER_SQLITE = 'sqlite';
	
	const DRIVER_MYSQL = 'mysql';
	
	const DRIVER_POSTGRESQL = 'pgsql';
	
	const DRIVER_DB2 = 'db2';
	
	const DRIVER_MSSQL = 'mssql';
	
	const DRIVER_ORACLE = 'oci';
	
	const DRIVER_CUBRID = 'cubrid';
	
	const FETCH_BOTH = 0;
	
	const FETCH_ASSOC = 1;
	
	const FETCH_NUM = 2;
}
