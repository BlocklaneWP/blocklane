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
import { pencil as editIcon, unseen, seen } from '@wordpress/icons';
import { Button, Flex, Notice, RadioControl } from '@wordpress/components';

import { seo as seoApi } from '../../api/client';
import {
	errorMessage,
	StatusChip,
	SCHEMA_OPTIONS,
	schemaLabel,
} from './shared';

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
	fields: [ 'type', 'schema', 'seo_title', 'seo_description', 'visibility' ],
	...defaultLayouts.table,
};

// The "Set schema type" bulk action's modal: one radio choice applied to
// every selected row through the shared bulk-save loop.
const SchemaModal = ( { items, closeModal, onApply } ) => {
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
				label={ __( 'Schema type', 'blocklane' ) }
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

export const ContentTab = ( { onEdit } ) => {
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
				label: __( 'Title', 'blocklane' ),
				enableSorting: true,
				enableGlobalSearch: true,
				getValue: ( { item } ) => item.title,
				render: ( { item } ) => <strong>{ item.title }</strong>,
			},
			{
				id: 'type',
				label: __( 'Type', 'blocklane' ),
				enableSorting: true,
				getValue: ( { item } ) => item.type,
				elements: types.map( ( t ) => ( {
					value: t.slug,
					label: t.label,
				} ) ),
				// isPrimary keeps the Type filter chip always visible above
				// the table instead of hidden behind the funnel toggle.
				filterBy: { operators: [ 'is' ], isPrimary: true },
				render: ( { item } ) => item.type_label,
			},
			{
				id: 'schema',
				label: __( 'Schema', 'blocklane' ),
				enableSorting: false,
				getValue: ( { item } ) => item.schema_type || 'none',
				// Filterable but not primary — reachable through "Add filter",
				// the Jetpack SEO dashboard's exact arrangement. The filter
				// wire value for "no schema" is 'none' (the REST enum), not ''.
				elements: SCHEMA_OPTIONS.map( ( option ) => ( {
					value: option.value || 'none',
					label: option.label,
				} ) ),
				filterBy: { operators: [ 'is' ] },
				render: ( { item } ) =>
					item.schema_type ? schemaLabel( item.schema_type ) : '—',
			},
			{
				id: 'seo_title',
				label: __( 'SEO title', 'blocklane' ),
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
						<StatusChip>
							{ __( 'Not set', 'blocklane' ) }
						</StatusChip>
					),
			},
			{
				id: 'seo_description',
				label: __( 'Meta description', 'blocklane' ),
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
						<StatusChip>
							{ __( 'Not set', 'blocklane' ) }
						</StatusChip>
					),
			},
			{
				id: 'visibility',
				label: __( 'Search', 'blocklane' ),
				enableSorting: false,
				render: ( { item } ) =>
					item.noindex ? (
						<StatusChip tone="warning">
							{ __( 'Hidden', 'blocklane' ) }
						</StatusChip>
					) : (
						<StatusChip tone="positive">
							{ __( 'Visible', 'blocklane' ) }
						</StatusChip>
					),
			},
		],
		[ types ]
	);

	// The patterns page's action anatomy: one isPrimary action surfaced on
	// row hover/focus (DataViews shows it as a compact text button in table
	// layout, exactly like core), everything else in the ellipsis menu.
	// supportsBulk actions add core's selection checkboxes + bulk toolbar.
	const actions = useMemo(
		() => [
			{
				id: 'edit-seo',
				label: __( 'Edit SEO', 'blocklane' ),
				icon: editIcon,
				isPrimary: true,
				callback: ( [ item ] ) => editItem( item ),
			},
			{
				id: 'hide-from-search',
				label: __( 'Hide from search', 'blocklane' ),
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
				label: __( 'Show in search', 'blocklane' ),
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
				label: __( 'Set schema type', 'blocklane' ),
				supportsBulk: true,
				modalHeader: __( 'Set schema type', 'blocklane' ),
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
		[ editItem, bulkSave ]
	);

	// An error never takes the whole tab down while there are rows to show —
	// a failed bulk row-save or refetch surfaces as a dismissible notice
	// ABOVE the table (rows already saved stay patched in). Only a failed
	// FIRST load has nothing behind it, and that gets a Retry.
	if ( error && ! items.length ) {
		return (
			<Notice
				status="error"
				actions={ [
					{
						label: __( 'Retry', 'blocklane' ),
						onClick: () => setReload( ( n ) => n + 1 ),
					},
				] }
				isDismissible={ false }
			>
				{ error }
			</Notice>
		);
	}

	return (
		<div className="blocklane-pro-seo-screen__content-tab">
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
			/>
		</div>
	);
};
