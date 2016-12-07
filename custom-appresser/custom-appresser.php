<?php
/*
Plugin Name: AppPresser Custom Stuff
Plugin URI: http://apppresser.com
Description: Sample code for extending AppPresser functionality
Version: 0.1
Author: AppPresser Team
Author URI: http://apppresser.com
License: GPLv2
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
 * Custom push notifications - example of sending notification when comment is posted.
 * Requires paid Pushwoosh account, with API key added in AppPresser settings
 * Docs: http://apppresser.com/docs/extensions/apppush/#mcb_toc_head13
 */
 
function send_custom_push_notification( $id, $comment ) {
	
	if( function_exists('apppush_send_notification') )
		return;
	
	$data = $comment->comment_content;
	
	apppush_send_notification( $data );
    
}

// Uncomment this line to send pushes when comments posted
add_action( 'wp_insert_comment', 'send_custom_push_notification', 10, 2 );

/*
 * Filter push notifications. Overrides entire message when push sent through WordPress. Does not affect pushes through PushWoosh.
 * Docs: http://apppresser.com/docs/extensions/apppush/#mcb_toc_head11
 */
 
function send_custom_push( $message, $post_id, $post ) {
	
    if( 'apppush' === $post->post_type ) {
	    $message = 'My custom title';
    }

    return $message;

}
// Uncomment this line to filter pushes
//add_filter( 'send_push_post_content', 'send_custom_push', 10, 3 );


/**
 * Send notifications
 */
function push_to_group( $args ) {

    // Don't do anything if this is not a group activity item.
    if ( 'groups' !== $args['component'] ) {
        return;
    }

    // The group id is 'item_id' in the argument array.
    $group_id = $args['item_id'];
    $group_member_query = new BP_Group_Member_Query( array(
        'group_id' => $group_id,
        'group_role' => array( 'member', 'mod', 'admin' ),
    ) );
    $group_members = $group_member_query->results;
    // User IDs are the keys in the $group_members array.
    $recipients = array_keys( $group_members );

    /* output different messages based on type */
    if($args['type'] == 'joined_group'){
        $message = get_userdata($args['user_id'])->first_name
                 . ' joined your project: '
                 . groups_get_group($args['item_id'])->name;
    }
    else {
        $message = get_userdata($args['user_id'])->first_name
                 . ' posted in your project: '
                 . groups_get_group($args['item_id'])->name;
    }

    $push = new AppPresser_Notifications_Update;
	$devices = $push->get_devices_by_user_id( $recipients );
	if( $devices ) {
		$push->notification_send( 'now', $message, 1, $devices );
	}
}

add_action( 'bp_activity_add', 'push_to_group');

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

