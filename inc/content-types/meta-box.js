/**
 * The Content Types meta box behavior — star ratings, image and gallery
 * fields — for entries edited in the classic meta box. Enqueued by
 * inc/content-types/mu-runtime.php after the media editor; a file rather than
 * an inline script because the wordpress.org review refuses heredoc/nowdoc
 * PHP and inline scripts are what a reviewer reads first (checklist B5).
 * Ships in both editions with the runtime shim that owns it.
 */
( function () {
	const L = window.blocklaneProCtL10n || {};
	document.addEventListener( 'click', function ( e ) {
		const t = e.target;
		if ( t.classList.contains( 'blocklane-pro-ct-mb__star' ) ) {
			const sw = t.closest( '.blocklane-pro-ct-mb__stars' );
			const v = parseInt( t.getAttribute( 'data-v' ), 10 ) || 0;
			sw.querySelector( '.blocklane-pro-ct-mb__starval' ).value = v;
			sw.querySelectorAll( '.blocklane-pro-ct-mb__star' ).forEach(
				function ( s, i ) {
					s.classList.toggle( 'is-on', i + 1 <= v );
					s.setAttribute(
						'aria-pressed',
						i + 1 === v ? 'true' : 'false'
					);
				}
			);
		} else if ( t.classList.contains( 'blocklane-pro-ct-mb__starclear' ) ) {
			const cw = t.closest( '.blocklane-pro-ct-mb__stars' );
			cw.querySelector( '.blocklane-pro-ct-mb__starval' ).value = 0;
			cw.querySelectorAll( '.blocklane-pro-ct-mb__star' ).forEach(
				function ( s ) {
					s.classList.remove( 'is-on' );
					s.setAttribute( 'aria-pressed', 'false' );
				}
			);
		} else if (
			t.classList.contains( 'blocklane-pro-ct-mb__imageselect' )
		) {
			e.preventDefault();
			const iw = t.closest( '.blocklane-pro-ct-mb__image' );
			const frame = wp.media( {
				title: 'Select image',
				multiple: false,
				library: { type: 'image' },
			} );
			frame.on( 'select', function () {
				const att = frame.state().get( 'selection' ).first().toJSON();
				iw.querySelector( '.blocklane-pro-ct-mb__imageval' ).value =
					att.url;
				let img = iw.querySelector(
					'.blocklane-pro-ct-mb__imagepreview'
				);
				if ( ! img ) {
					img = document.createElement( 'img' );
					img.className = 'blocklane-pro-ct-mb__imagepreview';
					img.alt = '';
					iw.insertBefore( img, t );
				}
				img.src = att.url;
				const clr = iw.querySelector(
					'.blocklane-pro-ct-mb__imageclear'
				);
				if ( clr ) {
					clr.style.display = '';
				}
			} );
			frame.open();
		} else if (
			t.classList.contains( 'blocklane-pro-ct-mb__imageclear' )
		) {
			const dw = t.closest( '.blocklane-pro-ct-mb__image' );
			dw.querySelector( '.blocklane-pro-ct-mb__imageval' ).value = '';
			const pv = dw.querySelector( '.blocklane-pro-ct-mb__imagepreview' );
			if ( pv ) {
				pv.remove();
			}
			t.style.display = 'none';
		} else if ( t.classList.contains( 'blocklane-pro-ct-mb__galselect' ) ) {
			e.preventDefault();
			const gw = t.closest( '.blocklane-pro-ct-mb__gallery' );
			const valInput = gw.querySelector( '.blocklane-pro-ct-mb__galval' );
			const gframe = wp.media( {
				title: L.selectImages || 'Select images',
				multiple: 'add',
				library: { type: 'image' },
			} );
			gframe.on( 'open', function () {
				const sel = gframe.state().get( 'selection' );
				( valInput.value ? valInput.value.split( ',' ) : [] ).forEach(
					function ( id ) {
						const a = wp.media.attachment( id );
						a.fetch();
						sel.add( a );
					}
				);
			} );
			gframe.on( 'select', function () {
				const ids = [],
					preview = gw.querySelector(
						'.blocklane-pro-ct-mb__galpreview'
					);
				preview.innerHTML = '';
				gframe
					.state()
					.get( 'selection' )
					.toJSON()
					.forEach( function ( att ) {
						ids.push( att.id );
						const span = document.createElement( 'span' );
						span.className = 'blocklane-pro-ct-mb__galthumb';
						const img = document.createElement( 'img' );
						img.src =
							att.sizes && att.sizes.thumbnail
								? att.sizes.thumbnail.url
								: att.url;
						img.alt = '';
						span.appendChild( img );
						preview.appendChild( span );
					} );
				valInput.value = ids.join( ',' );
				t.textContent = ids.length
					? L.editGallery || 'Edit gallery'
					: L.addImages || 'Add images';
				const gclr = gw.querySelector(
					'.blocklane-pro-ct-mb__galclear'
				);
				if ( gclr ) {
					gclr.style.display = ids.length ? '' : 'none';
				}
			} );
			gframe.open();
		} else if ( t.classList.contains( 'blocklane-pro-ct-mb__galclear' ) ) {
			const gcw = t.closest( '.blocklane-pro-ct-mb__gallery' );
			gcw.querySelector( '.blocklane-pro-ct-mb__galval' ).value = '';
			gcw.querySelector( '.blocklane-pro-ct-mb__galpreview' ).innerHTML =
				'';
			const gsel = gcw.querySelector( '.blocklane-pro-ct-mb__galselect' );
			if ( gsel ) {
				gsel.textContent = L.addImages || 'Add images';
			}
			t.style.display = 'none';
		}
	} );
} )();
