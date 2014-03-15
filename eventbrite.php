<?php
/*
Plugin Name: Eventbrite Services
Plugin URI: http://vocecommunications.com/
Description: Provides Eventbrite service, widgets, and features to supporting themes.
Author: Voce Communications
Author URI: http://vocecommunications.com/
Version: 0.1
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/**
 * Load Keyring first.
 */
require( 'keyring/keyring.php' );

/**
 * Load the Eventbrite extended Keyring class.
 */
function eventbrite_load_eventbrite_keyring_service() {

	require( 'eventbrite-keyring/eventbrite.php' );

}
add_action( 'plugins_loaded', 'eventbrite_load_eventbrite_keyring_service' );

/**
 * Load remaining Eventbrite code after Keyring.
 */
function eventbrite_load_post_keyring() {

	require( 'voce-settings-api/voce-settings-api.php' );
	require( 'eventbrite-api/eventbrite-api.php' );
	require( 'eventbrite-settings/eventbrite-settings.php' );
	require( 'eventbrite-widgets/eventbrite-widgets.php' );
	require( 'suggested-pages-setup/suggested-pages-setup.php' );
	require( 'tlc-transients/tlc-transients.php' );
	require( 'php-calendar/calendar.php' );

}
add_action( 'plugins_loaded', 'eventbrite_load_post_keyring' );