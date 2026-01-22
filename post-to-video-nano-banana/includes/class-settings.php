<?php

namespace Post_To_Video_Nano_Banana;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {
	private static ?Settings $instance = null;
	private const OPTION_KEY = 'nb_video_settings';

	private array $defaults = [
		'api_base_url'         => 'https://api.nanobanana.example',
		'api_key'              => '',
		'default_style_preset'  => 'cinematic',
		'default_voice'         => 'en-US',
		'default_duration'      => 30,
		'default_aspect_ratio'  => '16:9',
		'default_captions'      => 'yes',
		'default_watermark'     => '',
		'webhook_secret'        => '',
		'save_locally'          => 'yes',
		'auto_generate_on_publish' => 'no',
		'max_images'            => 5,
		'jobs_per_hour'         => 10,
	];

	public static function get_instance(): Settings {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function maybe_set_default_options(): void {
		$defaults = self::get_instance()->defaults;
		$options  = get_option( self::OPTION_KEY );
		if ( false === $options ) {
			add_option( self::OPTION_KEY, $defaults );
		}
	}

	public function get_options(): array {
		$options = get_option( self::OPTION_KEY, [] );
		return wp_parse_args( $options, $this->defaults );
	}

	public function register_settings(): void {
		register_setting(
			'nb_video_settings',
			self::OPTION_KEY,
			[ $this, 'sanitize_settings' ]
		);

		add_settings_section(
			'nb_video_main',
			__( 'Nano Banana Settings', 'post-to-video-nano-banana' ),
			'__return_null',
			'nb_video_settings'
		);

		$this->add_field( 'api_base_url', __( 'Nano Banana API Base URL', 'post-to-video-nano-banana' ) );
		$this->add_field( 'api_key', __( 'API Key', 'post-to-video-nano-banana' ), 'password' );
		$this->add_field( 'default_style_preset', __( 'Default video style preset', 'post-to-video-nano-banana' ), 'select', [
			'cinematic' => __( 'Cinematic', 'post-to-video-nano-banana' ),
			'modern'    => __( 'Modern', 'post-to-video-nano-banana' ),
			'documentary' => __( 'Documentary', 'post-to-video-nano-banana' ),
		] );
		$this->add_field( 'default_voice', __( 'Default voice/language', 'post-to-video-nano-banana' ), 'select', [
			'en-US' => __( 'English (US)', 'post-to-video-nano-banana' ),
			'en-GB' => __( 'English (UK)', 'post-to-video-nano-banana' ),
			'es-ES' => __( 'Spanish (ES)', 'post-to-video-nano-banana' ),
		] );
		$this->add_field( 'default_duration', __( 'Default duration (seconds)', 'post-to-video-nano-banana' ), 'number' );
		$this->add_field( 'default_aspect_ratio', __( 'Default aspect ratio', 'post-to-video-nano-banana' ), 'select', [
			'16:9' => '16:9',
			'9:16' => '9:16',
			'1:1'  => '1:1',
		] );
		$this->add_field( 'default_captions', __( 'Default captions', 'post-to-video-nano-banana' ), 'select', [
			'yes' => __( 'On', 'post-to-video-nano-banana' ),
			'no'  => __( 'Off', 'post-to-video-nano-banana' ),
		] );
		$this->add_field( 'default_watermark', __( 'Default branding watermark (optional)', 'post-to-video-nano-banana' ) );
		$this->add_field( 'webhook_secret', __( 'Callback/Webhook secret (optional)', 'post-to-video-nano-banana' ), 'password' );
		$this->add_field( 'save_locally', __( 'Save videos locally', 'post-to-video-nano-banana' ), 'select', [
			'yes' => __( 'Yes', 'post-to-video-nano-banana' ),
			'no'  => __( 'No', 'post-to-video-nano-banana' ),
		] );
		$this->add_field( 'auto_generate_on_publish', __( 'Auto-generate on publish', 'post-to-video-nano-banana' ), 'select', [
			'yes' => __( 'Yes', 'post-to-video-nano-banana' ),
			'no'  => __( 'No', 'post-to-video-nano-banana' ),
		] );
		$this->add_field( 'max_images', __( 'Maximum inline images', 'post-to-video-nano-banana' ), 'number' );
		$this->add_field( 'jobs_per_hour', __( 'Max jobs per hour', 'post-to-video-nano-banana' ), 'number' );
	}

	public function sanitize_settings( array $input ): array {
		$sanitized = [];
		$sanitized['api_base_url'] = esc_url_raw( $input['api_base_url'] ?? '' );
		$sanitized['api_key'] = sanitize_text_field( $input['api_key'] ?? '' );
		$sanitized['default_style_preset'] = sanitize_text_field( $input['default_style_preset'] ?? $this->defaults['default_style_preset'] );
		$sanitized['default_voice'] = sanitize_text_field( $input['default_voice'] ?? $this->defaults['default_voice'] );
		$sanitized['default_duration'] = absint( $input['default_duration'] ?? $this->defaults['default_duration'] );
		$sanitized['default_aspect_ratio'] = sanitize_text_field( $input['default_aspect_ratio'] ?? $this->defaults['default_aspect_ratio'] );
		$sanitized['default_captions'] = in_array( $input['default_captions'] ?? 'no', [ 'yes', 'no' ], true ) ? $input['default_captions'] : 'no';
		$sanitized['default_watermark'] = sanitize_text_field( $input['default_watermark'] ?? '' );
		$sanitized['webhook_secret'] = sanitize_text_field( $input['webhook_secret'] ?? '' );
		$sanitized['save_locally'] = in_array( $input['save_locally'] ?? 'yes', [ 'yes', 'no' ], true ) ? $input['save_locally'] : 'yes';
		$sanitized['auto_generate_on_publish'] = in_array( $input['auto_generate_on_publish'] ?? 'no', [ 'yes', 'no' ], true ) ? $input['auto_generate_on_publish'] : 'no';
		$sanitized['max_images'] = max( 0, absint( $input['max_images'] ?? $this->defaults['max_images'] ) );
		$sanitized['jobs_per_hour'] = max( 1, absint( $input['jobs_per_hour'] ?? $this->defaults['jobs_per_hour'] ) );

		return $sanitized;
	}

	private function add_field( string $key, string $label, string $type = 'text', array $options = [] ): void {
		add_settings_field(
			$key,
			$label,
			function () use ( $key, $type, $options ) {
				$settings = $this->get_options();
				$value    = $settings[ $key ] ?? '';

				if ( 'select' === $type ) {
					echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']">';
					foreach ( $options as $option_value => $label ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $option_value ),
							selected( $value, $option_value, false ),
							esc_html( $label )
						);
					}
					echo '</select>';
					return;
				}

				$type_attr = in_array( $type, [ 'password', 'number', 'text' ], true ) ? $type : 'text';
				echo '<input type="' . esc_attr( $type_attr ) . '" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" class="regular-text" />';
			},
			'nb_video_settings',
			'nb_video_main'
		);
	}
}
