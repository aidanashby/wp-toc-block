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

	var SVG_NS = 'http://www.w3.org/2000/svg';

	/** Chevron, built with DOM methods rather than an HTML string. */
	function chevron() {
		var svg = document.createElementNS( SVG_NS, 'svg' );
		svg.setAttribute( 'class', 'wp-toc__icon' );
		svg.setAttribute( 'width', '16' );
		svg.setAttribute( 'height', '16' );
		svg.setAttribute( 'viewBox', '0 0 16 16' );
		svg.setAttribute( 'aria-hidden', 'true' );
		svg.setAttribute( 'focusable', 'false' );

		var path = document.createElementNS( SVG_NS, 'path' );
		path.setAttribute( 'd', 'M4 6l4 4 4-4' );
		path.setAttribute( 'fill', 'none' );
		path.setAttribute( 'stroke', 'currentColor' );
		path.setAttribute( 'stroke-width', '2' );
		path.setAttribute( 'stroke-linecap', 'round' );
		path.setAttribute( 'stroke-linejoin', 'round' );

		svg.appendChild( path );
		return svg;
	}

	/** Mirrors WordPress's sanitize_title() closely enough for anchors. */
	function slugify( text ) {
		return text
			.toLowerCase()
			.replace( /[ \s]+/g, '-' )
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
	function renderList( nodes, listType ) {
		var list = document.createElement( 'number' === listType ? 'ol' : 'ul' );

		nodes.forEach( function ( node ) {
			var li = document.createElement( 'li' );
			var a = document.createElement( 'a' );

			a.href = '#' + node.id;
			a.textContent = node.text;
			li.appendChild( a );

			if ( node.children.length ) {
				li.appendChild( renderList( node.children, listType ) );
			}

			list.appendChild( li );
		} );

		return list;
	}

	function injectCss( s ) {
		if ( document.getElementById( 'wp-toc-style' ) ) {
			return;
		}

		// Margin-based alignment, not float: Divi 5 modules are flex
		// containers, and flex items don't wrap around floats — a floated
		// block just overlapped the next module and got painted over, so
		// expanding it appeared to do nothing.
		var align = '';
		if ( 'left' === s.align ) {
			align = '.wp-toc--align-left{margin:0 auto 1em 0;}';
		} else if ( 'right' === s.align ) {
			align = '.wp-toc--align-right{margin:0 0 1em auto;}';
		} else if ( 'center' === s.align ) {
			align = '.wp-toc--align-center{margin:0 auto 1em;}';
		}

		// List rules are prefixed with .wp-toc and marked !important because
		// Divi resets lists inside content areas with selectors like
		// `.et_pb_text_inner ul` (0,1,1), which out-specifies a lone
		// `.wp-toc__list` (0,1,0) — that reset was silently removing the
		// nested indent and the padding the markers sit in, so nesting
		// vanished and bullets were clipped outside the box.
		var markers = '';
		if ( 'bullet' === s.listType ) {
			markers =
				'.wp-toc .wp-toc__list,.wp-toc .wp-toc__list ul{list-style:disc !important;padding-left:1.4em !important;}';
		} else if ( 'number' === s.listType ) {
			markers =
				'.wp-toc .wp-toc__list,.wp-toc .wp-toc__list ol{list-style:decimal !important;padding-left:1.6em !important;}';
		} else {
			markers =
				'.wp-toc .wp-toc__list,.wp-toc .wp-toc__list ul,.wp-toc .wp-toc__list ol' +
				'{list-style:none !important;padding-left:0 !important;}';
		}

		var css =
			// position/z-index so nothing in the theme can overlay the block
			// and swallow clicks on the header.
			'.wp-toc{background:' + s.colors.bg + ';color:' + s.colors.text +
			';border:1px solid ' + s.colors.border +
			';padding:1em 1.5em;box-sizing:border-box;width:100%;max-width:' + s.maxWidth + 'px;' +
			'margin:0 0 1em 0;position:relative;z-index:2;}' +
			align +

			// Shrink-to-fit only once the closing animation has finished. A
			// visibility:hidden panel still occupies layout width, so applying
			// fit-content while the list is merely collapsed measures the whole
			// list and produces a box far wider than the configured maximum.
			'.wp-toc.is-shrunk{width:fit-content;max-width:100%;}' +
			'.wp-toc.is-shrunk .wp-toc__panel{width:0;}' +
			'.wp-toc.is-shrunk .wp-toc__header{white-space:nowrap;}' +

			// The header is a real <button> when toggling is on, so the whole
			// top of the block is clickable and keyboard-operable. Strip the
			// button chrome so it still looks like a heading row.
			'.wp-toc__header{display:flex;align-items:center;justify-content:space-between;' +
			'gap:1em;width:100%;margin:0;padding:0;background:none;border:0;color:inherit;' +
			'font:inherit;text-align:left;}' +
			'button.wp-toc__header{cursor:pointer;}' +
			'.wp-toc__label{font-weight:600;}' +
			'.wp-toc__icon{flex-shrink:0;transition:transform 200ms ease;}' +
			'.wp-toc:not(.is-collapsed) .wp-toc__icon{transform:rotate(180deg);}' +

			// Animating to height:auto isn't possible, but a grid row track
			// from 0fr to 1fr is, and it handles any content height.
			'.wp-toc__panel{display:grid;grid-template-rows:1fr;' +
			'transition:grid-template-rows 220ms ease,visibility 220ms;}' +
			'.wp-toc.is-collapsed .wp-toc__panel{grid-template-rows:0fr;visibility:hidden;}' +
			'.wp-toc__panel>*{overflow:hidden;min-height:0;}' +

			markers +
			'.wp-toc .wp-toc__list{margin:0;}' +
			'.wp-toc:not(.is-collapsed) .wp-toc__list{margin-top:.75em;}' +
			'.wp-toc .wp-toc__list ul,.wp-toc .wp-toc__list ol' +
			'{font-size:calc(' + s.scale + ' * 1em);margin-left:' + s.indent + 'px !important;}' +
			'.wp-toc a{color:' + s.colors.link + ';text-decoration:none;}' +
			'.wp-toc a:hover{color:' + s.colors.linkHover + ';text-decoration:underline;}' +
			'@media (prefers-reduced-motion:reduce){.wp-toc__panel,.wp-toc__icon{transition:none;}}';

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
		var panelId = 'wp-toc-panel-' + instance;
		var collapsed = s.toggle && s.startHidden;

		nav.className =
			'wp-toc wp-toc--align-' + s.align + ( collapsed ? ' is-collapsed is-shrunk' : '' );
		nav.setAttribute( 'aria-label', 'Table of contents' );

		if ( s.showLabel || s.toggle ) {
			// A <button> rather than a <div> when toggling: the whole top row
			// becomes the click target while staying keyboard-operable and
			// announced properly, which a clickable div would not be.
			var header = document.createElement( s.toggle ? 'button' : 'div' );
			header.className = 'wp-toc__header';

			if ( s.toggle ) {
				header.type = 'button';
				header.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
				header.setAttribute( 'aria-controls', panelId );
				if ( ! s.showLabel ) {
					header.setAttribute( 'aria-label', 'Table of contents' );
				}
			}

			if ( s.showLabel ) {
				var label = document.createElement( 'span' );
				label.className = 'wp-toc__label';
				label.textContent = s.labelText;
				header.appendChild( label );
			}

			if ( s.toggle ) {
				header.appendChild( chevron() );
				header.addEventListener( 'click', function () {
					var expanded = 'true' === header.getAttribute( 'aria-expanded' );
					header.setAttribute( 'aria-expanded', String( ! expanded ) );
					nav.classList.toggle( 'is-collapsed', expanded );

					if ( expanded ) {
						// Closing: shrink the box only once the list has
						// finished sliding shut, or it would snap away
						// sideways instead of animating.
						window.setTimeout( function () {
							if ( nav.classList.contains( 'is-collapsed' ) ) {
								nav.classList.add( 'is-shrunk' );
							}
						}, 230 );
					} else {
						// Opening: give the list its width back immediately so
						// it has somewhere to animate into.
						nav.classList.remove( 'is-shrunk' );
					}
				} );
			}

			nav.appendChild( header );
		}

		// The panel is the animated wrapper; the list sits inside it.
		var panel = document.createElement( 'div' );
		panel.className = 'wp-toc__panel';
		panel.id = panelId;

		var list = renderList( tree, s.listType );
		list.className = 'wp-toc__list';
		panel.appendChild( list );
		nav.appendChild( panel );

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
