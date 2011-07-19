<?php

	/**
	 * The User is an active record and model representing a single
	 * User record.
	 *
	 * @author Matthew J. Sahagian [mjs] <gent@dotink.org>
	 * @copyright Copyright (c) 2011, Matthew J. Sahagian
	 */
	class User extends ActiveRecord
	{
	
		/**
		 * @var boolean
		 * @static
		 * @access private
		 *
		 * Whether or not we are building an account
		 */
		static private $building = FALSE;

		/**
		 * Initializes all static class information for the User model
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

			fORM::registerHookCallback(
				__CLASS__,
				'pre::validate()',
				iw::makeTarget(__CLASS__, 'buildAccount')
			);
		}

		/**
		 * Gets the record name for the User class
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
		 * Gets the record table name for the User class
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
		 * Gets the record set name for the User class
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
		 * Gets the entry name for the User class
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
		 * Gets the order for the User class
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
		 * Creates a new User from a slug and identifier.  The
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
		 * Creates a new User from a provided resource key.
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
		 * Logic for when a new user is added to a system.  This method is run
		 * prior to validation and after commiting the user to the database in
		 * order to establish initial group membership and home directory
		 * structure.
		 *
		 * @static
		 * @access public
		 */
		static public function buildAccount($user, &$values)
		{
			if (!$user->exists() && !self::$building) {

				self::$building = TRUE;

				// Operations performed prior to user account creation

				try {
				
					$username  = $values['username'];
					$home      = fDirectory::create('/home/users/' . $username);
					$www       = fDirectory::create($home          . 'www');
					$localwww  = fDirectory::create($www           . 'local');
					$userwww   = fDirectory::create($localwww      . $username);
					$docroot   = fDirectory::create($userwww       . 'docroot');
					$mail      = fDirectory::create($home          . 'mail');

					$group = new Group();
					$group->setGroupname($values['username']);
					$group->store();
	
					$values['group_id'] = $group->getId();
					$values['home']     = $home->getPath();

					fORM::registerHookCallback(
						__CLASS__,
						'post-commit::store()',
						iw::makeTarget(__CLASS__, 'buildAccount')
					);

				} catch (fException $e) {

					throw new fEnvironmentException (
						'Unable to build requirements for user account.'
					);

					self::$building = TRUE;
				}

			} else {

				// Operations performed after user account creation

				$shadow   = new UserShadow();
				$home     = $values['home'];
				$username = $values['username'];

				$shadow->setUsername($username);
				$shadow->store();

				exec('chown -R ' . $username . ' ' . $home, $o, $r);
				exec('chgrp -R ' . $username . ' ' . $home, $o, $r);
				exec('chmod g+s ' . $home . 'www/local/' . $username, $o, $r);

				self::$building = FALSE;
			}
		}
		
		/**
		 *
		 */


	}
