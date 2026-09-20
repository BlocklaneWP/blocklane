/**
 * In-modal "Replace file".
 *
 * Adds a "Replace file" action to the attachment-details actions (before
 * "Download file"). Clicking it opens a file picker and replaces the file in
 * place via REST — same attachment, same URL — then refreshes the preview.
 * Vanilla JS, no dependencies; works wherever the media modal/sidebar renders.
 */
( function () {
	const cfg = window.blocklaneProMediaReplace || {};
	if ( ! cfg.restUrl ) {
		return;
	}

	let picker = null;
	let pendingId = null;
	let busy = false;

	function filePicker() {
		if ( picker ) {
			return picker;
		}
		picker = document.createElement( 'input' );
		picker.type = 'file';
		picker.style.display = 'none';
		picker.addEventListener( 'change', function () {
			if ( picker.files.length && pendingId ) {
				replace( pendingId, picker.files[ 0 ] );
			}
			picker.value = '';
		} );
		document.body.appendChild( picker );
		return picker;
	}

	function attachmentId( actions ) {
		// The "Edit more details" link points at post.php?post=ID — most reliable
		// across the various modal/details templates (their links aren't classed).
		const links = actions.querySelectorAll( 'a[href]' );
		for ( let i = 0; i < links.length; i++ ) {
			const m = /[?&]post=(\d+)/.exec(
				links[ i ].getAttribute( 'href' ) || ''
			);
			if ( m ) {
				return m[ 1 ];
			}
		}
		const selected = document.querySelector(
			'.attachment.details[data-id], .attachment.selected[data-id]'
		);
		return selected ? selected.getAttribute( 'data-id' ) : null;
	}

	function downloadLink( actions ) {
		return actions.querySelector( 'a[download], a.download-attachment' );
	}

	function bustCache( url ) {
		const base = url.split( '?' )[ 0 ];
		Array.prototype.forEach.call( document.images, function ( img ) {
			if ( ( img.src || '' ).split( '?' )[ 0 ] === base ) {
				img.src = url;
			}
		} );
	}

	function replace( id, file ) {
		busy = true;
		const link = document.querySelector(
			'.blocklane-pro-replace-link[data-id="' + id + '"]'
		);
		const label = link ? link.textContent : '';
		const restore = function () {
			busy = false;
			if ( link ) {
				link.textContent = label;
				link.style.pointerEvents = '';
			}
		};
		if ( link ) {
			link.textContent = cfg.working;
			link.style.pointerEvents = 'none';
		}

		const body = new FormData();
		body.append( 'id', id );
		body.append( 'file', file );

		fetch( cfg.restUrl, {
			method: 'POST',
			headers: { 'X-WP-Nonce': cfg.nonce },
			body,
			credentials: 'same-origin',
		} )
			.then( function ( response ) {
				return response.json().then( function ( json ) {
					return { ok: response.ok, json };
				} );
			} )
			.then( function ( result ) {
				if ( ! result.ok ) {
					if ( link ) {
						link.style.pointerEvents = '';
						link.textContent =
							( result.json && result.json.message ) || cfg.error;
						window.setTimeout( restore, 4000 );
					}
					return;
				}
				if ( result.json && result.json.url ) {
					bustCache( result.json.url );
				}
				if ( link ) {
					link.style.pointerEvents = '';
					link.textContent = cfg.done;
					window.setTimeout( restore, 3000 );
				}
			} )
			.catch( function () {
				if ( link ) {
					link.style.pointerEvents = '';
					link.textContent = cfg.error;
					window.setTimeout( restore, 4000 );
				}
			} );
	}

	function inject() {
		const lists = document.querySelectorAll(
			'.attachment-details .actions, .media-sidebar .actions'
		);
		Array.prototype.forEach.call( lists, function ( actions ) {
			if ( actions.querySelector( '.blocklane-pro-replace-link' ) ) {
				return;
			}
			const id = attachmentId( actions );
			const link = document.createElement( 'a' );
			link.href = '#';
			link.className = 'blocklane-pro-replace-link';
			link.textContent = cfg.label;
			if ( id ) {
				link.setAttribute( 'data-id', id );
			}

			const download = downloadLink( actions );
			if ( download ) {
				// Insert "<link> | " before Download. Core's .links-separator spans
				// get their spacing from surrounding whitespace text nodes, so add a
				// space on each side of the pipe to match.
				const separator = actions.querySelector( '.links-separator' );
				actions.insertBefore( link, download );
				actions.insertBefore(
					document.createTextNode( ' ' ),
					download
				);
				actions.insertBefore(
					separator
						? separator.cloneNode( true )
						: document.createTextNode( '|' ),
					download
				);
				actions.insertBefore(
					document.createTextNode( ' ' ),
					download
				);
			} else {
				actions.appendChild( link );
			}
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		const link = event.target.closest( '.blocklane-pro-replace-link' );
		if ( ! link ) {
			return;
		}
		event.preventDefault();
		if ( busy ) {
			return;
		}
		let id = link.getAttribute( 'data-id' );
		if ( ! id ) {
			const actions = link.closest( '.actions' );
			if ( actions ) {
				id = attachmentId( actions );
			}
		}
		if ( ! id ) {
			return;
		}
		pendingId = id;
		filePicker().click();
	} );

	if ( window.MutationObserver ) {
		new window.MutationObserver( inject ).observe( document.body, {
			childList: true,
			subtree: true,
		} );
	}
	inject();
} )();
