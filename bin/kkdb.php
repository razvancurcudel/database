<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use KoolKode\Database\Migration\MigrationManager;

$parts = explode(DIRECTORY_SEPARATOR, getcwd());
$dir = NULL;

while(!empty($parts))
{
	$cwd = implode(DIRECTORY_SEPARATOR, $parts);
	
	if(is_file($cwd . DIRECTORY_SEPARATOR . 'composer.json'))
	{
		$dir = $cwd;
		break;
	}
	
	array_pop($parts);
}

if($dir === NULL)
{
	echo "No project directory found for working directory \"", getcwd(), "\"\n";
	exit(1);
}

require $dir . '/vendor/autoload.php';

MigrationManager::handleCommand();
