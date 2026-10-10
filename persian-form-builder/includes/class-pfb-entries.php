<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PFB_Entries {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_delete' ) );
	}

	public function handle_delete() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'pfb-entries' || ! isset( $_GET['action'] ) || $_GET['action'] !== 'delete' ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) return;

		$entry_id = isset( $_GET['entry_id'] ) ? (int) $_GET['entry_id'] : 0;
		if ( ! $entry_id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'pfb_delete_entry_' . $entry_id ) ) {
			return;
		}

		global $wpdb;
		$entries_table      = $wpdb->prefix . PFB_TABLE_ENTRIES;
		$entry_values_table = $wpdb->prefix . PFB_TABLE_ENTRY_VALUES;

		$form_id = $wpdb->get_var( $wpdb->prepare( "SELECT form_id FROM $entries_table WHERE id = %d", $entry_id ) );

		pfb_delete_entry_uploaded_files( array( $entry_id ) );

		$wpdb->delete( $entry_values_table, array( 'entry_id' => $entry_id ) );
		$wpdb->delete( $entries_table, array( 'id' => $entry_id ) );

		wp_safe_redirect( admin_url( 'admin.php?page=pfb-entries&form_id=' . (int) $form_id . '&deleted=1' ) );
		exit;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		global $wpdb;
		$forms_table  = $wpdb->prefix . PFB_TABLE_FORMS;
		$fields_table = $wpdb->prefix . PFB_TABLE_FIELDS;
		$entries_table = $wpdb->prefix . PFB_TABLE_ENTRIES;
		$entry_values_table = $wpdb->prefix . PFB_TABLE_ENTRY_VALUES;

		$forms = $wpdb->get_results( "SELECT id, title FROM $forms_table ORDER BY id DESC" );
		$selected_form_id = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : ( ! empty( $forms ) ? (int) $forms[0]->id : 0 );

		$fields = array();
		$entries = array();
		$values_map = array();

		if ( $selected_form_id ) {
			$fields = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $fields_table WHERE form_id = %d ORDER BY sort_order ASC, id ASC", $selected_form_id ) );
			$fields = array_values( array_filter( $fields, function( $f ) { return ! in_array( $f->field_type, pfb_non_input_field_types(), true ); } ) );

			$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
			$per_page = 20;
			$offset   = ( $paged - 1 ) * $per_page;

			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $entries_table WHERE form_id = %d", $selected_form_id ) );
			$entries = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $entries_table WHERE form_id = %d ORDER BY id DESC LIMIT %d OFFSET %d", $selected_form_id, $per_page, $offset ) );

			if ( ! empty( $entries ) ) {
				$entry_ids = wp_list_pluck( $entries, 'id' );
				$placeholders = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $entry_values_table WHERE entry_id IN ($placeholders)", $entry_ids ) );
				foreach ( $rows as $r ) {
					$values_map[ $r->entry_id ][ $r->field_id ] = $r->value;
				}
			}
			$total_pages = ceil( $total / $per_page );
		}

		include PFB_PATH . 'admin/views/entries-list.php';
	}
}
