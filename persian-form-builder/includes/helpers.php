<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * تبدیل تاریخ میلادی به شمسی
 */
function pfb_gregorian_to_jalali( $gy, $gm, $gd ) {
	$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
	$jy = ( $gy <= 1600 ) ? 0 : 979;
	$gy -= ( $gy <= 1600 ) ? 621 : 1600;
	$gy2 = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
	$days = ( 365 * $gy ) + ( (int) ( ( $gy2 + 3 ) / 4 ) ) - ( (int) ( ( $gy2 + 99 ) / 100 ) ) + ( (int) ( ( $gy2 + 399 ) / 400 ) ) - 80 + $gd + $g_d_m[ $gm - 1 ];
	$jy += 33 * ( (int) ( $days / 12053 ) );
	$days %= 12053;
	$jy += 4 * ( (int) ( $days / 1461 ) );
	$days %= 1461;
	if ( $days > 365 ) {
		$jy += (int) ( ( $days - 1 ) / 365 );
		$days = ( $days - 1 ) % 365;
	}
	if ( $days < 186 ) {
		$jm = 1 + (int) ( $days / 31 );
		$jd = 1 + ( $days % 31 );
	} else {
		$jm = 7 + (int) ( ( $days - 186 ) / 30 );
		$jd = 1 + ( ( $days - 186 ) % 30 );
	}
	return array( $jy, $jm, $jd );
}

/**
 * تبدیل یک تاریخ/زمان MySQL به رشته‌ی نمایشی تاریخ شمسی (برای ستون «تاریخ ثبت» در صفحه پاسخ‌ها)
 * از current_time('mysql') که وردپرس هنگام ثبت پاسخ ذخیره می‌کند استفاده می‌شود (زمان محلی سایت)؛
 * برای جلوگیری از جابه‌جایی منطقه زمانی، اجزای رشته مستقیماً پارس می‌شوند (بدون تبدیل timestamp).
 */
function pfb_format_jalali_datetime( $mysql_datetime ) {
	if ( empty( $mysql_datetime ) ) return '';
	$parts = date_parse( $mysql_datetime );
	if ( empty( $parts ) || ! empty( $parts['error_count'] ) || ! checkdate( (int) $parts['month'], (int) $parts['day'], (int) $parts['year'] ) ) {
		return '';
	}
	list( $jy, $jm, $jd ) = pfb_gregorian_to_jalali( (int) $parts['year'], (int) $parts['month'], (int) $parts['day'] );
	return sprintf( '%04d/%02d/%02d - %02d:%02d', $jy, $jm, $jd, (int) $parts['hour'], (int) $parts['minute'] );
}

/**
 * مشابه pfb_format_jalali_datetime() اما تاریخ و ساعت را جدا برمی‌گرداند
 * (برای نمایش دو‌خطی در جدول‌های فشرده، و برای گروه‌بندی/شماره‌گذاری بر اساس روز)
 */
function pfb_format_jalali_date_parts( $mysql_datetime ) {
	$empty = array( 'date' => '', 'time' => '', 'day_key' => '' );
	if ( empty( $mysql_datetime ) ) return $empty;
	$parts = date_parse( $mysql_datetime );
	if ( empty( $parts ) || ! empty( $parts['error_count'] ) || ! checkdate( (int) $parts['month'], (int) $parts['day'], (int) $parts['year'] ) ) {
		return $empty;
	}
	list( $jy, $jm, $jd ) = pfb_gregorian_to_jalali( (int) $parts['year'], (int) $parts['month'], (int) $parts['day'] );
	$date_str = sprintf( '%04d/%02d/%02d', $jy, $jm, $jd );
	$time_str = sprintf( '%02d:%02d', (int) $parts['hour'], (int) $parts['minute'] );
	return array( 'date' => $date_str, 'time' => $time_str, 'day_key' => $date_str );
}

/**
 * اعتبارسنجی کد ملی ایرانی
 */
