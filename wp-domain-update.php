<?php
/**
 * Copyright (c) 2012 Cast Iron Coding <www.castironcoding.com>
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy,
 * modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * UI to support operation of the domain update
 */
class CicWpDomainUpdate {
	var $dbHost;
	var $dbPassword;
	var $dbUser;
	var $dbName;
	var $oldDomain;
	var $newDomain;
	var $oldDomainCurrentSite;
	var $configFile;

	var $shouldBackUp;
	var $backupLocation;

	const BACKUP_FOLDER_NAME = '_wp_backup';

	var $db; // The db connection

	/**
 	 * Constructor
	 */
	public function __construct() {
		// Try to set some path variables based on the user's operating environment
		putenv("PATH=" .$GLOBALS['_SERVER']['PATH']. ':/usr/local/bin:/usr/bin');
	}

	/**
 	 * Get some info from the user and execute the database and config file changes
	 */
	public function run() {
		$this->messageUser('WORDPRESS FIND/REPLACE');
		$this->messageUser('	-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_');
		$this->messageUser('	| I am intended to replace all instances of one domain in a Wordpress installation with another domain name.');
		$this->messageUser('	| BE CAREFUL with me, as I perform a rather unintelligent find/replace.  If you have any instance of your');
		$this->messageUser('	| existing domain name in content or otherwise that you wish to keep, I might not be the script for you...');
		$this->messageUser('	| Also, you guys: IT\'S NOT RECOMMENDED to run me on a production Wordpress installation. Have fun, though!');
		$this->messageUser('	-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_'."\n");

		$this->messageUser('	I am going to update every field in every table that I can within the Wordpress database from');
		$this->messageUser('	the config file specified, and also modify the definition of "DOMAIN_CURRENT_SITE" in the wp-config.php');
		$this->messageUser('	file specified, if it is found there.');

		// Get the config
		$this->getWpConfig();

		// Connect to the database
		$this->connectDb();

		// Get the domains
		$this->getOldDomain();
		$this->getNewDomain();

		$this->getShouldBackUp();
		if($this->shouldBackUp) {
			$this->backupLocation = $this->getBackupLocation();
			$this->messageUser('Ok. Attempting to back up to a folder here in your working directory: '.$this->backupLocation . '/');
			$this->backUp();
			$this->messageUser('Ok. Backed up your database and config file.');
		} else {
			$this->messageUser('*************************************');
			$this->messageUser('	Mmmmkay. THERE WILL BE NO BACK UP OF THE DATABASE. IF YOU HAVE NOT BACKED UP YOUR DATABASE');
			$this->messageUser('	AND wp-config.php FILE YET, YOU SHOULD QUIT THIS SCRIPT (CTRL-c) AND BACK THOSE UP FIRST, YOU GUYS!!!!');
			$this->messageUser('*************************************');
		}

		// Perform the database and file updates
		$this->messageUser('Ok. I\'m about to rather blindly replace all instances of "'.$this->oldDomain.'" with "'.$this->newDomain.'"');
		$this->performUpdate();
	}

	/**
	 * Make the changes
	 */
	protected function performUpdate() {
		// First, confirm:
		$this->messageUser('Are you certain you wish to proceed (Y/n)?: ', false);
		$confirm = trim(fgets(STDIN));
		if($this->convertToBool($confirm) || $confirm === '') {
			$this->messageUser('Alrighty.  Proceeding with the domain update...');

			$this->updateDb();
			$this->updateConfig();

		} else {
			$this->messageUser('Ok. Never mind, then - I\'m stopping here - no harm done.');
			exit();
		}
	}

	/**
 	 * Update the wp-config.php file
	 */
	protected function updateConfig() {
		$contents = file_get_contents($this->configFile);
		if($newContents = preg_replace('/'.str_replace('.','\.',$this->oldDomain).'/', $this->newDomain, $contents)) {
			$h = fopen($this->configFile, 'w');
			fwrite($h, $newContents);
			fclose($h);
		}
	}

	/**
	 * Gets an array of tables from the database
	 * @return array
	 */
	private function getTables() {
		$q = 'SHOW TABLES FROM '.$this->dbName;
		$qres = mysql_query($q);
		$tables = array();
		while($row = mysql_fetch_array($qres)) {
			$tables[] = $row[0];
		}
		return $tables;
	}

	/**
	 * Get an array of fields for a particular table
	 * @param $table
	 * @return array
	 */
	private function getFields($table) {
		$q = 'SHOW FIELDS FROM '.$table;
		$qres = mysql_query($q);
		$fields = array();
		while($row = mysql_fetch_assoc($qres)) {
			if(preg_match('/char|text|blob/i',$row['Type'])) {
				$fields[] = $row;
			}
		}
		return $fields;
	}

	protected function updateDb() {
		// get all tables
		$tables = $this->getTables();

		$i = 0;
		foreach($tables as $table) {
			$fields = $this->getFields($table);
			$affectedRows = 0;
			if(count($fields) > 0) {
				$this->messageUser('Updating table "' . $table . '" ',false);

				foreach($fields as $field) {
					$fieldName = $field['Field'];
					if($this->oldDomain && $this->newDomain) {
						// TODO: add number of affected rows
						mysql_query('UPDATE '.$table.' SET '.$fieldName.' = REPLACE('.$fieldName.',\''.$this->oldDomain.'\',\''.$this->newDomain.'\')');
						$affectedRows += mysql_affected_rows();
						$i++;
					}
				}
			}
			$this->messageUser('(' . mysql_affected_rows() . ' rows affected)');
		}
		$this->messageUser(
			'Executed '.$i.' queries. '.
				($i > 1000
					? 'Wow, that\'s a lot!'
					: 'Heck, that\'s nothing!'
				)
		);
	}

