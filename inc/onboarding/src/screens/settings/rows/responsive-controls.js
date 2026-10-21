/**
 * Settings row: Responsive Controls (`responsive-controls`).
 *
 * Owned by the `extension:responsive-controls` unit: an edition without the unit carries
 * neither this file nor its line in ../rows.js, which the generator
 * rewrites into a Pro row built from the localized catalog.
 *
 * One row of the Settings list (the C-settings spec (2026-10-12)
 * §3.2): its store, copy and About in one place. The copy is drafted from
 * the feature's behavior (the C-settings About copy (2026-10-12),
 * for approval).
 * Its options are defined here, so they leave a build with the row (RW5).
 */

import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useState } from '@wordpress/element';
import {
	Button,
	Spinner,
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';

import { get, post } from '../../../api/client';
import { errorMessage } from '../../../api/errors';
import { LoadError } from '../../../components/LoadError';

/** The unit the two fields take (UnitControl, px only). */
const PX = [ { value: 'px', label: 'px' } ];

/**
 * The site-wide breakpoints, under the row while it is ON (design spec
 * Amendment F4/4a): two px fields, one help line and Save. They live on their
 * own route (/breakpoints), so this component owns its load (LoadError and
 * Retry on a failure) and its save, and reports through the screen's toast
 * and save-error paths.
 *
 * @param {Object}                    props
 * @param {(message: string) => void} props.saved  The screen's toast path.
 * @param {(detail: string) => void}  props.failed The screen's save-error path.
 * @return {JSX.Element} The options.
 */
const BreakpointsOptions = ( { saved, failed } ) => {
	const [ status, setStatus ] = useState( 'loading' );
	// The server's message when the load failed: the load error's second line.
	const [ detail, setDetail ] = useState( '' );
	const [ values, setValues ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	const load = useCallback( () => {
		setStatus( 'loading' );
		setDetail( '' );
		return get( '/breakpoints' ).then(
			( res ) => {
				setValues( res?.breakpoints || null );
				setStatus( res?.breakpoints ? 'ready' : 'failed' );
			},
			( err ) => {
				setDetail( errorMessage( err ) );
				setStatus( 'failed' );
			}
		);
	}, [] );
	useEffect( () => {
		load();
	}, [ load ] );

	if ( 'loading' === status ) {
		return <Spinner />;
	}
	if ( 'failed' === status ) {
		return (
			<LoadError
				message={ __(
					'The breakpoints could not be loaded.',
					'blocklane'
				) }
				detail={ detail }
				onRetry={ load }
			/>
		);
	}

	const tablet = parseInt( values.tablet, 10 ) || 0;
	const mobile = parseInt( values.mobile, 10 ) || 0;
	const crossed = mobile >= tablet;
	const set = ( key ) => ( next ) =>
		setValues( ( prev ) => ( {
			...prev,
			[ key ]: parseInt( next, 10 ) || 0,
		} ) );

	const save = () => {
		setBusy( true );
		post( '/breakpoints', { tablet, mobile } )
			.then(
				( res ) => {
					setValues( res?.breakpoints || values );
					saved(
						__(
							'Breakpoints saved. Reload the editor to see them in its previews.',
							'blocklane'
						)
					);
				},
				( error ) => failed( errorMessage( error ) )
			)
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="blocklane-breakpoints">
			<div className="blocklane-breakpoints__fields">
				<UnitControl
					__next40pxDefaultSize
					label={ __( 'Tablet breakpoint', 'blocklane' ) }
					units={ PX }
					value={ `${ tablet }px` }
					onChange={ set( 'tablet' ) }
				/>
				<UnitControl
					__next40pxDefaultSize
					label={ __( 'Mobile breakpoint', 'blocklane' ) }
					units={ PX }
					value={ `${ mobile }px` }
					onChange={ set( 'mobile' ) }
				/>
			</div>
			<p
				className="blocklane-breakpoints__help"
				data-warning={ crossed || undefined }
			>
				{ crossed
					? __(
							'Mobile must be narrower than tablet. Saving moves it below tablet.',
							'blocklane'
						)
					: __(
							'Tablet and mobile settings apply below these widths, on every block.',
							'blocklane'
						) }
			</p>
			<div>
				<Button
					variant="secondary"
					isBusy={ busy }
					disabled={ busy }
					onClick={ save }
				>
					{ __( 'Save breakpoints', 'blocklane' ) }
				</Button>
			</div>
		</div>
	);
};

export default {
	slug: 'responsive-controls',
	store: 'extensions',
	title: __( 'Responsive Settings', 'blocklane' ),
	short: __(
		'Adds per-device display order, max width and hide options to core blocks.',
		'blocklane'
	),
	about: {
		intro: __(
			'Adds the tablet and mobile settings core blocks lack: display order, max width and hiding a block on one device.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it adds', 'blocklane' ),
				items: [
					__(
						'Display order per device for blocks inside a Row.',
						'blocklane'
					),
					__(
						"Max width per device, in the block's Dimensions panel.",
						'blocklane'
					),
					__(
						'A Hide on Desktop, Tablet or Mobile switch for the selected device.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Where it shows', 'blocklane' ),
				items: [
					__(
						"Inside core's own Layout and Dimensions panels, following the editor's device preview.",
						'blocklane'
					),
					__(
						'Tablet and mobile widths default to 768 and 480 pixels and can be changed in the editor.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						"Core's responsive styles handle typography and spacing; this adds only what core has no setting for.",
						'blocklane'
					),
					__(
						"Hiding a block per device uses core's own setting, so it keeps working when this is off.",
						'blocklane'
					),
				],
			},
		],
		learnMoreUrl: 'https://blocklanewp.com/responsive-controls/',
	},
	options: BreakpointsOptions,
};
