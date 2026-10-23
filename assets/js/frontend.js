/**
 * Municipal Phonebook – front-end search, category filter and toolbar.
 *
 * Vanilla JS, no dependencies. Every directory instance on the page is independent.
 * The search index is built from the rendered (already escaped) DOM and results are rendered with text nodes only.
 */
( function () {
	'use strict';

	var config = window.munipbConfig || {};
	var i18n = config.i18n || {};
	var MIN_CHARS = config.minChars || 2;
	var MAX_RESULTS = config.maxResults || 10;

	var ACCENT_GROUPS = {
		'α': '[αά]', 'ε': '[εέ]', 'η': '[ηή]', 'ι': '[ιίϊΐ]',
		'ο': '[οό]', 'υ': '[υύϋΰ]', 'ω': '[ωώ]', 'σ': '[σς]'
	};

	function normalize( text ) {
		return String( text || '' )
			.toLowerCase()
			.normalize( 'NFD' )
			.replace( /[\u0300-\u036f]/g, '' )
			.replace( /ς/g, 'σ' );
	}

	function digitsOnly( text ) {
		return String( text || '' ).replace( /\D/g, '' );
	}

	function escapeRegex( str ) {
		return str.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	}

	function textOf( el ) {
		return el ? el.textContent.replace( /\s+/g, ' ' ).trim() : '';
	}

	/**
	 * Append text to a parent, wrapping accent-insensitive matches in <mark>.
	 * Text nodes only (never innerHTML), so it is XSS-safe by construction.
	 */
	function appendHighlighted( parent, text, words ) {
		var patterns = words.filter( function ( w ) {
			return w.length >= MIN_CHARS;
		} ).map( function ( w ) {
			var out = '';
			for ( var i = 0; i < w.length; i++ ) {
				out += ACCENT_GROUPS[ w[ i ] ] || escapeRegex( w[ i ] );
			}
			return out;
		} );

		if ( ! patterns.length ) {
			parent.appendChild( document.createTextNode( text ) );
			return;
		}

		var regex = new RegExp( '(' + patterns.join( '|' ) + ')', 'gi' );
		var last = 0;
		var match;
		while ( ( match = regex.exec( text ) ) !== null ) {
			if ( match[ 0 ] === '' ) {
				regex.lastIndex++;
				continue;
			}
			parent.appendChild( document.createTextNode( text.slice( last, match.index ) ) );
			var mark = document.createElement( 'mark' );
			mark.className = 'munipb-hl';
			mark.textContent = match[ 0 ];
			parent.appendChild( mark );
			last = match.index + match[ 0 ].length;
		}
		parent.appendChild( document.createTextNode( text.slice( last ) ) );
	}

	function reducedMotion() {
		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	function scrollOffset() {
		var offset = 20;
		var bar = document.getElementById( 'wpadminbar' );
		if ( bar ) {
			offset += bar.offsetHeight;
		}
		var sticky = document.querySelector( '.elementor-sticky--active, header.sticky, .whb-sticked, .site-header.is-sticky' );
		if ( sticky && sticky.offsetHeight < 200 ) {
			offset += sticky.offsetHeight;
		}
		return offset;
	}

	function scrollToEl( el, done ) {
		var reduce = reducedMotion();
		var top = el.getBoundingClientRect().top + window.pageYOffset - scrollOffset();
		window.scrollTo( { top: Math.max( 0, top ), behavior: reduce ? 'auto' : 'smooth' } );
		if ( done ) {
			setTimeout( done, reduce ? 0 : 450 );
		}
	}

	function focusEl( el ) {
		if ( ! el.hasAttribute( 'tabindex' ) ) {
			el.setAttribute( 'tabindex', '-1' );
		}
		el.focus( { preventScroll: true } );
	}

	function flash( el ) {
		el.classList.add( 'munipb-flash' );
		setTimeout( function () {
			el.classList.remove( 'munipb-flash' );
		}, 2000 );
	}

	function revealTarget( el ) {
		scrollToEl( el, function () {
			if ( el.matches( 'tr' ) ) {
				flash( el );
				focusEl( el );
			} else {
				focusEl( el.querySelector( '.munipb-section-title' ) || el );
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Search                                                              */
	/* ------------------------------------------------------------------ */

	function buildIndex( root ) {
		var data = [];
		root.querySelectorAll( '.munipb-section' ).forEach( function ( section ) {
			var sectionName = textOf( section.querySelector( '.munipb-section-title' ) );
			data.push( { type: 'dept', title: sectionName, meta: '', context: '', el: section, search: normalize( sectionName ), digits: '' } );

			var currentSub = '';
			section.querySelectorAll( 'tbody tr' ).forEach( function ( row ) {
				if ( row.hasAttribute( 'data-munipb-subdept' ) ) {
					currentSub = textOf( row.querySelector( '.munipb-subdept-name' ) );
					var subPhone = textOf( row.querySelector( '.munipb-col-phone' ) );
					data.push( {
						type: 'subdept',
						title: currentSub,
						meta: subPhone,
						context: sectionName,
						el: row,
						search: normalize( [ currentSub, sectionName, subPhone, textOf( row.querySelector( '.munipb-col-email' ) ) ].join( ' ' ) ),
						digits: digitsOnly( subPhone )
					} );
					return;
				}
				var name = textOf( row.querySelector( '.munipb-col-name' ) );
				if ( ! name ) {
					return;
				}
				var role = textOf( row.querySelector( '.munipb-col-role' ) );
				var phone = textOf( row.querySelector( '.munipb-col-phone' ) );
				var email = textOf( row.querySelector( '.munipb-col-email' ) );
				data.push( {
					type: 'entry',
					title: name,
					meta: [ role, phone ].filter( Boolean ).join( ' • ' ),
					context: currentSub || sectionName,
					el: row,
					search: normalize( [ name, role, phone, email, currentSub, sectionName ].join( ' ' ) ),
					digits: digitsOnly( phone )
				} );
			} );
		} );
		return data;
	}

	function wordsMatch( search, digits, words ) {
		if ( ! words.length ) {
			return true;
		}
		return words.every( function ( w ) {
			if ( search.indexOf( w ) !== -1 ) {
				return true;
			}
			var d = digitsOnly( w );
			return d.length >= 3 && digits.indexOf( d ) !== -1;
		} );
	}

	function queryWords( query ) {
		if ( String( query || '' ).trim().length < MIN_CHARS ) {
			return [];
		}
		return normalize( query ).split( /\s+/ ).filter( Boolean );
	}

	function selectedIds( root ) {
		var ids = [];
		root.querySelectorAll( '.munipb-cats-panel input[type="checkbox"]:checked' ).forEach( function ( box ) {
			ids.push( box.value );
		} );
		return ids;
	}

	function rowAllowed( el, selected ) {
		if ( ! selected.length || ! el ) {
			return true;
		}
		var section = el.classList.contains( 'munipb-section' ) ? el : el.closest( '.munipb-section' );
		if ( ! section ) {
			return true;
		}
		var sectionId = section.getAttribute( 'data-munipb-section' ) || '';
		if ( selected.indexOf( sectionId ) !== -1 ) {
			return true;
		}
		if ( el.classList.contains( 'munipb-section' ) ) {
			var rows = section.querySelectorAll( '[data-munipb-dept]' );
			for ( var i = 0; i < rows.length; i++ ) {
				if ( selected.indexOf( rows[ i ].getAttribute( 'data-munipb-dept' ) ) !== -1 ) {
					return true;
				}
			}
			return false;
		}
		return selected.indexOf( el.getAttribute( 'data-munipb-dept' ) || '' ) !== -1;
	}

	function setHidden( el, hidden ) {
		if ( ! el ) {
			return;
		}
		el.hidden = hidden;
		el.classList.toggle( 'munipb-is-hidden', hidden );
	}

	function localSearch( index, words, selected ) {
		return index.filter( function ( item ) {
			return rowAllowed( item.el, selected ) && wordsMatch( item.search, item.digits, words );
		} ).slice( 0, MAX_RESULTS );
	}

	/**
	 * Hide rows that fail the department filter or the current search.
	 * A checked top-level department shows that whole section. A checked
	 * sub-department shows only that group. No checks shows everything.
	 */
	function applyView( state ) {
		var selected = selectedIds( state.root );
		var input = state.root.querySelector( '.munipb-search-input' );
		var words = queryWords( input ? input.value : '' );
		var anyVisible = false;

		state.root.querySelectorAll( '.munipb-section' ).forEach( function ( section ) {
			var sectionId = section.getAttribute( 'data-munipb-section' ) || '';
			var sectionName = textOf( section.querySelector( '.munipb-section-title' ) );
			var sectionOn = ! selected.length || selected.indexOf( sectionId ) !== -1;
			var rows = section.querySelectorAll( 'tbody tr' );
			var sectionHas = false;
			var i = 0;

			while ( i < rows.length ) {
				var row = rows[ i ];
				if ( row.hasAttribute( 'data-munipb-subdept' ) ) {
					var deptId = row.getAttribute( 'data-munipb-dept' ) || '';
					var allowed = sectionOn || selected.indexOf( deptId ) !== -1;
					var subName = textOf( row.querySelector( '.munipb-subdept-name' ) );
					var headerBlob = normalize( [ subName, sectionName, textOf( row ) ].join( ' ' ) );
					var headerDigits = digitsOnly( textOf( row.querySelector( '.munipb-col-phone' ) ) );
					var headerMatch = wordsMatch( headerBlob, headerDigits, words );
					var entryVisible = false;
					var j = i + 1;

					while ( j < rows.length && ! rows[ j ].hasAttribute( 'data-munipb-subdept' ) ) {
						var entry = rows[ j ];
						var blob = normalize( [ textOf( entry ), subName, sectionName ].join( ' ' ) );
						var show = allowed && wordsMatch( blob, digitsOnly( textOf( entry.querySelector( '.munipb-col-phone' ) ) ), words );
						setHidden( entry, ! show );
						if ( show ) {
							entryVisible = true;
						}
						j++;
					}

					var showHeader = allowed && ( headerMatch || entryVisible );
					setHidden( row, ! showHeader );
					if ( showHeader || entryVisible ) {
						sectionHas = true;
					}
					i = j;
					continue;
				}

				var entryBlob = normalize( [ textOf( row ), sectionName ].join( ' ' ) );
				var showEntry = sectionOn && wordsMatch( entryBlob, digitsOnly( textOf( row.querySelector( '.munipb-col-phone' ) ) ), words );
				setHidden( row, ! showEntry );
				if ( showEntry ) {
					sectionHas = true;
				}
				i++;
			}

			setHidden( section, ! sectionHas );
			if ( sectionHas ) {
				anyVisible = true;
			}
		} );

		var none = state.root.querySelector( '[data-munipb-none]' );
		if ( none ) {
			setHidden( none, ! ( state.root.querySelector( '.munipb-section' ) && ! anyVisible ) );
		}
		stripeRows( state.root );
	}

	/**
	 * Odd/even classes follow the rows that are still visible, per department.
	 * :nth-child would keep counting rows hidden by the filter.
	 */
	function stripeRows( root ) {
		root.querySelectorAll( '.munipb-section' ).forEach( function ( section ) {
			var n = 0;
			section.querySelectorAll( 'tbody tr.munipb-entry-row' ).forEach( function ( row ) {
				row.classList.remove( 'munipb-row-odd', 'munipb-row-even' );
				if ( row.hidden || row.classList.contains( 'munipb-is-hidden' ) ) {
					return;
				}
				n++;
				row.classList.add( ( n % 2 === 1 ) ? 'munipb-row-odd' : 'munipb-row-even' );
			} );
		} );
	}

	function updateBadge( state ) {
		var badge = state.root.querySelector( '[data-munipb-count]' );
		if ( ! badge ) {
			return;
		}
		var count = selectedIds( state.root ).length;
		var num = badge.querySelector( '.munipb-count-num' );
		var text = badge.querySelector( '[data-munipb-count-text]' );
		if ( num ) {
			num.textContent = String( count );
		}
		if ( text ) {
			text.textContent = String( i18n.selected || '%d selected' ).replace( '%d', String( count ) );
		}
		setHidden( badge, count === 0 );
	}

	function initSearch( state ) {
		var form = state.root.querySelector( '.munipb-search' );
		if ( ! form ) {
			return;
		}
		var input = form.querySelector( '.munipb-search-input' );
		var list = form.querySelector( '.munipb-search-results' );
		var clear = form.querySelector( '.munipb-search-clear' );
		var status = form.querySelector( '.munipb-search-status' );
		var submit = form.querySelector( '.munipb-search-submit' );
		var index = buildIndex( state.root );
		var results = [];
		var selected = -1;
		var timer = null;

		if ( submit ) {
			submit.hidden = true;
		}
		if ( clear && input.value.trim() !== '' ) {
			clear.hidden = false;
		}

		function announce( msg ) {
			if ( status ) {
				status.textContent = msg;
			}
		}

		function hide() {
			list.hidden = true;
			list.textContent = '';
			input.setAttribute( 'aria-expanded', 'false' );
			input.removeAttribute( 'aria-activedescendant' );
			selected = -1;
			results = [];
		}

		function select( i ) {
			var items = list.querySelectorAll( '[role="option"]' );
			selected = i;
			items.forEach( function ( item, n ) {
				var on = n === i;
				item.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				item.classList.toggle( 'is-selected', on );
				if ( on ) {
					input.setAttribute( 'aria-activedescendant', item.id );
					item.scrollIntoView( { block: 'nearest' } );
				}
			} );
		}

		function go( i ) {
			var item = results[ i ];
			if ( ! item ) {
				return;
			}
			input.value = '';
			if ( clear ) {
				clear.hidden = true;
			}
			hide();
			if ( state.applyView ) {
				state.applyView();
			}
			revealTarget( item.el );
		}

		function render( found, words ) {
			results = found;
			list.textContent = '';
			list.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
			selected = -1;

			if ( ! results.length ) {
				var empty = document.createElement( 'div' );
				empty.className = 'munipb-result-empty';
				empty.textContent = i18n.noResults || 'No results found';
				list.appendChild( empty );
				announce( i18n.noResults || 'No results found' );
				return;
			}

			results.forEach( function ( item, n ) {
				var opt = document.createElement( 'div' );
				opt.className = 'munipb-result munipb-result-' + ( [ 'dept', 'subdept', 'entry' ].indexOf( item.type ) !== -1 ? item.type : 'entry' );
				opt.id = list.id + '-opt-' + n;
				opt.setAttribute( 'role', 'option' );
				opt.setAttribute( 'aria-selected', 'false' );

				var title = document.createElement( 'div' );
				title.className = 'munipb-result-title';
				appendHighlighted( title, String( item.title || '' ), words );
				opt.appendChild( title );

				if ( item.meta ) {
					var meta = document.createElement( 'div' );
					meta.className = 'munipb-result-meta';
					appendHighlighted( meta, String( item.meta ), words );
					opt.appendChild( meta );
				}
				if ( item.context ) {
					var ctx = document.createElement( 'div' );
					ctx.className = 'munipb-result-context';
					ctx.textContent = String( item.context );
					opt.appendChild( ctx );
				}
				opt.addEventListener( 'mousedown', function ( e ) {
					e.preventDefault();
				} );
				opt.addEventListener( 'click', function () {
					go( n );
				} );
				list.appendChild( opt );
			} );
			announce( results.length === 1 && i18n.oneResult ? i18n.oneResult : String( i18n.results || '%d results' ).replace( '%d', results.length ) );
		}

		function search( query ) {
			var words = queryWords( query );
			render( localSearch( index, words, selectedIds( state.root ) ), words );
		}

		state.refreshSearch = function () {
			var q = input.value.trim();
			if ( list.hidden || q.length < MIN_CHARS ) {
				return;
			}
			search( q.slice( 0, 100 ) );
		};

		input.addEventListener( 'input', function () {
			var q = input.value.trim();
			clearTimeout( timer );
			if ( clear ) {
				clear.hidden = q === '';
			}
			if ( state.applyView ) {
				state.applyView();
			}
			if ( q.length < MIN_CHARS ) {
				hide();
				announce( '' );
				return;
			}
			timer = setTimeout( function () {
				search( q.slice( 0, 100 ) );
			}, 150 );
		} );

		input.addEventListener( 'keydown', function ( e ) {
			var count = list.querySelectorAll( '[role="option"]' ).length;
			if ( e.key === 'ArrowDown' && count ) {
				e.preventDefault();
				select( Math.min( selected + 1, count - 1 ) );
			} else if ( e.key === 'ArrowUp' && count ) {
				e.preventDefault();
				select( Math.max( selected - 1, 0 ) );
			} else if ( e.key === 'Enter' ) {
				e.preventDefault();
				go( selected >= 0 ? selected : 0 );
			} else if ( e.key === 'Escape' ) {
				var empty = input.value.trim() === '';
				hide();
				if ( empty && state.closeSearch ) {
					e.preventDefault();
					state.closeSearch();
				}
			}
		} );

		input.addEventListener( 'blur', function () {
			setTimeout( hide, 150 );
		} );

		if ( clear ) {
			clear.addEventListener( 'click', function () {
				input.value = '';
				clear.hidden = true;
				hide();
				announce( '' );
				if ( state.applyView ) {
					state.applyView();
				}
				input.focus();
			} );
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
		} );

		state.hideResults = hide;
	}

	/* ------------------------------------------------------------------ */
	/* Categories                                                          */
	/* ------------------------------------------------------------------ */

	function initCats( state ) {
		var wrap = state.root.querySelector( '.munipb-cats' );
		if ( ! wrap ) {
			return;
		}
		var toggle = wrap.querySelector( '.munipb-cats-toggle' );
		var panel = wrap.querySelector( '.munipb-cats-panel' );
		if ( ! toggle || ! panel ) {
			return;
		}

		function boxes() {
			return Array.prototype.slice.call( panel.querySelectorAll( 'input[type="checkbox"]' ) );
		}

		function setOpen( open ) {
			setHidden( panel, ! open );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			wrap.classList.toggle( 'is-open', open );
		}

		state.closeCats = function () {
			setOpen( false );
		};

		toggle.addEventListener( 'click', function () {
			setOpen( panel.hidden );
		} );

		toggle.addEventListener( 'keydown', function ( e ) {
			var list = boxes();
			if ( ! list.length ) {
				return;
			}
			if ( e.key === 'ArrowDown' ) {
				e.preventDefault();
				setOpen( true );
				list[ 0 ].focus();
			} else if ( e.key === 'ArrowUp' ) {
				e.preventDefault();
				setOpen( true );
				list[ list.length - 1 ].focus();
			} else if ( e.key === 'Escape' ) {
				setOpen( false );
			}
		} );

		panel.addEventListener( 'keydown', function ( e ) {
			var list = boxes();
			var index = list.indexOf( document.activeElement );
			if ( e.key === 'ArrowDown' && index < list.length - 1 ) {
				e.preventDefault();
				list[ index + 1 ].focus();
			} else if ( e.key === 'ArrowUp' ) {
				e.preventDefault();
				if ( index > 0 ) {
					list[ index - 1 ].focus();
				} else {
					toggle.focus();
				}
			} else if ( e.key === 'Home' && list.length ) {
				e.preventDefault();
				list[ 0 ].focus();
			} else if ( e.key === 'End' && list.length ) {
				e.preventDefault();
				list[ list.length - 1 ].focus();
			} else if ( e.key === 'Escape' ) {
				e.preventDefault();
				setOpen( false );
				toggle.focus();
			}
		} );

		panel.addEventListener( 'change', function ( e ) {
			if ( ! e.target.matches( 'input[type="checkbox"]' ) ) {
				return;
			}
			updateBadge( state );
			if ( state.applyView ) {
				state.applyView();
			}
			if ( state.refreshSearch ) {
				state.refreshSearch();
			}
		} );

		panel.addEventListener( 'focusout', function ( e ) {
			if ( ! wrap.contains( e.relatedTarget ) ) {
				setOpen( false );
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! wrap.contains( e.target ) ) {
				setOpen( false );
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Search reveal and clear                                             */
	/* ------------------------------------------------------------------ */

	function initSearchToggle( state ) {
		var button = state.root.querySelector( '.munipb-search-toggle' );
		var form = state.root.querySelector( '.munipb-search' );
		if ( ! button || ! form ) {
			return;
		}
		var input = form.querySelector( '.munipb-search-input' );

		function openSearch() {
			form.classList.add( 'is-open' );
			button.setAttribute( 'aria-expanded', 'true' );
			if ( input ) {
				input.focus();
			}
		}

		state.closeSearch = function () {
			form.classList.remove( 'is-open' );
			button.setAttribute( 'aria-expanded', 'false' );
			if ( state.hideResults ) {
				state.hideResults();
			}
			button.focus();
		};

		button.addEventListener( 'click', function () {
			openSearch();
		} );
	}

	function initClear( state ) {
		var button = state.root.querySelector( '.munipb-clear' );
		if ( ! button ) {
			return;
		}
		button.addEventListener( 'click', function () {
			if ( state.root.getAttribute( 'data-munipb-server-query' ) === '1' ) {
				var url = new URL( window.location.href );
				url.searchParams.delete( 'munipb_q' );
				if ( state.root.id ) {
					url.hash = state.root.id;
				}
				window.location.assign( url.toString() );
				return;
			}
			state.root.querySelectorAll( '.munipb-cats-panel input[type="checkbox"]' ).forEach( function ( box ) {
				box.checked = false;
			} );
			var input = state.root.querySelector( '.munipb-search-input' );
			var clear = state.root.querySelector( '.munipb-search-clear' );
			if ( input ) {
				input.value = '';
			}
			if ( clear ) {
				clear.hidden = true;
			}
			if ( state.hideResults ) {
				state.hideResults();
			}
			if ( state.closeCats ) {
				state.closeCats();
			}
			updateBadge( state );
			if ( state.applyView ) {
				state.applyView();
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Boot                                                                */
	/* ------------------------------------------------------------------ */

	function initDirectory( root ) {
		if ( ! root || root.munipbState ) {
			return;
		}
		var state = { root: root };
		root.munipbState = state;
		root.classList.add( 'munipb-js' );
		state.applyView = function () {
			applyView( state );
		};

		initSearch( state );
		initCats( state );
		initSearchToggle( state );
		initClear( state );
		updateBadge( state );
		state.applyView();

		root.addEventListener( 'click', function ( e ) {
			if ( config.trackCalls ) {
				var phone = e.target.closest( 'a.munipb-phone' );
				if ( phone && typeof window.gtag === 'function' ) {
					window.gtag( 'event', 'click_to_call', { phone_number: phone.textContent.trim(), source: 'municipal_phonebook' } );
				}
			}
		} );
	}

	function initAll( scope ) {
		( scope || document ).querySelectorAll( '[data-munipb-directory]' ).forEach( initDirectory );
	}


	// Public hook for page builders that inject markup later.
	window.munipbInit = initAll;

	// Elementor (front end and editor preview): re-init whenever the widget is (re)rendered.
	function hookElementor() {
		if ( window.elementorFrontend && window.elementorFrontend.hooks && ! hookElementor.done ) {
			hookElementor.done = true;
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/municipal-phonebook.default', function ( $scope ) {
				initAll( $scope && $scope[ 0 ] ? $scope[ 0 ] : document );
			} );
		}
	}
	if ( window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', hookElementor );
	}
	hookElementor();

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initAll();
		} );
	} else {
		initAll();
	}
}() );
