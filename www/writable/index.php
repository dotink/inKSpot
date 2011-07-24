<?php

	$include_directory = 'includes';

	// Check for rewrite if it's not set, prep some $_SERVER variables

	if (!isset($_SERVER['REWRITE_ENABLED']) || !$_SERVER['REWRITE_ENABLED'])) {
		if ($_SERVER['REQUEST_URI'] == '/' || empty($_SERVER['REQUEST_URI'])) {
			$_SERVER['PATH_INFO']   = '/';
			$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
		}
	} else {
		$_SERVER['PATH_INFO'] = NULL;
	}

	// Step back until we find our includes directory (should be 1 at most)

	while (!is_dir($include_directory)) {
		$include_directory = '..' . DIRECTORY_SEPARATOR . $include_directory;
	}

	// Change to our includes directory

	define('APPLICATION_ROOT', realpath(dirname($include_directory)));

	chdir($include_directory);

	// Boostrap!

	require 'init.php';
