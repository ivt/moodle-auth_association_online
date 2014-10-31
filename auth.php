<?php

/**
 * @author  Jerome Mouneyrac (original)
 * @author  Internet Vision Technologies
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package moodle multiauth
 *
 * Authentication Plugin: Associaiton Online Authentication
 * If the email doesn't exist, then the auth plugin creates the user.
 * If the email exist (and the user has for auth plugin this current one),
 * then the plugin login the user related to this email.
 */

if ( !defined( 'MOODLE_INTERNAL' ) )
{
	die( 'Direct access to this script is forbidden.' );    ///  It must be included from a Moodle page
}

require_once( $CFG->libdir . '/authlib.php' );

/**
 * Association Online Oauth2 authentication plugin.
 */
class auth_plugin_googleoauth2 extends auth_plugin_base
{

	function __construct()
	{
		$this->authtype    = 'googleoauth2';
		$this->roleauth    = 'auth_googleoauth2';
		$this->errorlogtag = '[AUTH GOOGLEOAUTH2] ';
		$this->config      = get_config( 'auth/googleoauth2' );
	}

	/**
	 * Prevent authenticate_user_login() to update the password in the DB
	 * @return boolean
	 */
	function prevent_local_passwords()
	{
		return true;
	}

	/**
	 * Authenticates user against Association Online
	 *
	 * @param string $username The username (with system magic quotes)
	 * @param string $password The password (with system magic quotes)
	 * @return bool Authentication success or failure.
	 */
	function user_login( $username, $password )
	{
		global $DB, $CFG;

		//retrieve the user matching username
		$user = $DB->get_record( 'user', array( 'username'   => $username,
		                                        'mnethostid' => $CFG->mnet_localhost_id ) );

		//username must exist and have the right authentication method
		if ( !empty( $user ) && ( $user->auth == 'googleoauth2' ) )
		{
			$code = optional_param( 'code', false, PARAM_TEXT );
			if ( empty( $code ) )
			{
				return false;
			}
			return true;
		}

		return false;
	}

	/**
	 * Returns true if this authentication plugin is 'internal'.
	 *
	 * @return bool
	 */
	function is_internal()
	{
		return false;
	}

	/**
	 * Returns true if this authentication plugin can change the user's
	 * password.
	 *
	 * @return bool
	 */
	function can_change_password()
	{
		return false;
	}

