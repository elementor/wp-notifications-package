<?php
/**
 * Plugin Name: WP Notifications Package
 * Description: ...
 * Plugin URI: https://elementor.com/
 * Author: Elementor.com
 * Version: 1.0.0
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * Text Domain: wp-notifications-package
 */

use WPNotificationsPackage\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Plugin_Example {

	public Notifications $notifications;

	public function __construct() {
		$this->init();
	}

	private function init() {
		require __DIR__ . '/notifications.php';

		$this->notifications = new Notifications(
			'wp_notifications_package',
			'1.0.0'
		);

		add_action( 'admin_notices', [ $this, 'display_notifications' ] );
	}

	public function display_notifications() {
		$notifications = $this->notifications->get_notifications( true );

		if ( empty( $notifications['notifications'] ) ) {
			return;
		}

		?>
		<div class="notice notice-info is-dismissible">
			<h3><?php esc_html_e( 'What\'s new:', 'wp-notifications-package' ); ?></h3>
			<ul>
				<?php foreach ( $notifications['notifications'] as $item ) : ?>
					<li><a href="<?php echo esc_url( $item['link'] ?? '#' ); ?>" target="_blank"><?php echo esc_html( $item['title'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}

new Plugin_Example();

