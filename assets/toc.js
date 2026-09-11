document.addEventListener( 'DOMContentLoaded', function () {
	document.querySelectorAll( '.wp-toc__toggle' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var list = document.getElementById( button.getAttribute( 'aria-controls' ) );
			if ( ! list ) {
				return;
			}
			var expanded = button.getAttribute( 'aria-expanded' ) === 'true';
			button.setAttribute( 'aria-expanded', String( ! expanded ) );
			list.hidden = expanded;

			var nav = button.closest( '.wp-toc' );
			if ( nav ) {
				nav.classList.toggle( 'is-collapsed', expanded );
			}
		} );
	} );
} );
