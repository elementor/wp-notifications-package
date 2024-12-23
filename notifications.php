<?php
namespace WPNotificationsPackage;
// Change here the Namespace

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Notifications {

	private string $app_name;

	private string $app_version;

	private string $app_type;

	private string $transient_key;

	private string $api_endpoint = 'https://assets.elementor.com/notifications/v1/notifications.json';

	public function __construct( string $app_name, string $app_version, string $app_type = 'plugin' ) {
		$this->app_name = sanitize_title( $app_name );
		$this->app_version = $app_version;
		$this->app_type = $app_type;

		$this->transient_key = "_{$this->app_name}_notifications";

		add_action( 'admin_init', [ $this, 'refresh_notifications' ] );
		add_filter( 'body_class', [ $this, 'add_body_class' ] );
	}

	public function refresh_notifications(): void {
		$this->get_notifications();
	}

	public function add_body_class( array $classes ): array {
		$classes[] = $this->app_type . '-' . $this->app_name;

		return $classes;
	}

	public function get_notifications_by_conditions( $force_request = false ) {
		$notifications = $this->get_notifications( $force_request );

		$filtered_notifications = [];

		foreach ( $notifications as $notification ) {
			if ( empty( $notification['conditions'] ) ) {
				$filtered_notifications = $this->add_to_array( $filtered_notifications, $notification );

				continue;
			}

			if ( ! $this->check_conditions( $notification['conditions'] ) ) {
				continue;
			}

			$filtered_notifications = $this->add_to_array( $filtered_notifications, $notification );
		}

		return $filtered_notifications;
	}

	private function get_notifications( $force_update = false ): array {
		$notifications = static::get_transient( $this->transient_key );

		if ( false === $notifications || $force_update ) {
			$notifications = $this->fetch_data();
			static::set_transient( $this->transient_key, $notifications );
		}

		return $notifications;
	}

	private function add_to_array( $filtered_notifications, $notification ) {
		foreach ( $filtered_notifications as $filtered_notification ) {
			if ( $filtered_notification['id'] === $notification['id'] ) {
				return $filtered_notifications;
			}
		}

		$filtered_notifications[] = $notification;

		return $filtered_notifications;
	}

	private function check_conditions( $groups ): bool {
		foreach ( $groups as $group ) {
			if ( $this->check_group( $group ) ) {
				return true;
			}
		}

		return false;
	}

	private function check_group( $group ) {
		$is_or_relation = ! empty( $group['relation'] ) && 'OR' === $group['relation'];
		unset( $group['relation'] );
		$result = false;

		foreach ( $group as $condition ) {
			// Reset results for each condition.
			$result = false;
			switch ( $condition['type'] ) {
				case 'wordpress': // phpcs:ignore WordPress.WP.CapitalPDangit
					// include an unmodified $wp_version
					include ABSPATH . WPINC . '/version.php';
					$result = version_compare( $wp_version, $condition['version'], $condition['operator'] );
					break;
				case 'multisite':
					$result = is_multisite() === $condition['multisite'];
					break;
				case 'language':
					$in_array = in_array( get_locale(), $condition['languages'], true );
					$result = 'in' === $condition['operator'] ? $in_array : ! $in_array;
					break;
				case 'plugin':
					if ( ! function_exists( 'is_plugin_active' ) ) {
						require_once ABSPATH . 'wp-admin/includes/plugin.php';
					}

					$is_plugin_active = is_plugin_active( $condition['plugin'] );

					if ( empty( $condition['operator'] ) ) {
						$condition['operator'] = '==';
					}

					$result = '==' === $condition['operator'] ? $is_plugin_active : ! $is_plugin_active;
					break;
				case 'theme':
					$theme = wp_get_theme();
					if ( wp_get_theme()->parent() ) {
						$theme = wp_get_theme()->parent();
					}

					if ( $theme->get_template() === $condition['theme'] ) {
						$version = $theme->version;
					} else {
						$version = '';
					}

					$result = version_compare( $version, $condition['version'], $condition['operator'] );
					break;

				default:
					$result = apply_filters( "$this->app_name/notifications/condition/{$condition['type']}", $result, $condition );
					break;
			}

			if ( ( $is_or_relation && $result ) || ( ! $is_or_relation && ! $result ) ) {
				return $result;
			}
		}

		return $result;
	}

	private function fetch_data(): array {
		$response = wp_remote_get(
			$this->api_endpoint,
			[
				'timeout' => 10,
				'body' => [
					'app_name' => $this->app_name,
					'app_version' => $this->app_version,
					'site_lang' => get_bloginfo( 'language' ),
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return [];
		}

		$data = \json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['notifications'] ) || ! is_array( $data['notifications'] ) ) {
			return [];
		}

		return $data['notifications'];
	}

	private static function get_transient( $cache_key ) {
		$cache = get_option( $cache_key );

		if ( empty( $cache['timeout'] ) ) {
			return false;
		}

		if ( current_time( 'timestamp' ) > $cache['timeout'] ) {
			return false;
		}

		return json_decode( $cache['value'], true );
	}

	private static function set_transient( $cache_key, $value, $expiration = '+12 hours' ) {
		$data = [
			'timeout' => strtotime( $expiration, current_time( 'timestamp' ) ),
			'value' => json_encode( $value ),
		];

		return update_option( $cache_key, $data, false );
	}
}
