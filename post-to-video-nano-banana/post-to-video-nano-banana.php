<?php
/**
 * Plugin Name: Post-to-Video (Nano Banana)
 * Description: Generate short videos from WordPress posts using the Nano Banana API.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: OpenAI
 * Text Domain: post-to-video-nano-banana
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NB_VIDEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

define( 'NB_VIDEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

define( 'NB_VIDEO_PLUGIN_VERSION', '1.0.0' );

require_once NB_VIDEO_PLUGIN_DIR . 'includes/class-logger.php';
require_once NB_VIDEO_PLUGIN_DIR . 'includes/class-settings.php';
require_once NB_VIDEO_PLUGIN_DIR . 'includes/class-client.php';
require_once NB_VIDEO_PLUGIN_DIR . 'includes/class-media-handler.php';
require_once NB_VIDEO_PLUGIN_DIR . 'includes/class-content-extractor.php';
require_once NB_VIDEO_PLUGIN_DIR . 'includes/class-job-manager.php';
require_once NB_VIDEO_PLUGIN_DIR . 'includes/class-admin-ui.php';
require_once NB_VIDEO_PLUGIN_DIR . 'includes/class-shortcode.php';

use Post_To_Video_Nano_Banana\Admin_UI;
use Post_To_Video_Nano_Banana\Job_Manager;
use Post_To_Video_Nano_Banana\Settings;
use Post_To_Video_Nano_Banana\Shortcode;

add_action(
	'plugins_loaded',
	static function () {
		Settings::get_instance();
		Job_Manager::get_instance();
		Admin_UI::get_instance();
		Shortcode::get_instance();
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		Settings::maybe_set_default_options();
	}
);
