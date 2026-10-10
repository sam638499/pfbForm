<?php
/**
 * Template Name: فرم‌ساز — تمام صفحه (بدون هدر/فوتر قالب)
 * Description: قالب صفحه اختصاصی افزونه فرم‌ساز فارسی. هدر و فوتر قالب اصلی سایت را حذف می‌کند
 * و به‌جای آن یک فوتر مختصر و قابل تنظیم (توضیحات، شماره تماس) نمایش می‌دهد.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$pfb_settings = PFB_Fullscreen::get_settings();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<?php wp_head(); ?>
<style>
	html, body { margin: 0; padding: 0; min-height: 100%; }
	body.pfb-fullscreen-body { background: <?php echo esc_html( $pfb_settings['page_bg_color'] ); ?>; display: flex; flex-direction: column; min-height: 100vh; }
	.pfb-fullscreen-main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 16px; }
	.pfb-fullscreen-footer { background: <?php echo esc_html( $pfb_settings['footer_bg_color'] ); ?>; color: <?php echo esc_html( $pfb_settings['footer_text_color'] ); ?>; padding: 20px 16px; text-align: center; direction: rtl; font-family: Tahoma, "Vazirmatn", sans-serif; font-size: 13px; line-height: 2; }
	.pfb-fullscreen-footer a { color: inherit; }
	.pfb-fullscreen-footer .pfb-footer-contact { font-weight: 700; margin-top: 4px; }
</style>
</head>
<body <?php body_class( 'pfb-fullscreen-body' ); ?>>

	<main class="pfb-fullscreen-main">
		<?php
		while ( have_posts() ) :
			the_post();
			the_content();
		endwhile;
		?>
	</main>

	<?php if ( ! empty( $pfb_settings['footer_enable'] ) ) : ?>
	<footer class="pfb-fullscreen-footer">
		<?php if ( ! empty( $pfb_settings['footer_text'] ) ) : ?>
			<div class="pfb-footer-text"><?php echo wp_kses_post( nl2br( esc_html( $pfb_settings['footer_text'] ) ) ); ?></div>
		<?php endif; ?>
		<?php if ( ! empty( $pfb_settings['footer_phone'] ) || ! empty( $pfb_settings['footer_email'] ) ) : ?>
			<div class="pfb-footer-contact">
				<?php if ( ! empty( $pfb_settings['footer_phone'] ) ) : ?>
					<span>تماس با ما: <?php echo esc_html( $pfb_settings['footer_phone'] ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $pfb_settings['footer_phone'] ) && ! empty( $pfb_settings['footer_email'] ) ) : ?> | <?php endif; ?>
				<?php if ( ! empty( $pfb_settings['footer_email'] ) ) : ?>
					<span><?php echo esc_html( $pfb_settings['footer_email'] ); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</footer>
	<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
