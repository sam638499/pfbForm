jQuery( function( $ ) {

	var fieldTypes   = ( window.PFB_Admin_Data && PFB_Admin_Data.field_types ) || {};
	var existing     = window.PFB_EXISTING_FIELDS || [];
	var $list        = $( '#pfb-fields-list' );
	var rowCounter   = 0;

	function esc( str ) {
		return $( '<div>' ).text( str || '' ).html();
	}

	var EDITABLE_TYPES = [ 'text', 'select', 'radio', 'range' ];

	function typeSettingsHtml( type, options ) {
		options = options || {};
		if ( type === 'text' ) {
			var template = options.template || 'none';
			return '' +
				'<div class="full-width">' +
					'<label>نوع اعتبارسنجی متن</label>' +
					'<select class="pfb-input pfb-opt-template">' +
						'<option value="none"' + ( template === 'none' ? ' selected' : '' ) + '>بدون اعتبارسنجی خاص</option>' +
						'<option value="national_code"' + ( template === 'national_code' ? ' selected' : '' ) + '>کد ملی</option>' +
						'<option value="mobile"' + ( template === 'mobile' ? ' selected' : '' ) + '>شماره همراه</option>' +
						'<option value="numeric"' + ( template === 'numeric' ? ' selected' : '' ) + '>فقط اعداد</option>' +
					'</select>' +
				'</div>';
		}
		if ( type === 'select' || type === 'radio' ) {
			var choices = options.choices && options.choices.length ? options.choices.join( '\n' ) : '';
			return '' +
				'<div class="full-width">' +
					'<label>گزینه‌ها (هر گزینه در یک خط)</label>' +
					'<textarea class="pfb-choices-textarea pfb-opt-choices" placeholder="گزینه اول&#10;گزینه دوم&#10;گزینه سوم">' + esc( choices ) + '</textarea>' +
				'</div>';
		}
		if ( type === 'jalali_date' ) {
			return '<div class="full-width"><p class="description" style="margin:0;">این فیلد به‌صورت خودکار یک تقویم شمسی برای انتخاب تاریخ نمایش می‌دهد.</p></div>';
		}
		if ( type === 'image' ) {
			var categories = ( options.file_categories && options.file_categories.length ) ? options.file_categories : [ 'image' ];
			var hasImage = categories.indexOf( 'image' ) !== -1;
			var hasDocs = categories.indexOf( 'documents' ) !== -1;
			return '' +
				'<div class="full-width">' +
					'<label>نوع فایل‌های مجاز (حداقل یکی را انتخاب کنید)</label>' +
					'<div class="pfb-required-toggle">' +
						'<label><input type="checkbox" class="pfb-opt-filecat" value="image" ' + ( hasImage ? 'checked' : '' ) + ' /> تصویر (jpg, png, gif, webp) — حداکثر ۵ مگابایت</label>' +
					'</div>' +
					'<div class="pfb-required-toggle">' +
						'<label><input type="checkbox" class="pfb-opt-filecat" value="documents" ' + ( hasDocs ? 'checked' : '' ) + ' /> سند (Word, Excel, PDF, ZIP, RAR) — حداکثر ۲۰ مگابایت</label>' +
					'</div>' +
					'<p class="description" style="margin:8px 0 0;">متنی که در «توضیح راهنما» (بالای همین بخش) بنویسید، زیر دکمه‌ی آپلود به کاربر نشان داده می‌شود.</p>' +
				'</div>';
		}
		if ( type === 'car_plate' ) {
			return '<div class="full-width"><p class="description" style="margin:0;">پلاک خودرو ایرانی به‌صورت بصری و تعاملی نمایش داده می‌شود؛ حرف پلاک از یک لیست انتخاب می‌شود.</p></div>';
		}
		if ( type === 'range' ) {
			var min = options.min !== undefined && options.min !== '' ? options.min : 0;
			var max = options.max !== undefined && options.max !== '' ? options.max : 10;
			var step = options.step !== undefined && options.step !== '' ? options.step : 1;
			var minLabel = options.min_label || '';
			var maxLabel = options.max_label || '';
			return '' +
				'<div><label>حداقل مقدار</label><input type="number" class="pfb-input pfb-opt-min" value="' + esc( min ) + '" /></div>' +
				'<div><label>حداکثر مقدار</label><input type="number" class="pfb-input pfb-opt-max" value="' + esc( max ) + '" /></div>' +
				'<div><label>گام (Step)</label><input type="number" class="pfb-input pfb-opt-step" value="' + esc( step ) + '" /></div>' +
				'<div></div>' +
				'<div><label>برچسب ابتدای طیف (مثلاً کم)</label><input type="text" class="pfb-input pfb-opt-minlabel" value="' + esc( minLabel ) + '" /></div>' +
				'<div><label>برچسب انتهای طیف (مثلاً زیاد)</label><input type="text" class="pfb-input pfb-opt-maxlabel" value="' + esc( maxLabel ) + '" /></div>';
		}
		return '';
	}

	function buildFieldRow( field ) {
		rowCounter++;
		var uid = 'pfbnew_' + rowCounter;
		var typeLabel = fieldTypes[ field.type ] || field.type;

		if ( field.type === 'page_break' ) {
			var $pbRow = $( '<div class="pfb-field-row pfb-field-row-pagebreak"></div>' );
			$pbRow.attr( 'data-field-id', field.id || 0 );
			$pbRow.attr( 'data-uid', uid );
			$pbRow.attr( 'data-type', 'page_break' );

			var $pbHead = $( '<div class="pfb-field-row-head"></div>' );
			$pbHead.append( '<span class="dashicons dashicons-move" title="جابه‌جایی"></span>' );
			$pbHead.append( '<span class="pfb-field-row-type-badge pfb-badge-pagebreak">⤵ شکست صفحه</span>' );
			$pbHead.append( '<span class="pfb-pagebreak-note">کاربر با دکمه «بعدی» از این نقطه وارد صفحه جدیدی از فرم می‌شود</span>' );

			var $pbActions = $( '<div class="pfb-field-row-actions"></div>' );
			$pbActions.append( $( '<button type="button" class="button pfb-remove-field">حذف</button>' ) );
			$pbHead.append( $pbActions );

			$pbRow.append( $pbHead );
			return $pbRow;
		}

		var $row = $( '<div class="pfb-field-row"></div>' );
		$row.attr( 'data-field-id', field.id || 0 );
		$row.attr( 'data-uid', uid );
		$row.attr( 'data-type', field.type );

		var $head = $( '<div class="pfb-field-row-head"></div>' );
		$head.append( '<span class="dashicons dashicons-move" title="جابه‌جایی"></span>' );
		$head.append( '<span class="pfb-field-row-type-badge">' + esc( typeLabel ) + '</span>' );
		$head.append( '<input type="text" class="pfb-field-row-title-input pfb-opt-label" placeholder="عنوان فیلد" value="' + esc( field.label || '' ) + '" />' );

		var $actions = $( '<div class="pfb-field-row-actions"></div>' );
		var $toggle = $( '<button type="button" class="button pfb-toggle-row">تنظیمات</button>' );
		var $remove = $( '<button type="button" class="button pfb-remove-field">حذف</button>' );
		$actions.append( $toggle ).append( $remove );
		$head.append( $actions );

		var $body = $( '<div class="pfb-field-row-body"></div>' );
		var placeholderLabel = ( field.type === 'image' ) ? 'توضیح راهنما (زیر دکمه‌ی آپلود نمایش داده می‌شود)' : 'متن راهنما (Placeholder)';
		$body.append(
			'<div>' +
				'<label>' + placeholderLabel + '</label>' +
				'<input type="text" class="pfb-input pfb-opt-placeholder" value="' + esc( field.placeholder || '' ) + '" />' +
			'</div>'
		);
		$body.append(
			'<div>' +
				'<label>الزامی بودن</label>' +
				'<div class="pfb-required-toggle">' +
					'<label><input type="checkbox" class="pfb-opt-required" ' + ( field.required ? 'checked' : '' ) + ' /> پر کردن این فیلد اجباری باشد</label>' +
				'</div>' +
			'</div>'
		);
		if ( EDITABLE_TYPES.indexOf( field.type ) !== -1 ) {
			var isEditable = field.options && field.options.editable;
			$body.append(
				'<div>' +
					'<label>ویرایش در جدول خروجی</label>' +
					'<div class="pfb-required-toggle">' +
						'<label><input type="checkbox" class="pfb-opt-editable" ' + ( isEditable ? 'checked' : '' ) + ' /> این فیلد در صفحه‌ی پاسخ‌ها قابل ویرایش باشد</label>' +
					'</div>' +
				'</div>'
			);
		}
		$body.append(
			'<div class="full-width pfb-condition-block">' +
				'<label>نمایش شرطی این فیلد</label>' +
				'<select class="pfb-input pfb-cond-trigger"><option value="">بدون شرط (همیشه نمایش داده شود)</option></select>' +
				'<div class="pfb-cond-values-wrap"></div>' +
			'</div>'
		);
		$body.append( typeSettingsHtml( field.type, field.options ) );

		$row.append( $head ).append( $body );
		$row.data( 'savedCondition', ( field.options && field.options.condition ) || null );
		return $row;
	}

	/* ===================== نمایش شرطی فیلدها (اگر فیلد X برابر Y بود، این فیلد نشان داده شود) ===================== */

	// فقط فیلدهای کرکره‌ای/چندگزینه‌ای که «قبل از» فیلد جاری در لیست قرار دارند، می‌توانند محرک باشند
	// (به‌علاوه‌ی محرکی که همین الان برای این فیلد انتخاب شده، حتی اگر بعداً جابه‌جا شده باشد، تا انتخاب کاربر ناگهان از دست نرود)
	function getEligibleTriggers( $currentRow ) {
		var eligible = [];
		var $rows = $list.children( '.pfb-field-row' );
		var currentIndex = $rows.index( $currentRow );
		var currentSelectedUid = $currentRow.find( '.pfb-cond-trigger' ).val();

		$rows.each( function( idx ) {
			var $r = $( this );
			if ( $r.is( $currentRow ) ) return;
			var rType = $r.data( 'type' );
			if ( rType !== 'select' && rType !== 'radio' ) return;
			var rUid = $r.attr( 'data-uid' );
			var isBefore = idx < currentIndex;
			if ( ! isBefore && rUid !== currentSelectedUid ) return;
			eligible.push( {
				uid: rUid,
				label: $r.find( '.pfb-opt-label' ).val() || '(بدون عنوان)'
			} );
		} );
		return eligible;
	}

	// بازسازی چک‌باکس‌های «کدام گزینه‌(ها) این فیلد را نمایش بدهند» بر اساس گزینه‌های فیلد محرکِ فعلاً انتخاب‌شده
	function refreshConditionValues( $row, presetValues ) {
		var $wrap = $row.find( '.pfb-cond-values-wrap' );
		var triggerUid = $row.find( '.pfb-cond-trigger' ).val();
		if ( ! triggerUid ) { $wrap.empty(); return; }

		var $triggerRow = $list.children( '[data-uid="' + triggerUid + '"]' );
		if ( ! $triggerRow.length ) { $wrap.empty(); return; }

		var choicesRaw = $triggerRow.find( '.pfb-opt-choices' ).val() || '';
		var choices = choicesRaw.split( '\n' ).map( function( s ) { return s.trim(); } ).filter( function( s ) { return s.length; } );

		var previouslyChecked = presetValues;
		if ( ! previouslyChecked ) {
			previouslyChecked = [];
			$wrap.find( '.pfb-cond-value-checkbox:checked' ).each( function() { previouslyChecked.push( $( this ).val() ); } );
		}

		if ( ! choices.length ) {
			$wrap.html( '<p class="description" style="margin:4px 0 0;">این فیلد محرک هنوز گزینه‌ای ندارد.</p>' );
			return;
		}

		var html = '';
		choices.forEach( function( choice ) {
			var isChecked = previouslyChecked.indexOf( choice ) !== -1;
			html += '<label class="pfb-cond-value-item"><input type="checkbox" class="pfb-cond-value-checkbox" value="' + esc( choice ) + '" ' + ( isChecked ? 'checked' : '' ) + ' /> ' + esc( choice ) + '</label>';
		} );
		$wrap.html( html );
	}

	// بازسازی لیست فیلدهای محرکِ قابل‌انتخاب برای همه‌ی ردیف‌ها — هرگاه فیلدی اضافه/حذف/جابه‌جا شود یا برچسب/گزینه‌های یک فیلد تغییر کند فراخوانی می‌شود
	function refreshConditionTriggerOptions() {
		$list.children( '.pfb-field-row' ).each( function() {
			var $row = $( this );
			var $select = $row.find( '.pfb-cond-trigger' );
			if ( ! $select.length ) return; // ردیف شکست صفحه چنین بخشی ندارد

			var currentUid = $select.val();
			var eligible = getEligibleTriggers( $row );

			var html = '<option value="">بدون شرط (همیشه نمایش داده شود)</option>';
			eligible.forEach( function( t ) {
				html += '<option value="' + t.uid + '">' + esc( t.label ) + '</option>';
			} );
			$select.html( html );

			var stillEligible = eligible.some( function( t ) { return t.uid === currentUid; } );
			$select.val( stillEligible ? currentUid : '' );
			refreshConditionValues( $row );
		} );
	}

	// فقط یک‌بار، بلافاصله پس از بارگذاری فیلدهای موجود: شرط‌های ذخیره‌شده (که بر اساس شناسه‌ی واقعی فیلد محرک هستند) را بازیابی می‌کند
	function restoreSavedConditions() {
		$list.children( '.pfb-field-row' ).each( function() {
			var $row = $( this );
			var saved = $row.data( 'savedCondition' );
			if ( ! saved || ! saved.field_id ) return;

			var $triggerRow = $list.children( '.pfb-field-row' ).filter( function() {
				var fid = parseInt( $( this ).attr( 'data-field-id' ), 10 ) || 0;
				return fid !== 0 && fid === saved.field_id;
			} );
			if ( ! $triggerRow.length ) return; // فیلد محرک اصلی دیگر وجود ندارد

			var triggerUid = $triggerRow.attr( 'data-uid' );
			var $select = $row.find( '.pfb-cond-trigger' );
			if ( $select.find( 'option[value="' + triggerUid + '"]' ).length ) {
				$select.val( triggerUid );
				refreshConditionValues( $row, saved.values || [] );
			}
		} );
	}

	function refreshEmptyMsg() {
		$list.find( '.pfb-empty-fields-msg' ).remove();
		if ( $list.children( '.pfb-field-row' ).length === 0 ) {
			$list.append( '<div class="pfb-empty-fields-msg">هنوز هیچ فیلدی اضافه نشده است. از دکمه‌های بالا یک فیلد اضافه کنید.</div>' );
		}
	}

	// بارگذاری فیلدهای موجود
	existing.forEach( function( field ) {
		$list.append( buildFieldRow( field ) );
	} );
	refreshEmptyMsg();
	refreshConditionTriggerOptions();
	restoreSavedConditions();

	/* ===================== ستون‌های وضعیت (تیک‌خوردنی‌های قابل مدیریت) ===================== */

	var $flagsList    = $( '#pfb-flags-list' );
	var existingFlags = window.PFB_EXISTING_FLAGS || [];
	var flagRowCounter = 0;

	function buildFlagRow( flag ) {
		flagRowCounter++;
		var $row = $( '<div class="pfb-flag-row"></div>' );
		$row.attr( 'data-flag-id', flag.id || 0 );
		$row.attr( 'data-uid', 'pfbflag_' + flagRowCounter );
		$row.append( '<span class="dashicons dashicons-move" title="جابه‌جایی"></span>' );
		$row.append( '<input type="text" class="pfb-input pfb-flag-label" placeholder="مثلاً: تحویل دفتر" value="' + esc( flag.label || '' ) + '" />' );
		$row.append( '<button type="button" class="button pfb-remove-flag">حذف</button>' );
		return $row;
	}

	function refreshFlagsEmptyMsg() {
		$flagsList.find( '.pfb-empty-flags-msg' ).remove();
		if ( $flagsList.children( '.pfb-flag-row' ).length === 0 ) {
			$flagsList.append( '<div class="pfb-empty-fields-msg pfb-empty-flags-msg">هنوز هیچ ستون وضعیتی اضافه نشده است.</div>' );
		}
	}

	existingFlags.forEach( function( flag ) {
		$flagsList.append( buildFlagRow( flag ) );
	} );
	refreshFlagsEmptyMsg();

	$( '#pfb-add-flag-btn' ).on( 'click', function() {
		$flagsList.find( '.pfb-empty-flags-msg' ).remove();
		var $row = buildFlagRow( { id: 0, label: '' } );
		$flagsList.append( $row );
		$row.find( '.pfb-flag-label' ).trigger( 'focus' );
	} );

	$flagsList.on( 'click', '.pfb-remove-flag', function() {
		if ( confirm( 'این ستون وضعیت حذف شود؟ تیک‌های ثبت‌شده‌ی آن هم برای همیشه پاک می‌شود.' ) ) {
			$( this ).closest( '.pfb-flag-row' ).remove();
			refreshFlagsEmptyMsg();
		}
	} );

	if ( $flagsList.length ) {
		$flagsList.sortable( { handle: '.dashicons-move', axis: 'y' } );
	}

	// افزودن فیلد جدید
	$( '.pfb-add-field-btn' ).on( 'click', function() {
		var type = $( this ).data( 'type' );
		$list.find( '.pfb-empty-fields-msg' ).remove();
		var $row = buildFieldRow( { id: 0, type: type, label: '', placeholder: '', required: false, options: {} } );
		$list.append( $row );
		$row.get( 0 ).scrollIntoView( { behavior: 'smooth', block: 'center' } );
		refreshConditionTriggerOptions();
	} );

	// حذف فیلد
	$list.on( 'click', '.pfb-remove-field', function() {
		if ( confirm( 'این فیلد حذف شود؟' ) ) {
			$( this ).closest( '.pfb-field-row' ).remove();
			refreshEmptyMsg();
			refreshConditionTriggerOptions();
		}
	} );

	// نمایش/مخفی کردن بدنه تنظیمات
	$list.on( 'click', '.pfb-toggle-row', function() {
		$( this ).closest( '.pfb-field-row' ).find( '.pfb-field-row-body' ).slideToggle( 120 );
	} );

	// مرتب‌سازی با درگ اند دراپ
	$list.sortable( {
		handle: '.pfb-field-row-head',
		placeholder: 'pfb-sorting-placeholder',
		axis: 'y',
		update: function() { refreshConditionTriggerOptions(); }
	} );

	// تغییر برچسب یا گزینه‌های یک فیلد ممکن است روی فیلدهای وابسته به آن (به‌عنوان محرک) اثر بگذارد
	$list.on( 'input', '.pfb-opt-label, .pfb-opt-choices', function() {
		refreshConditionTriggerOptions();
	} );

	// انتخاب یک فیلد محرک جدید برای نمایش شرطی: لیست گزینه‌های قابل‌تیک را بازسازی کن
	$list.on( 'change', '.pfb-cond-trigger', function() {
		refreshConditionValues( $( this ).closest( '.pfb-field-row' ) );
	} );

	// تب‌ها
	$( '.pfb-tab-btn' ).on( 'click', function() {
		var tab = $( this ).data( 'tab' );
		$( '.pfb-tab-btn' ).removeClass( 'active' );
		$( this ).addClass( 'active' );
		$( '.pfb-tab-panel' ).removeClass( 'active' );
		$( '.pfb-tab-panel[data-panel="' + tab + '"]' ).addClass( 'active' );
	} );

	// انتخاب تصویر خوش‌آمدگویی
	var mediaFrame;
	$( '#pfb_choose_image' ).on( 'click', function( e ) {
		e.preventDefault();
		if ( mediaFrame ) {
			mediaFrame.open();
			return;
		}
		mediaFrame = wp.media( { title: 'انتخاب تصویر', button: { text: 'استفاده از این تصویر' }, multiple: false } );
		mediaFrame.on( 'select', function() {
			var attachment = mediaFrame.state().get( 'selection' ).first().toJSON();
			$( '#pfb_welcome_image_url' ).val( attachment.url );
			$( '#pfb_welcome_image_preview' ).attr( 'src', attachment.url ).show();
			$( '#pfb_remove_image' ).show();
		} );
		mediaFrame.open();
	} );
	$( '#pfb_remove_image' ).on( 'click', function() {
		$( '#pfb_welcome_image_url' ).val( '' );
		$( '#pfb_welcome_image_preview' ).attr( 'src', '' ).hide();
		$( this ).hide();
	} );

	// جمع‌آوری داده فیلدها قبل از ثبت فرم
	$( '#pfb-form-editor' ).on( 'submit', function() {
		// همگام‌سازی نهایی متن‌های غنی با فیلدهای مخفی مربوطه
		$( '.pfb-richtext-editable' ).each( function() {
			$( this ).siblings( '.pfb-richtext-source' ).val( $( this ).html() );
		} );

		var data = [];
		$list.find( '.pfb-field-row' ).each( function() {
			var $row = $( this );
			var type = $row.data( 'type' );
			var options = {};

			if ( type === 'text' ) {
				options.template = $row.find( '.pfb-opt-template' ).val();
			}
			if ( type === 'select' || type === 'radio' ) {
				var raw = $row.find( '.pfb-opt-choices' ).val() || '';
				options.choices = raw.split( '\n' ).map( function( s ) { return s.trim(); } ).filter( function( s ) { return s.length; } );
			}
			if ( type === 'range' ) {
				options.min = $row.find( '.pfb-opt-min' ).val();
				options.max = $row.find( '.pfb-opt-max' ).val();
				options.step = $row.find( '.pfb-opt-step' ).val();
				options.min_label = $row.find( '.pfb-opt-minlabel' ).val();
				options.max_label = $row.find( '.pfb-opt-maxlabel' ).val();
			}
			if ( type === 'image' ) {
				var cats = [];
				$row.find( '.pfb-opt-filecat:checked' ).each( function() { cats.push( $( this ).val() ); } );
				if ( ! cats.length ) cats = [ 'image' ]; // اگر کاربر هیچ‌کدام را انتخاب نکرد، حداقل تصویر مجاز بماند
				options.file_categories = cats;
			}
			if ( EDITABLE_TYPES.indexOf( type ) !== -1 ) {
				options.editable = $row.find( '.pfb-opt-editable' ).is( ':checked' ) ? 1 : 0;
			}

			// نمایش شرطی: فقط اگر یک فیلد محرک انتخاب شده و حداقل یک گزینه تیک خورده باشد ذخیره می‌شود
			var condTriggerUid = $row.find( '.pfb-cond-trigger' ).val();
			if ( condTriggerUid ) {
				var condValues = [];
				$row.find( '.pfb-cond-value-checkbox:checked' ).each( function() { condValues.push( $( this ).val() ); } );
				if ( condValues.length ) {
					options.condition = { trigger_uid: condTriggerUid, values: condValues };
				}
			}

			data.push( {
				id: parseInt( $row.attr( 'data-field-id' ), 10 ) || 0,
				uid: $row.attr( 'data-uid' ) || '',
				type: type,
				label: $row.find( '.pfb-opt-label' ).val() || '',
				placeholder: $row.find( '.pfb-opt-placeholder' ).val() || '',
				required: $row.find( '.pfb-opt-required' ).is( ':checked' ),
				options: options
			} );
		} );
		$( '#pfb_fields_json' ).val( JSON.stringify( data ) );

		var flagsData = [];
		$flagsList.find( '.pfb-flag-row' ).each( function() {
			var $row = $( this );
			var label = $row.find( '.pfb-flag-label' ).val() || '';
			if ( ! label.trim() ) return; // ردیف‌های بدون عنوان نادیده گرفته می‌شوند
			flagsData.push( {
				id: parseInt( $row.attr( 'data-flag-id' ), 10 ) || 0,
				label: label.trim()
			} );
		} );
		$( '#pfb_flags_json' ).val( JSON.stringify( flagsData ) );
	} );

	// راه‌اندازی انتخاب‌گر رنگ (برای تنظیمات تمام‌صفحه)
	if ( $.fn.wpColorPicker ) {
		$( '.pfb-color-picker' ).wpColorPicker();
	}

	// راه‌اندازی ویرایشگر متن غنی (بولد، چینش، رنگ، اندازه)
	$( '.pfb-richtext-wrap' ).on( 'click', '.pfb-rt-btn', function( e ) {
		e.preventDefault();
		var $wrap = $( this ).closest( '.pfb-richtext-wrap' );
		var $editable = $wrap.find( '.pfb-richtext-editable' );
		$editable.trigger( 'focus' );
		document.execCommand( $( this ).data( 'cmd' ), false, null );
		$editable.trigger( 'input' );
	} );

	$( '.pfb-richtext-wrap' ).on( 'input', '.pfb-rt-color', function() {
		var $wrap = $( this ).closest( '.pfb-richtext-wrap' );
		var $editable = $wrap.find( '.pfb-richtext-editable' );
		$editable.trigger( 'focus' );
		document.execCommand( 'foreColor', false, $( this ).val() );
		$editable.trigger( 'input' );
	} );

	$( '.pfb-richtext-wrap' ).on( 'change', '.pfb-rt-size', function() {
		var val = $( this ).val();
		if ( ! val ) return;
		var $wrap = $( this ).closest( '.pfb-richtext-wrap' );
		var $editable = $wrap.find( '.pfb-richtext-editable' );
		$editable.trigger( 'focus' );
		document.execCommand( 'fontSize', false, val );
		$editable.trigger( 'input' );
		$( this ).val( '' );
	} );

	$( '.pfb-richtext-wrap' ).on( 'input', '.pfb-richtext-editable', function() {
		$( this ).siblings( '.pfb-richtext-source' ).val( $( this ).html() );
	} );

	// جلوگیری از Enter در فیلدهای تک‌خطی مثل عنوان
	$( '.pfb-richtext-wrap' ).on( 'keydown', '.pfb-richtext-oneline', function( e ) {
		if ( e.which === 13 ) e.preventDefault();
	} );

} );
