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

use KoolKode\Config\Configuration;
use KoolKode\Database\ConnectionManager;
use KoolKode\Database\ConnectionManagerInterface;
use KoolKode\Database\Migration\MigrationConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use KoolKode\Util\Filesystem;

abstract class AbstractDatabaseCommand extends Command
{
	protected $configFile;
	
	protected $manager;
	
	protected $migrationDirectories = [];
	
	public function __construct($configFile)
	{
		parent::__construct();
	
		$this->configFile = $configFile;
	}
	
	public function setConnectionManager(ConnectionManagerInterface $manager = NULL)
	{
		$this->manager = $manager;
	}
	
	public function setMigrationConfiguration(array $directories)
	{
		$this->migrationDirectories = $directories;
	}
	
	protected function setupDatabase(InputInterface $input, OutputInterface $output)
	{
		if($this->manager === NULL)
		{
			$questionHelper = $this->getHelper('question');
			
			$file = Filesystem::normalizePath($this->configFile);
			
			if(!is_file($file))
			{
				$output->writeln(sprintf('Missing config file: <info>%s</info>', $this->configFile));
				
				$question = new ConfirmationQuestion('Do you want to generate the config file? [n] ', false);
				
				if($questionHelper->ask($input, $output, $question))
				{
					$tpl = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'ConfigTemplate.txt');
					$replacements = [];
					
					$question = new Question('Database DSN (PDO): ', '');
					$dsn = $questionHelper->ask($input, $output, $question);
					
					$question = new Question('DB username: ', '');
					$username = $questionHelper->ask($input, $output, $question);
					
					$question = new Question('DB password: ', '');
					$password = $questionHelper->ask($input, $output, $question);
					
					$replacements['###DSN###'] = var_export((string)$dsn, true);
					$replacements['###USERNAME###'] = var_export((trim($username) === '') ? NULL : $username, true);
					$replacements['###PASSWORD###'] = var_export((trim($password) === '') ? NULL : $password, true);
					
					$code = strtr($tpl, $replacements);
					
					Filesystem::writeFile($file, $code);
					
					$output->writeln(sprintf('Generated <info>%s</file> with this contents:', $file));
					$output->writeln('');
					$output->writeln($code);
				}
				
				return false;
			}
			
			$config = new Configuration($this->processConfigData(require $file));
			
			$this->manager = new ConnectionManager($config->getConfig('ConnectionManager'));
			$this->migrationDirectories = $config->getConfig('Migration.MigrationManager.directories')->toArray();
		}
		
		return true;
	}
	
	protected function processConfigData(array $data)
	{
		$result = array_change_key_case($data, CASE_LOWER);
		
		foreach($result as & $val)
		{
			if(is_array($val))
			{
				$val = $this->processConfigData($val);
			}
		}
		
		return $result;
	}
	
	protected function createMigrationConfig()
	{
		$config = new MigrationConfig();
	
		foreach($this->migrationDirectories as $dir)
		{
			$config->loadMigrationsFromDirectory($dir);
		}
	
		return $config;
	}
}
