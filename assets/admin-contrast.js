/**
 * Settings page: a tick or cross beside each colour showing whether it has
 * enough contrast against the background (WCAG 2.x AA). Hover for the ratio.
 */
( function ( $ ) {
	// Minimum ratio per field: 4.5 for text, 3 for the non-text border.
	var CHECKS = {
		color_text: [ 'Text', 4.5 ],
		color_link: [ 'Link', 4.5 ],
		color_link_hover: [ 'Link hover', 4.5 ],
		color_border: [ 'Border', 3 ]
	};

	function parseHex( hex ) {
		var m = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec( ( hex || '' ).trim() );
		if ( ! m ) {
			return null;
		}
		var h = m[ 1 ];
		if ( 3 === h.length ) {
			h = h.replace( /./g, '$&$&' );
		}
		return [ 0, 2, 4 ].map( function ( i ) {
			return parseInt( h.substr( i, 2 ), 16 );
		} );
	}

	function luminance( rgb ) {
		var c = rgb.map( function ( v ) {
			v /= 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * c[ 0 ] + 0.7152 * c[ 1 ] + 0.0722 * c[ 2 ];
	}

	function ratio( a, b ) {
		var la = luminance( a ),
			lb = luminance( b );
		return ( Math.max( la, lb ) + 0.05 ) / ( Math.min( la, lb ) + 0.05 );
	}

	function mark( key, ok, tip ) {
		var $td = $( '#toc-' + key ).closest( 'td' );
		var $m = $td.find( '.wp-toc-contrast' );
		if ( ! $m.length ) {
			$m = $( '<span class="wp-toc-contrast" role="img"></span>' ).css( {
				marginLeft: '8px',
				fontSize: '18px',
				fontWeight: 700,
				cursor: 'help',
				verticalAlign: 'middle'
			} );
			$td.append( $m );
		}
		$m.text( ok ? '✓' : '✗' )
			.css( 'color', ok ? '#1a7f37' : '#d63638' )
			.attr( { title: tip, 'aria-label': tip } );
	}

	function update() {
		var bg = parseHex( $( '#toc-color_bg' ).val() );
		if ( ! bg ) {
			return;
		}
		var failed = [];

		$.each( CHECKS, function ( key, spec ) {
			var fg = parseHex( $( '#toc-' + key ).val() );
			if ( ! fg ) {
				return;
			}
			var r = ratio( fg, bg );
			var ok = r >= spec[ 1 ];
			if ( ! ok ) {
				failed.push( spec[ 0 ] );
			}
			mark(
				key,
				ok,
				( ok ? 'Passes' : 'Fails' ) + ': contrast ' + r.toFixed( 2 ) +
					':1 against the background (needs ' + spec[ 1 ] + ':1)'
			);
		} );

		mark(
			'color_bg',
			! failed.length,
			failed.length
				? 'Low contrast against this background: ' + failed.join( ', ' )
				: 'All colours have enough contrast against this background'
		);
	}

	// Called by the colour picker's change callback, once the input holds the new value.
	window.wpTocContrast = function () {
		window.setTimeout( update, 0 );
	};

	$( function () {
		$( '.wp-toc-color' ).on( 'input change', update );
		update();
	} );
}( jQuery ) );