	/**
 	 * Connect to the database
	 */
	protected function connectDb() {
		try {
			$this->db = mysql_connect($this->dbHost,$this->dbUser,$this->dbPassword);
			mysql_select_db($this->dbName,$this->db);
		} catch(Exception $e) {
			$this->fatalError('Could not connect to the database.  Please check your wp-config file');
		}

	}

	/**
	 * Backs up the database and wp-config.php file to $this->backupLocation
	 */
	protected function backUp() {
		exec('mkdir -p ' . $this->backupLocation); // Create the folder to hold the backups
		if (is_dir($this->backupLocation)) {
			// Use mysqldump to back up the database
			$dbBackupFile = $this->backupLocation.'/'.$this->dbName . '.sql';
			exec('mysqldump -u' . $this->dbUser . ' -h' . $this->dbHost . ' -p' . $this->dbPassword . ' ' . $this->dbName . ' > '.$dbBackupFile.' 2>&1');
			if (!file_exists($dbBackupFile)) {
				$this->fatalError('ERROR: could not back up database to ' . $dbBackupFile . ' for some reason. Exiting!');
			}

			// Copy wp-config.php to backup location
			exec('cp ' . $this->configFile . ' ' . $this->backupLocation . '/');
		} else {
			$this->fatalError('Could not create backup folder at ' . $this->backupLocation);
		}
	}

	/**
	 * @return string
	 */
	protected function getBackupLocation() {
		return getcwd(). '/' . self::BACKUP_FOLDER_NAME . '/' . strftime('%Y-%m-%d-%s');
	}

	/**
 	 *
	 */
	protected function getShouldBackUp() {
		$this->shouldBackUp = true;
		$this->messageUser('Should I attempt to back up your database and wp-config.php file first (Y/n):',false);
		$input = trim(fgets(STDIN));
		if($input) $this->shouldBackUp = $this->convertToBool($input);
	}

	/**
	 * @param $input
	 * @return bool
	 */
	protected function convertToBool($input) {
		$out = false;
		$input = trim(strtolower($input));
		switch($input) {
			case 'yes':
			case 'y':
				$out = true;
			break;
		}
		return $out;
	}

	/**
 	 * Get the new domain name from the user
	 */
	protected function getNewDomain() {
		$domain = '';
		while(!$domain) {
			$this->messageUser('What is the new domain?:',false);
			$domain = trim(fgets(STDIN));
		}
		$this->newDomain = $domain;
	}

	/**
	 * Get the filename of the first found file called 'wp-config.php' in or below the current working directory
	 *
	 * @return mixed
	 */
	protected function findConfigFileCandidate() {
		$out = false;
		$it = new RecursiveDirectoryIterator(getcwd());
		foreach(new RecursiveIteratorIterator($it) as $file) {
			$fileName = $file->getFileName();
			$path = $file->getPathname();
			if (preg_match('/wp-config\.php/', $fileName) && !preg_match('/'.self::BACKUP_FOLDER_NAME.'/',$path)) {
				$out = $path;
				break; // Break on the first one
			}
		}
		return $out;
	}

	/**
 	 * Get the old domain name from the user
	 */
	protected function getOldDomain() {
		$domain = '';
		while(!$domain) {
			$this->messageUser('Enter the domain name that you want to change ("'.$this->oldDomainCurrentSite.'"):',false);
			$domain = trim(fgets(STDIN));
			if (!$domain && $this->oldDomainCurrentSite) {
				$domain = $this->oldDomainCurrentSite; // Use the DOMAIN_CURRENT_SITE setting from wp-config.php
			}
		}
		$this->oldDomain = $domain;
	}

	/**
	 * @param $msg
	 * @param $newline
	 */
	protected function messageUser($msg, $newline = true) {
		echo $msg . ($newline ? "\n" : '');
	}

	/**
 	 * Get the location of the wp-config.php file as user input and includes it in the global scope.
	 */
	protected function getWpConfig() {
		$configPath = '';
		$configFileCandidate = $this->findConfigFileCandidate();
		if(file_exists($configFileCandidate)) {
			while(!file_exists($configPath)) {
				$this->messageUser('Please enter the path to your wp-config file ('.$configFileCandidate.'): ',false);
				$configPath = trim(fgets(STDIN));
				if (!$configPath) {
					$configPath = $configFileCandidate;
				}
			}
		} else {
			while(!file_exists($configPath)) {
				$this->messageUser('Please enter the path to your wp-config file: ',false);
				$configPath = trim(fgets(STDIN));
			}
		}
		$contents = preg_replace('/require_once\([^\)]*\);/', '', file_get_contents($configPath));
		$raw = preg_replace('/<\?(php)?/', '', $contents);
		eval($raw);
		$this->dbHost = DB_HOST;
		$this->dbUser = DB_USER;
		$this->dbName = DB_NAME;
		$this->dbPassword = DB_PASSWORD;
		if(
			$this->dbHost &&
			$this->dbUser &&
			$this->dbName &&
			$this->dbPassword
		) {
			$this->oldDomainCurrentSite = DOMAIN_CURRENT_SITE;
			$this->configFile = $configPath;
		} else {
			$this->fatalError('Could not determine DB credentials from config file provided');
		}
		return true;
	}


	/**
	 * @param $msg
	 */
	protected function fatalError($msg) {
		$this->messageUser($msg);
		exit();
	}
}
$interface = new CicWpDomainUpdate();
$interface->run();

?>