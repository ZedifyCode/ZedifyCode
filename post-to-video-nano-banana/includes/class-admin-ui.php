<?php

namespace Post_To_Video_Nano_Banana;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_UI {
	private static ?Admin_UI $instance = null;

	public static function get_instance(): Admin_UI {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
		add_action( 'admin_init', [ Settings::get_instance(), 'register_settings' ] );
		add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
		add_action( 'save_post', [ $this, 'save_meta_box' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_nb_video_test_connection', [ $this, 'handle_test_connection' ] );
		add_action( 'wp_ajax_nb_video_generate', [ $this, 'handle_generate' ] );
		add_action( 'wp_ajax_nb_video_cancel', [ $this, 'handle_cancel' ] );
		add_filter( 'bulk_actions-edit-post', [ $this, 'register_bulk_action' ] );
		add_filter( 'handle_bulk_actions-edit-post', [ $this, 'handle_bulk_action' ], 10, 3 );
		add_action( 'admin_notices', [ $this, 'bulk_action_notice' ] );
	}

	public function register_settings_page(): void {
		add_options_page(
			__( 'Post-to-Video', 'post-to-video-nano-banana' ),
			__( 'Post-to-Video', 'post-to-video-nano-banana' ),
			'manage_options',
			'nb-video-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Post-to-Video (Nano Banana)', 'post-to-video-nano-banana' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'nb_video_settings' );
				do_settings_sections( 'nb_video_settings' );
				submit_button();
				?>
			</form>
			<button type="button" class="button" id="nb-video-test-connection" data-nonce="<?php echo esc_attr( wp_create_nonce( 'nb_video_test_connection' ) ); ?>">
				<?php esc_html_e( 'Test Connection', 'post-to-video-nano-banana' ); ?>
			</button>
			<div id="nb-video-test-result"></div>
		</div>
		<?php
	}

	public function register_meta_box(): void {
		$post_types = get_post_types( [ 'public' => true ], 'names' );
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'nb-video-meta',
				__( 'Post-to-Video', 'post-to-video-nano-banana' ),
				[ $this, 'render_meta_box' ],
				$post_type,
				'side',
				'default'
			);
		}
	}

