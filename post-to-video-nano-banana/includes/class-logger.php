<?php

namespace Post_To_Video_Nano_Banana;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Logger {
	private const META_KEY = '_nb_video_logs';
	private const MAX_ENTRIES = 50;

	public static function add( int $post_id, string $message, array $context = [] ): void {
		$logs = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $logs ) ) {
			$logs = [];
		}

		$entry = [
			'timestamp' => current_time( 'mysql' ),
			'message'   => sanitize_text_field( $message ),
			'context'   => array_map( 'sanitize_text_field', $context ),
		];

		$logs[] = $entry;
		$logs   = array_slice( $logs, -1 * self::MAX_ENTRIES );

		update_post_meta( $post_id, self::META_KEY, $logs );
	}

	public static function get_recent( int $post_id, int $count = 10 ): array {
		$logs = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $logs ) ) {
			return [];
		}

		return array_slice( $logs, -1 * $count );
	}
}
