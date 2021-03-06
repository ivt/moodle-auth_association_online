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
 * @copyright  2013 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_association_online\event;
defined('MOODLE_INTERNAL') || die();

require_once( $CFG->dirroot . '/auth/association_online/constants.php' );

/**
 * @copyright  2013 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_loggedin extends \core\event\base {

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user $this->relateduserid has sent logged in with Oauth2.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_user_loggedin', \Constants::PLUGIN_NAME);
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/login/index.php', array());
    }

    /**
     * Sets the "level" field of the event to self::LEVEL_OTHER.
     * Behaves differently if we are in moodle version 2.6 or 2.7, as "level" was renamed to "edulevel".
     * https://tracker.moodle.org/browse/MDL-44069
     */
    private function initLevel() {

        global $CFG;
        if ($CFG->version < 2013111899) {
            $this->data['level'] = self::LEVEL_OTHER;
        } else {
            $this->data['edulevel'] = self::LEVEL_OTHER;
        }

    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->initLevel();
        $this->data['objecttable'] = 'user';
    }

    /**
     * Custom validation.
     *
     * @return void
     */
    protected function validate_data() {
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The property relateduserid must be set.');
        }
    }

}
