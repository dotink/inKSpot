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
					$sshdir    = fDirectory::create($home          . '.ssh');
					$www       = fDirectory::create($home          . 'www');
					$localwww  = fDirectory::create($www           . 'local');
					$userwww   = fDirectory::create($localwww      . $username);
					$docroot   = fDirectory::create($userwww       . 'docroot');
					$mail      = fDirectory::create($home          . 'mail');
					$auth_keys = fFile::create($sshdir . 'authorized_keys', '');
					
					$immutable = array(
						'home', 'www', 'localwww', 'userwww', 'docroot'
					);
					
					foreach ($immutable as $dir) {
						fFile::create($$dir . '.immutable', '');					
					}

				} catch (fValidationException $e) {
					fFilesystem::rollback();
					throw new fEnvironmentException (
						'Could not build home directory for user %s: %s',
						$username,
						$e->getMessage()
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

			} elseif (self::$building) {

				// If we got here we can commit our filesystem changes

				fFilesystem::commit();

				// Now we need to fix up permissions, let's first define
				// our directories again.

				$username  = $values['username'];
				$home      = $values['home'];
				$sshdir    = $home     . '.ssh'    . DIRECTORY_SEPARATOR;
				$www       = $home     . 'www'     . DIRECTORY_SEPARATOR;
				$localwww  = $www      . 'local'   . DIRECTORY_SEPARATOR;
				$userwww   = $localwww . $username . DIRECTORY_SEPARATOR;
				$docroot   = $userwww  . 'docroot' . DIRECTORY_SEPARATOR;
				$auth_keys = $sshdir   . 'authorized_keys';

				// User owns their home, but it falls under group inkspot
				// so that trusted users cannot view outside of their
				// affiliates userwww directory

				sexec('chown -R '         . $username . ' ' . $home);
				sexec('chgrp -R inkspot ' . $home);
				sexec('chmod -R g+s '     . $home);

				// Make .ssh folder and files owned by inkspot so they cannot
				// be modified by user via SSH, but make them group readable
				// so we can log in!

				sexec('chown -R inkspot:' . $username . ' ' . $sshdir);
				sexec('chmod 750 '                          . $sshdir);
				sexec('chmod 640 '                          . $auth_keys);

				// Make the user's www has their group and change and is setgid
				// so all files and dirs created within it also use their
				// group.  

				sexec('chgrp -R  ' . $username . ' ' . $userwww);
				sexec('chmod g+s ' . $userwww);

				// Remove write permissions for www leading directories,
				// preventing users from creating stray directories or
				// renaming others without changing permissions.
				
				sexec('chmod u-w ' . $www);
				sexec('chmod u-w ' . $localwww);

				// Make path leading up to user's www executable by all so
				// users not in 'inkspot' group can cd to them, but not read
				// them.

				sexec('chmod a+x ' . $home);
				sexec('chmod a+x ' . $www);
				sexec('chmod a+x ' . $localwww);
				sexec('chmod a+x ' . $userwww);

				// Make all immutable files... well... umm immutable.  This
				// prevents the directories from being removed by any user
				// except root.

				sexec('chattr +i ' . $home     . '.immutable');
				sexec('chattr +i ' . $www      . '.immutable');
				sexec('chattr +i ' . $localwww . '.immutable');
				sexec('chattr +i ' . $userwww  . '.immutable');
				sexec('chattr +i ' . $docroot  . '.immutable');

				$hosts = new fFile('/etc/hosts');
				$hosts->append(implode(' ', array(
					'127.0.2.1',
					$user->getDomain(),
					$user->getUsername() . "\n"
				)));
				
				inKSpot::writeWebConfig(
					$user->buildWebConfiguration(),
					$user->getUsername()
				);

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
		 * Gets the domain for the user account
		 *
		 * @access public
		 * @param void
		 * @return string The current user subdomain
		 */
		public function getDomain()
		{
			return $this->getUsername() . '.' . inKSpot::getDomain();
		}

		/**
		 * Builds a user's web hosting configuration file
		 *
		 * @access public
		 * @param void
		 * @return string The configuration content
		 */
		public function buildWebConfiguration()
		{
			$sub_config  = '';
			$username    = $this->getUsername();
			$domain      = $this->getDomain();

			$userwww     = implode(DIRECTORY_SEPARATOR, array(
				$this->getHome() . 'www/local', // localwww
				$username,                      // userwww
				'docroot'                       // docroot
			));

			$web_configs =	UserWebConfigurations::build(
				array('user_id=' => $this->getId())
			);	

			foreach ($web_configs as $web_config) {

				$template_vars = array(
					'%SOCKET%' => 'socket'
				);

				$config = $web_conf->getTemplate()->read();
				$engine = $web_conf->createEngine();
				$socket = UserWebEngine::getSocket($this, $engine);
				
				foreach ($template_vars as $var => $value) {
					$config = str_replace(
						$var,
						$$value,
						$config
					);
				}

				$sub_config .= "\n\n" . $config;
			}

			$template_vars = array(
				'%SUB_CONFIGURATIONS%' => 'sub_config',
				'%DOMAIN%'             => 'domain',
				'%DOCUMENT_ROOT%'      => 'userwww',				
			);

			$config = WebConfiguration::getStandardConfiguration();

			foreach ($template_vars as $var => $value) {
				$config = str_replace(
					$var,
					$$value,
					$config
				);
			}
			
			return $config;
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
			sexec('chattr -i ' . $dst);
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

			sexec('chattr -i ' . $dst);
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
