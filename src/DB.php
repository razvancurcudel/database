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
	const SCHEMA_OBJECT_PREFIX = '#__';
	
	const DRIVER_SQLITE = 'sqlite';
	
	const DRIVER_MYSQL = 'mysql';
	
	const DRIVER_POSTGRESQL = 'pgsql';
	
	const DRIVER_DB2 = 'db2';
	
	const DRIVER_MSSQL = 'mssql';
	
	const DRIVER_ORACLE = 'oci';
	
	const DRIVER_CUBRID = 'cubrid';
	
	/**
	 * String option indicating DB encoding / character set to be used.
	 * 
	 * Connection will attempt to use UTF-8 by default.
	 * 
	 * @var string
	 */
	const OPTION_ENCODING = 'encoding';
	
	/**
	 * String option indicating a timezone name (or offset) for datetime types that do not store timezone info.
	 * 
	 * @var string
	 */
	const OPTION_TIMEZONE = 'timezone';
	
	/**
	 * Array options that declares pragma directives to be set when connecting to a Sqlite DB.
	 * 
	 * @var string
	 */
	const OPTION_SQLITE_PRAGMA = 'sqlite_pragma';
	
	/**
	 * Boolean option that indicates LIMIT / OFFSET support in IBM DB2 9.7+.
	 * 
	 * This requires the database to enable <code>DB2_COMPATIBILITY_VECTOR=4000</code> (every single bit can
	 * be OR-ed together during creation of the vector).
	 * 
	 * @link https://www.ibm.com/developerworks/community/blogs/SQLTips4DB2LUW/entry/limit_offset?lang=en
	 * 
	 * @var string
	 */
	const OPTION_DB2_LIMIT_OFFSET = 'db2_limit_offset';
	
	const FETCH_BOTH = 0;
	
	const FETCH_ASSOC = 1;
	
	const FETCH_NUM = 2;
}
