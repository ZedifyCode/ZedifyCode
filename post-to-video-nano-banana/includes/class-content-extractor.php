<?php

namespace Post_To_Video_Nano_Banana;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Content_Extractor {
	public static function extract( \\WP_Post $post, int $max_images ): array {
		$title = $post->post_title;
		$excerpt = $post->post_excerpt;
		$content = $post->post_content;

		$clean_content = strip_shortcodes( $content );
		$clean_content = wp_strip_all_tags( $clean_content, true );
		$clean_content = trim( preg_replace( '/\s+/', ' ', $clean_content ) );

		if ( empty( $excerpt ) ) {
			$excerpt = mb_substr( $clean_content, 0, 200 );
		}

		$featured_image = get_the_post_thumbnail_url( $post, 'full' );
		$inline_images = self::extract_images_from_content( $content, $max_images );

		return [
			'title'          => $title,
			'excerpt'        => $excerpt,
			'content'        => $clean_content,
			'featured_image' => $featured_image,
			'images'         => $inline_images,
		];
	}

	private static function extract_images_from_content( string $content, int $max_images ): array {
		$images = [];
		preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches );
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $src ) {
				$images[] = esc_url_raw( $src );
				if ( count( $images ) >= $max_images ) {
					break;
				}
			}
		}

		return $images;
	}
}
