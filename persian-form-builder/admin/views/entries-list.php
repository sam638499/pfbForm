<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap pfb-wrap" dir="rtl">
	<h1>پاسخ‌های فرم‌ها</h1>

	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>پاسخ مورد نظر حذف شد.</p></div>
	<?php endif; ?>

	<?php if ( empty( $forms ) ) : ?>
		<p>هنوز هیچ فرمی ساخته نشده است.</p>
	<?php else : ?>

	<form method="get" class="pfb-entries-filter">
		<input type="hidden" name="page" value="pfb-entries" />
		<label>انتخاب فرم: </label>
		<select name="form_id" onchange="this.form.submit()">
			<?php foreach ( $forms as $f ) : ?>
				<option value="<?php echo (int) $f->id; ?>" <?php selected( $selected_form_id, $f->id ); ?>><?php echo esc_html( $f->title ?: '(بدون عنوان)' ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php if ( $selected_form_id ) :
			$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=pfb_export_csv&form_id=' . $selected_form_id ), 'pfb_export_' . $selected_form_id );
		?>
			<a href="<?php echo esc_url( $export_url ); ?>" class="button">خروجی اکسل (CSV)</a>
		<?php endif; ?>
	</form>

	<table class="wp-list-table widefat fixed striped pfb-table" style="margin-top:15px;">
		<thead>
			<tr>
				<th style="width:60px;">#</th>
				<th style="width:150px;">تاریخ ثبت</th>
				<?php foreach ( $fields as $f ) : ?>
					<th><?php echo esc_html( $f->label ); ?></th>
				<?php endforeach; ?>
				<th style="width:80px;">عملیات</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $entries ) ) : ?>
				<tr><td colspan="<?php echo 3 + count( $fields ); ?>">هنوز پاسخی برای این فرم ثبت نشده است.</td></tr>
			<?php else : ?>
				<?php foreach ( $entries as $entry ) :
					$delete_url = wp_nonce_url( admin_url( 'admin.php?page=pfb-entries&action=delete&entry_id=' . $entry->id . '&form_id=' . $selected_form_id ), 'pfb_delete_entry_' . $entry->id );
				?>
					<tr>
						<td><?php echo (int) $entry->id; ?></td>
						<td><?php echo esc_html( $entry->created_at ); ?></td>
						<?php foreach ( $fields as $f ) :
							$val = isset( $values_map[ $entry->id ][ $f->id ] ) ? $values_map[ $entry->id ][ $f->id ] : '';
						?>
							<td>
								<?php if ( $f->field_type === 'image' && ! empty( $val ) ) : ?>
									<?php if ( pfb_is_image_url( $val ) ) : ?>
										<a href="<?php echo esc_url( $val ); ?>" target="_blank"><img src="<?php echo esc_url( $val ); ?>" style="max-width:60px;max-height:60px;border-radius:4px;" /></a>
									<?php else :
										list( $doc_icon, $doc_label ) = pfb_document_icon_label( $val );
									?>
										<a href="<?php echo esc_url( $val ); ?>" target="_blank"><?php echo esc_html( $doc_icon . ' ' . $doc_label ); ?></a>
									<?php endif; ?>
								<?php else : ?>
									<?php echo esc_html( $val ); ?>
								<?php endif; ?>
							</td>
						<?php endforeach; ?>
						<td>
							<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small pfb-btn-danger" onclick="return confirm('حذف این پاسخ؟');">حذف</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( ! empty( $total_pages ) && $total_pages > 1 ) : ?>
		<div class="pfb-pagination">
			<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pfb-entries&form_id=' . $selected_form_id . '&paged=' . $i ) ); ?>" class="button <?php echo $i === $paged ? 'button-primary' : ''; ?>"><?php echo $i; ?></a>
			<?php endfor; ?>
		</div>
	<?php endif; ?>

	<?php endif; ?>
</div>
