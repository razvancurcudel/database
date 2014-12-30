<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database\Migration;

class MigrationManager
{
	public function handleCommand()
	{
		$projectDir = realpath(__DIR__ . '/../../../../../');
		$migrationsDir = $projectDir . '/migration';
		
		$args = array_slice($_SERVER['argv'], 1);
		
		if(is_dir($migrationsDir))
		{
			printf("MIGRATIONS:\n");
			
			foreach(glob($migrationsDir . '/*.php') as $file)
			{
				printf("- %s\n", $file);
			}
		}
		
		if(!empty($args) && $args[0] == 'generate')
		{
			$date = new \DateTime('@' . time());
			$date->setTimezone(new \DateTimeZone('UTC'));
			
			$class = 'Version' . $date->format('YmdHis');
			$file = $class . '.php';
			
			$tpl = [
				'###CLASS###' => $class,
				'###DATE###' => $date->format('Y-m-d H:i:s') . ' UTC'
			];
			$code = strtr(file_get_contents(__DIR__ . '/MigrationTemplate.txt'), $tpl);
			
			file_put_contents($migrationsDir . '/' . $file, $code);
		}
	}
}
