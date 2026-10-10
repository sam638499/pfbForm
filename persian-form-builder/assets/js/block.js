( function( blocks, element, blockEditor, components, serverSideRender ) {
	var el = element.createElement;
	var forms = window.PFB_Forms_List || [];

	blocks.registerBlockType( 'pfb/form', {
		title: 'فرم فارسی (Persian Form)',
		description: 'نمایش یکی از فرم‌های ساخته‌شده در افزونه فرم‌ساز فارسی',
		icon: 'feedback',
		category: 'widgets',
		attributes: {
			formId: { type: 'number', default: 0 }
		},

		edit: function( props ) {
			var formId = props.attributes.formId;
			var options = [ { label: '— انتخاب فرم —', value: 0 } ];
			forms.forEach( function( f ) {
				options.push( { label: f.title || ( 'فرم #' + f.id ), value: f.id } );
			} );

			var inspector = el(
				blockEditor.InspectorControls,
				{},
				el(
					components.PanelBody,
					{ title: 'تنظیمات فرم' },
					el( components.SelectControl, {
						label: 'انتخاب فرم',
						value: formId,
						options: options,
						onChange: function( value ) {
							props.setAttributes( { formId: parseInt( value, 10 ) } );
						}
					} )
				)
			);

			var content;
			if ( ! formId ) {
				content = el( 'div', { style: { border: '1px dashed #ccc', padding: '30px', textAlign: 'center', direction: 'rtl' } }, 'لطفاً از تنظیمات سمت راست، یک فرم را انتخاب کنید.' );
			} else if ( serverSideRender ) {
				content = el( serverSideRender, { block: 'pfb/form', attributes: { formId: formId } } );
			} else {
				content = el( 'div', { style: { direction: 'rtl' } }, 'فرم شماره ' + formId + ' در سایت نمایش داده خواهد شد.' );
			}

			return el( element.Fragment, {}, inspector, content );
		},

		save: function() {
			return null; // رندر داینامیک در سمت سرور
		}
	} );

} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender );
