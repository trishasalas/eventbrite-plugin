<?php
/**
 * Eventbrite theme widgets
 *
 * @package eventbrite-parent
 * @author  Voce Communications
 */


/**
 * Widget that displays the register/ticket call to action button
 */
if ( !class_exists( 'Eventbrite_Register_Ticket_Widget' ) ) {
class Eventbrite_Register_Ticket_Widget extends WP_Widget {

	/**
	 * Create the widget
	 */
	function __construct() {
		$widget_ops = array( 'classname' => 'widget_register_ticket', 'description' => __( 'Display a Register/Ticket button for your Featured Eventbrite Event', 'eventbrite-parent' ) );
		parent::__construct( 'register-ticket', __( 'Eventbrite: Register/Ticket Button', 'eventbrite-parent' ), $widget_ops );
		$this->alt_option_name = 'widget_register_ticket';

		add_action( 'save_post', array($this, 'flush_widget_cache') );
		add_action( 'deleted_post', array($this, 'flush_widget_cache') );
		add_action( 'switch_theme', array($this, 'flush_widget_cache') );
	}

	/**
	 * Display function for widget
	 * @param type $args
	 * @param type $instance
	 * @return type
	 */
	function widget($args, $instance) {
		if ( !Voce_Eventbrite_API::get_auth_service() )
			return;

		$cache = wp_cache_get('widget_register_ticket', 'widget');

		if ( !is_array($cache) )
			$cache = array();

		if ( ! isset( $args['widget_id'] ) )
			$args['widget_id'] = $this->id;

		if ( isset( $cache[ $args['widget_id'] ] ) ) {
			echo wp_kses( $cache[ $args['widget_id'] ], wp_kses_allowed_html( 'post' ) );
			return;
		}

		if ( ! $featured_events_setting = Voce_Settings_API::GetInstance()->get_setting( 'featured-event-ids', Eventbrite_Settings::eventbrite_group_key() , array() ) ) {
			return;
		}

		ob_start();

		$featured_event_id = array_shift( $featured_events_setting );
		?>
		<p class="text-center">
			<a class="btn btn-full" href="<?php echo esc_url( sprintf( 'http://www.eventbrite.com/event/%1$s/?ref=wpcta', $featured_event_id['id'] ) ); ?>" target="_blank"><?php echo esc_html( eb_get_call_to_action() ); ?></a>
		</p>
		<?php

		$cache[$args['widget_id']] = ob_get_flush();
		wp_cache_set('widget_register_ticket', $cache, 'widget');
	}

	/**
	 * Delete widget cache
	 */
	function flush_widget_cache() {
		wp_cache_delete('widget_register_ticket', 'widget');
	}
}
}

/**
 * Widget that displays a freeform text box with a large button
 */
if ( !class_exists( 'Eventbrite_Introduction_Widget' ) ) {
class Eventbrite_Introduction_Widget extends WP_Widget {

	/**
	 * Create the widget
	 */
	function __construct() {
		$widget_ops = array( 'classname' => 'widget_introduction', 'description' => __( 'Display an Introduction widget, with text and a link', 'eventbrite-parent' ) );
		parent::__construct( 'introduction', __( 'Eventbrite: Introduction', 'eventbrite-parent' ), $widget_ops );
		$this->alt_option_name = 'widget_introduction';
	}

	/**
	 * Update function for widget
	 * @param type $new_instance
	 * @param type $old_instance
	 * @return type
	 */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		if ( current_user_can('unfiltered_html') )
			$instance['text'] =  $new_instance['text'];
		else
			$instance['text'] = stripslashes( wp_filter_post_kses( addslashes($new_instance['text']) ) ); // wp_filter_post_kses() expects slashed
		$instance['filter'] = isset($new_instance['filter']);
		$instance['link-label'] = strip_tags($new_instance['link-label']);
		$instance['link-url'] = esc_url_raw( $new_instance['link-url'] );
		return $instance;
	}

	/**
	 * Form used with the admin
	 * @param type $instance
	 */
	function form( $instance ) {
		$instance   = wp_parse_args( (array) $instance, array( 'title' => '', 'text' => '', 'link-label' => '', 'link-url' => '' ) );
		$title      = strip_tags( $instance['title'] );
		$text       = esc_textarea( $instance['text'] );
		$link_label = strip_tags( $instance['link-label'] );
		$link_url   = esc_url( $instance['link-url'] );
		?>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Title:', 'eventbrite-parent' ); ?></label>
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id('title') ); ?>" name="<?php echo esc_attr( $this->get_field_name('title') ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></p>

		<textarea class="widefat" rows="16" cols="20" id="<?php echo esc_attr( $this->get_field_id('text') ); ?>" name="<?php echo esc_attr( $this->get_field_name('text') ); ?>"><?php echo esc_html( $text ); ?></textarea>

		<p><input id="<?php echo esc_attr( $this->get_field_id( 'filter' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'filter' ) ); ?>" type="checkbox" <?php checked( isset( $instance['filter'] ) ? $instance['filter'] : 0 ); ?> />&nbsp;<label for="<?php echo esc_attr( $this->get_field_id( 'filter' ) ); ?>"><?php _e( 'Automatically add paragraphs', 'eventbrite-parent' ); ?></label></p>

		<p><label for="<?php echo esc_attr( $this->get_field_id( 'link-label' ) ); ?>"><?php _e( 'Link Label:', 'eventbrite-parent' ); ?></label>
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'link-label' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'link-label' ) ); ?>" type="text" value="<?php echo esc_html( $link_label ); ?>" /></p>

		<p><label for="<?php echo esc_attr( $this->get_field_id( 'link-url' ) ); ?>"><?php _e( 'Link URL:', 'eventbrite-parent' ); ?></label>
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'link-url' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'link-url' ) ); ?>" type="text" value="<?php echo esc_url( $link_url ); ?>" /></p>
		<?php
	}

	/**
	 * Display function for widget
	 * @param type $args
	 * @param type $instance
	 * @return type
	 */
	function widget( $args, $instance ) {
		extract($args);
		$title       = empty( $instance['title'] ) ? '' : $instance['title'];
		$title       = apply_filters( 'widget_title', $title, $instance, $this->id_base );
		$text        = empty( $instance['text'] ) ? '' : $instance['text'];
		$link_label  = empty( $instance['link-label'] ) ? '' : $instance['link-label'];
		$link_url    = empty( $instance['link-url'] ) ? '' : $instance['link-url'];

		echo $before_widget;

		if ( !empty( $title ) )
			echo $before_title . $title . $after_title;

		?>
			<div class="textwidget">
				<?php echo !empty( $instance['filter'] ) ? wpautop( $text ) : $text; ?>
				<?php if ( $link_url && $link_label ) : ?>
				<p class="eventbrite-intro-widget-button"><a href="<?php echo esc_url( $link_url ); ?>" class="btn btn-warning"><?php echo esc_html( $link_label ); ?></a></p>
				<?php endif; ?>
			</div>
		<?php
		echo $after_widget;
	}
}
}
