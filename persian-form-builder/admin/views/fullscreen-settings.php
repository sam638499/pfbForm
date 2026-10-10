<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap pfb-wrap" dir="rtl">
	<h1>تنظیمات نمایش تمام‌صفحه</h1>

	<div class="pfb-shortcode-line">
		<strong>نحوه استفاده:</strong> یک صفحه‌ی جدید در وردپرس بسازید (صفحات ← افزودن صفحه جدید)، شورت‌کد فرم موردنظر (مثلاً <code>[pfb_form id="1"]</code>) را داخل آن قرار دهید،
		سپس از باکس «ویژگی‌های صفحه» (Page Attributes) در سایدبار سمت راست ویرایشگر، از قسمت «قالب» (Template) گزینه‌ی
		<strong>«فرم‌ساز — تمام صفحه (بدون هدر/فوتر قالب)»</strong> را انتخاب و صفحه را منتشر کنید. با این کار هدر و فوتر قالب اصلی سایت شما نمایش داده نمی‌شود
		و به‌جای آن، فوتر مختصر زیر تنظیم می‌شود.
	</div>

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-success is-dismissible" style="margin-top:15px;"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>

	<form method="post" style="margin-top:20px;">
		<?php wp_nonce_field( 'pfb_fullscreen_action', 'pfb_fullscreen_nonce' ); ?>

		<table class="form-table">
			<tr>
				<th>پس‌زمینه کل صفحه</th>
				<td><input type="text" name="page_bg_color" class="pfb-color-picker" value="<?php echo esc_attr( $settings['page_bg_color'] ); ?>" /></td>
			</tr>
			<tr>
				<th colspan="2"><hr /></th>
			</tr>
			<tr>
				<th>نمایش فوتر مختصر</th>
				<td><label><input type="checkbox" name="footer_enable" value="1" <?php checked( $settings['footer_enable'], 1 ); ?> /> فوتر پایین صفحه نمایش داده شود</label></td>
			</tr>
			<tr>
				<th><label for="footer_text">توضیحات مختصر فوتر</label></th>
				<td>
					<textarea id="footer_text" name="footer_text" class="large-text" rows="3"><?php echo esc_textarea( $settings['footer_text'] ); ?></textarea>
					<p class="description">مثال: یک جمله کوتاه درباره مجموعه یا راهنمایی برای کاربر.</p>
				</td>
			</tr>
			<tr>
				<th><label for="footer_phone">شماره تماس</label></th>
				<td><input type="text" id="footer_phone" name="footer_phone" class="regular-text" placeholder="021-12345678" value="<?php echo esc_attr( $settings['footer_phone'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="footer_email">ایمیل پشتیبانی (اختیاری)</label></th>
				<td><input type="text" id="footer_email" name="footer_email" class="regular-text" placeholder="support@example.com" value="<?php echo esc_attr( $settings['footer_email'] ); ?>" /></td>
			</tr>
			<tr>
				<th>رنگ پس‌زمینه فوتر</th>
				<td><input type="text" name="footer_bg_color" class="pfb-color-picker" value="<?php echo esc_attr( $settings['footer_bg_color'] ); ?>" /></td>
			</tr>
			<tr>
				<th>رنگ متن فوتر</th>
				<td><input type="text" name="footer_text_color" class="pfb-color-picker" value="<?php echo esc_attr( $settings['footer_text_color'] ); ?>" /></td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" name="pfb_save_fullscreen" value="1" class="button button-primary button-large">ذخیره تنظیمات</button>
		</p>
	</form>
</div>
