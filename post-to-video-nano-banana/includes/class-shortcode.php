<?php

namespace Post_To_Video_Nano_Banana;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcode {
	private static ?Shortcode $instance = null;

	public static function get_instance(): Shortcode {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'nb_video', [ $this, 'render_shortcode' ] );
	}

	public function render_shortcode( array $atts = [] ): string {
		$atts = shortcode_atts(
			[ 'post_id' => get_the_ID() ],
			$atts,
			'nb_video'
		);

		$post_id = absint( $atts['post_id'] );
		if ( ! $post_id ) {
			return '';
		}

		$video_url = get_post_meta( $post_id, '_nb_video_url', true );
		if ( ! $video_url ) {
			return '';
		}

		return sprintf(
			'<video controls src="%s"></video>',
			esc_url( $video_url )
		);
	}
}
