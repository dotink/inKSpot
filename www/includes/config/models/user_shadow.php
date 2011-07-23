<?php

	return iw::createConfig('ActiveRecord', array(
		'table'            => 'auth.user_shadows',
		'password_columns' => array('login_password'),

		// Defines the maximum number of login attempts to afford in
		// a certain time frame.  This can be, default is 5 every 15
		// minutes.

		'max_login_attempts' => '5/15 minutes',

		// Allows users to log in via any of their registered e-mail addresses
		// instead of their username

		'allow_email_login'  => TRUE

	));