	/**
	 * Authentication hook - is called every time user hit the login page
	 * The code is run only if the param code is mentionned.
	 */
	function loginpage_hook()
	{
		global $USER, $SESSION, $CFG, $DB;

		//check the Google authorization code
		$authorizationcode = optional_param( 'code', '', PARAM_TEXT );
		if ( !empty( $authorizationcode ) )
		{

			//set the params specific to the authentication provider
			$params = array();

			$params[ 'grant_type' ]    = 'authorization_code';
			$params[ 'code' ]          = $authorizationcode;
			$params[ 'redirect_uri' ]  = $CFG->wwwroot . '/auth/googleoauth2/ao_redirect.php';
			$params[ 'client_id' ]     = get_config( 'auth/googleoauth2', 'ao_client_id' );
			$params[ 'client_secret' ] = get_config( 'auth/googleoauth2', 'ao_client_secret' );
			$requestaccesstokenurl     = get_config( 'auth/googleoauth2', 'ao_oauth_url' ) . "/token";

			//request by curl an access token and refresh token
			require_once( $CFG->libdir . '/filelib.php' );
			$curl = new curl();

			$postreturnvalues = $curl->post( $requestaccesstokenurl, $params );
			$postreturnvalues = json_decode( $postreturnvalues );
			$accesstoken      = $postreturnvalues->access_token;

			//with access token request by curl the email address
			if ( !empty( $accesstoken ) )
			{

				//get the username matching the email
				$url    = get_config( 'auth/googleoauth2', 'ao_soap_url' );
				$client = new SoapClient( $url . "?wsdl" );
				$client->__setCookie( 'oauth2_access_token', $accesstoken );
				$ao_user   = $client->getBasicUserDetails();
				$useremail = $ao_user[ 'Email1' ];

				// Prohibit login if email belongs to the prohibited domain
				if ( $err = email_is_not_allowed( $useremail ) )
				{
					throw new moodle_exception( $err, 'auth_googleoauth2' );
				}

				//if email not existing in user database then create a new username (userX).
				if ( empty( $useremail ) or $useremail != clean_param( $useremail, PARAM_EMAIL ) )
				{
					throw new moodle_exception( 'couldnotgetuseremail' );
					//TODO: display a link for people to retry
				}
				//get the user - don't bother with auth = googleoauth2 because
				//authenticate_user_login() will fail it if it's not 'googleoauth2'
				$user = $DB->get_record( 'user',
					array( 'email' => $useremail, 'deleted' => 0, 'mnethostid' => $CFG->mnet_localhost_id ) );

				//create the user if it doesn't exist
				if ( empty( $user ) )
				{

					// deny login if setting "Prevent account creation when authenticating" is on
					if ( $CFG->authpreventaccountcreation )
						throw new moodle_exception( "noaccountyet", "auth_googleoauth2" );

					//get following incremented username
					$googleuserprefix = core_text::strtolower( get_config( 'auth/googleoauth2', 'googleuserprefix' ) );
					$lastusernumber   = get_config( 'auth/googleoauth2', 'lastusernumber' );
					$lastusernumber   = empty( $lastusernumber ) ? 1 : $lastusernumber++;
					//check the user doesn't exist
					$nextuser = $DB->record_exists( 'user',
						array( 'username' => $googleuserprefix . $lastusernumber ) );
					while ( $nextuser )
					{
						$lastusernumber++;
						$nextuser = $DB->record_exists( 'user',
							array( 'username' => $googleuserprefix . $lastusernumber ) );
					}
					set_config( 'lastusernumber', $lastusernumber, 'auth/googleoauth2' );
					$username = $googleuserprefix . $lastusernumber;

					//retrieve more information from the provider
					$newuser            = new stdClass();
					$newuser->email     = $useremail;
					$newuser->firstname = $ao_user[ 'FirstName' ];
					$newuser->lastname  = $ao_user[ 'LastName' ];

					// Some providers allow empty firstname and lastname.
					if ( empty( $newuser->firstname ) )
						$newuser->firstname = get_string( 'unknownfirstname', 'auth_googleoauth2' );

					if ( empty( $newuser->lastname ) )
						$newuser->lastname = get_string( 'unknownlastname', 'auth_googleoauth2' );

					create_user_record( $username, '', 'googleoauth2' );
				}
				else
				{
					$username = $user->username;
				}

				//authenticate the user
				//TODO: delete this log later
				require_once( $CFG->dirroot . '/auth/googleoauth2/lib.php' );
				$userid = empty( $user ) ? 'new user' : $user->id;
				oauth_add_to_log( SITEID, 'auth_googleoauth2', '', '', $username . '/' . $useremail . '/' . $userid );
				$user = authenticate_user_login( $username, null );
				if ( $user )
				{
					//prefill more user information if new user
					if ( !empty( $newuser ) )
					{
						$newuser->id = $user->id;
						$DB->update_record( 'user', $newuser );
						$user = (object) array_merge( (array) $user, (array) $newuser );
					}

					complete_user_login( $user );

					// Create event for authenticated user.
					$event = \auth_googleoauth2\event\user_loggedin::create(
						array( 'context'       => context_system::instance(),
						       'objectid'      => $user->id,
						       'relateduserid' => $user->id,
						       'other'         => array( 'accesstoken' => $accesstoken ) ) );
					$event->trigger();

					// Redirection
					if ( user_not_fully_set_up( $USER ) )
					{
						$urltogo = $CFG->wwwroot . '/user/edit.php';
						// We don't delete $SESSION->wantsurl yet, so we get there later
					}
					else if ( isset( $SESSION->wantsurl ) and ( strpos( $SESSION->wantsurl, $CFG->wwwroot ) === 0 ) )
					{
						$urltogo = $SESSION->wantsurl;    // Because it's an address in this site
						unset( $SESSION->wantsurl );
					}
					else
					{
						// No wantsurl stored or external - go to homepage
						$urltogo = $CFG->wwwroot . '/';
						unset( $SESSION->wantsurl );
					}
					redirect( $urltogo );
				}
				else
				{
					// authenticate_user_login() failure, probably email registered by another auth plugin
					// Do a check to confirm this hypothesis.
					$userexist = $DB->get_record( 'user', array( 'email' => $useremail ) );
					if ( !empty( $userexist ) and $userexist->auth != 'googleoauth2' )
					{
						$a             = new stdClass();
						$a->loginpage  = (string) new moodle_url( empty( $CFG->alternateloginurl ) ? '/login/index.php'
								: $CFG->alternateloginurl );
						$a->forgotpass = (string) new moodle_url( '/login/forgot_password.php' );
						throw new moodle_exception( 'couldnotauthenticateuserlogin', 'auth_googleoauth2', '', $a );
					}
					else
					{
						throw new moodle_exception( 'couldnotauthenticate', 'auth_googleoauth2' );
					}
				}
			}
			else
			{
				throw new moodle_exception( 'couldnotgetgoogleaccesstoken', 'auth_googleoauth2', '', null, var_export( $postreturnvalues, true ) );
			}
		}
		else
		{
			// If you are having issue with the display buttons option, add the button code directly in the theme login page.
			if ( get_config( 'auth/googleoauth2', 'oauth2displaybuttons' )
			     // Check manual parameter that indicate that we are trying to log a manual user.
			     // We can add more param check for others provider but at the end,
			     // the best way may be to not use the oauth2displaybuttons option and
			     // add the button code directly in the theme login page.
			     and empty( $_POST[ 'username' ] )
			         and empty( $_POST[ 'password' ] )
			)
			{
				// Display the button on the login page.
				require_once( $CFG->dirroot . '/auth/googleoauth2/lib.php' );
				auth_googleoauth2_display_buttons();
			}
		}
	}

