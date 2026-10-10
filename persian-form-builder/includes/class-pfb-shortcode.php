<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PFB_Shortcode {

	public function __construct() {
		add_shortcode( 'pfb_form', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	public function register_assets() {
		// فونت وزیرمتن برای نمایش صحیح ارقام و حروف فارسی روی همه دستگاه‌ها (اندروید/iOS معمولاً Tahoma ندارند)
		wp_register_style( 'pfb-google-font', 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@500;700;800&display=swap', array(), null );
		wp_register_style( 'pfb-frontend', PFB_URL . 'assets/css/frontend.css', array( 'pfb-google-font' ), PFB_VERSION );
		wp_register_script( 'pfb-frontend', PFB_URL . 'assets/js/frontend.js', array( 'jquery' ), PFB_VERSION, true );
		wp_localize_script( 'pfb-frontend', 'PFB_Front', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		) );
	}

	public function render( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'pfb_form' );
		if ( empty( $atts['id'] ) ) {
			return '<p class="pfb-error-box">شناسه فرم مشخص نشده است.</p>';
		}
		return PFB_Render::render_form( (int) $atts['id'] );
	}
}
