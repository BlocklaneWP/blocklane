/**
 * The track's settings sidebar, and the helpers the track's canvas shares
 * with it: the device preview, the post types and taxonomies a carousel may
 * query, and the query arguments the preview asks for.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
	FormTokenField,
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
	__experimentalNumberControl as NumberControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useDebouncedInput } from '@wordpress/compose';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useMemo, useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies
 */
import metadata from './block.json';

/** The query attribute's default, as block.json declares it. */
export const QUERY_DEFAULT = metadata.attributes.query.default;

/** The query that lists every post type or taxonomy the REST API knows. */
export const ALL_RECORDS = { per_page: -1 };

/** The query that lists the site's authors. */
const AUTHORS_QUERY = { who: 'authors', per_page: 100, context: 'view' };

/** A shared empty list, so a selector never returns a new one. */
const NONE = [];

/** The props every sized control takes. */
const SIZED = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

/** A numeric string, as PHP's is_numeric() reads one. */
const NUMERIC = /^\s*[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?\s*$/;

/**
 * Whether a value is a plain object (not a list, not null).
 *
 * @param {unknown} value The value.
 * @return {boolean} Whether it is a plain object.
 */
export function isPlainObject( value ) {
	return !! value && 'object' === typeof value && ! Array.isArray( value );
}

/**
 * A value read as a number, or a fallback when it is not one.
 *
 * @param {unknown} value    The value.
 * @param {number}  fallback What to use when the value is not a number.
 * @return {number} The number.
 */
export function numberOr( value, fallback ) {
	if (
		undefined === value ||
		null === value ||
		'' === value ||
		'boolean' === typeof value
	) {
		return fallback;
	}
	const number = Number( value );
	return Number.isFinite( number ) ? number : fallback;
}

/**
 * A value read as a whole number clamped to a range, as the server reads it;
 * the fallback when it is not numeric.
 *
 * @param {unknown} value    The value.
 * @param {number}  min      The lowest result.
 * @param {number}  max      The highest result.
 * @param {number}  fallback The result for a value that is not numeric.
 * @return {number} The whole number.
 */
export function clampInt( value, min, max, fallback ) {
	let number;
	if ( 'number' === typeof value ) {
		number = value;
	} else if ( 'string' === typeof value && NUMERIC.test( value ) ) {
		number = Number( value.trim() );
	} else {
		return fallback;
	}
	if ( Number.isNaN( number ) ) {
		return fallback;
	}
	return Math.trunc( Math.max( min, Math.min( max, number ) ) );
}

/**
 * A list of ids, each once, at most 100: the items of a list, or of a comma-
 * or space-separated string, that are numbers of at least 1 (a fraction is
 * truncated). Anything else is dropped.
 *
 * @param {unknown} value A list, a string or a number.
 * @return {number[]} The ids.
 */
export function idList( value ) {
	let items;
	if ( Array.isArray( value ) ) {
		items = value;
	} else if ( 'string' === typeof value ) {
		items = value.split( /[\s,]+/ );
	} else if ( 'number' === typeof value ) {
		items = [ value ];
	} else {
		return [];
	}
	const ids = [];
	for ( const item of items ) {
		let number;
		if ( 'number' === typeof item ) {
			number = item;
		} else if ( 'string' === typeof item && NUMERIC.test( item ) ) {
			number = Number( item.trim() );
		} else {
			continue;
		}
		if ( ! Number.isFinite( number ) || number < 1 ) {
			continue;
		}
		const id = Math.trunc( number );
		if ( ! ids.includes( id ) ) {
			ids.push( id );
		}
	}
	return ids.slice( 0, 100 );
}

/**
 * The post types the server lets a carousel query, when it says (a list);
 * null when it does not.
 *
 * @return {string[]|null} The post type names, or null.
 */
export function serverQueryPostTypes() {
	const state =
		'undefined' === typeof window ? null : window.blocklaneProCarousel;
	const list =
		state && 'object' === typeof state ? state.queryPostTypes : null;
	return Array.isArray( list ) ? list : null;
}

/**
 * The post types a carousel may query: viewable, not attachment, and
 * allowed by the server when it lists them.
 *
 * @param {Object[]|null} types The post types the REST API lists.
 * @return {Object[]} The queryable post types.
 */
export function queryableTypes( types ) {
	if ( ! Array.isArray( types ) ) {
		return NONE;
	}
	const allowed = serverQueryPostTypes();
	return types.filter(
		( type ) =>
			type &&
			type.viewable &&
			'attachment' !== type.slug &&
			( null === allowed || allowed.includes( type.slug ) )
	);
}

/**
 * The taxonomies that can filter a post type: publicly queryable and
 * attached to it.
 *
 * @param {Object[]|null} taxonomies The taxonomies the REST API lists.
 * @param {string}        postType   The post type.
 * @return {Object[]} The taxonomies.
 */
export function filterTaxonomies( taxonomies, postType ) {
	if ( ! Array.isArray( taxonomies ) ) {
		return NONE;
	}
	return taxonomies.filter(
		( taxonomy ) =>
			taxonomy &&
			taxonomy.visibility?.publicly_queryable &&
			Array.isArray( taxonomy.types ) &&
			taxonomy.types.includes( postType )
	);
}

/**
 * The post type a stored query names ('post' when it names none).
 *
 * @param {unknown} query The stored query.
 * @return {string} The post type.
 */
export function queryPostType( query ) {
	const postType = isPlainObject( query ) ? query.postType : null;
	return 'string' === typeof postType && '' !== postType ? postType : 'post';
}

/**
 * The REST API arguments that preview a stored query, mirroring the
 * server's own query for the page.
 *
 * @param {unknown}  query      The stored query.
 * @param {string}   postType   The post type queried.
 * @param {Object[]} taxonomies The taxonomies the REST API lists.
 * @param {number}   currentId  The post being edited, or 0.
 * @return {Object} The arguments.
 */
export function buildPreviewArgs( query, postType, taxonomies, currentId ) {
	const q = isPlainObject( query ) ? query : {};
	const args = {
		per_page: clampInt( q.perPage, 1, 24, 6 ),
		offset: clampInt( q.offset, 0, 10000, 0 ),
		order:
			'string' === typeof q.order && 'asc' === q.order.toLowerCase()
				? 'asc'
				: 'desc',
		orderby:
			'date' === q.orderBy || 'title' === q.orderBy ? q.orderBy : 'date',
		status: 'publish',
	};
	if ( 'string' === typeof q.search && '' !== q.search && '0' !== q.search ) {
		args.search = Array.from( q.search ).slice( 0, 256 ).join( '' );
	}
	if ( q.author ) {
		const authors = idList( q.author );
		if ( authors.length ) {
			args.author = authors;
		}
	}
	if ( 'post' === postType ) {
		if ( 'only' === q.sticky ) {
			args.sticky = true;
		} else if ( 'exclude' === q.sticky ) {
			args.sticky = false;
		}
	}
	if ( q.excludeCurrent && currentId > 0 ) {
		args.exclude = [ currentId ];
	}
	if ( isPlainObject( q.taxQuery ) ) {
		const usable = filterTaxonomies( taxonomies, postType );
		let count = 0;
		for ( const [ slug, terms ] of Object.entries( q.taxQuery ) ) {
			if ( count >= 10 ) {
				break;
			}
			const ids = idList( terms );
			const taxonomy = usable.find( ( item ) => item.slug === slug );
			const key = taxonomy ? taxonomy.rest_base || taxonomy.slug : null;
			if ( ids.length && key && ! ( key in args ) ) {
				args[ key ] = ids;
				count++;
			}
		}
	}
	return args;
}

/**
 * The device the editor previews ('Desktop' when the post editor's store is
 * not there).
 *
 * @return {string} 'Desktop', 'Tablet' or 'Mobile'.
 */
export function useDeviceType() {
	return useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		const device =
			editor && 'function' === typeof editor.getDeviceType
				? editor.getDeviceType()
				: null;
		return 'Tablet' === device || 'Mobile' === device ? device : 'Desktop';
	}, [] );
}