function pfb_validate_national_code( $code ) {
	$code = trim( $code );
	if ( ! preg_match( '/^\d{8,10}$/', $code ) ) {
		return false;
	}
	$code = str_pad( $code, 10, '0', STR_PAD_LEFT );
	if ( preg_match( '/^(\d)\1{9}$/', $code ) ) {
		return false; // اعداد تکراری مثل 0000000000
	}
	$sum = 0;
	for ( $i = 0; $i < 9; $i++ ) {
		$sum += ( (int) $code[ $i ] ) * ( 10 - $i );
	}
	$remainder = $sum % 11;
	$control   = (int) $code[9];
	if ( $remainder < 2 ) {
		return $control === $remainder;
	} else {
		return $control === ( 11 - $remainder );
	}
}

/**
 * اعتبارسنجی شماره همراه ایرانی
 */
function pfb_validate_mobile( $mobile ) {
	$mobile = trim( $mobile );
	$mobile = preg_replace( '/^\+98/', '0', $mobile );
	$mobile = preg_replace( '/^0098/', '0', $mobile );
	return (bool) preg_match( '/^09\d{9}$/', $mobile );
}

/**
 * لیست حروف مجاز پلاک خودرو ایران
 */
function pfb_plate_letters() {
	return array( 'الف', 'ب', 'پ', 'ت', 'ث', 'ج', 'د', 'ز', 'س', 'ش', 'ص', 'ط', 'ع', 'ف', 'ق', 'ک', 'گ', 'ل', 'م', 'ن', 'و', 'ه', 'ی' );
}

/**
 * ساخت آرایه انواع فیلد به همراه برچسب فارسی
 */
function pfb_field_type_labels() {
	return array(
		'text'        => 'متن کوتاه',
		'select'      => 'کرکره‌ای (لیست کشویی)',
		'radio'       => 'چند گزینه‌ای',
		'jalali_date' => 'تاریخ شمسی',
		'image'       => 'بارگذاری تصویر/فایل',
		'car_plate'   => 'پلاک خودرو ایرانی',
		'range'       => 'طیفی (اسلایدر امتیازی)',
		'page_break'  => 'شکست صفحه (دکمه بعدی/قبلی)',
	);
}

/**
 * انواعی که «فیلد ورودی واقعی» نیستند و در جدول پاسخ‌ها/خروجی ستون نمی‌گیرند
 */
function pfb_non_input_field_types() {
	return array( 'page_break' );
}

/**
 * خروجی امن HTML برای فیلدهای متنی غنی (توضیحات و عنوان‌ها) — امکان بولد، رنگ، اندازه و چینش را حفظ می‌کند
 */
function pfb_richtext_field( $name, $value, $oneline = false ) {
	?>
	<div class="pfb-richtext-wrap">
		<div class="pfb-richtext-toolbar">
			<button type="button" class="button pfb-rt-btn" data-cmd="bold" title="ضخیم"><strong>B</strong></button>
			<button type="button" class="button pfb-rt-btn" data-cmd="justifyRight" title="راست‌چین">⇥</button>
			<button type="button" class="button pfb-rt-btn" data-cmd="justifyCenter" title="وسط‌چین">↔</button>
			<button type="button" class="button pfb-rt-btn" data-cmd="justifyLeft" title="چپ‌چین">⇤</button>
			<input type="color" class="pfb-rt-color" title="رنگ متن" value="#111111" />
			<select class="pfb-rt-size" title="اندازه متن">
				<option value="">اندازه متن</option>
				<option value="2">کوچک</option>
				<option value="3">معمولی</option>
				<option value="5">بزرگ</option>
				<option value="6">خیلی بزرگ</option>
			</select>
			<button type="button" class="button pfb-rt-btn" data-cmd="removeFormat" title="پاک کردن قالب‌بندی">✕</button>
		</div>
		<div class="pfb-richtext-editable<?php echo $oneline ? ' pfb-richtext-oneline' : ''; ?>" contenteditable="true"><?php echo $value; /* از قبل با wp_kses_post پاکسازی شده */ ?></div>
		<textarea name="<?php echo esc_attr( $name ); ?>" class="pfb-richtext-source" style="display:none;"><?php echo esc_textarea( $value ); ?></textarea>
	</div>
	<?php
}

