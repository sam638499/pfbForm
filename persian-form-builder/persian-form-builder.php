<?php
/**
 * Plugin Name: فرم‌ساز فارسی (Persian Form Builder)
 * Plugin URI: https://example.com
 * Description: افزونه مدیریت و ساخت فرم‌های سفارشی برای وردپرس با فیلدهای اختصاصی ایرانی (کد ملی، شماره همراه، تاریخ شمسی، پلاک خودرو) شبیه به پرس‌لاین.
 * Version: 1.7.0
 * Author: Persian Form Builder
 * Text Domain: pfb
 * Requires at least: 5.6
 * Requires PHP: 7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // جلوگیری از دسترسی مستقیم
}

define( 'PFB_VERSION', '1.7.0' );
define( 'PFB_PATH', plugin_dir_path( __FILE__ ) );
define( 'PFB_URL', plugin_dir_url( __FILE__ ) );
define( 'PFB_TABLE_FORMS', 'pfb_forms' );
define( 'PFB_TABLE_FIELDS', 'pfb_fields' );
define( 'PFB_TABLE_ENTRIES', 'pfb_entries' );
define( 'PFB_TABLE_ENTRY_VALUES', 'pfb_entry_values' );
define( 'PFB_TABLE_FLAGS', 'pfb_flags' );
define( 'PFB_TABLE_ENTRY_FLAGS', 'pfb_entry_flags' );

require_once PFB_PATH . 'includes/helpers.php';
require_once PFB_PATH . 'includes/class-pfb-activator.php';
require_once PFB_PATH . 'includes/class-pfb-field-types.php';
require_once PFB_PATH . 'includes/class-pfb-render.php';
require_once PFB_PATH . 'includes/class-pfb-admin.php';
require_once PFB_PATH . 'includes/class-pfb-entries.php';
require_once PFB_PATH . 'includes/class-pfb-export.php';
require_once PFB_PATH . 'includes/class-pfb-shortcode.php';
require_once PFB_PATH . 'includes/class-pfb-block.php';
require_once PFB_PATH . 'includes/class-pfb-ajax.php';
require_once PFB_PATH . 'includes/class-pfb-fullscreen.php';
require_once PFB_PATH . 'includes/class-pfb-webview.php';

register_activation_hook( __FILE__, array( 'PFB_Activator', 'activate' ) );

/**
 * راه‌اندازی افزونه
 */
final class Persian_Form_Builder {

	private static $instance = null;

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		new PFB_Admin();
		new PFB_Entries();
		new PFB_Export();
		new PFB_Shortcode();
		new PFB_Block();
		new PFB_Ajax();
		new PFB_Fullscreen();
		new PFB_Webview();

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'pfb', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * در صورت تغییر نسخه افزونه (مثلاً پس از افزودن قابلیت جدید که ساختار جدول را تغییر می‌دهد)
	 * ساختار جداول را به‌روزرسانی می‌کند، بدون از دست رفتن اطلاعات موجود
	 */
	public function maybe_upgrade() {
		if ( get_option( 'pfb_db_version' ) !== PFB_VERSION ) {
			PFB_Activator::activate();
		}
	}
}

Persian_Form_Builder::instance();
