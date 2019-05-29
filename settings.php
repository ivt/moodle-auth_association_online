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
 * Admin settings and defaults.
 *
 * @package    auth_association_online
 * @copyright  2019 ASI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    // Introductory explanation.
    $settings->add(new admin_setting_heading('association_online/pluginname', '',
            new lang_string('auth_association_onlinedescription', 'auth_association_online')));

    // Association Online Association Name
    $settings->add(new admin_setting_configtext('auth_association_online/ao_association_name', get_string('auth_ao_association_name_key','auth_association_online'),
            get_string('auth_ao_association_name_description', 'auth_association_online'), '', PARAM_RAW));

    // URL
    $settings->add(new admin_setting_configtext('auth_association_online/ao_url', get_string('auth_ao_url_key','auth_association_online'),
            get_string('auth_ao_url_description', 'auth_association_online'), '', PARAM_URL));

    // Client ID.
    $settings->add(new admin_setting_configtext('auth_association_online/ao_client_id', get_string('auth_ao_client_id_key','auth_association_online'),
            get_string('auth_ao_client_id_description', 'auth_association_online'), '', PARAM_RAW));

    // Client Secret.
    $settings->add(new admin_setting_configtext('auth_association_online/ao_client_secret', get_string('auth_ao_client_secret_key','auth_association_online'),
            get_string('auth_ao_client_secret_description', 'auth_association_online'), '', PARAM_RAW));

    // Contacts instance name.
    $settings->add(new admin_setting_configtext('auth_association_online/ao_contacts_instance', get_string('auth_ao_contacts_instance_key','auth_association_online'),
            get_string('auth_ao_contacts_instance_description', 'auth_association_online'), get_string('auth_ao_contacts_instance_default', 'auth_association_online'), PARAM_RAW));

    // Single Sign On Path.
    $settings->add(new admin_setting_configtext('auth_association_online/ao_sso_path', get_string('auth_ao_sso_path_key','auth_association_online'),
            get_string('auth_ao_sso_path_description', 'auth_association_online'), get_string('auth_ao_sso_path_default','auth_association_online'), PARAM_RAW));

    // Username prefix.
    $settings->add(new admin_setting_configtext('auth_association_online/user_prefix', get_string('auth_user_prefix_key','auth_association_online'),
            get_string('auth_user_prefix', 'auth_association_online'), 'association_online_user_', PARAM_RAW));

    // Display Login Button.
    $yesno = array(
            new lang_string('no'),
            new lang_string('yes'),
        );
    $settings->add(new admin_setting_configselect('auth_association_online/oauth2displaybuttons',
        new lang_string('oauth2displaybuttons', 'auth_association_online'),
        new lang_string('oauth2displaybuttonshelp', 'auth_association_online','<code>&lt;?php require_once( $CFG-&gt;dirroot . \'/auth/association_online/lib.php\' ); auth_association_online_display_buttons(); ?&gt;</code>'), 1 , $yesno));

    $authplugin = get_auth_plugin('association_online');
    display_auth_lock_options($settings, $authplugin->authtype, $authplugin->userfields,
            get_string('auth_fieldlocks_help', 'auth'), false, false);

}
