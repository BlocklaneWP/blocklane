/**
 * SEO screen — Content tab.
 *
 * Every published post/page/CPT with its SEO field state, in a DataViews
 * table (search, type + schema filters, sorting, pagination — all
 * server-driven through GET /seo/content). Constructed exactly like the Site Editor's
 * patterns page (edit-site/page-patterns): fields + a top-level `actions`
 * array handed to DataViews, which renders its own actions column (primary
 * actions on row hover, the rest behind the ellipsis menu) and makes rows
 * clickable via isItemClickable/onClickItem — so the markup, keyboard and
 * responsive behavior are DataViews' own, identical to core's screens.
 * Row click and the primary action hand the item to the screen shell via
 * onEdit, which opens the Edit SEO panel in the suite's right-hand drawer
 * slot (core's "Quick Edit" drawer arrangement).
 *
 * Against the frames (the C-seo spec (2026-10-12); design spec
 * Amendments K, L/D16, V18, W15, Y1, Y2): the default view shows the title
 * with its content type as a Badge, Structured data and Search indexing;
 * Content type, Title in search and Description in search stay as fields
 * the View options can show. The header carries core's Quick edit toggle,
 * pressed while the drawer is open; opening a row selects it, as core's
 * Quick Edit edits the selection.
 */

import { __ } from '@wordpress/i18n';
import {
	useState,
	useEffect,
	useMemo,
	useCallback,
	useRef,
} from '@wordpress/element';
// NOTE: the DataViews stylesheet is @import-ed by ../../style.css — importing
// it here would make the dependency-extraction plugin register a bogus
// "wp-dataviews/…css" script dependency and WordPress would drop the bundle.
import { DataViews } from '@wordpress/dataviews';
import {
	pencil as editIcon,
	unseen,
	seen,
	drawerRight,
} from '@wordpress/icons';
import {
	Button,
	Flex,
	Notice,
	RadioControl,
	Spinner,
} from '@wordpress/components';
import { Badge } from '@wordpress/ui';

import { seo as seoApi } from '../../api/client';
import { LoadError } from '../../components/LoadError';
import {
	errorMessage,
	StatusChip,
	SCHEMA_OPTIONS,
	schemaLabel,
} from './shared';
import { seoCopy } from '../../../../shared/seo-copy';

// Core's patterns page shape: per-layout config spread into the default
// view, and passed to DataViews so a layout reset restores the same values.
const defaultLayouts = {
	table: {},
};

const DEFAULT_VIEW = {
	type: 'table',
	page: 1,
	perPage: 20,
	search: '',
	filters: [],
	// The templates page's default ordering; sortable fields put "Sort by"
	// in the view-options panel and the sort menu in the column headers.
	sort: { field: 'title', direction: 'asc' },
	titleField: 'title',
	// The frame's default view (W15): the title (with its type badge, Y2),
	// then Structured data and Search indexing. Content type, Title in search
	// and Description in search are shown from View options › Properties.
	fields: [ 'schema', 'visibility' ],
	...defaultLayouts.table,
};

// The "Set schema type" bulk action's modal: one radio choice applied to
// every selected row through the shared bulk-save loop.
const SchemaModal = ( { items, closeModal, onApply } ) => {
	// The strings shared/seo-copy.js owns (a clean-room rewrite).
	const copy = seoCopy();
	const [ schemaType, setSchemaType ] = useState( '' );
	const [ isBusy, setIsBusy ] = useState( false );

	const apply = async () => {
		setIsBusy( true );
		await onApply( items, { schema_type: schemaType } );
		closeModal();
	};

	return (
		<>
			<RadioControl
				label={ copy.fieldSchemaLabel }
				hideLabelFromVision
				selected={ schemaType }
				options={ SCHEMA_OPTIONS }
				onChange={ setSchemaType }
			/>
			<Flex justify="flex-end" gap={ 2 }>
				<Button
					variant="tertiary"
					disabled={ isBusy }
					onClick={ closeModal }
				>
					{ __( 'Cancel', 'blocklane' ) }
				</Button>
				<Button
					variant="primary"
					isBusy={ isBusy }
					disabled={ isBusy }
					onClick={ apply }
				>
					{ __( 'Apply', 'blocklane' ) }
				</Button>
			</Flex>
		</>
	);
};

