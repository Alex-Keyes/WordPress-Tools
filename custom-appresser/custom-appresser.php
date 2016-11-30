<?php
/*
  Plugin Name: AppPresser Custom Push Notifications
  Plugin URI: http://thinkmerlin.com
  Description: Code for extending AppPush functionality
  Version: 0.2
  Author: Alex Keyes <alex@thinkmerlin.com>
  Author URI: http://thinkmerlin.com
  License: GPLv2
*/

if ( !class_exists( 'AppPresser' ) )
	return;

/*
 * Load a custom javascript file only for the AppPresser app
 */

add_action( 'wp_enqueue_scripts', 'appp_custom_scripts' );

function appp_custom_scripts() {
	// Remove cordova-core dependency to load script in browser. Otherwise it will only load in app.
	wp_enqueue_script( 'appp-custom', plugins_url( 'js/apppresser-custom.js' , __FILE__ ), array('jquery', 'cordova-core'), 1.0 );
}

/*
 * Custom push notifications - sends notification when triggered
 */
function send_custom_push_notification( $id, $comment ) {
    $msg = 'Custom Push Notificaton was triggered';
    if( function_exists('apppush_send_notification') ) { // check to see if apppush is installed
        apppush_send_notification( $msg );              // make a call to apppush
    }
}

/*
 * Custom push notifications - example of sending notification when comment is posted.
 * Requires paid Pushwoosh account, with API key added in AppPresser settings
 * Docs: http://apppresser.com/docs/extensions/apppush/#mcb_toc_head13
 */
function send_buddypress_push_notification( $args ) {
    $data = 'Comment: '. $args['component'] . $args['primary_link']; // display comment location
    if( function_exists('apppush_send_notification') ) { // check to see if apppush is installed
        apppush_send_notification( $data );              // make a call to apppush
    }
}

// Send pushes when triggered
//add_action( 'wp_insert_comment', 'send_custom_push_notification', 10, 2 ); // Send a push when a comment happens
add_action( 'user_register', 'send_custom_push_notification', 10, 2 ); // Send a push when a user is created
add_action( 'bp_activity_add', 'send_buddypress_push_notification', 10, 2 ); // Send a push when someone posts in a buddypress group

/*
 * Filter push notifications. Overrides entire message when push sent through WordPress. Does not affect pushes through PushWoosh.
 * Docs: http://apppresser.com/docs/extensions/apppush/#mcb_toc_head11
 *
function send_custom_push( $message, $post_id, $post ) {
    return $message = 'Generic Filtered Push Notification';

}
// Uncomment this line to filter pushes
add_filter( 'send_push_post_content', 'send_custom_push', 10, 3 );
*/
/*
 * Send notifications only to certain devices
 * http://apppresser.com/docs/extensions/apppush/#mcb_toc_head14
 */

// Change this hook and uncomment
add_action( 'wp_insert_comment', 'push_to_devices', 999 );

function push_to_devices() {
	// this should be an array of user IDs that you want to send the pushes too. AppPresser saves device IDs if the app user is a
    // logged in member of your WordPress site, for example in BuddyPress. This will not work unless the user has logged in through your app.
	$recipients = array( 1, 24 );
	$message = 'Hi there!';
	$push = new AppPresser_Notifications_Update;
	$devices = $push->get_devices_by_user_id( $recipients );

	if( $devices ) {
		$push->notification_send( 'now', $message, 1, $devices );
	}
}

/**
 * Uncomment this add_action to force notifications of new products in the coin category
 */
// add_action( 'init', 'push_to_subscribers_hooks' );

function push_to_subscribers_hooks() {

	$post_type = 'product';

	add_action( "publish_$post_type", 'push_new_product_to_subscribers', 10, 2 );
	add_action( "publish_future_$post_type", 'push_new_product_to_subscribers' );
}

/**
 * This will ignore the preferences and always send a notification, but you supply both
 * the taxonomy and term.
 * The user must be subscribed to that category to receive the notification.
 */
function push_new_product_to_subscribers( $post_id, $post = '' ) {

	$product_id = $post_id;

	$message    = 'We have a new coin you might be interest in';
	$post_type  = 'product';
	$term_slug  = 'coins';
	$taxonomy   = 'product_cat';

	$term = get_term_by( 'slug', $term_slug, $taxonomy );

	if( $term ) {
		$terms = array();
		$terms[] = array(
			'post_type' => $post_type,
			'taxonomy'  => $taxonomy,
			'term_id'   => $term->term_id,
		);

		$user_ids = AppPresser_Notifications_Segments::get_user_ids_by_segment_terms( $terms );
		$device_ids = AppPresser_Notifications_Segments::get_appp_push_device_ids( $user_ids );

		if( $device_ids ) {
			$custom_url = get_permalink( $product_id );
			$push = new AppPresser_Notifications_Update();
			$push->notification_send( 'now', $message, 1, $device_ids, array(), $custom_url );
		}
	}
}

/**
 * Uncomment this statement to force notifications of new Captian's log in any category
 */
// $my_custom_subscribe_push = new My_Custom_Subcribe_Push();

/**
 * Like the previous example, this will ignore the preferences and always send a notification.
 * However, in this example you don't limit this to a certain term.
 * User must be subscribed to any associated term to receive the notification.
 */
class My_Custom_Subcribe_Push {

	public $message   = 'We have a new Captain\'s Log you might be interest in';
	public $post_type = 'captains-log'; // i.e. post

	public function __construct() {
		add_action( "publish_{$this->post_type}", array( $this, 'push_my_new_posttype_to_subscribers' ), 10, 2 );
		add_action( "publish_future_{$this->post_type}", array( $this, 'push_my_new_posttype_to_subscribers' ) );	
	}

	public function push_my_new_posttype_to_subscribers( $post_id, $post = '' ) {

		if( $post == '' || ! is_object( $post ) ) {
			$post = get_post( $post_id );
		}

		$taxonomies = get_object_taxonomies( $post );

		$post_terms = wp_get_post_terms( $post_id, $taxonomies );

		if( $post_terms ) {
			// Create an array of terms structured in a way AppPresser_Notifications_Segments expects
			$terms_to_match = array();
			foreach ($post_terms as $term) {
				$terms_to_match[] = array(
					'post_type' => $this->post_type,
					'taxonomy'  => $term->taxonomy,
					'term_id'   => $term->term_id,
				);
			}

			$user_ids = AppPresser_Notifications_Segments::get_user_ids_by_segment_terms( $terms_to_match );
			$device_ids = AppPresser_Notifications_Segments::get_appp_push_device_ids( $user_ids );

			if( $device_ids ) {
				$custom_url = get_permalink( $post_id );
				$push = new AppPresser_Notifications_Update();
				$push->notification_send( 'now', $this->message, 1, $device_ids, array(), $custom_url );
			}
		}
	}
}

