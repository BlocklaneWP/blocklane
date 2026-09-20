/**
 * Form wrapper — edit. Renders the real <form> with the same structure as
 * render.php (required notice included) so the canvas is 1:1 with the front.
 *
 * Fresh inserts start from a useful default template (name / email / message /
 * submit / notifications). Phase 5 puts the scoped pattern picker in front of
 * this (Query Loop precedent) once the starter patterns exist.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import {
	cloneBlock,
	createBlocksFromInnerBlocksTemplate,
} from '@wordpress/blocks';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	BlockPreview,
	store as blockEditorStore,
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	Button,
	Notice,
	Placeholder,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
	ToggleControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
	__experimentalUnitControl as UnitControl,
	__experimentalUseCustomUnits as useCustomUnits,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import useFormFieldNames from '../shared/use-form-field-names';
import useResolvedFieldNames from '../shared/use-resolved-field-names';
import { envelope } from '@wordpress/icons';
import useToolsPanelDropdownMenuProps from '../../../shared/use-tools-panel-dropdown-menu-props';
import { inputStyleVars } from '../shared/input-styles';

const DEFAULT_TEMPLATE = [
	[
		'blocklane/form-input',
		{
			type: 'text',
			label: __( 'Name', 'blocklane' ),
			required: true,
			autocomplete: 'name',
		},
	],
	[
		'blocklane/form-input',
		{
			type: 'email',
			label: __( 'Email', 'blocklane' ),
			required: true,
			isReplyTo: true,
			autocomplete: 'email',
		},
	],
	[
		'blocklane/form-textarea',
		{ label: __( 'Message', 'blocklane' ), required: true },
	],
	[ 'blocklane/form-submit-button', {} ],
	[ 'blocklane/form-notification', { type: 'success' } ],
	[ 'blocklane/form-notification', { type: 'error' } ],
];

// Mirrors blocklane_pro_forms_has_required() in inc/forms/runtime.php — the
// required-fields notice must render identically in the canvas and the front.
// Keep the two block-name lists in sync.
const REQUIRED_CAPABLE = [
	'blocklane/form-input',
	'blocklane/form-textarea',
	'blocklane/form-select',
	'blocklane/form-group',
	'blocklane/form-file',
];

/* Tri-state submission toggle (v2): "Default" inherits the site-wide form
   default from the Forms screen (undefined attribute — the block.json
   defaults were removed for this); On/Off are explicit per-form overrides
   stored as real booleans, so pre-v2 content that carries one keeps its
   behavior. In the Default state the help line surfaces the resolved value —
   an inherited setting is never a surprise. */
function TriStateControl( { label, help, value, siteDefault, onChange } ) {
	const hint =
		value === undefined
			? ' ' +
				sprintf(
					/* translators: %s: the resolved site default: On, Off, or the default action. */
					__( 'Site default: %s.', 'blocklane' ),
					siteDefault
						? __( 'On', 'blocklane' )
						: __( 'Off', 'blocklane' )
				)
			: '';
	let segment = 'off';
	if ( value === undefined ) {
		segment = 'default';
	} else if ( value ) {
		segment = 'on';
	}
	return (
		<ToggleGroupControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ label }
			help={ help + hint }
			value={ segment }
			isBlock
			onChange={ ( next ) =>
				onChange( 'default' === next ? undefined : 'on' === next )
			}
		>
			<ToggleGroupControlOption
				value="default"
				label={ __( 'Default', 'blocklane' ) }
			/>
			<ToggleGroupControlOption
				value="on"
				label={ __( 'On', 'blocklane' ) }
			/>
			<ToggleGroupControlOption
				value="off"
				label={ __( 'Off', 'blocklane' ) }
			/>
		</ToggleGroupControl>
	);
}

const generateFormId = () =>
	'f' +
	( window.crypto?.randomUUID?.() || Math.random().toString( 36 ).slice( 2 ) )
		.replace( /-/g, '' )
		.slice( 0, 8 );

const hasRequiredField = ( blocks ) =>
	blocks.some(
		( block ) =>
			( REQUIRED_CAPABLE.includes( block.name ) &&
				block.attributes?.required ) ||
			hasRequiredField( block.innerBlocks || [] )
	);

