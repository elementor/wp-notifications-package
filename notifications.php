<?php
namespace WPNotificationsPackage;

// Change here the Namespace

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Notifications {

	private string $app_name;

	private string $app_version;

	private string $transient_key;

	private string $api_endpoint = 'https://assets.elementor.com/notifications/v1/notifications.json';

	public function __construct( string $app_name, string $app_version ) {
		$this->app_name = sanitize_title( $app_name );
		$this->app_version = $app_version;

		$this->transient_key = "_{$this->app_name}_notifications";

		add_action( 'admin_init', [ $this, 'refresh_notifications' ] );
		add_filter( 'body_class', [ $this, 'add_body_class' ] );
	}

	public function refresh_notifications(): void {
		$this->get_notifications();
	}

	public function add_body_class( array $classes ): array {
		$classes[] = 'plugin-' . $this->app_name;

		return $classes;
	}

	public function get_notifications( $force_update = false ): array {
		$notifications = get_transient( $this->transient_key );

		if ( false === $notifications || $force_update ) {
			$notifications = $this->get_data();
			set_transient( $this->transient_key, $notifications, 12 * HOUR_IN_SECONDS );
		}

		return $notifications;
	}

	private function get_data(): array {
		$response = wp_remote_get(
			$this->api_endpoint,
			[
				'timeout' => 10,
				'body' => [
					'app_version' => $this->app_version,
					'site_lang' => get_bloginfo( 'language' ),
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return [];
		}

		$data = \json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data ) || ! is_array( $data ) ) {
			return [];
		}

		return $data;
	}
}
