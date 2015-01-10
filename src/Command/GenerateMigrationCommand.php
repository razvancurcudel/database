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

use KoolKode\Util\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Generates a migration class in the configured directory.
 * 
 * @author Martin Schröder
 */
class GenerateMigrationCommand extends Command
{
	protected $directory;
	
	public function __construct($directory)
	{
		parent::__construct();
		
		$this->directory = str_replace('\\/', DIRECTORY_SEPARATOR, $directory);
	}
	
	protected function configure()
	{
		$this->setName('migration:generate');
		$this->setDescription('Generates a new DB migration class template.');
	}
	
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$questionHelper = $this->getHelper('question');
		
		if(!is_dir($this->directory))
		{
			$question = new ConfirmationQuestion(sprintf('Migration directory <info>%s</info> does not exist, dou you want to create it?', $this->directory), false);
			
			if(!$questionHelper->ask($input, $output, $question))
			{
				return;
			}
			
			Filesystem::createDirectory($this->directory);
		}
		
		if(!is_writable($this->directory))
		{
			$output->writeln(sprintf('<error>Migration directory %s is not writable</error>', $this->directory));
				
			return;
		}
		
		$date = new \DateTime('@' . time());
		$date->setTimezone(new \DateTimeZone('UTC'));
		
		$class = 'Version' . $date->format('YmdHis');
		$file = $class . '.php';
		
		$tpl = [
			'###CLASS###' => $class,
			'###DATE###' => $date->format('Y-m-d H:i:s') . ' UTC'
		];
		$code = strtr(file_get_contents(__DIR__ . '/MigrationTemplate.txt'), $tpl);
		$target = str_replace('\\/', DIRECTORY_SEPARATOR, $this->directory . '/' . $file);
		
		$question = new ConfirmationQuestion(sprintf('Generate migration <info>%s</info>?', $target), true);
		
		if(!$questionHelper->ask($input, $output, $question))
		{
			return;
		}
		
		if(false === file_put_contents($target, $code))
		{
			$output->writeln(sprintf('<error>Failed to write migration %s to disk.</error>', $target));
			
			return;
		}
		
		$output->writeln(sprintf('  Migration <info>%s</info> generated.', $target));
	}
}