	public function render_meta_box( \\WP_Post $post ): void {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		$settings = Settings::get_instance()->get_options();
		$status   = get_post_meta( $post->ID, '_nb_video_status', true );
		$status   = $status ?: __( 'Not started', 'post-to-video-nano-banana' );
		$video_url = get_post_meta( $post->ID, '_nb_video_url', true );
		$logs = Logger::get_recent( $post->ID );
		$override = [
			'style'   => get_post_meta( $post->ID, '_nb_video_style', true ),
			'voice'   => get_post_meta( $post->ID, '_nb_video_voice', true ),
			'duration' => get_post_meta( $post->ID, '_nb_video_duration', true ),
			'aspect_ratio' => get_post_meta( $post->ID, '_nb_video_aspect_ratio', true ),
			'captions' => get_post_meta( $post->ID, '_nb_video_captions', true ),
			'script_length' => get_post_meta( $post->ID, '_nb_video_script_length', true ),
		];
		wp_nonce_field( 'nb_video_meta_box', 'nb_video_meta_box_nonce' );
		?>
		<p><strong><?php esc_html_e( 'Current status:', 'post-to-video-nano-banana' ); ?></strong> <?php echo esc_html( $status ); ?></p>
		<p>
			<button type="button" class="button nb-video-generate" data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-action="generate" data-nonce="<?php echo esc_attr( wp_create_nonce( 'nb_video_generate' ) ); ?>">
				<?php esc_html_e( 'Generate Video', 'post-to-video-nano-banana' ); ?>
			</button>
			<button type="button" class="button nb-video-generate" data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-action="regenerate" data-nonce="<?php echo esc_attr( wp_create_nonce( 'nb_video_generate' ) ); ?>">
				<?php esc_html_e( 'Regenerate Video', 'post-to-video-nano-banana' ); ?>
			</button>
			<button type="button" class="button nb-video-cancel" data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'nb_video_cancel' ) ); ?>">
				<?php esc_html_e( 'Cancel Job', 'post-to-video-nano-banana' ); ?>
			</button>
		</p>
		<?php if ( $video_url ) : ?>
			<p><a href="<?php echo esc_url( $video_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View generated video', 'post-to-video-nano-banana' ); ?></a></p>
		<?php endif; ?>
		<p><strong><?php esc_html_e( 'Overrides', 'post-to-video-nano-banana' ); ?></strong></p>
		<p>
			<label>
				<?php esc_html_e( 'Style preset', 'post-to-video-nano-banana' ); ?>
				<input type="text" name="nb_video_style" value="<?php echo esc_attr( $override['style'] ); ?>" placeholder="<?php echo esc_attr( $settings['default_style_preset'] ); ?>" />
			</label>
		</p>
		<p>
			<label>
				<?php esc_html_e( 'Voice/language', 'post-to-video-nano-banana' ); ?>
				<input type="text" name="nb_video_voice" value="<?php echo esc_attr( $override['voice'] ); ?>" placeholder="<?php echo esc_attr( $settings['default_voice'] ); ?>" />
			</label>
		</p>
		<p>
			<label>
				<?php esc_html_e( 'Duration (seconds)', 'post-to-video-nano-banana' ); ?>
				<input type="number" name="nb_video_duration" value="<?php echo esc_attr( $override['duration'] ); ?>" placeholder="<?php echo esc_attr( $settings['default_duration'] ); ?>" />
			</label>
		</p>
		<p>
			<label>
				<?php esc_html_e( 'Aspect ratio', 'post-to-video-nano-banana' ); ?>
				<input type="text" name="nb_video_aspect_ratio" value="<?php echo esc_attr( $override['aspect_ratio'] ); ?>" placeholder="<?php echo esc_attr( $settings['default_aspect_ratio'] ); ?>" />
			</label>
		</p>
		<p>
			<label>
				<?php esc_html_e( 'Captions (yes/no)', 'post-to-video-nano-banana' ); ?>
				<input type="text" name="nb_video_captions" value="<?php echo esc_attr( $override['captions'] ); ?>" placeholder="<?php echo esc_attr( $settings['default_captions'] ); ?>" />
			</label>
		</p>
		<p>
			<label>
				<?php esc_html_e( 'Script length (short/medium/long)', 'post-to-video-nano-banana' ); ?>
				<input type="text" name="nb_video_script_length" value="<?php echo esc_attr( $override['script_length'] ); ?>" placeholder="medium" />
			</label>
		</p>
		<?php if ( ! empty( $logs ) ) : ?>
			<p><strong><?php esc_html_e( 'Recent log entries', 'post-to-video-nano-banana' ); ?></strong></p>
			<ul>
				<?php foreach ( $logs as $log ) : ?>
					<li><?php echo esc_html( $log['timestamp'] . ' - ' . $log['message'] ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<div class="nb-video-result"></div>
		<?php
	}

	public function save_meta_box( int $post_id ): void {
		if ( ! isset( $_POST['nb_video_meta_box_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nb_video_meta_box_nonce'] ) ), 'nb_video_meta_box' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = [
			'nb_video_style' => '_nb_video_style',
			'nb_video_voice' => '_nb_video_voice',
			'nb_video_duration' => '_nb_video_duration',
			'nb_video_aspect_ratio' => '_nb_video_aspect_ratio',
			'nb_video_captions' => '_nb_video_captions',
			'nb_video_script_length' => '_nb_video_script_length',
		];

		foreach ( $fields as $input_key => $meta_key ) {
			if ( isset( $_POST[ $input_key ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $input_key ] ) );
				if ( '' === $value ) {
					delete_post_meta( $post_id, $meta_key );
				} else {
					update_post_meta( $post_id, $meta_key, $value );
				}
			}
		}
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_nb-video-settings' !== $hook && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'nb-video-admin',
			NB_VIDEO_PLUGIN_URL . 'assets/admin.js',
			[ 'jquery' ],
			NB_VIDEO_PLUGIN_VERSION,
			true
		);
		wp_localize_script(
			'nb-video-admin',
			'NBVideoAdmin',
			[ 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ]
		);
	}

	public function handle_test_connection(): void {
		check_ajax_referer( 'nb_video_test_connection', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'post-to-video-nano-banana' ) ], 403 );
		}

		$client = new NanoBanana_Client();
		$result = $client->test_connection();
		if ( $result['success'] ) {
			wp_send_json_success( [ 'message' => __( 'Connection successful.', 'post-to-video-nano-banana' ) ] );
		}

		wp_send_json_error( [ 'message' => $result['message'] ] );
	}

	public function handle_generate(): void {
		check_ajax_referer( 'nb_video_generate', 'nonce' );
		$post_id = absint( $_POST['post_id'] ?? 0 );
		$action  = sanitize_text_field( wp_unslash( $_POST['action_type'] ?? '' ) );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'post-to-video-nano-banana' ) ], 403 );
		}

		$job_manager = Job_Manager::get_instance();
		$result      = $job_manager->create_job( $post_id, 'regenerate' === $action );

		if ( $result['success'] ) {
			wp_send_json_success( [ 'message' => $result['message'] ] );
		}

		wp_send_json_error( [ 'message' => $result['message'] ] );
	}

	public function handle_cancel(): void {
		check_ajax_referer( 'nb_video_cancel', 'nonce' );
		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'post-to-video-nano-banana' ) ], 403 );
		}

		$job_manager = Job_Manager::get_instance();
		$result      = $job_manager->cancel_job( $post_id );

		if ( $result['success'] ) {
			wp_send_json_success( [ 'message' => $result['message'] ] );
		}

		wp_send_json_error( [ 'message' => $result['message'] ] );
	}

	public function register_bulk_action( array $bulk_actions ): array {
		$bulk_actions['nb_video_generate'] = __( 'Generate Video (Nano Banana)', 'post-to-video-nano-banana' );
		return $bulk_actions;
	}

	public function handle_bulk_action( string $redirect_to, string $action, array $post_ids ): string {
		if ( 'nb_video_generate' !== $action ) {
			return $redirect_to;
		}

		$job_manager = Job_Manager::get_instance();
		$count       = 0;
		foreach ( $post_ids as $post_id ) {
			$result = $job_manager->create_job( (int) $post_id, false, true );
			if ( $result['success'] ) {
				$count++;
			}
		}

		$redirect_to = add_query_arg( 'nb_video_bulk', $count, $redirect_to );
		return $redirect_to;
	}

	public function bulk_action_notice(): void {
		if ( ! isset( $_GET['nb_video_bulk'] ) ) {
			return;
		}
		$count = absint( $_GET['nb_video_bulk'] );
		printf(
			'<div class="notice notice-success"><p>%s</p></div>',
			esc_html( sprintf( _n( '%d video job queued.', '%d video jobs queued.', $count, 'post-to-video-nano-banana' ), $count ) )
		);
	}
}
