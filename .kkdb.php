<?php

return [
	'ConnectionManager' => [
		'adapter' => [
			'default' => [
				'dsn' => 'sqlite::memory:',
				'username' => NULL,
				'password' => NULL
			]
		],
		'connection' => [
			'default' => [
				'adapter' => 'default',
				'prefix' => ''
			]
		]
	],
	'Migration' => [
		'MigrationManager' => [
			'directories' => [
				__DIR__ . '/test/src/Migration'
			]
		]
	]
];
