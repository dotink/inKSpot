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

			fORMValidation::addRegexRule(
				__CLASS__,
				'username',
				'/^[a-z]([a-z0-9_]*)$/',
				'The username can only conatain lowercase letters, numbers, ' .
				'and underscores, and must begin with a letter.'
			);

			fORM::registerHookCallback(
				__CLASS__,
				'post::validate()',
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
			if ($user->exists() && !self::$building) {
				return;
			} elseif (self::$building) {

				// Build our web configuration

				try {
					inKSpot::writeWebConfig(
						$user->buildWebConfiguration(),
						$user->getUsername()
					);
				} catch (fException $e){
					fFileSystem::rollback();
					throw new fEnvironmentException (
						'Web configuration for %s could not be built',
						$user
					);
				}

				$user->setFilePermissions();

				self::$building = FALSE;
			}

			$username  = $values['username'];

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

			fFilesystem::commit();

			// Associate the user with _everyone_

			$user->associateGroups(array(9999));

			$values['home']     = $home->getPath();
			$values['group_id'] = $group->getId();
			self::$building     = TRUE;
		}

		/**
		 * Attempts to fetch a user from a somewhat ambiguous parameter
		 *
		 * @static
		 * @access public
		 * @param int|string|User $user The identifier for the user
		 * @return User The user object
		 */
		static public function fetch($user)
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
		 * Sets the file permissions on a user's home directory
		 *
		 * @static
		 * @access public
		 * @param void
		 * @return boolean TRUE if all operations succeed, FALSE otherwise
		 */
		public function setFilePermissions() {

			$failed    = 0;
			$username  = $user->getUsername();
			$home      = $user->getHome();
			$sshdir    = $home     . '.ssh'    . DIRECTORY_SEPARATOR;
			$www       = $home     . 'www'     . DIRECTORY_SEPARATOR;
			$localwww  = $www      . 'local'   . DIRECTORY_SEPARATOR;
			$userwww   = $localwww . $username . DIRECTORY_SEPARATOR;
			$docroot   = $userwww  . 'docroot' . DIRECTORY_SEPARATOR;
			$auth_keys = $sshdir   . 'authorized_keys';

			// User owns their home, but it falls under group inkspot
			// so that trusted users cannot view outside of their
			// affiliates userwww directory

			$failed |= sexec('chown -hR '         . $username . ' ' . $home);
			$failed |= sexec('chgrp -hR inkspot ' . $home);
			$failed |= sexec('chmod -hR g+s '     . $home);

			// Make .ssh folder and files owned by inkspot so they cannot
			// be modified by user via SSH, but make them group readable
			// so we can log in!

			$failed |= sexec('chown -R inkspot:' . $username . ' ' . $sshdir);
			$failed |= sexec('chmod 750 '                          . $sshdir);
			$failed |= sexec('chmod 640 '                          . $auth_keys);

			// Make the user's www have their group and change and is setgid
			// so all files and dirs created within it also use their
			// group.

			$failed |= sexec('chgrp -hR  ' . $username . ' ' . $userwww);
			$failed |= sexec('chmod g+s  ' . $userwww);

			// Remove write permissions for www leading directories,
			// preventing users from creating stray directories or
			// renaming others without changing permissions.

			$failed |= sexec('chmod u-w ' . $www);
			$failed |= sexec('chmod u-w ' . $localwww);

			// Make path leading up to user's www executable by all so
			// users not in 'inkspot' group can cd to them, but not read
			// them.

			$failed |= sexec('chmod a+x ' . $home);
			$failed |= sexec('chmod a+x ' . $www);
			$failed |= sexec('chmod a+x ' . $localwww);
			$failed |= sexec('chmod a+x ' . $userwww);

			// Make all immutable files... well... umm immutable.  This
			// prevents the directories from being removed by any user
			// except root.

			$failed |= sexec('chattr +i ' . $home     . '.immutable');
			$failed |= sexec('chattr +i ' . $www      . '.immutable');
			$failed |= sexec('chattr +i ' . $localwww . '.immutable');
			$failed |= sexec('chattr +i ' . $userwww  . '.immutable');
			$failed |= sexec('chattr +i ' . $docroot  . '.immutable');

			return $failed;
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
		 * Adds trust between the user and another user.  If the user is
		 * already trusted then this will recreate the symbolic link in their
		 * home directory.
		 *
		 * @access public
		 * @param int|string|User The User, user id, or username to trust
		 * @return void
		 */
		public function addTrust($user)
		{
			$user  = self::fetch($user);

			if (!($trust = $this->fetchTrust($user)->exists())) {
				$trust->store();
			}

			$src_user = $this->getUsername();
			$dst_user = $user->getUsername();
			$src      = '/home/users/' . $src_user . '/www/local/' . $src_user;
			$dst      = '/home/users/' . $dst_user . '/www/local/' . $src_user;

			sexec('rm -rf ' . $dst);
			sexec('ln -s  ' . $src . ' ' . $dst);
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
			$user  = self::fetch($user);

			if ($trust = $this->fetchTrust($user)->exists()) {
				$trust->delete();
			}

			$src_user = $this->getUsername();
			$dst_user = $user->getUsername();
			$dst      = '/home/users/' . $dst_user . '/www/local/' . $src_user;

			sexec('rm -rf ' . $dst);
		}

		/**
		 * Checks whether or not a user is trusted by the user
		 *
		 * @access public
		 * @param int|string|User The User, user id, or username to trust
		 * @return boolean Whether or not the user is trusted
		 */
		public function checkTrust($user)
		{
			return $this->fetchTrust($user)->exists();
		}

		/**
		 * Fetches a UserGroup (trust) object if it exists, or creates
		 * a new one with the appropriate ids if it does not exist.
		 *
		 * @access public
		 * @param int|string|User The User, user id, or username to trust
		 * @return User The UserGroup object representing trust
		 */
		public function fetchTrust($user)
		{
			$user  = self::fetch($user);
			$group = $this->createGroup();

			try {
				return new UserGroup(array(
					'user_id'  => $user->getId(),
					'group_id' => $group->getId()
				));
			} catch (fNotFoundException $e) {
				$trust = new UserGroup();
				$trust->setUserId($user->getId());
				$trust->setGroupId($group->getId());
				return $trust;
			}
		}
	}
