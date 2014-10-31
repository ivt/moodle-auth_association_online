<?php
// This file is part of Oauth2 authentication plugin for Moodle.
//
// Oauth2 authentication plugin for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Oauth2 authentication plugin for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Oauth2 authentication plugin for Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains lib functions for the Oauth2 authentication plugin.
 *
 * @package   auth_googleoauth2
 * @copyright 2013 Jerome Mouneyrac {@link http://jerome.mouneyrac.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined( 'MOODLE_INTERNAL' ) || die();

/**
 * oauth_add_to_log is a quick hack to avoid add_to_log debugging
 */
function oauth_add_to_log( $courseid, $module, $action, $url = '', $info = '', $cm = 0, $user = 0 )
{
	if ( function_exists( 'get_log_manager' ) )
	{
		$manager = get_log_manager();
		$manager->legacy_add_to_log( $courseid, $module, $action, $url, $info, $cm, $user );
	}
	else if ( function_exists( 'add_to_log' ) )
	{
		add_to_log( $courseid, $module, $action, $url, $info, $cm, $user );
	}
}

/**
 * Get (generate) session state token.
 *
 * @return string the state token.
 */
function auth_googleoauth2_get_state_token()
{
	// Create a state token to prevent request forgery.
	// Store it in the session for later validation.
	if ( empty( $_SESSION[ 'STATETOKEN' ] ) )
	{
		$state                    = md5( rand() );
		$_SESSION[ 'STATETOKEN' ] = $state;
	}
	return $_SESSION[ 'STATETOKEN' ];
}

/**
 * For backwards compatibility only: this echoes the html created in auth_googleoauth2_render_buttons
 */
function auth_googleoauth2_display_buttons()
{
	echo auth_googleoauth2_render_buttons();
}

function auth_googleoauth2_render_buttons()
{
	global $CFG;
	$html = '';

	$a = new stdClass();
	$isEnabled = get_config( 'auth/googleoauth2', 'ao_client_id' );
	if ( !is_enabled_auth( 'googleoauth2' ) || !$isEnabled )
		return '';

	$a->providerName = get_config( 'auth/googleoauth2', 'ao_association_name' );
	$aoUrl           = get_config( 'auth/googleoauth2', 'ao_oauth_url' );
	$aoClientId      = get_config( 'auth/googleoauth2', 'ao_client_id' );

	$link = $aoUrl . '/auth?client_id=' . $aoClientId . '&redirect_uri=' . $CFG->wwwroot . '/auth/googleoauth2/ao_redirect.php&state=' . auth_googleoauth2_get_state_token() . '&scope=clients.contact.getBasicUserDetails&response_type=code';
	$html .= '<div class="singinprovider">';
	$html .= '<a class="ao" href="' . $link . '">';
	$html .= get_string( 'auth_sign-in_with', 'auth_googleoauth2', $a );
	$html .= '</a></div></div>';

	return $html;
}
