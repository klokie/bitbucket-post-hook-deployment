bitbucket-post-hook-deployment
==============================

Used for automated deployment from Bitbucket to development server.

When code is pushed to Bitbucket, the post hook will invoke this url:

	http://git:pass@yourdomain.com/git-hook.php

then this script will pull down the appropriate branch and merge locally.

## Usage

1. Copy the both files to your web root.
2. Add post hook URL into your Bitbucket repo admin panel.
3. Initialize your git repo, including `git add remote` using SSH protocol.
4. Add the SSH public key to Bitbucket deploy keys.
5. Check out the branch you want to deploy so that the pulled code will be deployed to your site.

## Configuration

If you place an optional [INI format](http://en.wikipedia.org/wiki/Ini_file) configuration file named `config.ini` in the root of this directory, it may be used to set the configuration parameters to match your particular deployment case. In this way, you can use this one script to manage multiple deployments.

For example, your `config.ini` file could look like this:

	domain =        example.com
	user =          user1
	pass =          foo
	path =          /var/www/example.com
	branch =       	master
	log =           deployments.log

	[repo2]
	domain =        repo2.example.com
	user =          user2
	pass =          bar
	path =          /var/www/repo2.example.com
	branch =        develop
	log =           repo1.log

In this case, a POST hook from the Bitbucket repository `repo2` would use the second configuration block, resulting in deployment of that repository's `develop` branch to your local `/var/www/repo2.example.com` directory, whereas other POST hooks would use the first (global) configuration block.

Note: if the `branch` parameter is specified, deployments for that repository will be restricted to that branch. Otherwise, commits to any branch that trigger the POST hook may be merged in to the local repository.

## Thanks

This project was based on a [Gist](https://gist.github.com/mytharcher/9138422) by [MyArcher](https://github.com/mytharcher).

The Deploy class comes from the blog post [Using Bitbucket for Automated Deployments](http://brandonsummers.name/blog/2012/02/10/using-bitbucket-for-automated-deployments/).
