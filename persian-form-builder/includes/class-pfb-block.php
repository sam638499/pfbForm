<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PFB_Block {

	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'pfb-block-editor',
			PFB_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			PFB_VERSION,
			true
		);

		global $wpdb;
		$forms_table = $wpdb->prefix . PFB_TABLE_FORMS;
		$forms = $wpdb->get_results( "SELECT id, title FROM $forms_table ORDER BY id DESC" );
		$forms_list = array();
		foreach ( $forms as $f ) {
			$forms_list[] = array( 'id' => (int) $f->id, 'title' => $f->title );
		}
		wp_localize_script( 'pfb-block-editor', 'PFB_Forms_List', $forms_list );

		register_block_type( 'pfb/form', array(
			'editor_script'   => 'pfb-block-editor',
			'render_callback' => array( $this, 'render_block' ),
			'attributes'      => array(
				'formId' => array(
					'type'    => 'number',
					'default' => 0,
				),
			),
		) );
	}

	public function render_block( $attributes ) {
		$form_id = isset( $attributes['formId'] ) ? (int) $attributes['formId'] : 0;
		if ( empty( $form_id ) ) {
			return '<p class="pfb-error-box">لطفاً یک فرم را از تنظیمات بلاک انتخاب کنید.</p>';
		}
		return PFB_Render::render_form( $form_id );
	}
}