/**
 * The title's anchor slug for the parity harness (`@seo-title:<slug>`):
 * lowercase letters, digits and single dashes.
 *
 * @param {string} title The row's title.
 * @return {string} Its slug.
 */
const titleSlug = ( title ) =>
	String( title )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-|-$/g, '' );

/**
 * @param {Object}                                                                                 props
 * @param {(item: Object, onSaved: (row: Object) => void, stepper: Object, site: ?Object) => void} props.onEdit          Opens the Edit SEO drawer for a row.
 * @param {number|null}                                                                            [props.editingId]     The row the drawer edits, or null when it is closed.
 * @param {() => void}                                                                             [props.onStopEditing] Closes the drawer.
 */
export const ContentTab = ( {
	onEdit,
	editingId = null,
	onStopEditing = () => {},
} ) => {
	// The strings shared/seo-copy.js owns (a clean-room rewrite), made once
	// so the memoized fields and actions below can depend on them.
	const copy = useMemo( seoCopy, [] );
	const [ view, setView ] = useState( DEFAULT_VIEW );
	const [ items, setItems ] = useState( [] );
	const [ types, setTypes ] = useState( [] );
	const [ totalItems, setTotalItems ] = useState( 0 );
	const [ totalPages, setTotalPages ] = useState( 0 );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ selection, setSelection ] = useState( [] );
	const [ site, setSite ] = useState( null );
	// Bumped by the error notice's Retry button to re-run the fetch effect.
	const [ reload, setReload ] = useState( 0 );

	// The drawer's stepper resolves prev/next against the rows as they are
	// NOW (they change under it on refetch), so keep a live handle.
	const itemsRef = useRef( items );
	itemsRef.current = items;

	const typeFilter =
		view.filters?.find( ( f ) => f.field === 'type' )?.value || '';
	const schemaFilter =
		view.filters?.find( ( f ) => f.field === 'schema' )?.value || '';
	const sortField = view.sort?.field || 'title';
	const sortDirection = view.sort?.direction || 'asc';

	useEffect( () => {
		let stale = false;
		setLoading( true );
		setError( '' );
		seoApi
			.content( {
				search: view.search || '',
				type: typeFilter,
				schema: schemaFilter,
				page: view.page,
				per_page: view.perPage,
				orderby: sortField,
				order: sortDirection,
			} )
			.then( ( data ) => {
				if ( stale ) {
					return;
				}
				setItems( data.items || [] );
				setTotalItems( data.total || 0 );
				setTotalPages( data.total_pages || 0 );
				setTypes( data.types || [] );
				setSite( data.site || null );
				// A new result set invalidates any row selection.
				setSelection( [] );
			} )
			.catch( ( err ) => ! stale && setError( errorMessage( err ) ) )
			.finally( () => ! stale && setLoading( false ) );

		return () => {
			stale = true;
		};
	}, [
		view.search,
		typeFilter,
		schemaFilter,
		sortField,
		sortDirection,
		view.page,
		view.perPage,
		reload,
	] );

	// Patch saved rows back into the table without a refetch.
	const patchRows = useCallback( ( rows ) => {
		const byId = new Map( rows.map( ( row ) => [ row.id, row ] ) );
		setItems( ( current ) =>
			current.map( ( it ) => byId.get( it.id ) || it )
		);
	}, [] );

	// Open the shell's Edit SEO panel: a saver that patches the edited row,
	// plus a stepper the drawer's prev/next and Save & next ride — resolved
	// live against the current rows, true when it moved to a neighbor.
	const editItem = useCallback(
		( item ) => {
			// Core's Quick Edit edits the selection: the row being edited
			// is the one row selected (frame 62:1094).
			setSelection( [ String( item.id ) ] );
			const rows = itemsRef.current;
			const index = rows.findIndex( ( it ) => it.id === item.id );

			onEdit(
				item,
				( row ) => patchRows( [ row ] ),
				{
					hasPrev: index > 0,
					hasNext: index !== -1 && index < rows.length - 1,
					step: ( fromId, delta ) => {
						const current = itemsRef.current;
						const at = current.findIndex(
							( it ) => it.id === fromId
						);
						const target = at === -1 ? null : current[ at + delta ];
						if ( ! target ) {
							return false;
						}
						editItem( target );
						return true;
					},
				},
				site
			);
		},
		[ onEdit, patchRows, site ]
	);

	// One bulk save loop shared by the flag actions: sequential per-row
	// saves through the same endpoint the drawer uses, patching rows as
	// they land. Errors stop the run and surface in the tab's notice.
	const bulkSave = useCallback(
		async ( targets, payload ) => {
			try {
				for ( const target of targets ) {
					const row = await seoApi.saveContentRow( {
						id: target.id,
						...payload,
					} );
					patchRows( [ row ] );
				}
			} catch ( err ) {
				setError( errorMessage( err ) );
			}
			setSelection( [] );
		},
		[ patchRows ]
	);

	const fields = useMemo(
		() => [
			// Title and Type sort server-side (the templates page's sortable
			// pair); the meta-backed columns below stay unsortable — ordering
			// by postmeta needs a join the endpoint deliberately avoids, and
			// gap-hunting is the audit filter's job, not the sort's.
			{
				id: 'title',
				label: copy.columnTitle,
				enableSorting: true,
				enableGlobalSearch: true,
				getValue: ( { item } ) => item.title,
				// Y2: the content type rides beside the title as a Badge.
				render: ( { item } ) => (
					<span
						className="blocklane-seo-content__title"
						data-seo-title={ titleSlug( item.title ) }
					>
						{ item.title }
						<Badge>{ item.type_label }</Badge>
					</span>
				),
			},
			{
				id: 'type',
				label: copy.columnType,
				enableSorting: true,
				getValue: ( { item } ) => item.type,
				elements: types.map( ( t ) => ( {
					value: t.slug,
					label: t.label,
				} ) ),
				// Behind the funnel, not a standing chip: the default view
				// shows no filter row (W15).
				filterBy: { operators: [ 'is' ] },
				render: ( { item } ) => item.type_label,
			},
			{
				id: 'schema',
				label: copy.columnSchema,
				enableSorting: false,
				getValue: ( { item } ) => item.schema_type || 'none',
				// Filterable but not primary: DataViews lists it under its
				// "Add filter" menu instead of as an always-visible chip. The
				// filter wire value for "no schema" is 'none' (the REST enum),
				// not ''.
				elements: SCHEMA_OPTIONS.map( ( option ) => ( {
					value: option.value || 'none',
					label: option.label,
				} ) ),
				filterBy: { operators: [ 'is' ] },
				// Y1: an unset type reads "None", the option's own label.
				render: ( { item } ) => schemaLabel( item.schema_type ),
			},
			{
				id: 'seo_title',
				label: copy.columnSearchTitle,
				enableSorting: false,
				// The actual value, ellipsized — scannable for quality, not
				// just coverage. The chip only marks the gaps.
				render: ( { item } ) =>
					item.seo_title ? (
						<span
							className="blocklane-pro-seo-screen__cell-value"
							title={ item.seo_title }
						>
							{ item.seo_title }
						</span>
					) : (
						<StatusChip>{ copy.cellEmpty }</StatusChip>
					),
			},
			{
				id: 'seo_description',
				label: copy.columnSearchDescription,
				enableSorting: false,
				render: ( { item } ) =>
					item.seo_description ? (
						<span
							className="blocklane-pro-seo-screen__cell-value is-wide"
							title={ item.seo_description }
						>
							{ item.seo_description }
						</span>
					) : (
						<StatusChip>{ copy.cellEmpty }</StatusChip>
					),
			},
			{
				id: 'visibility',
				label: copy.columnIndexing,
				enableSorting: false,
				// Plain text, as the frames draw the column.
				render: ( { item } ) =>
					item.noindex ? copy.indexingHidden : copy.indexingShown,
			},
		],
		[ types, copy ]
	);

	// The patterns page's action anatomy: one isPrimary action surfaced on
	// row hover/focus (DataViews shows it as a compact text button in table
	// layout, exactly like core), everything else in the ellipsis menu.
	// supportsBulk actions add core's selection checkboxes + bulk toolbar.
	const actions = useMemo(
		() => [
			{
				id: 'edit-seo',
				label: copy.editSeo,
				icon: editIcon,
				isPrimary: true,
				callback: ( [ item ] ) => editItem( item ),
			},
			{
				id: 'hide-from-search',
				label: copy.actionHide,
				icon: unseen,
				supportsBulk: true,
				isEligible: ( item ) => ! item.noindex,
				// The toolbar hands over ALL selected rows; keep the run to
				// the eligible ones so already-hidden rows aren't re-saved.
				callback: ( selected ) =>
					bulkSave(
						selected.filter( ( item ) => ! item.noindex ),
						{ noindex: true }
					),
			},
			{
				id: 'show-in-search',
				label: copy.actionShow,
				icon: seen,
				supportsBulk: true,
				isEligible: ( item ) => !! item.noindex,
				callback: ( selected ) =>
					bulkSave(
						selected.filter( ( item ) => !! item.noindex ),
						{ noindex: false }
					),
			},
			{
				id: 'set-schema',
				label: copy.actionSetSchema,
				supportsBulk: true,
				modalHeader: copy.actionSetSchema,
				RenderModal: ( { items: selected, closeModal } ) => (
					<SchemaModal
						items={ selected }
						closeModal={ closeModal }
						onApply={ bulkSave }
					/>
				),
			},
			{
				id: 'edit-post',
				label: __( 'Edit page', 'blocklane' ),
				isEligible: ( item ) => !! item.edit_url,
				callback: ( [ item ] ) => {
					window.location.href = item.edit_url;
				},
			},
			{
				id: 'view-post',
				label: __( 'View page', 'blocklane' ),
				isEligible: ( item ) => !! item.url,
				callback: ( [ item ] ) => {
					window.open( item.url, '_blank' );
				},
			},
		],
		[ editItem, bulkSave, copy ]
	);

	// D16: core's Quick edit toggle, pressed while the drawer is open. It
	// opens the drawer for the selected row (the first, when several are),
	// or for the first row when nothing is selected, and closes it when open.
	const quickEdit = (
		<Button
			className="blocklane-seo-content__quick-edit"
			data-quick-edit=""
			size="compact"
			icon={ drawerRight }
			label={ __( 'Quick edit', 'blocklane' ) }
			isPressed={ null !== editingId }
			disabled={ null === editingId && ! items.length }
			onClick={ () => {
				if ( null !== editingId ) {
					onStopEditing();
					return;
				}
				const target =
					items.find(
						( it ) => String( it.id ) === selection[ 0 ]
					) || items[ 0 ];
				if ( target ) {
					editItem( target );
				}
			} }
		/>
	);

	// An error never takes the whole tab down while there are rows to show —
	// a failed bulk row-save or refetch surfaces as a dismissible notice
	// ABOVE the table (rows already saved stay patched in). Only a failed
	// FIRST load has nothing behind it: the R3a sentence and Retry (V2).
	if ( error && ! items.length ) {
		return (
			<div className="blocklane-seo-content">
				<LoadError
					message={ __(
						'Your content could not be loaded. Your posts and pages are unchanged.',
						'blocklane'
					) }
					detail={ error }
					onRetry={ () => {
						setError( '' );
						setReload( ( n ) => n + 1 );
					} }
				/>
			</div>
		);
	}

	return (
		<div className="blocklane-seo-content" data-dataviews="seo-content">
			{ error ? (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) : null }
			<DataViews
				data={ items }
				fields={ fields }
				actions={ actions }
				view={ view }
				onChangeView={ setView }
				isLoading={ loading }
				defaultLayouts={ defaultLayouts }
				paginationInfo={ { totalItems, totalPages } }
				getItemId={ ( item ) => String( item.id ) }
				isItemClickable={ () => true }
				onClickItem={ editItem }
				selection={ selection }
				onChangeSelection={ setSelection }
				header={ quickEdit }
				// V18: the first load shows the spinner, never "No results".
				empty={ loading ? <Spinner /> : undefined }
			/>
		</div>
	);
};
