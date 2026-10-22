/**
 * Municipal Phonebook – front-end search and table of contents.
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

	function localSearch( index, words ) {
		return index.filter( function ( item ) {
			return words.every( function ( w ) {
				if ( item.search.indexOf( w ) !== -1 ) {
					return true;
				}
				var d = digitsOnly( w );
				return d.length >= 3 && item.digits.indexOf( d ) !== -1;
			} );
		} ).slice( 0, MAX_RESULTS );
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

		form.classList.add( 'munipb-js' );
		if ( submit ) {
			submit.hidden = true;
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
			var words = normalize( query ).split( /\s+/ ).filter( Boolean );
			render( localSearch( index, words ), words );
		}
		input.addEventListener( 'input', function () {
			var q = input.value.trim();
			clearTimeout( timer );
			if ( clear ) {
				clear.hidden = q === '';
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
				hide();
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
				input.focus();
			} );
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Table of contents                                                   */
	/* ------------------------------------------------------------------ */

	function observeSections( state ) {
		if ( ! state.toc || ! ( 'IntersectionObserver' in window ) ) {
			return;
		}
		if ( state.observer ) {
			state.observer.disconnect();
		}
		var links = state.toc.querySelectorAll( 'a[data-munipb-target]' );
		state.observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( ! entry.isIntersecting ) {
					return;
				}
				links.forEach( function ( a ) {
					var active = a.getAttribute( 'data-munipb-target' ) === entry.target.id;
					a.classList.toggle( 'is-active', active );
					if ( active ) {
						a.setAttribute( 'aria-current', 'location' );
					} else {
						a.removeAttribute( 'aria-current' );
					}
				} );
			} );
		}, { rootMargin: '-10% 0px -70% 0px', threshold: 0 } );
		state.root.querySelectorAll( '.munipb-section' ).forEach( function ( s ) {
			state.observer.observe( s );
		} );
	}

	function initToc( state ) {
		var toc = state.root.querySelector( '.munipb-toc' );
		if ( ! toc ) {
			return;
		}
		state.toc = toc;
		var toggle = toc.querySelector( '.munipb-toc-toggle' );
		var list = toc.querySelector( '.munipb-toc-list' );
		toc.classList.add( 'munipb-js' );
		list.hidden = true;

		function setOpen( open ) {
			list.hidden = ! open;
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			toc.classList.toggle( 'is-open', open );
		}

		toggle.addEventListener( 'click', function () {
			setOpen( list.hidden );
		} );

		list.addEventListener( 'click', function ( e ) {
			var link = e.target.closest( 'a[data-munipb-target]' );
			if ( ! link ) {
				return;
			}
			var targetId = link.getAttribute( 'data-munipb-target' );
			var target = document.getElementById( targetId );
			e.preventDefault();
			setOpen( false );
			if ( target ) {
				revealTarget( target );
				// Keep whatever state other scripts stored (e.g. the WP Interactivity API) on this entry.
				window.history.pushState( window.history.state, '', '#' + targetId );
			}
		} );

		observeSections( state );
	}

	/* ------------------------------------------------------------------ */
	/* Boot                                                                */
	/* ------------------------------------------------------------------ */

	function initDirectory( root ) {
		if ( ! root || root.munipbState ) {
			return;
		}
		var state = {
			root: root,
			toc: null,
			observer: null
		};
		root.munipbState = state;

		initSearch( state );
		initToc( state );

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
