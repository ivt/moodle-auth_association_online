<?php

/**
 * @author  Jerome Mouneyrac (original)
 * @author  Internet Vision Technologies
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * Authentication Plugin: Associaiton Online Authentication
 * If the email doesn't exist, then the auth plugin creates the user.
 * If the email exist (and the user has for auth plugin this current one),
 * then the plugin login the user related to this email.
 */

use auth_association_online\util\AoUuid;

if ( !defined( 'MOODLE_INTERNAL' ) )
{
	die( 'Direct access to this script is forbidden.' );    ///  It must be included from a Moodle page
}

require_once( $CFG->libdir . '/authlib.php' );
require_once( $CFG->dirroot . '/auth/association_online/constants.php' );

/**
 * Association Online Oauth2 authentication plugin.
 */
class auth_plugin_association_online extends auth_plugin_base
{

	function __construct()
	{
		$this->authtype    = 'association_online';
		$this->roleauth    = Constants::PLUGIN_NAME;
		$this->errorlogtag = '[AUTH ASSOCIATION ONLINE] ';
		$this->config      = get_config( 'auth/association_online' );
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
		if ( !empty( $user ) && ( $user->auth == Constants::AUTH_TYPE ) )
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

	private function config( $name )
	{
		return get_config( Constants::CONFIG_PATH, $name );
	}

	private function set_config( $name, $value )
	{
		return set_config( $name, $value, Constants::CONFIG_PATH );
	}

	/**
	 * Authentication hook - is called every time user hit the login page
	 * The code is run only if the param code is mentionned.
	 */
	function loginpage_hook()
	{
		global $USER, $SESSION, $CFG, $DB;

		$authorizationcode = optional_param( 'code', '', PARAM_TEXT );
		if ( !empty( $authorizationcode ) )
		{

			//set the params specific to the authentication provider
			$params = array();

			$params[ 'grant_type' ]    = 'authorization_code';
			$params[ 'code' ]          = $authorizationcode;
			$params[ 'redirect_uri' ]  = $CFG->wwwroot . '/auth/association_online/ao_redirect.php';
			$params[ 'client_id' ]     = $this->config( 'ao_client_id' );
			$params[ 'client_secret' ] = $this->config( 'ao_client_secret' );
			$requestaccesstokenurl     = $this->config( 'ao_oauth_url' ) . "/token";

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
				$url    = $this->config( 'ao_soap_url' );
				$client = new SoapClient( $url . "?wsdl" );
				$client->__setCookie( 'oauth2_access_token', $accesstoken );
				$ao_user   = $client->getBasicUserDetails();
				$useremail = $ao_user[ 'Email1' ];
				$ao_id     = $ao_user[ 'id' ];

				// Prohibit login if email belongs to the prohibited domain
				if ( $err = email_is_not_allowed( $useremail ) )
				{
					throw new moodle_exception( $err, Constants::PLUGIN_NAME );
				}

				//if email not existing in user database then create a new username (userX).
				if ( empty( $useremail ) or $useremail != clean_param( $useremail, PARAM_EMAIL ) )
				{
					throw new moodle_exception( 'couldnotgetuseremail' );
					//TODO: display a link for people to retry
				}
				//get the user - don't bother with auth = Constants::AUTH_TYPE because
				//authenticate_user_login() will fail it if it's not Constants::AUTH_TYPE

				$user = $this->get_user_from_ao_id( $ao_id );

				//create the user if it doesn't exist
				if ( empty( $user ) )
				{

					// deny login if setting "Prevent account creation when authenticating" is on
					if ( $CFG->authpreventaccountcreation )
						throw new moodle_exception( "noaccountyet", Constants::PLUGIN_NAME );

					//get following incremented username
					$user_prefix = core_text::strtolower( $this->config( 'user_prefix' ) );
					$last_user_number   = $this->config( 'last_user_number' );
					$last_user_number   = empty( $last_user_number ) ? 1 : $last_user_number++;
					//check the user doesn't exist
					$nextuser = $DB->record_exists( 'user',
						array( 'username' => $user_prefix . $last_user_number ) );
					while ( $nextuser )
					{
						$last_user_number++;
						$nextuser = $DB->record_exists( 'user',
							array( 'username' => $user_prefix . $last_user_number ) );
					}
					$this->set_config( 'last_user_number', $last_user_number );
					$username = $user_prefix . $last_user_number;

					//retrieve more information from the provider
					$newuser            = new stdClass();
					$newuser->email     = $useremail;
					$newuser->firstname = $ao_user[ 'FirstName' ];
					$newuser->lastname  = $ao_user[ 'LastName' ];

					// Some providers allow empty firstname and lastname.
					if ( empty( $newuser->firstname ) )
						$newuser->firstname = get_string( 'unknownfirstname', Constants::PLUGIN_NAME );

					if ( empty( $newuser->lastname ) )
						$newuser->lastname = get_string( 'unknownlastname', Constants::PLUGIN_NAME );

					$newly_created_user = create_user_record( $username, '', Constants::AUTH_TYPE );
					$this->set_users_ao_id( $newly_created_user->id, $ao_id );
				}
				else
				{
					$username = $user->username;
				}

				//authenticate the user
				//TODO: delete this log later
				require_once( $CFG->dirroot . '/auth/association_online/lib.php' );
				$userid = empty( $user ) ? 'new user' : $user->id;
				oauth_add_to_log( SITEID, Constants::LOG_TAG, '', '', $username . '/' . $useremail . '/' . $userid );
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
					$event = \auth_association_online\event\user_loggedin::create(
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
					if ( !empty( $userexist ) and $userexist->auth != Constants::AUTH_TYPE )
					{
						$a             = new stdClass();
						$a->loginpage  = (string) new moodle_url( empty( $CFG->alternateloginurl ) ? '/login/index.php'
								: $CFG->alternateloginurl );
						$a->forgotpass = (string) new moodle_url( '/login/forgot_password.php' );
						throw new moodle_exception( 'couldnotauthenticateuserlogin', Constants::PLUGIN_NAME, '', $a );
					}
					else
					{
						throw new moodle_exception( 'couldnotauthenticate', Constants::PLUGIN_NAME );
					}
				}
			}
			else
			{
				throw new moodle_exception( 'couldnotgetaccesstoken', Constants::PLUGIN_NAME, '', null, var_export( $postreturnvalues, true ) );
			}
		}
		else
		{
			// If you are having issue with the display buttons option, add the button code directly in the theme login page.
			if ( $this->config(  'oauth2displaybuttons' )
			     // Check manual parameter that indicate that we are trying to log a manual user.
			     // We can add more param check for others provider but at the end,
			     // the best way may be to not use the oauth2displaybuttons option and
			     // add the button code directly in the theme login page.
			     && empty( $_POST[ 'username' ] )
		         && empty( $_POST[ 'password' ] )
			)
			{
				// Display the button on the login page.
				require_once( $CFG->dirroot . '/auth/association_online/lib.php' );
				auth_association_online_display_buttons();
			}
		}
	}