export default function FormEdit( { attributes, setAttributes, clientId } ) {
	const {
		formId,
		formName,
		recipients,
		subject,
		successAction,
		redirectUrl,
		replaceOnSuccess,
		storeSubmissions,
		notifyAdmin,
		autoResponder,
		inputStyles,
		scheduleEnabled,
		scheduleStart,
		scheduleEnd,
		requireLogin,
		closedMessage,
		uniqueField,
		uniqueMessage,
	} = attributes;

	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	/* The resolved site-wide form defaults (Forms screen → Settings → Form
	   defaults), bridged like .turnstile/.maxUpload. Drives the inherit
	   placeholders and the tri-state "Default" hints; recipients arrives
	   pre-resolved to the admin email when the site default is empty. */
	const siteDefaults = window.blocklaneProForms?.defaults || {};
	const effectiveAction =
		successAction ?? siteDefaults.successAction ?? 'message';
	const effectiveAutoEnabled =
		autoResponder?.enabled ?? siteDefaults.autoResponder?.enabled ?? false;

	/* Auto-responder writes keep the attribute minimal: empty strings and
	   undefined mean "inherit", so they leave the object rather than being
	   stored, and an all-inherit object drops the attribute entirely. */
	const setAutoResponder = ( patch ) => {
		const next = { ...( autoResponder || {} ), ...patch };
		Object.keys( next ).forEach( ( key ) => {
			if ( undefined === next[ key ] || '' === next[ key ] ) {
				delete next[ key ];
			}
		} );
		setAttributes( {
			autoResponder: Object.keys( next ).length ? next : undefined,
		} );
	};

	/* formId: set once on insert; regenerate on the LATER copy when a
	   same-document duplicate carries the same id (cross-document duplicates
	   are harmless — schema derivation is source-scoped by design). */
	const duplicateOfEarlier = useSelect(
		( select ) => {
			if ( ! formId ) {
				return false;
			}
			const { getBlocksByName, getBlockAttributes } =
				select( blockEditorStore );
			if ( typeof getBlocksByName !== 'function' ) {
				return false;
			}
			const formIds = getBlocksByName( 'blocklane/form' );
			const mine = formIds.indexOf( clientId );
			return formIds.some(
				( id, index ) =>
					index < mine && getBlockAttributes( id )?.formId === formId
			);
		},
		[ formId, clientId ]
	);

	useEffect( () => {
		if ( ! formId || duplicateOfEarlier ) {
			setAttributes( { formId: generateFormId() } );
		}
	}, [ formId, duplicateOfEarlier, setAttributes ] );

	const { hasInnerBlocks, showRequiredNotice, hasSteps } = useSelect(
		( select ) => {
			const inner =
				select( blockEditorStore ).getBlock( clientId )?.innerBlocks ||
				[];
			return {
				hasInnerBlocks: inner.length > 0,
				showRequiredNotice: hasRequiredField( inner ),
				hasSteps:
					inner.filter(
						( child ) => 'blocklane/form-step' === child.name
					).length > 1,
			};
		},
		[ clientId ]
	);

	// The unique-entry picker's options: this form's own field names through
	// the shared resolver (also feeding every field's Visibility picker).
	// uniqueEligible drops the field types a visitor cannot vary — a hidden
	// input holds the same authored constant for everyone, so "one entry per"
	// that field takes one submission ever and then rejects the form forever.
	const fieldNameOptions = useFormFieldNames( clientId, {
		uniqueEligible: true,
	} );

	// Which field names carry a Visibility rule. A conditional field can be
	// resolved HIDDEN, and a hidden field is excluded before validation — so
	// the uniqueness gate (which only fires on a collected value) never runs.
	// A submitter who controls the referenced field can therefore waive
	// "one entry per…" at will. The author cannot see that from the picker,
	// so say it there.
	const conditionalRows = useSelect(
		( select ) => {
			const sel = select( blockEditorStore );
			const rows = [];
			const walk = ( blocks ) => {
				( blocks || [] ).forEach( ( inner ) => {
					if ( inner.attributes?.condition?.field ) {
						const attrs = inner.attributes || {};
						const isGroup =
							'blocklane/form-group' === ( inner.name || '' );
						rows.push( {
							name: attrs.name || '',
							label: isGroup
								? attrs.legend || ''
								: attrs.label || '',
							fallback: isGroup ? 'choices' : 'field',
						} );
					}
					walk( inner.innerBlocks );
				} );
			};
			walk( sel.getBlock( clientId )?.innerBlocks );

			return rows;
		},
		[ clientId ]
	);
	const conditionalFieldNames =
		useResolvedFieldNames( conditionalRows ).filter( Boolean );

	/* Duplicate-name auto-heal (v3, the formId-regeneration precedent at
	   field level). Duplicating a step copies its fields' labels, and the
	   label-derived names then COLLIDE: one server schema entry (last-wins
	   swallows a value on submit), duplicate element ids (both labels bind
	   to the first input — typing lands in the wrong, even hidden, field;
	   Garrett's "steps look synced/mirrored" report), and autofill treats
	   them as one field. Names must stay a pure function of attributes
	   (NEVER render-time dedup — the render↔schema invariant), so the heal
	   is CONTENT-level: the later duplicate gets an explicit unique `name`
	   attribute, visible in its Field name item. */
	// Two halves on purpose: the store walk is synchronous, name resolution is
	// a round trip to the one namer. Splitting them is what removes the JS
	// twin of blocklane_pro_forms_field_name() — the heal now compares the
	// same strings the server will.
	const healInput = useSelect(
		( select ) => {
			const sel = select( blockEditorStore );
			const rows = [];
			// Every block that POINTS AT a field name, with the step it sits
			// in. Duplicating a step copies its dependents alongside its
			// fields, so a rewritten name has to drag its own copies with it.
			const refs = [];
			const walk = ( blocks, step = '' ) => {
				( blocks || [] ).forEach( ( inner ) => {
					const block = inner.name || '';
					const attrs = inner.attributes || {};
					const inStep =
						'blocklane/form-step' === block ? inner.clientId : step;
					if ( attrs.condition?.field ) {
						refs.push( {
							clientId: inner.clientId,
							step: inStep,
							field: attrs.condition.field,
							condition: attrs.condition,
						} );
					}
					if (
						[
							'blocklane/form-input',
							'blocklane/form-textarea',
							'blocklane/form-select',
							'blocklane/form-file',
						].includes( block )
					) {
						rows.push( {
							clientId: inner.clientId,
							step: inStep,
							name: attrs.name || '',
							label: attrs.label || '',
							fallback: 'field',
						} );
					} else if ( 'blocklane/form-group' === block ) {
						rows.push( {
							clientId: inner.clientId,
							step: inStep,
							name: attrs.name || '',
							label: attrs.legend || '',
							fallback: 'choices',
						} );
					}
					walk( inner.innerBlocks, inStep );
				} );
			};
			walk( sel.getBlock( clientId )?.innerBlocks );

			return { rows, refs };
		},
		[ clientId ]
	);

	const healNames = useResolvedFieldNames(
		healInput.rows.map( ( r ) => ( {
			name: r.name,
			label: r.label,
			fallback: r.fallback,
		} ) )
	);

	const fieldNameCollisions = useMemo( () => {
		const { rows, refs } = healInput;
		// Nothing to compare until the server has answered for every row.
		if ( healNames.length !== rows.length ) {
			return [];
		}
		const resolvedRows = rows.map( ( r, i ) => ( {
			...r,
			resolved: healNames[ i ],
		} ) );

		const seen = new Set();
		const all = new Set( resolvedRows.map( ( r ) => r.resolved ) );
		const fixes = [];
		resolvedRows.forEach( ( row ) => {
			if ( ! row.resolved ) {
				return;
			}
			if ( ! seen.has( row.resolved ) ) {
				seen.add( row.resolved );
				return;
			}
			// Later duplicate: pick the first free -N variant.
			let suffix = 2;
			while (
				all.has( `${ row.resolved }-${ suffix }` ) ||
				seen.has( `${ row.resolved }-${ suffix }` )
			) {
				suffix++;
			}
			const unique = `${ row.resolved }-${ suffix }`;
			seen.add( unique );
			fixes.push( { clientId: row.clientId, name: unique } );

			// Follow the rename with the rules that were duplicated
			// ALONGSIDE this field. Two conditions, and the second is the
			// one that keeps this honest:
			//
			// 1. Same step — duplicating a step is the case this exists for.
			// 2. That step contains NO OTHER field still resolving to the
			//    old name. If one does, a rule naming it is unambiguous —
			//    it means that field — and rewriting would repoint a rule
			//    the author wrote by hand. Only when the old name has left
			//    the step entirely must the rule mean the renamed copy.
			//
			// Rules outside the step are never touched: the original keeps
			// its name, so they still mean what their author meant.
			const oldNameStillInStep = resolvedRows.some(
				( other ) =>
					other.clientId !== row.clientId &&
					other.step === row.step &&
					other.resolved === row.resolved
			);
			refs.forEach( ( r ) => {
				if (
					! oldNameStillInStep &&
					r.field === row.resolved &&
					r.step &&
					r.step === row.step &&
					r.clientId !== row.clientId
				) {
					fixes.push( {
						clientId: r.clientId,
						condition: { ...r.condition, field: unique },
					} );
				}
			} );
		} );

		return fixes;
	}, [ healInput, healNames ] );

	const { updateBlockAttributes } = useDispatch( blockEditorStore );
	useEffect( () => {
		fieldNameCollisions.forEach( ( fix ) =>
			updateBlockAttributes(
				fix.clientId,
				fix.condition
					? { condition: fix.condition }
					: { name: fix.name }
			)
		);
	}, [ fieldNameCollisions, updateBlockAttributes ] );

	/* Stepped forms: the submit button's canonical slot is a DIRECT child of
	   the form, immediately after the last step — where the runtime's
	   closing-step nav row picks it up. The editor ENFORCES that (core's
	   Block Locking API; WooCommerce Checkout is the structural-children
	   precedent): a submit found inside a step or before the last step is
	   moved to the slot, and it carries lock:{move} so the List View shows
	   the padlock instead of silently snapping back. Flat forms keep free
	   placement (a submit beside a note in columns is legitimate) and the
	   lock is lifted. */
	const submitEnforcement = useSelect(
		( select ) => {
			const sel = select( blockEditorStore );
			const inner = sel.getBlock( clientId )?.innerBlocks || [];
			const findSubmits = ( blocks, out = [] ) => {
				for ( const b of blocks ) {
					if ( 'blocklane/form-submit-button' === b.name ) {
						out.push( b );
					}
					findSubmits( b.innerBlocks || [], out );
				}
				return out;
			};
			const submits = findSubmits( inner );
			if ( ! submits.length ) {
				return { moves: [], locks: [], unlocks: [] };
			}
			const steps = inner.filter(
				( b ) => 'blocklane/form-step' === b.name
			);
			const stepped = steps.length > 1;
			const lastStepIx = stepped
				? inner.indexOf( steps[ steps.length - 1 ] )
				: -1;
			const moves = [];
			const locks = [];
			const unlocks = [];
			submits.forEach( ( submit ) => {
				const direct = inner.some(
					( b ) => b.clientId === submit.clientId
				);
				const locked = !! submit.attributes?.lock?.move;
				if ( stepped ) {
					const directIx = direct
						? inner.findIndex(
								( b ) => b.clientId === submit.clientId
							)
						: -1;
					if ( ! direct || directIx <= lastStepIx ) {
						// A locked stray unlocks first — moveBlocksToPosition
						// is a thunk that consults canMoveBlock at execution
						// time, so a lock (even our own from a previous pass)
						// silently vetoes the move.
						moves.push( {
							clientId: submit.clientId,
							from: sel.getBlockRootClientId( submit.clientId ),
							index: lastStepIx + ( direct ? 0 : 1 ),
							unlockFirst: locked,
						} );
					} else if ( ! locked ) {
						// Lock ONLY once placement is correct — locking in
						// the same pass as the move races the move thunk
						// and the lock wins (live-caught).
						locks.push( submit.clientId );
					}
				} else if ( locked ) {
					unlocks.push( submit.clientId );
				}
			} );
			return { moves, locks, unlocks };
		},
		[ clientId ]
	);
	const { moveBlocksToPosition } = useDispatch( blockEditorStore );
	useEffect( () => {
		submitEnforcement.moves.forEach( ( move ) => {
			if ( move.unlockFirst ) {
				updateBlockAttributes( move.clientId, { lock: undefined } );
			}
			moveBlocksToPosition(
				[ move.clientId ],
				move.from,
				clientId,
				move.index
			);
		} );
		submitEnforcement.locks.forEach( ( id ) =>
			updateBlockAttributes( id, {
				lock: { move: true, remove: false },
			} )
		);
		submitEnforcement.unlocks.forEach( ( id ) =>
			updateBlockAttributes( id, { lock: undefined } )
		);
	}, [
		submitEnforcement,
		moveBlocksToPosition,
		updateBlockAttributes,
		clientId,
	] );

	/* Setup state (Query Loop precedent): an empty form offers the scoped
	   starter patterns + Start blank. Patterns ship WITHOUT a formId, so the
	   generation effect above mints one per insertion.

	   `patternsReady` is the load-bearing part: block patterns can resolve
	   asynchronously (core-data resolver), so a naive "0 patterns → start
	   blank" races the fetch and permanently blanks the form before the
	   picker can appear. We only decide "there are no patterns" once
	   resolution has actually settled. */
	const { patterns, patternsReady } = useSelect(
		( select ) => {
			const sel = select( blockEditorStore );
			const matches =
				typeof sel.__experimentalGetPatternsByBlockTypes === 'function'
					? sel.__experimentalGetPatternsByBlockTypes(
							'blocklane/form',
							sel.getBlockRootClientId( clientId )
						)
					: [];

			// Ready when the editor's full pattern list is populated, or when
			// core-data has finished resolving patterns (newer WP), or when
			// our own patterns are already present.
			const core = select( 'core' );
			let ready = matches.length > 0;
			if ( ! ready && core ) {
				if ( typeof core.getBlockPatterns === 'function' ) {
					core.getBlockPatterns();
					ready = core.hasFinishedResolution(
						'getBlockPatterns',
						[]
					);
				}
			}
			if ( ! ready ) {
				const all = sel.getSettings().__experimentalBlockPatterns || [];
				ready = all.length > 0;
			}

			return { patterns: matches, patternsReady: ready };
		},
		[ clientId ]
	);
	const { replaceInnerBlocks } = useDispatch( blockEditorStore );

	const startBlank = () =>
		replaceInnerBlocks(
			clientId,
			createBlocksFromInnerBlocksTemplate( DEFAULT_TEMPLATE ),
			false
		);

	const choosePattern = ( pattern ) => {
		const blocks = ( pattern.blocks || [] ).map( ( block ) =>
			cloneBlock( block )
		);
		const formBlock = blocks.find(
			( block ) => 'blocklane/form' === block.name
		);
		if ( formBlock ) {
			// Adopt the pattern's form attributes into THIS block (keeping
			// our freshly minted formId) and take its fields as ours.
			const { formId: patternFormId, ...patternAttrs } =
				formBlock.attributes || {};
			setAttributes( patternAttrs );
			replaceInnerBlocks( clientId, formBlock.innerBlocks, false );
		} else {
			replaceInnerBlocks( clientId, blocks, false );
		}
	};

	/* No patterns registered (filtered away, or a stripped install): behave
	   like a template block and start blank — but only once pattern
	   resolution has SETTLED, so we never blank the form out from under a
	   still-loading picker. */
	useEffect( () => {
		if ( ! hasInnerBlocks && patternsReady && 0 === patterns.length ) {
			replaceInnerBlocks(
				clientId,
				createBlocksFromInnerBlocksTemplate( DEFAULT_TEMPLATE ),
				false
			);
		}
	}, [
		hasInnerBlocks,
		patternsReady,
		patterns.length,
		clientId,
		replaceInnerBlocks,
	] );

	// Mirrors the front: when Turnstile is active the form's LAST child is
	// the challenge widget, so the canvas shows its footprint (1:1) — sized,
	// themed, and ghosted like the configured widget; with "Only when needed"
	// visibility the front usually shows NO widget, so the placeholder says
	// so instead of promising a box. Runtime sets the global via an inline
	// script on this block's editor script — the post editor drops custom
	// block_editor_settings_all keys.
	const turnstile = window.blocklaneProForms?.turnstile;
	const turnstileActive = !! turnstile?.active;
	const turnstileSize = turnstile?.size || 'normal';
	const turnstileTheme = turnstile?.theme || 'auto';
	const turnstileGhost = 'interaction-only' === turnstile?.appearance;

	const blockProps = useBlockProps( {
		className: 'blocklane-form' + ( formId ? ` blf-${ formId }` : '' ),
		style: inputStyleVars( inputStyles ),
	} );
	const { children, ...innerBlocksProps } = useInnerBlocksProps( blockProps, {
		// The quick inserter leads with the blocks that belong in a form
		// (Navigation's prioritizedInserterBlocks precedent) — everything
		// else stays reachable by search; deliberately NOT allowedBlocks
		// (headings, columns, media inside forms are legitimate).
		prioritizedInserterBlocks: [
			'blocklane/form-input',
			'blocklane/form-textarea',
			'blocklane/form-select',
			'blocklane/form-group',
			'blocklane/form-file',
			'blocklane/form-step',
			'blocklane/form-submit-button',
			'blocklane/form-notification',
		],
	} );

	const setInputStyle = ( key ) => ( value ) =>
		setAttributes( {
			inputStyles: { ...inputStyles, [ key ]: value },
		} );

	const inputColorSettings = [
		{
			key: 'background',
			label: __( 'Input background', 'blocklane' ),
		},
		{ key: 'text', label: __( 'Input text', 'blocklane' ) },
		{ key: 'border', label: __( 'Input border', 'blocklane' ) },
		{
			key: 'focusBorder',
			label: __( 'Input focus border', 'blocklane' ),
		},
		{ key: 'asterisk', label: __( 'Asterisk', 'blocklane' ) },
	];

	/* Reset-all filters for the shared Styles-tab panels (the Color and
	   Border panels are slot-fills merged with core's own items; their
	   Reset All runs every item's filter over the block's attributes). All
	   four color items share ONE filter that clears every color key at once
	   — per-key filters would clobber each other, since each spreads the
	   same stale inputStyles. */
	const INPUT_COLOR_KEYS = [
		'background',
		'text',
		'border',
		'focusBorder',
		'asterisk',
	];
	const clearInputColors = ( attrs = {} ) => ( {
		...attrs,
		inputStyles: Object.fromEntries(
			Object.entries( inputStyles || {} ).filter(
				( [ key ] ) => ! INPUT_COLOR_KEYS.includes( key )
			)
		),
	} );
	const clearInputRadius = ( attrs = {} ) => ( {
		...attrs,
		inputStyles: { ...inputStyles, radius: undefined },
	} );

	const radiusUnits = useCustomUnits( {
		availableUnits: [ 'px', 'em', 'rem' ],
	} );

	// The body: a loading placeholder while patterns resolve, the setup
	// picker when starters exist, otherwise the live form.
	let body;
	if ( ! hasInnerBlocks && ! patternsReady ) {
		body = (
			<div { ...blockProps }>
				<Placeholder icon={ envelope }>
					<Spinner />
				</Placeholder>
			</div>
		);
	} else if ( ! hasInnerBlocks && patterns.length > 0 ) {
		body = (
			<div { ...blockProps }>
				<Placeholder
					icon={ envelope }
					label={ __( 'Form', 'blocklane' ) }
					instructions={ __(
						'Start from a ready-made form, or build one from blank.',
						'blocklane'
					) }
					className="blocklane-form__setup"
				>
					<div className="blocklane-form__setup-grid">
						{ patterns.map( ( pattern ) => (
							<button
								key={ pattern.name }
								type="button"
								className="blocklane-form__setup-choice"
								onClick={ () => choosePattern( pattern ) }
							>
								<BlockPreview
									blocks={ pattern.blocks }
									viewportWidth={ 500 }
								/>
								<span className="blocklane-form__setup-choice-title">
									{ pattern.title }
								</span>
							</button>
						) ) }
					</div>
					<Button variant="secondary" onClick={ startBlank }>
						{ __( 'Start blank', 'blocklane' ) }
					</Button>
				</Placeholder>
			</div>
		);
	} else {
		body = (
			<form
				{ ...innerBlocksProps }
				onSubmit={ ( event ) => event.preventDefault() }
			>
				{ showRequiredNotice &&
					false !== attributes.showRequiredNotice && (
						<p className="blocklane-form__required-notice">
							{ __(
								'Required fields are marked with an asterisk (*).',
								'blocklane'
							) }
						</p>
					) }
				{ children }
				{ turnstileActive && (
					<div
						className={
							`blocklane-form__turnstile blocklane-form__turnstile-placeholder is-size-${ turnstileSize } is-theme-${ turnstileTheme }` +
							( turnstileGhost ? ' is-interaction-only' : '' )
						}
					>
						{ ( turnstileGhost
							? __(
									'Cloudflare Turnstile (only shown when needed)',
									'blocklane'
								)
							: __( 'Cloudflare Turnstile', 'blocklane' ) ) +
							// Test mode is a site state worth seeing in the
							// canvas too — the front widget carries
							// Cloudflare's own testing notice, so the
							// placeholder mirrors it (1:1).
							( turnstile?.testMode
								? ' — ' + __( 'test mode', 'blocklane' )
								: '' ) }
					</div>
				) }
			</form>
		);
	}

	return (
		<>
			<InspectorControls>
				<ToolsPanel
					label={ __( 'Submission', 'blocklane' ) }
					resetAll={ () =>
						setAttributes( {
							formName: '',
							recipients: '',
							subject: '',
							successAction: undefined,
							redirectUrl: '',
							replaceOnSuccess: undefined,
							storeSubmissions: undefined,
							notifyAdmin: undefined,
							autoResponder: undefined,
						} )
					}
					dropdownMenuProps={ dropdownMenuProps }
				>
					<ToolsPanelItem
						hasValue={ () => '' !== formName }
						label={ __( 'Form name', 'blocklane' ) }
						onDeselect={ () => setAttributes( { formName: '' } ) }
						isShownByDefault
					>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Form name', 'blocklane' ) }
							help={ __(
								'Identifies this form in email subjects and the submissions inbox.',
								'blocklane'
							) }
							value={ formName }
							onChange={ ( value ) =>
								setAttributes( { formName: value } )
							}
						/>
					</ToolsPanelItem>
					<ToolsPanelItem
						hasValue={ () => '' !== recipients }
						label={ __( 'Recipients', 'blocklane' ) }
						onDeselect={ () => setAttributes( { recipients: '' } ) }
					>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Recipients', 'blocklane' ) }
							help={
								// The bridge blanks the resolved addresses
								// for non-admin roles — don't promise a
								// placeholder that isn't there.
								siteDefaults.recipients
									? __(
											'Comma-separated email addresses. Empty uses the site default shown.',
											'blocklane'
										)
									: __(
											'Comma-separated email addresses. Empty uses the site default.',
											'blocklane'
										)
							}
							placeholder={ siteDefaults.recipients || undefined }
							value={ recipients }
							onChange={ ( value ) =>
								setAttributes( { recipients: value } )
							}
						/>
					</ToolsPanelItem>
					<ToolsPanelItem
						hasValue={ () => '' !== subject }
						label={ __( 'Email subject', 'blocklane' ) }
						onDeselect={ () => setAttributes( { subject: '' } ) }
					>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Email subject', 'blocklane' ) }
							help={
								siteDefaults.subject
									? __(
											'Empty uses the site default shown.',
											'blocklane'
										)
									: __(
											'Empty uses “New submission” with the form name and site title.',
											'blocklane'
										)
							}
							placeholder={ siteDefaults.subject || undefined }
							value={ subject }
							onChange={ ( value ) =>
								setAttributes( { subject: value } )
							}
						/>
					</ToolsPanelItem>
					<ToolsPanelItem
						hasValue={ () =>
							undefined !== successAction ||
							'' !== redirectUrl ||
							undefined !== replaceOnSuccess
						}
						label={ __( 'After submission', 'blocklane' ) }
						onDeselect={ () =>
							setAttributes( {
								successAction: undefined,
								redirectUrl: '',
								replaceOnSuccess: undefined,
							} )
						}
					>
						<VStack spacing={ 3 }>
							<ToggleGroupControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'After submission', 'blocklane' ) }
								help={
									undefined === successAction
										? sprintf(
												/* translators: %s: the resolved site default: On, Off, or the default action. */
												__(
													'Site default: %s.',
													'blocklane'
												),
												'redirect' === effectiveAction
													? __(
															'Redirect',
															'blocklane'
														)
													: __(
															'Show message',
															'blocklane'
														)
											)
										: undefined
								}
								value={ successAction ?? 'default' }
								isBlock
								onChange={ ( value ) =>
									setAttributes( {
										successAction:
											'default' === value
												? undefined
												: value,
									} )
								}
							>
								<ToggleGroupControlOption
									value="default"
									label={ __( 'Default', 'blocklane' ) }
								/>
								<ToggleGroupControlOption
									value="message"
									label={ __( 'Message', 'blocklane' ) }
								/>
								<ToggleGroupControlOption
									value="redirect"
									label={ __( 'Redirect', 'blocklane' ) }
								/>
							</ToggleGroupControl>
							{ 'redirect' === effectiveAction && (
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __( 'Redirect URL', 'blocklane' ) }
									help={
										'' === redirectUrl &&
										siteDefaults.redirectUrl
											? __(
													'Empty uses the site default shown.',
													'blocklane'
												)
											: undefined
									}
									type="url"
									placeholder={
										siteDefaults.redirectUrl || undefined
									}
									value={ redirectUrl }
									onChange={ ( value ) =>
										setAttributes( {
											redirectUrl: value,
										} )
									}
								/>
							) }
							{ 'message' === effectiveAction && (
								<TriStateControl
									label={ __(
										'Replace form with message',
										'blocklane'
									) }
									help={ __(
										'After a successful submission, hide the form and leave only the success message.',
										'blocklane'
									) }
									value={ replaceOnSuccess }
									siteDefault={
										siteDefaults.replaceOnSuccess ?? false
									}
									onChange={ ( value ) =>
										setAttributes( {
											replaceOnSuccess: value,
										} )
									}
								/>
							) }
						</VStack>
					</ToolsPanelItem>
					<ToolsPanelItem
						hasValue={ () => undefined !== storeSubmissions }
						label={ __( 'Store submissions', 'blocklane' ) }
						onDeselect={ () =>
							setAttributes( { storeSubmissions: undefined } )
						}
					>
						<TriStateControl
							label={ __( 'Store submissions', 'blocklane' ) }
							help={ __(
								'Keep a copy in the submissions inbox. The inbox is the safety net when email fails.',
								'blocklane'
							) }
							value={ storeSubmissions }
							siteDefault={
								siteDefaults.storeSubmissions ?? true
							}
							onChange={ ( value ) =>
								setAttributes( { storeSubmissions: value } )
							}
						/>
					</ToolsPanelItem>
					<ToolsPanelItem
						hasValue={ () => undefined !== notifyAdmin }
						label={ __( 'Email notification', 'blocklane' ) }
						onDeselect={ () =>
							setAttributes( { notifyAdmin: undefined } )
						}
					>
						<TriStateControl
							label={ __( 'Email notification', 'blocklane' ) }
							help={ __(
								'Email each submission to the recipients.',
								'blocklane'
							) }
							value={ notifyAdmin }
							siteDefault={ siteDefaults.notifyAdmin ?? true }
							onChange={ ( value ) =>
								setAttributes( { notifyAdmin: value } )
							}
						/>
					</ToolsPanelItem>
					<ToolsPanelItem
						hasValue={ () => undefined !== autoResponder }
						label={ __( 'Auto-responder', 'blocklane' ) }
						onDeselect={ () =>
							setAttributes( { autoResponder: undefined } )
						}
					>
						<VStack spacing={ 3 }>
							<TriStateControl
								label={ __( 'Auto-responder', 'blocklane' ) }
								help={ __(
									'Send a confirmation email to the submitter, using the field marked as reply-to.',
									'blocklane'
								) }
								value={ autoResponder?.enabled }
								siteDefault={
									siteDefaults.autoResponder?.enabled ?? false
								}
								onChange={ ( value ) =>
									setAutoResponder( { enabled: value } )
								}
							/>
							{ effectiveAutoEnabled && (
								<>
									<TextControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __(
											'Auto-responder subject',
											'blocklane'
										) }
										placeholder={
											siteDefaults.autoResponder
												?.subject || undefined
										}
										value={ autoResponder?.subject || '' }
										onChange={ ( value ) =>
											setAutoResponder( {
												subject: value,
											} )
										}
									/>
									<TextareaControl
										__nextHasNoMarginBottom
										label={ __(
											'Auto-responder message',
											'blocklane'
										) }
										help={ __(
											'A summary of the submitted values is appended.',
											'blocklane'
										) }
										placeholder={
											siteDefaults.autoResponder
												?.message || undefined
										}
										value={ autoResponder?.message || '' }
										onChange={ ( value ) =>
											setAutoResponder( {
												message: value,
											} )
										}
									/>
								</>
							) }
						</VStack>
					</ToolsPanelItem>
				</ToolsPanel>
				<ToolsPanel
					label={ __( 'Notices', 'blocklane' ) }
					resetAll={ () =>
						setAttributes( { showRequiredNotice: true } )
					}
					dropdownMenuProps={ dropdownMenuProps }
				>
					<ToolsPanelItem
						hasValue={ () =>
							false === attributes.showRequiredNotice
						}
						label={ __( 'Required-fields note', 'blocklane' ) }
						onDeselect={ () =>
							setAttributes( { showRequiredNotice: true } )
						}
						isShownByDefault={ false }
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Required-fields note', 'blocklane' ) }
							help={ __(
								'The “Required fields are marked with an asterisk (*)” line above the fields.',
								'blocklane'
							) }
							checked={ false !== attributes.showRequiredNotice }
							onChange={ ( value ) =>
								setAttributes( {
									showRequiredNotice: value,
								} )
							}
						/>
					</ToolsPanelItem>
				</ToolsPanel>
				{ hasSteps && (
					<ToolsPanel
						label={ __( 'Steps', 'blocklane' ) }
						resetAll={ () =>
							setAttributes( { showProgress: true } )
						}
						dropdownMenuProps={ dropdownMenuProps }
					>
						<ToolsPanelItem
							hasValue={ () => false === attributes.showProgress }
							label={ __( 'Progress indicator', 'blocklane' ) }
							onDeselect={ () =>
								setAttributes( { showProgress: true } )
							}
							isShownByDefault
						>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'Progress indicator',
									'blocklane'
								) }
								help={ __(
									'Step labels above the form, with the current step highlighted.',
									'blocklane'
								) }
								checked={ false !== attributes.showProgress }
								onChange={ ( value ) =>
									setAttributes( { showProgress: value } )
								}
							/>
						</ToolsPanelItem>
					</ToolsPanel>
				) }
				<ToolsPanel
					label={ __( 'Availability', 'blocklane' ) }
					resetAll={ () =>
						setAttributes( {
							scheduleEnabled: false,
							scheduleStart: '',
							scheduleEnd: '',
							requireLogin: false,
							closedMessage: '',
							uniqueField: '',
							uniqueMessage: '',
						} )
					}
					dropdownMenuProps={ dropdownMenuProps }
				>
					<ToolsPanelItem
						hasValue={ () => !! scheduleEnabled }
						label={ __( 'Schedule', 'blocklane' ) }
						onDeselect={ () =>
							setAttributes( {
								scheduleEnabled: false,
								scheduleStart: '',
								scheduleEnd: '',
							} )
						}
					>
						<VStack spacing={ 3 }>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'Only accept submissions between…',
									'blocklane'
								) }
								help={ __(
									'Outside the window the form shows the closed message instead of its fields. Site timezone. Leave a side empty for no bound.',
									'blocklane'
								) }
								checked={ !! scheduleEnabled }
								onChange={ ( value ) =>
									setAttributes( { scheduleEnabled: value } )
								}
							/>
							{ !! scheduleEnabled && (
								<>
									<TextControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										type="datetime-local"
										label={ __( 'Opens', 'blocklane' ) }
										value={ scheduleStart }
										onChange={ ( value ) =>
											setAttributes( {
												scheduleStart: value,
											} )
										}
									/>
									<TextControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										type="datetime-local"
										label={ __( 'Closes', 'blocklane' ) }
										value={ scheduleEnd }
										onChange={ ( value ) =>
											setAttributes( {
												scheduleEnd: value,
											} )
										}
									/>
								</>
							) }
						</VStack>
					</ToolsPanelItem>
					<ToolsPanelItem
						hasValue={ () => !! requireLogin }
						label={ __( 'Require login', 'blocklane' ) }
						onDeselect={ () =>
							setAttributes( { requireLogin: false } )
						}
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Require login', 'blocklane' ) }
							help={ __(
								'Logged-out visitors see the closed message instead of the form. Enforced again at submission.',
								'blocklane'
							) }
							checked={ !! requireLogin }
							onChange={ ( value ) =>
								setAttributes( { requireLogin: value } )
							}
						/>
					</ToolsPanelItem>
					{ ( !! scheduleEnabled || !! requireLogin ) && (
						<ToolsPanelItem
							hasValue={ () => '' !== closedMessage }
							label={ __( 'Closed message', 'blocklane' ) }
							onDeselect={ () =>
								setAttributes( { closedMessage: '' } )
							}
						>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Closed message', 'blocklane' ) }
								help={ __(
									'Shown in place of the form while it isn’t accepting submissions.',
									'blocklane'
								) }
								placeholder={ __(
									'This form is not currently accepting submissions.',
									'blocklane'
								) }
								value={ closedMessage }
								onChange={ ( value ) =>
									setAttributes( { closedMessage: value } )
								}
							/>
						</ToolsPanelItem>
					) }
					<ToolsPanelItem
						hasValue={ () => '' !== uniqueField }
						label={ __( 'One entry per…', 'blocklane' ) }
						onDeselect={ () =>
							setAttributes( {
								uniqueField: '',
								uniqueMessage: '',
							} )
						}
					>
						<VStack spacing={ 3 }>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'One entry per…', 'blocklane' ) }
								help={
									uniqueField &&
									conditionalFieldNames.includes(
										uniqueField
									)
										? __(
												'This field has a Visibility rule. While it is hidden its value is not collected, so the uniqueness check does not run and a visitor can submit again by changing the field the rule depends on. Pick a field that is always shown.',
												'blocklane'
											)
										: __(
												'Rejects a submission whose value for this field matches an earlier one — one entry per email address, typically.',
												'blocklane'
											)
								}
								value={ uniqueField }
								options={ [
									{
										label: __( 'Off', 'blocklane' ),
										value: '',
									},
									...fieldNameOptions.map( ( name ) => ( {
										label: name,
										value: name,
									} ) ),
								] }
								onChange={ ( value ) =>
									setAttributes( { uniqueField: value } )
								}
							/>
							{ '' !== uniqueField && (
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __(
										'Duplicate message',
										'blocklane'
									) }
									placeholder={ __(
										'You have already submitted this form.',
										'blocklane'
									) }
									value={ uniqueMessage }
									onChange={ ( value ) =>
										setAttributes( {
											uniqueMessage: value,
										} )
									}
								/>
							) }
							{ /* Resolve the EFFECTIVE storage state through the
							     defaults bridge — an inherited Off must warn
							     exactly like an explicit one (the tri-state
							     lesson from the v2 review). */ }
							{ '' !== uniqueField &&
								false ===
									( storeSubmissions ??
										window.blocklaneProForms?.defaults
											?.storeSubmissions ??
										true ) && (
									<Notice
										status="warning"
										isDismissible={ false }
									>
										{ __(
											'Storing submissions is off for this form, so there is nothing to compare against — the unique check cannot run.',
											'blocklane'
										) }
									</Notice>
								) }
						</VStack>
					</ToolsPanelItem>
				</ToolsPanel>
			</InspectorControls>
			{ /* Input styling lives in the Styles tab with core's own
			     panels: the color items join the block's Color panel and the
			     radius joins Border (Navigation's submenu colors precedent).
			     The slot's shared ToolsPanel supplies panel chrome, so no
			     wrapping panel here — items are opt-in via the panel menu. */ }
			<InspectorControls group="color">
				{ colorGradientSettings.hasColorsOrGradients &&
					inputColorSettings.map( ( { key, label } ) => (
						<ColorGradientSettingsDropdown
							key={ key }
							__experimentalIsRenderedInSidebar
							settings={ [
								{
									colorValue: inputStyles?.[ key ],
									label,
									onColorChange: setInputStyle( key ),
									resetAllFilter: clearInputColors,
									isShownByDefault: false,
									enableAlpha: true,
									clearable: true,
								},
							] }
							panelId={ clientId }
							{ ...colorGradientSettings }
						/>
					) ) }
			</InspectorControls>
			<InspectorControls group="border">
				<ToolsPanelItem
					hasValue={ () => !! inputStyles?.radius }
					label={ __( 'Input radius', 'blocklane' ) }
					onDeselect={ () => setInputStyle( 'radius' )( undefined ) }
					resetAllFilter={ clearInputRadius }
					isShownByDefault={ false }
					panelId={ clientId }
				>
					<UnitControl
						__next40pxDefaultSize
						label={ __( 'Input radius', 'blocklane' ) }
						value={ inputStyles?.radius || '' }
						units={ radiusUnits }
						onChange={ setInputStyle( 'radius' ) }
					/>
				</ToolsPanelItem>
			</InspectorControls>
			{ body }
		</>
	);
}
