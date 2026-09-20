/**
 * FeatureDescription — a feature's detail copy in the drawer. A plain
 * string is one paragraph; a structured array is paragraphs, headings
 * ({ type: 'heading', content }) and lists ({ type: 'list', items }) in
 * order. Shared by the Extensions and Advanced screens (each used to carry
 * an identical renderDescription). A component rather than a helper in
 * feature-tabs.js so that module stays React-free, as its docblock says.
 *
 * @param {Object}       props
 * @param {string|Array} [props.description] The copy; nothing renders for none.
 */
export const FeatureDescription = ( { description } ) => {
	if ( ! description ) {
		return null;
	}
	if ( typeof description === 'string' ) {
		return <p>{ description }</p>;
	}
	return (
		<>
			{ description.map( ( block, i ) => {
				if ( typeof block === 'string' ) {
					return <p key={ i }>{ block }</p>;
				}
				if ( block.type === 'heading' ) {
					return <h2 key={ i }>{ block.content }</h2>;
				}
				if ( block.type === 'list' ) {
					return (
						<ul key={ i }>
							{ block.items.map( ( item, j ) => (
								<li key={ j }>{ item }</li>
							) ) }
						</ul>
					);
				}
				return null;
			} ) }
		</>
	);
};