	/**
	 * Prints a form for configuring this authentication plugin.
	 *
	 * This function is called from admin/auth.php, and outputs a full page with
	 * a form for configuring this plugin.
	 *
	 * TODO: as print_auth_lock_options() core function displays an old-fashion HTML table, I didn't bother writing
	 * some proper Moodle code. This code is similar to other auth plugins (04/09/11)
	 *
	 * @param array $page An object containing all the data for this page.
	 */
	function config_form( $config, $err, $user_fields )
	{
		global $OUTPUT, $CFG;

		// set to defaults if undefined
		if ( !isset ( $config->ao_association_name ) )
			$config->ao_association_name = '';

		if ( !isset ( $config->ao_client_id ) )
			$config->ao_client_id = '';

		if ( !isset ( $config->ao_client_secret ) )
			$config->ao_client_secret = '';

		if ( !isset ( $config->ao_oauth_url ) )
			$config->ao_oauth_url = '';

		if ( !isset ( $config->ao_soap_url ) )
			$config->ao_soap_url = '';

		if ( !isset( $config->googleuserprefix ) )
			$config->googleuserprefix = 'social_user_';

		if ( !isset( $config->oauth2displaybuttons ) )
			$config->oauth2displaybuttons = 1;

		echo '<table cellspacing="0" cellpadding="5" border="0">
            <tr>
               <td colspan="3">
                    <h2 class="main">';

		print_string( 'auth_googlesettings', 'auth_googleoauth2' );

		echo '</h2></td></tr>';

		$this->render_ao_settings( $OUTPUT, $config );

		// User prefix

		echo '<tr>
                <td align="right"><label for="googleuserprefix">';

		print_string( 'auth_googleuserprefix_key', 'auth_googleoauth2' );

		echo '</label></td><td>';

		echo html_writer::empty_tag( 'input',
			array( 'type'  => 'text',
			       'id'    => 'googleuserprefix',
			       'name'  => 'googleuserprefix',
			       'class' => 'googleuserprefix',
			       'value' => $config->googleuserprefix ) );

		if ( isset( $err[ "googleuserprefix" ] ) )
		{
			echo $OUTPUT->error_text( $err[ "googleuserprefix" ] );
		}

		echo '</td><td>';

		print_string( 'auth_googleuserprefix', 'auth_googleoauth2' );

		echo '</td></tr>';

		// Display buttons

		echo '<tr>
                <td align="right"><label for="oauth2displaybuttons">';

		print_string( 'oauth2displaybuttons', 'auth_googleoauth2' );

		echo '</label></td><td>';

		$checked = empty( $config->oauth2displaybuttons ) ? '' : 'checked';
		echo html_writer::checkbox( 'oauth2displaybuttons', 1, $checked, '',
			array( 'type' => 'checkbox', 'id' => 'oauth2displaybuttons', 'class' => 'oauth2displaybuttons' ) );

		if ( isset( $err[ "oauth2displaybuttons" ] ) )
		{
			echo $OUTPUT->error_text( $err[ "oauth2displaybuttons" ] );
		}

		echo '</td><td>';

		$code = '<code>&lt;?php require_once($CFG-&gt;dirroot . \'/auth/googleoauth2/lib.php\'); auth_googleoauth2_display_buttons(); ?&gt;</code>';
		print_string( 'oauth2displaybuttonshelp', 'auth_googleoauth2', $code );

		echo '</td></tr>';

		/// Block field options
		// Hidden email options - email must be set to: locked
		echo html_writer::empty_tag( 'input', array( 'type'  => 'hidden',
		                                             'value' => 'locked',
		                                             'name'  => 'lockconfig_field_lock_email' ) );

		//display other field options
		foreach ( $user_fields as $key => $user_field )
		{
			if ( $user_field == 'email' )
			{
				unset( $user_fields[ $key ] );
			}
		}
		print_auth_lock_options( 'googleoauth2', $user_fields, get_string( 'auth_fieldlocks_help', 'auth' ), false,
			false );

		echo '</table>';
	}

