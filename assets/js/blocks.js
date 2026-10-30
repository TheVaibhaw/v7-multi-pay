( function () {
	'use strict';

	var registry = window.wc && window.wc.wcBlocksRegistry;
	var wcSettings = window.wc && window.wc.wcSettings;

	if ( ! registry || ! wcSettings ) {
		return;
	}

	var el = window.wp.element.createElement;
	var decode = window.wp.htmlEntities.decodeEntities;
	var settings = wcSettings.getSetting( 'v7mp_stripe_data', {} );
	var title = decode( settings.title || '' );

	var Content = function () {
		return el( 'div', null, decode( settings.description || '' ) );
	};

	registry.registerPaymentMethod( {
		name: 'v7mp_stripe',
		label: el( 'span', null, title ),
		ariaLabel: title,
		content: el( Content ),
		edit: el( Content ),
		canMakePayment: function () {
			return true;
		},
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );
} )();
