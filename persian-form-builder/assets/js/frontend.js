( function( $ ) {

	/* ================= تبدیل ارقام فارسی/عربی/انگلیسی ================= */

	var FA_DIGITS = [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ];
	var AR_DIGITS = [ '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ];

	function digitsToEn( str ) {
		str = String( str || '' );
		for ( var i = 0; i < 10; i++ ) {
			str = str.split( FA_DIGITS[ i ] ).join( i ).split( AR_DIGITS[ i ] ).join( i );
		}
		return str;
	}

	function digitsToFa( str ) {
		str = digitsToEn( str );
		var out = '';
		for ( var i = 0; i < str.length; i++ ) {
			var ch = str[ i ];
			out += ( ch >= '0' && ch <= '9' ) ? FA_DIGITS[ parseInt( ch, 10 ) ] : ch;
		}
		return out;
	}

	/* ================= تبدیل تاریخ میلادی به شمسی و برعکس ================= */

	function div( a, b ) { return Math.floor( a / b ); }

	function g2j( gy, gm, gd ) {
		var g_d_m = [ 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 ];
		var jy = ( gy <= 1600 ) ? 0 : 979;
		gy -= ( gy <= 1600 ) ? 621 : 1600;
		var gy2 = ( gm > 2 ) ? ( gy + 1 ) : gy;
		var days = ( 365 * gy ) + div( ( gy2 + 3 ), 4 ) - div( ( gy2 + 99 ), 100 ) + div( ( gy2 + 399 ), 400 ) - 80 + gd + g_d_m[ gm - 1 ];
		jy += 33 * div( days, 12053 );
		days %= 12053;
		jy += 4 * div( days, 1461 );
		days %= 1461;
		var jm, jd;
		if ( days > 365 ) {
			jy += div( ( days - 1 ), 365 );
			days = ( days - 1 ) % 365;
		}
		if ( days < 186 ) {
			jm = 1 + div( days, 31 );
			jd = 1 + ( days % 31 );
		} else {
			jm = 7 + div( ( days - 186 ), 30 );
			jd = 1 + ( ( days - 186 ) % 30 );
		}
		return [ jy, jm, jd ];
	}

	function j2g( jy, jm, jd ) {
		var gy = ( jy <= 979 ) ? 621 : 1600;
		jy -= ( jy <= 979 ) ? 0 : 979;
		var days = ( 365 * jy ) + ( div( jy, 33 ) * 8 ) + div( ( ( jy % 33 ) + 3 ), 4 ) + 78 + jd + ( ( jm < 7 ) ? ( jm - 1 ) * 31 : ( ( jm - 7 ) * 30 ) + 186 );
		gy += 400 * div( days, 146097 );
		days %= 146097;
		if ( days > 36524 ) {
			gy += 100 * div( --days, 36524 );
			days %= 36524;
			if ( days >= 365 ) days++;
		}
		gy += 4 * div( days, 1461 );
		days %= 1461;
		if ( days > 365 ) {
			gy += div( ( days - 1 ), 365 );
			days = ( days - 1 ) % 365;
		}
		var gd = days + 1;
		var sal_a = [ 0, 31, ( ( gy % 4 === 0 && gy % 100 !== 0 ) || ( gy % 400 === 0 ) ) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];
		var gm;
		for ( gm = 0; gm < 13; gm++ ) {
			var v = sal_a[ gm ];
			if ( gd <= v ) break;
			gd -= v;
		}
		return [ gy, gm, gd ];
	}

	function isJalaliLeap( jy ) {
		return ( ( ( ( ( jy - ( ( jy > 0 ) ? 474 : 473 ) ) % 2820 ) + 474 ) + 38 ) * 682 ) % 2816 < 682;
	}

	function jalaliMonthLength( jy, jm ) {
		if ( jm <= 6 ) return 31;
		if ( jm <= 11 ) return 30;
		return isJalaliLeap( jy ) ? 30 : 29;
	}

	var jalaliMonthNames = [ 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' ];
	var dowShort = [ 'ی', 'د', 'س', 'چ', 'پ', 'ج', 'ش' ]; // 0=Sunday ... مطابق getDay جاوااسکریپت

	/* ================= تقویم شمسی (Date Picker) ================= */

	function initJalaliDatePicker( $input ) {
		var today = new Date();
		var todayJ = g2j( today.getFullYear(), today.getMonth() + 1, today.getDate() );
		var view = { y: todayJ[0], m: todayJ[1] };
		var selected = null;
		var mode = 'days'; // days | months | years
		var yearRangeStart = todayJ[0] - 6;

		var $wrapper = $input.closest( '.pfb-date-wrapper' );
		var $cal = $( '<div class="pfb-calendar"></div>' );
		$wrapper.append( $cal );

		// جلوگیری از بسته‌شدن تقویم هنگام کلیک داخل خود آن (علت اصلی بسته‌شدن هنگام تغییر ماه)
		$cal.on( 'click', function( e ) {
			e.stopPropagation();
		} );

		function renderDays() {
			var g = j2g( view.y, view.m, 1 );
			var weekday = new Date( g[0], g[1] - 1, g[2] ).getDay(); // 0=یکشنبه در جاوااسکریپت
			var startOffset = ( weekday + 1 ) % 7; // تبدیل به هفته ایرانی که شنبه روز اول است
			var totalDays = jalaliMonthLength( view.y, view.m );

			var html = '<div class="pfb-cal-head">' +
				'<button type="button" class="pfb-cal-prev" title="ماه بعد">›</button>' +
				'<span class="pfb-cal-title">' +
					'<span class="pfb-cal-label pfb-cal-month-label" data-role="month">' + jalaliMonthNames[ view.m - 1 ] + '</span>' +
					'<span class="pfb-cal-label pfb-cal-year-label" data-role="year">' + view.y + '</span>' +
				'</span>' +
				'<button type="button" class="pfb-cal-next" title="ماه قبل">‹</button>' +
			'</div>';
			html += '<div class="pfb-cal-grid">';
			var dows = [ 'ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج' ];
			dows.forEach( function( d ) { html += '<div class="pfb-cal-dow">' + d + '</div>'; } );
			for ( var i = 0; i < startOffset; i++ ) {
				html += '<div class="pfb-cal-day pfb-cal-empty"></div>';
			}
			for ( var d = 1; d <= totalDays; d++ ) {
				var cls = 'pfb-cal-day';
				if ( view.y === todayJ[0] && view.m === todayJ[1] && d === todayJ[2] ) cls += ' pfb-cal-today';
				if ( selected && selected.y === view.y && selected.m === view.m && selected.d === d ) cls += ' pfb-cal-selected';
				html += '<div class="' + cls + '" data-d="' + d + '">' + d + '</div>';
			}
			html += '</div>';
			$cal.html( html );
		}

		function renderMonths() {
			var html = '<div class="pfb-cal-head">' +
				'<button type="button" class="pfb-cal-prev" data-nav="year-prev" title="سال بعد">›</button>' +
				'<span class="pfb-cal-title"><span class="pfb-cal-label">' + view.y + '</span></span>' +
				'<button type="button" class="pfb-cal-next" data-nav="year-next" title="سال قبل">‹</button>' +
			'</div>';
			html += '<div class="pfb-cal-grid pfb-cal-month-grid">';
			jalaliMonthNames.forEach( function( name, idx ) {
				var cls = 'pfb-cal-month-item';
				if ( view.m === idx + 1 ) cls += ' pfb-cal-selected';
				html += '<div class="' + cls + '" data-m="' + ( idx + 1 ) + '">' + name + '</div>';
			} );
			html += '</div>';
			$cal.html( html );
		}

		function renderYears() {
			var years = [];
			for ( var y = yearRangeStart; y < yearRangeStart + 12; y++ ) years.push( y );
			var html = '<div class="pfb-cal-head">' +
				'<button type="button" class="pfb-cal-prev" data-nav="range-prev" title="بازه بعد">›</button>' +
				'<span class="pfb-cal-title"><span class="pfb-cal-label">' + years[0] + ' - ' + years[ years.length - 1 ] + '</span></span>' +
				'<button type="button" class="pfb-cal-next" data-nav="range-next" title="بازه قبل">‹</button>' +
			'</div>';
			html += '<div class="pfb-cal-grid pfb-cal-year-grid">';
			years.forEach( function( y ) {
				var cls = 'pfb-cal-year-item';
				if ( view.y === y ) cls += ' pfb-cal-selected';
				html += '<div class="' + cls + '" data-y="' + y + '">' + y + '</div>';
			} );
			html += '</div>';
			$cal.html( html );
		}

		function render() {
			if ( mode === 'months' ) renderMonths();
			else if ( mode === 'years' ) renderYears();
			else renderDays();
		}

		$input.on( 'click', function( e ) {
			e.stopPropagation();
			$( '.pfb-calendar.open' ).not( $cal ).removeClass( 'open' );
			mode = 'days';
			render();
			$cal.toggleClass( 'open' );
		} );

		// ناوبری در حالت روزها (ماه قبل/بعد)
		$cal.on( 'click', '.pfb-cal-head .pfb-cal-prev[title="ماه بعد"]', function() {
			view.m++;
			if ( view.m > 12 ) { view.m = 1; view.y++; }
			render();
		} );
		$cal.on( 'click', '.pfb-cal-head .pfb-cal-next[title="ماه قبل"]', function() {
			view.m--;
			if ( view.m < 1 ) { view.m = 12; view.y--; }
			render();
		} );

		// باز کردن لیست انتخاب ماه یا سال
		$cal.on( 'click', '[data-role="month"]', function() {
			mode = 'months';
			render();
		} );
		$cal.on( 'click', '[data-role="year"]', function() {
			mode = 'years';
			yearRangeStart = view.y - 6;
			render();
		} );

		// ناوبری در حالت انتخاب ماه (سال قبل/بعد)
		$cal.on( 'click', '[data-nav="year-prev"]', function() { view.y++; render(); } );
		$cal.on( 'click', '[data-nav="year-next"]', function() { view.y--; render(); } );

		// ناوبری در حالت انتخاب سال (بازه بعد/قبل)
		$cal.on( 'click', '[data-nav="range-prev"]', function() { yearRangeStart += 12; render(); } );
		$cal.on( 'click', '[data-nav="range-next"]', function() { yearRangeStart -= 12; render(); } );

		// انتخاب ماه از لیست
		$cal.on( 'click', '.pfb-cal-month-item', function() {
			view.m = parseInt( $( this ).data( 'm' ), 10 );
			mode = 'days';
			render();
		} );

		// انتخاب سال از لیست
		$cal.on( 'click', '.pfb-cal-year-item', function() {
			view.y = parseInt( $( this ).data( 'y' ), 10 );
			mode = 'days';
			render();
		} );

		// انتخاب روز
		$cal.on( 'click', '.pfb-cal-day:not(.pfb-cal-empty)', function() {
			var d = parseInt( $( this ).data( 'd' ), 10 );
			selected = { y: view.y, m: view.m, d: d };
			var mm = ( view.m < 10 ? '0' : '' ) + view.m;
			var dd = ( d < 10 ? '0' : '' ) + d;
			$input.val( view.y + '/' + mm + '/' + dd );
			$input.trigger( 'change' );
			$cal.removeClass( 'open' );
		} );

		$( document ).on( 'click', function() {
			$cal.removeClass( 'open' );
		} );
	}

	/* ================= اعتبارسنجی کد ملی و موبایل ================= */

	function validateNationalCode( code ) {
		code = ( code || '' ).trim();
		if ( ! /^\d{8,10}$/.test( code ) ) return false;
		while ( code.length < 10 ) code = '0' + code;
		if ( /^(\d)\1{9}$/.test( code ) ) return false;
		var sum = 0;
		for ( var i = 0; i < 9; i++ ) {
			sum += parseInt( code[ i ], 10 ) * ( 10 - i );
		}
		var remainder = sum % 11;
		var control = parseInt( code[9], 10 );
		return remainder < 2 ? control === remainder : control === ( 11 - remainder );
	}

	function validateMobile( mobile ) {
		mobile = ( mobile || '' ).trim().replace( /^\+98/, '0' ).replace( /^0098/, '0' );
		return /^09\d{9}$/.test( mobile );
	}

	/* ================= پلاک خودرو: خانه‌های تک‌رقمی با پیشروی خودکار ================= */

	function initPlateField( $field ) {
		var $letterBtn   = $field.find( '.pfb-plate-letter' );
		var $letterValue = $field.find( '.pfb-plate-letter-value' );
		var $letterGrid  = $field.find( '.pfb-plate-letter-grid' );

		function cellsOf( group ) {
			return $field.find( '.pfb-plate-cellgroup[data-group="' + group + '"] input' );
		}

		function syncHidden( group ) {
			var combined = '';
			cellsOf( group ).each( function() {
				combined += digitsToEn( $( this ).val() );
			} );
			var hiddenClass = group === 'ir' ? '.pfb-plate-ir-code' : ( group === 'p1' ? '.pfb-plate-p1' : '.pfb-plate-p2' );
			$field.find( hiddenClass ).val( digitsToFa( combined ) );
		}

		function wireGroup( group, onComplete ) {
			var $cells = cellsOf( group );
			$cells.each( function( index ) {
				$( this ).on( 'input', function() {
					var v = digitsToEn( $( this ).val() ).replace( /[^0-9]/g, '' ).slice( 0, 1 );
					$( this ).val( digitsToFa( v ) );
					syncHidden( group );
					if ( v.length === 1 ) {
						var $next = $cells.eq( index + 1 );
						if ( $next.length ) {
							$next.trigger( 'focus' );
						} else if ( onComplete ) {
							onComplete();
						}
					}
				} );
				$( this ).on( 'keydown', function( e ) {
					if ( e.key === 'Backspace' && $( this ).val() === '' && index > 0 ) {
						$cells.eq( index - 1 ).trigger( 'focus' ).trigger( 'select' );
					}
				} );
			} );
		}

		// پس از تکمیل ۲ رقم اول، به‌صورت خودکار پنجره‌ی انتخاب حرف باز می‌شود
		wireGroup( 'p1', function() {
			$letterBtn.trigger( 'focus' );
			openLetterGrid();
		} );
		wireGroup( 'p2', function() { cellsOf( 'ir' ).eq( 0 ).trigger( 'focus' ); } );
		wireGroup( 'ir', null );

		function positionLetterGrid() {
			var rect = $letterBtn.get( 0 ).getBoundingClientRect();
			var gw = $letterGrid.outerWidth();
			var gh = $letterGrid.outerHeight();
			var left = rect.left + ( rect.width / 2 ) - ( gw / 2 );
			left = Math.max( 8, Math.min( left, window.innerWidth - gw - 8 ) );
			var top = rect.bottom + 8;
			// اگر پایین صفحه جا کافی نبود، پنجره بالای دکمه باز می‌شود
			if ( top + gh > window.innerHeight && rect.top - gh - 8 > 0 ) {
				top = rect.top - gh - 8;
			}
			$letterGrid.css( { top: top + 'px', left: left + 'px' } );
		}

		function openLetterGrid() {
			$( '.pfb-plate-letter-grid.open, .pfb-calendar.open' ).not( $letterGrid ).removeClass( 'open' );
			$letterGrid.addClass( 'open' );
			positionLetterGrid();
		}
		function closeLetterGrid() {
			$letterGrid.removeClass( 'open' );
		}

		$letterBtn.on( 'click', function( e ) {
			e.stopPropagation();
			if ( $letterGrid.hasClass( 'open' ) ) {
				closeLetterGrid();
			} else {
				openLetterGrid();
			}
		} );

		// جلوگیری از بسته‌شدن پنجره هنگام کلیک داخل خود آن
		$letterGrid.on( 'click', function( e ) {
			e.stopPropagation();
		} );

		$letterGrid.on( 'click', '.pfb-plate-letter-item', function() {
			var letter = $( this ).data( 'letter' );
			$letterValue.val( letter );
			$letterBtn.text( letter );
			$letterGrid.find( '.pfb-plate-letter-item' ).removeClass( 'pfb-plate-letter-selected' );
			$( this ).addClass( 'pfb-plate-letter-selected' );
			closeLetterGrid();
			cellsOf( 'p2' ).eq( 0 ).trigger( 'focus' );
		} );

		$( document ).on( 'click', closeLetterGrid );
		$( window ).on( 'scroll resize', closeLetterGrid );
	}

	/* ================= آماده‌سازی هر فرم ================= */

	function initForm( $wrapper ) {
		var $form = $wrapper.find( '.pfb-form' );
		var formId = $form.data( 'form-id' );

		// دکمه شروع
		$wrapper.find( '.pfb-start-btn' ).on( 'click', function() {
			goToStep( $wrapper, 'fields' );
		} );

		// راه‌اندازی تاریخ شمسی
		$wrapper.find( '.pfb-jalali-date' ).each( function() {
			initJalaliDatePicker( $( this ) );
		} );

		// راه‌اندازی پیش‌نمایش پلاک
		$wrapper.find( '.pfb-field-car_plate' ).each( function() {
			initPlateField( $( this ) );
		} );

		// پیش‌نمایش تصویر آپلودی (یا نام فایل، اگر سند غیرتصویری انتخاب شده باشد)
		$wrapper.find( '.pfb-file-input' ).on( 'change', function() {
			var file = this.files && this.files[0];
			var $preview = $( this ).closest( '.pfb-image-upload' ).find( '.pfb-image-preview' );
			if ( ! file ) { $preview.hide(); return; }

			if ( file.type && file.type.indexOf( 'image/' ) === 0 ) {
				var reader = new FileReader();
				reader.onload = function( e ) {
					$preview.html( '<img src="' + e.target.result + '" alt="پیش‌نمایش" />' ).show();
				};
				reader.readAsDataURL( file );
			} else {
				$preview.html( '<span class="pfb-file-name-preview">📎 ' + file.name + '</span>' ).show();
			}
		} );

		// محدود کردن ورودی فقط عددی برای موبایل، کد ملی و قالب فقط‌عددی (ارقام فارسی هم پذیرفته و به انگلیسی تبدیل می‌شوند)
		$wrapper.find( 'input[data-template="mobile"], input[data-template="national_code"], input[data-template="numeric"]' ).on( 'input', function() {
			$( this ).val( digitsToEn( $( this ).val() ).replace( /[^0-9]/g, '' ) );
		} );

		// نمایش زنده مقدار فیلد طیفی
		$wrapper.find( '.pfb-range-input' ).on( 'input', function() {
			$( this ).closest( '.pfb-range-field' ).find( '.pfb-range-value' ).text( $( this ).val() );
		} );

		// راه‌اندازی نمایش شرطی فیلدها (اگر فیلد X برابر Y بود، این فیلد نشان داده شود)
		initConditionalFields( $wrapper, $form );

		// ناوبری بین صفحات فرم (در صورت وجود شکست صفحه)
		initPager( $wrapper, $form );

		$form.on( 'submit', function( e ) {
			e.preventDefault();
			submitForm( $wrapper, $form, formId );
		} );
	}

	function initConditionalFields( $wrapper, $form ) {
		var $conditionalFields = $form.find( '.pfb-field-conditional' );
		if ( ! $conditionalFields.length ) return;

		// نگاشت: شناسه‌ی فیلد محرک => آرایه‌ای از فیلدهای وابسته به آن به‌همراه مقادیر مجاز
		var triggerMap = {};
		$conditionalFields.each( function() {
			var $field = $( this );
			var triggerId = $field.data( 'cond-field' );
			var values;
			try {
				values = JSON.parse( $field.attr( 'data-cond-values' ) || '[]' );
			} catch ( e ) {
				values = [];
			}
			if ( ! triggerMap[ triggerId ] ) triggerMap[ triggerId ] = [];
			triggerMap[ triggerId ].push( { $field: $field, values: values } );
		} );

		function getTriggerValue( fieldId ) {
			var $f = $form.find( '.pfb-field[data-field-id="' + fieldId + '"]' );
			if ( ! $f.length ) return '';
			if ( $f.data( 'type' ) === 'radio' ) {
				return $f.find( 'input[type=radio]:checked' ).val() || '';
			}
			return $f.find( '.pfb-select' ).val() || '';
		}

		function setFieldEnabled( $field, enabled ) {
			$field.find( 'input, select, textarea' ).prop( 'disabled', ! enabled );
		}

		function evaluateConditions() {
			$.each( triggerMap, function( triggerId, dependents ) {
				var currentVal = getTriggerValue( triggerId );
				$.each( dependents, function( i, dep ) {
					var shouldShow = dep.values.indexOf( currentVal ) !== -1;
					dep.$field.toggle( shouldShow );
					setFieldEnabled( dep.$field, shouldShow );
				} );
			} );
		}

		// فیلدهای وابسته در ابتدا (چون پنهانند) غیرفعال می‌شوند تا در ارسال فرم دخالتی نداشته باشند
		$conditionalFields.each( function() {
			setFieldEnabled( $( this ), false );
		} );

		$form.on( 'change', 'select.pfb-input, input[type=radio]', function() {
			evaluateConditions();
		} );

		evaluateConditions();
	}

	function initPager( $wrapper, $form ) {
		var $pages = $form.find( '.pfb-page' );
		if ( $pages.length <= 1 ) return;

		var total = $pages.length;
		var current = 1;

		function show( n ) {
			current = n;
			$pages.removeClass( 'active' );
			$form.find( '.pfb-page[data-page="' + n + '"]' ).addClass( 'active' );
			$form.find( '.pfb-current-page-num' ).text( n );
			$form.find( '.pfb-prev-page-btn' ).toggle( n > 1 );
			$form.find( '.pfb-next-page-btn' ).toggle( n < total );
			$form.find( '.pfb-submit-btn' ).toggle( n === total );
			$wrapper.get( 0 ).scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}

		$form.on( 'click', '.pfb-next-page-btn', function() {
			var $currentPage = $form.find( '.pfb-page[data-page="' + current + '"]' );
			clearErrors( $form, $currentPage );
			if ( ! validateForm( $form, $currentPage ) ) {
				$form.find( '.pfb-alert-error' ).text( 'لطفاً خطاهای این صفحه را برطرف کنید.' ).show();
				return;
			}
			$form.find( '.pfb-alert-error' ).hide();
			if ( current < total ) show( current + 1 );
		} );

		$form.on( 'click', '.pfb-prev-page-btn', function() {
			if ( current > 1 ) show( current - 1 );
		} );

		show( 1 );
	}

	function goToStep( $wrapper, step ) {
		$wrapper.find( '.pfb-step' ).removeClass( 'active' );
		$wrapper.find( '.pfb-step[data-step="' + step + '"]' ).addClass( 'active' );
		$wrapper.get( 0 ).scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}

	function clearErrors( $form, $scope ) {
		$scope = $scope && $scope.length ? $scope : $form;
		$scope.find( '.pfb-field' ).removeClass( 'pfb-has-error' );
		$scope.find( '.pfb-field-error' ).text( '' );
		$form.find( '.pfb-alert-error' ).hide().text( '' );
	}

	function showFieldError( $form, fieldId, message ) {
		var $field = $form.find( '.pfb-field[data-field-id="' + fieldId + '"]' );
		$field.addClass( 'pfb-has-error' );
		$field.find( '.pfb-field-error' ).text( message );
	}

	function validateForm( $form, $scope ) {
		$scope = $scope && $scope.length ? $scope : $form;
		var valid = true;
		$scope.find( '.pfb-field' ).each( function() {
			var $field = $( this );

			// فیلدهایی که به‌خاطر شرط نمایش در حال حاضر مخفی‌اند، اعتبارسنجی نمی‌شوند (کاربر اصلاً آن‌ها را نمی‌بیند)
			if ( $field.hasClass( 'pfb-field-conditional' ) && ! $field.is( ':visible' ) ) {
				return;
			}

			var type = $field.data( 'type' );
			var required = $field.data( 'required' ) == 1;
			var fieldId = $field.data( 'field-id' );

			if ( type === 'car_plate' ) {
				var p1 = digitsToEn( $field.find( '.pfb-plate-p1' ).val() || '' );
				var letter = $field.find( '.pfb-plate-letter-value' ).val() || '';
				var p2 = digitsToEn( $field.find( '.pfb-plate-p2' ).val() || '' );
				var ir = digitsToEn( $field.find( '.pfb-plate-ir-code' ).val() || '' );
				var anyFilled = p1 || letter || p2 || ir;
				if ( required && ( ! p1 || ! letter || ! p2 || ! ir ) ) {
					showFieldError( $form, fieldId, 'تکمیل تمام قسمت‌های پلاک الزامی است.' );
					valid = false;
				} else if ( anyFilled && ( p1.length !== 2 || p2.length !== 3 || ir.length !== 2 ) ) {
					showFieldError( $form, fieldId, 'تعداد ارقام پلاک صحیح نیست.' );
					valid = false;
				}
				return;
			}

			if ( type === 'image' ) {
				var hasFile = $field.find( '.pfb-file-input' )[0].files.length > 0;
				if ( required && ! hasFile ) {
					showFieldError( $form, fieldId, 'بارگذاری فایل الزامی است.' );
					valid = false;
				}
				return;
			}

			var val = '';
			if ( type === 'radio' ) {
				val = $field.find( 'input[type=radio]:checked' ).val() || '';
			} else {
				val = $field.find( '.pfb-input' ).val() || '';
			}
			val = val.trim();

			if ( required && ! val ) {
				showFieldError( $form, fieldId, 'این فیلد الزامی است.' );
				valid = false;
				return;
			}
			if ( ! val ) return;

			if ( type === 'text' ) {
				var template = $field.find( '.pfb-input' ).data( 'template' );
				if ( template === 'national_code' && ! validateNationalCode( digitsToEn( val ) ) ) {
					showFieldError( $form, fieldId, 'کد ملی وارد شده معتبر نیست.' );
					valid = false;
				}
				if ( template === 'mobile' && ! validateMobile( digitsToEn( val ) ) ) {
					showFieldError( $form, fieldId, 'شماره همراه معتبر نیست (مثال: 09123456789).' );
					valid = false;
				}
				if ( template === 'numeric' && ! /^[0-9]+$/.test( digitsToEn( val ) ) ) {
					showFieldError( $form, fieldId, 'این فیلد فقط باید عدد باشد.' );
					valid = false;
				}
			}
		} );
		return valid;
	}

	function submitForm( $wrapper, $form, formId ) {
		clearErrors( $form );
		if ( ! validateForm( $form ) ) {
			$form.find( '.pfb-alert-error' ).text( 'لطفاً خطاهای فرم را برطرف کنید.' ).show();
			return;
		}

		var formData = new FormData( $form.get( 0 ) );
		formData.append( 'action', 'pfb_submit_form' );
		formData.append( 'form_id', formId );

		var $btn = $form.find( '.pfb-submit-btn' );
		$btn.prop( 'disabled', true ).text( 'در حال ارسال...' );

		$.ajax( {
			url: PFB_Front.ajax_url,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function( res ) {
				if ( res.success ) {
					goToStep( $wrapper, 'end' );
				} else {
					if ( res.data && res.data.errors ) {
						$.each( res.data.errors, function( fieldId, msg ) {
							showFieldError( $form, fieldId, msg );
						} );
					}
					$form.find( '.pfb-alert-error' ).text( ( res.data && res.data.message ) || 'خطایی رخ داد.' ).show();
				}
			},
			error: function() {
				$form.find( '.pfb-alert-error' ).text( 'ارتباط با سرور برقرار نشد. لطفاً دوباره تلاش کنید.' ).show();
			},
			complete: function() {
				$btn.prop( 'disabled', false ).text( 'ثبت اطلاعات' );
			}
		} );
	}

	$( function() {
		$( '.pfb-form-wrapper' ).each( function() {
			initForm( $( this ) );
		} );
	} );

} )( jQuery );
