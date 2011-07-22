<?php

	/**
	 * The PublicController, a standard controller class.
	 *
	 * @author Matthew J. Sahagian [mjs] <gent@dotink.org>
	 * @copyright Copyright (c) 2011, Matthew J. Sahagian
	 */
	class PublicController extends Controller
	{

		/**
		 * Prepares a new PublicController for running actions.
		 *
		 * @access protected
		 * @param string void
		 * @return void
		 */
		protected function prepare()
		{
			parent::prepare();

			$this -> view
				  -> add  ('styles', '/styles/inkling.css')
				  -> add  ('styles', '/styles/main.css')
				  -> add  ('styles', '/styles/public/main.css')
				  -> add  ('header', 'public/header.php')
				  -> pack ('id',     self::getAction());

		}

		/**
		 * Initializes all static class information for the PublicController class
		 *
		 * @static
		 * @access public
		 * @param array $config The configuration array
		 * @param string $element The element name of the configuration array
		 * @return boolean TRUE if the initialization succeeds, FALSE otherwise
		 */
		static public function __init(array $config = array(), $element = NULL)
		{
			// All custom initialization goes here, make sure to check any
			// configuration you're setting up for errors and return FALSE
			// in the event the class cannot be initialized with the provided
			// configuration.

			return TRUE;
		}

		/**
		 * The public facing home page
		 */
		static public function home()
		{
			$self =  new self();
			$self -> view
				  -> add   ('contents', 'public/home.php')
				  -> render();
		}

		/**
		 * The signup page
		 */
		static public function signup($email = NULL, $key = NULL)
		{
			$self = new self();

			if (fRequest::isPost()) {

				$email_address  = fRequest::get('email_address');
				$user_full_name = fRequest::get('name');
				$activation_key = sha1($email_address . microtime());
				
				try {
					$activation_request = new ActivationRequest($email_address);
				} catch (fNotFoundException $e) {
					$activation_request = new ActivationRequest();
					$activation_request->setEmailAddress($email_address);
				}

				$activation_request->setKey($activation_key);
				$activation_request->store();

				$self -> view
					  -> pack ('email_address', $email_address)
					  -> pack ('name',          $user_full_name);

				$email = new fEmail();
				$email->setFromAddress('noreply@' . fURL::getDomain());
				$email->addRecipient($email, $user_full_name);
				$email->setSubjct('Create your inKSpot account!');
				
				$body  = new View();
				$body -> load ('emails/signup.php')
					  -> pack ('name', $user_full_name)
					  -> pack ('path', self::linkTo('*::signup', array(
					  	':key' => $activation_key
					  )));

				$email->setBody($body->render(TRUE))->send();
			}


			$self -> view
				  -> add   ('contents', 'public/signup.php')
				  -> render();
		}

	}
