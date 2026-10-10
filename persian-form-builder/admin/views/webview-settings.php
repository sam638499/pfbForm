<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap pfb-wrap" dir="rtl">
	<h1>نمایش وب پاسخ‌ها</h1>

	<div class="pfb-shortcode-line">
		<strong>نحوه استفاده:</strong> یک صفحه‌ی جدید در وردپرس بسازید (صفحات ← افزودن صفحه جدید) و شورت‌کد <code>[pfb_entries_view]</code> را داخل آن قرار دهید و منتشر کنید.
		در این صفحه، جدولی شبیه اکسل از پاسخ‌های هر فرم (با امکان انتخاب فرم از یک لیست کشویی) به همراه ستون «تاریخ ثبت» به‌صورت شمسی و دو ستون تیک‌خوردنی
		«تحویل دفتر» و «صدور» نمایش داده می‌شود. این صفحه با رمز عبوری که پایین تنظیم می‌کنید محافظت می‌شود و در نتایج گوگل، سایر موتورهای جستجو و مدل‌های
		زبانی/هوش مصنوعی ایندکس یا خوانده نخواهد شد.
	</div>

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-success is-dismissible" style="margin-top:15px;"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>

	<form method="post" style="margin-top:20px;max-width:560px;">
		<?php wp_nonce_field( 'pfb_webview_settings_action', 'pfb_webview_settings_nonce' ); ?>
		<table class="form-table">
			<tr>
				<th>وضعیت فعلی</th>
				<td>
					<?php if ( $has_password ) : ?>
						<span class="pfb-badge pfb-badge-active">رمز عبور تنظیم شده است</span>
					<?php else : ?>
						<span class="pfb-badge pfb-badge-inactive">هنوز رمزی تنظیم نشده — صفحه در دسترس نیست</span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="pfb_webview_password"><?php echo $has_password ? 'تغییر رمز عبور' : 'تعیین رمز عبور'; ?></label></th>
				<td>
					<input type="text" id="pfb_webview_password" name="pfb_webview_password" class="regular-text" placeholder="رمز عبور جدید را وارد کنید" autocomplete="off" />
					<p class="description">این رمز برای مشاهده صفحه‌ی عمومی پاسخ‌ها لازم است. برای تغییر ندادن رمز فعلی، این فیلد را خالی بگذارید و دکمه‌ی ذخیره را نزنید.</p>
				</td>
			</tr>
		</table>
		<p class="submit">
			<button type="submit" name="pfb_webview_save" value="1" class="button button-primary button-large">ذخیره رمز عبور</button>
		</p>
	</form>
</div>
