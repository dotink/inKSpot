<?php

	/**
	 * The UserWebEngine is an active record and model representing a single
	 * User Web Engine record.
	 *
	 * @author Matthew J. Sahagian [mjs] <gent@dotink.org>
	 * @copyright Copyright (c) 2011, Matthew J. Sahagian
	 */
	class UserWebEngine extends ActiveRecord
	{

		const CGI_ROOT = 'users';

		/**
		 * Initializes all static class information for the UserWebEngine model
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
		 * Gets the record name for the UserWebEngine class
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
		 * Gets the record table name for the UserWebEngine class
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
		 * Gets the record set name for the UserWebEngine class
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
		 * Gets the entry name for the UserWebEngine class
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
		 * Gets the order for the UserWebEngine class
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
		 * Creates a new UserWebEngine from a slug and identifier.  The
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
		 * Creates a new UserWebEngine from a provided resource key.
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
		 * Attempts to start a user web engine.  If the web engine is already
		 * started for the provided user, the function fails gracefully, if
		 * not it will be started and the PID updated.
		 *
		 * @static
		 * @access public
		 * @param User $user The user to start the engine for
		 * @param Engine $engine The engine to start
		 * @return UserWebEngine The object for easy PID retrieval
		 */
		static public function start(User $user, Engine $engine)
		{	
			try {
				$self = new self(array(
					'user_id'   => $user->getId(),
					'engine_id' => $engine->getId()
				));
				
				if (!$self->getPid()) {
					throw new fExpectedException(
						'The engine record exists, but is not running'
					);
				}

			} catch (fExpectedException $e) {
				$self = new self();
				$self->setUserId($user->getId());
				$self->setEngineId($engine->getId());
				
				$pid = WebEngine::start(
					$engine,
					$user,
					$user->createGroup(),
					self::CGI_ROOT . DIRECTORY_SEPARATOR . $user->getUsername()
				);
				
				$self->setPid($pid);
				$self->store();	
			}
			
			return $self;
		}
		
		/**
		 * Stops a user web engine.  If the web engine is already stopped for
		 * the provided user the function exits gracefully if not it will be
		 * stopped and the record removed.
		 *
		 * @static
		 * @access public
		 * @param User $user The user to start the engine for
		 * @param Engine $engine The engine to start
		 * @return boolean TRUE if the engine was/is stopped, FALSE otherwise
		 */
		static public function stop(User $user, Engine $engine)
		{
			try {
				$self = new self(array(
					'user_id'   => $user->getId(),
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
