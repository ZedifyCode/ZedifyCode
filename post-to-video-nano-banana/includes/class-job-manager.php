<?php

namespace Post_To_Video_Nano_Banana;

use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Job_Manager {
	private static ?Job_Manager $instance = null;

	public static function get_instance(): Job_Manager {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'nb_video_poll_job', [ $this, 'poll_job' ], 10, 1 );
		add_action( 'rest_api_init', [ $this, 'register_webhook_endpoint' ] );
		add_action( 'transition_post_status', [ $this, 'maybe_auto_generate' ], 10, 3 );
	}

	public function create_job( int $post_id, bool $force = false, bool $silent = false ): array {
		if ( ! $this->check_rate_limit() ) {
			return [ 'success' => false, 'message' => __( 'Rate limit exceeded.', 'post-to-video-nano-banana' ) ];
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return [ 'success' => false, 'message' => __( 'Post not found.', 'post-to-video-nano-banana' ) ];
		}

		$options = Settings::get_instance()->get_options();
		$payload = $this->build_payload( $post, $options );
		$hash    = md5( wp_json_encode( $payload ) );

		if ( ! $force ) {
			$existing_hash = get_post_meta( $post_id, '_nb_video_payload_hash', true );
			if ( $existing_hash && $existing_hash === $hash ) {
				return [ 'success' => false, 'message' => __( 'No changes detected since last request.', 'post-to-video-nano-banana' ) ];
			}
		}

		$client = new NanoBanana_Client();
		$response = $client->create_video_job( $payload );

		if ( ! $response['success'] ) {
			Logger::add( $post_id, 'API request failed', [ 'error' => $response['error'] ?? 'Unknown error' ] );
			update_post_meta( $post_id, '_nb_video_status', 'Failed' );
			update_post_meta( $post_id, '_nb_video_last_error', $response['error'] ?? 'Unknown error' );
			return [ 'success' => false, 'message' => __( 'Failed to create job.', 'post-to-video-nano-banana' ) ];
		}

		$job_id = $response['body']['job_id'] ?? '';
		$status = $response['body']['status'] ?? 'Queued';

		update_post_meta( $post_id, '_nb_video_status', $status );
		update_post_meta( $post_id, '_nb_video_job_id', sanitize_text_field( $job_id ) );
		update_post_meta( $post_id, '_nb_video_payload_hash', $hash );
		update_post_meta( $post_id, '_nb_video_created_at', current_time( 'mysql' ) );

		Logger::add( $post_id, 'Job created', [ 'job_id' => $job_id, 'status' => $status ] );

		$this->schedule_poll( $post_id );

		return [ 'success' => true, 'message' => $silent ? __( 'Job queued.', 'post-to-video-nano-banana' ) : __( 'Video job queued.', 'post-to-video-nano-banana' ) ];
	}

	public function poll_job( int $post_id ): void {
		$job_id = get_post_meta( $post_id, '_nb_video_job_id', true );
		if ( ! $job_id ) {
			return;
		}

		$client = new NanoBanana_Client();
		$response = $client->get_video_status( $job_id );
		if ( ! $response['success'] ) {
			Logger::add( $post_id, 'Status check failed', [ 'error' => $response['error'] ?? 'Unknown error' ] );
			return;
		}

		$status = $response['body']['status'] ?? 'processing';
		update_post_meta( $post_id, '_nb_video_status', ucfirst( $status ) );
		Logger::add( $post_id, 'Status update', [ 'status' => $status ] );

		if ( in_array( $status, [ 'completed', 'failed' ], true ) ) {
			if ( 'completed' === $status ) {
				$this->handle_completion( $post_id, $response['body'] );
			} else {
				update_post_meta( $post_id, '_nb_video_last_error', $response['body']['error'] ?? 'Unknown error' );
			}
			return;
		}

		$this->schedule_poll( $post_id );
	}

	public function cancel_job( int $post_id ): array {
		$job_id = get_post_meta( $post_id, '_nb_video_job_id', true );
		if ( ! $job_id ) {
			return [ 'success' => false, 'message' => __( 'No job to cancel.', 'post-to-video-nano-banana' ) ];
		}

		$client = new NanoBanana_Client();
		$response = $client->cancel_video_job( $job_id );
		if ( ! $response['success'] ) {
			Logger::add( $post_id, 'Cancel failed', [ 'error' => $response['error'] ?? 'Unknown error' ] );
			return [ 'success' => false, 'message' => __( 'Failed to cancel job.', 'post-to-video-nano-banana' ) ];
		}

		update_post_meta( $post_id, '_nb_video_status', 'Cancelled' );
		Logger::add( $post_id, 'Job cancelled', [ 'job_id' => $job_id ] );

		return [ 'success' => true, 'message' => __( 'Job cancelled.', 'post-to-video-nano-banana' ) ];
	}

	public function register_webhook_endpoint(): void {
		register_rest_route(
			'post-to-video/v1',
			'/webhook',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_webhook' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function handle_webhook( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$options = Settings::get_instance()->get_options();
		$secret  = $options['webhook_secret'] ?? '';
		$signature = $request->get_header( 'x-nb-signature' );

		if ( $secret ) {
			$expected = hash_hmac( 'sha256', wp_json_encode( $payload ), $secret );
			if ( ! hash_equals( $expected, $signature ) ) {
				return new \WP_REST_Response( [ 'message' => 'Invalid signature' ], 403 );
			}
		}

		$post_id = absint( $payload['post_id'] ?? 0 );
		if ( ! $post_id ) {
			return new \WP_REST_Response( [ 'message' => 'Missing post ID' ], 400 );
		}

		$status = $payload['status'] ?? '';
		if ( $status ) {
			update_post_meta( $post_id, '_nb_video_status', ucfirst( $status ) );
		}

		if ( 'completed' === $status ) {
			$this->handle_completion( $post_id, $payload );
		}

		return new \WP_REST_Response( [ 'message' => 'OK' ], 200 );
	}

	private function handle_completion( int $post_id, array $payload ): void {
		$video_url = $payload['video_url'] ?? $payload['file_url'] ?? '';
		if ( ! $video_url ) {
			Logger::add( $post_id, 'Completed without video URL' );
			return;
		}

		update_post_meta( $post_id, '_nb_video_url', esc_url_raw( $video_url ) );

		$options = Settings::get_instance()->get_options();
		if ( 'yes' === $options['save_locally'] ) {
			$media_handler = new Media_Handler();
			$result = $media_handler->sideload_video( $video_url, $post_id );
			if ( $result['success'] ) {
				update_post_meta( $post_id, '_nb_video_media_id', $result['media_id'] );
			} else {
				Logger::add( $post_id, 'Failed to sideload video', [ 'error' => $result['error'] ] );
			}
		}

		update_post_meta( $post_id, '_nb_video_status', 'Completed' );
		Logger::add( $post_id, 'Video completed', [ 'video_url' => $video_url ] );
	}

	private function build_payload( \\WP_Post $post, array $options ): array {
		$max_images = (int) $options['max_images'];
		$content = Content_Extractor::extract( $post, $max_images );
		$overrides = [
			'style' => get_post_meta( $post->ID, '_nb_video_style', true ),
			'voice' => get_post_meta( $post->ID, '_nb_video_voice', true ),
			'duration' => get_post_meta( $post->ID, '_nb_video_duration', true ),
			'aspect_ratio' => get_post_meta( $post->ID, '_nb_video_aspect_ratio', true ),
			'captions' => get_post_meta( $post->ID, '_nb_video_captions', true ),
			'script_length' => get_post_meta( $post->ID, '_nb_video_script_length', true ),
		];

		$duration = $overrides['duration'] ? absint( $overrides['duration'] ) : (int) $options['default_duration'];
		$captions = $overrides['captions'] ? $overrides['captions'] : $options['default_captions'];

		$script = $this->build_script( $content['title'], $content['excerpt'], $content['content'], $duration );

		$payload = [
			'post_id'      => $post->ID,
			'title'        => $content['title'],
			'script'       => $script,
			'images'       => array_values( array_filter( array_merge( [ $content['featured_image'] ], $content['images'] ) ) ),
			'voice'        => $overrides['voice'] ?: $options['default_voice'],
			'language'     => $overrides['voice'] ?: $options['default_voice'],
			'duration'     => $duration,
			'aspect_ratio' => $overrides['aspect_ratio'] ?: $options['default_aspect_ratio'],
			'captions'     => 'yes' === $captions,
			'style_preset' => $overrides['style'] ?: $options['default_style_preset'],
			'watermark'    => $options['default_watermark'],
			'script_length' => $overrides['script_length'] ?: 'medium',
		];

		return $payload;
	}

	private function build_script( string $title, string $excerpt, string $content, int $duration ): string {
		$words_per_second = 2.2;
		$target_words = max( 40, (int) ( $duration * $words_per_second ) );

		$base_text = trim( $title . ' ' . $excerpt . ' ' . $content );
		$words = preg_split( '/\s+/', $base_text );
		$script_words = array_slice( $words, 0, $target_words );

		return trim( implode( ' ', $script_words ) );
	}

	private function schedule_poll( int $post_id ): void {
		if ( ! wp_next_scheduled( 'nb_video_poll_job', [ $post_id ] ) ) {
			wp_schedule_single_event( time() + 300, 'nb_video_poll_job', [ $post_id ] );
		}
	}

	private function check_rate_limit(): bool {
		$options = Settings::get_instance()->get_options();
		$limit = (int) $options['jobs_per_hour'];
		$counter = get_transient( 'nb_video_jobs_count' );
		if ( false === $counter ) {
			set_transient( 'nb_video_jobs_count', 1, HOUR_IN_SECONDS );
			return true;
		}
		if ( (int) $counter >= $limit ) {
			return false;
		}

		set_transient( 'nb_video_jobs_count', (int) $counter + 1, HOUR_IN_SECONDS );
		return true;
	}

	public function maybe_auto_generate( string $new_status, string $old_status, \\WP_Post $post ): void {
		if ( 'publish' !== $new_status || $old_status === $new_status ) {
			return;
		}
		$options = Settings::get_instance()->get_options();
		if ( 'yes' !== $options['auto_generate_on_publish'] ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$this->create_job( $post->ID, false, true );
	}
}
