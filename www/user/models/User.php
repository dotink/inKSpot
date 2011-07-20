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

			fORM::registerHookCallback(
				__CLASS__,
				'post::store()',
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
		 * @param User $user The User Object
		 * @param array $values The array of current property values
		 * @return void
		 */
		static public function buildAccount(User $user, &$values)
		{
			if (!$user->exists() && !self::$building) {

				$username  = $values['username'];

				if (count(Users::build(array('username=' => $username)))) {
					throw new fValidationException (
						'The username %s already exists',
						$username
					);
				}

				// Operations performed prior to user account creation

				fFilesystem::begin();

				try {
					$home      = fDirectory::create('/home/users/' . $username);
					$www       = fDirectory::create($home          . 'www');
					$localwww  = fDirectory::create($www           . 'local');
					$userwww   = fDirectory::create($localwww      . $username);
					$docroot   = fDirectory::create($userwww       . 'docroot');
					$mail      = fDirectory::create($home          . 'mail');
					$sshdir    = fDirectory::create($home          . '.ssh');
					$auth_keys = fFile::create($sshdir . 'authorized_keys', '');
				} catch (fValidationException $e) {
					fFilesystem::rollback();
					throw new fEnvironmentException (
						'Could not build home directory for user %s',
						$username
					);
				} 

				try {
					$group = new Group();
					$group->setGroupname($values['username']);
					$group->store();	
				} catch (fException $e) {
					fFilesystem::rollback();
					throw new fEnvironmentException (
						'Could not create group for user %s',
						$username
					);
				}

				$values['home']     = $home->getPath();
				$values['group_id'] = $group->getId();

				self::$building     = TRUE;

			} else {

				// Operations performed after user account creation

				$home     = $values['home'];
				$username = $values['username'];

				try {
					$shadow = new UserShadow();
					$shadow->setUsername($username);
					$shadow->setLastChangeDays(floor(time() / 60 / 60 / 24));
					$shadow->store();
				} catch (fException $e) {
					fFilesystem::rollback();
					throw new fEnvironmentException (
						'Unable to create shadow for user %s',
						$username
					);
				}

				fFilesystem::commit();

				// User owns their home, but it falls under group inkspot
				// so that trusted users cannot view outside of their
				// affiliates userwww directory

				sexec('chown -R '         . $username . ' ' . $home);
				sexec('chgrp -R inkspot ' . $home);
				sexec('chmod -R g+s '     . $home);

				// Make .ssh folders owned by inkspot so they cannot
				// be modified by user via SSH

				sexec('chown -R inkspot ' . $home . '.ssh');

				// Make the user's www have their group and change
				// to g+s so all files and dirs created within it also
				// have that group
								
				$userwww = $home . 'www/local/' . $username;

				sexec('chgrp -R  ' . $username . ' ' . $userwww);
				sexec('chmod g+s ' . $userwww);

				self::$building = FALSE;
			}
		}
		
		/**
		 * Attempts to create a user from a somewhat ambiguous parameter
		 *
		 * @static
		 * @access public
		 * @param int|string|User $user The identifier for the user
		 * @return User The user object
		 */
		static public function create($user)
		{
			if (!is_object($user) || !($user instanceof User)) {
				if (is_numeric($user)) {
					$user = new User($user);
				} else {
					$user = new User(array('username' => $user));
				}
			}
			
			return $user;
		}
		
		/**
		 * Adds trust between the user and another user
		 *
		 * @access public
		 * @param int|string|User The User, user id, or username to trust
		 * @return void
		 */
		public function addTrust($user)
		{
			$user = self::create($user);

			fORMDatabase::retrieve(__CLASS__)->query(
				'INSERT INTO user_groups(user_id, group_id) VALUES(%i, %i)',
				$user->getId(),
				$this->createGroup()->getId()
			);
			
			$src_user = $this->getUsername();
			$dst_user = $user->getUsername();
			$src      = '/home/users/' . $src_user . '/www/local/' . $src_user;
			$dst      = '/home/users/' . $dst_user . '/www/local/' . $src_user;

			exec('ln -s ' . $src . ' ' . $dst);
			exec('chmod 775 ' . $dst);
		}

		/**
		 * Removes trust between the user and another user
		 *
		 * @access public
		 * @param int|string|User The User, user id, or username to trust
		 * @return void
		 */
		public function removeTrust($user)
		{
			$user = self::create($user);

			fORMDatabase::retrieve(__CLASS__)->query(
				'DELETE FROM user_groups WHERE user_id=%i AND group_id=%i',
				$user->getId(),
				$this->createGroup()->getId()
			);

			$src_user = $this->getUsername();
			$dst_user = $user->getUsername();
			$dst      = '/home/users/' . $dst_user . '/www/local/' . $src_user;
			
			exec('rm ' . $dst);
		}

		/**
		 * Checks whether or not a user is trusted by the user
		 * @access public
		 * @param int|string|User The User, user id, or username to trust
		 * @return boolean Whether or not the user is trusted	 
		 */
		public function checkTrust($user)
		{
			$user = self::create($user);
			
			return fORMDatabase::retrieve(__CLASS__)->query(
				'SELECT * FROM user_groups WHERE user_id=%i AND group_id=%i',
				$user->getId(),
				$this->createGroup()->getId()
			)->countReturnedRows();		
		}
	}
