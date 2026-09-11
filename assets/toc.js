/**
 * Builds the table of contents in the browser.
 *
 * Deliberately client-side: Divi 5 assembles its module tree through its own
 * pipeline and never passes the finished HTML back through WordPress's
 * the_content filter, so no server-side filter reliably sees the rendered
 * markup. By the time this runs, the finished DOM is right there and how it
 * got built no longer matters.
 */
( function () {
	'use strict';

	/** Mirrors WordPress's sanitize_title() closely enough for anchors. */
	function slugify( text ) {
		return text
			.toLowerCase()
			.replace( /[ \s]+/g, '-' )
			.replace( /[^a-z0-9\-_]/g, '' )
			.replace( /-{2,}/g, '-' )
			.replace( /^-+|-+$/g, '' );
	}

	/**
	 * A unique id for this heading. Checks ids already taken elsewhere on the
	 * page too, not just ones we assigned, so we can't collide with existing
	 * anchors.
	 */
	function uniqueId( base, used ) {
		if ( ! base ) {
			base = 'heading';
		}
		var id = base;
		var n = 2;
		while ( used[ id ] || document.getElementById( id ) ) {
			id = base + '-' + n;
			n++;
		}
		used[ id ] = true;
		return id;
	}

	/**
	 * Flat, document-order headings -> nested tree. A heading that skips a
	 * level (h2 straight to h4) nests under the last shallower one.
	 */
	function buildTree( flat ) {
		var root = [];
		var stack = [];

		flat.forEach( function ( item ) {
			var node = { id: item.id, text: item.text, children: [] };

			while ( stack.length && stack[ stack.length - 1 ].level >= item.level ) {
				stack.pop();
			}

			if ( stack.length ) {
				stack[ stack.length - 1 ].node.children.push( node );
			} else {
				root.push( node );
			}

			stack.push( { level: item.level, node: node } );
		} );

		return root;
	}

	/** Built with createElement/textContent, so heading text can't inject markup. */
	function renderList( nodes ) {
		var ul = document.createElement( 'ul' );

		nodes.forEach( function ( node ) {
			var li = document.createElement( 'li' );
			var a = document.createElement( 'a' );

			a.href = '#' + node.id;
			a.textContent = node.text;
			li.appendChild( a );

			if ( node.children.length ) {
				li.appendChild( renderList( node.children ) );
			}

			ul.appendChild( li );
		} );

		return ul;
	}

	function injectCss( s ) {
		if ( document.getElementById( 'wp-toc-style' ) ) {
			return;
		}

		var align = '';
		if ( 'left' === s.align ) {
			align = '.wp-toc{float:left;margin:0 1.5em 1em 0;}';
		} else if ( 'right' === s.align ) {
			align = '.wp-toc{float:right;margin:0 0 1em 1.5em;}';
		} else if ( 'center' === s.align ) {
			align = '.wp-toc{margin:0 auto 1em;}';
		}

		var css =
			'.wp-toc{background:' + s.colors.bg + ';color:' + s.colors.text +
			';border:1px solid ' + s.colors.border +
			';padding:1em 1.5em;box-sizing:border-box;width:100%;max-width:' + s.maxWidth + 'px;margin:0 0 1em 0;}' +
			align +
			// Collapsed: shrink to fit the label and toggle rather than sitting
			// at full width with nothing to show for it.
			'.wp-toc.is-collapsed{width:fit-content;max-width:100%;}' +
			'.wp-toc.is-collapsed .wp-toc__header{white-space:nowrap;}' +
			'.wp-toc__header{display:flex;align-items:center;justify-content:space-between;gap:1em;}' +
			'.wp-toc__label{font-weight:600;}' +
			'.wp-toc__toggle{background:none;border:1px solid currentColor;cursor:pointer;padding:.25em .75em;flex-shrink:0;}' +
			'.wp-toc__list,.wp-toc__list ul{list-style:none;margin:0;padding:0;}' +
			'.wp-toc__list{margin-top:.75em;}' +
			'.wp-toc__list ul{font-size:calc(' + s.scale + ' * 1em);margin-left:' + s.indent + 'px;}' +
			'.wp-toc a{color:' + s.colors.link + ';text-decoration:none;}' +
			'.wp-toc a:hover{color:' + s.colors.linkHover + ';text-decoration:underline;}';

		var style = document.createElement( 'style' );
		style.id = 'wp-toc-style';
		style.textContent = css;
		document.head.appendChild( style );
	}

	/** ItemList is the schema.org type that actually fits an in-page TOC. */
	function injectSchema( flat ) {
		if ( document.getElementById( 'wp-toc-schema' ) ) {
			return;
		}

		var base = window.location.href.split( '#' )[ 0 ];
		var schema = {
			'@context': 'https://schema.org',
			'@type': 'ItemList',
			itemListElement: flat.map( function ( item, i ) {
				return {
					'@type': 'ListItem',
					position: i + 1,
					name: item.text,
					url: base + '#' + item.id
				};
			} )
		};

		var el = document.createElement( 'script' );
		el.type = 'application/ld+json';
		el.id = 'wp-toc-schema';
		el.textContent = JSON.stringify( schema );
		document.head.appendChild( el );
	}

	function buildToc( tree, s, instance ) {
		var nav = document.createElement( 'nav' );
		var listId = 'wp-toc-list-' + instance;
		var collapsed = s.toggle && s.startHidden;

		nav.className = 'wp-toc wp-toc--align-' + s.align + ( collapsed ? ' is-collapsed' : '' );
		nav.setAttribute( 'aria-label', 'Table of contents' );

		if ( s.showLabel || s.toggle ) {
			var header = document.createElement( 'div' );
			header.className = 'wp-toc__header';

			if ( s.showLabel ) {
				var label = document.createElement( 'span' );
				label.className = 'wp-toc__label';
				label.textContent = s.labelText;
				header.appendChild( label );
			}

			if ( s.toggle ) {
				var button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'wp-toc__toggle';
				button.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
				button.setAttribute( 'aria-controls', listId );
				button.textContent = 'Toggle';
				button.addEventListener( 'click', function () {
					var expanded = 'true' === button.getAttribute( 'aria-expanded' );
					button.setAttribute( 'aria-expanded', String( ! expanded ) );
					list.hidden = expanded;
					nav.classList.toggle( 'is-collapsed', expanded );
				} );
				header.appendChild( button );
			}

			nav.appendChild( header );
		}

		var list = renderList( tree );
		list.className = 'wp-toc__list';
		list.id = listId;
		list.hidden = collapsed;
		nav.appendChild( list );

		return nav;
	}

	function init() {
		var mounts = document.querySelectorAll( '.wp-toc-mount' );
		if ( ! mounts.length ) {
			return;
		}

		var s;
		try {
			s = JSON.parse( mounts[ 0 ].getAttribute( 'data-wp-toc' ) );
		} catch ( e ) {
			return;
		}

		var scope = s.scope ? document.querySelector( s.scope ) : null;
		if ( ! scope ) {
			scope = mounts[ 0 ].closest( 'article' ) || document.body;
			if ( window.console && console.warn ) {
				console.warn(
					'[wp-toc-block] No element matched "' + s.scope +
					'". Falling back to ' + scope.nodeName.toLowerCase() +
					' — set the content container selector in Settings → WP TOC Block.'
				);
			}
		}

		if ( s.minWords > 0 ) {
			var words = scope.textContent.trim().split( /\s+/ ).length;
			if ( words < s.minWords ) {
				return;
			}
		}

		var selector = s.levels
			.map( function ( l ) {
				return 'h' + l;
			} )
			.join( ',' );

		var used = {};
		var flat = [];

		Array.prototype.forEach.call( scope.querySelectorAll( selector ), function ( heading ) {
			var text = heading.textContent.trim();
			if ( ! text ) {
				return;
			}
			// Don't list headings inside a table of contents we're building.
			if ( heading.closest( '.wp-toc' ) || heading.closest( '.wp-toc-mount' ) ) {
				return;
			}

			var id = heading.id;
			if ( id ) {
				used[ id ] = true;
			} else {
				id = uniqueId( slugify( text ), used );
				heading.id = id;
			}

			flat.push( { level: parseInt( heading.nodeName.substring( 1 ), 10 ), id: id, text: text } );
		} );

		if ( flat.length < s.minHeadings ) {
			return;
		}

		injectCss( s );
		var tree = buildTree( flat );

		Array.prototype.forEach.call( mounts, function ( mount, i ) {
			mount.appendChild( buildToc( tree, s, i + 1 ) );
		} );

		injectSchema( flat );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
