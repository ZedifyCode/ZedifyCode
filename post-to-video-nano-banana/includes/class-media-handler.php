<?php

namespace Post_To_Video_Nano_Banana;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Media_Handler {
	public function sideload_video( string $file_url, int $post_id ): array {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $file_url );
		if ( is_wp_error( $tmp ) ) {
			return [ 'success' => false, 'error' => $tmp->get_error_message() ];
		}

		$file_array = [
			'name'     => basename( $file_url ),
			'tmp_name' => $tmp,
		];

		$media_id = media_handle_sideload( $file_array, $post_id );
		if ( is_wp_error( $media_id ) ) {
			@unlink( $tmp );
			return [ 'success' => false, 'error' => $media_id->get_error_message() ];
		}

		return [ 'success' => true, 'media_id' => $media_id ];
	}
}
