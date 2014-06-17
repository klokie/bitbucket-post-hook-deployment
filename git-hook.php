<?php
/**
 * Used for automated deploy web site code from Bitbucket to development server.
 * 
 * When push branch 'master' to bitbucket, the post hook will invoke this url:
 * 
 *	http://git:pass@yourdomain.com/git-hook.php
 * 
 * then this script will pull the origin/master down.
 * 
 * ## Usage ##
 * 
 * 1. Copy the both files to your Dreamhost site web root.
 * 2. Add post hook URL into your bitbucket repo admin panel.
 * 3. Initialize your git repo on Dreamhost, including add remote as a ssh protocol.
 * 4. Add the ssh public key to Bitbucket deploy keys.
 * 5. Checkout the branch you want to deploy So that the pulled code will be present on your site.
 * 
 * ## Thanks ##
 * 
 * The Deploy class is from Brandon's post:
 * 
 * [Using Bitbucket for Automated Deployments](http://brandonsummers.name/blog/2012/02/10/using-bitbucket-for-automated-deployments/)
 *
 * Based on [MyArcher](https://github.com/mytharcher)'s [Gist](https://gist.github.com/mytharcher/9138422).
 */

define('CONFIGFILE', '.' . DIRECTORY_SEPARATOR . 'config.ini');
require_once('.' . DIRECTORY_SEPARATOR . 'Deploy.php');

//define('DEBUG', true);

// default configuration, may be overridden in config file
$config = array();
$config['domain']   = 'your.test.domain';
$config['user']     = 'git';
$config['pass']     = 'pass';
$config['path']     = '.';
$config['branch']   = null;
$config['log'] = "deployments.log";

if (is_file(CONFIGFILE)) {
    $config = array_merge($config, parse_ini_file(CONFIGFILE, true));
}

// if 'branch' is specified in config file, it may not be overridden
if (empty($config['branch'])) {
    if (!empty($_GET['branch'])) {
        $config['branch'] = $_GET['branch'];
    } else {
        $config['branch'] = 'master';
    }
}

try {
    $payload = $_POST['payload'];
    $payload = json_decode($payload, true);

    // override with repo-specific config options
    if (isset($payload['repository']) && isset($config[$payload['repository']['slug']])) {
        $config = array_merge($config, $config[$payload['repository']['slug']]);
    }
} catch( Exception $ErrorHandle ) {
    die(json_encode($ErrorHandle->getMessage()));
}

if (defined('DEBUG')) {
    die(json_encode($config));
}

list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) =
	explode(':' , base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));

if ($_SERVER['HTTP_HOST'] == $config['domain'] &&
	$_SERVER['PHP_AUTH_USER'] == $config['user'] &&
	$_SERVER['PHP_AUTH_PW'] == $config['pass']) {

    $deploy = new Deploy($config['path'], $config);
    
    $deploy->post_deploy = function() use ($deploy) {
        // Anything to do when git pull done.
    };

    if (defined('DEBUG')) {
// this is just for debug, or would leak the auth infomation.
        $deploy->log($_SERVER['HTTP_AUTHORIZATION']);
    }

	// log post data;
	$deploy->log($payload);
    if ($payload) {
        if ($payload['commits'] && count($payload['commits'])) {
            $commits = $payload['commits'];
            $last = array_pop($commits);
            if ($last['branch'] == $config['branch']) {
                // using a flag file to avoid duplicated triggering
                if (!file_exists('.deploying')) {
                    touch('.deploying');
                        $deploy->execute();
                        unlink('.deploying');
                }
            }
        } else {
            $deploy->log('No commits updated on branch: '.$config['branch']);
        }
    }
} else {
	$deploy->log('HTTP Basic Authorization failed.', 'ERROR');
	header('WWW-Authenticate: Basic realm="'.$_SERVER['HTTP_HOST'].'"');
	header('HTTP/1.1 401 Unauthorized');
	exit;
}