/**
 * The id of the post being edited, or 0 when there is none.
 *
 * @return {number} The id.
 */
export function useCurrentPostId() {
	return useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		const id =
			editor && 'function' === typeof editor.getCurrentPostId
				? Number( editor.getCurrentPostId() )
				: 0;
		return Number.isInteger( id ) && id > 0 ? id : 0;
	}, [] );
}

/**
 * The post types the REST API lists, and whether the list has arrived.
 *
 * @return {Object} { types, typesResolved }.
 */
export function useQueryTypes() {
	return useSelect( ( select ) => {
		const { getEntityRecords, hasFinishedResolution } = select( coreStore );
		return {
			types: getEntityRecords( 'root', 'postType', ALL_RECORDS ),
			typesResolved: hasFinishedResolution( 'getEntityRecords', [
				'root',
				'postType',
				ALL_RECORDS,
			] ),
		};
	}, [] );
}

/**
 * The taxonomies the REST API lists, and whether the list has arrived.
 *
 * @return {Object} { taxonomies, taxonomiesResolved }.
 */
export function useTaxonomies() {
	return useSelect( ( select ) => {
		const { getEntityRecords, hasFinishedResolution } = select( coreStore );
		return {
			taxonomies: getEntityRecords( 'root', 'taxonomy', ALL_RECORDS ),
			taxonomiesResolved: hasFinishedResolution( 'getEntityRecords', [
				'root',
				'taxonomy',
				ALL_RECORDS,
			] ),
		};
	}, [] );
}

