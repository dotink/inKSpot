<?php

	return iw::createConfig('ActiveRecord', array(
		'table'         => 'auth.user_email_addresses',
		'email_columns' => array('email_address')
	));
