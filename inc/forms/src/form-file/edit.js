/**
 * File upload field — edit. Same structure as render.php; the control is a
 * real (inert) file input so the canvas is the front, and the hint line
 * mirrors the effective constraints the server will enforce.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	BaseControl,
	CheckboxControl,
	Notice,
	ToggleControl,
	__experimentalNumberControl as NumberControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import useToolsPanelDropdownMenuProps from '../../../shared/use-tools-panel-dropdown-menu-props';
import { useResolvedFieldName } from '../shared/use-resolved-field-names';
import {
	FieldLabelItem,
	RequiredItem,
	VisibilityItem,
	FieldNameItem,
} from '../shared/field-controls';
import { useControlStyleProps, toControlProps } from '../shared/control-props';
import { formatSize } from '../shared/format-size';

// The effective type map (group => { label, exts }), bridged from the
// server by editor_state(): the blocklane_pro_forms_file_types filter and
// the deny list applied — the same truth file_constraints enforces, so
// filter-added groups get toggles here too. The static copy below is only
// the fallback if the inline bridge ever fails to run.
const FALLBACK_GROUPS = {
	images: {
		label: __( 'Images', 'blocklane' ),
		exts: [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ],
	},
	documents: {
		label: __( 'Documents', 'blocklane' ),
		exts: [
			'pdf',
			'doc',
			'docx',
			'xls',
			'xlsx',
			'ppt',
			'pptx',
			'odt',
			'ods',
			'txt',
			'rtf',
			'csv',
		],
	},
	archives: {
		label: __( 'Archives (zip)', 'blocklane' ),
		exts: [ 'zip' ],
	},
};
const TYPE_GROUPS = window.blocklaneProForms?.fileTypes || FALLBACK_GROUPS;
const DEFAULT_GROUPS = [ 'images', 'documents' ];

export default function FormFileEdit( {
	attributes,
	setAttributes,
	clientId,
} ) {
	const { name, label, required, accept, multiple, maxFiles, maxSize } =
		attributes;

	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	const resolvedName = useResolvedFieldName( attributes );
	const controlProps = toControlProps( useControlStyleProps( attributes ) );
	const editId = 'blf-edit-' + clientId;

	// Uploads only persist when the parent form stores submissions — surface
	// the footgun right on the field instead of losing files silently. The
	// attribute is tri-state (undefined = inherit the site default), so an
	// absent value resolves through the bridged defaults exactly like
	// resolve_config() does at submit time — a form left at "Default" on a
	// storage-off site must warn too.
	const storageOff = useSelect(
		( select ) => {
			const { getBlockParentsByBlockName, getBlockAttributes } =
				select( blockEditorStore );
			const formId = getBlockParentsByBlockName(
				clientId,
				'blocklane/form'
			)[ 0 ];
			return (
				!! formId &&
				false ===
					( getBlockAttributes( formId )?.storeSubmissions ??
						window.blocklaneProForms?.defaults?.storeSubmissions ??
						true )
			);
		},
		[ clientId ]
	);

	const groups = ( accept?.length ? accept : DEFAULT_GROUPS ).filter(
		( key ) => TYPE_GROUPS[ key ]
	);
	// Mirrors the server fallback: unknown/empty selections fall back to
	// whichever defaults the (filterable) map still contains.
	const effectiveGroups = groups.length
		? groups
		: DEFAULT_GROUPS.filter( ( key ) => TYPE_GROUPS[ key ] );
	const exts = effectiveGroups.flatMap( ( key ) => TYPE_GROUPS[ key ].exts );

	// Server cap bridged by runtime (wp_max_upload_size) so the canvas hint
	// shows the EFFECTIVE limit, exactly like render.php.
	const serverCap = window.blocklaneProForms?.maxUpload || 0;
	const authored = ( maxSize > 0 ? maxSize : 8 ) * 1024 * 1024;
	const effectiveSize =
		serverCap > 0 ? Math.min( authored, serverCap ) : authored;
	// Mirrors the server clamp exactly (min(10, max(1, absint))): a saved
	// maxFiles of 0 must read "1 file" here because that is what the server
	// enforces — `maxFiles || 3` promoted falsy 0 to 3 and the canvas lied.
	const effectiveFiles = multiple
		? Math.min( 10, Math.max( 1, parseInt( maxFiles, 10 ) || 0 ) )
		: 1;

	let hint = sprintf(
		/* translators: 1: extension list, 2: maximum file size (e.g. "8 MB"). */
		__( 'Accepted: %1$s · Max %2$s', 'blocklane' ),
		exts.join( ', ' ),
		formatSize( effectiveSize )
	);
	if ( multiple ) {
		hint +=
			' · ' +
			sprintf(
				/* translators: %d: maximum number of files. */
				_n(
					'Up to %d file',
					'Up to %d files',
					effectiveFiles,
					'blocklane'
				),
				effectiveFiles
			);
	}

	const blockProps = useBlockProps( {
		className:
			'blocklane-form__field is-type-file' +
			( required ? ' is-required' : '' ),
	} );

	const toggleGroup = ( key, checked ) => {
		const next = checked
			? [ ...new Set( [ ...groups, key ] ) ]
			: groups.filter( ( g ) => g !== key );
		setAttributes( { accept: next } );
	};

	return (
		<>
			<InspectorControls>
				<ToolsPanel
					label={ __( 'Field settings', 'blocklane' ) }
					resetAll={ () =>
						setAttributes( {
							condition: {
								enabled: false,
								field: '',
								operator: 'is',
								value: '',
							},
							label: '',
							required: false,
							name: '',
							accept: DEFAULT_GROUPS,
							multiple: false,
							maxFiles: 3,
							maxSize: 8,
						} )
					}
					dropdownMenuProps={ dropdownMenuProps }
				>
					<FieldLabelItem
						label={ label }
						onChange={ ( value ) =>
							setAttributes( { label: value } )
						}
					/>
					<RequiredItem
						required={ required }
						onChange={ ( value ) =>
							setAttributes( { required: value } )
						}
					/>
					<ToolsPanelItem
						hasValue={ () =>
							JSON.stringify( groups ) !==
							JSON.stringify( DEFAULT_GROUPS )
						}
						label={ __( 'Allowed types', 'blocklane' ) }
						onDeselect={ () =>
							setAttributes( { accept: DEFAULT_GROUPS } )
						}
						isShownByDefault
					>
						<VStack spacing={ 3 }>
							<BaseControl.VisualLabel>
								{ __( 'Allowed types', 'blocklane' ) }
							</BaseControl.VisualLabel>
							{ Object.entries( TYPE_GROUPS ).map(
								( [ key, group ] ) => (
									<CheckboxControl
										__nextHasNoMarginBottom
										key={ key }
										label={ group.label }
										help={ group.exts.join( ', ' ) }
										checked={ effectiveGroups.includes(
											key
										) }
										onChange={ ( checked ) =>
											toggleGroup( key, checked )
										}
									/>
								)
							) }
						</VStack>
					</ToolsPanelItem>
					<ToolsPanelItem
						hasValue={ () => multiple }
						label={ __( 'Multiple files', 'blocklane' ) }
						onDeselect={ () =>
							setAttributes( { multiple: false, maxFiles: 3 } )
						}
					>
						<VStack spacing={ 3 }>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Multiple files', 'blocklane' ) }
								checked={ multiple }
								onChange={ ( value ) =>
									setAttributes( { multiple: value } )
								}
							/>
							{ multiple && (
								<NumberControl
									__next40pxDefaultSize
									label={ __( 'Maximum files', 'blocklane' ) }
									min={ 1 }
									max={ 10 }
									value={ maxFiles }
									onChange={ ( value ) =>
										setAttributes( {
											maxFiles: value
												? Math.min(
														10,
														Math.max(
															1,
															parseInt(
																value,
																10
															) || 1
														)
													)
												: 3,
										} )
									}
								/>
							) }
						</VStack>
					</ToolsPanelItem>
					<ToolsPanelItem
						hasValue={ () => 8 !== maxSize }
						label={ __( 'Maximum size (MB)', 'blocklane' ) }
						onDeselect={ () => setAttributes( { maxSize: 8 } ) }
					>
						<NumberControl
							__next40pxDefaultSize
							label={ __( 'Maximum size (MB)', 'blocklane' ) }
							help={
								serverCap > 0
									? sprintf(
											/* translators: %s: effective upload ceiling, e.g. "64 MB". */
											__(
												'Uploads are capped at %s per file (server limit and Forms settings).',
												'blocklane'
											),
											formatSize( serverCap )
										)
									: undefined
							}
							min={ 1 }
							value={ maxSize }
							onChange={ ( value ) =>
								setAttributes( {
									maxSize: value ? parseInt( value, 10 ) : 8,
								} )
							}
						/>
					</ToolsPanelItem>
					<VisibilityItem
						condition={ attributes.condition }
						onChange={ ( condition ) =>
							setAttributes( { condition } )
						}
						clientId={ clientId }
					/>
					<FieldNameItem
						name={ name }
						resolvedName={ resolvedName }
						onChange={ ( value ) =>
							setAttributes( { name: value } )
						}
					/>
				</ToolsPanel>
				{ storageOff && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'This form has submission storage turned off, so uploaded files are discarded — only their names reach the notification email.',
							'blocklane'
						) }
					</Notice>
				) }
			</InspectorControls>
			<div { ...blockProps }>
				<label className="blocklane-form__label" htmlFor={ editId }>
					<RichText
						tagName="span"
						className="blocklane-form__label-text"
						value={ label }
						onChange={ ( value ) =>
							setAttributes( { label: value } )
						}
						placeholder={ __( 'Add a label…', 'blocklane' ) }
						allowedFormats={ [ 'core/bold', 'core/italic' ] }
					/>
					{ required && (
						<span
							className="blocklane-form__required"
							aria-hidden="true"
						>
							*
						</span>
					) }
				</label>
				<input
					{ ...controlProps }
					className={ ( controlProps.className || '' ) + ' is-file' }
					type="file"
					id={ editId }
					multiple={ multiple }
					tabIndex={ -1 }
					onClick={ ( event ) => event.preventDefault() }
				/>
				<p className="blocklane-form__hint">{ hint }</p>
			</div>
		</>
	);
}