/**
 * A record's name as text.
 *
 * @param {Object} record A term or a user.
 * @return {string} The name.
 */
function recordName( record ) {
	return decodeEntities( String( record?.name ?? '' ) );
}

/**
 * A token field over records with ids and names: the tokens are the chosen
 * ids' names, and only a suggested name becomes an id.
 *
 * @param {Object}                          props          The props.
 * @param {string}                          props.label    The field's label.
 * @param {number[]}                        props.ids      The chosen ids.
 * @param {Object[]|null}                   props.known    Records whose names are known.
 * @param {Object[]|null}                   props.found    Records to suggest.
 * @param {(...args: unknown[]) => unknown} props.onChange Receives the new list of ids.
 * @param {(...args: unknown[]) => unknown} props.onInput  Receives what the author types.
 * @return {Element} The field.
 */
function RecordTokens( { label, ids, known, found, onChange, onInput } ) {
	const [ picked, setPicked ] = useState( {} );
	const names = useMemo( () => {
		const map = new Map();
		for ( const record of [ ...( known ?? NONE ), ...( found ?? NONE ) ] ) {
			if ( record && undefined !== record.id ) {
				map.set( Number( record.id ), recordName( record ) );
			}
		}
		return map;
	}, [ known, found ] );
	const suggestions = useMemo(
		() => ( found ?? NONE ).map( recordName ),
		[ found ]
	);
	const shown = ids.map(
		( id ) => names.get( id ) ?? picked[ id ] ?? String( id )
	);

	/**
	 * Store the chosen tokens' ids; a token that names no record is dropped.
	 *
	 * @param {Array} tokens The tokens, strings or objects.
	 */
	const change = ( tokens ) => {
		const byName = new Map();
		ids.forEach( ( id, index ) => byName.set( shown[ index ], id ) );
		names.forEach( ( name, id ) => {
			if ( ! byName.has( name ) ) {
				byName.set( name, id );
			}
		} );
		const next = [];
		const seen = {};
		for ( const token of tokens ) {
			const text = 'string' === typeof token ? token : token?.value;
			const id = byName.get( text );
			if ( undefined !== id && ! next.includes( id ) ) {
				next.push( id );
				seen[ id ] = text;
			}
		}
		setPicked( ( before ) => ( { ...before, ...seen } ) );
		onChange( next );
	};

	return (
		<FormTokenField
			{ ...SIZED }
			label={ label }
			value={ shown }
			suggestions={ suggestions }
			onChange={ change }
			onInputChange={ onInput }
		/>
	);
}

/**
 * The token field of one taxonomy's terms.
 *
 * @param {Object}                          props          The props.
 * @param {Object}                          props.taxonomy The taxonomy, as the REST API lists it.
 * @param {unknown}                         props.value    The stored ids.
 * @param {(...args: unknown[]) => unknown} props.onChange Receives the new list of ids.
 * @return {Element} The field.
 */
