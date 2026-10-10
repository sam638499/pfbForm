<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PFB_Fullscreen {

	const OPTION_KEY = 'pfb_fullscreen_settings';

	public function __construct() {
		add_filter( 'theme_page_templates', array( $this, 'register_template' ) );
		add_filter( 'template_include', array( $this, 'load_template' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
	}

	/**
	 * افزودن قالب صفحه به لیست «ویژگی‌های صفحه» در ویرایشگر وردپرس
	 */
	public function register_template( $templates ) {
		$templates['pfb-fullscreen.php'] = 'فرم‌ساز — تمام صفحه (بدون هدر/فوتر قالب)';
		return $templates;
	}

	/**
	 * جایگزینی فایل قالب هنگام نمایش صفحه‌ای که این قالب برای آن انتخاب شده
	 */
	public function load_template( $template ) {
		if ( is_page() ) {
			$slug = get_page_template_slug( get_the_ID() );
			if ( $slug === 'pfb-fullscreen.php' ) {
				$custom = PFB_PATH . 'templates/page-fullscreen.php';
				if ( file_exists( $custom ) ) {
					return $custom;
				}
			}
		}
		return $template;
	}

	public static function get_settings() {
		$defaults = array(
			'footer_enable'     => 1,
			'footer_text'       => 'هرگونه سوال یا مشکل در تکمیل فرم را با پشتیبانی ما در میان بگذارید.',
			'footer_phone'      => '',
			'footer_email'      => '',
			'footer_bg_color'   => '#111827',
			'footer_text_color' => '#ffffff',
			'page_bg_color'     => '#f5f6fa',
		);
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) $saved = array();
		return wp_parse_args( $saved, $defaults );
	}

	public function register_menu() {
		add_submenu_page( 'pfb-forms', 'تنظیمات تمام‌صفحه', 'تنظیمات تمام‌صفحه', 'manage_options', 'pfb-fullscreen-settings', array( $this, 'page' ) );
	}

	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$settings = self::get_settings();
		$notice   = '';

		if ( isset( $_POST['pfb_save_fullscreen'] ) && check_admin_referer( 'pfb_fullscreen_action', 'pfb_fullscreen_nonce' ) ) {
			$settings['footer_enable']     = isset( $_POST['footer_enable'] ) ? 1 : 0;
			$settings['footer_text']       = sanitize_textarea_field( wp_unslash( $_POST['footer_text'] ) );
			$settings['footer_phone']      = sanitize_text_field( wp_unslash( $_POST['footer_phone'] ) );
			$settings['footer_email']      = sanitize_text_field( wp_unslash( $_POST['footer_email'] ) );
			$settings['footer_bg_color']   = sanitize_hex_color( wp_unslash( $_POST['footer_bg_color'] ) ) ?: $settings['footer_bg_color'];
			$settings['footer_text_color'] = sanitize_hex_color( wp_unslash( $_POST['footer_text_color'] ) ) ?: $settings['footer_text_color'];
			$settings['page_bg_color']     = sanitize_hex_color( wp_unslash( $_POST['page_bg_color'] ) ) ?: $settings['page_bg_color'];

			update_option( self::OPTION_KEY, $settings );
			$notice = 'تنظیمات با موفقیت ذخیره شد.';
		}

		include PFB_PATH . 'admin/views/fullscreen-settings.php';
	}
}
