<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PFB_Webview {

	const OPTION_PASSWORD = 'pfb_webview_password_hash';
	const COOKIE_NAME     = 'pfb_webview_auth';
	const NONCE_ACTION    = 'pfb_webview_nonce';

	public function __construct() {
		add_shortcode( 'pfb_entries_view', array( $this, 'render_shortcode' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ), 21 );

		add_action( 'wp_ajax_pfb_webview_login', array( $this, 'ajax_login' ) );
		add_action( 'wp_ajax_nopriv_pfb_webview_login', array( $this, 'ajax_login' ) );
		add_action( 'wp_ajax_pfb_webview_toggle', array( $this, 'ajax_toggle' ) );
		add_action( 'wp_ajax_nopriv_pfb_webview_toggle', array( $this, 'ajax_toggle' ) );
		add_action( 'wp_ajax_pfb_webview_logout', array( $this, 'ajax_logout' ) );
		add_action( 'wp_ajax_nopriv_pfb_webview_logout', array( $this, 'ajax_logout' ) );
		add_action( 'wp_ajax_pfb_webview_update_field', array( $this, 'ajax_update_field' ) );
		add_action( 'wp_ajax_nopriv_pfb_webview_update_field', array( $this, 'ajax_update_field' ) );
		add_action( 'wp_ajax_pfb_webview_export', array( $this, 'ajax_export' ) );
		add_action( 'wp_ajax_nopriv_pfb_webview_export', array( $this, 'ajax_export' ) );

		// جلوگیری از ایندکس شدن این صفحه توسط موتورهای جستجو و ربات‌های هوش مصنوعی
		add_action( 'wp_head', array( $this, 'maybe_noindex_meta' ), 1 );
		add_action( 'send_headers', array( $this, 'maybe_noindex_header' ) );
	}

	/* ===================== تشخیص حضور شورت‌کد در صفحه فعلی ===================== */

	private function current_page_has_shortcode() {
		global $post;
		if ( ! $post || empty( $post->post_content ) ) return false;
		return has_shortcode( $post->post_content, 'pfb_entries_view' );
	}

	public function maybe_noindex_meta() {
		if ( $this->current_page_has_shortcode() ) {
			echo '<meta name="robots" content="noindex, nofollow, noarchive, nosnippet" />' . "\n";
		}
	}

	public function maybe_noindex_header() {
		if ( ! is_admin() && $this->current_page_has_shortcode() ) {
			header( 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet' );
		}
	}

	/* ===================== احراز هویت با رمز عبور ساده ===================== */

	private function has_password_set() {
		return (bool) get_option( self::OPTION_PASSWORD );
	}

	private function expected_cookie_token() {
		$hash = get_option( self::OPTION_PASSWORD );
		return wp_hash( 'pfb_webview|' . $hash );
	}

	private function is_authed() {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) || ! $this->has_password_set() ) {
			return false;
		}
		return hash_equals( $this->expected_cookie_token(), $_COOKIE[ self::COOKIE_NAME ] );
	}

	private function set_auth_cookie() {
		if ( headers_sent() ) return;
		setcookie( self::COOKIE_NAME, $this->expected_cookie_token(), time() + 12 * HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
	}

	private function clear_auth_cookie() {
		if ( headers_sent() ) return;
		setcookie( self::COOKIE_NAME, '', time() - 3600, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
	}

	public function ajax_login() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => 'خطای امنیتی، لطفاً صفحه را رفرش کرده و دوباره تلاش کنید.' ) );
		}
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		if ( ! $this->has_password_set() ) {
			wp_send_json_error( array( 'message' => 'ابتدا باید مدیر سایت یک رمز عبور برای این صفحه تنظیم کند.' ) );
		}
		$stored_hash = get_option( self::OPTION_PASSWORD );
		if ( ! password_verify( $password, $stored_hash ) ) {
			wp_send_json_error( array( 'message' => 'رمز عبور اشتباه است.' ) );
		}
		$this->set_auth_cookie();
		wp_send_json_success( array( 'message' => 'ورود موفق' ) );
	}

	public function ajax_logout() {
		$this->clear_auth_cookie();
		wp_send_json_success();
	}

	/* ===================== تغییر وضعیت تیک‌های وضعیت (ستون‌های قابل مدیریت) ===================== */

	public function ajax_toggle() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => 'خطای امنیتی.' ) );
		}
		if ( ! $this->is_authed() ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$entry_id = isset( $_POST['entry_id'] ) ? (int) $_POST['entry_id'] : 0;
		$flag_id  = isset( $_POST['flag_id'] ) ? (int) $_POST['flag_id'] : 0;
		$value    = ! empty( $_POST['value'] ) ? 1 : 0;

		if ( ! $entry_id || ! $flag_id ) {
			wp_send_json_error( array( 'message' => 'درخواست نامعتبر است.' ) );
		}

		global $wpdb;
		$entry_flags_table = $wpdb->prefix . PFB_TABLE_ENTRY_FLAGS;

		$existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $entry_flags_table WHERE entry_id = %d AND flag_id = %d", $entry_id, $flag_id ) );
		if ( $existing_id ) {
			$wpdb->update( $entry_flags_table, array( 'value' => $value ), array( 'id' => $existing_id ) );
		} else {
			$wpdb->insert( $entry_flags_table, array( 'entry_id' => $entry_id, 'flag_id' => $flag_id, 'value' => $value ) );
		}

		wp_send_json_success();
	}

	/* ===================== ویرایش مقدار یک فیلد قابل‌ویرایش، مستقیم از جدول خروجی ===================== */

	public function ajax_update_field() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => 'خطای امنیتی.' ) );
		}
		if ( ! $this->is_authed() ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$entry_id = isset( $_POST['entry_id'] ) ? (int) $_POST['entry_id'] : 0;
		$field_id = isset( $_POST['field_id'] ) ? (int) $_POST['field_id'] : 0;
		$value    = isset( $_POST['value'] ) ? sanitize_textarea_field( wp_unslash( $_POST['value'] ) ) : '';

		if ( ! $entry_id || ! $field_id ) {
			wp_send_json_error( array( 'message' => 'درخواست نامعتبر است.' ) );
		}

		global $wpdb;
		$fields_table       = $wpdb->prefix . PFB_TABLE_FIELDS;
		$entries_table      = $wpdb->prefix . PFB_TABLE_ENTRIES;
		$entry_values_table = $wpdb->prefix . PFB_TABLE_ENTRY_VALUES;

		$field = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $fields_table WHERE id = %d", $field_id ) );
		if ( ! $field ) {
			wp_send_json_error( array( 'message' => 'فیلد یافت نشد.' ) );
		}

		// اطمینان از اینکه این پاسخ واقعاً متعلق به همان فرمی است که این فیلد به آن تعلق دارد
		$entry_form_id = $wpdb->get_var( $wpdb->prepare( "SELECT form_id FROM $entries_table WHERE id = %d", $entry_id ) );
		if ( (int) $entry_form_id !== (int) $field->form_id ) {
			wp_send_json_error( array( 'message' => 'درخواست نامعتبر است.' ) );
		}

		$options = json_decode( $field->options, true );
		if ( ! is_array( $options ) ) $options = array();
		if ( empty( $options['editable'] ) ) {
			wp_send_json_error( array( 'message' => 'این فیلد قابل ویرایش نیست.' ) );
		}

		// نرمال‌سازی ارقام فارسی برای فیلدهای عددی (مثل خود فرم عمومی)
		if ( $field->field_type === 'range' ) {
			$value = pfb_fa_to_en_digits( $value );
		} elseif ( $field->field_type === 'text' ) {
			$template = isset( $options['template'] ) ? $options['template'] : 'none';
			if ( in_array( $template, array( 'mobile', 'national_code', 'numeric' ), true ) ) {
				$value = pfb_fa_to_en_digits( $value );
			}
		}

		$valid = PFB_Field_Types::validate( $field, $value );
		if ( $valid !== true ) {
			wp_send_json_error( array( 'message' => $valid ) );
		}

		$existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $entry_values_table WHERE entry_id = %d AND field_id = %d", $entry_id, $field_id ) );
		if ( $existing_id ) {
			$wpdb->update( $entry_values_table, array( 'value' => $value ), array( 'id' => $existing_id ) );
		} else {
			$wpdb->insert( $entry_values_table, array( 'entry_id' => $entry_id, 'field_id' => $field_id, 'value' => $value ) );
		}

		wp_send_json_success( array( 'value' => $value ) );
	}

	/* ===================== خروجی اکسل (CSV سازگار با اکسل) ===================== */

	public function ajax_export() {
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), self::NONCE_ACTION ) ) {
			wp_die( 'درخواست نامعتبر است.' );
		}
		if ( ! $this->is_authed() ) {
			wp_die( 'دسترسی غیرمجاز.' );
		}

		global $wpdb;
		$forms_table         = $wpdb->prefix . PFB_TABLE_FORMS;
		$fields_table        = $wpdb->prefix . PFB_TABLE_FIELDS;
		$entries_table       = $wpdb->prefix . PFB_TABLE_ENTRIES;
		$entry_values_table  = $wpdb->prefix . PFB_TABLE_ENTRY_VALUES;
		$flags_table         = $wpdb->prefix . PFB_TABLE_FLAGS;
		$entry_flags_table   = $wpdb->prefix . PFB_TABLE_ENTRY_FLAGS;

		$form_id = isset( $_GET['pfb_form'] ) ? (int) $_GET['pfb_form'] : 0;
		$form    = $form_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $forms_table WHERE id = %d", $form_id ) ) : null;

		$fields = $form_id ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $fields_table WHERE form_id = %d ORDER BY sort_order ASC, id ASC", $form_id ) ) : array();
		$fields = array_values( array_filter( $fields, function( $f ) { return ! in_array( $f->field_type, pfb_non_input_field_types(), true ); } ) );

		$flags = $form_id ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $flags_table WHERE form_id = %d ORDER BY sort_order ASC, id ASC", $form_id ) ) : array();

		$entries = $form_id ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $entries_table WHERE form_id = %d ORDER BY id DESC", $form_id ) ) : array();

		$values_map       = array();
		$entry_flags_map  = array();
		if ( ! empty( $entries ) ) {
			$entry_ids    = wp_list_pluck( $entries, 'id' );
			$placeholders = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );

			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $entry_values_table WHERE entry_id IN ($placeholders)", $entry_ids ) );
			foreach ( $rows as $r ) {
				$values_map[ $r->entry_id ][ $r->field_id ] = $r->value;
			}
			if ( ! empty( $flags ) ) {
				$flag_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $entry_flags_table WHERE entry_id IN ($placeholders)", $entry_ids ) );
				foreach ( $flag_rows as $fr ) {
					$entry_flags_map[ $fr->entry_id ][ $fr->flag_id ] = (int) $fr->value;
				}
			}
		}

		$row_numbers = self::compute_daily_row_numbers( $entries );

		$filename = 'pfb-webview-' . sanitize_title( $form ? $form->title : 'form' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // BOM برای نمایش صحیح فارسی در اکسل

		$header_row = array( 'ردیف روز', 'تاریخ ثبت' );
		foreach ( $fields as $f ) $header_row[] = $f->label;
		foreach ( $flags as $fl ) $header_row[] = $fl->label;
		fputcsv( $output, $header_row );

		foreach ( $entries as $entry ) {
			$row = array( $row_numbers[ $entry->id ], pfb_format_jalali_datetime( $entry->created_at ) );
			foreach ( $fields as $f ) {
				$row[] = isset( $values_map[ $entry->id ][ $f->id ] ) ? $values_map[ $entry->id ][ $f->id ] : '';
			}
			foreach ( $flags as $fl ) {
				$row[] = ! empty( $entry_flags_map[ $entry->id ][ $fl->id ] ) ? 'بله' : 'خیر';
			}
			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}

	/**
	 * شماره ردیف هر پاسخ را طوری محاسبه می‌کند که هر روز (شمسی) از عدد ۱ شروع شود؛
	 * قدیمی‌ترین پاسخ هر روز عدد ۱ می‌گیرد، حتی اگر جدول از جدید به قدیم نمایش داده شود.
	 */
	private static function compute_daily_row_numbers( $entries ) {
		$day_key_of  = array();
		$day_totals  = array();
		foreach ( $entries as $entry ) {
			$dparts = pfb_format_jalali_date_parts( $entry->created_at );
			$day_key_of[ $entry->id ] = $dparts['day_key'];
			if ( ! isset( $day_totals[ $dparts['day_key'] ] ) ) $day_totals[ $dparts['day_key'] ] = 0;
			$day_totals[ $dparts['day_key'] ]++;
		}
		$running = $day_totals;
		$numbers = array();
		foreach ( $entries as $entry ) {
			$dk = $day_key_of[ $entry->id ];
			$numbers[ $entry->id ] = $running[ $dk ];
			$running[ $dk ]--;
		}
		return $numbers;
	}

	/* ===================== تنظیمات مدیریت (تعیین/تغییر رمز) ===================== */

	public function register_menu() {
		add_submenu_page( 'pfb-forms', 'نمایش وب پاسخ‌ها', 'نمایش وب پاسخ‌ها', 'manage_options', 'pfb-webview-settings', array( $this, 'settings_page' ) );
	}

	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$notice = '';
		if ( isset( $_POST['pfb_webview_save'] ) && check_admin_referer( 'pfb_webview_settings_action', 'pfb_webview_settings_nonce' ) ) {
			$new_password = isset( $_POST['pfb_webview_password'] ) ? (string) wp_unslash( $_POST['pfb_webview_password'] ) : '';
			if ( ! empty( $new_password ) ) {
				update_option( self::OPTION_PASSWORD, password_hash( $new_password, PASSWORD_DEFAULT ) );
				$notice = 'رمز عبور با موفقیت ذخیره شد.';
			}
		}

		$has_password = $this->has_password_set();
		include PFB_PATH . 'admin/views/webview-settings.php';
	}

	/* ===================== رندر شورت‌کد در صفحه وردپرس ===================== */

	public function render_shortcode() {
		wp_register_style( 'pfb-google-font', 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800&display=swap', array(), null );
		wp_enqueue_style( 'pfb-webview', PFB_URL . 'assets/css/webview.css', array( 'pfb-google-font' ), PFB_VERSION );
		wp_enqueue_script( 'pfb-webview', PFB_URL . 'assets/js/webview.js', array( 'jquery' ), PFB_VERSION, true );
		wp_localize_script( 'pfb-webview', 'PFB_Webview_Data', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
		) );

		if ( ! $this->has_password_set() ) {
			return '<div class="pfb-wv-wrap" dir="rtl"><p class="pfb-wv-error-box">مدیر سایت هنوز رمز عبوری برای این صفحه تنظیم نکرده است.</p></div>';
		}

		if ( ! $this->is_authed() ) {
			return $this->render_login_form();
		}

		return $this->render_table();
	}

	private function render_login_form() {
		ob_start();
		?>
		<div class="pfb-wv-wrap" dir="rtl">
			<form class="pfb-wv-login-form">
				<h2>ورود به بخش گزارش پاسخ‌ها</h2>
				<p class="description">این صفحه محافظت‌شده است. لطفاً رمز عبور را وارد کنید.</p>
				<input type="password" class="pfb-wv-password-input" placeholder="رمز عبور" autocomplete="off" />
				<button type="submit" class="pfb-wv-btn pfb-wv-btn-primary">ورود</button>
				<div class="pfb-wv-login-error" style="display:none;"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * رندر یک سلول از جدول برای یک فیلد: تصویر، متن ساده، یا (در صورت قابل‌ویرایش بودن) یک ورودی زنده
	 */
	private function render_field_cell( $field, $value, $entry_id ) {
		$options = json_decode( $field->options, true );
		if ( ! is_array( $options ) ) $options = array();
		$editable = ! empty( $options['editable'] );

		if ( $field->field_type === 'image' ) {
			if ( ! empty( $value ) ) {
				if ( pfb_is_image_url( $value ) ) {
					printf( '<a href="%1$s" target="_blank"><img src="%1$s" class="pfb-wv-thumb" /></a>', esc_url( $value ) );
				} else {
					list( $doc_icon, $doc_label ) = pfb_document_icon_label( $value );
					printf( '<a href="%1$s" target="_blank" class="pfb-wv-doc-link">%2$s %3$s</a>', esc_url( $value ), esc_html( $doc_icon ), esc_html( $doc_label ) );
				}
			}
			return;
		}

		if ( ! $editable ) {
			echo esc_html( $value );
			return;
		}

		$common = 'class="pfb-wv-editable-input pfb-wv-no-print" data-entry-id="' . (int) $entry_id . '" data-field-id="' . (int) $field->id . '"';

		if ( $field->field_type === 'select' || $field->field_type === 'radio' ) {
			$choices = isset( $options['choices'] ) && is_array( $options['choices'] ) ? $options['choices'] : array();
			echo '<select ' . $common . '>';
			echo '<option value="">—</option>';
			foreach ( $choices as $c ) {
				printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $c ), selected( $value, $c, false ) );
			}
			echo '</select>';
		} elseif ( $field->field_type === 'range' ) {
			$min  = isset( $options['min'] ) && $options['min'] !== '' ? $options['min'] : 0;
			$max  = isset( $options['max'] ) && $options['max'] !== '' ? $options['max'] : 10;
			$step = isset( $options['step'] ) && $options['step'] !== '' ? $options['step'] : 1;
			printf(
				'<input type="number" %1$s min="%2$s" max="%3$s" step="%4$s" value="%5$s" />',
				$common, esc_attr( $min ), esc_attr( $max ), esc_attr( $step ), esc_attr( $value )
			);
		} else {
			printf( '<input type="text" %1$s value="%2$s" />', $common, esc_attr( $value ) );
		}

		echo '<span class="pfb-wv-print-only">' . esc_html( $value ) . '</span>';
	}

	private function render_table() {
		global $wpdb;
		$forms_table         = $wpdb->prefix . PFB_TABLE_FORMS;
		$fields_table        = $wpdb->prefix . PFB_TABLE_FIELDS;
		$entries_table       = $wpdb->prefix . PFB_TABLE_ENTRIES;
		$entry_values_table  = $wpdb->prefix . PFB_TABLE_ENTRY_VALUES;
		$flags_table         = $wpdb->prefix . PFB_TABLE_FLAGS;
		$entry_flags_table   = $wpdb->prefix . PFB_TABLE_ENTRY_FLAGS;

		$forms = $wpdb->get_results( "SELECT id, title FROM $forms_table ORDER BY id DESC" );
		if ( empty( $forms ) ) {
			return '<div class="pfb-wv-wrap" dir="rtl"><p class="pfb-wv-error-box">هنوز هیچ فرمی ساخته نشده است.</p></div>';
		}

		$selected_form_id = isset( $_GET['pfb_form'] ) ? (int) $_GET['pfb_form'] : (int) $forms[0]->id;

		$fields = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $fields_table WHERE form_id = %d ORDER BY sort_order ASC, id ASC", $selected_form_id ) );
		$fields = array_values( array_filter( $fields, function( $f ) { return ! in_array( $f->field_type, pfb_non_input_field_types(), true ); } ) );

		$flags = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $flags_table WHERE form_id = %d ORDER BY sort_order ASC, id ASC", $selected_form_id ) );

		$entries = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $entries_table WHERE form_id = %d ORDER BY id DESC", $selected_form_id ) );

		$values_map = array();
		$entry_flags_map = array();
		if ( ! empty( $entries ) ) {
			$entry_ids    = wp_list_pluck( $entries, 'id' );
			$placeholders = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );

			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $entry_values_table WHERE entry_id IN ($placeholders)", $entry_ids ) );
			foreach ( $rows as $r ) {
				$values_map[ $r->entry_id ][ $r->field_id ] = $r->value;
			}

			if ( ! empty( $flags ) ) {
				$flag_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $entry_flags_table WHERE entry_id IN ($placeholders)", $entry_ids ) );
				foreach ( $flag_rows as $fr ) {
					$entry_flags_map[ $fr->entry_id ][ $fr->flag_id ] = (int) $fr->value;
				}
			}
		}

		$total_cols = 3 + count( $fields ) + count( $flags );
		$row_numbers = self::compute_daily_row_numbers( $entries );

		$export_url = wp_nonce_url( admin_url( 'admin-ajax.php?action=pfb_webview_export&pfb_form=' . $selected_form_id ), self::NONCE_ACTION, 'nonce' );

		ob_start();
		?>
		<div class="pfb-wv-wrap" dir="rtl">
			<div class="pfb-wv-toolbar">
				<div class="pfb-wv-form-select">
					<label>فرم:</label>
					<select id="pfb-wv-form-picker">
						<?php foreach ( $forms as $f ) : ?>
							<option value="<?php echo (int) $f->id; ?>" <?php selected( $selected_form_id, $f->id ); ?>><?php echo esc_html( $f->title ?: '(بدون عنوان)' ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="pfb-wv-toolbar-actions">
					<a href="<?php echo esc_url( $export_url ); ?>" class="pfb-wv-btn">📊 خروجی اکسل</a>
					<button type="button" class="pfb-wv-btn pfb-wv-print-btn">🖨 چاپ</button>
					<button type="button" class="pfb-wv-btn pfb-wv-logout-btn">خروج</button>
				</div>
			</div>

			<p class="pfb-wv-print-hint">برای چاپ فقط بخشی از جدول، ابتدا با تیک زدن ستون اول ردیف‌های موردنظر را انتخاب کنید (برای انتخاب یک محدوده، ردیف اول را تیک بزنید و با نگه‌داشتن Shift روی ردیف آخر تیک بزنید)، سپس روی «چاپ» بزنید. اگر هیچ ردیفی انتخاب نشود، کل جدول چاپ می‌شود. فیلدهایی که در تنظیمات فیلد «قابل ویرایش» شده‌اند، مستقیماً در همین جدول با کلیک قابل تغییر و ذخیره‌اند. برای تغییر اندازه‌ی هر ستون، لبه‌ی آن را بکشید. ردیف دوم سربرگ برای فیلتر کردن هر ستون است.</p>

			<?php if ( ! empty( $flags ) ) : ?>
			<div class="pfb-wv-legend">
				<span class="pfb-wv-legend-item"><i class="pfb-wv-dot pfb-wv-dot-yellow"></i> برخی ستون‌های وضعیت تیک خورده</span>
				<span class="pfb-wv-legend-item"><i class="pfb-wv-dot pfb-wv-dot-green"></i> همه‌ی ستون‌های وضعیت تیک خورده</span>
			</div>
			<?php endif; ?>

			<div class="pfb-wv-table-scroll">
				<table class="pfb-wv-table">
					<thead>
						<tr class="pfb-wv-header-row">
							<th class="pfb-wv-col-select pfb-wv-no-print"><input type="checkbox" class="pfb-wv-select-all" title="انتخاب همه" /></th>
							<th class="pfb-wv-col-num">ردیف<span class="pfb-wv-col-resizer"></span></th>
							<th class="pfb-wv-col-date">تاریخ ثبت<span class="pfb-wv-col-resizer"></span></th>
							<?php foreach ( $fields as $f ) : ?>
								<th><?php echo esc_html( $f->label ); ?><span class="pfb-wv-col-resizer"></span></th>
							<?php endforeach; ?>
							<?php foreach ( $flags as $fl ) : ?>
								<th class="pfb-wv-col-flag"><?php echo esc_html( $fl->label ); ?><span class="pfb-wv-col-resizer"></span></th>
							<?php endforeach; ?>
						</tr>
						<tr class="pfb-wv-filter-row pfb-wv-no-print">
							<th class="pfb-wv-col-select"></th>
							<th class="pfb-wv-col-num"></th>
							<th class="pfb-wv-col-date"><input type="text" class="pfb-wv-filter-input" placeholder="فیلتر" /></th>
							<?php foreach ( $fields as $f ) : ?>
								<th><input type="text" class="pfb-wv-filter-input" placeholder="فیلتر" /></th>
							<?php endforeach; ?>
							<?php foreach ( $flags as $fl ) : ?>
								<th class="pfb-wv-col-flag"></th>
							<?php endforeach; ?>
						</tr>
						<tr class="pfb-wv-summary-row pfb-wv-no-print">
							<th class="pfb-wv-col-select"></th>
							<th class="pfb-wv-col-num"><span class="pfb-wv-summary-total"></span></th>
							<th class="pfb-wv-col-date"></th>
							<?php foreach ( $fields as $f ) : ?>
								<th></th>
							<?php endforeach; ?>
							<?php foreach ( $flags as $fl ) : ?>
								<th class="pfb-wv-col-flag"><span class="pfb-wv-summary-flag" data-flag-id="<?php echo (int) $fl->id; ?>"></span></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $entries ) ) : ?>
							<tr><td colspan="<?php echo (int) $total_cols; ?>" class="pfb-wv-empty">هنوز پاسخی برای این فرم ثبت نشده است.</td></tr>
						<?php else : ?>
							<?php foreach ( $entries as $i => $entry ) :
								$checked_count = 0;
								foreach ( $flags as $fl ) {
									if ( ! empty( $entry_flags_map[ $entry->id ][ $fl->id ] ) ) $checked_count++;
								}
								$total_flags = count( $flags );
								if ( $total_flags === 0 || $checked_count === 0 ) {
									$status_class = 'pfb-wv-status-none';
								} elseif ( $checked_count === $total_flags ) {
									$status_class = 'pfb-wv-status-complete';
								} else {
									$status_class = 'pfb-wv-status-partial';
								}
								$dparts = pfb_format_jalali_date_parts( $entry->created_at );
							?>
								<tr data-entry-id="<?php echo (int) $entry->id; ?>" class="<?php echo esc_attr( $status_class ); ?>">
									<td class="pfb-wv-col-select pfb-wv-no-print"><input type="checkbox" class="pfb-wv-select-row" /></td>
									<td><?php echo (int) $row_numbers[ $entry->id ]; ?></td>
									<td class="pfb-wv-col-date">
										<div class="pfb-wv-date-line"><?php echo esc_html( $dparts['date'] ); ?></div>
										<div class="pfb-wv-time-line"><?php echo esc_html( $dparts['time'] ); ?></div>
									</td>
									<?php foreach ( $fields as $f ) :
										$val = isset( $values_map[ $entry->id ][ $f->id ] ) ? $values_map[ $entry->id ][ $f->id ] : '';
									?>
										<td><?php $this->render_field_cell( $f, $val, $entry->id ); ?></td>
									<?php endforeach; ?>
									<?php foreach ( $flags as $fl ) :
										$checked = ! empty( $entry_flags_map[ $entry->id ][ $fl->id ] );
									?>
										<td class="pfb-wv-col-flag">
											<input type="checkbox" class="pfb-wv-flag pfb-wv-no-print" data-flag-id="<?php echo (int) $fl->id; ?>" <?php checked( $checked, true ); ?> />
											<span class="pfb-wv-print-only"><?php echo $checked ? '✓' : '—'; ?></span>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