function TermTokens( { taxonomy, value, onChange } ) {
	const ids = idList( value );
	const key = ids.join( ',' );
	const [ , setInput, search ] = useDebouncedInput( '' );
	const { found, known } = useSelect(
		( select ) => {
			const { getEntityRecords } = select( coreStore );
			const chosen = key ? key.split( ',' ).map( Number ) : [];
			return {
				found: getEntityRecords( 'taxonomy', taxonomy.slug, {
					search,
					per_page: 20,
					context: 'view',
				} ),
				known: chosen.length
					? getEntityRecords( 'taxonomy', taxonomy.slug, {
							include: chosen,
							per_page: chosen.length,
							context: 'view',
						} )
					: null,
			};
		},
		[ taxonomy.slug, search, key ]
	);
	return (
		<RecordTokens
			label={ recordName( taxonomy ) }
			ids={ ids }
			known={ known }
			found={ found }
			onChange={ onChange }
			onInput={ setInput }
		/>
	);
}

/**
 * The token field of the site's authors.
 *
 * @param {Object}                          props          The props.
 * @param {unknown}                         props.value    The stored ids (a comma-joined string).
 * @param {(...args: unknown[]) => unknown} props.onChange Receives the new list of ids.
 * @return {Element} The field.
 */
function AuthorTokens( { value, onChange } ) {
	const authors = useSelect(
		( select ) =>
			select( coreStore ).getEntityRecords(
				'root',
				'user',
				AUTHORS_QUERY
			),
		[]
	);
	return (
		<RecordTokens
			label={ __( 'Authors', 'blocklane' ) }
			ids={ idList( value ) }
			known={ authors }
			found={ authors }
			onChange={ onChange }
		/>
	);
}

/**
 * The query's filters: a tools panel whose items an author shows from its
 * menu.
 *
 * @param {Object}                          props          The props.
 * @param {Object}                          props.query    The whole query.
 * @param {(...args: unknown[]) => unknown} props.setQuery Stores a change to the query.
 * @return {Element|null} The panel, once the taxonomies are known.
 */
function QueryFilters( { query, setQuery } ) {
	const { taxonomies, taxonomiesResolved } = useTaxonomies();
	const filters = useMemo(
		() => filterTaxonomies( taxonomies, query.postType ),
		[ taxonomies, query.postType ]
	);
	if ( ! taxonomiesResolved ) {
		return null;
	}
	const taxQuery = isPlainObject( query.taxQuery ) ? query.taxQuery : {};

	/**
	 * Store one taxonomy's terms (none removes the taxonomy).
	 *
	 * @param {string}   slug The taxonomy.
	 * @param {number[]} ids  The term ids.
	 */
	const setTerms = ( slug, ids ) => {
		const next = { ...taxQuery };
		if ( ids.length ) {
			next[ slug ] = ids;
		} else {
			delete next[ slug ];
		}
		setQuery( { taxQuery: next } );
	};

	return (
		<ToolsPanel
			key={ query.postType }
			label={ __( 'Filters', 'blocklane' ) }
			resetAll={ () =>
				setQuery( {
					taxQuery: {},
					author: '',
					search: '',
					excludeCurrent: false,
				} )
			}
		>
			{ filters.map( ( taxonomy ) => (
				<ToolsPanelItem
					key={ taxonomy.slug }
					label={ recordName( taxonomy ) }
					hasValue={ () =>
						idList( taxQuery[ taxonomy.slug ] ).length > 0
					}
					onDeselect={ () => setTerms( taxonomy.slug, [] ) }
				>
					<TermTokens
						taxonomy={ taxonomy }
						value={ taxQuery[ taxonomy.slug ] }
						onChange={ ( ids ) => setTerms( taxonomy.slug, ids ) }
					/>
				</ToolsPanelItem>
			) ) }
			<ToolsPanelItem
				label={ __( 'Authors', 'blocklane' ) }
				hasValue={ () => idList( query.author ).length > 0 }
				onDeselect={ () => setQuery( { author: '' } ) }
			>
				<AuthorTokens
					value={ query.author }
					onChange={ ( ids ) =>
						setQuery( { author: ids.join( ',' ) } )
					}
				/>
			</ToolsPanelItem>
			<ToolsPanelItem
				label={ __( 'Keyword', 'blocklane' ) }
				hasValue={ () =>
					'string' === typeof query.search && '' !== query.search
				}
				onDeselect={ () => setQuery( { search: '' } ) }
			>
				<TextControl
					{ ...SIZED }
					label={ __( 'Keyword', 'blocklane' ) }
					value={
						'string' === typeof query.search ? query.search : ''
					}
					onChange={ ( search ) => setQuery( { search } ) }
				/>
			</ToolsPanelItem>
			<ToolsPanelItem
				label={ __( 'Exclude current post', 'blocklane' ) }
				hasValue={ () => !! query.excludeCurrent }
				onDeselect={ () => setQuery( { excludeCurrent: false } ) }
			>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Exclude current post', 'blocklane' ) }
					help={ __(
						'Leave out the post being viewed — for related-posts carousels.',
						'blocklane'
					) }
					checked={ !! query.excludeCurrent }
					onChange={ ( excludeCurrent ) =>
						setQuery( { excludeCurrent } )
					}
				/>
			</ToolsPanelItem>
		</ToolsPanel>
	);
}

