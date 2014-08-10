<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$autoload = __DIR__ . '/../vendor/autoload.php';

if(is_file($autoload)) {
	require_once $autoload;
}

spl_autoload_register(function($typeName) {
	if('koolkode\\database\\' === strtolower(substr($typeName, 0, 18))) {
		$sub = substr($typeName, 18) . '.php';
		
		$file = str_replace('\\', '/', __DIR__ . '/../src/' . $sub);
		if(is_file($file)) {
			require_once $file;
		}
		
		$file = str_replace('\\', '/', __DIR__ . '/unit/' . $sub);
		if(is_file($file)) {
			require_once $file;
		}
	}
});

