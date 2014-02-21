<?php

/**
 * Used for automated deploy web site code from Bitbucket to Dreamhost test site.
 * 
 * When push branch 'dev' to bitbucket, the post hook will invoke this url:
 * 
 *	http://git:pass@your.test.domain/git-hook.php
 * 
 * then this script will pull the origin/dev down.
 * 
 * Notice
 * 
 * Before start a push you need to something below:
 * 
 * 1. initialize your git repo on Dreamhost, including add remote as a ssh protocal.
 * 2. Add the ssh public key to Bitbucket deploy keys (I'm not sure of this, but I added mine).
 * 3. Checkout the branch you want to deploy So that the pulled code will be present on your test site.
 * 
 * Thanks
 * 
 * The Deploy class is from Brandon's post:
 * 
 * [Using Bitbucket for Automated Deployments](http://brandonsummers.name/blog/2012/02/10/using-bitbucket-for-automated-deployments/)
 */

$domain   = 'your.test.domain';
$user     = 'git';
$pass     = 'pass';
$branch   = 'dev';
$log_path = "../logs/$domain/deployments.log";



class Deploy {

	/**
	* A callback function to call after the deploy has finished.
	* 
	* @var callback
	*/
	public $post_deploy;
	
	/**
	* The name of the file that will be used for logging deployments. Set to 
	* FALSE to disable logging.
	* 
	* @var string
	*/
	private $_log = 'deployments.log';

	/**
	* The timestamp format used for logging.
	* 
	* @link    http://www.php.net/manual/en/function.date.php
	* @var     string
	*/
	private $_date_format = 'Y-m-d H:i:sP';

	/**
	* The name of the branch to pull from.
	* 
	* @var string
	*/
	private $_branch = 'master';

	/**
	* The name of the remote to pull from.
	* 
	* @var string
	*/
	private $_remote = 'origin';

	/**
	* The directory where your website and git repository are located, can be 
	* a relative or absolute path
	* 
	* @var string
	*/
	private $_directory;

	/**
	* Sets up defaults.
	* 
	* @param  string  $directory  Directory where your website is located
	* @param  array   $data       Information about the deployment
	*/
	public function __construct($directory, $options = array())
	{
		// Determine the directory path
		$this->_directory = realpath($directory).DIRECTORY_SEPARATOR;

		$available_options = array('log', 'date_format', 'branch', 'remote');

		foreach ($options as $option => $value)
		{
			if (in_array($option, $available_options))
			{
				$this->{'_'.$option} = $value;
			}
		}

		$this->log('Attempting deployment...');
	}

	/**
	* Writes a message to the log file.
	* 
	* @param  string  $message  The message to write
	* @param  string  $type     The type of log message (e.g. INFO, DEBUG, ERROR, etc.)
	*/
	public function log($message, $type = 'INFO')
	{
		if ($this->_log)
		{
			// Set the name of the log file
			$filename = $this->_log;

			if ( ! file_exists($filename))
			{
				// Create the log file
				file_put_contents($filename, '');

				// Allow anyone to write to log files
				chmod($filename, 0666);
			}

			// Write the message into the log file
			// Format: time --- type: message
			file_put_contents($filename, date($this->_date_format).' --- '.$type.': '.$message.PHP_EOL, FILE_APPEND);
		}
	}

	/**
	* Executes the necessary commands to deploy the website.
	*/
	public function execute()
	{
		try
		{
			// Make sure we're in the right directory
			exec('cd '.$this->_directory, $output);
			$this->log('Changing working directory... '.implode(' ', $output));

			// Discard any changes to tracked files since our last deploy
			exec('git reset --hard HEAD', $output);
			$this->log('Reseting repository... '.implode(' ', $output));

			// Update the local repository
			exec('git pull '.$this->_remote.' '.$this->_branch, $output);
			$this->log('Pulling in changes... '.implode(' ', $output));

			// Secure the .git directory
			exec('chmod -R og-rx .git');
			$this->log('Securing .git directory... ');

			if (is_callable($this->post_deploy))
			{
				call_user_func($this->post_deploy, $this->_data);
			}

			$this->log('Deployment successful.');
		}
		catch (Exception $e)
		{
			$this->log($e, 'ERROR');
		}
	}

}

// This is just an example
$deploy = new Deploy('.', array(
	'branch' => $branch,
	'log'    => $log_path
));

$deploy->post_deploy = function() use ($deploy) {
	// Anything to do when git pull done.
};

$deploy->log($_SERVER['HTTP_AUTHORIZATION']);

list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) =
	explode(':' , base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));

if ($_SERVER['HTTP_HOST'] == $domain &&
	$_SERVER['PHP_AUTH_USER'] == $user &&
	$_SERVER['PHP_AUTH_PW'] == $pass) {
	// log post data;
	$deploy->log($_POST['payload']);
	$data = json_decode($_POST['payload'], true);
	if ($data && $data['commits'] && count($data['commits'])) {
		$commits = $data['commits'];
		$last = array_pop($commits);
		if ($last['branch'] == $branch) {
			$deploy->execute();
		}
	} else {
		$deploy->log('No commits updated on branch: '.$branch);
	}
} else {
	$deploy->log('HTTP Basic Authorization failed.', 'ERROR');
	header('WWW-Authenticate: Basic realm="'.$_SERVER['HTTP_HOST'].'"');
	header('HTTP/1.1 401 Unauthorized');
	exit;
}

?>