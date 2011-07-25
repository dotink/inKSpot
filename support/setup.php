<?php

	$message = escapeshellarg(implode("\n", array(
		'# Welcome to inKSpot',
		'',
		'In order to configure your system we need to ask you a few questions.',
		'These should be pretty straight-forward.',
		'',
		'* Note that the username must be unique on the actual system.',
		"\n"
	)));


	$errors     = array();
	$domain     = NULL;
	$username   = NULL;
	$email_addr = NULL;
	$password   = NULL;

	do {

		$fields = array(
			'domain'     => '"Domain Name:"      2  2 %VAL% 2  20 30 255 ',
			'username'   => '"Admin Username:"   4  2 %VAL% 4  20 30 32  ',
			'email_addr' => '"Admin E-mail:"     6  2 %VAL% 6  20 30 128 ',
			'password'   => '"Admin Password:"   8  2 %VAL% 8  20 30 40  '	
		);

		foreach ($fields as $var => $value) {
			$fields[$var] = str_replace('%VAL%', escapeshellarg($$var), $value);
		}
		
		$errors = escapeshellarg(implode("\n", $errors));

		passthru(
			'dialog --stdout --form ' . $message . $errors . ' 100 100 100 ' .
			implode(' ', $fields) .
			'>> data',
			$cancelled
		);
		
		$errors = array();

		if ($cancelled) {
			echo "Setup is not complete...";
			exit(1);
		}

		$fp = fopen('data', 'r');
		foreach ($fields as $var => $value) {
			$$var = ($var == 'password')
				? trim(fgets($fp), "\n")
				: trim(fgets($fp));

			if (empty($$var)) {
				$errors[] = '** All fields are required.';
			}
		}
		fclose($fp);
		unlink('data');
		
		if (count($errors)) {
			continue;
		}

		try {

			$user   = new User();
			$shadow = new UserShadow();	
			$email  = new UserEmailAddress();

			$user->setUsername($username);
			$user->setHome('/home/users/' . $username);
			$user->setShell('/bin/bash');
			$user->store();
	
			$shadow->setUserId($user->getId());
			$shadow->setLoginPassword($password);
			$shadow->store();
	
			$email->setUserId($user->getId());
			$email->setEmailAddress($email_addr);
			$email->store();
		} catch (fException $e) {
			$errors[] = strip_tags($e->getMessage());
		}

	} while (count($errors));
	
	
