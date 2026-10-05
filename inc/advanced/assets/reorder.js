/**
 * Drag-to-reorder for the "Sort by Order" view of an enabled post type's list
 * table, plus nesting for hierarchical types (pages).
 *
 * Flat types: drag a row and the new order of row IDs is sent to the REST
 * endpoint, which re-sequences menu_order.
 *
 * Hierarchical types render core's tree view, so structure comes first:
 * dragging reorders an item among its current siblings only — its subtree
 * moves with it, and the drop snaps to the nearest sibling boundary — while
 * injected Indent/Outdent row actions re-parent: Indent nests an item under
 * its previous sibling (its position doesn't change, only its depth), Outdent
 * lifts it out to become its parent's next sibling. Every row with children
 * gets a chevron that collapses/expands its subtree — a per-browser view
 * state (localStorage), so big page lists read as groups while you work.
 * Structure is read fresh on every operation — the row order from the DOM,
 * each row's true parent from the Quick Edit inline data (#inline_{id}
 * .post_parent, refreshed by core on inline save) with a PHP-localized
 * parent map as the fallback — so there is no client model to drift out
 * of sync.
 *
 * While saving, the list is locked (no drags or arrows) and the moved row
 * shows WordPress's native .spinner. A failed save rolls the DOM back so the
 * table keeps matching the (unchanged) server state. Plain jQuery + the
 * bundled jquery-ui-sortable so it needs no build step and matches core's
 * admin tooling.
 *
 * @param {jQuery} $ The jQuery instance.
 */
