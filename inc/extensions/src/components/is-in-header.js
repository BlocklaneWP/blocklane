/**
 * Shared "is this block being built in the header?" selector, used by the
 * advanced-group smart-header toggle and the transparent-header controls.
 *
 * True when editing a header-area template part directly (site editor or the
 * template-part editor), or when the block is nested inside a header-area
 * template part within a template.
 *
 * @param {Function} select   Registry select (from useSelect).
 * @param {string}   clientId Block client id.
 * @return {boolean} Whether the block renders inside the header.
 */
export function isInHeaderArea( select, clientId ) {
	const core = select( 'core' );

	const editSite = select( 'core/edit-site' );
	if (
		editSite?.getEditedPostType &&
		editSite.getEditedPostType() === 'wp_template_part'
	) {
		const id = editSite.getEditedPostId?.();
		const record =
			id &&
			core?.getEditedEntityRecord( 'postType', 'wp_template_part', id );
		if ( record?.area === 'header' ) {
			return true;
		}
	}

	const coreEditor = select( 'core/editor' );
	if (
		coreEditor?.getCurrentPostType &&
		coreEditor.getCurrentPostType() === 'wp_template_part'
	) {
		const post = coreEditor.getCurrentPost?.();
		if ( post?.area === 'header' ) {
			return true;
		}
	}

	const { getBlockParentsByBlockName, getBlock } =
		select( 'core/block-editor' );
	return getBlockParentsByBlockName( clientId, 'core/template-part' ).some(
		( id ) => {
			const a = getBlock( id )?.attributes || {};
			if ( a.area === 'header' || a.tagName === 'header' ) {
				return true;
			}
			// A template may reference the part by slug alone
			// ({"slug":"header"}) — the area then lives on the REGISTERED
			// part, which is how the front end resolves it too. Look the
			// part up so an attribute-less reference still counts here.
			if ( ! a.slug ) {
				return false;
			}
			const theme = a.theme || core?.getCurrentTheme?.()?.stylesheet;
			const part =
				theme &&
				core?.getEntityRecord(
					'postType',
					'wp_template_part',
					`${ theme }//${ a.slug }`
				);
			return part?.area === 'header';
		}
	);
}
