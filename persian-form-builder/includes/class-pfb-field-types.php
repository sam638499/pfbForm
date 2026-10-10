<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PFB_Field_Types {

	/**
	 * اعتبارسنجی مقدار ارسالی یک فیلد بر اساس نوع آن
	 * خروجی: true در صورت معتبر بودن، در غیر این صورت رشته خطا
	 */
	public static function validate( $field, $value ) {
		$type     = $field->field_type;
		$required = (bool) $field->is_required;
		$options  = json_decode( $field->options, true );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( $type === 'page_break' ) {
			return true; // نشانگر شکست صفحه است، مقداری برای ذخیره ندارد
		}

		if ( $type === 'image' ) {
			// فایل جداگانه بررسی می‌شود
			if ( $required && empty( $value ) ) {
				return 'بارگذاری فایل برای این فیلد الزامی است.';
			}
			return true;
		}

		if ( $type === 'car_plate' ) {
			if ( empty( $value ) || ! is_array( $value ) ) {
				if ( $required ) {
					return 'تکمیل پلاک خودرو الزامی است.';
				}
				return true;
			}
			$p1     = isset( $value['p1'] ) ? trim( $value['p1'] ) : '';
			$letter = isset( $value['letter'] ) ? trim( $value['letter'] ) : '';
			$p2     = isset( $value['p2'] ) ? trim( $value['p2'] ) : '';
			$ir     = isset( $value['ir'] ) ? trim( $value['ir'] ) : '';

			if ( $required && ( $p1 === '' || $letter === '' || $p2 === '' || $ir === '' ) ) {
				return 'تکمیل تمام قسمت‌های پلاک الزامی است.';
			}
			// ارقام ممکن است فارسی تایپ شده باشند؛ برای بررسی الگو به انگلیسی تبدیل می‌شوند (مقدار اصلی برای نمایش حفظ می‌شود)
			if ( $p1 !== '' && ! preg_match( '/^\d{2}$/', pfb_fa_to_en_digits( $p1 ) ) ) {
				return 'بخش اول پلاک باید ۲ رقم باشد.';
			}
			if ( $p2 !== '' && ! preg_match( '/^\d{3}$/', pfb_fa_to_en_digits( $p2 ) ) ) {
				return 'بخش دوم پلاک باید ۳ رقم باشد.';
			}
			if ( $ir !== '' && ! preg_match( '/^\d{2}$/', pfb_fa_to_en_digits( $ir ) ) ) {
				return 'کد ایران پلاک باید ۲ رقم باشد.';
			}
			if ( $letter !== '' && ! in_array( $letter, pfb_plate_letters(), true ) ) {
				return 'حرف پلاک نامعتبر است.';
			}
			return true;
		}

		// سایر انواع، مقدار متنی ساده هستند
		$value = is_string( $value ) ? trim( $value ) : $value;

		if ( $required && ( $value === '' || $value === null ) ) {
			return 'تکمیل فیلد "' . esc_html( $field->label ) . '" الزامی است.';
		}

		if ( $value === '' || $value === null ) {
			return true; // فیلد اختیاری و خالی
		}

		if ( $type === 'text' ) {
			$template = isset( $options['template'] ) ? $options['template'] : 'none';
			if ( $template === 'national_code' && ! pfb_validate_national_code( pfb_fa_to_en_digits( $value ) ) ) {
				return 'کد ملی وارد شده معتبر نیست.';
			}
			if ( $template === 'mobile' && ! pfb_validate_mobile( pfb_fa_to_en_digits( $value ) ) ) {
				return 'شماره همراه وارد شده معتبر نیست (مثال: 09123456789).';
			}
			if ( $template === 'numeric' && ! preg_match( '/^\d+$/', pfb_fa_to_en_digits( $value ) ) ) {
				return 'این فیلد فقط باید عدد باشد.';
			}
		}

		if ( $type === 'select' || $type === 'radio' ) {
			$choices = isset( $options['choices'] ) ? $options['choices'] : array();
			if ( ! empty( $choices ) && ! in_array( $value, $choices, true ) ) {
				return 'مقدار انتخاب‌شده برای این فیلد معتبر نیست.';
			}
		}

		if ( $type === 'jalali_date' ) {
			if ( ! preg_match( '/^\d{4}\/\d{1,2}\/\d{1,2}$/', $value ) ) {
				return 'تاریخ وارد شده معتبر نیست.';
			}
		}

		if ( $type === 'range' ) {
			$value_n = pfb_fa_to_en_digits( $value );
			if ( ! is_numeric( $value_n ) ) {
				return 'مقدار طیف معتبر نیست.';
			}
			$min = isset( $options['min'] ) && $options['min'] !== '' ? (float) $options['min'] : 0;
			$max = isset( $options['max'] ) && $options['max'] !== '' ? (float) $options['max'] : 10;
			$v   = (float) $value_n;
			if ( $v < $min || $v > $max ) {
				return 'مقدار طیف خارج از بازه مجاز است.';
			}
		}

		return true;
	}

	/**
	 * تبدیل مقدار برای ذخیره در دیتابیس (رشته)
	 */
	public static function prepare_value_for_storage( $field, $value ) {
		if ( $field->field_type === 'car_plate' && is_array( $value ) ) {
			$p1     = isset( $value['p1'] ) ? sanitize_text_field( $value['p1'] ) : '';
			$letter = isset( $value['letter'] ) ? sanitize_text_field( $value['letter'] ) : '';
			$p2     = isset( $value['p2'] ) ? sanitize_text_field( $value['p2'] ) : '';
			$ir     = isset( $value['ir'] ) ? sanitize_text_field( $value['ir'] ) : '';
			if ( $p1 === '' && $letter === '' && $p2 === '' && $ir === '' ) {
				return '';
			}
			return "{$p1} {$letter} {$p2} - ایران {$ir}";
		}
		if ( is_array( $value ) ) {
			return sanitize_text_field( wp_json_encode( $value ) );
		}
		return sanitize_textarea_field( $value );
	}
}
