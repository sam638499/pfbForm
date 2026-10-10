jQuery( function( $ ) {

	// ورود با رمز عبور
	$( document ).on( 'submit', '.pfb-wv-login-form', function( e ) {
		e.preventDefault();
		var $form = $( this );
		var $btn  = $form.find( '.pfb-wv-btn-primary' );
		var $err  = $form.find( '.pfb-wv-login-error' );
		var pass  = $form.find( '.pfb-wv-password-input' ).val();

		$err.hide().text( '' );
		$btn.prop( 'disabled', true ).text( 'در حال بررسی...' );

		$.post( PFB_Webview_Data.ajax_url, {
			action: 'pfb_webview_login',
			password: pass,
			nonce: PFB_Webview_Data.nonce
		} ).done( function( res ) {
			if ( res.success ) {
				location.reload();
			} else {
				$err.text( ( res.data && res.data.message ) || 'رمز عبور اشتباه است.' ).show();
			}
		} ).fail( function() {
			$err.text( 'خطا در ارتباط با سرور. دوباره تلاش کنید.' ).show();
		} ).always( function() {
			$btn.prop( 'disabled', false ).text( 'ورود' );
		} );
	} );

	// خروج
	$( document ).on( 'click', '.pfb-wv-logout-btn', function() {
		$.post( PFB_Webview_Data.ajax_url, { action: 'pfb_webview_logout' } ).always( function() {
			location.reload();
		} );
	} );

	// تغییر فرم انتخابی از لیست کشویی
	$( document ).on( 'change', '#pfb-wv-form-picker', function() {
		var formId = $( this ).val();
		var url = window.location.href.split( '#' )[ 0 ];
		var separator = url.indexOf( '?' ) === -1 ? '?' : '&';
		if ( /[?&]pfb_form=\d+/.test( url ) ) {
			url = url.replace( /([?&]pfb_form=)\d+/, '$1' + formId );
		} else {
			url = url + separator + 'pfb_form=' + formId;
		}
		window.location.href = url;
	} );

	// تغییر وضعیت تیک یک ستون وضعیت
	$( document ).on( 'change', '.pfb-wv-flag', function() {
		var $cb = $( this );
		var $row = $cb.closest( 'tr' );
		var entryId = $row.data( 'entry-id' );
		var flagId = $cb.data( 'flag-id' );
		var value = $cb.is( ':checked' ) ? 1 : 0;
		var wasChecked = ! $cb.is( ':checked' );

		$.post( PFB_Webview_Data.ajax_url, {
			action: 'pfb_webview_toggle',
			entry_id: entryId,
			flag_id: flagId,
			value: value,
			nonce: PFB_Webview_Data.nonce
		} ).done( function( res ) {
			if ( res.success ) {
				$cb.siblings( '.pfb-wv-print-only' ).text( value ? '✓' : '—' );
				updateRowStatus( $row );
			} else {
				$cb.prop( 'checked', wasChecked );
				alert( ( res.data && res.data.message ) || 'خطا در ذخیره تغییرات.' );
			}
		} ).fail( function() {
			$cb.prop( 'checked', wasChecked );
			alert( 'خطا در ارتباط با سرور.' );
		} );
	} );

	// رنگ‌آمیزی سطر: هیچ‌کدام تیک نخورده = بدون رنگ، بخشی تیک خورده = زرد، همه تیک خورده = سبز
	function updateRowStatus( $row ) {
		var $flags = $row.find( '.pfb-wv-flag' );
		var total = $flags.length;
		var checkedCount = $flags.filter( ':checked' ).length;
		var cls = 'pfb-wv-status-none';
		if ( total > 0 && checkedCount === total ) cls = 'pfb-wv-status-complete';
		else if ( checkedCount > 0 ) cls = 'pfb-wv-status-partial';
		$row.removeClass( 'pfb-wv-status-none pfb-wv-status-partial pfb-wv-status-complete' ).addClass( cls );
		recomputeSummary();
	}

	/* ===================== ویرایش مستقیم مقدار فیلدهای قابل‌ویرایش در جدول ===================== */

	$( document ).on( 'change', '.pfb-wv-editable-input', function() {
		var $input = $( this );
		var $cell = $input.closest( 'td' );
		var entryId = $input.data( 'entry-id' );
		var fieldId = $input.data( 'field-id' );
		var newValue = $input.val();
		var previousValue = $input.attr( 'data-last-value' ) !== undefined ? $input.attr( 'data-last-value' ) : newValue;

		$input.prop( 'disabled', true );

		$.post( PFB_Webview_Data.ajax_url, {
			action: 'pfb_webview_update_field',
			entry_id: entryId,
			field_id: fieldId,
			value: newValue,
			nonce: PFB_Webview_Data.nonce
		} ).done( function( res ) {
			if ( res.success ) {
				var savedValue = ( res.data && typeof res.data.value !== 'undefined' ) ? res.data.value : newValue;
				$input.attr( 'data-last-value', savedValue );
				if ( $input.is( 'input' ) ) $input.val( savedValue );
				$cell.find( '.pfb-wv-print-only' ).text( savedValue );
				flashSaved( $cell );
			} else {
				$input.val( previousValue );
				alert( ( res.data && res.data.message ) || 'خطا در ذخیره تغییرات.' );
			}
		} ).fail( function() {
			$input.val( previousValue );
			alert( 'خطا در ارتباط با سرور.' );
		} ).always( function() {
			$input.prop( 'disabled', false );
		} );
	} );

	function flashSaved( $cell ) {
		$cell.addClass( 'pfb-wv-cell-saved' );
		setTimeout( function() { $cell.removeClass( 'pfb-wv-cell-saved' ); }, 900 );
	}

	/* ===================== فیلتر هر ستون (ردیف دوم سربرگ) ===================== */

	$( document ).on( 'input', '.pfb-wv-filter-input', function() {
		var $table = $( '.pfb-wv-table' );
		var $filterInputs = $table.find( '.pfb-wv-filter-row .pfb-wv-filter-input' );

		// جمع‌آوری فیلترهای فعال به همراه اندیس ستونشان (بر اساس ترتیب فیزیکی سلول‌ها)
		var activeFilters = [];
		$filterInputs.each( function() {
			var val = $( this ).val().trim().toLowerCase();
			if ( ! val ) return;
			var colIndex = $( this ).closest( 'th' ).index();
			activeFilters.push( { index: colIndex, value: val } );
		} );

		$table.find( 'tbody tr[data-entry-id]' ).each( function() {
			var $row = $( this );
			var $cells = $row.children();
			var visible = true;
			for ( var i = 0; i < activeFilters.length; i++ ) {
				var cellText = ( $cells.eq( activeFilters[ i ].index ).text() || '' ).toLowerCase();
				if ( cellText.indexOf( activeFilters[ i ].value ) === -1 ) {
					visible = false;
					break;
				}
			}
			$row.toggle( visible );
		} );

		recomputeSummary();
	} );

	/* ===================== جمع‌های بالای جدول (تعداد ردیف و تعداد هر ستون وضعیت تیک‌خورده) — هماهنگ با فیلتر ===================== */

	function recomputeSummary() {
		var $visibleRows = $( '.pfb-wv-table tbody tr[data-entry-id]' ).filter( function() {
			return $( this ).css( 'display' ) !== 'none';
		} );
		$( '.pfb-wv-summary-total' ).text( $visibleRows.length ? ( 'مجموع: ' + $visibleRows.length ) : '' );
		$( '.pfb-wv-summary-flag' ).each( function() {
			var flagId = $( this ).data( 'flag-id' );
			var count = $visibleRows.find( '.pfb-wv-flag[data-flag-id="' + flagId + '"]:checked' ).length;
			$( this ).text( count ? ( 'مجموع: ' + count ) : '' );
		} );
	}

	/* ===================== تغییر اندازه‌ی دستی ستون‌ها با کشیدن لبه ===================== */

	(function() {
		var $table = $( '.pfb-wv-table' );
		var resizing = false, $resizingTh = null, startX = 0, startWidth = 0;

		function freezeColumnWidths() {
			if ( $table.data( 'pfb-frozen' ) ) return;
			$table.css( 'table-layout', 'fixed' );
			$table.find( '.pfb-wv-header-row' ).children().each( function() {
				$( this ).css( 'width', $( this ).outerWidth() + 'px' );
			} );
			$table.data( 'pfb-frozen', true );
		}

		$( document ).on( 'mousedown', '.pfb-wv-col-resizer', function( e ) {
			resizing = true;
			$resizingTh = $( this ).closest( 'th' );
			startX = e.pageX;
			startWidth = $resizingTh.outerWidth();
			freezeColumnWidths();
			$( 'body' ).css( 'user-select', 'none' );
			e.preventDefault();
		} );

		$( document ).on( 'mousemove', function( e ) {
			if ( ! resizing ) return;
			// در چیدمان راست‌به‌چپ، کشیدن به سمت چپ عرض ستون را افزایش می‌دهد
			var delta = startX - e.pageX;
			var newWidth = Math.max( 50, startWidth + delta );
			$resizingTh.css( 'width', newWidth + 'px' );
		} );

		$( document ).on( 'mouseup', function() {
			if ( resizing ) {
				resizing = false;
				$( 'body' ).css( 'user-select', '' );
			}
		} );
	})();

	/* ===================== انتخاب ردیف برای چاپ (تکی، همه، یا محدوده با Shift) ===================== */

	var lastCheckedRowIndex = null;

	$( document ).on( 'change', '.pfb-wv-select-all', function() {
		var checked = $( this ).is( ':checked' );
		$( '.pfb-wv-table tbody tr[data-entry-id]:visible' ).find( '.pfb-wv-select-row' ).prop( 'checked', checked );
	} );

	$( document ).on( 'click', '.pfb-wv-select-row', function( e ) {
		var $rows = $( '.pfb-wv-table tbody tr[data-entry-id]:visible' );
		var currentIndex = $rows.index( $( this ).closest( 'tr' ) );

		if ( e.shiftKey && lastCheckedRowIndex !== null ) {
			var start = Math.min( lastCheckedRowIndex, currentIndex );
			var end = Math.max( lastCheckedRowIndex, currentIndex );
			$rows.slice( start, end + 1 ).find( '.pfb-wv-select-row' ).prop( 'checked', true );
		}
		lastCheckedRowIndex = currentIndex;
	} );

	/* ===================== چاپ جدول (کامل یا فقط ردیف‌های انتخاب‌شده) ===================== */

	$( document ).on( 'click', '.pfb-wv-print-btn', function() {
		var $rows = $( '.pfb-wv-table tbody tr' );
		var $selectedRows = $( '.pfb-wv-select-row:checked' ).closest( 'tr' );
		if ( $selectedRows.length > 0 ) {
			$rows.addClass( 'pfb-wv-print-hide' );
			$selectedRows.removeClass( 'pfb-wv-print-hide' );
		} else {
			$rows.removeClass( 'pfb-wv-print-hide' );
		}
		window.print();
	} );

	$( window ).on( 'afterprint', function() {
		$( '.pfb-wv-table tbody tr' ).removeClass( 'pfb-wv-print-hide' );
	} );

	// محاسبه‌ی اولیه‌ی جمع‌ها هنگام بارگذاری صفحه
	recomputeSummary();

	/* ===================== تنظیم دقیق فاصله‌ی چسبندگی سربرگ‌ها (۳ ردیف: عنوان/فیلتر/جمع) ===================== */

	function updateStickyOffsets() {
		var offset = 0;
		$( '.pfb-wv-table thead tr' ).each( function() {
			$( this ).find( 'th' ).css( 'top', offset + 'px' );
			offset += $( this ).outerHeight();
		} );
	}

	updateStickyOffsets();
	$( window ).on( 'resize', updateStickyOffsets );

} );
