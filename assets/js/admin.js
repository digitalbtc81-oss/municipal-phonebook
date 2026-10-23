/**
 * Column repeater, department column order and the media picker.
 */
( function () {
	'use strict';

	var i18n = window.munipbAdmin || {};

	var GREEK = {
		'ά': 'a', 'ὰ': 'a', 'έ': 'e', 'ὲ': 'e', 'ή': 'i', 'ὴ': 'i', 'ί': 'i', 'ὶ': 'i', 'ΐ': 'i', 'ϊ': 'i',
		'ό': 'o', 'ὸ': 'o', 'ύ': 'y', 'ὺ': 'y', 'ΰ': 'y', 'ϋ': 'y', 'ώ': 'o', 'ὼ': 'o',
		'θ': 'th', 'χ': 'ch', 'ψ': 'ps',
		'α': 'a', 'β': 'b', 'γ': 'g', 'δ': 'd', 'ε': 'e', 'ζ': 'z', 'η': 'i', 'ι': 'i',
		'κ': 'k', 'λ': 'l', 'μ': 'm', 'ν': 'n', 'ξ': 'x', 'ο': 'o', 'π': 'p', 'ρ': 'r',
		'σ': 's', 'ς': 's', 'τ': 't', 'υ': 'y', 'φ': 'f', 'ω': 'o'
	};

	function slugify( text ) {
		var lower = String( text || '' ).toLowerCase();
		var out = '';
		for ( var i = 0; i < lower.length; i++ ) {
			out += Object.prototype.hasOwnProperty.call( GREEK, lower.charAt( i ) ) ? GREEK[ lower.charAt( i ) ] : lower.charAt( i );
		}
		out = out.replace( /[^a-z0-9]+/g, '_' ).replace( /^_+|_+$/g, '' );
		if ( ! /^[a-z]/.test( out ) ) {
			out = 'col_' + out;
		}
		return out.replace( /^_+|_+$/g, '' ).slice( 0, 32 );
	}

	function reindex( table ) {
		var rows = table.querySelectorAll( 'tbody tr' );
		rows.forEach( function ( row, index ) {
			row.querySelectorAll( '[name]' ).forEach( function ( field ) {
				field.name = field.name.replace( /munipb_settings\[columns\]\[[^\]]+\]/, 'munipb_settings[columns][' + index + ']' );
			} );
		} );
	}

	function moveRow( row, direction ) {
		var sibling = direction < 0 ? row.previousElementSibling : row.nextElementSibling;
		if ( ! sibling ) {
			return;
		}
		if ( direction < 0 ) {
			row.parentNode.insertBefore( row, sibling );
		} else {
			row.parentNode.insertBefore( sibling, row );
		}
	}

	function bindSortable( container, itemSelector ) {
		var dragged = null;
		container.addEventListener( 'dragstart', function ( event ) {
			dragged = event.target.closest( itemSelector );
			if ( ! dragged || ! container.contains( dragged ) ) {
				dragged = null;
				return;
			}
			event.dataTransfer.effectAllowed = 'move';
		} );
		container.addEventListener( 'dragover', function ( event ) {
			if ( ! dragged ) {
				return;
			}
			var target = event.target.closest( itemSelector );
			if ( ! target || target === dragged || ! container.contains( target ) ) {
				return;
			}
			event.preventDefault();
			var rect = target.getBoundingClientRect();
			var after = event.clientY > rect.top + rect.height / 2;
			target.parentNode.insertBefore( dragged, after ? target.nextElementSibling : target );
		} );
		container.addEventListener( 'drop', function ( event ) {
			if ( dragged ) {
				event.preventDefault();
			}
		} );
		container.addEventListener( 'dragend', function () {
			dragged = null;
		} );
		container.addEventListener( 'click', function ( event ) {
			var up = event.target.closest( '.munipb-up' );
			var down = event.target.closest( '.munipb-down' );
			var row = event.target.closest( itemSelector );
			if ( ! row ) {
				return;
			}
			if ( up ) {
				event.preventDefault();
				moveRow( row, -1 );
			} else if ( down ) {
				event.preventDefault();
				moveRow( row, 1 );
			}
		} );
	}

	function syncLinkField( row ) {
		var type = row.querySelector( '.munipb-column-type' );
		var link = row.querySelector( '.munipb-column-link' );
		if ( ! link ) {
			return;
		}
		var file = type && type.value === 'file';
		if ( file ) {
			link.hidden = false;
			link.removeAttribute( 'hidden' );
		} else {
			link.hidden = true;
		}
	}

	function initColumns() {
		var table = document.getElementById( 'munipb-columns' );
		var add = document.getElementById( 'munipb-add-column' );
		var template = document.getElementById( 'munipb-column-template' );
		if ( ! table || ! add || ! template ) {
			return;
		}
		bindSortable( table.querySelector( 'tbody' ), 'tr' );
		table.addEventListener( 'click', function ( event ) {
			var remove = event.target.closest( '.munipb-remove-column' );
			if ( ! remove ) {
				return;
			}
			event.preventDefault();
			var row = remove.closest( 'tr' );
			if ( row && row.getAttribute( 'data-builtin' ) !== '1' ) {
				row.parentNode.removeChild( row );
			}
		} );
		table.addEventListener( 'change', function ( event ) {
			var row = event.target.closest( 'tr' );
			if ( row ) {
				syncLinkField( row );
			}
		} );
		table.addEventListener( 'input', function ( event ) {
			if ( ! event.target.classList.contains( 'munipb-column-label' ) ) {
				return;
			}
			var row = event.target.closest( 'tr' );
			var key = row ? row.querySelector( '.munipb-column-key' ) : null;
			if ( key && ! key.readOnly && ( key.value === '' || key.dataset.auto === '1' ) ) {
				key.value = slugify( event.target.value );
				key.dataset.auto = '1';
			}
		} );
		table.addEventListener( 'input', function ( event ) {
			if ( event.target.classList.contains( 'munipb-column-key' ) ) {
				event.target.dataset.auto = '0';
			}
		} );
		add.addEventListener( 'click', function () {
			var body = table.querySelector( 'tbody' );
			var index = body.querySelectorAll( 'tr' ).length;
			var html = template.innerHTML.replace( /__i__/g, String( index ) );
			var holder = document.createElement( 'tbody' );
			holder.innerHTML = html.trim();
			var row = holder.querySelector( 'tr' );
			if ( ! row ) {
				return;
			}
			var key = row.querySelector( '.munipb-column-key' );
			if ( key ) {
				key.dataset.auto = '1';
			}
			body.appendChild( row );
			syncLinkField( row );
			var label = row.querySelector( '.munipb-column-label' );
			if ( label ) {
				label.focus();
			}
		} );
		var form = table.closest( 'form' );
		if ( form ) {
			form.addEventListener( 'submit', function () {
				reindex( table );
			} );
		}
	}

	function initPicker() {
		var list = document.querySelector( '.munipb-column-picker' );
		if ( ! list ) {
			return;
		}
		bindSortable( list, 'li' );
	}

	function initFiles() {
		document.querySelectorAll( '[data-munipb-file]' ).forEach( function ( field ) {
			var input = field.querySelector( 'input[type="hidden"]' );
			var name = field.querySelector( '.munipb-file-name' );
			var choose = field.querySelector( '.munipb-file-choose' );
			var clear = field.querySelector( '.munipb-file-clear' );
			if ( ! input || ! choose ) {
				return;
			}
			choose.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				if ( ! window.wp || ! wp.media ) {
					return;
				}
				var frame = wp.media( {
					title: i18n.frameTitle || 'Choose a file',
					button: { text: i18n.frameButton || 'Use this file' },
					multiple: false
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					input.value = String( attachment.id || '' );
					if ( name ) {
						name.textContent = attachment.filename || attachment.title || input.value;
					}
				} );
				frame.open();
			} );
			if ( clear ) {
				clear.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					input.value = '';
					if ( name ) {
						name.textContent = i18n.noFile || 'No file selected';
					}
				} );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initColumns();
			initPicker();
			initFiles();
		} );
	} else {
		initColumns();
		initPicker();
		initFiles();
	}
}() );
