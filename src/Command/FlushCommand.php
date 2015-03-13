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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class FlushCommand extends AbstractDatabaseCommand
{
	protected function configure()
	{
		$this->setName('flush');
		$this->setDescription('Flush DB, this command will delete all schema objects');
		
		$this->addOption('truncate', 't', InputOption::VALUE_OPTIONAL, 'Truncate all tables instead of removing them');
		
		$this->addArgument('connection', InputArgument::OPTIONAL, 'DB connection to be used to flush DB', 'default');
	}
	
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		if(!$this->setupDatabase($input, $output))
		{
			return;
		}
		
		$questionHelper = $this->getHelper('question');
		
		$name = $input->getArgument('connection');
		$conn = $this->manager->getConnection($name);
		$platform = $conn->getPlatform();
		
		$question = new ConfirmationQuestion(sprintf('Flush DB <info>%s</info>? [n] ', $name), false);
		
		if($questionHelper->ask($input, $output, $question))
		{
			$output->writeln('');
			
			if($input->getOption('truncate'))
			{
				$platform->flushData();
				
				$output->writeln(sprintf('DB <info>%s</info> data flushed', $name));
			}
			else
			{
				$platform->flushDatabase();
				
				$output->writeln(sprintf('DB <info>%s</info> flushed', $name));
			}
		}
	}
}
