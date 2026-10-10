<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PFB_Render {

	public static function render_form( $form_id ) {
		global $wpdb;
		$form_id = (int) $form_id;
		$forms_table  = $wpdb->prefix . PFB_TABLE_FORMS;
		$fields_table = $wpdb->prefix . PFB_TABLE_FIELDS;

		$form = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $forms_table WHERE id = %d", $form_id ) );
		if ( ! $form || $form->status !== 'active' ) {
			return '<p class="pfb-error-box">فرم مورد نظر یافت نشد یا غیرفعال است.</p>';
		}

		$fields = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $fields_table WHERE form_id = %d ORDER BY sort_order ASC, id ASC", $form_id ) );

		wp_enqueue_style( 'pfb-frontend' );
		wp_enqueue_script( 'pfb-frontend' );

		ob_start();
		?>
		<div class="pfb-form-wrapper" dir="rtl" data-form-id="<?php echo esc_attr( $form_id ); ?>">
			<form class="pfb-form" data-form-id="<?php echo esc_attr( $form_id ); ?>" novalidate>
				<?php wp_nonce_field( 'pfb_submit_' . $form_id, 'pfb_nonce' ); ?>

				<?php if ( ! empty( $form->welcome_title ) || ! empty( $form->welcome_description ) || ! empty( $form->welcome_image_url ) ) : ?>
				<div class="pfb-step pfb-step-welcome active" data-step="welcome">
					<?php if ( ! empty( $form->welcome_image_url ) ) : ?>
						<div class="pfb-welcome-image">
							<img src="<?php echo esc_url( $form->welcome_image_url ); ?>" alt="" />
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $form->welcome_title ) ) : ?>
						<h2 class="pfb-welcome-title"><?php echo wp_kses_post( $form->welcome_title ); ?></h2>
					<?php endif; ?>
					<?php if ( ! empty( $form->welcome_description ) ) : ?>
						<div class="pfb-welcome-desc"><?php echo wp_kses_post( $form->welcome_description ); ?></div>
					<?php endif; ?>
					<button type="button" class="pfb-btn pfb-btn-primary pfb-start-btn">شروع</button>
				</div>
				<?php endif; ?>

				<?php
				// تقسیم فیلدها به صفحات بر اساس نشانگرهای «شکست صفحه»
				$pages        = array();
				$current_page = array();
				foreach ( $fields as $f ) {
					if ( $f->field_type === 'page_break' ) {
						$pages[]      = $current_page;
						$current_page = array();
					} else {
						$current_page[] = $f;
					}
				}
				$pages[]     = $current_page;
				$total_pages = count( $pages );
				?>

				<div class="pfb-step pfb-step-fields <?php echo ( empty( $form->welcome_title ) && empty( $form->welcome_description ) && empty( $form->welcome_image_url ) ) ? 'active' : ''; ?>" data-step="fields">
					<?php if ( ! empty( $form->title ) ) : ?>
						<h3 class="pfb-form-title"><?php echo esc_html( $form->title ); ?></h3>
					<?php endif; ?>
					<?php if ( ! empty( $form->description ) ) : ?>
						<div class="pfb-form-desc"><?php echo wp_kses_post( $form->description ); ?></div>
					<?php endif; ?>

					<?php foreach ( $pages as $page_index => $page_fields ) :
						$page_num = $page_index + 1;
					?>
						<div class="pfb-page <?php echo $page_num === 1 ? 'active' : ''; ?>" data-page="<?php echo (int) $page_num; ?>">
							<div class="pfb-fields">
								<?php foreach ( $page_fields as $field ) : ?>
									<?php self::render_field( $field ); ?>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>

					<div class="pfb-alert pfb-alert-error" style="display:none;"></div>

					<?php if ( $total_pages > 1 ) : ?>
						<div class="pfb-page-indicator">صفحه <span class="pfb-current-page-num">1</span> از <?php echo (int) $total_pages; ?></div>
					<?php endif; ?>

					<div class="pfb-page-nav">
						<button type="button" class="pfb-btn pfb-btn-secondary pfb-prev-page-btn" style="display:none;">قبلی</button>
						<?php if ( $total_pages > 1 ) : ?>
							<button type="button" class="pfb-btn pfb-btn-primary pfb-next-page-btn">بعدی</button>
						<?php endif; ?>
						<button type="submit" class="pfb-btn pfb-btn-primary pfb-submit-btn" <?php echo $total_pages > 1 ? 'style="display:none;"' : ''; ?>>ثبت اطلاعات</button>
					</div>
				</div>

				<div class="pfb-step pfb-step-end" data-step="end">
					<div class="pfb-end-icon">✓</div>
					<h2 class="pfb-end-title"><?php echo wp_kses_post( ! empty( $form->end_title ) ? $form->end_title : 'با تشکر از شما' ); ?></h2>
					<?php if ( ! empty( $form->end_description ) ) : ?>
						<div class="pfb-end-desc"><?php echo wp_kses_post( $form->end_description ); ?></div>
					<?php endif; ?>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_field( $field ) {
		$options = json_decode( $field->options, true );
		if ( ! is_array( $options ) ) $options = array();
		$name       = 'pfb_field_' . $field->id;
		$required   = (bool) $field->is_required;
		$req_star   = $required ? '<span class="pfb-required-star">*</span>' : '';

		$has_condition = ! empty( $options['condition']['field_id'] );
		$cond_attrs = '';
		if ( $has_condition ) {
			$cond_attrs = ' data-cond-field="' . esc_attr( (int) $options['condition']['field_id'] ) . '" data-cond-values="' . esc_attr( wp_json_encode( array_values( (array) $options['condition']['values'] ) ) ) . '"';
		}
		?>
		<div class="pfb-field pfb-field-<?php echo esc_attr( $field->field_type ); ?><?php echo $has_condition ? ' pfb-field-conditional' : ''; ?>" data-field-id="<?php echo esc_attr( $field->id ); ?>" data-required="<?php echo $required ? '1' : '0'; ?>" data-type="<?php echo esc_attr( $field->field_type ); ?>"<?php echo $cond_attrs; ?> style="<?php echo $has_condition ? 'display:none;' : ''; ?>">
			<?php if ( $field->field_type !== 'radio' ) : ?>
				<label class="pfb-label" for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $field->label ); ?> <?php echo $req_star; ?></label>
			<?php else : ?>
				<div class="pfb-label"><?php echo esc_html( $field->label ); ?> <?php echo $req_star; ?></div>
			<?php endif; ?>

			<?php switch ( $field->field_type ) :
				case 'text':
					$template = isset( $options['template'] ) ? $options['template'] : 'none';
					$extra_attr = '';
					if ( $template === 'mobile' ) $extra_attr = 'inputmode="numeric" maxlength="11"';
					if ( $template === 'national_code' ) $extra_attr = 'inputmode="numeric" maxlength="10"';
					if ( $template === 'numeric' ) $extra_attr = 'inputmode="numeric"';
					?>
					<input type="text" class="pfb-input" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>"
						placeholder="<?php echo esc_attr( $field->placeholder ); ?>" data-template="<?php echo esc_attr( $template ); ?>" <?php echo $extra_attr; ?> />
					<?php
					if ( $template === 'national_code' ) echo '<small class="pfb-hint">کد ملی ۱۰ رقمی را وارد کنید.</small>';
					if ( $template === 'mobile' ) echo '<small class="pfb-hint">مثال: 09123456789</small>';
					break;

				case 'select':
					$choices = isset( $options['choices'] ) ? $options['choices'] : array();
					?>
					<select class="pfb-input pfb-select" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>">
						<option value="">— انتخاب کنید —</option>
						<?php foreach ( $choices as $choice ) : ?>
							<option value="<?php echo esc_attr( $choice ); ?>"><?php echo esc_html( $choice ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php
					break;

				case 'radio':
					$choices = isset( $options['choices'] ) ? $options['choices'] : array();
					?>
					<div class="pfb-radio-group">
						<?php foreach ( $choices as $i => $choice ) : ?>
							<label class="pfb-radio-item">
								<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $choice ); ?>" />
								<span><?php echo esc_html( $choice ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<?php
					break;

				case 'jalali_date':
					?>
					<div class="pfb-date-wrapper">
						<input type="text" class="pfb-input pfb-jalali-date" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>"
							placeholder="۱۴۰۴/۰۱/۰۱" readonly autocomplete="off" />
					</div>
					<?php
					break;

				case 'image':
					$file_categories = pfb_get_field_file_categories( $options );
					$accept_attr     = pfb_get_accept_attr_for_categories( $file_categories );
					$max_mb          = (int) ( pfb_get_max_upload_size_for_categories( $file_categories ) / 1024 / 1024 );
					$cat_labels      = pfb_file_category_labels();
					$allowed_labels  = array();
					foreach ( $file_categories as $cat ) {
						if ( isset( $cat_labels[ $cat ] ) ) $allowed_labels[] = $cat_labels[ $cat ];
					}
					?>
					<div class="pfb-image-upload">
						<input type="file" class="pfb-input pfb-file-input" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" accept="<?php echo esc_attr( $accept_attr ); ?>" />
						<div class="pfb-image-preview" style="display:none;"></div>
						<small class="pfb-hint">
							<?php if ( ! empty( $field->placeholder ) ) : ?>
								<?php echo esc_html( $field->placeholder ); ?><br />
							<?php endif; ?>
							انواع مجاز: <?php echo esc_html( implode( ' / ', $allowed_labels ) ); ?> — حداکثر <?php echo (int) $max_mb; ?> مگابایت
						</small>
					</div>
					<?php
					break;

				case 'car_plate':
					$letters = pfb_plate_letters();
					?>
					<div class="pfb-plate-field">
						<div class="pfb-plate-box" dir="ltr">
							<div class="pfb-plate-flag">
								<div class="pfb-plate-flag-stripes">
									<span class="pfb-flag-g"></span><span class="pfb-flag-w"></span><span class="pfb-flag-r"></span>
								</div>
								<div class="pfb-plate-flag-text">I.R.<br />IRAN</div>
							</div>
							<div class="pfb-plate-main">
								<div class="pfb-plate-cellgroup" data-group="p1">
									<input type="text" class="pfb-plate-cell" maxlength="1" inputmode="numeric" aria-label="رقم اول پلاک" />
									<input type="text" class="pfb-plate-cell" maxlength="1" inputmode="numeric" aria-label="رقم دوم پلاک" />
								</div>
								<div class="pfb-plate-letter-wrap">
									<button type="button" class="pfb-plate-letter" aria-haspopup="true" aria-label="انتخاب حرف پلاک">حرف</button>
									<input type="hidden" class="pfb-plate-letter-value" name="<?php echo esc_attr( $name ); ?>[letter]" value="" />
									<div class="pfb-plate-letter-grid">
										<div class="pfb-plate-letter-grid-title">انتخاب حرف پلاک</div>
										<div class="pfb-plate-letter-grid-items">
											<?php foreach ( $letters as $l ) : ?>
												<div class="pfb-plate-letter-item" data-letter="<?php echo esc_attr( $l ); ?>"><?php echo esc_html( $l ); ?></div>
											<?php endforeach; ?>
										</div>
									</div>
								</div>
								<div class="pfb-plate-cellgroup" data-group="p2">
									<input type="text" class="pfb-plate-cell" maxlength="1" inputmode="numeric" aria-label="رقم اول سه‌رقمی" />
									<input type="text" class="pfb-plate-cell" maxlength="1" inputmode="numeric" aria-label="رقم دوم سه‌رقمی" />
									<input type="text" class="pfb-plate-cell" maxlength="1" inputmode="numeric" aria-label="رقم سوم سه‌رقمی" />
								</div>
								<input type="hidden" class="pfb-plate-p1" name="<?php echo esc_attr( $name ); ?>[p1]" value="" />
								<input type="hidden" class="pfb-plate-p2" name="<?php echo esc_attr( $name ); ?>[p2]" value="" />
							</div>
							<div class="pfb-plate-ir">
								<div class="pfb-plate-iran">ایران</div>
								<div class="pfb-plate-cellgroup pfb-plate-cellgroup-ir" data-group="ir">
									<input type="text" class="pfb-plate-cell pfb-plate-cell-ir" maxlength="1" inputmode="numeric" aria-label="رقم اول کد شهر" />
									<input type="text" class="pfb-plate-cell pfb-plate-cell-ir" maxlength="1" inputmode="numeric" aria-label="رقم دوم کد شهر" />
								</div>
								<input type="hidden" class="pfb-plate-ir-code" name="<?php echo esc_attr( $name ); ?>[ir]" value="" />
							</div>
						</div>
						<p class="pfb-plate-hint">اعداد و حرف را مستقیماً روی تصویر پلاک وارد کنید.</p>
					</div>
					<?php
					break;
				case 'range':
					$min       = isset( $options['min'] ) && $options['min'] !== '' ? $options['min'] : 0;
					$max       = isset( $options['max'] ) && $options['max'] !== '' ? $options['max'] : 10;
					$step      = isset( $options['step'] ) && $options['step'] !== '' ? $options['step'] : 1;
					$min_label = isset( $options['min_label'] ) ? $options['min_label'] : '';
					$max_label = isset( $options['max_label'] ) ? $options['max_label'] : '';
					$default   = floor( ( (float) $min + (float) $max ) / 2 );
					?>
					<div class="pfb-range-field" dir="ltr">
						<div class="pfb-range-value-bubble"><span class="pfb-range-value"><?php echo esc_html( $default ); ?></span></div>
						<input type="range" class="pfb-input pfb-range-input" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>"
							min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $default ); ?>" />
						<?php if ( $min_label || $max_label ) : ?>
							<div class="pfb-range-labels">
								<span><?php echo esc_html( $min_label ); ?></span>
								<span><?php echo esc_html( $max_label ); ?></span>
							</div>
						<?php endif; ?>
					</div>
					<?php
					break;
			endswitch; ?>
			<div class="pfb-field-error"></div>
		</div>
		<?php
	}
}
