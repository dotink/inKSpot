<?php

	class inKSpot implements inkwell {
	
		const EXTERNAL_DOMAIN = 'inkspot';
		const WEB_CONFIGS_DIR = '/home/inkspot/nginx';
		
		/**
		 * @static
		 * @access private
		 * @var string
		 * 
		 * The external domain name for inKSpot
		 */
		static private $external_domain = NULL; 
		
		/**
		 * Configures inKSpot
		 *
		 * @static
		 * @access public
		 * @param array $config The configuration array
		 * @param string $element The name of the configuration element
		 * @return boolean TRUE if configuration was successful, FALSE otherwise
		 */
		static public function __init(Array $config, $element)
		{
			self::$external_domain = isset($config['external_domain'])
				? $config['external_domain']
				: self::EXTERNAL_DOMAIN;
			
			return TRUE;
		}
		
		/**
		 * Gets the external domain name for inkSpot
		 *
		 * @static
		 * @access public
		 * @param void
		 * @return string The current external domain for inKSpot
		 */
		static public function getExternalDomain()
		{
			return self::$external_domain;
		}
		
		/**
		 *
		 */
		static public function writeWebConfig($config, $location)
		{
			return fFile::create($config, implode(DIRECTORY_SEPARATOR, array(
				self::WEB_CONFIGS_DIR,
				$location
			)));
		}
	}
