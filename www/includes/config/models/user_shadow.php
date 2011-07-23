<?php

	return iw::createConfig('ActiveRecord', array(
		'table'            => 'auth.user_shadows',
		'password_columns' => array('login_password')
	));
