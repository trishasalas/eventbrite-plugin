<?php
/*
Plugin Name: Eventbrite Services
Plugin URI: http://automattic.com/
Description: Provides Eventbrite service, widgets, and features to supporting themes.
Author: Automattic
Author URI: http://automattic.com/
Version: 0.1
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

require( 'keyring/keyring.php' );
require( 'voce-settings-api/voce-settings-api.php' );
require( 'eventbrite-api/eventbrite-api.php' );
require( 'eventbrite-settings/eventbrite-settings.php' );
require( 'eventbrite-widgets/eventbrite-widgets.php' );
require( 'suggested-pages-setup/suggested-pages-setup.php' );
require( 'tlc-transients/tlc-transients.php' );
require( 'php-calendar/calendar.php' );