	function render_ao_settings( $OUTPUT, $config )
	{
		$this->render_setting( $OUTPUT, 'ao_association_name', $config->ao_association_name );
		$this->render_setting( $OUTPUT, 'ao_client_id', $config->ao_client_id );
		$this->render_setting( $OUTPUT, 'ao_client_secret', $config->ao_client_secret );
		$this->render_setting( $OUTPUT, 'ao_oauth_url', $config->ao_oauth_url );
		$this->render_setting( $OUTPUT, 'ao_soap_url', $config->ao_soap_url );
	}

	function render_setting( $OUTPUT, $setting, $value )
	{

		echo "<tr><td align='right'><label for='$setting'>";

		print_string( "auth_{$setting}_key", 'auth_googleoauth2' );

		echo '</label></td><td>';

		echo html_writer::empty_tag( 'input',
			array( 'type'  => 'text',
			       'id'    => $setting,
			       'name'  => $setting,
			       'class' => $setting,
			       'value' => $value ) );

		if ( isset( $err[ $setting ] ) )
		{
			echo $OUTPUT->error_text( $err[ $setting ] );
		}

		echo '</td><td>';

		print_string( "auth_{$setting}_description", 'auth_googleoauth2' );

		echo '</td></tr>';
	}

	/**
	 * Processes and stores configuration data for this authentication plugin.
	 */
	function process_config( $config )
	{
		// set to defaults if undefined
		if ( !isset ( $config->ao_association_name ) )
			$config->ao_association_name = '';

		if ( !isset ( $config->ao_client_id ) )
			$config->ao_client_id = '';

		if ( !isset ( $config->ao_client_secret ) )
			$config->ao_client_secret = '';

		if ( !isset ( $config->ao_oauth_url ) )
			$config->ao_oauth_url = '';

		if ( !isset ( $config->ao_soap_url ) )
			$config->ao_soap_url = '';

		if ( !isset ( $config->googleipinfodbkey ) )
			$config->googleipinfodbkey = '';

		if ( !isset ( $config->googleuserprefix ) )
			$config->googleuserprefix = 'social_user_';

		if ( !isset ( $config->oauth2displaybuttons ) )
			$config->oauth2displaybuttons = 0;

		// save settings
		set_config( 'ao_association_name', $config->ao_association_name, 'auth/googleoauth2' );
		set_config( 'ao_client_id', $config->ao_client_id, 'auth/googleoauth2' );
		set_config( 'ao_client_secret', $config->ao_client_secret, 'auth/googleoauth2' );
		set_config( 'ao_oauth_url', $config->ao_oauth_url, 'auth/googleoauth2' );
		set_config( 'ao_soap_url', $config->ao_soap_url, 'auth/googleoauth2' );
		set_config( 'oauth2displaybuttons', $config->oauth2displaybuttons, 'auth/googleoauth2' );

		return true;
	}

	/**
	 * Called when the user record is updated.
	 *
	 * We check there is no hack-attempt by a user to change his/her email address
	 *
	 * @param mixed $olduser Userobject before modifications    (without system magic quotes)
	 * @param mixed $newuser Userobject new modified userobject (without system magic quotes)
	 * @return boolean result
	 *
	 */
	function user_update( $olduser, $newuser )
	{
		if ( $olduser->email != $newuser->email )
		{
			return false;
		}
		else
		{
			return true;
		}
	}
}