/**
 * The query's controls, shown while the track is dynamic.
 *
 * @param {Object}                          props          The props.
 * @param {Object}                          props.query    The whole query.
 * @param {(...args: unknown[]) => unknown} props.setQuery Stores a change to the query.
 * @return {Element} The controls.
 */
function QueryControls( { query, setQuery } ) {
	const { types } = useQueryTypes();
	const options = useMemo(
		() =>
			queryableTypes( types ).map( ( type ) => ( {
				value: type.slug,
				label: recordName( type ),
			} ) ),
		[ types ]
	);
	return (
		<>
			<SelectControl
				{ ...SIZED }
				label={ __( 'Post type', 'blocklane' ) }
				value={ query.postType }
				options={ options }
				onChange={ ( postType ) =>
					setQuery( { postType, taxQuery: {} } )
				}
			/>
			<SelectControl
				{ ...SIZED }
				label={ __( 'Order by', 'blocklane' ) }
				value={ `${ query.orderBy }/${ query.order }` }
				options={ [
					{
						value: 'date/desc',
						label: __( 'Newest to oldest', 'blocklane' ),
					},
					{
						value: 'date/asc',
						label: __( 'Oldest to newest', 'blocklane' ),
					},
					{ value: 'title/asc', label: __( 'A → Z', 'blocklane' ) },
					{ value: 'title/desc', label: __( 'Z → A', 'blocklane' ) },
				] }
				onChange={ ( value ) => {
					const [ orderBy, order ] = value.split( '/' );
					setQuery( { orderBy, order } );
				} }
			/>
			{ 'post' === query.postType && (
				<SelectControl
					{ ...SIZED }
					label={ __( 'Sticky posts', 'blocklane' ) }
					value={
						'string' === typeof query.sticky ? query.sticky : ''
					}
					options={ [
						{ value: '', label: __( 'Include', 'blocklane' ) },
						{
							value: 'exclude',
							label: __( 'Exclude', 'blocklane' ),
						},
						{ value: 'only', label: __( 'Only', 'blocklane' ) },
					] }
					onChange={ ( sticky ) => setQuery( { sticky } ) }
				/>
			) }
			<RangeControl
				{ ...SIZED }
				label={ __( 'Items', 'blocklane' ) }
				value={ numberOr( query.perPage, 6 ) }
				min={ 1 }
				max={ 24 }
				step={ 1 }
				onChange={ ( perPage ) => {
					if ( undefined !== perPage ) {
						setQuery( { perPage } );
					}
				} }
			/>
			<NumberControl
				__next40pxDefaultSize
				label={ __( 'Offset', 'blocklane' ) }
				value={ numberOr( query.offset, 0 ) }
				min={ 0 }
				max={ 10000 }
				step={ 1 }
				onChange={ ( value ) => {
					const offset = numberOr( value, 0 );
					setQuery( { offset } );
				} }
			/>
			<QueryFilters query={ query } setQuery={ setQuery } />
		</>
	);
}

/**
 * The track's settings sidebar: Content, Carousel Settings, Autoplay and
 * Display.
 *
 * @param {Object}                       props               The props.
 * @param {Object}                       props.attributes    The track's attributes.
 * @param {(attributes: Object) => void} props.setAttributes Stores a change to them.
 * @return {Element} The sidebar's panels.
 */
