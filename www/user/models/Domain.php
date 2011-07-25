<?php

	/**
	 * The Domain is an active record and model representing a single
	 * Domain record.
	 *
	 * @author Matthew J. Sahagian [mjs] <gent@dotink.org>
	 * @copyright Copyright (c) 2011, Matthew J. Sahagian
	 */
	class Domain extends ActiveRecord
	{

		/**
		 * Initializes all static class information for the Domain model
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
		 * Gets the record name for the Domain class
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
		 * Gets the record table name for the Domain class
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
		 * Gets the record set name for the Domain class
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
		 * Gets the entry name for the Domain class
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
		 * Gets the order for the Domain class
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
		 * Creates a new Domain from a slug and identifier.  The
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
		 * Creates a new Domain from a provided resource key.
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
		 * Adds trust between the domain and a user.  If the user is
		 * already trusted then this will recreate the symbolic link in their
		 * home directory.
		 *
		 * @access public
		 * @param int|string|User The User, user id, or username to trust
		 * @return void
		 */
		public function addTrust($user)
		{
			$user  = User::fetch($user);

			if (!($trust = $this->fetchTrust($user)->exists())) {
				$trust->store();
			}

			$domain   = $this->getName();
			$dst_user = $user->getUsername();

			$src      = '/home/domains/' . $domain . '/www/';
			$dst      = '/home/users/' . $dst_user . '/www/' . $domain;

			sexec('rm -rf ' . $dst);
			sexec('ln -s  ' . $src . ' ' . $dst);
		}

		/**
		 * Removes trust between the domain and a user
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

			$domain   = $this->getName();
			$dst_user = $user->getUsername();
			$dst      = '/home/users/' . $dst_user . '/www/' . $domain;

			sexec('rm -rf ' . $dst);
		}

		/**
		 * Checks whether or not a user is trusted by the domain
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
			$group = $this->createGroup();
			$user  = self::fetch($user);

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