( function ( $ ) {
	$( function () {
		const data = window.blocklaneProReorder || {};
		const i18n = data.i18n || {};
		const list = $( '#the-list' );

		if ( ! list.length || ! data.restUrl ) {
			return;
		}

		const hierarchical = !! data.hierarchical;
		let saving = false;

		/* ---- messages ----------------------------------------------------- */

		const speak = function ( msg ) {
			if ( window.wp && window.wp.a11y && window.wp.a11y.speak ) {
				window.wp.a11y.speak( msg );
			}
		};

		// Surface a failed save as a dismissible admin notice above the table
		// (and to screen readers).
		const showError = function () {
			const msg =
				i18n.saveFailed ||
				'Couldn’t save the new order. Please refresh and try again.';
			$( '.blocklane-pro-reorder-notice' ).remove();
			const notice = $(
				'<div class="notice notice-error is-dismissible blocklane-pro-reorder-notice"><p></p></div>'
			);
			notice.find( 'p' ).text( msg );
			$( '.wp-list-table' ).first().before( notice );
			speak( msg );
		};

		// Placeholder substitution for the localized announcement strings
		// (wp-i18n is a script dependency).
		const sprintf = window.wp.i18n.sprintf;

		/* ---- structure (read fresh from core markup each operation) ------- */

		const idOf = function ( tr ) {
			const match = /post-(\d+)/.exec( tr.id );
			return match ? parseInt( match[ 1 ], 10 ) : 0;
		};

		// Parent lookups chain three sources: core's Quick Edit inline data
		// when the row has it (core refreshes it on inline save, so it's the
		// freshest), then this script's own re-parent bookkeeping, then the
		// map PHP localized at page load. The fallbacks matter: inline data
		// is absent when Quick Edit is disabled for the type or the user
		// can't edit a given row, and without them the whole tree would
		// silently read as flat.
		const serverParents = data.parents || {};
		const clientParents = {};

		const parentOf = function ( id ) {
			const holder = document.getElementById( 'inline_' + id );
			if ( holder ) {
				return (
					parseInt( $( holder ).find( '.post_parent' ).text(), 10 ) ||
					0
				);
			}
			if ( Object.prototype.hasOwnProperty.call( clientParents, id ) ) {
				return clientParents[ id ];
			}
			return parseInt( serverParents[ id ], 10 ) || 0;
		};

		const setParent = function ( id, parent ) {
			const holder = document.getElementById( 'inline_' + id );
			if ( holder ) {
				$( holder ).find( '.post_parent' ).text( String( parent ) );
			} else {
				clientParents[ id ] = parent;
			}
		};

		/**
		 * Snapshot the tree: rows in DOM order with id, parent, depth, and
		 * sibling group. A parent that isn't in the list (an orphan) counts as
		 * top level, matching how core's walker displays it.
		 */
		const buildModel = function () {
			const items = [];
			const byId = {};
			list.children( 'tr' ).each( function () {
				const id = idOf( this );
				if ( ! id ) {
					return;
				}
				const item = {
					id,
					tr: this,
					parent: parentOf( id ),
				};
				items.push( item );
				byId[ id ] = item;
			} );
			items.forEach( function ( item, index ) {
				let level = 0;
				let p = byId[ item.parent ];
				while ( p && level < items.length ) {
					level++;
					p = byId[ p.parent ];
				}
				item.level = level;
				item.index = index;
				item.group = byId[ item.parent ] ? item.parent : 0;
			} );
			items.forEach( function ( item ) {
				const parent = byId[ item.parent ];
				if ( parent ) {
					parent.childCount = ( parent.childCount || 0 ) + 1;
				}
			} );
			return { items, byId };
		};

		const isInSubtree = function ( item, rootId, byId ) {
			let p = byId[ item.parent ];
			let hops = 0;
			while ( p && hops < 1000 ) {
				if ( p.id === rootId ) {
					return true;
				}
				p = byId[ p.parent ];
				hops++;
			}
			return false;
		};

		/**
		 * An item's descendants, in DOM order.
		 * @param {Object} item  A buildModel() item.
		 * @param {Object} model The buildModel() result.
		 */
		const subtreeOf = function ( item, model ) {
			return model.items.filter( function ( it ) {
				return it !== item && isInSubtree( it, item.id, model.byId );
			} );
		};

		/**
		 * The other members of an item's sibling group, in DOM order.
		 * @param {Object} item  A buildModel() item.
		 * @param {Object} model The buildModel() result.
		 */
		const siblingsOf = function ( item, model ) {
			return model.items.filter( function ( it ) {
				return it !== item && it.group === item.group;
			} );
		};

		/**
		 * A row's title cell. WordPress 7.1 made the title cell the row's
		 * header — <th scope="row"> instead of <td> — so match either.
		 * @param {HTMLTableRowElement} tr
		 */
		const titleCellOf = function ( tr ) {
			return $( tr )
				.children( 'td.column-title, th.column-title' )
				.first();
		};

		/**
		 * Row title without core's depth pad or its hidden annotations —
		 * 7.1 adds screen-reader-only "Child of …" spans and a visible
		 * trimmed-excerpt span for untitled posts; neither belongs in a
		 * spoken "Collapse %s" label.
		 * @param {HTMLTableRowElement} tr
		 */
		const titleOf = function ( tr ) {
			const strong = titleCellOf( tr ).find( 'strong' ).first();
			let label = strong.find( '.row-title' ).first();
			if ( ! label.length ) {
				// Rows the user can't edit wrap the title in a plain span;
				// skip 7.1's aria-hidden depth-pad spans to find it.
				label = strong
					.children( 'span' )
					.not( '[aria-hidden="true"]' )
					.first();
			}
			const clone = ( label.length ? label : strong ).clone();
			clone
				.find( '.screen-reader-text, .hidden, .trimmed-post-excerpt' )
				.remove();
			return clone
				.text()
				.replace( /^(— )+/, '' )
				.trim();
		};

		/**
		 * Strip core's depth pad from a row's title — tree mode indents
		 * nested rows with real padding (the level-N CSS) instead, so the
		 * dashes would double-encode depth. Two markups: 7.1 renders the pad
		 * as aria-hidden "—" spans before the title link, 7.0 as a "— " text
		 * prefix inside it. Idempotent, and re-applied on every refresh
		 * because Quick Edit re-renders rows with fresh pads.
		 * @param {HTMLTableRowElement} tr
		 */
		const stripPad = function ( tr ) {
			const strong = titleCellOf( tr ).find( 'strong' ).first();
			// 7.1: remove each pad span and the space that follows it. The
			// post-state separator is a plain text node and the chevron's
			// dashicon sits inside its button, so only pad spans match.
			strong.children( 'span[aria-hidden="true"]' ).each( function () {
				const next = this.nextSibling;
				if ( next && 3 === next.nodeType ) {
					next.data = next.data.replace( /^\s+/, '' );
				}
				this.parentNode.removeChild( this );
			} );
			// 7.0: strip the text prefix inside the link (or plain span).
			let target = strong.find( '.row-title' ).first()[ 0 ];
			if ( ! target ) {
				target = strong.children( 'span' ).first()[ 0 ];
			}
			const node = target && target.firstChild;
			if ( node && 3 === node.nodeType ) {
				node.data = node.data.replace( /^(— )+/, '' );
			}
		};

		/**
		 * Set the visible depth of one row — the level-N class drives the
		 * CSS indentation.
		 * @param {HTMLTableRowElement} tr
		 * @param {number}              level
		 */
		const setLevel = function ( tr, level ) {
			tr.className =
				tr.className.replace( /(^|\s)level-\d+/g, '' ) +
				' level-' +
				level;
			// Depth also drives the title indentation (see reorder.css) via a
			// custom property, so the tree indents at ANY depth rather than
			// flattening past the last enumerated level class.
			tr.style.setProperty( '--blocklane-pro-level', level );
		};

		/* ---- collapse / expand (hierarchical only) -------------------------- */

		// Collapsed parent IDs survive reloads, per browser + post type. This is
		// a view state, not content — nothing about it is saved to the server.
		const collapseKey = 'blocklaneProReorderCollapsed:' + data.postType;
		let collapsedIds = {};
		try {
			(
				JSON.parse( window.localStorage.getItem( collapseKey ) ) || []
			).forEach( function ( id ) {
				collapsedIds[ id ] = true;
			} );
		} catch ( e ) {
			collapsedIds = {};
		}

		/**
		 * Store the collapsed set, pruned to rows that still have children.
		 * @param {Object} model The buildModel() result.
		 */
		const persistCollapsed = function ( model ) {
			const keep = [];
			model.items.forEach( function ( item ) {
				if ( collapsedIds[ item.id ] && item.childCount ) {
					keep.push( item.id );
				}
			} );
			collapsedIds = {};
			keep.forEach( function ( id ) {
				collapsedIds[ id ] = true;
			} );
			try {
				window.localStorage.setItem(
					collapseKey,
					JSON.stringify( keep )
				);
			} catch ( e ) {
				// Storage unavailable (private mode) — collapse still works,
				// it just won't survive a reload.
			}
		};

		/**
		 * A chevron before the title of every row that has children; clicking
		 * hides or shows the whole subtree.
		 * @param {Object} model The buildModel() result.
		 */
		const renderToggles = function ( model ) {
			model.items.forEach( function ( item ) {
				stripPad( item.tr );
				// Appended inside the <strong> (core styles it display:block)
				// so the chevron sits inline at the end of the title, after any
				// post state ("— Front Page", "— Draft").
				const strong = titleCellOf( item.tr ).find( 'strong' ).first();
				let toggle = strong.children( '.blocklane-pro-reorder-toggle' );

				if ( ! item.childCount ) {
					toggle.remove();
					return;
				}
				if ( ! toggle.length ) {
					toggle = $(
						'<button type="button" class="blocklane-pro-reorder-toggle"><span class="dashicons" aria-hidden="true"></span></button>'
					);
					strong.append( toggle );
				}

				const isCollapsed = !! collapsedIds[ item.id ];
				toggle
					.attr( 'aria-expanded', isCollapsed ? 'false' : 'true' )
					.attr(
						'aria-label',
						// eslint-disable-next-line @wordpress/valid-sprintf -- template is localized server-side.
						sprintf(
							isCollapsed
								? i18n.expand || 'Expand %s'
								: i18n.collapse || 'Collapse %s',
							titleOf( item.tr )
						)
					);
				toggle
					.find( '.dashicons' )
					.attr(
						'class',
						'dashicons ' +
							( isCollapsed
								? 'dashicons-arrow-right'
								: 'dashicons-arrow-down' )
					);
			} );
		};

		/**
		 * A "Collapse All" / "Expand All" button beside the list's Filter
		 * controls — one click folds the whole tree to its top-level groups
		 * (or unfolds it); the label follows the current state.
		 * @param {Object} model The buildModel() result.
		 */
		const renderBulkToggle = function ( model ) {
			const hasParents = model.items.some( function ( it ) {
				return it.childCount;
			} );
			let holder = $( '.tablenav.top .blocklane-pro-reorder-bulk' );

			if ( ! hasParents ) {
				holder.remove();
				return;
			}
			if ( ! holder.length ) {
				holder = $(
					'<div class="alignleft actions blocklane-pro-reorder-bulk"><button type="button" class="button"></button></div>'
				);
				const actions = $( '.tablenav.top .actions' ).last();
				if ( actions.length ) {
					holder.insertAfter( actions );
				} else {
					$( '.tablenav.top' ).prepend( holder );
				}
			}

			const anyExpanded = model.items.some( function ( it ) {
				return it.childCount && ! collapsedIds[ it.id ];
			} );
			holder
				.children( 'button' )
				.text(
					anyExpanded
						? i18n.collapseAll || 'Collapse All'
						: i18n.expandAll || 'Expand All'
				);
		};

		/**
		 * Hide every row that sits under a collapsed ancestor, and stripe the
		 * table by GROUP instead of by row: each top-level item flips the
		 * stripe color and its whole subtree inherits it, so a group reads as
		 * one block whether it's expanded or collapsed.
		 * @param {Object} model The buildModel() result.
		 */
		const applyVisibility = function ( model ) {
			let alt = true; // First top-level row flips this to false (white).
			model.items.forEach( function ( item ) {
				let hidden = false;
				let p = model.byId[ item.parent ];
				let hops = 0;
				while ( p && hops < 1000 ) {
					if ( collapsedIds[ p.id ] ) {
						hidden = true;
						break;
					}
					p = model.byId[ p.parent ];
					hops++;
				}
				if ( 0 === item.level ) {
					alt = ! alt;
				}
				item.hidden = hidden;
				$( item.tr )
					.toggleClass( 'blocklane-pro-reorder-hidden', hidden )
					.toggleClass( 'blocklane-pro-reorder-alt', alt );
			} );
		};

		/* ---- row actions, tree lines, and the one refresh pass ------------- */

		/**
		 * (Re)build the injected row actions from the current tree: Indent only
		 * where a previous sibling exists to nest under, Outdent only below the
		 * top level. Re-run after every operation — each one shifts validity.
		 * @param {Object} model The buildModel() result.
		 */
		const renderNestActions = function ( model ) {
			model.items.forEach( function ( item ) {
				const actions = $( item.tr ).find( '.row-actions' ).first();
				if ( ! actions.length ) {
					return;
				}
				actions.children( '.blocklane-pro-nest-action' ).remove();

				const addAction = function ( cls, label ) {
					const span = $( '<span/>' ).addClass(
						'blocklane-pro-nest-action ' + cls
					);
					span.append( document.createTextNode( ' | ' ) );
					span.append(
						$(
							'<button type="button" class="button-link"></button>'
						).text( label )
					);
					actions.append( span );
				};

				const hasPrevSibling = siblingsOf( item, model ).some(
					function ( s ) {
						return s.index < item.index;
					}
				);
				if ( hasPrevSibling ) {
					addAction(
						'blocklane-pro-indent',
						i18n.indent || 'Indent'
					);
				}
				if ( item.level > 0 ) {
					addAction(
						'blocklane-pro-outdent',
						i18n.outdent || 'Outdent'
					);
				}
			} );
		};

		/**
		 * (Re)build the Move Up / Move Down row actions — the keyboard equivalent
		 * of a drag, so the Sort by Order view isn't mouse-only (WCAG 2.1.1). Each
		 * moves an item (and, for hierarchical types, its whole subtree) past its
		 * previous/next SIBLING; the action is omitted at the ends of the sibling
		 * group. Runs for both flat and hierarchical lists.
		 * @param {Object} model The buildModel() result.
		 */
		const renderMoveActions = function ( model ) {
			const groups = {};
			model.items.forEach( function ( it ) {
				( groups[ it.group ] = groups[ it.group ] || [] ).push( it );
			} );
			model.items.forEach( function ( item ) {
				const actions = $( item.tr ).find( '.row-actions' ).first();
				if ( ! actions.length ) {
					return;
				}
				actions.children( '.blocklane-pro-move-action' ).remove();

				const sibs = ( groups[ item.group ] || [] )
					.slice()
					.sort( function ( a, b ) {
						return a.index - b.index;
					} );
				const pos = sibs.indexOf( item );

				const addMove = function ( cls, label ) {
					const span = $( '<span/>' ).addClass(
						'blocklane-pro-move-action ' + cls
					);
					span.append( document.createTextNode( ' | ' ) );
					span.append(
						$(
							'<button type="button" class="button-link"></button>'
						).text( label )
					);
					actions.append( span );
				};

				if ( pos > 0 ) {
					addMove(
						'blocklane-pro-move-up',
						i18n.moveUp || 'Move up'
					);
				}
				if ( pos > -1 && pos < sibs.length - 1 ) {
					addMove(
						'blocklane-pro-move-down',
						i18n.moveDown || 'Move down'
					);
				}
			} );
		};

		/**
		 * Tree guide lines in the title column: a rail down every sibling
		 * group (including the root) with a tick into each title, an elbow on
		 * the group's last row. Painted as background gradients on the title
		 * cell — each row's lines depend on which of its ancestors still have
		 * siblings below, so static CSS classes can't express them. When
		 * nothing is nested the lines (and the indent gutter, via the
		 * -has-depth class) disappear entirely.
		 * @param {Object} model The buildModel() result.
		 */
		const applyTreeLines = function ( model ) {
			const LINE = '#949494';
			// The gutter: a level-L title starts at 26 + 20L. A child
			// group's rail hangs 8px left of its parent's title — the same
			// breathing room the tick leaves before each title — and the
			// root rail sits at 6. Ticks run from the rail to 8px short of
			// the title, on its first line (8px cell padding + half the
			// ~18px line box).
			const railX = function ( level ) {
				return level ? 20 * level - 2 : 6;
			};
			const titleX = function ( level ) {
				return 26 + 20 * level;
			};
			const hasFollowingSibling = function ( item ) {
				return model.items.some( function ( it ) {
					return it.group === item.group && it.index > item.index;
				} );
			};

			// Lines describe the VISIBLE tree: collapse everything and the
			// list reads flat again — gutter and lines gone.
			let maxLevel = 0;
			model.items.forEach( function ( item ) {
				if ( ! item.hidden ) {
					maxLevel = Math.max( maxLevel, item.level );
				}
			} );
			list.closest( 'table' ).toggleClass(
				'blocklane-pro-reorder-has-depth',
				maxLevel > 0
			);

			model.items.forEach( function ( item ) {
				const cell = titleCellOf( item.tr )[ 0 ];
				if ( ! cell ) {
					return;
				}
				if ( ! maxLevel || item.hidden ) {
					cell.style.backgroundImage = '';
					return;
				}

				const images = [];
				const sizes = [];
				const positions = [];
				const line = function ( w, h, x, y ) {
					images.push(
						'linear-gradient(' + LINE + ', ' + LINE + ')'
					);
					sizes.push( w + ' ' + h );
					positions.push( x + ' ' + y );
				};

				// Tick from the group rail into this title; the rail stops at
				// the tick on the group's last row (an elbow).
				line(
					titleX( item.level ) - 8 - railX( item.level ) + 'px',
					'1px',
					railX( item.level ) + 'px',
					'17px'
				);
				line(
					'1px',
					hasFollowingSibling( item ) ? '100%' : '18px',
					railX( item.level ) + 'px',
					'0'
				);

				// Ancestor rails pass through rows where that ancestor still
				// has siblings below.
				let p = model.byId[ item.parent ];
				let hops = 0;
				while ( p && hops < 1000 ) {
					if ( hasFollowingSibling( p ) ) {
						line( '1px', '100%', railX( p.level ) + 'px', '0' );
					}
					p = model.byId[ p.parent ];
					hops++;
				}

				// An expanded parent's child rail starts where its own tick
				// ends (the tick flows around the corner and down to the
				// children), 8px clear of the row actions below.
				if ( item.childCount && ! collapsedIds[ item.id ] ) {
					line(
						'1px',
						'calc(100% - 17px)',
						railX( item.level + 1 ) + 'px',
						'17px'
					);
				}

				cell.style.backgroundImage = images.join( ', ' );
				cell.style.backgroundSize = sizes.join( ', ' );
				cell.style.backgroundPosition = positions.join( ', ' );
				cell.style.backgroundRepeat = 'no-repeat';
			} );
		};

		/** One pass over the current DOM: row actions, toggles, visibility. */
		const refreshTree = function () {
			const model = buildModel();
			persistCollapsed( model );
			renderNestActions( model );
			renderMoveActions( model );
			renderToggles( model );
			renderBulkToggle( model );
			applyVisibility( model );
			applyTreeLines( model );
			// Set the depth custom property on every row (server rows don't carry
			// it) so the title indentation is correct at any depth on load and
			// after every operation.
			model.items.forEach( function ( item ) {
				item.tr.style.setProperty(
					'--blocklane-pro-level',
					item.level
				);
			} );
		};

		/* ---- saving -------------------------------------------------------- */

		/** The flat ID order of the table as a request payload. */
		const orderPayload = function () {
			const order = [];
			list.children( 'tr' ).each( function () {
				const id = idOf( this );
				if ( id ) {
					order.push( id );
				}
			} );
			return { post_type: data.postType, order };
		};

		/**
		 * Lock the table, POST the payload, and spin on the moved row. onFail
		 * must roll the DOM back so the table keeps matching the (unchanged)
		 * server state; onDone announces success. Callers that already
		 * refreshed the tree optimistically pass refreshOnSuccess=false so a
		 * successful save doesn't pay a second identical rebuild — a failure
		 * always refreshes, since onFail just rolled the DOM back.
		 * @param {Object}              payload
		 * @param {HTMLTableRowElement} movedRow
		 * @param {() => void}          onFail
		 * @param {() => void}          [onDone]
		 * @param {boolean}             [refreshOnSuccess]
		 */
		const save = function (
			payload,
			movedRow,
			onFail,
			onDone,
			refreshOnSuccess
		) {
			saving = true;
			list.sortable( 'disable' ).addClass( 'blocklane-pro-reordering' );

			const row = $( movedRow );
			row.addClass( 'blocklane-pro-reordering-row' );
			let cell = row.find( '.check-column' ).first();
			if ( ! cell.length ) {
				cell = row.children( 'td, th' ).first();
			}
			const spinner = $(
				'<span class="spinner is-active blocklane-pro-reorder-spinner"></span>'
			);
			cell.append( spinner );

			let failed = false;

			$.ajax( {
				url: data.restUrl,
				method: 'POST',
				data: payload,
				beforeSend( xhr ) {
					xhr.setRequestHeader( 'X-WP-Nonce', data.nonce );
				},
			} )
				.done( function () {
					if ( onDone ) {
						onDone();
					}
				} )
				.fail( function () {
					failed = true;
					onFail();
					showError();
				} )
				.always( function () {
					spinner.remove();
					row.removeClass( 'blocklane-pro-reordering-row' );
					list.sortable( 'enable' ).removeClass(
						'blocklane-pro-reordering'
					);
					saving = false;
					if (
						hierarchical &&
						( failed || false !== refreshOnSuccess )
					) {
						refreshTree();
					}
				} );
		};

		/* ---- Move / Indent / Outdent: optimistic change, then save ---------- */

		/**
		 * Move an item (with its subtree) above its previous sibling or below its
		 * next sibling — the keyboard sequence change. Optimistic like the drag
		 * path, then save with the same rollback + announcement.
		 * @param {HTMLElement} trigger The clicked button.
		 * @param {boolean}     down    True to move down, else up.
		 */
		const moveBy = function ( trigger, down ) {
			if ( saving ) {
				return;
			}
			const model = buildModel();
			const item =
				model.byId[ idOf( $( trigger ).closest( 'tr' )[ 0 ] ) ];
			if ( ! item ) {
				return;
			}
			const sibs = siblingsOf( item, model )
				.concat( item )
				.sort( function ( a, b ) {
					return a.index - b.index;
				} );
			const pos = sibs.indexOf( item );
			const target = down ? sibs[ pos + 1 ] : sibs[ pos - 1 ];
			if ( ! target ) {
				return; // Already first/last among its siblings.
			}

			const block = [ item ].concat( subtreeOf( item, model ) );
			const oldRows = list.children( 'tr' ).toArray();

			if ( down ) {
				// After the next sibling's whole subtree.
				const targetBlock = [ target ].concat(
					subtreeOf( target, model )
				);
				let anchor = $( targetBlock[ targetBlock.length - 1 ].tr );
				block.forEach( function ( it ) {
					anchor = $( it.tr ).insertAfter( anchor );
				} );
			} else {
				// Before the previous sibling's first row (order preserved:
				// each insertBefore lands just before the fixed target).
				block.forEach( function ( it ) {
					$( it.tr ).insertBefore( target.tr );
				} );
			}

			const refresh = function () {
				if ( hierarchical ) {
					refreshTree();
				} else {
					renderMoveActions( buildModel() );
				}
			};
			refresh();

			save(
				orderPayload(),
				item.tr,
				function () {
					list.append( oldRows );
					refresh();
				},
				function () {
					speak(
						// eslint-disable-next-line @wordpress/valid-sprintf -- template is localized server-side.
						sprintf(
							down
								? i18n.movedDown || '%s moved down.'
								: i18n.movedUp || '%s moved up.',
							titleOf( item.tr )
						)
					);
				},
				false // Already refreshed optimistically above.
			);
		};

		/**
		 * The shared commit path for Indent and Outdent: apply the optimistic
		 * DOM change (optional row move, depth shift, parent bookkeeping,
		 * tree refresh), then save — rolling every piece back on failure.
		 * @param {Object}                    opts
		 * @param {Object}                    opts.item         The buildModel() item to re-parent.
		 * @param {Object}                    opts.model        The buildModel() result.
		 * @param {number}                    opts.parent       New parent ID (0 = top level).
		 * @param {number}                    opts.delta        Depth shift for the block (+1/-1).
		 * @param {(block: Object[]) => void} [opts.move]       Moves the block's rows, given the block.
		 * @param {number|null}               [opts.recollapse] Collapsed ID to restore on failure.
		 * @param {() => void}                opts.announce     Speaks the success message.
		 */
		const reparent = function ( opts ) {
			const item = opts.item;
			const block = [ item ].concat( subtreeOf( item, opts.model ) );
			const oldRows = list.children( 'tr' ).toArray();
			const oldParent = item.parent;

			if ( opts.move ) {
				opts.move( block );
			}
			block.forEach( function ( it ) {
				setLevel( it.tr, it.level + opts.delta );
			} );
			setParent( item.id, opts.parent );
			refreshTree();

			save(
				$.extend( orderPayload(), {
					moved: item.id,
					parent: opts.parent,
				} ),
				item.tr,
				function () {
					list.append( oldRows );
					block.forEach( function ( it ) {
						setLevel( it.tr, it.level );
					} );
					setParent( item.id, oldParent );
					if ( opts.recollapse ) {
						collapsedIds[ opts.recollapse ] = true;
					}
				},
				opts.announce,
				false // Already refreshed optimistically above.
			);
		};

		const onIndent = function () {
			if ( saving ) {
				return;
			}
			const model = buildModel();
			const item = model.byId[ idOf( $( this ).closest( 'tr' )[ 0 ] ) ];
			if ( ! item ) {
				return;
			}
			const preceding = siblingsOf( item, model ).filter( function ( s ) {
				return s.index < item.index;
			} );
			const prev = preceding[ preceding.length - 1 ];
			if ( ! prev ) {
				return;
			}

			// Becoming the previous sibling's last child: the sibling's subtree
			// ends exactly where this row sits, so the position doesn't change —
			// the row and its subtree just step one level deeper. Nesting into
			// a collapsed group would make the row vanish, so expand the new
			// parent (a failed save restores its collapsed state).
			const recollapse = collapsedIds[ prev.id ] ? prev.id : null;
			delete collapsedIds[ prev.id ];

			reparent( {
				item,
				model,
				parent: prev.id,
				delta: 1,
				recollapse,
				announce() {
					speak(
						// eslint-disable-next-line @wordpress/valid-sprintf -- template is localized server-side.
						sprintf(
							i18n.indented || '%1$s is now nested under %2$s.',
							titleOf( item.tr ),
							titleOf( prev.tr )
						)
					);
				},
			} );
		};

		const onOutdent = function () {
			if ( saving ) {
				return;
			}
			const model = buildModel();
			const item = model.byId[ idOf( $( this ).closest( 'tr' )[ 0 ] ) ];
			if ( ! item || ! item.level ) {
				return;
			}
			const parent = model.byId[ item.parent ];
			if ( ! parent ) {
				return;
			}

			// The item becomes its parent's next sibling: its block moves to
			// just after the parent's subtree (excluding the block itself),
			// one level shallower.
			const inBlock = {};
			[ item ]
				.concat( subtreeOf( item, model ) )
				.forEach( function ( it ) {
					inBlock[ it.id ] = true;
				} );
			const rest = [ parent ]
				.concat( subtreeOf( parent, model ) )
				.filter( function ( it ) {
					return ! inBlock[ it.id ];
				} );
			const target = rest[ rest.length - 1 ];

			reparent( {
				item,
				model,
				parent: parent.parent,
				delta: -1,
				move( block ) {
					let anchor = $( target.tr );
					block.forEach( function ( it ) {
						anchor = $( it.tr ).insertAfter( anchor );
					} );
				},
				announce() {
					speak(
						// eslint-disable-next-line @wordpress/valid-sprintf -- template is localized server-side.
						sprintf(
							i18n.outdented || '%s moved up one level.',
							titleOf( item.tr )
						)
					);
				},
			} );
		};

		/* ---- dragging ------------------------------------------------------ */

		// Pre-drag state, captured on start so a failed save can roll back.
		let snapshot = null;
		let dragModel = null;
		let dragBlock = null;

		/**
		 * Flat types: persist the new row order as-is.
		 * @param {jQuery} movedRow
		 */
		const updateFlat = function ( movedRow ) {
			const payload = orderPayload();
			if ( ! payload.order.length ) {
				return;
			}
			const oldRows = snapshot;
			save(
				payload,
				movedRow,
				function () {
					if ( oldRows && oldRows.length ) {
						list.append( oldRows );
					}
					renderMoveActions( buildModel() );
				},
				function () {
					renderMoveActions( buildModel() );
				}
			);
		};

		/**
		 * Hierarchical types: a drag reorders an item among its current siblings.
		 * Count which siblings now precede the dropped row, snap the row to that
		 * sibling boundary, pull its subtree back under it, and save the
		 * resulting flat order. Parents never change on drag — that's what the
		 * Indent/Outdent actions are for.
		 */
		const updateTree = function () {
			const model = dragModel;
			const block = dragBlock;
			dragModel = null;
			dragBlock = null;
			if ( ! model || ! block ) {
				return;
			}
			const item = block.item;

			const domIndex = {};
			list.children( 'tr' ).each( function ( i ) {
				domIndex[ idOf( this ) ] = i;
			} );
			const preceding = siblingsOf( item, model ).filter( function ( s ) {
				return domIndex[ s.id ] < domIndex[ item.id ];
			} );

			// Snap target: after the last preceding sibling's subtree, or directly
			// after the parent row (first child), or the top of the list.
			let anchor = null;
			if ( preceding.length ) {
				const last = preceding[ preceding.length - 1 ];
				const rest = [ last ].concat( subtreeOf( last, model ) );
				anchor = $( rest[ rest.length - 1 ].tr );
			} else if ( model.byId[ item.group ] ) {
				anchor = $( model.byId[ item.group ].tr );
			}

			if ( anchor ) {
				$( item.tr ).insertAfter( anchor );
			} else {
				list.prepend( item.tr );
			}
			let tail = $( item.tr );
			block.subtree.forEach( function ( it ) {
				tail = $( it.tr ).insertAfter( tail );
			} );

			// Dropped back where it started (or inside its own subtree, which
			// snaps back): nothing changed, nothing to save.
			const before = snapshot
				? snapshot.map( idOf ).filter( Boolean )
				: [];
			const payload = orderPayload();
			if ( payload.order.join() === before.join() ) {
				return;
			}

			const oldRows = snapshot;
			save( payload, item.tr, function () {
				if ( oldRows && oldRows.length ) {
					list.append( oldRows );
				}
			} );
		};

		list.sortable( {
			// Rows hidden under a collapsed parent aren't drop candidates —
			// they travel with their subtree root instead.
			items: '> tr:not(.blocklane-pro-reorder-hidden)',
			axis: 'y',
			cursor: 'move',
			// Keep column widths while dragging so the table doesn't collapse.
			// Two rules, both measured while the row is still in the flow:
			// - Freeze the header cells too: once the dragged row goes absolute,
			//   auto table layout re-derives column widths from the REMAINING
			//   rows, sliding the columns out from under the helper.
			// - Only pin visible cells: jQuery measures a display:none cell (a
			//   column hidden via Screen Options) by briefly un-hiding it OUT of
			//   table context, and pinning that phantom width inflates the row's
			//   total — the excess gets redistributed and every visible cell
			//   drifts out of alignment.
			helper( event, row ) {
				list.closest( 'table' )
					.find( 'thead th:visible, thead td:visible' )
					.each( function () {
						$( this ).width( $( this ).width() );
					} );
				row.children( ':visible' ).each( function () {
					$( this ).width( $( this ).width() );
				} );
				return row;
			},
			start( event, ui ) {
				ui.placeholder.height( ui.item.height() );
				snapshot = list.children( 'tr' ).toArray();
				if ( hierarchical ) {
					dragModel = buildModel();
					const item = dragModel.byId[ idOf( ui.item[ 0 ] ) ];
					dragBlock = item
						? { item, subtree: subtreeOf( item, dragModel ) }
						: null;
					if ( dragBlock ) {
						// The subtree travels with its root; dim it in place until
						// the drop reunites them.
						dragBlock.subtree.forEach( function ( it ) {
							$( it.tr ).addClass(
								'blocklane-pro-reorder-subtree'
							);
						} );
					}
				}
			},
			stop( event, ui ) {
				ui.item.children().css( 'width', '' );
				list.closest( 'table' )
					.find( 'thead th, thead td' )
					.css( 'width', '' );
				list.children( '.blocklane-pro-reorder-subtree' ).removeClass(
					'blocklane-pro-reorder-subtree'
				);
			},
			update( event, ui ) {
				if ( hierarchical ) {
					updateTree();
				} else {
					updateFlat( ui.item );
				}
			},
		} );

		// The sort view shows every row, so the per-page Screen Option can't
		// apply here — lock it and explain why, instead of leaving a control
		// that looks broken. Readonly, NOT disabled: a disabled input drops
		// out of the form and core's set_screen_options() warns on the
		// missing value, breaking the redirect's headers.
		const perPage = $(
			'#screen-options-wrap input.screen-per-page'
		).first();
		if ( perPage.length ) {
			// Show (and, on Apply, re-save) the user's real preference — the
			// rendered value is the lifted row count, which would otherwise
			// clobber their setting.
			if ( data.perPage ) {
				perPage.val( data.perPage );
			}
			perPage
				.prop( 'readOnly', true )
				.addClass( 'blocklane-pro-reorder-perpage-locked' );
			const tip = $(
				'<span class="blocklane-pro-reorder-tip" tabindex="0" aria-describedby="blocklane-pro-perpage-tip"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><span class="blocklane-pro-reorder-tip-bubble" role="tooltip" id="blocklane-pro-perpage-tip"></span></span>'
			);
			tip.find( '.blocklane-pro-reorder-tip-bubble' ).text(
				i18n.perPageInfo ||
					'The Sort by Order view shows every item so the full order can be arranged at once. This setting applies to the other views.'
			);
			perPage.after( tip );
		}

		if ( hierarchical ) {
			// Tree mode: group striping replaces core's per-row striping —
			// dropping the striped class disables core's rule outright, so
			// the plugin's group rule never has to out-rank it.
			list.closest( 'table' )
				.addClass( 'blocklane-pro-reorder-tree' )
				.removeClass( 'striped' );
			list.on( 'click', '.blocklane-pro-indent button', onIndent );
			list.on( 'click', '.blocklane-pro-outdent button', onOutdent );
			list.on( 'click', '.blocklane-pro-move-up button', function () {
				moveBy( this, false );
			} );
			list.on( 'click', '.blocklane-pro-move-down button', function () {
				moveBy( this, true );
			} );
			list.on(
				'click',
				'.blocklane-pro-reorder-toggle',
				function ( event ) {
					const id = idOf( $( this ).closest( 'tr' )[ 0 ] );
					if ( ! id ) {
						return;
					}
					if ( collapsedIds[ id ] ) {
						delete collapsedIds[ id ];
					} else {
						collapsedIds[ id ] = true;
					}
					refreshTree();
					// A mouse click would otherwise leave the button focused, which
					// keeps the row's actions pinned visible (core shows them for
					// :focus-within) until the user clicks elsewhere. Keyboard
					// activation (event.detail 0) keeps focus for a11y.
					if ( event.detail ) {
						this.blur();
					}
				}
			);
			$( document ).on(
				'click',
				'.blocklane-pro-reorder-bulk button',
				function () {
					const model = buildModel();
					const anyExpanded = model.items.some( function ( it ) {
						return it.childCount && ! collapsedIds[ it.id ];
					} );
					collapsedIds = {};
					if ( anyExpanded ) {
						model.items.forEach( function ( it ) {
							if ( it.childCount ) {
								collapsedIds[ it.id ] = true;
							}
						} );
					}
					refreshTree();
				}
			);
			refreshTree();

			// Quick Edit replaces a row's markup (and can change its parent) —
			// re-derive the injected actions from the fresh DOM.
			$( document ).ajaxSuccess( function ( event, xhr, settings ) {
				if (
					settings &&
					settings.data &&
					String( settings.data ).indexOf( 'action=inline-save' ) !==
						-1
				) {
					refreshTree();
				}
			} );
		} else {
			// Flat types get no tree, but the drag view is still mouse-only
			// without keyboard Move Up / Move Down actions (WCAG 2.1.1). Render
			// them now and wire the same handlers; updateFlat re-renders after a
			// successful drag so first/last visibility stays correct.
			renderMoveActions( buildModel() );
			list.on( 'click', '.blocklane-pro-move-up button', function () {
				moveBy( this, false );
			} );
			list.on( 'click', '.blocklane-pro-move-down button', function () {
				moveBy( this, true );
			} );
		}
	} );
} )( jQuery );
