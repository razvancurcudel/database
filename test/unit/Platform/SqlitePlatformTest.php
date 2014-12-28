<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database\Platform;

use KoolKode\Database\ConnectionInterface;

class SqlitePlatformTest extends BasePlatformTest
{
	protected function createPlatform(ConnectionInterface $conn)
	{
		return new SqlitePlatform($conn);
	}
}
