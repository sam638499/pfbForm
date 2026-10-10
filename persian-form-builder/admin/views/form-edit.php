<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$field_types = pfb_field_type_labels();

$fields_bootstrap = array();
foreach ( $fields as $f ) {
	$options = json_decode( $f->options, true );
	if ( ! is_array( $options ) ) $options = array();
	$fields_bootstrap[] = array(
		'id'          => (int) $f->id,
		'type'        => $f->field_type,
		'label'       => $f->label,
		'placeholder' => $f->placeholder,
		'required'    => (bool) $f->is_required,
		'options'     => $options,
	);
}

$flags_bootstrap = array();
foreach ( $flags as $fl ) {
	$flags_bootstrap[] = array(
		'id'    => (int) $fl->id,
		'label' => $fl->label,
	);
}
?>
<div class="wrap pfb-wrap" dir="rtl">
	<h1><?php echo $form ? 'ویرایش فرم' : 'افزودن فرم جدید'; ?></h1>

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>

	<?php if ( $form ) : ?>
		<p class="pfb-shortcode-line">شورت‌کد این فرم: <code>[pfb_form id="<?php echo (int) $form->id; ?>"]</code> — برای استفاده در ویرایشگر بلوکی نیز می‌توانید بلاک «فرم فارسی» را اضافه کنید.</p>
	<?php endif; ?>

	<form method="post" id="pfb-form-editor">
		<?php wp_nonce_field( 'pfb_save_form_action', 'pfb_save_form_nonce' ); ?>
		<input type="hidden" name="pfb_fields_json" id="pfb_fields_json" value="" />
		<input type="hidden" name="pfb_flags_json" id="pfb_flags_json" value="" />

		<div class="pfb-tabs">
			<button type="button" class="pfb-tab-btn active" data-tab="general">تنظیمات کلی</button>
			<button type="button" class="pfb-tab-btn" data-tab="welcome">صفحه خوش‌آمدگویی</button>
			<button type="button" class="pfb-tab-btn" data-tab="fields">فیلدهای فرم</button>
			<button type="button" class="pfb-tab-btn" data-tab="flags">ستون‌های وضعیت</button>
			<button type="button" class="pfb-tab-btn" data-tab="end">صفحه پایانی</button>
		</div>

		<div class="pfb-tab-panel active" data-panel="general">
			<table class="form-table">
				<tr>
					<th><label for="pfb_title">عنوان فرم</label></th>
					<td><input type="text" id="pfb_title" name="pfb_title" class="regular-text" value="<?php echo esc_attr( $form->title ?? '' ); ?>" required /></td>
				</tr>
				<tr>
					<th><label for="pfb_description">توضیحات فرم</label></th>
					<td><?php pfb_richtext_field( 'pfb_description', $form->description ?? '' ); ?>
					<p class="description">این توضیحات بالای فیلدهای فرم نمایش داده می‌شود.</p></td>
				</tr>
				<tr>
					<th>وضعیت</th>
					<td>
						<label><input type="radio" name="pfb_status" value="active" <?php checked( ( $form->status ?? 'active' ), 'active' ); ?> /> فعال</label>
						&nbsp;&nbsp;
						<label><input type="radio" name="pfb_status" value="inactive" <?php checked( ( $form->status ?? 'active' ), 'inactive' ); ?> /> غیرفعال</label>
					</td>
				</tr>
			</table>
		</div>

		<div class="pfb-tab-panel" data-panel="welcome">
			<table class="form-table">
				<tr>
					<th>تصویر بالای صفحه</th>
					<td>
						<div class="pfb-image-picker">
							<img id="pfb_welcome_image_preview" src="<?php echo esc_url( $form->welcome_image_url ?? '' ); ?>" style="<?php echo empty( $form->welcome_image_url ) ? 'display:none;' : ''; ?>max-width:240px;height:auto;display:block;margin-bottom:8px;border-radius:8px;" />
							<input type="hidden" id="pfb_welcome_image_url" name="pfb_welcome_image_url" value="<?php echo esc_attr( $form->welcome_image_url ?? '' ); ?>" />
							<button type="button" class="button" id="pfb_choose_image">انتخاب تصویر</button>
							<button type="button" class="button" id="pfb_remove_image" style="<?php echo empty( $form->welcome_image_url ) ? 'display:none;' : ''; ?>">حذف تصویر</button>
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="pfb_welcome_title">عنوان خوش‌آمدگویی</label></th>
					<td><?php pfb_richtext_field( 'pfb_welcome_title', $form->welcome_title ?? '', true ); ?></td>
				</tr>
				<tr>
					<th><label for="pfb_welcome_description">توضیحات خوش‌آمدگویی</label></th>
					<td><?php pfb_richtext_field( 'pfb_welcome_description', $form->welcome_description ?? '' ); ?>
					<p class="description">در صورت خالی بودن عنوان، تصویر و توضیحات، صفحه خوش‌آمدگویی نمایش داده نمی‌شود و فرم مستقیم باز می‌شود.</p></td>
				</tr>
			</table>
		</div>

		<div class="pfb-tab-panel" data-panel="fields">
			<div class="pfb-field-add-bar">
				<label>افزودن فیلد جدید:</label>
				<?php foreach ( $field_types as $type => $label ) : ?>
					<button type="button" class="button pfb-add-field-btn" data-type="<?php echo esc_attr( $type ); ?>">+ <?php echo esc_html( $label ); ?></button>
				<?php endforeach; ?>
			</div>
			<div id="pfb-fields-list" class="pfb-fields-list"></div>
			<p class="description">برای تغییر ترتیب فیلدها، آن‌ها را با ماوس جابه‌جا کنید.</p>
		</div>

		<div class="pfb-tab-panel" data-panel="flags">
			<p class="description" style="margin-bottom:14px;">این ستون‌ها فقط در صفحه‌ی «پاسخ‌ها» و «نمایش وب پاسخ‌ها» دیده می‌شوند (نه در خود فرم عمومی) و برای علامت‌گذاری وضعیت هر پاسخ توسط شما استفاده می‌شوند — مثلاً «تحویل دفتر» یا «صدور». هر تعداد که بخواهید می‌توانید اضافه، حذف یا تغییر نام دهید.</p>
			<div class="pfb-field-add-bar">
				<button type="button" class="button" id="pfb-add-flag-btn">+ افزودن ستون وضعیت</button>
			</div>
			<div id="pfb-flags-list" class="pfb-flags-list"></div>
		</div>

		<div class="pfb-tab-panel" data-panel="end">
			<table class="form-table">
				<tr>
					<th><label for="pfb_end_title">عنوان صفحه پایانی</label></th>
					<td><?php pfb_richtext_field( 'pfb_end_title', $form->end_title ?? '', true ); ?></td>
				</tr>
				<tr>
					<th><label for="pfb_end_description">توضیحات صفحه پایانی</label></th>
					<td><?php pfb_richtext_field( 'pfb_end_description', $form->end_description ?? '' ); ?></td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<button type="submit" name="pfb_save_form" value="1" class="button button-primary button-large">ذخیره فرم</button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=pfb-forms' ) ); ?>" class="button button-large">بازگشت به لیست</a>
		</p>
	</form>
</div>

<script>
	window.PFB_EXISTING_FIELDS = <?php echo wp_json_encode( $fields_bootstrap ); ?>;
	window.PFB_EXISTING_FLAGS = <?php echo wp_json_encode( $flags_bootstrap ); ?>;
	window.PFB_PLATE_LETTERS = <?php echo wp_json_encode( pfb_plate_letters() ); ?>;
</script>
