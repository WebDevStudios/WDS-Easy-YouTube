<?php
/*
Plugin Name: EasyYoutube
Plugin URI: http://webdevstudios.com
Description: Easily import youtube user videos and embed around your website.
Version: 1.0
Author: WebDevStudios.com
Author URI: http://webdevstudios.com
License: GPLv2
*/

/*
Select comments provided by the movie Young Frankenstein.
 */
/*
TODO: add widget
TODO: create shortcode output
TODO: wp.org repo readme & github readme.
TODO: localization
TODO: rework settings api stuff.
TODO: determine what fields in the post editor the CPT needs.
TODO: determine what meta fields we need to save, if any. See: easyyoutube_userchannel_videos()
TODO: make sure wds_get_results in ajax.js is correct.
TODO: add filters for user extensibility.
TODO: add return void where appropriate
TODO: add public/private to functions
 */

/*
Dr. Frederick Frankenstein: LIFE! DO YOU HEAR ME? GIVE MY CREATION... LIFE!
 */
if ( !class_exists( 'WDS_EasyYoutube' ) ) {
	class WDS_EasyYoutube {

		function __construct() {
			// Actions
			add_action( 'admin_enqueue_scripts', 'easyyoutube_load_scripts' );
			add_action( 'admin_init', 'easyyoutube_plugin_options_init' );
			add_action( 'admin_init', 'easyyoutube_schedule_cron' );
			add_action( 'admin_menu', 'easyyoutube_plugin_options_add_page' );
			add_action( 'easyyoutube_ajax_get_results', 'easyyoutube_process_ajax' );
			add_action( 'easyyoutube_cron_import', 'easyyoutube_userchannel_videos' );
			add_action( 'init', array( $this, 'register_post_types' ) );
			add_action( 'init', array( $this, 'register_taxonomies' ) );
			add_action( 'plugins_loaded', 'load_localization' );
			add_action( 'widgets_init', 'easyyoutube_widget' );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

			// Filters
			add_filter( 'cron_schedules', 'easyyoutube_cron_add_weekly' );

			// Misc
			add_shortcode( 'easyyoutube', 'register_shortcode' );
			register_deactivation_hook(__FILE__, 'easyyoutube_deactivation');
			register_activation_hook( __FILE__, 'flush_rewrite_rules' );
			register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
		}

		/**
		 * Load our textdomain
		 * @since 1.0
		 */
		function load_localization() {
	  		load_plugin_textdomain( 'easyyoutube', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
		}

		function register_widgets() {
			register_widget( 'bc_logo_widget' );
		}

		/**
		 * Register our Youtube Post Type
		 * @since 1.0
		 */
		function register_easyyoutube_post_types() {
			$args = array(
				'labels' => array(
					'name' => _x( 'YouTube Posts', 'post type general name', 'easyyoutube' ),
					'singular_name' => _x( 'YouTube Post', 'post type singular name', 'easyyoutube' ),
					'add_new' => _x( 'Add New', 'book', 'easyyoutube' ),
					'add_new_item' => __( 'Add New YouTube Post', 'easyyoutube' ),
					'edit_item' => __( 'Edit YouTube Post', 'easyyoutube' ),
					'new_item' => __( 'New YouTube Post', 'easyyoutube' ),
					'all_items' => __( 'All YouTube Posts', 'easyyoutube' ),
					'view_item' => __( 'View YouTube Post', 'easyyoutube' ),
					'search_items' => __( 'Search YouTube Posts', 'easyyoutube' ),
					'not_found' => __( 'No YouTube Posts found', 'easyyoutube' ),
					'not_found_in_trash' => __( 'No YouTube Posts found in Trash', 'easyyoutube' ),
					'parent_item_colon' => '',
					'menu_name' => __( 'YouTube Posts', 'easyyoutube' )
				),
				'public' => true,
				'publicly_queryable' => true,
				'show_ui' => true,
				'show_in_menu' => true,
				'query_var' => true,
				'capability_type' => 'post',
				'has_archive' => true,
				'hierarchical' => false,
				'menu_position' => null,
				'show_in_nav_menus' => true,
				'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' )
			);
			register_post_type( 'youtube', $args );
		}

		/**
		 * Register our Youtube tag taxonomy
		 * @since 1.0
		 */
		function register_easyyoutube_taxonomies() {

			$args = array(
				'labels' => array(
					'name' => _x( 'Youtube Tags', 'taxonomy general name', 'easyyoutube' ),
					'singular_name' => _x( 'Youtube Tag', 'taxonomy singular name', 'easyyoutube' ),
					'search_items' =>  __( 'Search Tags', 'easyyoutube' ),
					'all_items' => __( 'All Tags', 'easyyoutube' ),
					'parent_item' => __( 'Parent Tag', 'easyyoutube' ),
					'parent_item_colon' => __( 'Parent Tag:', 'easyyoutube' ),
					'edit_item' => __( 'Edit Tag', 'easyyoutube' ),
					'update_item' => __( 'Update Tag', 'easyyoutube' ),
					'add_new_item' => __( 'Add New Tag', 'easyyoutube' ),
					'new_item_name' => __( 'New Tag Name', 'easyyoutube' ),
					'menu_name' => __( 'Youtube Tag' ,'easyyoutube' ),
				),
				'show_ui' => true,
				'query_var' => true,
				'rewrite' => array( 'slug' => 'youtubetag' ),
			);
			register_taxonomy( 'videotags', array('youtube'), $args );
		}

		/**
		 * Properly enqueue our needed scripts, if any
		 * @since 1.0
		 */
		function enqueue_scripts() {

		}

		/**
		 * Register and display our EasyYoutube shortcode
		 * @param  array $atts    shortcode attributes
		 * @param  string $content FILL ME IN
		 * @since 1.0
		 */
		function register_shortcode( $atts, $content = null ) {
			extract( shortcode_atts( array(
						'type' => ''
					), $atts ) );
		}

		/**
		 * Import the youtube videos from specified user channel.
		 * @since 1.0
		 */
		function easyyoutube_userchannel_videos() {
			// Dr. Frederick Frankenstein: [to Inga from behind the bookcase] Put... the candle... back!
			if ( ! current_user_can( 'manage_options' ) )
				return;

			//grab plugin options
			$options = get_option('easyyoutube_options');

			//We'll need these for sideloading images.
			require_once(ABSPATH . "wp-admin" . '/includes/image.php');
			require_once(ABSPATH . "wp-admin" . '/includes/file.php');
			require_once(ABSPATH . "wp-admin" . '/includes/media.php');

			//Fetch me the video data.
			$ytvids = json_decode(wp_remote_retrieve_body(wp_remote_get('http://gdata.youtube.com/feeds/api/users/'.$options['youtubeuser'].'/uploads?v=2&alt=json')));
			if ( !is_wp_error($ytvids) ) {
				//Grab all of the current youtube video posts
				$args = array(
					'post_type' => 'youtube',
					'numberposts' => -1,
				);
				$current_vids = get_posts( $args );
				//Grab just the video ID meta for these posts.
				$vidstoskip = array();
				foreach ( $current_vids as $iamreal ) {
					//Grab all existing video IDs from meta data
					if ( get_post_meta( $iamreal->ID, '_easyyoutube_video_id', true ) != '' ) {
						$vidstoskip[] = get_post_meta( $iamreal->ID, '_easyyoutube_video_id', true );
					}
				}
				//Lets get the show going.
				foreach($ytvids->feed->entry as $vid) {
					//Move on to the next video if we already have a post with this meta value, cause Stone Cold said so.
					if ( in_array( $vid->{'media$group'}->{'yt$videoid'}->{'$t'}, $vidstoskip ) )
						continue;

					//set up args for basic post info
					$args = array(
						'post_title' => $vid->title->{'$t'},
						'post_content' => $vid->{'media$group'}->{'media$description'}->{'$t'},
						'post_status' => 'publish',
						'post_type' => 'youtube',
						'post_name' => sanitize_title( $vid->title->{'$t'} )
					);
					//insert post
					$pid = wp_insert_post($args);

					//check for post views
					if ( isset( $vid->{'yt$statistics'}->viewCount ) ) {
						$views = $vid->{'yt$statistics'}->viewCount;
					} else {
						$views = '0';
					}
					//grab the big image
					foreach( $vid->{'media$group'}->{'media$thumbnail'} as $thumbs ) {
						if ( $thumbs->{'yt$name'} == 'hqdefault' ) {
							$featimg = esc_url($thumbs->url);
							$tmp = download_url($featimg);
							$file_array = array(
								'name' => basename( $featimg ),
								'tmp_name' => $tmp
							);
						}
					}
					//set up metadata
					$meta = array(
						'_easyyoutube_video_id' => esc_html($vid->{'media$group'}->{'yt$videoid'}->{'$t'}),
						'_easyyoutube_video_views' => esc_html($views),
					);

					// Check for download errors
					if ( is_wp_error( $tmp ) ) {
						@unlink( $file_array[ 'tmp_name' ] );
					}
					//retrieve ID
					$img_id = media_handle_sideload( $file_array, $pid );
					// Check for handle sideload errors.
					if ( is_wp_error( $img_id ) ) {
						@unlink( $file_array['tmp_name'] );
					}
					//use ID to set post thumb
					set_post_thumbnail( $pid, $img_id );
					//set meta fields
					foreach( $meta as $key => $value ) {
						update_post_meta( $pid, $key, $value );
					}
					//Set video tags
					$tags = array();
					foreach ($vid->{'media$group'}->{'media$category'} as $cat ) {
						$tags[] = strtolower( $cat->{'$t'} );
					}
					wp_set_object_terms( $pid, $tags, 'videotags' );

				} //End video foreach
			} //End have videos check
		}

		/**
		 * Check for scheduled cron and schedule if there is none.
		 * @since 1.0
		 */
		function easyyoutube_schedule_cron() {
			if ( !wp_next_scheduled( 'easyyoutube_cron_import' ) ) {
				wp_schedule_event( time(), 'weekly', 'easyyoutube_cron_import' );
			}
		}

		/**
		 * Initialte the plugin settings
		 * @since 1.0
		 */
		function easyyoutube_plugin_options_init(){
			register_setting( 'easyyoutube_options_fields', 'easyyoutube_options', 'easyyoutube_options_validate' );
		}

		/**
		 * Add our plugin settings page to the menu
		 * @since 1.0
		 */
		function easyyoutube_plugin_options_add_page() {
			global $easyyoutube_hook;
			$easyyoutube_hook = add_options_page('EasyYoutube Import Options', 'EasyYoutube Import Options', 'manage_options', 'easyyoutube_options', 'easyyoutube_plugin_do_page');
		}

		/**
		 * Render our plugin settings page
		 * @since 1.0
		 */
		function easyyoutube_plugin_do_page() { ?>
			<div class="wrap">
				<?php screen_icon();?>
				<h2>EasyYoutube Importer Options</h2>

				<table class="form-table">
					<tr valign="top"><th scope="row">Manually update youtube video posts?</th>
						<td>
							<form id="easyyoutube_import" method="post" action="">
								<input id="easyyoutube_refresh" class="button-secondary" name="easyyoutube_refresh" type="submit" value="Refresh" />
								<img src="<?php echo admin_url('/images/wpspin_light.gif'); ?>" class="waiting" id="easyyoutube_loading" style="display: none;" />
								<div id="easyyoutube_results"></div>
							</form>
						</td>
					</tr>
				</table>

				<form id="easyyoutube_import_settings" method="post" action="options.php">
				<?php settings_fields('easyyoutube_options_fields');
				$options = get_option('easyyoutube_options'); ?>

				<table class="form-table">
					<tr valign="top"><th scope="row">Youtube user name</th>
						<td>
							<input class="regular-text" id="easyyoutube_options[youtubeuser]" name="easyyoutube_options[youtubeuser]" type="text" value="<?php echo $options['youtubeuser']; ?>" placeholder="http://www.youtube.com/user/USERNAME" />
						</td>
					</tr>
				</table>
				<input id="easyyoutube_yt_save" class="button-primary" name="easyyoutube_yt_save" type="submit" value="Save Settings" />
				</form>
			</div>
		<?php
		}

		/**
		 * Validate our options settings
		 * @param  array $input options needing sanitized and validated
		 * @return array        sanitized options
		 */
		function easyyoutube_options_validate( $input ) {
			$options = get_option('easyyoutube_options');

			//only one at the moment.
			$options['youtubeuser'] = wp_filter_nohtml_kses( $input['youtubeuser'] );
			$options['facebook'] = esc_url( $input['facebook'] );
			$options['twitter'] = esc_url( $input['twitter'] );
			$options['bylinetext'] = wp_filter_nohtml_kses( $input['bylinetext'] );
			return $options;
		}

		/**
		 * Load and localize our js script files for use in ajax
		 * @param  string $hook hook used to see if we need to continue. Why run needlessly
		 * @since 1.0
		 */
		function easyyoutube_load_scripts($hook) {
			global $easyyoutube_hook;

			// Dr. Frederick Frankenstein: [to Inga from behind the bookcase] Put... the candle... back!
			if ( $hook != $easyyoutube_hook )
				return;

			wp_enqueue_script( 'easyyoutube-ajax', plugin_dir_url( __FILE__ ) . '/js/easyyoutube-ajax.js', array( 'jquery' ));
			wp_localize_script( 'easyyoutube-ajax', 'easyyoutube_vars', array( 'easyyoutube_nonce' => wp_create_nonce( 'easyyoutube_nonce' ) ) );
		}

		/**
		 * Process our ajax request, aka run our video channel feed update. ??? Profit!
		 * @since 1.0
		 */
		function easyyoutube_process_ajax() {
			get_currentuserinfo();

			$name = ( !empty( $current_user->user_firstname ) ) ? $current_user->user_firstname : $current_user->user_login;
			if ( ! isset( $_POST['easyyoutube_nonce']) || ! wp_verify_nonce( $_POST['easyyoutube_nonce'], 'easyyoutube_nonce' ) )
				die("I'm sorry, $name, I'm afraid I can't do that.");

			//Screw it, we'll do it in ajax!
			$this->easyyoutube_userchannel_videos();

			echo '<p>Youtube videos imported successfully</p>';

			die();
		}

		/**
		 * Add "weekly" to cron schedule array
		 * @since 1.0
		 */
		function easyyoutube_cron_add_weekly( $schedules ) {
			$schedules['weekly'] = array(
				'interval' => 604800,
				'display' => __( 'Once Weekly' )
			);
			return $schedules;
		}

		/**
		 * Clear scheduled cronjobs on deactivation
		 * @since 1.0
		 */
		function easyyoutube_deactivation() {
			wp_clear_scheduled_hook('easyyoutube_cron_import');
			wp_clear_scheduled_hook('easyyoutube_update_cron_hook');
		}

		/**
		 * Flush rewrite rules on activation and deactivation of plugin
		 * @since 1.0
		 */
		function flush_rewrite_rules() {
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		}
	}
}
/*
Dr. Frederick Frankenstein: Throw... the third switch!
Igor: [shocked] Not the *third switch*!
 */
$wds_EasyYoutube = new WDS_EasyYoutube();

/**
 * Class to create our widget
 */
class WDS_EasyYoutube_Widget extends WP_Widget {

	/**
	 * Create our widget
	 * @return [type] [description]
	 */
	function easyyoutube_widget() {
		/* Widget settings. */
		$widget_ops = array( 'classname' => 'easyyoutube_widget', 'description' => __( "EasyYoutube video display widget" ) );

		/* Create the widget. */
		$this->WP_Widget( 'easyyoutube_widget', __( "Brand Connection Big Button Widget" ), $widget_ops );
    }

	function form( $instance ) {

	}

	function update( $new_instance, $old_instance ) {
		$instance['thetitle'] = $new_instance['thetitle'];

		return $instance;
	}

	function widget($args, $instance) {
		extract( $args );

		if(!$instance)
			return;
	}

}
