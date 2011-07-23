<?php

	return iw::createConfig('Custom', array(

		// Don't F'in touch this stuff

		'class'             => 'inKSpot',
		'preload'           => TRUE,
		
		// This should be configured to a value which represents the outside
		// publically accessible domain name.  If your website is not publically
		// accessible you can use any valid local domain, so long as your
		// lookups are right.
		
		'external_domain'   => NULL,

		// Users are sometimes prompted with notices and they may be directed
		// to e-mail you with questions... where should we send it?

		'information_email' => NULL,
		
		// Users are sometimes prompted with errors and they may be directed
		// to e-mail you with support inquiries or bug fixes... where should
		// we send this one?

		'support_email'     => NULL
	));
