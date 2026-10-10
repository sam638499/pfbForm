<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PFB_Export {

	public function __construct() {
		add_action( 'admin_post_pfb_export_csv', array( $this, 'export_csv' ) );
	}

	public function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$form_id = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0;
		if ( ! $form_id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'pfb_export_' . $form_id ) ) {
			wp_die( 'درخواست نامعتبر است.' );
		}

		global $wpdb;
		$forms_table         = $wpdb->prefix . PFB_TABLE_FORMS;
		$fields_table        = $wpdb->prefix . PFB_TABLE_FIELDS;
		$entries_table       = $wpdb->prefix . PFB_TABLE_ENTRIES;
		$entry_values_table  = $wpdb->prefix . PFB_TABLE_ENTRY_VALUES;

		$form   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $forms_table WHERE id = %d", $form_id ) );
		$fields = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $fields_table WHERE form_id = %d ORDER BY sort_order ASC, id ASC", $form_id ) );
		$fields = array_values( array_filter( $fields, function( $f ) { return ! in_array( $f->field_type, pfb_non_input_field_types(), true ); } ) );
		$entries = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $entries_table WHERE form_id = %d ORDER BY id ASC", $form_id ) );

		$entry_ids = wp_list_pluck( $entries, 'id' );
		$values_map = array();
		if ( ! empty( $entry_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $entry_values_table WHERE entry_id IN ($placeholders)", $entry_ids ) );
			foreach ( $rows as $r ) {
				$values_map[ $r->entry_id ][ $r->field_id ] = $r->value;
			}
		}

		$filename = 'pfb-' . sanitize_title( $form ? $form->title : 'form' ) . '-entries.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		fprintf( $output, chr(0xEF) . chr(0xBB) . chr(0xBF) ); // BOM برای نمایش صحیح فارسی در اکسل

		$header_row = array( 'شماره پاسخ', 'تاریخ ثبت', 'آی‌پی' );
		foreach ( $fields as $f ) {
			$header_row[] = $f->label;
		}
		fputcsv( $output, $header_row );

		foreach ( $entries as $entry ) {
			$row = array( $entry->id, $entry->created_at, $entry->ip );
			foreach ( $fields as $f ) {
				$row[] = isset( $values_map[ $entry->id ][ $f->id ] ) ? $values_map[ $entry->id ][ $f->id ] : '';
			}
			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}
}
