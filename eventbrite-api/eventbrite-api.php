<?php
/**
 * PHP implementation of the Eventbrite API
 *
 * @package eventbrite-api
 * @author  Voce Communications
 */

if ( !class_exists( 'Voce_Eventbrite_API' ) ) {
class Voce_Eventbrite_API {

	const ENDPOINT    = 'https://www.eventbrite.com/json/';

	/**
	 * Sets up the API
	 */
	public static function init() {

		if ( !class_exists( 'TLC_Transient' ) )
			return;

		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
	}

	/**
	 * Sets up the actions used in the admin
	 */
	static function admin_init() {
		add_action( 'keyring_connection_deleted', array(__CLASS__, 'flush_api_caches'));
	}

	public static function flush_api_caches( $service ) {
		if ( $service == 'eventbrite' ) {
			wp_clear_scheduled_hook( 'sync_eb_data' );

			// flush common api data
			$flush_method_caches = array(
				'user_list_events',
				'user_list_venues'
			);
			foreach ( $flush_method_caches as $flush_method_cache ) {
				$key = md5( Voce_Eventbrite_API::get_request_unique_string( $flush_method_cache ) );
				call_user_func( array( 'Voce_Eventbrite_API', 'flush_cache' ), $key );
			}
		}
	}

	/**
	 * Retrieve the Eventbrite keyring service
	 * @return object/null returns the Eventbrite keyring service or null on failure
	 */
	public static function get_service() {
		return Keyring::get_service_by_name( 'eventbrite' );
	}

	/**
	 * Get the Eventbrite tokens
	 * @return array array of tokens
	 */
	public static function get_token() {
		$token = get_option( 'eventbrite_token' );
		if ( $token )
			return Keyring::init()->get_token_store()->get_token( array( 'type' => 'access', 'id' => array_shift( $token ) ) );
		return false;
	}

	/**
	 * Retrieve the Eventbrite authentication service
	 * @return object/boolean returns the Eventbrite keyring service or false on failure
	 */
	public static function get_auth_service() {
		$service = self::get_service();
		$token = self::get_token();

		if ( $token ) {
			$service->set_token( $token );
			return $service;
		}
		return false;
	}

	/**
	 * Submits the request to the API
	 * @param string $method api method
	 * @param array $params request parameters
	 * @param boolean $force force a renewal of the cache
	 * @return object/boolean response object is an object when successful or an object/boolean on failure
	 */
	private static function get_auth_request( $method, $params = array(), $force = false ) {

		if ( !self::get_auth_service() )
			return false;

		$request_key = self::get_request_unique_string( $method, $params );

		$transient = tlc_transient( $request_key )
				->updates_with( array( 'Voce_Eventbrite_API', 'make_request' ) , array( $method, $params ) )
				->expires_in( '1200' ) // 20 minutes
				->extend_on_fail( '300' ); // 5 minutes

		if ( $force )
			$transient->fetch_and_cache();

		$response = $transient->get();

		return $response;
	}

	/**
	 * Delete the cache of the specified unique string
	 * @param string $key request unique string
	 */
	public static function flush_cache( $key ) {
		delete_transient( 'tlc__' . $key );
	}

	/**
	 * Makes the request to the API
	 * @param string $method api method
	 * @param array $params request parameters
	 * @return object response object
	 * @throws Exception exception when service is not available or an error occurs when submitting the request
	 */
	public static function make_request( $method, $params ) {
		$url = self::ENDPOINT . $method;
		$eb  = self::get_auth_service();

		if ( !$eb )
			throw new Exception( __( 'Eventbrite API: Failed to get auth service.', 'eventbrite-parent' ) );

		if (count($params) ) {
			$url = add_query_arg( $params, $url );
		}

		$response = $eb->request( $url );

		if ( is_a($response, 'Keyring_Error') || isset($response->error) )
			throw new Exception( sprintf( __( 'Eventbrite API: %s', 'eventbrite-parent' ), $response->error->error_message ) );

		return $response;
	}

	protected static function get_repeat_occurrences( $events ) {

		$repeat_events = array();

		foreach ( $events as $event ) {

			if ( 'yes' === $event->event->repeats ) {

				foreach ( $event->event->repeat_schedule as $i => $repeat ) {

					$same_start_date = ( $repeat->start_date === $event->event->start_date );
					$same_end_date   = ( $repeat->end_date === $event->event->end_date );

					if ( $same_start_date && $same_end_date ) {
						continue;
					}

					$repeat_event = $event;

					$repeat_event->event->start_date = $repeat->start_date;
					$repeat_event->event->end_date   = $repeat->end_date;
					$repeat_event->event->occurrence = $i;

					$repeat_events[] = $repeat_event;

				}

			}

		}

		return $repeat_events;

	}

	/**
	 * Creates a string to uniquely identify the provided method and parameters
	 * @param string $method api method
	 * @param array $params request parameters
	 * @return string
	 */
	public static function get_request_unique_string( $method, $params = array() ) {
		$unique = 'eventbrite-request-' . $method;
		if ( count($params) ) {
			$unique .= '-' . substr(md5(implode('-', $params)), 0, 5);
		}
		return $unique;
	}

	/**
	 * Get the authenticated user
	 * @param boolean $force force a renewal of the cache
	 * @return boolean
	 */
	public static function get_user( $force = false ) {
		$response = self::get_auth_request( 'user_get', array(), $force );
		if ( $response && isset($response->user) ) {
			$user = $response->user;
			return $user;
		}
		return false;
	}

	/**
	 * Get a list of the authenticated user's venues
	 * @param boolean $force force a renewal of the cache
	 * @return array array of the user venue objects
	 */
	public static function get_user_venues( $force = false ) {
		$response = self::get_auth_request( 'user_list_venues', array(), $force );
		if ( $response && isset( $response->venues ) ) {
			return $response->venues;
		}
		return array();
	}

	/**
	 * Get a list of the user events
	 *
	 * Parameters
	 * count              - int - number of items to return
	 * per_page           - int - number of items to have on a page
	 * page               - int - current page number
	 * orderby            - string - ordering of the results ( default: start_date; possible values - created )
	 * order	          - string - asc / desc
	 * include            - array - only return the specified event ids
	 * include_occurrence - array - only return the specified event ids with the specified occurrence. Ex format - array(array( 'id' => ###, 'occurrence' => ### ))
	 * exclude            - array - do not return the specifed event ids
	 * exclude_occurrence - array - do not return the specified event ids with the specified occurrence. Ex format - array(array( 'id' => ###, 'occurrence' => ### ))
	 * organizer          - string - only return results from the specified organizer id
	 * venue              - string - only return results from the specified venue id
	 * display            - string - comma-separated list of additional output fields to display
	 *
	 * @param array $params function and api method parameters
	 * @param boolean $force force a renewal of the cache
	 * @return array array of the user event objects
	 */
	public static function get_user_events( $params = array(), $force = false ) {
		$defaults = array(
			'count'               => -1,
			'per_page'            => 10,
			'page'                => -1,
			'orderby'             => '',         // default: start_date; possible values: created
			'order'               => 'asc',
			'include'             => array(),    // include events by id
			'include_occurrences' => array(),    // include events by id and occurrence combination
			'exclude_occurrences' => array(),
			'exclude'             => array(),
			'organizer'           => '',
			'venue'               => '',
			'display'             => 'repeat_schedule',
			'search'              => '',
		);
		$params = wp_parse_args( $params , $defaults );
		extract( $params );

		$request_args = array(
			'event_statuses' => 'live,started',
			'display'        => $display,
		);

		$response = self::get_auth_request( 'user_list_events', $request_args, $force );
		if ( $response && isset($response->events) ) {
			$events = $response->events;

			// include the following ids
			if ( $include ) {
				$events = array_filter( $events, array( new User_Events_Filter($params), 'filter_included' ) );
			}

            // exclude the following ids
			if ( $exclude ) {
				$events = array_filter( $events, array( new User_Events_Filter($params), 'filter_excluded' ) );
			}

			if ( $venue && $venue !== 'all' ) {
				$events = array_filter( $events, array( new User_Events_Filter($params), 'filter_venue' ) );
			}

			if ( $organizer && $organizer !== 'all' ) {
				$events = array_filter( $events, array( new User_Events_Filter($params), 'filter_organizer' ) );
			}

			// order by when event was created
			if ( $orderby == 'created' ) {

				usort( $events, array( new User_Events_Filter($params), 'order_events' ) );

			} else {

				$repeats = self::get_repeat_occurrences( $events );

				$events  = array_merge( $events, $repeats );

				unset( $repeats );

				// re-sort events by start date since reoccurrences could effect order
				usort( $events, array( __CLASS__, 'event_start_date_sort_cb' ) );

				// include the following ids with occurrences
				if ( $include_occurrences ) {
					$events = array_filter( $events, array( new User_Events_Filter($params), 'filter_included_occurrences' ) );
				}

				// exclude the following ids with occurrences
				if ( $exclude_occurrences ) {
					$events = array_filter( $events, array( new User_Events_Filter($params), 'filter_excluded_occurrences' ) );
				}

				// only show events after now since API shows the previous repeat events if there are repeat events in the future
				$events = array_filter( $events, array( new User_Events_Filter($params), 'filter_events_after_now' ) );

			}

			// allow the event titles to be searched
			if ( ! empty( $search ) ) {
				$search  = stripslashes( $search );
				$matched = array();
				foreach ( $events as $event ) {
					if ( isset( $event->event->title ) && false !== strpos( strtolower( $event->event->title ), strtolower( $search ) ) ) {
						$matched[] = $event;
					}
				}
				$events = $matched;
			}

			// pagination
			if ( $page > 0 ) {
				$events = array_slice( $events, ( $page - 1 ) * $per_page, $per_page );
			} else {
				// return the specified number
				if ( $count > 0 ) {
					$events = array_slice( $events, 0, $count );
				}
			}

			return $events;
		}
		return array();
	}

	/**
	 * Callback to sort events by start date
	 * @param object $event_a
	 * @param object $event_b
	 * @return boolean
	 */
	static function event_start_date_sort_cb( $event_a, $event_b ) {
		return $event_a->event->start_date > $event_b->event->start_date;
	}

	/**
	 * Get the authenticated user's events for the specified venue
	 *
	 * See get_user_events for parameter declarations
	 *
	 * @param int/string $venue_id id of the venue
	 * @param array $params function and api method parameters
	 * @param boolean $force force a renewal of the cache
	 * @return array array of the venue's event objects
	 */
	public static function get_venue_events( $venue_id, $params = array(), $force = false ) {
		$defaults = array(
			'count'    => -1,
			'per_page' => 10,
			'page'     => -1,
			'orderby'  => '',         // default: start_date; possible values: created
			'order'    => 'asc',
			'include'  => array(),
			'exclude'  => array(),
		);
		$params = wp_parse_args( $params , $defaults );
		extract( $params );
		$events = self::get_user_events( $params, $force );
		$events = array_filter($events, array( new User_Events_Filter(array('venue_id' => $venue_id)), 'filter_venue_ID' ) );
		return $events;
	}

	/**
	 * Get the venue for the authenticated user's specified venue id
	 * @param int/string $venue_id id of the venue
	 * @return object/boolean Eventbrite venue or false when does not exists
	 */
	public static function get_venue( $venue_id ) {
		$user_venues = self::get_user_venues();
		foreach ( $user_venues as $venue ) {
			if ( $venue->venue->id == $venue_id )
				return $venue->venue;
		}
		return false;
	}

	/**
	 * Retrieve the authorized user's organizers
	 * @param boolean $force force the retrieval of the organizers
	 * @return array array of organizers
	 */
	public static function get_user_organizers( $force = false ) {
		$response = self::get_auth_request( 'user_list_organizers', array(), $force );
		if ( $response && isset( $response->organizers ) ) {
			return $response->organizers;
		}
		return array();
	}

	/**
	 * Get the featured event ids
	 * @return array array of event ids
	 */
    public static function get_featured_event_ids() {
        return maybe_unserialize(
            Voce_Settings_API::GetInstance()->get_setting(
                'featured-event-ids',
                Eventbrite_Settings::eventbrite_group_key(),
                array()
            )
        );
    }
};
add_action( 'init', array( 'Voce_Eventbrite_API', 'init' ) );

/**
 * Gets the venue information from the Venue setting in the admin
 * @return object/boolean venue info or false when does not exist
 */
function eb_api_get_venue_info() {
	$venue = false;
	$venue_id = get_eventbrite_setting( 'venue-id' );
	if ( $venue_id )
		$venue = Voce_Eventbrite_API::get_venue( $venue_id );

	return $venue;
}

/**
 * Gets the events that have been featured in the admin
 * @param array $args function and api method parameters
 * @return array array of events
 */
function eb_api_get_featured_events( $args = array() ) {
	$events = array();
	$featured_event_ids = Voce_Eventbrite_API::get_featured_event_ids();
	if ( !empty($featured_event_ids) ) {
		$args['include_occurrences'] = $featured_event_ids;
		$events = Voce_Eventbrite_API::get_user_events( $args );
		// re-index array
		$events = array_values( $events );
	}
	return $events;
}

/**
 * Gets events that aren't featured
 *
 * @param array $args function and api method parameters
 * @return array array of events
 */
function eb_api_get_non_featured_events( $args = array() ) {
    $events = array();

	$featured_event_ids = Voce_Eventbrite_API::get_featured_event_ids();
	if ( !empty($featured_event_ids) ) {
		$args['exclude_occurrences'] = $featured_event_ids;
	}

	$events = Voce_Eventbrite_API::get_user_events( $args );

	return $events;
}

/**
 * Creates the ticket widget iframe
 * @param id/string $event_id event id
 * @param string $height css format of height
 * @param string $width css format of width
 */
function eb_print_ticket_widget( $event_id, $height='350px', $width='100%' ) {
	$src  = 'http://www.eventbrite.com/tickets-external';
	$args = array(
			'eid' => $event_id,
			'ref' => 'etckt',
			'v'   => 2
	);
	$frame_url = add_query_arg($args, $src);
	?>
        <div class="iframe-wrap eventbrite-widget" style="width:100%; text-align:left;" >
			<iframe src="<?php echo esc_url($frame_url); ?>" height="<?php echo esc_attr($height); ?>" width="<?php echo esc_attr($width); ?>" frameborder="0" vspace="0" hspace="0" marginheight="5" marginwidth="5" scrolling="auto" allowtransparency="true"></iframe>
		</div>
	<?php
}

/**
 * Get an event object from the given event id and optional occurrence number
 *
 * @param int $event_id
 * @param int $occurrence
 * @return object event
 */
function eb_get_event_by_id( $event_id, $occurrence = 0 ) {

    $args['include'] = array( $event_id );

	if ( ! $events = Voce_Eventbrite_API::get_user_events( $args ) ) {
        return false;
    }

	$event = array_shift( $events );

	if (
			( $occurrence > 0 ) &&
			is_array( $event->event->repeat_schedule ) &&
			isset( $event->event->repeat_schedule[$occurrence] )
	) {

		$event->event->start_date = $event->event->repeat_schedule[$occurrence]->start_date;
		$event->event->end_date   = $event->event->repeat_schedule[$occurrence]->end_date;

	}

    return $event;
}

/**
 * Class to filter events, used as a workaround  tomake array_filter calls with
 * additional arguments while avoiding using closures to allow PHP < 5.3 compatibility
 */
class User_Events_Filter {

	private $args;

	function __construct( $args ) {
        $this->args = $args;
    }

    function filter_included( $event ) {
		return in_array( $event->event->id, $this->args['include'] );
	}

	function filter_included_occurrences( $event ) {
		$occurrence    = isset( $event->event->occurrence ) ? $event->event->occurrence : 0;
		$id_occurrence = array( 'id' => (string) $event->event->id, 'occurrence' => (string) $occurrence );
		return in_array( $id_occurrence, $this->args['include_occurrences'] );
	}

	function filter_excluded_occurrences( $event ) {
		$occurrence    = isset( $event->event->occurrence ) ? $event->event->occurrence : 0;
		$id_occurrence = array( 'id' => (string) $event->event->id, 'occurrence' => (string) $occurrence );
		return !in_array( $id_occurrence, $this->args['exclude_occurrences'] );
	}

	function filter_excluded( $event ) {
		return !in_array( $event->event->id, $this->args['exclude'] );
	}

	function filter_venue( $event ) {
		// handles case when no venue is specified for an event ( online events )
		if ( isset( $event->event->venue ) ) {
			return $event->event->venue->id == $this->args['venue'];
		} elseif ( $this->args['venue'] === 'online' && !isset( $event->event->venue ) ) {
			return true;
		} else {
			return false;
		}
	}

	function filter_organizer( $event ) {
		if ( isset( $event->event->organizer->id ) ) {
			return $event->event->organizer->id == $this->args['organizer'];
		} else {
			return false;
		}
	}

	function order_events( $a, $b ) {
		if ( $this->args['order'] == 'asc' )
			return ( strtotime( $a->event->created ) > strtotime( $b->event->created ) );
		else
			return ( strtotime( $a->event->created ) < strtotime( $b->event->created ) );
	}

	function filter_venue_ID( $event ) {
		// handles case when no venue is specified for an event ( online events )
		if ( isset( $event->event->venue ) )
			return $event->event->venue->id == $this->args['venue_id'];
		elseif ( $this->args['venue_id'] === 'online' && !isset( $event->event->venue ) )
			return true;
		else
			return false;
	}

	function filter_events_after_now( $event ) {
		return current_time( 'timestamp' ) <= strtotime( $event->event->end_date );
	}

}
}
