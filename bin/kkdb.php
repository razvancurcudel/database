<?php

use KoolKode\Database\Migration\MigrationManager;

require dirname(dirname(dirname(realpath(__DIR__)))) . '/autoload.php';

MigrationManager::handleCommand();
