<?php

namespace Post_To_Video_Nano_Banana;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NanoBanana_Client {
	private string $base_url;
	private string $api_key;

	public function __construct() {
		$options       = Settings::get_instance()->get_options();
		$this->base_url = rtrim( $options['api_base_url'], '/' );
		$this->api_key  = $options['api_key'];
	}

	public function test_connection(): array {
		$response = wp_remote_get(
			$this->base_url . '/status',
			[ 'headers' => $this->get_headers() ]
		);
		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'message' => $response->get_error_message() ];
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status >= 200 && $status < 300 ) {
			return [ 'success' => true, 'message' => 'OK' ];
		}

		return [ 'success' => false, 'message' => 'Unexpected response: ' . $status ];
	}

	public function create_video_job( array $payload ): array {
		$response = wp_remote_post(
			$this->base_url . '/videos',
			[
				'headers' => $this->get_headers(),
				'timeout' => 30,
				'body'    => wp_json_encode( $payload ),
			]
		);

		return $this->parse_response( $response );
	}

	public function get_video_status( string $job_id ): array {
		$response = wp_remote_get(
			$this->base_url . '/videos/' . rawurlencode( $job_id ),
			[ 'headers' => $this->get_headers() ]
		);

		return $this->parse_response( $response );
	}

	public function cancel_video_job( string $job_id ): array {
		$response = wp_remote_post(
			$this->base_url . '/videos/' . rawurlencode( $job_id ) . '/cancel',
			[ 'headers' => $this->get_headers() ]
		);

		return $this->parse_response( $response );
	}

	private function get_headers(): array {
		return [
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		];
	}

	private function parse_response( $response ): array {
		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			$decoded = [];
		}

		return [
			'success'    => $status_code >= 200 && $status_code < 300,
			'status_code' => $status_code,
			'body'       => $decoded,
			'raw_body'   => $body,
		];
	}
}
