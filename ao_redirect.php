<?php

/*
 * Get association online code and call the normal login page
 * Needed to add the parameter authprovider in order to identify the authentication provider
 */
require('../../config.php');
$code = optional_param('code', '', PARAM_TEXT);

if (empty($code)) {
    throw new moodle_exception('ao_failure, was not passed a "code" from the Association Online OAuth2 server.');
}

// Ensure that this is no request forgery going on, and that the user
// sending us this connect request is the user that was supposed to.
if ($_SESSION['STATETOKEN'] !== required_param('state', PARAM_TEXT)) {
    throw new moodle_exception('Invalid state parameter');
}

$loginurl = '/login/index.php';
if (!empty($CFG->alternateloginurl)) {
    $loginurl = $CFG->alternateloginurl;
}
$url = new moodle_url($loginurl, array('code' => $code));
redirect($url);
?>
