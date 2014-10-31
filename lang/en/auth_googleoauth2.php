<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'auth_google', language 'en'
 *
 * @package   auth_google
 * @author Jerome Mouneyrac
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Association Online';
$string['auth_googleuserprefix'] = 'The created user\'s username will start with this prefix. On a basic Moodle site you don\'t need to change it.';
$string['auth_googleuserprefix_key'] = 'Username prefix';
$string['auth_googleoauth2description'] = 'Allow a user to connect to the site with an Association Online account. The first time the user connects with an AO provider, a new account is created. <a href="'.$CFG->wwwroot.'/admin/search.php?query=authpreventaccountcreation">Prevent account creation when authenticating</a> <b>must</b> be unset.';
$string['auth_ao_association_name_key'] = 'Association Online Association Name';
$string['auth_ao_association_name_description'] = 'The name of the association that the AO site belongs to. This will be used for the login button, where it will say "Login with [Association Name]".';
$string['auth_ao_client_id_key'] = 'Association Online Client ID';
$string['auth_ao_client_id_description'] = 'To get a Client ID/Secret pair, contact your Association Online administrator.';
$string['auth_ao_client_secret_key'] = 'Association Online Client Secret';
$string['auth_ao_client_secret_description'] = 'See above';
$string['auth_ao_oauth_url_key'] = 'Association Online OAuth2 URL';
$string['auth_ao_oauth_url_description'] = 'This is the path to the OAuth2 confirmation form on the AO server. This should not include "/token" or "/auth" - Moodle will append these to the end of the URL you enter here. For example, "http://example.associationonline.com.au/oauth"';
$string['auth_ao_soap_url_key'] = 'Association Online Web Service URL';
$string['auth_ao_soap_url_description'] = 'This path is to the SOAP web service which will be used to request the AO users details (e.g. first name, last name, email). For example, http://example.associationonline.com.au/soap/clients/contact"';

$string['auth_googlesettings'] = 'Settings';
$string['couldnotauthenticate'] = 'The authentication failed - Please try to sign-in again.';
$string['couldnotgetgoogleaccesstoken'] = 'The authentication provider sent us a communication error. Please try to sign-in again.';
$string['couldnotauthenticateuserlogin'] = 'Authentication method error.<br/>
Please try to login again with your username and password.<br/>
<br/>
<a href="{$a->loginpage}">Try again</a>.<br/>
<a href="{$a->forgotpass}">Forgot your password</a>?';
$string['oauth2displaybuttons'] = 'Display buttons on login page';
$string['oauth2displaybuttonshelp'] = 'Display "Sign in with [Association]" button on the top of the login page. If you want to position the buttons yourself in your login page, you can keep this option disabled and add the following code:
{$a}';
$string['auth_sign-in_with'] = 'Sign-in with {$a->providerName}';
$string['signinwithanaccount'] = 'Log in with:';
$string['noaccountyet'] = 'You do not have permission to use the site yet. Please contact your administrator and ask them to activate your account.';
$string['unknownfirstname'] = 'Unknown Firstname';
$string['unknownlastname'] = 'Unknown Lastname';
