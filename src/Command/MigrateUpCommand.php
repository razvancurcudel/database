<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database\Command;

use KoolKode\Database\ConnectionManagerInterface;
use KoolKode\Database\Migration\MigrationManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateUpCommand extends AbstractDatabaseCommand
{
	protected function configure()
	{
		$this->setName('migration:up');
		$this->setDescription('Apply UP migration(s) to a database');
		
		$this->addArgument('connection', InputArgument::OPTIONAL, 'DB connection to be migrated up', 'default');
	}
	
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		if(!$this->setupDatabase($input, $output))
		{
			return;
		}
		
		$name = $input->getArgument('connection');
		$conn = $this->manager->getConnection($name);
		
		$output->writeln('');
		$output->writeln(sprintf('Migrate UP: <info>%s</info>', $name));
		
		$migration = new MigrationManager($conn);
		
		$config = $this->createMigrationConfig();
		$count = 0;
		
		foreach($config->getMigrations() as $version => $file)
		{
			if($migration->executeMigrationUp($migration->instantiateMigration($file)))
			{
				$output->writeln('');
				$output->writeln(sprintf('<info>%s</info>', $version));
				$output->writeln('  ' . $file);

				$count++;
			}
		}
		
		$output->writeln('');
		$output->writeln(sprintf('<info>%s</info> DB migrations applied', $count));
	}
}