/**
 * دریافت آی‌پی کاربر
 */
function pfb_get_ip() {
	if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
	} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
	} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
	return '';
}

/**
 * تبدیل ارقام فارسی/عربی به ارقام انگلیسی (لاتین) — برای اعتبارسنجی و ذخیره‌سازی یکنواخت
 * لازم است چون کاربرانی که با کیبورد فارسی تایپ می‌کنند ارقام ۰-۹ (یا ٠-٩) وارد می‌کنند.
 */
function pfb_fa_to_en_digits( $str ) {
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	$ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
	return str_replace( $ar, $en, str_replace( $fa, $en, (string) $str ) );
}

/**
 * دسته‌های فایل مجاز برای فیلد «بارگذاری تصویر/فایل» به همراه پسوند/MIME هرکدام.
 * برای هر دسته: نقشه‌ی «پسوند => نوع MIME» که مستقیماً به عنوان overrides['mimes'] به wp_handle_upload() داده می‌شود.
 */
function pfb_file_category_mimes() {
	return array(
		'image'     => array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
		),
		'documents' => array(
			'pdf'  => 'application/pdf',
			'doc'  => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xls'  => 'application/vnd.ms-excel',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'zip'  => 'application/zip',
			'rar'  => 'application/x-rar-compressed',
		),
	);
}

/**
 * برچسب فارسی هر دسته، برای نمایش راهنما به کاربر
 */
function pfb_file_category_labels() {
	return array(
		'image'     => 'تصویر (jpg, png, gif, webp)',
		'documents' => 'سند (Word, Excel, PDF, ZIP, RAR)',
	);
}

/**
 * آیا یک URL ذخیره‌شده در فیلد «بارگذاری تصویر/فایل» واقعاً تصویر است یا یک سند (برای تصمیم بین نمایش بندانگشتی/آیکون)
 */
