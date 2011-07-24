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
		 *
		 * @static
		 * @access public
		 * @param void
		 * @return View The controller view
		 */
		static public function home()
		{
			$self =  new self();

			$self -> view
				  -> add   ('contents', 'public/home.php')
				  -> render();

			return $self->view;
		}

		/**
		 * The signup page.  This page handles the initial activation request
		 * by a new user.
		 *
		 * @static
		 * @access public
		 * @param void
		 * @return View The controller view
		 */
		static public function signup()
		{
			$self = new self();

			if ($key = fRequest::get('key')) {
				return self::activate($key);
			}

			if (fRequest::isPost()) {

				$email_address  = fRequest::get('email_address');
				$user_full_name = fRequest::get('name');
				$activation_key = sha1($email_address . microtime());
				$system_domain  = inKSpot::getExternalDomain();

				try {
					$activation_request = new ActivationRequest(array(
						'email_address' => $email_address
					));
				} catch (fNotFoundException $e) {
					$activation_request = new ActivationRequest();
					$activation_request->setEmailAddress($email_address);
				}

				$activation_request->setName($user_full_name);
				$activation_request->setKey($activation_key);
				$activation_request->store();

				$self -> view
					  -> pack ('email', $email_address)
					  -> pack ('name',  $user_full_name);

				$email = new fEmail();
				$email->setFromEmail('noreply@' . $system_domain);
				$email->addRecipient($email_address, $user_full_name);
				$email->setSubject('Create your inKSpot account!');

				$aurl  =
				$body  = new View();
				$body -> load ('emails/signup.php')
					  -> pack ('name',  $user_full_name)
					  -> pack ('path',  iw::makeLink('*::signup', array(
					  	':key' => $activation_key
					  )));

				$email->setBody($body->render(NULL, TRUE));
				try {
					$email->send();
					fMessaging::create('success', fURL::get(), sprintf(
						'You should receive an activation e-mail shortly.'
					));
				} catch (fConnectivityException $e) {
					fMessaging::create('error', fURL::get(), sprintf(
						'There was an error sending your activation ' .
						'email, please contact ' .
						'<a href="mailto:info@dotink.org">info@dotink.org</a>'
					));
				}
			}

			$self -> view
				  -> add   ('contents', 'public/signup.php')
				  -> render();

			return $self->view;
		}

		/**
		 * The not found view -- we'll probably add some kind of site indexing
		 * and a search with some results based on the URL eventually
		 *
		 * @static
		 * @access protected
		 * @param void
		 * @return View The Controller View
		 */
		static protected function notFound()
		{
			$self = new self();


			return $self->view;
		}

		/**
		 * This method will be internally redirected to in the event that you
		 * haven't read the tutorial on setting up inKSpot... wait... there's
		 * a tutorial?
		 *
		 * @static
		 * @access public
		 * @param void
		 * @return View The Controller View
		 */
		static public function setup()
		{
			$self = new self();

			if (iw::checkSAPI('cli')) {
				$self->view->load('cli/setup.php');
			} else {
				$self -> view
					  -> add  ('contents', $view)
					  -> push ('title',    'Whoa there, not so fast!');
			}
			
			$self->view->render();
			
			return $self->view;
		}

		/**
		 * The activation page.  This is internally redirected from the
		 * signup page if a key is provided in the request.
		 *
		 * @static
		 * @access private
		 * @param string $key The Activation key
		 * @return View The controller view
		 */
		static private function activate($key)
		{
			$self = new self();

			if (fRequest::isPost()) {
				try {
					$key           = fSession::get('key');
					$user          = new User();
					$shadow        = new UserShadow();
					$email         = new UserEmailAddress();
					$request       = new ActivationRequest($key);
					$username      = fRequest::get('username');
					$location      = fRequest::get('location');
					$password      = fRequest::get('login_password');

					$user->setUsername(fRequest::get('username'));
					$user->setLocation(fRequest::get('location'));
					$user->setFullName($request->getName());
					$user->store();

					$shadow->setUserId($user->getId());
					$shadow->setLoginPassword($password);
					$shadow->store();
					
					$email->setUserId($user->getId());
					$email->setEmailAddress($request->getEmailAddress());
					$email->store();
					
					$request->delete();
					fSession::delete('key');

				} catch (fNotFoundException $e) {
					fMessaging::create('error', fURL::get(), sprintf(
						'Something went terribly wrong!'
					));
				} catch (fValidationException $e) {
					fMessaging::create('error', fURL::get(), $e->getMessage());
				}
				
				fSession::set('new_user', TRUE);
				fURL::redirect(iw::makeLink('*::home'));
			}

			try {
				$activation_request = new ActivationRequest($key);
				fSession::set('key', $activation_request->getKey());
			} catch (fNotFoundException $e) {
				fMessaging::create('error', fURL::get(), sprintf(
					'We were unable to find your activation request. '      .
					'If you have submitted multiple requests you may '      .
					'need to follow the link in another e-mail.  Or  '      .
					'requesting a new activation link '                     .
					'<a href="' . iw::makeLink('*::signup') . '">here</a>.'
					
				));
			}

			$self -> view
				  -> add   ('contents', 'public/activate.php')
				  -> render();

			return $self->view;
		}

	}