export default function TrackInspector( { attributes, setAttributes } ) {
	const device = useDeviceType();
	const isDynamic = 'dynamic' === attributes.sourceType;
	const storedQuery = attributes.query;
	const query = useMemo(
		() => ( {
			...QUERY_DEFAULT,
			...( isPlainObject( storedQuery ) ? storedQuery : {} ),
		} ),
		[ storedQuery ]
	);

	/**
	 * Store the whole query with a change applied.
	 *
	 * @param {Object} change The query keys to change.
	 */
	const setQuery = ( change ) =>
		setAttributes( { query: { ...query, ...change } } );

	/**
	 * Store a slides-per-view value; anything but one per view turns fade
	 * off (a removed value counts as one).
	 *
	 * @param {string}           key   The attribute.
	 * @param {number|undefined} value The value (undefined removes it).
	 */
	const setPerView = ( key, value ) => {
		const change = { [ key ]: value };
		if ( attributes.fade && 1 !== ( undefined === value ? 1 : value ) ) {
			change.fade = false;
		}
		setAttributes( change );
	};

	const desktopView = numberOr( attributes.slidesPerView, 3 );
	const desktopGroup = numberOr( attributes.slidesPerGroup, 1 );
	const fadeAllowed = [
		desktopView,
		attributes.tabletSlidesPerView,
		attributes.mobileSlidesPerView,
	]
		.filter( ( value ) => undefined !== value && null !== value )
		.every( ( value ) => 1 === Number( value ) );

	let direction = 'default';
	if ( true === attributes.rtl ) {
		direction = 'rtl';
	} else if ( false === attributes.rtl ) {
		direction = 'ltr';
	}

	return (
		<InspectorControls>
			<PanelBody title={ __( 'Content', 'blocklane' ) } initialOpen>
				<VStack spacing={ 4 }>
					<TextControl
						{ ...SIZED }
						label={ __( 'Carousel label', 'blocklane' ) }
						placeholder={ __( 'Carousel', 'blocklane' ) }
						help={ __(
							'What screen readers call this carousel, such as "Client testimonials". Empty uses "Carousel".',
							'blocklane'
						) }
						value={
							'string' === typeof attributes.label
								? attributes.label
								: ''
						}
						onChange={ ( label ) =>
							setAttributes( {
								label: '' === label ? undefined : label,
							} )
						}
					/>
					<ToggleGroupControl
						{ ...SIZED }
						isBlock
						label={ __( 'Slides', 'blocklane' ) }
						value={ isDynamic ? 'dynamic' : 'static' }
						help={
							isDynamic
								? __(
										'Dynamic mode uses your first slide as the template.',
										'blocklane'
									)
								: undefined
						}
						onChange={ ( sourceType ) => {
							if (
								'static' === sourceType ||
								'dynamic' === sourceType
							) {
								setAttributes( { sourceType } );
							}
						} }
					>
						<ToggleGroupControlOption
							value="static"
							label={ __( 'Static', 'blocklane' ) }
						/>
						<ToggleGroupControlOption
							value="dynamic"
							label={ __( 'Dynamic', 'blocklane' ) }
						/>
					</ToggleGroupControl>
					{ isDynamic && (
						<QueryControls query={ query } setQuery={ setQuery } />
					) }
				</VStack>
			</PanelBody>
			<PanelBody
				title={ __( 'Carousel Settings', 'blocklane' ) }
				initialOpen
			>
				<VStack spacing={ 4 }>
					{ 'Desktop' === device && (
						<>
							<RangeControl
								{ ...SIZED }
								label={ __( 'Slides per view', 'blocklane' ) }
								value={ desktopView }
								min={ 1 }
								max={ 10 }
								step={ 0.1 }
								onChange={ ( value ) => {
									if ( undefined !== value ) {
										setPerView( 'slidesPerView', value );
									}
								} }
							/>
							<RangeControl
								{ ...SIZED }
								label={ __( 'Slides to scroll', 'blocklane' ) }
								value={ desktopGroup }
								min={ 1 }
								max={ 10 }
								step={ 1 }
								onChange={ ( value ) => {
									if ( undefined !== value ) {
										setAttributes( {
											slidesPerGroup: value,
										} );
									}
								} }
							/>
						</>
					) }
					{ 'Tablet' === device && (
						<>
							<RangeControl
								{ ...SIZED }
								label={ __(
									'Slides per view (Tablet)',
									'blocklane'
								) }
								value={ numberOr(
									attributes.tabletSlidesPerView,
									desktopView
								) }
								min={ 1 }
								max={ 10 }
								step={ 0.1 }
								allowReset
								onChange={ ( value ) =>
									setPerView( 'tabletSlidesPerView', value )
								}
							/>
							<RangeControl
								{ ...SIZED }
								label={ __(
									'Slides to scroll (Tablet)',
									'blocklane'
								) }
								value={ numberOr(
									attributes.tabletSlidesPerGroup,
									desktopGroup
								) }
								min={ 1 }
								max={ 10 }
								step={ 1 }
								allowReset
								onChange={ ( value ) =>
									setAttributes( {
										tabletSlidesPerGroup: value,
									} )
								}
							/>
						</>
					) }
					{ 'Mobile' === device && (
						<>
							<RangeControl
								{ ...SIZED }
								label={ __(
									'Slides per view (Mobile)',
									'blocklane'
								) }
								value={ numberOr(
									attributes.mobileSlidesPerView,
									1
								) }
								min={ 1 }
								max={ 10 }
								step={ 0.1 }
								allowReset
								onChange={ ( value ) =>
									setPerView( 'mobileSlidesPerView', value )
								}
							/>
							<RangeControl
								{ ...SIZED }
								label={ __(
									'Slides to scroll (Mobile)',
									'blocklane'
								) }
								value={ numberOr(
									attributes.mobileSlidesPerGroup,
									1
								) }
								min={ 1 }
								max={ 10 }
								step={ 1 }
								allowReset
								onChange={ ( value ) =>
									setAttributes( {
										mobileSlidesPerGroup: value,
									} )
								}
							/>
						</>
					) }
					<RangeControl
						{ ...SIZED }
						label={ __( 'Minimum slide width', 'blocklane' ) }
						help={ __(
							'Slides never shrink below this — fewer show instead.',
							'blocklane'
						) }
						value={ numberOr( attributes.slideMinWidth, 250 ) }
						min={ 0 }
						max={ 800 }
						step={ 50 }
						allowReset
						resetFallbackValue={ 250 }
						onChange={ ( value ) =>
							setAttributes( {
								slideMinWidth:
									undefined === value ? 250 : value,
							} )
						}
					/>
					<RangeControl
						{ ...SIZED }
						label={ __( 'Maximum slide width', 'blocklane' ) }
						value={ attributes.slideMaxWidth }
						min={ 100 }
						max={ 1200 }
						step={ 10 }
						allowReset
						onChange={ ( value ) =>
							setAttributes( { slideMaxWidth: value } )
						}
					/>
					<RangeControl
						{ ...SIZED }
						label={ __( 'Transition speed (ms)', 'blocklane' ) }
						value={ numberOr( attributes.speed, 300 ) }
						min={ 0 }
						max={ 1000 }
						step={ 50 }
						onChange={ ( value ) => {
							if ( undefined !== value ) {
								setAttributes( { speed: value } );
							}
						} }
					/>
				</VStack>
			</PanelBody>
			<PanelBody
				title={ __( 'Autoplay', 'blocklane' ) }
				initialOpen={ false }
			>
				<VStack spacing={ 4 }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Autoplay', 'blocklane' ) }
						checked={ !! attributes.autoplay }
						onChange={ ( autoplay ) =>
							setAttributes( { autoplay } )
						}
					/>
					{ attributes.autoplay && (
						<>
							<ToggleGroupControl
								{ ...SIZED }
								isBlock
								label={ __( 'Style', 'blocklane' ) }
								value={
									'scroll' === attributes.autoplayStyle
										? 'scroll'
										: 'slide'
								}
								onChange={ ( autoplayStyle ) => {
									if (
										'slide' === autoplayStyle ||
										'scroll' === autoplayStyle
									) {
										setAttributes( { autoplayStyle } );
									}
								} }
							>
								<ToggleGroupControlOption
									value="slide"
									label={ __( 'Slide', 'blocklane' ) }
								/>
								<ToggleGroupControlOption
									value="scroll"
									label={ __( 'Scroll', 'blocklane' ) }
								/>
							</ToggleGroupControl>
							{ 'scroll' === attributes.autoplayStyle ? (
								<RangeControl
									{ ...SIZED }
									label={ __( 'Scroll speed', 'blocklane' ) }
									value={ numberOr(
										attributes.scrollSpeed,
										2
									) }
									min={ 0.25 }
									max={ 10 }
									step={ 0.25 }
									onChange={ ( value ) => {
										if ( undefined !== value ) {
											setAttributes( {
												scrollSpeed: value,
											} );
										}
									} }
								/>
							) : (
								<RangeControl
									{ ...SIZED }
									label={ __( 'Delay (ms)', 'blocklane' ) }
									value={ numberOr(
										attributes.autoplaySpeed,
										3000
									) }
									min={ 500 }
									max={ 10000 }
									step={ 100 }
									onChange={ ( value ) => {
										if ( undefined !== value ) {
											setAttributes( {
												autoplaySpeed: value,
											} );
										}
									} }
								/>
							) }
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Pause on hover', 'blocklane' ) }
								checked={ !! attributes.pauseOnMouseEnter }
								onChange={ ( pauseOnMouseEnter ) =>
									setAttributes( { pauseOnMouseEnter } )
								}
							/>
						</>
					) }
				</VStack>
			</PanelBody>
			<PanelBody
				title={ __( 'Display', 'blocklane' ) }
				initialOpen={ false }
			>
				<VStack spacing={ 4 }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Loop', 'blocklane' ) }
						checked={ !! attributes.loop }
						onChange={ ( loop ) => setAttributes( { loop } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Fade transition', 'blocklane' ) }
						help={
							fadeAllowed
								? undefined
								: __(
										'Fade needs a single slide per view at every breakpoint.',
										'blocklane'
									)
						}
						disabled={ ! fadeAllowed }
						checked={ !! attributes.fade }
						onChange={ ( fade ) => setAttributes( { fade } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show overflowing slides', 'blocklane' ) }
						checked={ !! attributes.overflowVisible }
						onChange={ ( overflowVisible ) =>
							setAttributes( { overflowVisible } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Fade out edges', 'blocklane' ) }
						checked={ !! attributes.fadeOut }
						onChange={ ( fadeOut ) => setAttributes( { fadeOut } ) }
					/>
					{ attributes.fadeOut && (
						<ToggleGroupControl
							{ ...SIZED }
							isBlock
							label={ __( 'Faded edges', 'blocklane' ) }
							value={
								'end' === attributes.fadeOutEdges
									? 'end'
									: 'both'
							}
							onChange={ ( fadeOutEdges ) => {
								if (
									'both' === fadeOutEdges ||
									'end' === fadeOutEdges
								) {
									setAttributes( { fadeOutEdges } );
								}
							} }
						>
							<ToggleGroupControlOption
								value="both"
								label={ __( 'Both', 'blocklane' ) }
							/>
							<ToggleGroupControlOption
								value="end"
								label={ __( 'End', 'blocklane' ) }
							/>
						</ToggleGroupControl>
					) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Drag cursor', 'blocklane' ) }
						checked={ !! attributes.dragCursor }
						onChange={ ( dragCursor ) =>
							setAttributes( { dragCursor } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Mouse wheel navigation', 'blocklane' ) }
						checked={ !! attributes.wheelGestures }
						onChange={ ( wheelGestures ) =>
							setAttributes( { wheelGestures } )
						}
					/>
					<ToggleGroupControl
						{ ...SIZED }
						isBlock
						label={ __( 'Direction', 'blocklane' ) }
						help={ __(
							'Default follows the site language direction.',
							'blocklane'
						) }
						value={ direction }
						onChange={ ( choice ) => {
							if ( 'rtl' === choice ) {
								setAttributes( { rtl: true } );
							} else if ( 'ltr' === choice ) {
								setAttributes( { rtl: false } );
							} else if ( 'default' === choice ) {
								setAttributes( { rtl: undefined } );
							}
						} }
					>
						<ToggleGroupControlOption
							value="default"
							label={ __( 'Default', 'blocklane' ) }
						/>
						<ToggleGroupControlOption
							value="ltr"
							label={ __( 'LTR', 'blocklane' ) }
						/>
						<ToggleGroupControlOption
							value="rtl"
							label={ __( 'RTL', 'blocklane' ) }
						/>
					</ToggleGroupControl>
				</VStack>
			</PanelBody>
		</InspectorControls>
	);
}
