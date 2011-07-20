<?php

	return iw::createConfig('Controller', array(
		'routes'      => array(
			'/'       => 'PublicController::home',
			'/signup' => 'PublicController::signup'
		)
	));