function pfb_is_image_url( $url ) {
	$ext = strtolower( pathinfo( parse_url( (string) $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	return in_array( $ext, array( 'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp' ), true );
}

/**
 * آیکون و برچسب مناسب برای نمایش یک فایل سند بر اساس پسوندش (برای جدول‌های پاسخ)
 */
function pfb_document_icon_label( $url ) {
	$ext = strtolower( pathinfo( parse_url( (string) $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	$map = array(
		'pdf'  => array( '📕', 'PDF' ),
		'doc'  => array( '📄', 'Word' ),
		'docx' => array( '📄', 'Word' ),
		'xls'  => array( '📊', 'Excel' ),
		'xlsx' => array( '📊', 'Excel' ),
		'zip'  => array( '🗜️', 'ZIP' ),
		'rar'  => array( '🗜️', 'RAR' ),
	);
	return isset( $map[ $ext ] ) ? $map[ $ext ] : array( '📎', 'فایل' );
}

/**
 * دسته‌های فعال یک فیلد بارگذاری فایل را از options آن استخراج می‌کند (پیش‌فرض: فقط تصویر)
 */
function pfb_get_field_file_categories( $options ) {
	$categories = array( 'image' );
	if ( is_array( $options ) && ! empty( $options['file_categories'] ) && is_array( $options['file_categories'] ) ) {
		$allowed = array_keys( pfb_file_category_mimes() );
		$picked  = array_values( array_intersect( $options['file_categories'], $allowed ) );
		if ( ! empty( $picked ) ) $categories = $picked;
	}
	return $categories;
}

/**
 * نقشه‌ی mimes مناسب برای wp_handle_upload() بر اساس دسته‌های فعال یک فیلد
 */
function pfb_get_allowed_mimes_for_categories( $categories ) {
	$all   = pfb_file_category_mimes();
	$mimes = array();
	foreach ( $categories as $cat ) {
		if ( isset( $all[ $cat ] ) ) {
			$mimes = array_merge( $mimes, $all[ $cat ] );
		}
	}
	return $mimes;
}

/**
 * مقدار attribute «accept» فیلد input[type=file] بر اساس دسته‌های فعال (برای فیلتر اولیه در انتخابگر فایل مرورگر)
 */
function pfb_get_accept_attr_for_categories( $categories ) {
	$accept = array();
	if ( in_array( 'image', $categories, true ) ) {
		$accept[] = 'image/*';
	}
	if ( in_array( 'documents', $categories, true ) ) {
		$accept = array_merge( $accept, array( '.pdf', '.doc', '.docx', '.xls', '.xlsx', '.zip', '.rar' ) );
	}
	return implode( ',', $accept );
}

/**
 * حداکثر حجم مجاز (بایت) بر اساس دسته‌های فعال — اسناد سقف بزرگ‌تری دارند
 */
function pfb_get_max_upload_size_for_categories( $categories ) {
	return in_array( 'documents', $categories, true ) ? 20 * 1024 * 1024 : 5 * 1024 * 1024;
}

/**
 * حذف فایل‌های آپلودشده‌ی فیلدهای تصویر/فایل برای مجموعه‌ای از پاسخ‌ها — هنگام حذف پاسخ یا کل فرم فراخوانی می‌شود
 * تا فضای هاست اشغال‌شده توسط فایل‌های حذف‌شده باقی نماند.
 */
function pfb_delete_entry_uploaded_files( $entry_ids ) {
	$entry_ids = array_filter( array_map( 'intval', (array) $entry_ids ) );
	if ( empty( $entry_ids ) ) return;

	global $wpdb;
	$fields_table       = $wpdb->prefix . PFB_TABLE_FIELDS;
	$entry_values_table = $wpdb->prefix . PFB_TABLE_ENTRY_VALUES;

	$placeholders = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT ev.value FROM $entry_values_table ev
		 INNER JOIN $fields_table f ON f.id = ev.field_id
		 WHERE ev.entry_id IN ($placeholders) AND f.field_type = 'image' AND ev.value <> ''",
		$entry_ids
	) );

	if ( empty( $rows ) ) return;

	if ( ! function_exists( 'wp_delete_attachment' ) ) {
		require_once ABSPATH . 'wp-admin/includes/post.php';
	}

	foreach ( $rows as $row ) {
		$url = trim( (string) $row->value );
		if ( $url === '' ) continue;

		$attachment_id = attachment_url_to_postid( $url );
		if ( $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
			continue;
		}

		// فایل‌هایی که پیش از این تغییر (نسخه‌های قدیمی‌تر افزونه) به‌عنوان پیوست ثبت نشده‌اند: حذف مستقیم از دیسک
		$upload_dir = wp_upload_dir();
		if ( strpos( $url, $upload_dir['baseurl'] ) === 0 ) {
			$rel_path  = substr( $url, strlen( $upload_dir['baseurl'] ) );
			$file_path = $upload_dir['basedir'] . $rel_path;
			if ( file_exists( $file_path ) ) {
				@unlink( $file_path );
			}
		}
	}
}

/**
 * آیا شرط نمایش یک فیلد برآورده شده است؟ (نمایش شرطی: «اگر فیلد X برابر بود با Y، این فیلد نشان داده شود»)
 * $raw_values_by_field: نگاشت field_id => مقدار خام ارسالی همان فیلد در این ثبت (از پیش محاسبه‌شده)
 * بدون شرط تعریف‌شده = همیشه نمایش داده می‌شود (خروجی true)
 */
function pfb_field_condition_met( $field, $raw_values_by_field ) {
	$options = json_decode( $field->options, true );
	if ( ! is_array( $options ) || empty( $options['condition'] ) || empty( $options['condition']['field_id'] ) ) {
		return true;
	}
	$condition   = $options['condition'];
	$trigger_id  = (int) $condition['field_id'];
	$allowed     = isset( $condition['values'] ) && is_array( $condition['values'] ) ? $condition['values'] : array();
	$trigger_val = isset( $raw_values_by_field[ $trigger_id ] ) ? $raw_values_by_field[ $trigger_id ] : '';
	if ( is_array( $trigger_val ) ) $trigger_val = ''; // فیلد پلاک/تصویر هرگز محرک شرط نیست
	return in_array( $trigger_val, $allowed, true );
}
