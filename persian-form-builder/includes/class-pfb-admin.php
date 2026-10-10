<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PFB_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	public function register_menu() {
		add_menu_page( 'فرم‌ساز', 'فرم‌ساز', 'manage_options', 'pfb-forms', array( $this, 'page_forms_list' ), 'dashicons-feedback', 26 );
		add_submenu_page( 'pfb-forms', 'همه فرم‌ها', 'همه فرم‌ها', 'manage_options', 'pfb-forms', array( $this, 'page_forms_list' ) );
		add_submenu_page( 'pfb-forms', 'افزودن فرم جدید', 'افزودن فرم جدید', 'manage_options', 'pfb-form-edit', array( $this, 'page_form_edit' ) );
		add_submenu_page( 'pfb-forms', 'پاسخ‌های فرم‌ها', 'پاسخ‌ها', 'manage_options', 'pfb-entries', array( 'PFB_Entries', 'page' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'pfb-' ) === false && strpos( $hook, 'page_pfb-' ) === false ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style( 'pfb-admin', PFB_URL . 'assets/css/admin.css', array(), PFB_VERSION );
		wp_enqueue_script( 'pfb-admin', PFB_URL . 'assets/js/admin-builder.js', array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ), PFB_VERSION, true );
		wp_localize_script( 'pfb-admin', 'PFB_Admin_Data', array(
			'field_types' => pfb_field_type_labels(),
			'ajax_url'    => admin_url( 'admin-ajax.php' ),
		) );
	}

	/**
	 * مدیریت عملیات: حذف / تکثیر فرم
	 */
	public function handle_actions() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'pfb-forms' || ! isset( $_GET['action'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action  = sanitize_text_field( $_GET['action'] );
		$form_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		if ( ! $form_id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'pfb_action_' . $form_id ) ) {
			return;
		}

		global $wpdb;
		$forms_table  = $wpdb->prefix . PFB_TABLE_FORMS;
		$fields_table = $wpdb->prefix . PFB_TABLE_FIELDS;

		if ( $action === 'delete' ) {
			$entries_table      = $wpdb->prefix . PFB_TABLE_ENTRIES;
			$entry_values_table = $wpdb->prefix . PFB_TABLE_ENTRY_VALUES;
			$flags_table        = $wpdb->prefix . PFB_TABLE_FLAGS;
			$entry_flags_table  = $wpdb->prefix . PFB_TABLE_ENTRY_FLAGS;

			$entry_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $entries_table WHERE form_id = %d", $form_id ) );
			if ( ! empty( $entry_ids ) ) {
				pfb_delete_entry_uploaded_files( $entry_ids );
				$placeholders = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM $entry_values_table WHERE entry_id IN ($placeholders)", $entry_ids ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM $entry_flags_table WHERE entry_id IN ($placeholders)", $entry_ids ) );
			}
			$wpdb->delete( $entries_table, array( 'form_id' => $form_id ) );
			$wpdb->delete( $flags_table, array( 'form_id' => $form_id ) );
			$wpdb->delete( $fields_table, array( 'form_id' => $form_id ) );
			$wpdb->delete( $forms_table, array( 'id' => $form_id ) );
			wp_safe_redirect( admin_url( 'admin.php?page=pfb-forms&deleted=1' ) );
			exit;
		}

		if ( $action === 'duplicate' ) {
			$form = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $forms_table WHERE id = %d", $form_id ), ARRAY_A );
			if ( $form ) {
				unset( $form['id'] );
				$form['title']      = $form['title'] . ' (کپی)';
				$form['created_at'] = current_time( 'mysql' );
				$wpdb->insert( $forms_table, $form );
				$new_id = $wpdb->insert_id;

				$fields = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $fields_table WHERE form_id = %d", $form_id ), ARRAY_A );
				foreach ( $fields as $f ) {
					unset( $f['id'] );
					$f['form_id'] = $new_id;
					$wpdb->insert( $fields_table, $f );
				}

				$flags_table = $wpdb->prefix . PFB_TABLE_FLAGS;
				$flags = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $flags_table WHERE form_id = %d", $form_id ), ARRAY_A );
				foreach ( $flags as $fl ) {
					unset( $fl['id'] );
					$fl['form_id'] = $new_id;
					$wpdb->insert( $flags_table, $fl );
				}
			}
			wp_safe_redirect( admin_url( 'admin.php?page=pfb-forms&duplicated=1' ) );
			exit;
		}
	}

	public function page_forms_list() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		global $wpdb;
		$forms_table = $wpdb->prefix . PFB_TABLE_FORMS;
		$entries_table = $wpdb->prefix . PFB_TABLE_ENTRIES;
		$forms = $wpdb->get_results( "SELECT * FROM $forms_table ORDER BY id DESC" );
		include PFB_PATH . 'admin/views/forms-list.php';
	}

	public function page_form_edit() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		global $wpdb;
		$forms_table  = $wpdb->prefix . PFB_TABLE_FORMS;
		$fields_table = $wpdb->prefix . PFB_TABLE_FIELDS;

		$form_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$notice  = '';

		// ذخیره فرم
		if ( isset( $_POST['pfb_save_form'] ) && check_admin_referer( 'pfb_save_form_action', 'pfb_save_form_nonce' ) ) {
			$title       = sanitize_text_field( wp_unslash( $_POST['pfb_title'] ) );
			$description = wp_kses_post( wp_unslash( $_POST['pfb_description'] ) );
			$status      = ( isset( $_POST['pfb_status'] ) && $_POST['pfb_status'] === 'active' ) ? 'active' : 'inactive';

			$welcome_image_url  = esc_url_raw( wp_unslash( $_POST['pfb_welcome_image_url'] ) );
			$welcome_title      = wp_kses_post( wp_unslash( $_POST['pfb_welcome_title'] ) );
			$welcome_description = wp_kses_post( wp_unslash( $_POST['pfb_welcome_description'] ) );
			$end_title          = wp_kses_post( wp_unslash( $_POST['pfb_end_title'] ) );
			$end_description    = wp_kses_post( wp_unslash( $_POST['pfb_end_description'] ) );

			$data = array(
				'title'                => $title,
				'description'          => $description,
				'status'               => $status,
				'welcome_image_url'    => $welcome_image_url,
				'welcome_title'        => $welcome_title,
				'welcome_description'  => $welcome_description,
				'end_title'            => $end_title,
				'end_description'      => $end_description,
			);

			if ( $form_id ) {
				$wpdb->update( $forms_table, $data, array( 'id' => $form_id ) );
			} else {
				$data['created_at'] = current_time( 'mysql' );
				$wpdb->insert( $forms_table, $data );
				$form_id = $wpdb->insert_id;
			}

			// ذخیره فیلدها
			$fields_json = isset( $_POST['pfb_fields_json'] ) ? wp_unslash( $_POST['pfb_fields_json'] ) : '[]';
			$fields_data = json_decode( $fields_json, true );
			if ( ! is_array( $fields_data ) ) $fields_data = array();

			$existing_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $fields_table WHERE form_id = %d", $form_id ) );
			$kept_ids     = array();
			$uid_to_id    = array(); // شناسه‌ی موقت سمت مرورگر => شناسه‌ی واقعی در دیتابیس (برای رفع ارجاع شرط نمایش)
			$saved_index  = array(); // index در fields_data => array('id'=>.., 'options'=>..) برای رفع ارجاع در گام دوم

			foreach ( $fields_data as $index => $f ) {
				$field_id    = isset( $f['id'] ) ? (int) $f['id'] : 0;
				$field_uid   = isset( $f['uid'] ) ? sanitize_text_field( $f['uid'] ) : '';
				$field_type  = isset( $f['type'] ) ? sanitize_text_field( $f['type'] ) : 'text';
				$label       = isset( $f['label'] ) ? sanitize_text_field( $f['label'] ) : '';
				$placeholder = isset( $f['placeholder'] ) ? sanitize_text_field( $f['placeholder'] ) : '';
				$required    = ! empty( $f['required'] ) ? 1 : 0;
				$options_arr = isset( $f['options'] ) && is_array( $f['options'] ) ? $f['options'] : array();

				// پاکسازی گزینه‌ها
				$clean_options = array();
				if ( isset( $options_arr['template'] ) ) {
					$clean_options['template'] = sanitize_text_field( $options_arr['template'] );
				}
				if ( isset( $options_arr['choices'] ) && is_array( $options_arr['choices'] ) ) {
					$clean_options['choices'] = array_map( 'sanitize_text_field', $options_arr['choices'] );
				}
				foreach ( array( 'min', 'max', 'step', 'min_label', 'max_label' ) as $rk ) {
					if ( isset( $options_arr[ $rk ] ) ) {
						$clean_options[ $rk ] = sanitize_text_field( $options_arr[ $rk ] );
					}
				}
				$clean_options['editable'] = ! empty( $options_arr['editable'] ) ? 1 : 0;
				if ( isset( $options_arr['file_categories'] ) && is_array( $options_arr['file_categories'] ) ) {
					$allowed_cats = array( 'image', 'documents' );
					$clean_cats   = array_values( array_intersect( array_map( 'sanitize_key', $options_arr['file_categories'] ), $allowed_cats ) );
					$clean_options['file_categories'] = ! empty( $clean_cats ) ? $clean_cats : array( 'image' );
				}
				// نمایش شرطی: در این مرحله هنوز شناسه‌ی محرک را به‌صورت trigger_uid (موقت) نگه می‌داریم؛
				// چون فیلد محرک ممکن است خودش هنوز شناسه‌ی واقعی دیتابیس نگرفته باشد (فیلد تازه‌ساخته‌شده در همین ذخیره‌سازی).
				if ( isset( $options_arr['condition'] ) && is_array( $options_arr['condition'] ) && ! empty( $options_arr['condition']['trigger_uid'] ) ) {
					$clean_options['condition'] = array(
						'trigger_uid' => sanitize_text_field( $options_arr['condition']['trigger_uid'] ),
						'values'      => isset( $options_arr['condition']['values'] ) && is_array( $options_arr['condition']['values'] )
							? array_map( 'sanitize_text_field', $options_arr['condition']['values'] )
							: array(),
					);
				}

				$row = array(
					'form_id'     => $form_id,
					'field_type'  => $field_type,
					'label'       => $label,
					'placeholder' => $placeholder,
					'is_required' => $required,
					'options'     => wp_json_encode( $clean_options ),
					'sort_order'  => $index,
				);

				if ( $field_id && in_array( $field_id, $existing_ids ) ) {
					$wpdb->update( $fields_table, $row, array( 'id' => $field_id ) );
					$resolved_id = $field_id;
				} else {
					$wpdb->insert( $fields_table, $row );
					$resolved_id = $wpdb->insert_id;
				}

				$kept_ids[] = $resolved_id;
				if ( $field_uid !== '' ) {
					$uid_to_id[ $field_uid ] = $resolved_id;
				}
				if ( ! empty( $clean_options['condition']['trigger_uid'] ) ) {
					$saved_index[ $index ] = array( 'id' => $resolved_id, 'options' => $clean_options );
				}
			}

			$to_delete = array_map( 'intval', array_diff( $existing_ids, $kept_ids ) );
			foreach ( $to_delete as $del_id ) {
				$wpdb->delete( $fields_table, array( 'id' => $del_id ) );
			}

			// گام دوم: رفع ارجاع فیلدهای دارای شرط نمایش — شناسه‌ی موقت محرک را به شناسه‌ی واقعی آن در دیتابیس تبدیل می‌کند.
			// اگر فیلد محرک حذف شده یا در همان لحظه از لیست برداشته شده باشد، شرط نادیده گرفته می‌شود (فیلد همیشه نمایش داده می‌شود).
			foreach ( $saved_index as $info ) {
				$trigger_uid = $info['options']['condition']['trigger_uid'];
				$final_options = $info['options'];
				if ( isset( $uid_to_id[ $trigger_uid ] ) && ! in_array( $uid_to_id[ $trigger_uid ], $to_delete, true ) ) {
					$final_options['condition'] = array(
						'field_id' => $uid_to_id[ $trigger_uid ],
						'values'   => $info['options']['condition']['values'],
					);
				} else {
					unset( $final_options['condition'] );
				}
				$wpdb->update( $fields_table, array( 'options' => wp_json_encode( $final_options ) ), array( 'id' => $info['id'] ) );
			}

			// ذخیره ستون‌های وضعیت (تیک‌خوردنی‌های قابل مدیریت مثل «تحویل دفتر»/«صدور»)
			$flags_table = $wpdb->prefix . PFB_TABLE_FLAGS;
			$entry_flags_table = $wpdb->prefix . PFB_TABLE_ENTRY_FLAGS;

			$flags_json = isset( $_POST['pfb_flags_json'] ) ? wp_unslash( $_POST['pfb_flags_json'] ) : '[]';
			$flags_data = json_decode( $flags_json, true );
			if ( ! is_array( $flags_data ) ) $flags_data = array();

			$existing_flag_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $flags_table WHERE form_id = %d", $form_id ) );
			$kept_flag_ids      = array();

			foreach ( $flags_data as $findex => $fl ) {
				$flag_id    = isset( $fl['id'] ) ? (int) $fl['id'] : 0;
				$flag_label = isset( $fl['label'] ) ? sanitize_text_field( $fl['label'] ) : '';
				if ( $flag_label === '' ) continue;

				$frow = array(
					'form_id'    => $form_id,
					'label'      => $flag_label,
					'sort_order' => $findex,
				);

				if ( $flag_id && in_array( $flag_id, $existing_flag_ids ) ) {
					$wpdb->update( $flags_table, $frow, array( 'id' => $flag_id ) );
					$kept_flag_ids[] = $flag_id;
				} else {
					$wpdb->insert( $flags_table, $frow );
					$kept_flag_ids[] = $wpdb->insert_id;
				}
			}

			$flags_to_delete = array_diff( $existing_flag_ids, $kept_flag_ids );
			foreach ( $flags_to_delete as $del_flag_id ) {
				$wpdb->delete( $flags_table, array( 'id' => $del_flag_id ) );
				$wpdb->delete( $entry_flags_table, array( 'flag_id' => $del_flag_id ) );
			}

			$notice = 'فرم با موفقیت ذخیره شد.';
		}

		$form = $form_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $forms_table WHERE id = %d", $form_id ) ) : null;
		$fields = $form_id ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $fields_table WHERE form_id = %d ORDER BY sort_order ASC, id ASC", $form_id ) ) : array();

		$flags_table = $wpdb->prefix . PFB_TABLE_FLAGS;
		$flags = $form_id ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $flags_table WHERE form_id = %d ORDER BY sort_order ASC, id ASC", $form_id ) ) : array();

		include PFB_PATH . 'admin/views/form-edit.php';
	}
}
