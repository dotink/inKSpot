<?php

	class inKSpot implements inkwell
	{
	
		const WEB_CONFIGS_DIR = '/home/inkspot/nginx';
				
		/**
		 * @static
		 * @access private
		 * @var string
		 *
		 * The e-mail which handles information requests
		 */
		static private $information_email = NULL;
		
		/**
		 * @static
		 * @access private
		 * @var string
		 *
		 * The e-mail which handles support inquiries
		 */
		static private $support_email = NULL;
		
		/**
		 * Configures inKSpot
		 *
		 * @static
		 * @access public
		 * @param array $config The configuration array
		 * @param string $element The name of the configuration element
		 * @return boolean TRUE if configuration was successful, FALSE otherwise
		 */
		static public function __init(array $config = array(), $element)
		{			
			self::$information_email = isset($config['information_email'])
				? $config['information_email']
				: NULL;

			self::$support_email = isset($config['support_email'])
				? $config['support_email']
				: NULL;
			
			return TRUE;
		}
		
		/**
		 * Gets the domain name for inkSpot
		 *
		 * @static
		 * @access public
		 * @param void
		 * @return string The current domain for inKSpot
		 */
		static public function getDomain()
		{
			return parse_url(fURL::getDomain(), PHP_URL_HOST);
		}

		/**
		 * Gets the information e-mail for the system.
		 *
		 * @static
		 * @access public
		 * @param boolean $as_html_link Return anchor mailing anchor instead
		 * @return string The current information e-mail address
		 */
		static public function getInformationEmail($as_html_link = FALSE)
		{
			$email = self::$information_email;

			return ($as_html_link)
				? sprintf('<a href="mailto:%s">%s</a>', $email, $email)
				: $email;
		}

		/**
		 * Gets the support e-mail for the system.
		 *
		 * @static
		 * @access public
		 * @param boolean $as_html_link Return anchor mailing anchor instead
		 * @return string The current support e-mail address
		 */
		static public function getSupportEmail($as_html_link = FALSE)
		{
			$email = self::$support_email;
			
			return ($as_html_link)
				? sprintf('<a href="mailto:%s">%s</a>', $email, $email)
				: $email;
		}
		
		/**
		 * Writes a web configuration to the web configurations directory
		 * with a given name and restarts the web server to re-read
		 * the configurations
		 *
		 * @static
		 * @access public
		 * @param string $config The text of the configuration
		 * @param string $name The name of the configuration
		 * @return fFile The configuration for re-use
		 * @throws fEnvironmentException If the file cannot be written
		 */
		static public function writeWebConfig($config, $name)
		{
			if (self::deleteConfig($name)) {
				try {
					$config = fFile::create(implode(DIRECTORY_SEPARATOR, array(
						self::WEB_CONFIGS_DIR,
						$name
					)), $config);

					self::restartWebServer();
					return $config;

				} catch (fValidationException $e) {}

			}

			throw new fEnvironmentException (
				'The configuration file could not be written'
			);
		}
		
		/**
		 * Deletes a web configuration from the web configurations directory
		 *
		 * @static
		 * @access public
		 * @param string $name The name of the configuration
		 * @return boolean TRUE if file was/is deleted, FALSE otherwise
		 */
		static public function deleteConfig($name)
		{
			try {
				$config = new fFile(implode(DIRECTORY_SEPARATOR, array(
					self::WEB_CONFIGS_DIR,
					$name
				)));
				
				if (!$config->isWritable()) {
					return FALSE;
				} else {
					$config->delete();
				}
			} catch (fValidationException $e) {}
			
			return TRUE;
		}
		
		/**
		 * Attempts to restart the web server
		 *
		 * @static
		 * @access public
		 * @param boolean $graceful Whether or not to shut down gracefully
		 * @return integer The new pid of the web server
		 * @throws fEnvironmentException If the server cannot be restarted
		 */
		static public function restartWebServer($graceful = TRUE)
		{
			sexec('nginx -t', $output, $failure);

			if ($failure) {
				throw new fEnvironmentException (
					'Cannot restart web server, configuration file error'
				);		
			} elseif ($graceful) {
				sexec('nginx -s reload', $output, $failure);
				sleep(5);
				if ($failure) {
					self::restartWebServer(!$graceful);
				}
			} else {
				$pid = intval(trim(file_get_contents('/var/run/nginx.pid')));
				sexec('nginx -s stop', $output, $failure);
				sleep(5);
				if (`ps -A | grep nginx | grep $pid`) {
					sexec('kill -9 ' . $pid, $output, $failure);
					if ($failure) {
						throw new fEnvironmentException (
							'There was a problem shutting down the web server'
						);
					}
				}
				sexec('nginx');
			}
			
			return trim(`cat /var/run/nginx.pid`);
		}
	}
