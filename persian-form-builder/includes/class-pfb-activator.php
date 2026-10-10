<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PFB_Activator {

	public static function activate() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$forms_table         = $wpdb->prefix . PFB_TABLE_FORMS;
		$fields_table         = $wpdb->prefix . PFB_TABLE_FIELDS;
		$entries_table        = $wpdb->prefix . PFB_TABLE_ENTRIES;
		$entry_values_table   = $wpdb->prefix . PFB_TABLE_ENTRY_VALUES;
		$flags_table          = $wpdb->prefix . PFB_TABLE_FLAGS;
		$entry_flags_table    = $wpdb->prefix . PFB_TABLE_ENTRY_FLAGS;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql1 = "CREATE TABLE $forms_table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL DEFAULT '',
			description TEXT NULL,
			welcome_image_url VARCHAR(500) NULL,
			welcome_title TEXT NULL,
			welcome_description TEXT NULL,
			end_title TEXT NULL,
			end_description TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$sql2 = "CREATE TABLE $fields_table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL,
			field_type VARCHAR(50) NOT NULL,
			label VARCHAR(255) NOT NULL DEFAULT '',
			placeholder VARCHAR(255) NULL,
			is_required TINYINT(1) NOT NULL DEFAULT 0,
			options LONGTEXT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY form_id (form_id)
		) $charset_collate;";

		$sql3 = "CREATE TABLE $entries_table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			ip VARCHAR(100) NULL,
			delivered TINYINT(1) NOT NULL DEFAULT 0,
			issued TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY form_id (form_id)
		) $charset_collate;";

		$sql4 = "CREATE TABLE $entry_values_table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			entry_id BIGINT UNSIGNED NOT NULL,
			field_id BIGINT UNSIGNED NOT NULL,
			value LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY entry_id (entry_id),
			KEY field_id (field_id)
		) $charset_collate;";

		$sql5 = "CREATE TABLE $flags_table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL,
			label VARCHAR(255) NOT NULL DEFAULT '',
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY form_id (form_id)
		) $charset_collate;";

		$sql6 = "CREATE TABLE $entry_flags_table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			entry_id BIGINT UNSIGNED NOT NULL,
			flag_id BIGINT UNSIGNED NOT NULL,
			value TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY entry_id (entry_id),
			KEY flag_id (flag_id)
		) $charset_collate;";

		dbDelta( $sql1 );
		dbDelta( $sql2 );
		dbDelta( $sql3 );
		dbDelta( $sql4 );
		dbDelta( $sql5 );
		dbDelta( $sql6 );

		self::migrate_legacy_flags();

		update_option( 'pfb_db_version', PFB_VERSION );
	}

	/**
	 * انتقال یک‌بارهٔ ستون‌های قدیمی delivered/issued (که ثابت و غیرقابل‌تغییر بودند)
	 * به سامانهٔ جدید و انعطاف‌پذیر «ستون‌های وضعیت» که هر فرم می‌تواند مجموعهٔ دلخواه خودش را داشته باشد.
	 * فقط یک‌بار اجرا می‌شود و داده‌های موجود کاربران را حفظ می‌کند.
	 */
	private static function migrate_legacy_flags() {
		if ( get_option( 'pfb_legacy_flags_migrated' ) ) {
			return;
		}

		global $wpdb;
		$forms_table       = $wpdb->prefix . PFB_TABLE_FORMS;
		$entries_table     = $wpdb->prefix . PFB_TABLE_ENTRIES;
		$flags_table       = $wpdb->prefix . PFB_TABLE_FLAGS;
		$entry_flags_table = $wpdb->prefix . PFB_TABLE_ENTRY_FLAGS;

		$forms = $wpdb->get_results( "SELECT id FROM $forms_table" );

		foreach ( $forms as $form ) {
			$has_entries = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $entries_table WHERE form_id = %d", $form->id ) );
			if ( ! $has_entries ) {
				continue;
			}

			$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $flags_table WHERE form_id = %d", $form->id ) );
			if ( $existing ) {
				continue; // این فرم قبلاً ستون وضعیت دارد، احتمالاً قبلاً مهاجرت شده
			}

			$wpdb->insert( $flags_table, array( 'form_id' => $form->id, 'label' => 'تحویل دفتر', 'sort_order' => 0 ) );
			$delivered_flag_id = $wpdb->insert_id;
			$wpdb->insert( $flags_table, array( 'form_id' => $form->id, 'label' => 'صدور', 'sort_order' => 1 ) );
			$issued_flag_id = $wpdb->insert_id;

			$entries = $wpdb->get_results( $wpdb->prepare( "SELECT id, delivered, issued FROM $entries_table WHERE form_id = %d", $form->id ) );
			foreach ( $entries as $entry ) {
				$wpdb->insert( $entry_flags_table, array( 'entry_id' => $entry->id, 'flag_id' => $delivered_flag_id, 'value' => (int) $entry->delivered ) );
				$wpdb->insert( $entry_flags_table, array( 'entry_id' => $entry->id, 'flag_id' => $issued_flag_id, 'value' => (int) $entry->issued ) );
			}
		}

		update_option( 'pfb_legacy_flags_migrated', 1 );
	}
}
