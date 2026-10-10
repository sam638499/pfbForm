<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PFB_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_pfb_submit_form', array( $this, 'submit_form' ) );
		add_action( 'wp_ajax_nopriv_pfb_submit_form', array( $this, 'submit_form' ) );
	}

	public function submit_form() {
		global $wpdb;

		$form_id = isset( $_POST['form_id'] ) ? (int) $_POST['form_id'] : 0;
		if ( ! $form_id ) {
			wp_send_json_error( array( 'message' => 'فرم نامعتبر است.' ) );
		}

		if ( ! isset( $_POST['pfb_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pfb_nonce'] ) ), 'pfb_submit_' . $form_id ) ) {
			wp_send_json_error( array( 'message' => 'خطای امنیتی، لطفاً صفحه را رفرش کرده و دوباره تلاش کنید.' ) );
		}

		$forms_table  = $wpdb->prefix . PFB_TABLE_FORMS;
		$fields_table = $wpdb->prefix . PFB_TABLE_FIELDS;

		$form = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $forms_table WHERE id = %d", $form_id ) );
		if ( ! $form || $form->status !== 'active' ) {
			wp_send_json_error( array( 'message' => 'فرم مورد نظر یافت نشد یا غیرفعال است.' ) );
		}

		$fields = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $fields_table WHERE form_id = %d ORDER BY sort_order ASC, id ASC", $form_id ) );

		$errors         = array();
		$clean_values   = array(); // field_id => storage value
		$raw_values_by_field = array(); // field_id => مقدار خام (برای بررسی شرط نمایش سایر فیلدها لازم است)

		// گام اول: خواندن مقدار خام هر فیلد از $_POST (بدون اعتبارسنجی) تا مقدار فیلدهای محرکِ شرط، صرف‌نظر از ترتیبشان در فرم، در دسترس باشد
		foreach ( $fields as $field ) {
			if ( $field->field_type === 'page_break' || $field->field_type === 'image' ) {
				continue;
			}
			$post_key = 'pfb_field_' . $field->id;

			if ( $field->field_type === 'car_plate' ) {
				$v = isset( $_POST[ $post_key ] ) ? wp_unslash( $_POST[ $post_key ] ) : array();
				$raw_values_by_field[ $field->id ] = is_array( $v ) ? array_map( 'sanitize_text_field', $v ) : array();
				continue;
			}

			$raw_value = isset( $_POST[ $post_key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $post_key ] ) ) : '';

			// نرمال‌سازی ارقام فارسی/عربی به انگلیسی برای فیلدهایی که مقدارشان باید عددی استاندارد باشد
			if ( $field->field_type === 'range' || $field->field_type === 'jalali_date' ) {
				$raw_value = pfb_fa_to_en_digits( $raw_value );
			} elseif ( $field->field_type === 'text' ) {
				$field_options = json_decode( $field->options, true );
				$template      = is_array( $field_options ) && isset( $field_options['template'] ) ? $field_options['template'] : 'none';
				if ( $template === 'mobile' || $template === 'national_code' || $template === 'numeric' ) {
					$raw_value = pfb_fa_to_en_digits( $raw_value );
				}
			}

			$raw_values_by_field[ $field->id ] = $raw_value;
		}

		// گام دوم: بررسی شرط نمایش، اعتبارسنجی و آماده‌سازی برای ذخیره
		foreach ( $fields as $field ) {
			if ( $field->field_type === 'page_break' ) {
				continue;
			}

			// اگر شرط نمایش این فیلد برآورده نشده، یعنی کاربر اصلاً آن را ندیده — نادیده گرفته می‌شود (نه اعتبارسنجی، نه ذخیره)
			if ( ! pfb_field_condition_met( $field, $raw_values_by_field ) ) {
				continue;
			}

			$post_key = 'pfb_field_' . $field->id;

			if ( $field->field_type === 'image' ) {
				if ( ! empty( $_FILES[ $post_key ] ) && ! empty( $_FILES[ $post_key ]['name'] ) ) {
					$check = self::handle_file_upload( $post_key, $field );
					if ( is_wp_error( $check ) ) {
						$errors[ $field->id ] = $check->get_error_message();
						continue;
					}
					$raw_value = $check; // URL
				} else {
					$raw_value = '';
				}
			} else {
				$raw_value = $raw_values_by_field[ $field->id ];
			}

			$valid = PFB_Field_Types::validate( $field, $raw_value );
			if ( $valid !== true ) {
				$errors[ $field->id ] = $valid;
				continue;
			}

			$clean_values[ $field->id ] = PFB_Field_Types::prepare_value_for_storage( $field, $raw_value );
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'message' => 'لطفاً خطاهای فرم را برطرف کنید.', 'errors' => $errors ) );
		}

		$entries_table      = $wpdb->prefix . PFB_TABLE_ENTRIES;
		$entry_values_table = $wpdb->prefix . PFB_TABLE_ENTRY_VALUES;

		$wpdb->insert( $entries_table, array(
			'form_id'    => $form_id,
			'created_at' => current_time( 'mysql' ),
			'ip'         => pfb_get_ip(),
		) );
		$entry_id = $wpdb->insert_id;

		foreach ( $clean_values as $field_id => $value ) {
			$wpdb->insert( $entry_values_table, array(
				'entry_id' => $entry_id,
				'field_id' => $field_id,
				'value'    => $value,
			) );
		}

		wp_send_json_success( array(
			'message'         => 'اطلاعات با موفقیت ثبت شد.',
			'end_title'       => ! empty( $form->end_title ) ? $form->end_title : 'با تشکر از شما',
			'end_description' => $form->end_description,
		) );
	}

	private static function handle_file_upload( $post_key, $field ) {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$options    = json_decode( $field->options, true );
		$categories = pfb_get_field_file_categories( is_array( $options ) ? $options : array() );
		$mimes      = pfb_get_allowed_mimes_for_categories( $categories );
		$max_size   = pfb_get_max_upload_size_for_categories( $categories );

		$file = $_FILES[ $post_key ];

		if ( $file['size'] > $max_size ) {
			return new WP_Error( 'too_large', 'حجم فایل نباید بیشتر از ' . (int) ( $max_size / 1024 / 1024 ) . ' مگابایت باشد.' );
		}

		// اعتبارسنجی نوع فایل بر عهده‌ی خود wp_handle_upload (بر اساس پسوند و بررسی محتوای واقعی فایل) گذاشته می‌شود؛
		// این قابل‌اعتمادتر از فیلد type ارسالی توسط مرورگر است که به‌سادگی قابل جعل است.
		$overrides = array( 'test_form' => false, 'mimes' => $mimes );
		$movefile  = wp_handle_upload( $file, $overrides );

		if ( isset( $movefile['error'] ) ) {
			return new WP_Error( 'upload_error', $movefile['error'] );
		}

		// ثبت رسمی فایل به‌عنوان پیوست وردپرس تا در کتابخانه رسانه دیده شود و بتوان بعداً (مثلاً هنگام حذف پاسخ) پاکش کرد
		if ( ! function_exists( 'wp_insert_attachment' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		$attachment_data = array(
			'post_mime_type' => $movefile['type'],
			'post_title'     => sanitize_file_name( pathinfo( $movefile['file'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id = wp_insert_attachment( $attachment_data, $movefile['file'] );
		if ( $attach_id && ! is_wp_error( $attach_id ) ) {
			update_post_meta( $attach_id, '_pfb_uploaded_field_id', (int) $field->id );
			if ( strpos( $movefile['type'], 'image/' ) === 0 && function_exists( 'wp_generate_attachment_metadata' ) ) {
				$meta = wp_generate_attachment_metadata( $attach_id, $movefile['file'] );
				wp_update_attachment_metadata( $attach_id, $meta );
			}
		}

		return $movefile['url'];
	}
}
