<?php

	/**
	 * The DomainWebEngine is an active record and model representing a single
	 * Domain Web Engine record.
	 *
	 * @author Matthew J. Sahagian [mjs] <gent@dotink.org>
	 * @copyright Copyright (c) 2011, Matthew J. Sahagian
	 */
	class DomainWebEngine extends ActiveRecord
	{

		const SOCK_ROOT = 'domains';

		/**
		 * Initializes all static class information for the DomainWebEngine model
		 *
		 * @static
		 * @access public
		 * @param array $config The configuration array
		 * @param array $element The element name of the configuration array
		 * @return void
		 */
		static public function __init(array $config = array(), $element = NULL)
		{
			parent::__init($config, $element);
		}

		/**
		 * Gets the record name for the DomainWebEngine class
		 *
		 * @static
		 * @access public
		 * @param void
		 * @return string The custom or default record translation
		 */
		static public function getRecordName()
		{
			return parent::getRecordName(__CLASS__);
		}

		/**
		 * Gets the record table name for the DomainWebEngine class
		 *
		 * @static
		 * @access public
		 * @param void
		 * @return string The custom or default record table translation
		 */
		static public function getRecordTable()
		{
			return parent::getRecordTable(__CLASS__);
		}

		/**
		 * Gets the record set name for the DomainWebEngine class
		 *
		 * @static
		 * @access public
		 * @param void
		 * @return string The custom or default record set translation
		 */
		static public function getRecordSet()
		{
			return parent::getRecordSet(__CLASS__);
		}

		/**
		 * Gets the entry name for the DomainWebEngine class
		 *
		 * @static
		 * @access public
		 * @param void
		 * @return string The custom or default entry translation
		 */
		static public function getEntry()
		{
			return parent::getEntry(__CLASS__);
		}

		/**
		 * Gets the order for the DomainWebEngine class
		 *
		 * @static
		 * @access public
		 * @param void
		 * @return array The default sort array
		 */
		static public function getOrder()
		{
			return parent::getOrder(__CLASS__);
		}

		/**
		 * Determines whether the record class only serves as a relationship,
		 * i.e. a many to many table.
		 *
		 * @static
		 * @access public
		 * @param void
		 * @return boolean TRUE if it is a relationship, FALSE otherwise
		 */
		static public function isRelationship()
		{
			return parent::isRelationship(__CLASS__);
		}

		/**
		 * Creates a new DomainWebEngine from a slug and identifier.  The
		 * identifier is optional, but if is provided acts as an additional
		 * check against the validity of the record.
		 *
		 * @static
		 * @access public
		 * @param $slug A primary key string representation or "slug" string
		 * @param $identifier The identifier as provided as an extension to the slug
		 * @return fActiveRecord The active record matching the slug
		 */
		static public function createFromSlug($slug, $identifier = NULL)
		{
			return parent::createFromSlug(__CLASS__, $slug, $identifier);
		}

		/**
		 * Creates a new DomainWebEngine from a provided resource key.
		 *
		 * @static
		 * @access public
		 * @param string $resource_key A JSON encoded primary key
		 * @return fActiveRecord The active record matching the resource key
		 *
		 */
		static public function createFromResourceKey($resource_key)
		{
			return parent::createFromResourceKey(__CLASS__, $resource_key);
		}
		
		/**
		 * Gets the socket location for a domain web engine
		 *
		 * @static
		 * @access public
		 * @param Domain $domain The domain to get the socket for
		 * @param Engine $engine The engine to get the socket for
		 * @return string The absolute socket path
		 */
		static public function getSocket(Domain $domain, Engine $engine)
		{
			return implode(DIRECTORY_SEPARATOR, array(
				parent::SOCK_ROOT,
				self::SOCK_ROOT,
				$domain->getName() . '.' . $engine->getName()
			));
		}

		/**
		 * Attempts to start a domain web engine.  If the web engine is already
		 * started for the provided domain, the function fails gracefully, if
		 * not it will be started and the PID updated.
		 *
		 * @static
		 * @access public
		 * @param Domain $domain The domain to start the engine for
		 * @param Engine $engine The engine to start
		 * @return DomainWebEngine The object for easy PID retrieval
		 */
		static public function start(Domain $domain, Engine $engine)
		{	
			try {
				$self = new self(array(
					'domain_id' => $domain->getId(),
					'engine_id' => $engine->getId()
				));
				
				if (!$self->getPid()) {
					throw new fExpectedException(
						'The engine record exists, but is not running'
					);
				}

			} catch (fExpectedException $e) {
				$self = new self();
				$self->setDomainId($domain->getId());
				$self->setEngineId($engine->getId());
				
				$pid = WebEngine::start(
					$engine,
					$domain->createUser(),
					$domain->createGroup(),
					self::getSocket($domain, $engine)
				);		
				
				$self->setPid($pid);
				$self->store();		
			}
			
			return $self;
		}

		/**
		 * Stops a domain web engine.  If the web engine is already stopped for
		 * the provided domain the function exits gracefully if not it will be
		 * stopped and the record removed.
		 *
		 * @static
		 * @access public
		 * @param Domain $domain The domain to start the engine for
		 * @param Engine $engine The engine to start
		 * @return boolean TRUE if the engine was/is stopped, FALSE otherwise
		 */
		static public function stop(Domain $domain, Engine $engine)
		{
			try {
				$self = new self(array(
					'domain_id' => $domain->getId(),
					'engine_id' => $engine->getId()
				));
			} catch (fNotFoundException $e) {
				return TRUE;
			}

			if ($self->getPid()) {

				$kill_commands = array(
					'kill '    . $self->getPid(),
					'kill -9 ' . $self->getPid()
				);

				$check_command = 'ps -A | grep ' . $self->getPid();

				foreach ($kill_commands as $command) {
					sexec($command, $output, $failure);
					if ($failure || $check_command) {
						sleep(5);
					} else {
						$self->delete();
						return TRUE;
					}
				}
			}
			
			return FALSE;
		}

	}
