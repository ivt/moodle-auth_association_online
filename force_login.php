<?php

/*
 * Redirect the user to association online without asking their confirmation.
 * If they are logged in on the AO site, they will be automatically redirected back to Moodle and logged in.
 * If not, they will be shown a prompt asking them to log in on AO first.
 */
require('../../config.php');
require('lib.php');

$url = auth_association_online_generate_ao_auth_link();
if ( !$url )
    throw new moodle_exception( "Association Online OAuth2 connection not setup correctly.", Constants::PLUGIN_NAME );
else
    redirect( new moodle_url( $url ) );
