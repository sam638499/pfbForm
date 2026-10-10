<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap pfb-wrap" dir="rtl">
	<h1 class="wp-heading-inline">فرم‌های ساخته‌شده</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=pfb-form-edit' ) ); ?>" class="page-title-action">افزودن فرم جدید</a>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>فرم مورد نظر حذف شد.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['duplicated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>فرم با موفقیت تکثیر شد.</p></div>
	<?php endif; ?>

	<table class="wp-list-table widefat fixed striped pfb-table">
		<thead>
			<tr>
				<th style="width:40px;">#</th>
				<th>عنوان فرم</th>
				<th style="width:120px;">وضعیت</th>
				<th style="width:320px;">شورت‌کد</th>
				<th style="width:220px;">عملیات</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $forms ) ) : ?>
				<tr><td colspan="5">هنوز هیچ فرمی ساخته نشده است.</td></tr>
			<?php else : ?>
				<?php foreach ( $forms as $form ) :
					$edit_url    = admin_url( 'admin.php?page=pfb-form-edit&id=' . $form->id );
					$entries_url = admin_url( 'admin.php?page=pfb-entries&form_id=' . $form->id );
					$delete_url  = wp_nonce_url( admin_url( 'admin.php?page=pfb-forms&action=delete&id=' . $form->id ), 'pfb_action_' . $form->id );
					$dup_url     = wp_nonce_url( admin_url( 'admin.php?page=pfb-forms&action=duplicate&id=' . $form->id ), 'pfb_action_' . $form->id );
				?>
				<tr>
					<td><?php echo (int) $form->id; ?></td>
					<td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $form->title ?: '(بدون عنوان)' ); ?></a></strong></td>
					<td>
						<?php if ( $form->status === 'active' ) : ?>
							<span class="pfb-badge pfb-badge-active">فعال</span>
						<?php else : ?>
							<span class="pfb-badge pfb-badge-inactive">غیرفعال</span>
						<?php endif; ?>
					</td>
					<td><code>[pfb_form id="<?php echo (int) $form->id; ?>"]</code></td>
					<td>
						<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">ویرایش</a>
						<a href="<?php echo esc_url( $entries_url ); ?>" class="button button-small">پاسخ‌ها</a>
						<a href="<?php echo esc_url( $dup_url ); ?>" class="button button-small">تکثیر</a>
						<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small pfb-btn-danger" onclick="return confirm('آیا از حذف این فرم مطمئن هستید؟ تمام پاسخ‌های آن نیز حذف می‌شود.');">حذف</a>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
