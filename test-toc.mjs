/**
 * Self-check for the non-obvious logic in assets/toc.js — tree nesting and
 * anchor id de-duplication. Run with: node test-toc.mjs
 *
 * toc.js is a browser IIFE, so rather than import it, the two pure functions
 * are mirrored here and checked. If you change them in toc.js, change them
 * here too — these are the parts with real edge cases.
 */

import assert from 'node:assert';

function slugify( text ) {
	return text
		.toLowerCase()
		.replace( /[ \s]+/g, '-' )
		.replace( /[^a-z0-9\-_]/g, '' )
		.replace( /-{2,}/g, '-' )
		.replace( /^-+|-+$/g, '' );
}

function uniqueId( base, used, existsOnPage = () => false ) {
	if ( ! base ) {
		base = 'heading';
	}
	let id = base;
	let n = 2;
	while ( used[ id ] || existsOnPage( id ) ) {
		id = base + '-' + n;
		n++;
	}
	used[ id ] = true;
	return id;
}

function buildTree( flat ) {
	const root = [];
	const stack = [];

	flat.forEach( ( item ) => {
		const node = { id: item.id, text: item.text, children: [] };
		while ( stack.length && stack[ stack.length - 1 ].level >= item.level ) {
			stack.pop();
		}
		if ( stack.length ) {
			stack[ stack.length - 1 ].node.children.push( node );
		} else {
			root.push( node );
		}
		stack.push( { level: item.level, node } );
	} );

	return root;
}

const flatOf = ( levels ) =>
	levels.map( ( l, i ) => ( { level: l, id: 'h' + i, text: 'Heading ' + i } ) );

const countNodes = ( tree ) =>
	tree.reduce( ( n, node ) => n + 1 + countNodes( node.children ), 0 );

// --- nesting ---
let tree = buildTree( [
	{ level: 2, id: 'a', text: 'A' },
	{ level: 3, id: 'a1', text: 'A1' },
	{ level: 2, id: 'b', text: 'B' },
] );
assert.equal( tree.length, 2, 'two top-level nodes' );
assert.equal( tree[ 0 ].children.length, 1, 'A has one child' );
assert.equal( tree[ 0 ].children[ 0 ].id, 'a1', 'A child is A1' );
assert.equal( tree[ 1 ].children.length, 0, 'B has no children' );

// --- skipped level nests under the last shallower heading ---
tree = buildTree( [
	{ level: 2, id: 'x', text: 'X' },
	{ level: 4, id: 'x1', text: 'X1' },
] );
assert.equal( tree[ 0 ].children[ 0 ].id, 'x1', 'h2 -> h4 nests under the h2' );

// --- no nodes lost or duplicated across realistic shapes ---
for ( const levels of [
	[ 2, 2, 2, 2, 2, 2 ],
	[ 2, 3, 2, 3, 2, 3 ],
	[ 2, 3, 3, 2, 3, 2, 2, 3, 3, 3, 2, 3, 2, 2, 3, 3, 2, 3 ],
	[ 3, 3, 2, 3, 2, 2, 3, 3, 2, 3, 2, 3, 3, 2, 2, 3, 2, 3 ],
	[ 2, 3, 4, 5, 6, 2, 3, 4, 5, 6, 2, 3, 4, 5, 6, 2, 3, 4 ],
] ) {
	assert.equal(
		countNodes( buildTree( flatOf( levels ) ) ),
		levels.length,
		`every heading appears exactly once (${ levels.length } headings)`
	);
}

// --- id de-duplication ---
const used = {};
assert.equal( uniqueId( slugify( 'Same Title' ), used ), 'same-title', 'first is unsuffixed' );
assert.equal( uniqueId( slugify( 'Same Title' ), used ), 'same-title-2', 'second gets -2' );
assert.equal( uniqueId( slugify( 'Same Title' ), used ), 'same-title-3', 'third gets -3' );

// --- must not collide with an id already on the page ---
const used2 = {};
assert.equal(
	uniqueId( slugify( 'Contact' ), used2, ( id ) => id === 'contact' ),
	'contact-2',
	'avoids an id that already exists elsewhere on the page'
);

// --- headings that slugify to nothing still get a usable anchor ---
const used3 = {};
assert.equal( uniqueId( slugify( '!!!' ), used3 ), 'heading', 'unslugifiable text falls back' );
assert.equal( uniqueId( slugify( '???' ), used3 ), 'heading-2', 'and still de-duplicates' );

console.log( 'All checks passed.' );
