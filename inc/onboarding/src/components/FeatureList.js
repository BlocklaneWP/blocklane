/**
 * FeatureList — a Settings tab's rows (spec
 * the C-settings spec (2026-10-12) §3.3; design spec Amendments F2, J).
 *
 * A tab is a list of sections. A section with a label draws an h2 (muted,
 * heading-sm) over ONE bordered rows card; an unlabeled section is the whole
 * tab's card, marked `data-list` (the `@list` anchor). A section with no
 * rows is not drawn at all, so a search that empties a section takes its
 * heading with it; when no section has a row the tab shows the empty state
 * instead (`data-empty`), in the caller's words. Each card is a list:
 * `role="list"` is restated because Safari drops list semantics from a
 * `list-style: none` list.
 */
import { EmptyState } from '@wordpress/ui';

/**
 * @param {Object} props
 * @param {Array}  props.sections [ { slug, label|null, rows: JSX.Element[] } ].
 * @param {string} props.empty    The empty state's sentence.
 * @return {JSX.Element} The list.
 */
export const FeatureList = ( { sections, empty } ) => {
	const shown = sections.filter( ( s ) => s.rows.length > 0 );
	if ( ! shown.length ) {
		return (
			<EmptyState.Root
				className="blocklane-feature-list__empty"
				data-empty
			>
				<EmptyState.Description>{ empty }</EmptyState.Description>
			</EmptyState.Root>
		);
	}
	return (
		<div className="blocklane-feature-list">
			{ shown.map( ( section ) =>
				section.label ? (
					<section
						key={ section.slug }
						className="blocklane-feature-list__section"
						data-section={ section.slug }
					>
						<h2 className="blocklane-feature-list__title">
							{ section.label }
						</h2>
						{ /* eslint-disable-next-line jsx-a11y/no-redundant-roles -- Safari/VoiceOver drops list semantics under list-style: none. */ }
						<ul
							className="blocklane-feature-list__card"
							role="list"
						>
							{ section.rows }
						</ul>
					</section>
				) : (
					// eslint-disable-next-line jsx-a11y/no-redundant-roles -- Safari/VoiceOver drops list semantics under list-style: none.
					<ul
						key={ section.slug }
						className="blocklane-feature-list__card"
						role="list"
						data-list
					>
						{ section.rows }
					</ul>
				)
			) }
		</div>
	);
};

export default FeatureList;
