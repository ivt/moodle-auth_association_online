<?php


namespace auth_association_online\util;

use Constants;

defined( 'MOODLE_INTERNAL' ) || die();

global $CFG;

require_once( $CFG->libdir . '/authlib.php' );
require_once( $CFG->dirroot . '/auth/association_online/constants.php' );

class AoUuid
{

	public static function get()
	{
		$currentClientId = get_config( Constants::CONFIG_PATH, 'ao_client_id' );
		$lastUuid        = get_config( Constants::CONFIG_PATH, 'ao_site_uuid' );
		if ( $lastUuid )
		{
			$lastUuidClientId = get_config( Constants::CONFIG_PATH, 'ao_site_uuid_client_id' );
			if ( $lastUuidClientId == $currentClientId )
				return $lastUuid;
		}

		$uuid = self::getUuidFromServer();

		set_config( 'ao_site_uuid',           $uuid,            Constants::CONFIG_PATH );
		set_config( 'ao_site_uuid_client_id', $currentClientId, Constants::CONFIG_PATH );

		return $uuid;

	}

	private static function getUuidFromServer()
	{
		$url   = get_config( Constants::CONFIG_PATH, 'ao_url' );

		$wsdl = $url . '/soap/specialRequest?wsdl';

		$server = new \SoapClient( $wsdl );
		$details = $server->getDescription();
		return isset( $details[ 'uuid' ] ) ? $details[ 'uuid' ] : null;
	}
}