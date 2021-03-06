<?php
// This file is not a part of Moodle - http://moodle.org/
// This is a none core contributed module.
//
// This is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// The GNU General Public License
// can be see at <http://www.gnu.org/licenses/>.

/**
 * Association Online OAuth2 authentication.
 *
 * @package    auth
 * @subpackage googleoauth2
 * @copyright  2012 Jerome Mouneyrac {@link http://www.moodleitandme.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version  = 2019052402;
$plugin->requires = 2018051700;   // Requires Moodle 3.5 or later. earlier commits should support version 2.6-3.4. this may support some of those versions, but i haven't tested yet.
$plugin->release = '1.5.1 (Build: 2019052401)';
$plugin->maturity = MATURITY_STABLE;             // this version's maturity level
$plugin->component = 'auth_association_online';