	private function print_string( $name, $params = null )
	{
		print_string( $name, Constants::PLUGIN_NAME, $params );
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

		if ( !isset( $config->user_prefix ) )
			$config->user_prefix = 'association_online_user_';

		if ( !isset( $config->oauth2displaybuttons ) )
			$config->oauth2displaybuttons = 1;

		echo '<table cellspacing="0" cellpadding="5" border="0">
            <tr>
               <td colspan="3">
                    <h2 class="main">';

		$this->print_string( 'auth_association_online_settings' );

		echo '</h2></td></tr>';

		$this->render_ao_settings( $OUTPUT, $config );

		// User prefix

		echo '<tr>
                <td align="right"><label for="user_prefix">';

		$this->print_string( 'auth_user_prefix_key' );

		echo '</label></td><td>';

		echo html_writer::empty_tag( 'input',
			array( 'type'  => 'text',
			       'id'    => 'user_prefix',
			       'name'  => 'user_prefix',
			       'class' => 'user_prefix',
			       'value' => $config->user_prefix ) );

		if ( isset( $err[ "user_prefix" ] ) )
		{
			echo $OUTPUT->error_text( $err[ "user_prefix" ] );
		}

		echo '</td><td>';

		$this->print_string( 'auth_user_prefix' );

		echo '</td></tr>';

		// Display buttons

		echo '<tr>
                <td align="right"><label for="oauth2displaybuttons">';

		$this->print_string( 'oauth2displaybuttons' );

		echo '</label></td><td>';

		$checked = empty( $config->oauth2displaybuttons ) ? '' : 'checked';
		echo html_writer::checkbox( 'oauth2displaybuttons', 1, $checked, '',
			array( 'type' => 'checkbox', 'id' => 'oauth2displaybuttons', 'class' => 'oauth2displaybuttons' ) );

		if ( isset( $err[ "oauth2displaybuttons" ] ) )
		{
			echo $OUTPUT->error_text( $err[ "oauth2displaybuttons" ] );
		}

		echo '</td><td>';

		$code = '<code>&lt;?php require_once( $CFG-&gt;dirroot . \'/auth/association_online/lib.php\' ); auth_association_online_display_buttons(); ?&gt;</code>';
		$this->print_string( 'oauth2displaybuttonshelp', $code );

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
		print_auth_lock_options( Constants::AUTH_TYPE, $user_fields, get_string( 'auth_fieldlocks_help', 'auth' ), false,
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

		$this->print_string( "auth_{$setting}_key" );

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

		$this->print_string( "auth_{$setting}_description" );

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

		if ( !isset ( $config->user_prefix ) )
			$config->user_prefix = 'social_user_';

		if ( !isset ( $config->oauth2displaybuttons ) )
			$config->oauth2displaybuttons = 0;

		// save settings
		$this->set_config( 'ao_association_name', $config->ao_association_name );
		$this->set_config( 'ao_client_id', $config->ao_client_id );
		$this->set_config( 'ao_client_secret', $config->ao_client_secret );
		$this->set_config( 'ao_oauth_url', $config->ao_oauth_url );
		$this->set_config( 'ao_soap_url', $config->ao_soap_url );
		$this->set_config( 'oauth2displaybuttons', $config->oauth2displaybuttons );

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

	private function get_ao_field_id()
	{
		global $DB;

		$ao_id_field = $DB->get_record(
			'user_info_field',
			array( 'shortname' => 'associationOnlineId' )
		);

		if ( !$ao_id_field )
			throw new moodle_exception( '', Constants::PLUGIN_NAME, "", null, "Your site needs ot have an extra use field with the shot name \"associationOnlineId\" for the auth_association_online plugin to work." );

		return $ao_id_field->id;

	}

	private static function ao_id_with_site_uuid( $ao_id )
	{
		$uuid = AoUuid::get();
		return "$ao_id,$uuid";
	}

	/**
	 * Returns an array with two elements - the first is the ao id, the second is the site UUID that
	 * the ao id belongs to.
	 * @param $ao_id_with_uuid
	 * @return array
	 */
	private static function parse_ao_id_with_uuid( $ao_id_with_uuid )
	{
		return explode( ',', $ao_id_with_uuid );
	}

	private function get_user_from_ao_id( $ao_id )
	{
		global $DB, $CFG;

		$ao_id_field_id = $this->get_ao_field_id();

		$ao_id_with_uuid = $this->ao_id_with_site_uuid( $ao_id );

		$existing_ao_id = $DB->get_record_sql(
			"SELECT * FROM user_info_data WHERE fieldid = :fieldid AND data = :data",
			array(
				'fieldid' => $ao_id_field_id,
			    'data'    => $ao_id_with_uuid,
			)
		);

		if ( !$existing_ao_id )
			return null;

		$user = $DB->get_record(
			'user',
			array(
				'id' => $existing_ao_id->userid,
				'deleted' => 0,
				'mnethostid' => $CFG->mnet_localhost_id
			)
		);

		return $user ? $user : null;

	}

	private function set_users_ao_id( $user_id, $ao_id )
	{
		global $DB;

		$data = new stdClass();
		$data->userid  = $user_id;
		$data->fieldid = $this->get_ao_field_id();
		$data->data    = $this->ao_id_with_site_uuid( $ao_id );

		$DB->insert_record( 'user_info_data', $data );
	}
}
