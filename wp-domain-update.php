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

		// Prompt for the required params
		$this->dbHost = $this->getWhoopyParam('Type in the database host: ');
		$this->dbUser = $this->getWhoopyParam('Type in the database user: ');
		$this->dbPassword = $this->getWhoopyParam('Type in the databsse password: ');
		$this->dbName = $this->getWhoopyParam('Type in the database name: ');
		$this->oldDomain = $this->getWhoopyParam('Type in the current (old) domain: ');
		$this->newDomain = $this->getWhoopyParam('Type in the new domain: ');

		$confirmDetails = array(
			'Database host: ' => $this->dbHost,
			'Database username: ' => $this->dbUser,
			'Database password: ' => $this->dbPassword,
			'Database name: ' => $this->dbName,
			'Replacing action: '=> $this->newDomain.' (new) --> '.$this->oldDomain.' (old)'
		);
		foreach ($confirmDetails as $type => $value) {
			$this->messageUser($type.$value);
		}
		$this->confirmAction('Is the above correct?');

		// Connect to the database
		$this->connectDb();

		// Perform the database and file updates
		$this->messageUser('Ok. I\'m about to rather blindly replace all instances of "'.$this->oldDomain.'" with "'.$this->newDomain.'"');
		$this->performUpdate();
	}

	/**
	 * Gets user input from stdin
	 * @param $message
	 * @return string
	 */
	private function getWhoopyParam($message) {
		$this->messageUser($message,false);
		$input = trim(fgets(STDIN));
		return $input;
	}

	/**
	 * Prompts user to confirm; Exits script if not confirmed
	 * @param $message
	 */
	private function confirmAction($message='Are you certain you wish to proceed (Y/n)?:') {
		$this->messageUser($message, false);
		$confirm = trim(fgets(STDIN));
		if($this->convertToBool($confirm) || $confirm === '') {
			$this->messageUser('Alrighty.  Proceeding...');
		} else {
			$this->messageUser('Ok. Never mind, then - I\'m stopping here - no harm done.');
			exit();
		}
	}
	/**
	 * Make the changes
	 */
	protected function performUpdate() {
		// First, confirm:
		$this->confirmAction();
		$this->updateDb();
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
	 * @param $msg
	 * @param $newline
	 */
	protected function messageUser($msg, $newline = true) {
		echo $msg . ($newline ? "\n" : '');
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