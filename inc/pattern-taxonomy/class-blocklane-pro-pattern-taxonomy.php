<?php
/**
 * Pattern_Taxonomy: the one door to what a pattern IS (spec
 * 2026-10-17-pattern-catalog-browse). See the class docblock.
 *
 * The header stays short on purpose: Plugin Check's direct-access check
 * reads a namespaced file's guard only within its first 50 lines (its AST
 * pass never looks inside the namespace), so a long header hides the guard.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pattern_Taxonomy: the one door to what a pattern IS.
 *
 * Spec 2026-10-17-pattern-catalog-browse (§2, §3.2–§3.5, §6). A pattern
 * declares its content type exactly once, in untranslated data: its
 * structure (a template-part area, a coming-soon or maintenance template,
 * a full page), or one TYPE TERM in its keywords. This class resolves that
 * declaration against one file, taxonomy.json, beside it, and every surface
 * that groups, orders, labels or searches patterns derives from the answer:
 * nothing parses a title or any other copy back into data.
 *
 * WHAT IT ANSWERS.
 *   classify()       a registry entry → its group, entry, slot, rank, lead,
 *                    area, source and descriptors, or a typed failure
 *                    (blocklane_pattern_ambiguous, _unclassified, or the
 *                    taxonomy's own load failure).
 *   area_of()        the template-part area from blockTypes.
 *   is_full_page()   a page's whole content or a whole template; true
 *                    exactly when classify() puts the pattern in the
 *                    `pages` group (structural steps 2 and 3).
 *   normalize()      text → { phrase, term, kind }: one fold, one stemmer,
 *                    one synonym index. Its first two steps (phrase()) have
 *                    a JavaScript twin, src/normalize.js, held to this one
 *                    by normalize-vectors.json (spec §3.4).
 *   search_forms()   every surface form of a classification, for a search
 *                    to expand into.
 *   assembly_order() picks → page order (spec §6), the one rule a scripted
 *                    build and the AI's assemble_order both call.
 *   labels()         the translated labels (taxonomy-i18n.php, generated).
 *   family_of(), families(), member_name(), renamed(), op_problems(),
 *   op_leaves()      pattern families (spec 2026-10-17-pattern-families
 *                    §3.3, §3.5): one catalog card per layout, its derived
 *                    members as layout ops, its header and footer siblings,
 *                    the retired names; and the op grammar Pro's recipes
 *                    share. family_labeled() and families_labeled() are the
 *                    translated forms the public API returns.
 *
 * PURE. Nothing here calls WordPress except labels() (translation and the
 * catalog) and assembly_order()'s default resolver (the pattern registry),
 * so bin/ checks include this file without WordPress: they define ABSPATH and
 * a WP_Error stand-in, and normalize() needs neither (failure row N17). In
 * particular the fold is a FIXED table in taxonomy.json, never
 * remove_accents(), which reads the site's locale (spec §13 F12).
 *
 * TYPED FAILURES. load(), classify(), normalize(), search_forms(),
 * assembly_order() and labels() return WP_Error on failure, never '' or [].
 * normalize()'s `term: null` is a documented value ("not a vocabulary
 * term"), not a failure.
 *
 * A core path, carried by both editions (decision D8): the free build's
 * bundled-pattern checks and the public API read it as Pro's AI does. It is
 * data and pure functions, with no UI.
 */
final class Pattern_Taxonomy {

	/** The data file, beside this class. */
	public const FILE = 'taxonomy.json';

	/** The generated translations file, beside this class. */
	public const I18N_FILE = 'taxonomy-i18n.php';

	/** The one schema version this class reads. */
	public const VERSION = 1;

	/**
	 * Where each slot goes on a page (spec §6): header first, footer last,
	 * sections between in story order. Slots the spec leaves to the
	 * consumer (overlay, panel, component, page, template) share a
	 * neighbor's rank, and the group and entry order breaks the tie: an
	 * overlay and a Mega Menu panel go with the header that opens them.
	 */
	public const SLOT_RANK = array(
		'header'    => 0,
		'overlay'   => 0,
		'panel'     => 0,
		'opener'    => 1,
		'body'      => 2,
		'component' => 2,
		'footer'    => 3,
		'page'      => 4,
		'template'  => 4,
	);

	/**
	 * The template-part areas a pattern can be for: core's three, and the
	 * `menu` area a Mega Menu panel lives in (Pro registers the area; the
	 * taxonomy only reads the name).
	 */
	public const AREAS = array( 'header', 'footer', 'navigation-overlay', 'menu' );

	/** The most picks one assembly_order() call takes. */
	public const ASSEMBLY_MAX = 50;

	/** The longest text normalize() reads, in code points (spec §3.4 step 1). */
	public const CAP = 200;

	/** The three kinds of vocabulary term normalize() can answer. */
	public const KINDS = array( 'entry', 'descriptor', 'feature' );

	/** The FAMILY# rules family_problems() reports (any other code is TAXONOMY#). */
	public const FAMILY_RULES = array( 'value', 'graph', 'name', 'op', 'sibling', 'renamed' );

	/** The deepest selector the op grammar takes (spec 2026-10-17-pattern-families §3.5). */
	public const DEPTH_MAX = 8;

	/** A block type name, as the op grammar reads it. */
	public const BLOCK_NAME = '#^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$#D';

	/** A layout_ops preset grammar: preset:<kind> admits var:preset|<kind>|<slug>. */
	public const PRESET_GRAMMAR = '/^preset:[a-z]+(?:-[a-z]+)*$/D';

	/**
	 * The shipped taxonomy, compiled once per request (or its failure).
	 *
	 * @var array<string, mixed>|\WP_Error|null
	 */
	private static array|\WP_Error|null $loaded = null;

	/**
	 * The shipped taxonomy, compiled: read, validated and indexed once per
	 * request. A file that is missing or not JSON is
	 * blocklane_taxonomy_unreadable; one that breaks a rule of the schema
	 * is blocklane_taxonomy_invalid, its data carrying every problem.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function load(): array|\WP_Error {
		if ( null === self::$loaded ) {
			self::$loaded = self::load_file( __DIR__ . '/' . self::FILE );
		}
		return self::$loaded;
	}

	/**
	 * Read, validate and compile one taxonomy file, uncached: the door
	 * load() goes through, and the one bin/generate-taxonomy.php and the
	 * battery use for fixtures.
	 *
	 * @param string $file An absolute path to a taxonomy JSON file.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function load_file( string $file ): array|\WP_Error {
		$bytes = is_readable( $file ) ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a data file shipped in this plugin, read from disk; never a URL.
		if ( false === $bytes ) {
			return new \WP_Error( 'blocklane_taxonomy_unreadable', 'The pattern taxonomy file cannot be read.', array( 'file' => basename( $file ) ) );
		}
		$data = json_decode( $bytes, true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'blocklane_taxonomy_unreadable', 'The pattern taxonomy file is not a JSON object.', array( 'file' => basename( $file ) ) );
		}
		return self::compile( $data );
	}

	/**
	 * Validate decoded taxonomy data and compile it: the tables
	 * normalize() reads, the entries in order, the type terms, the
	 * category fallbacks and the synonym index.
	 *
	 * @param array<mixed> $data Decoded taxonomy.json.
	 * @return array<string, mixed>|\WP_Error blocklane_taxonomy_invalid with data['problems'] (list of "<RULE>: <message>").
	 */
	public static function compile( array $data ): array|\WP_Error {
		$problems = self::problems( $data );
		if ( array() !== $problems ) {
			return new \WP_Error(
				'blocklane_taxonomy_invalid',
				'The pattern taxonomy file breaks a rule of its schema.',
				array( 'problems' => array_map( static fn( array $p ): string => $p[0] . ': ' . $p[1], $problems ) )
			);
		}
		return self::build( $data );
	}

	/**
	 * Every rule the data breaks, as [rule, message] pairs; an empty list
	 * is valid. The rules are the TAXONOMY# family bin/generate-taxonomy.php
	 * prints (spec §4, §13 F4, Garrett's D3): the shape, duplicate ids,
	 * non-ASCII vocabulary, a surface form two terms claim, a fallback
	 * category two entries claim, a dead exception, irregular or synonym, a
	 * type term core's wp_parse_list() would split, a structural entry with
	 * a type, a feature naming a core block, a catalog-unit feature with a
	 * label of its own, and a retired slug that collides or points nowhere.
	 * The one rule that needs the plugin's manifest (a unit that is not
	 * cataloged) is the generator's.
	 *
	 * @param mixed $data Decoded taxonomy.json.
	 * @return list<array{0: string, 1: string}>
	 */
	public static function problems( mixed $data ): array {
		$out  = array();
		$fail = static function ( string $rule, string $message ) use ( &$out ): void {
			$out[] = array( 'TAXONOMY#' . $rule, $message );
		};
		if ( ! is_array( $data ) ) {
			$fail( 'shape', 'the taxonomy is not an object' );
			return $out;
		}
		if ( self::VERSION !== ( $data['version'] ?? null ) ) {
			$fail( 'shape', 'version is not ' . self::VERSION );
		}
		$kw = $data['keywords'] ?? null;
		if ( ! is_array( $kw ) || ! is_int( $kw['max'] ?? null ) || ! is_int( $kw['types_max'] ?? null ) || $kw['max'] < 1 || $kw['types_max'] < 0 || $kw['types_max'] > $kw['max'] ) {
			$fail( 'shape', 'keywords needs integers max >= 1 and 0 <= types_max <= max' );
		}
		$norm = $data['normalizer'] ?? null;
		if ( ! is_array( $norm ) || ! is_int( $norm['min_stem'] ?? null ) || $norm['min_stem'] < 1 || ! self::is_list_of_strings( $norm['exceptions'] ?? null ) || ! self::is_map_of_strings( $norm['irregular'] ?? null ) || ! self::is_map_of_strings( $norm['fold'] ?? null ) ) {
			$fail( 'shape', 'normalizer needs min_stem >= 1, exceptions (strings), irregular and fold (string maps)' );
			return $out; // Nothing below can normalize without the tables.
		}
		$tables = self::tables_of( $data );
		foreach ( $tables['fold'] as $from => $to ) {
			$from = (string) $from;
			if ( 1 !== mb_strlen( $from, 'UTF-8' ) || 1 === preg_match( '/^[\x00-\x7F]$/', $from ) ) {
				$fail( 'fold', 'fold key ' . json_encode( $from ) . ' is not one non-ASCII character' );
			}
			foreach ( mb_str_split( $to, 1, 'UTF-8' ) as $c ) {
				if ( isset( $tables['fold'][ $c ] ) || 1 === preg_match( '/[A-Z]/', $c ) ) {
					$fail( 'fold', 'fold value for ' . json_encode( $from ) . ' holds a character the table or the lowercasing would change again' );
					break;
				}
			}
		}
		// A dead exception or irregular: the rules alone already give its answer.
		$bare = array( 'exceptions' => array(), 'irregular' => array() ) + $tables;
		foreach ( $tables['exceptions'] as $word ) {
			if ( ! self::ascii_word( $word ) ) {
				$fail( 'ascii', 'exception ' . json_encode( $word ) . ' is not a lowercase ASCII word' );
			} elseif ( self::stem( $word, $bare ) === $word ) {
				$fail( 'dead', "exception \"{$word}\" is a word the stemmer would not change" );
			}
		}
		foreach ( $tables['irregular'] as $word => $to ) {
			$word = (string) $word;
			if ( ! self::ascii_word( $word ) || ! self::ascii_word( $to ) ) {
				$fail( 'ascii', "irregular \"{$word}\" => \"{$to}\" is not lowercase ASCII words" );
			} elseif ( self::stem( $word, $bare ) === $to ) {
				$fail( 'dead', "irregular \"{$word}\" maps to \"{$to}\", which the stemmer already gives" );
			}
		}

		// Groups and entries.
		$groups = $data['groups'] ?? null;
		if ( ! is_array( $groups ) || array() === $groups || ! array_is_list( $groups ) ) {
			$fail( 'shape', 'groups is not a non-empty list' );
			return $out;
		}
		$ids       = array();
		$entry_ids = array();
		$types     = array();
		$fallback  = array();
		$forms     = array(); // phrase => [ "kind:term" => surface ].
		$claim     = static function ( string $kind, string $term, string $surface ) use ( &$forms, $tables ): void {
			$forms[ self::phrase( $surface, $tables ) ][ "{$kind}:{$term}" ][] = $surface;
		};
		foreach ( $groups as $g => $group ) {
			if ( ! is_array( $group ) || ! self::is_slug( $group['id'] ?? null ) || ! is_string( $group['label'] ?? null ) || ! is_array( $group['entries'] ?? null ) || array() === $group['entries'] ) {
				$fail( 'shape', "group {$g} needs a slug id, a label and a non-empty entries list" );
				continue;
			}
			self::vocabulary( $fail, "group {$group['id']} label", $group['label'] );
			if ( isset( $ids[ 'group:' . $group['id'] ] ) ) {
				$fail( 'duplicate', "group id \"{$group['id']}\" appears twice" );
			}
			$ids[ 'group:' . $group['id'] ] = true;
			foreach ( $group['entries'] as $e => $entry ) {
				if ( ! is_array( $entry ) || ! self::is_slug( $entry['id'] ?? null ) || ! is_string( $entry['label'] ?? null ) ) {
					$fail( 'shape', "entry {$e} of group {$group['id']} needs a slug id and a label (an abbreviated entry is the spec's shorthand, never the file's)" );
					continue;
				}
				$id = $entry['id'];
				if ( isset( $entry_ids[ $id ] ) ) {
					$fail( 'duplicate', "entry id \"{$id}\" appears twice" );
				}
				$entry_ids[ $id ] = true;
				$leads            = isset( $entry['leads'] ) ? $entry['leads'] : ( isset( $entry['lead'] ) ? array( $entry['lead'] ) : null );
				if ( ! self::is_list_of_strings( $leads ) || array() === $leads || ( isset( $entry['lead'] ) && isset( $entry['leads'] ) ) ) {
					$fail( 'shape', "entry {$id} needs exactly one of lead (a string) or leads (a list)" );
					$leads = array();
				}
				if ( ! isset( self::SLOT_RANK[ $entry['slot'] ?? '' ] ) ) {
					$fail( 'shape', "entry {$id} has slot " . json_encode( $entry['slot'] ?? null ) . ', not one of ' . implode( ', ', array_keys( self::SLOT_RANK ) ) );
				}
				$synonyms = $entry['synonyms'] ?? array();
				if ( ! self::is_list_of_strings( $synonyms ) ) {
					$fail( 'shape', "entry {$id} synonyms is not a list of strings" );
					$synonyms = array();
				}
				$structural = $entry['structural'] ?? null;
				if ( null !== $structural ) {
					if ( isset( $entry['type'] ) ) {
						$fail( 'structural', "structural entry {$id} carries a type term; its structure is its declaration (D16)" );
					}
					if ( isset( $entry['categories'] ) ) {
						$fail( 'structural', "structural entry {$id} carries fallback categories; only its structure classifies into it" );
					}
					$area  = $structural['area'] ?? null;
					$full  = $structural['full_page'] ?? null;
					$tmpl  = $structural['template_types'] ?? null;
					$kinds = ( null !== $area ) + ( null !== $full ) + ( null !== $tmpl );
					if ( ! is_array( $structural ) || 1 !== $kinds || ( null !== $area && ! in_array( $area, self::AREAS, true ) ) || ( null !== $full && true !== $full ) || ( null !== $tmpl && ( ! self::is_list_of_strings( $tmpl ) || array() === $tmpl ) ) ) {
						$fail( 'shape', "structural entry {$id} needs exactly one of area (" . implode( '|', self::AREAS ) . '), full_page: true or template_types (a list)' );
					} elseif ( null !== $tmpl && isset( $entry['leads'] ) && count( $tmpl ) !== count( $leads ) ) {
						$fail( 'shape', "structural entry {$id} has leads that do not pair one to one with its template_types" );
					}
				} else {
					$type = $entry['type'] ?? null;
					if ( ! is_string( $type ) || '' === $type ) {
						$fail( 'shape', "entry {$id} is not structural and has no type term" );
					} else {
						self::vocabulary( $fail, "entry {$id} type", $type );
						if ( 1 === preg_match( '/[\s,]/', $type ) ) {
							$fail( 'type-space', "entry {$id} type \"{$type}\" holds a space or a comma, so core's wp_parse_list() splits a theme's Keywords header there; hyphenate it" );
						}
						if ( isset( $types[ $type ] ) ) {
							$fail( 'duplicate', "type term \"{$type}\" belongs to {$types[ $type ]} and {$id}" );
						}
						$types[ $type ] = $id;
						$claim( 'entry', $id, $type );
					}
					$cats = $entry['categories'] ?? array();
					if ( ! self::is_list_of_strings( $cats ) ) {
						$fail( 'shape', "entry {$id} categories is not a list of strings" );
						$cats = array();
					}
					foreach ( $cats as $cat ) {
						if ( isset( $fallback[ $cat ] ) ) {
							$fail( 'category', "fallback category \"{$cat}\" is claimed by {$fallback[ $cat ]} and {$id}" );
						}
						$fallback[ $cat ] = $id;
					}
				}
				self::vocabulary( $fail, "entry {$id} label", $entry['label'] );
				foreach ( $leads as $lead ) {
					self::vocabulary( $fail, "entry {$id} lead", $lead );
					$claim( 'entry', $id, $lead );
				}
				$claim( 'entry', $id, $id );
				$claim( 'entry', $id, $entry['label'] );
				foreach ( $synonyms as $synonym ) {
					self::vocabulary( $fail, "entry {$id} synonym", $synonym );
					$claim( 'entry', $id, $synonym );
				}
			}
		}

		// Descriptors.
		$descriptors = $data['descriptors'] ?? null;
		if ( ! is_array( $descriptors ) || ! array_is_list( $descriptors ) ) {
			$fail( 'shape', 'descriptors is not a list' );
			$descriptors = array();
		}
		foreach ( $descriptors as $d => $descriptor ) {
			$term     = is_string( $descriptor ) ? $descriptor : ( is_array( $descriptor ) ? ( $descriptor['term'] ?? null ) : null );
			$synonyms = is_array( $descriptor ) ? ( $descriptor['synonyms'] ?? array() ) : array();
			if ( ! is_string( $term ) || '' === $term || ! self::is_list_of_strings( $synonyms ) ) {
				$fail( 'shape', "descriptor {$d} needs a term (a string) and, optionally, synonyms (strings)" );
				continue;
			}
			self::vocabulary( $fail, 'descriptor', $term );
			if ( 1 === preg_match( '/[\s,]/', $term ) ) {
				$fail( 'type-space', "descriptor \"{$term}\" holds a space or a comma, so core's wp_parse_list() splits it; hyphenate it" );
			}
			if ( isset( $types[ $term ] ) || isset( $ids[ 'descriptor:' . $term ] ) ) {
				$fail( 'duplicate', "descriptor \"{$term}\" is already a type term or a descriptor" );
			}
			$ids[ 'descriptor:' . $term ] = true;
			$claim( 'descriptor', $term, $term );
			foreach ( $synonyms as $synonym ) {
				self::vocabulary( $fail, "descriptor {$term} synonym", $synonym );
				$claim( 'descriptor', $term, $synonym );
			}
		}

		// Features (Garrett's D3: only what Blocklane provides; §13 F11).
		$features = $data['features'] ?? null;
		if ( ! is_array( $features ) || ! array_is_list( $features ) ) {
			$fail( 'shape', 'features is not a list' );
			$features = array();
		}
		foreach ( $features as $f => $feature ) {
			if ( ! is_array( $feature ) || ! self::is_slug( $feature['id'] ?? null ) ) {
				$fail( 'shape', "feature {$f} needs a slug id" );
				continue;
			}
			$id     = $feature['id'];
			$blocks = $feature['blocks'] ?? array();
			$effect = $feature['effect'] ?? null;
			if ( isset( $ids[ 'feature:' . $id ] ) ) {
				$fail( 'duplicate', "feature id \"{$id}\" appears twice" );
			}
			$ids[ 'feature:' . $id ] = true;
			if ( ! self::is_list_of_strings( $blocks ) || ( array() === $blocks && ! is_string( $effect ) ) ) {
				$fail( 'shape', "feature {$id} needs blocks (a list of block names) or an effect" );
				$blocks = array();
			}
			foreach ( $blocks as $block ) {
				if ( str_starts_with( $block, 'core/' ) ) {
					$fail( 'feature-core', "feature {$id} names {$block}: a feature is something Blocklane provides, never a core block (D3)" );
				}
			}
			$unit = $feature['unit'] ?? null;
			if ( null !== $unit && ( ! is_string( $unit ) || 1 !== preg_match( '/^(?:module|extension|block):[a-z0-9]+(?:-[a-z0-9]+)*$/', $unit ) ) ) {
				$fail( 'shape', "feature {$id} unit is not a catalog unit id" );
			}
			if ( null !== $unit && isset( $feature['label'] ) ) {
				$fail( 'feature-label', "feature {$id} is catalog unit {$unit} and carries a label of its own; its label is the catalog's (F11)" );
			}
			if ( null === $unit && ! is_string( $feature['label'] ?? null ) ) {
				$fail( 'feature-label', "feature {$id} is no catalog unit and has no label" );
			}
			$synonyms = $feature['synonyms'] ?? array();
			if ( ! self::is_list_of_strings( $synonyms ) ) {
				$fail( 'shape', "feature {$id} synonyms is not a list of strings" );
				$synonyms = array();
			}
			$claim( 'feature', $id, $id );
			if ( is_string( $feature['label'] ?? null ) ) {
				self::vocabulary( $fail, "feature {$id} label", $feature['label'] );
				$claim( 'feature', $id, $feature['label'] );
			}
			foreach ( $synonyms as $synonym ) {
				self::vocabulary( $fail, "feature {$id} synonym", $synonym );
				$claim( 'feature', $id, $synonym );
			}
		}

		// Parts (inner List View names, spec §3.10).
		$parts = $data['parts'] ?? null;
		if ( ! self::is_list_of_strings( $parts ) || count( array_unique( $parts ) ) !== count( $parts ) ) {
			$fail( 'shape', 'parts is not a list of distinct strings' );
		} else {
			foreach ( $parts as $part ) {
				if ( 1 !== preg_match( '/^[a-z]+(?:-[a-z]+)*$/', $part ) ) {
					$fail( 'ascii', "part \"{$part}\" is not a lowercase ASCII word" );
				}
			}
		}

		// One surface form, one term; and no synonym the term already has.
		foreach ( $forms as $phrase => $claims ) {
			if ( '' === $phrase ) {
				$fail( 'shape', 'a surface form normalizes to nothing: ' . json_encode( array_merge( ...array_values( $claims ) ) ) );
				continue;
			}
			if ( count( $claims ) > 1 ) {
				$fail( 'collision', "\"{$phrase}\" is claimed by " . implode( ' and ', array_keys( $claims ) ) . ' (' . implode( ', ', array_map( static fn( array $s ): string => implode( ', ', $s ), $claims ) ) . ')' );
			}
		}
		foreach ( $groups as $group ) {
			foreach ( is_array( $group['entries'] ?? null ) ? $group['entries'] : array() as $entry ) {
				self::redundant( $fail, 'entry', (string) ( $entry['id'] ?? '' ), is_array( $entry['synonyms'] ?? null ) ? $entry['synonyms'] : array(), array_merge( array( (string) ( $entry['id'] ?? '' ), (string) ( $entry['label'] ?? '' ), (string) ( $entry['type'] ?? '' ) ), isset( $entry['leads'] ) && is_array( $entry['leads'] ) ? $entry['leads'] : array( (string) ( $entry['lead'] ?? '' ) ) ), $tables );
			}
		}
		foreach ( $descriptors as $descriptor ) {
			if ( is_array( $descriptor ) && is_string( $descriptor['term'] ?? null ) ) {
				self::redundant( $fail, 'descriptor', $descriptor['term'], is_array( $descriptor['synonyms'] ?? null ) ? $descriptor['synonyms'] : array(), array( $descriptor['term'] ), $tables );
			}
		}
		foreach ( $features as $feature ) {
			if ( is_array( $feature ) && is_string( $feature['id'] ?? null ) ) {
				self::redundant( $fail, 'feature', $feature['id'], is_array( $feature['synonyms'] ?? null ) ? $feature['synonyms'] : array(), array( $feature['id'], (string) ( $feature['label'] ?? '' ) ), $tables );
			}
		}

		// Retired slugs (§13 F4): never a live entry or form; always somewhere to go.
		$retired = $data['retired_slugs'] ?? null;
		if ( ! self::is_map_of_strings( $retired ) ) {
			$fail( 'shape', 'retired_slugs is not a map of strings' );
		} else {
			foreach ( $retired as $slug => $to ) {
				$slug = (string) $slug;
				if ( ! self::is_slug( $slug ) ) {
					$fail( 'retired', "retired slug \"{$slug}\" is not a page slug" );
				} elseif ( isset( $entry_ids[ $slug ] ) || isset( $forms[ self::phrase( $slug, $tables ) ] ) ) {
					$fail( 'retired', "retired slug \"{$slug}\" is a live entry id or surface form, so a redirect would hide a page" );
				}
				if ( '*' !== $to && ! isset( $entry_ids[ $to ] ) ) {
					$fail( 'retired', "retired slug \"{$slug}\" points at \"{$to}\", which is neither an entry id nor *" );
				}
			}
		}

		// Reserved for the roadmap (§6): empty, or the documented shape.
		$page_types = $data['page_types'] ?? null;
		if ( ! is_array( $page_types ) || ! array_is_list( $page_types ) ) {
			$fail( 'shape', 'page_types is not a list' );
		} else {
			foreach ( $page_types as $p => $page ) {
				if ( ! is_array( $page ) || ! self::is_slug( $page['id'] ?? null ) || ! is_string( $page['label'] ?? null ) || ! self::is_list_of_strings( $page['sections'] ?? null ) || array() !== array_diff( $page['sections'], array_keys( $entry_ids ) ) ) {
					$fail( 'shape', "page type {$p} needs { id, label, sections: [entry ids] }" );
				}
			}
		}
		return array_merge( $out, self::family_problems( $data ) );
	}

	/**
	 * The family rules (spec 2026-10-17-pattern-families §3.3, §14 G14): the
	 * shape of layout_values, layout_ops, families and renamed (TAXONOMY#shape)
	 * and the FAMILY# rules over them:
	 *   FAMILY#value    an unknown or duplicate layout value, a base_value an
	 *                   option repeats, a family with no option;
	 *   FAMILY#graph    a duplicate family id, a pattern in two families, a
	 *                   base that is another family's member or sibling;
	 *   FAMILY#name     a base or sibling whose slug is no single-hyphen slug
	 *                   (a `--` name is a computed member, never authored);
	 *   FAMILY#op       an op outside the grammar (op_problems()), an op that
	 *                   does not open with a typed root step, an attribute
	 *                   path outside layout_ops for its block, a value outside
	 *                   its enum or preset grammar;
	 *   FAMILY#sibling  a sibling `reason` other than "structure" (whether a
	 *                   sibling is a header or footer is judged on the site,
	 *                   by the theme's families gate);
	 *   FAMILY#renamed  a retired name that is a live family pattern, or a
	 *                   target that is neither a family and one of its values
	 *                   nor a pattern that is not itself retired.
	 * Pure; called by problems(), so load() and the generator agree.
	 *
	 * @param array<mixed> $data Decoded taxonomy.json.
	 * @return list<array{0: string, 1: string}>
	 */
	private static function family_problems( array $data ): array {
		$out  = array();
		$fail = static function ( string $rule, string $message ) use ( &$out ): void {
			$out[] = array( ( in_array( $rule, self::FAMILY_RULES, true ) ? 'FAMILY#' : 'TAXONOMY#' ) . $rule, $message );
		};

		// Layout values: the closed set of option ids, each labeled once.
		$values = array();
		$list   = $data['layout_values'] ?? null;
		if ( ! is_array( $list ) || array() === $list || ! array_is_list( $list ) ) {
			$fail( 'shape', 'layout_values is not a non-empty list' );
			$list = array();
		}
		foreach ( $list as $i => $value ) {
			if ( ! is_array( $value ) || ! self::is_slug( $value['id'] ?? null ) || ! is_string( $value['label'] ?? null ) ) {
				$fail( 'shape', "layout value {$i} needs a slug id and a label" );
				continue;
			}
			self::vocabulary( $fail, "layout value {$value['id']} label", $value['label'] );
			if ( isset( $values[ $value['id'] ] ) ) {
				$fail( 'value', "layout value \"{$value['id']}\" appears twice" );
			}
			$values[ $value['id'] ] = true;
		}

		// Layout ops: block => path => enum list or preset grammar, plus move.
		$ops_table = $data['layout_ops'] ?? null;
		if ( ! is_array( $ops_table ) || array_is_list( $ops_table ) ) {
			$fail( 'shape', 'layout_ops is not an object of blocks' );
			$ops_table = array();
		}
		foreach ( $ops_table as $block => $paths ) {
			$block = (string) $block;
			if ( 'move' === $block ) {
				if ( ! is_bool( $paths ) ) {
					$fail( 'shape', 'layout_ops.move is not a boolean' );
				}
				continue;
			}
			if ( 1 !== preg_match( self::BLOCK_NAME, $block ) || ! is_array( $paths ) || array() === $paths || array_is_list( $paths ) ) {
				$fail( 'shape', "layout_ops {$block} is not a block name with an object of attribute paths" );
				continue;
			}
			foreach ( $paths as $path => $allowed ) {
				$path = (string) $path;
				$enum = is_array( $allowed ) && array() !== $allowed && array_is_list( $allowed ) && array() === array_filter( $allowed, static fn( mixed $v ): bool => ! is_string( $v ) && ! is_bool( $v ) );
				if ( 1 !== preg_match( '/^[a-zA-Z]+(?:\.[a-zA-Z]+)*$/', $path ) || ! ( $enum || ( is_string( $allowed ) && 1 === preg_match( self::PRESET_GRAMMAR, $allowed ) ) ) ) {
					$fail( 'shape', "layout_ops {$block}.{$path} admits neither a list of enum values nor preset:<kind>" );
				}
			}
		}

		// Families.
		$families = $data['families'] ?? null;
		if ( ! is_array( $families ) || ! array_is_list( $families ) ) {
			$fail( 'shape', 'families is not a list' );
			$families = array();
		}
		$ids     = array();
		$owner   = array(); // pattern name => family id.
		$options = array(); // family id => [ value => true ].
		$claim   = static function ( string $name, string $id, string $as ) use ( &$owner, $fail ): void {
			if ( isset( $owner[ $name ] ) ) {
				$fail( 'graph', "{$name} is in family {$owner[ $name ]} and, as {$as}, in family {$id}" );
				return;
			}
			$owner[ $name ] = $id;
		};
		foreach ( $families as $f => $family ) {
			if ( ! is_array( $family ) || ! self::is_slug( $family['id'] ?? null ) || ! is_string( $family['base'] ?? null ) || ! is_string( $family['base_value'] ?? null ) ) {
				$fail( 'shape', "family {$f} needs a slug id, a base and a base_value" );
				continue;
			}
			$id = $family['id'];
			if ( isset( $ids[ $id ] ) ) {
				$fail( 'graph', "family id \"{$id}\" appears twice" );
			}
			$ids[ $id ] = true;
			$base       = $family['base'];
			if ( null === self::slug_of( $base ) ) {
				$fail( 'name', "family {$id} base \"{$base}\" is not blocklane/<slug> with single hyphens; a `--` name is a computed member" );
			}
			$claim( $base, $id, 'its base' );
			if ( ! isset( $values[ $family['base_value'] ] ) ) {
				$fail( 'value', "family {$id} base_value \"{$family['base_value']}\" is not a layout value" );
			}
			$seen     = array( $family['base_value'] => 'the base' );
			$derived  = $family['derived'] ?? array();
			$siblings = $family['siblings'] ?? array();
			if ( ! is_array( $derived ) || ! array_is_list( $derived ) || ! is_array( $siblings ) || ! array_is_list( $siblings ) ) {
				$fail( 'shape', "family {$id} derived and siblings are lists when present" );
				continue;
			}
			if ( array() === $derived && array() === $siblings ) {
				$fail( 'value', "family {$id} has no option besides its base; a single layout is no family" );
			}
			$option = static function ( mixed $value, string $what ) use ( $id, $values, &$seen, $fail ): ?string {
				if ( ! is_string( $value ) || ! isset( $values[ $value ] ) ) {
					$fail( 'value', "family {$id} {$what} has value " . json_encode( $value ) . ', which is not a layout value' );
					return null;
				}
				if ( isset( $seen[ $value ] ) ) {
					$fail( 'value', "family {$id} gives value \"{$value}\" to {$seen[ $value ]} and to {$what}" );
					return null;
				}
				$seen[ $value ] = $what;
				return $value;
			};
			foreach ( $derived as $d => $member ) {
				if ( ! is_array( $member ) || ! is_array( $member['ops'] ?? null ) || array() === $member['ops'] || ! array_is_list( $member['ops'] ) ) {
					$fail( 'shape', "family {$id} derived {$d} needs a value and a non-empty ops list" );
					continue;
				}
				$value = $option( $member['value'] ?? null, "derived {$d}" );
				if ( null !== $value && null !== self::slug_of( $base ) ) {
					$claim( self::member_name( $base, $value ), $id, "derived {$value}" );
				}
				foreach ( $member['ops'] as $o => $op ) {
					foreach ( self::layout_op_problems( $op, $ops_table ) as $message ) {
						$fail( 'op', "family {$id} derived " . ( is_string( $value ) ? $value : $d ) . " op {$o}: {$message}" );
					}
				}
			}
			foreach ( $siblings as $s => $sibling ) {
				if ( ! is_array( $sibling ) || ! is_string( $sibling['pattern'] ?? null ) ) {
					$fail( 'shape', "family {$id} sibling {$s} needs a pattern name and a value" );
					continue;
				}
				if ( null === self::slug_of( $sibling['pattern'] ) ) {
					$fail( 'name', "family {$id} sibling \"{$sibling['pattern']}\" is not blocklane/<slug> with single hyphens" );
				}
				if ( array_key_exists( 'reason', $sibling ) && 'structure' !== $sibling['reason'] ) {
					$fail( 'sibling', "family {$id} sibling {$sibling['pattern']} gives reason " . json_encode( $sibling['reason'] ) . '; the one reason a sibling may be other than a header or footer is "structure" (D4)' );
				}
				$option( $sibling['value'] ?? null, "sibling {$sibling['pattern']}" );
				$claim( $sibling['pattern'], $id, 'a sibling' );
			}
			$options[ $id ] = $seen;
		}

		// Renamed: retired names point at a family's layout or the pattern they folded into.
		$renamed = $data['renamed'] ?? null;
		if ( ! is_array( $renamed ) || ( array() !== $renamed && array_is_list( $renamed ) ) ) {
			$fail( 'shape', 'renamed is not an object of retired pattern names' );
			$renamed = array();
		}
		foreach ( $renamed as $name => $to ) {
			$name = (string) $name;
			if ( null === self::slug_of( $name ) ) {
				$fail( 'renamed', "retired name \"{$name}\" is not blocklane/<slug>" );
			}
			if ( isset( $owner[ $name ] ) ) {
				$fail( 'renamed', "retired name {$name} is a live pattern of family {$owner[ $name ]}" );
			}
			$family = is_array( $to ) ? ( $to['family'] ?? null ) : null;
			$layout = is_array( $to ) ? ( $to['layout'] ?? null ) : null;
			$folded = is_array( $to ) ? ( $to['pattern'] ?? null ) : null;
			$keys   = is_array( $to ) ? array_keys( $to ) : array();
			sort( $keys );
			if ( array( 'family', 'layout' ) === $keys ) {
				if ( ! is_string( $family ) || ! isset( $options[ $family ] ) || ! is_string( $layout ) || ! isset( $options[ $family ][ $layout ] ) ) {
					$fail( 'renamed', "retired name {$name} points at " . json_encode( $to ) . ', which is not a family and one of its layout values' );
				}
			} elseif ( array( 'pattern' ) === $keys ) {
				if ( ! is_string( $folded ) || null === self::slug_of( $folded ) || isset( $renamed[ $folded ] ) || $folded === $name ) {
					$fail( 'renamed', "retired name {$name} folds into " . json_encode( $folded ) . ', which is not a live blocklane/<slug> pattern' );
				}
			} else {
				$fail( 'renamed', "retired name {$name} points at " . json_encode( $to ) . '; a target is { family, layout } or { pattern }' );
			}
		}
		return $out;
	}

	/**
	 * Why one op is outside the op grammar (spec §3.5), as messages; an empty
	 * list is a well-formed op. The grammar is shared by families (layout
	 * ops) and Pro's recipes:
	 *   at     a list of at most DEPTH_MAX steps from the pattern's single top
	 *          block: a first step { type } alone names (and asserts) the top
	 *          block; { type, nth: n >= 0 } is the n-th inner block of that
	 *          type; { type, each: true } is every inner block of that type;
	 *          at most one `each`;
	 *   set    a non-empty object deep-merged into the attributes (objects
	 *          merge, scalars and lists replace, null removes);
	 *   cycle  only with `each`: attribute => a non-empty list, match i gets
	 *          values[i mod n];
	 *   move   layout only, alone: { at: the container, from, to }.
	 *
	 * @param mixed $op   One op.
	 * @param bool  $move Whether `move` is admitted (layout ops only).
	 * @return list<string>
	 */
	public static function op_problems( mixed $op, bool $move = false ): array {
		if ( ! is_array( $op ) || array_is_list( $op ) ) {
			return array( 'an op is an object' );
		}
		$out   = array();
		$extra = array_diff( array_keys( $op ), array( 'at', 'set', 'cycle', 'from', 'to' ) );
		if ( array() !== $extra ) {
			$out[] = 'unknown keys ' . implode( ', ', $extra );
		}
		$at = $op['at'] ?? null;
		if ( ! is_array( $at ) || ! array_is_list( $at ) || count( $at ) > self::DEPTH_MAX ) {
			return array_merge( $out, array( 'at is a list of at most ' . self::DEPTH_MAX . ' steps' ) );
		}
		$each = 0;
		foreach ( $at as $i => $step ) {
			if ( ! is_array( $step ) || ! is_string( $step['type'] ?? null ) || 1 !== preg_match( self::BLOCK_NAME, $step['type'] ) ) {
				$out[] = "step {$i} needs a block type";
				continue;
			}
			$keys = array_keys( $step );
			sort( $keys );
			if ( array( 'type' ) === $keys ) {
				if ( 0 !== $i ) {
					$out[] = "step {$i} has neither nth nor each; only the first step may name the top block alone";
				}
			} elseif ( array( 'nth', 'type' ) === $keys ) {
				if ( ! is_int( $step['nth'] ) || $step['nth'] < 0 ) {
					$out[] = "step {$i} nth is not an integer of 0 or more";
				}
			} elseif ( array( 'each', 'type' ) === $keys ) {
				if ( true !== $step['each'] ) {
					$out[] = "step {$i} each is not true";
				}
				++$each;
			} else {
				$out[] = "step {$i} carries keys other than type and one of nth or each";
			}
		}
		if ( $each > 1 ) {
			$out[] = 'at has more than one each step';
		}
		$is_move = array_key_exists( 'from', $op ) || array_key_exists( 'to', $op );
		if ( $is_move ) {
			if ( ! $move ) {
				$out[] = 'move is a layout op only';
			}
			if ( isset( $op['set'] ) || isset( $op['cycle'] ) || ! is_int( $op['from'] ?? null ) || ! is_int( $op['to'] ?? null ) || $op['from'] < 0 || $op['to'] < 0 || $op['from'] === $op['to'] ) {
				$out[] = 'a move is { at, from, to } alone, with two different indices of 0 or more';
			}
			return $out;
		}
		$set   = $op['set'] ?? null;
		$cycle = $op['cycle'] ?? null;
		if ( null === $set && null === $cycle ) {
			$out[] = 'an op needs set, cycle or a move';
		}
		if ( null !== $set && ( ! is_array( $set ) || array() === $set || array_is_list( $set ) ) ) {
			$out[] = 'set is a non-empty object';
		}
		if ( null !== $cycle ) {
			if ( 0 === $each ) {
				$out[] = 'cycle needs an each step';
			}
			if ( ! is_array( $cycle ) || array() === $cycle || array_is_list( $cycle ) ) {
				$out[] = 'cycle is a non-empty object';
			} else {
				foreach ( $cycle as $attr => $list ) {
					if ( ! is_array( $list ) || array() === $list || ! array_is_list( $list ) ) {
						$out[] = "cycle {$attr} is not a non-empty list";
					}
				}
			}
		}
		return $out;
	}

	/**
	 * Every attribute leaf an op writes, as [ dotted path, value ]: objects
	 * are walked, a list or a scalar (null included) is a leaf. Cycle values
	 * are leaves of their attribute. Pure; families and recipes both read it.
	 *
	 * @param array<string, mixed> $op A well-formed op.
	 * @return list<array{0: string, 1: mixed}>
	 */
	public static function op_leaves( array $op ): array {
		$out  = array();
		$walk = static function ( array $node, string $prefix ) use ( &$walk, &$out ): void {
			foreach ( $node as $key => $value ) {
				$path = '' === $prefix ? (string) $key : $prefix . '.' . $key;
				if ( is_array( $value ) && array() !== $value && ! array_is_list( $value ) ) {
					$walk( $value, $path );
				} else {
					$out[] = array( $path, $value );
				}
			}
		};
		if ( is_array( $op['set'] ?? null ) ) {
			$walk( $op['set'], '' );
		}
		foreach ( is_array( $op['cycle'] ?? null ) ? $op['cycle'] : array() as $attr => $list ) {
			foreach ( (array) $list as $value ) {
				$out[] = array( (string) $attr, $value );
			}
		}
		return $out;
	}

	/**
	 * Why one layout op is not a derived member's op: the grammar, a typed
	 * root step, then each leaf against layout_ops for the target block (the
	 * last step's type), by the longest admitted path that equals the leaf
	 * or is its prefix. A move needs `move: true`.
	 *
	 * @param mixed               $op        One op.
	 * @param array<mixed, mixed> $ops_table Decoded layout_ops.
	 * @return list<string>
	 */
	private static function layout_op_problems( mixed $op, array $ops_table ): array {
		$out = self::op_problems( $op, true === ( $ops_table['move'] ?? false ) );
		if ( array() !== $out || ! is_array( $op ) ) {
			return $out;
		}
		$at = $op['at'];
		if ( array() === $at || array( 'type' ) !== array_keys( $at[0] ) ) {
			return array( 'a layout op opens with the typed root step { type }, so its target block is known' );
		}
		if ( array_key_exists( 'from', $op ) ) {
			return $out;
		}
		$type  = (string) end( $at )['type'];
		$paths = is_array( $ops_table[ $type ] ?? null ) ? $ops_table[ $type ] : array();
		if ( array() === $paths ) {
			return array( "{$type} has no layout ops" );
		}
		foreach ( self::op_leaves( $op ) as list( $path, $value ) ) {
			$allowed = null;
			$best    = -1;
			foreach ( $paths as $admit => $grammar ) {
				$admit = (string) $admit;
				if ( ( $path === $admit || str_starts_with( $path, $admit . '.' ) ) && strlen( $admit ) > $best ) {
					$allowed = $grammar;
					$best    = strlen( $admit );
				}
			}
			if ( null === $allowed ) {
				$out[] = "{$type} {$path} is not a layout op";
				continue;
			}
			if ( null === $value ) {
				continue; // Clearing returns the block's default: always the editor's to offer.
			}
			$ok = is_array( $allowed )
				? in_array( $value, $allowed, true )
				: is_string( $value ) && 1 === preg_match( '/^var:preset\|' . preg_quote( substr( (string) $allowed, strlen( 'preset:' ) ), '/' ) . '\|[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value );
			if ( ! $ok ) {
				$out[] = "{$type} {$path} " . json_encode( $value ) . ' is outside ' . ( is_array( $allowed ) ? json_encode( $allowed ) : "the {$allowed} grammar" );
			}
		}
		return $out;
	}

	/**
	 * A derived member's computed name (spec §3.3): `<base>--<value>`. Never
	 * authored; pure.
	 *
	 * @param string $base  The base's registered name (blocklane/<slug>).
	 * @param string $value A layout value id.
	 */
	public static function member_name( string $base, string $value ): string {
		return $base . '--' . $value;
	}

	/**
	 * Every family, compiled: id => { id, base, base_value, options: list<{
	 * value, label, pattern, kind (base|derived|sibling), ops? }> }, the base
	 * first, then derived members, then siblings, in file order. Labels are
	 * the untranslated ones; labels() translates them.
	 *
	 * @param array<string, mixed>|null $tax Internal: a compiled taxonomy; null loads the shipped one.
	 * @return array<string, array<string, mixed>>|\WP_Error
	 */
	public static function families( ?array $tax = null ): array|\WP_Error {
		$tax ??= self::load();
		if ( $tax instanceof \WP_Error ) {
			return $tax;
		}
		return $tax['families'];
	}

	/**
	 * The family a pattern name belongs to, from the taxonomy alone: { id,
	 * base, value, kind, renamed, options }, or null for a name no family
	 * holds. It resolves a base, a derived member, a sibling and a retired
	 * name that `renamed` points at a family's layout (renamed: true, kind
	 * that option's). A retired name folded into a plain pattern is no
	 * family's. Pure.
	 *
	 * @param string                    $name A pattern name.
	 * @param array<string, mixed>|null $tax  Internal: a compiled taxonomy; null loads the shipped one.
	 * @return array<string, mixed>|\WP_Error|null
	 */
	public static function family_of( string $name, ?array $tax = null ): array|\WP_Error|null {
		$tax ??= self::load();
		if ( $tax instanceof \WP_Error ) {
			return $tax;
		}
		$renamed = false;
		$hit     = $tax['members'][ $name ] ?? null;
		if ( null === $hit && is_array( $tax['renamed'][ $name ] ?? null ) && isset( $tax['renamed'][ $name ]['family'] ) ) {
			$hit     = array( $tax['renamed'][ $name ]['family'], $tax['renamed'][ $name ]['layout'] );
			$renamed = true;
		}
		if ( null === $hit ) {
			return null;
		}
		$family = $tax['families'][ $hit[0] ];
		$kind   = '';
		foreach ( $family['options'] as $option ) {
			if ( $option['value'] === $hit[1] ) {
				$kind = $option['kind'];
			}
		}
		return array(
			'id'      => $family['id'],
			'base'    => $family['base'],
			'value'   => $hit[1],
			'kind'    => $kind,
			'renamed' => $renamed,
			'options' => array_map(
				static fn( array $o ): array => array(
					'value'   => $o['value'],
					'label'   => $o['label'],
					'pattern' => $o['pattern'],
					'kind'    => $o['kind'],
				),
				$family['options']
			),
		);
	}

	/**
	 * Where a retired name goes (`renamed`): { family, layout } or { pattern },
	 * or null for a name never retired. Pure.
	 *
	 * @param string                    $name A pattern name.
	 * @param array<string, mixed>|null $tax  Internal: a compiled taxonomy; null loads the shipped one.
	 * @return array<string, string>|\WP_Error|null
	 */
	public static function renamed( string $name, ?array $tax = null ): array|\WP_Error|null {
		$tax ??= self::load();
		if ( $tax instanceof \WP_Error ) {
			return $tax;
		}
		return $tax['renamed'][ $name ] ?? null;
	}

	/**
	 * The slug of a blocklane/<slug> name whose slug has single hyphens only,
	 * or null.
	 *
	 * @param mixed $name A pattern name.
	 */
	private static function slug_of( mixed $name ): ?string {
		return is_string( $name ) && str_starts_with( $name, 'blocklane/' ) && self::is_slug( substr( $name, 10 ) ) ? substr( $name, 10 ) : null;
	}

	/**
	 * Classify one pattern (spec §3.3; failure cases C1–C14). The rule, in
	 * order:
	 *   1. a template-part area in blockTypes → that area's entry;
	 *   2. templateTypes naming the coming-soon entry's types → coming-soon;
	 *   3. a full page (is_full_page(): a page's whole content, or any other
	 *      whole template) → page-layouts;
	 *   4. keywords that are EXACT type terms: one → its entry, more than
	 *      one → blocklane_pattern_ambiguous (data['terms']);
	 *   5. otherwise the first keyword whose normalized term is an entry,
	 *      then the first category an entry falls back to;
	 *   6. otherwise blocklane_pattern_unclassified.
	 * The gates are stricter than this (a shipped pattern reaches 1–4);
	 * this is the runtime, which also classifies other plugins' patterns.
	 * Non-array keywords or categories read as empty (C13).
	 *
	 * @param array<string, mixed>      $pattern A registry entry.
	 * @param array<string, mixed>|null $tax     Internal: a compiled taxonomy (fixtures); null loads the shipped one.
	 * @return array{group: string, entry: string, slot: string, rank: array{0: int, 1: int}, lead: string, area: string, source: string, descriptors: list<string>}|\WP_Error
	 */
	public static function classify( array $pattern, ?array $tax = null ): array|\WP_Error {
		$tax ??= self::load();
		if ( $tax instanceof \WP_Error ) {
			return $tax;
		}
		$keywords = self::strings( $pattern['keywords'] ?? null );
		$area     = self::area_of( $pattern );
		$found    = self::structural( $pattern, $area, $tax );
		if ( null !== $found ) {
			return self::shape( $tax, $found[0], 'structural', $found[1], $area, $keywords );
		}
		$typed = array();
		foreach ( $keywords as $keyword ) {
			if ( isset( $tax['types'][ $keyword ] ) ) {
				$typed[ $keyword ] = $tax['types'][ $keyword ];
			}
		}
		if ( count( array_unique( $typed ) ) > 1 ) {
			return new \WP_Error( 'blocklane_pattern_ambiguous', 'The pattern carries more than one type term.', array( 'terms' => array_keys( $typed ) ) );
		}
		if ( array() !== $typed ) {
			return self::shape( $tax, (string) reset( $typed ), 'type', null, $area, $keywords );
		}
		foreach ( $keywords as $keyword ) {
			$n = self::lookup( $keyword, $tax );
			if ( 'entry' === $n['kind'] ) {
				return self::shape( $tax, (string) $n['term'], 'normalized', null, $area, $keywords );
			}
		}
		foreach ( self::strings( $pattern['categories'] ?? null ) as $category ) {
			if ( isset( $tax['fallback'][ $category ] ) ) {
				return self::shape( $tax, $tax['fallback'][ $category ], 'category', null, $area, $keywords );
			}
		}
		return new \WP_Error( 'blocklane_pattern_unclassified', 'The pattern declares no content type this taxonomy knows.', array( 'name' => is_string( $pattern['name'] ?? null ) ? $pattern['name'] : '' ) );
	}

	/**
	 * The template-part area a pattern is for ('header', 'footer',
	 * 'navigation-overlay' or 'menu'), from its blockTypes, or '' for any
	 * other. The battery's TC15 holds this list to AREAS.
	 *
	 * @param array<string, mixed> $pattern A registry entry.
	 */
	public static function area_of( array $pattern ): string {
		foreach ( (array) ( $pattern['blockTypes'] ?? array() ) as $type ) {
			if ( is_string( $type ) && 1 === preg_match( '#^core/template-part/(header|footer|navigation-overlay|menu)$#D', $type, $m ) ) {
				return $m[1];
			}
		}
		return '';
	}

	/**
	 * Whether a pattern is a full page: offered for a page's whole content
	 * (blockTypes holds core/post-content and postTypes is empty or holds
	 * page) or for a whole template (any templateTypes). A popup's content
	 * (postTypes its own type) is not. classify() puts exactly these in the
	 * `pages` group, so a wall that excludes full pages and the taxonomy
	 * never disagree.
	 *
	 * @param array<string, mixed> $pattern A registry entry.
	 */
	public static function is_full_page( array $pattern ): bool {
		$post_types = array_values( (array) ( $pattern['postTypes'] ?? array() ) );
		return ( in_array( 'core/post-content', (array) ( $pattern['blockTypes'] ?? array() ), true ) && ( array() === $post_types || in_array( 'page', $post_types, true ) ) )
			|| array() !== array_values( (array) ( $pattern['templateTypes'] ?? array() ) );
	}

	/**
	 * Text as the vocabulary reads it (spec §3.4): the phrase and, when the
	 * phrase is a surface form of a term, that term and its kind (entry,
	 * descriptor or feature). `term` and `kind` are null for text that is
	 * not a vocabulary term — a value, not a failure.
	 *
	 * @param string                    $text Any text (a keyword, a URL segment, a query).
	 * @param array<string, mixed>|null $tax  Internal: a compiled taxonomy; null loads the shipped one.
	 * @return array{phrase: string, term: ?string, kind: ?string}|\WP_Error
	 */
	public static function normalize( string $text, ?array $tax = null ): array|\WP_Error {
		$tax ??= self::load();
		if ( $tax instanceof \WP_Error ) {
			return $tax;
		}
		return self::lookup( $text, $tax );
	}

	/**
	 * Steps 1 and 2 of the normalizer: the phrase. Cap at CAP code points,
	 * lowercase, fold each character through the fixed table, strip
	 * combining marks, delete apostrophes, make every run of hyphens,
	 * underscores and ASCII whitespace one space, trim, then stem each word.
	 * MIRROR CONTRACT with phrase() in src/normalize.js; both read the
	 * tables from taxonomy.json and are held to normalize-vectors.json.
	 * Invalid UTF-8 is scrubbed first (each bad byte a "?").
	 *
	 * @param string               $text   Any text.
	 * @param array<string, mixed> $tables { min_stem, exceptions, irregular, fold }.
	 */
	public static function phrase( string $text, array $tables ): string {
		$text = self::fold( $text, $tables );
		if ( '' === $text ) {
			return '';
		}
		return implode( ' ', array_map( static fn( string $w ): string => self::stem( $w, $tables ), explode( ' ', $text ) ) );
	}

	/**
	 * Step 1 alone: capped, lowercased, folded, words separated by single
	 * spaces, unstemmed. MIRROR CONTRACT with fold() in src/normalize.js.
	 *
	 * @param string               $text   Any text.
	 * @param array<string, mixed> $tables { fold, … }.
	 */
	public static function fold( string $text, array $tables ): string {
		if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
			$text = mb_scrub( $text, 'UTF-8' );
		}
		$chars = array_slice( mb_str_split( $text, 1, 'UTF-8' ), 0, self::CAP );
		$table = (array) ( $tables['fold'] ?? array() );
		$out   = '';
		foreach ( mb_str_split( mb_strtolower( implode( '', $chars ), 'UTF-8' ), 1, 'UTF-8' ) as $c ) {
			$out .= $table[ $c ] ?? $c;
		}
		$out = (string) preg_replace( '/[\x{0300}-\x{036F}]/u', '', $out );
		$out = str_replace( "'", '', $out );
		return trim( (string) preg_replace( '/[\-_\x09-\x0D\x20]+/', ' ', $out ), ' ' );
	}

	/**
	 * One word without its plural ending (spec §3.4 step 2): an exception is
	 * kept, an irregular is mapped, -ss/-us/-is are kept, -ies is -y,
	 * -ses/-xes/-zes/-ches/-shes drop -es, any other -s drops; a result
	 * shorter than min_stem keeps the word. MIRROR CONTRACT with stemOf()
	 * in src/normalize.js.
	 *
	 * @param string               $word   One folded word.
	 * @param array<string, mixed> $tables { min_stem, exceptions, irregular, … }.
	 */
	public static function stem( string $word, array $tables ): string {
		if ( '' === $word || in_array( $word, (array) ( $tables['exceptions'] ?? array() ), true ) ) {
			return $word;
		}
		$irregular = (array) ( $tables['irregular'] ?? array() );
		if ( isset( $irregular[ $word ] ) ) {
			return (string) $irregular[ $word ];
		}
		if ( str_ends_with( $word, 'ss' ) || str_ends_with( $word, 'us' ) || str_ends_with( $word, 'is' ) ) {
			return $word;
		}
		if ( str_ends_with( $word, 'ies' ) ) {
			$stem = substr( $word, 0, -3 ) . 'y';
		} elseif ( 1 === preg_match( '/(?:s|x|z|ch|sh)es$/', $word ) ) {
			$stem = substr( $word, 0, -2 );
		} elseif ( str_ends_with( $word, 's' ) ) {
			$stem = substr( $word, 0, -1 );
		} else {
			return $word;
		}
		return mb_strlen( $stem, 'UTF-8' ) < (int) ( $tables['min_stem'] ?? 3 ) ? $word : $stem;
	}

	/**
	 * Every surface form of a classification's entry, its descriptors and
	 * the given features, as authored (spec §3.4): what a search expands a
	 * card's text with, so a synonym finds the card with no synonym table
	 * in the browser.
	 *
	 * @param array<string, mixed>      $classification A classify() result.
	 * @param list<string>              $features       Feature ids the pattern uses.
	 * @param array<string, mixed>|null $tax            Internal: a compiled taxonomy; null loads the shipped one.
	 * @return list<string>|\WP_Error
	 */
	public static function search_forms( array $classification, array $features = array(), ?array $tax = null ): array|\WP_Error {
		$tax ??= self::load();
		if ( $tax instanceof \WP_Error ) {
			return $tax;
		}
		$keys = array( 'entry:' . (string) ( $classification['entry'] ?? '' ) );
		foreach ( self::strings( $classification['descriptors'] ?? null ) as $descriptor ) {
			$keys[] = 'descriptor:' . $descriptor;
		}
		foreach ( $features as $feature ) {
			$keys[] = 'feature:' . (string) $feature;
		}
		$out = array();
		foreach ( $keys as $key ) {
			foreach ( $tax['surfaces'][ $key ] ?? array() as $surface ) {
				$out[ $surface ] = true;
			}
		}
		return array_keys( $out );
	}

	/**
	 * Picks in page order (spec §6): the one assembly rule. Each name
	 * resolves to its registered pattern and its classification; the sort
	 * key is (slot rank, group index, entry index, pick index), so a header
	 * leads, a footer closes and sections follow the taxonomy's story order
	 * whatever order they were picked in. A pick that does not resolve, is
	 * hidden from the inserter, or cannot be classified is refused by name
	 * and code, and the rest are still ordered. A family's derived member is
	 * hidden yet a pick: it is placed as its base, which must be offered.
	 * Its name stays the member's.
	 *
	 * @param array<mixed>              $names   Registered pattern names, in pick order (keys are ignored).
	 * @param callable|null             $resolve Internal: name → registry entry or null; null reads the pattern registry.
	 * @param array<string, mixed>|null $tax     Internal: a compiled taxonomy; null loads the shipped one.
	 * @return array{order: list<array{name: string, entry: string, slot: string, rank: array{0: int, 1: int, 2: int}, pick: int}>, refused: list<array{name: string, code: string}>}|\WP_Error
	 */
	public static function assembly_order( array $names, ?callable $resolve = null, ?array $tax = null ): array|\WP_Error {
		if ( array() === $names ) {
			return new \WP_Error( 'blocklane_assembly_empty', 'Pick at least one pattern to order.' );
		}
		if ( count( $names ) > self::ASSEMBLY_MAX ) {
			return new \WP_Error( 'blocklane_assembly_too_many', 'At most ' . self::ASSEMBLY_MAX . ' patterns can be ordered at once.', array( 'max' => self::ASSEMBLY_MAX ) );
		}
		$tax ??= self::load();
		if ( $tax instanceof \WP_Error ) {
			return $tax;
		}
		$resolve ??= static function ( string $name ): ?array {
			$found = \WP_Block_Patterns_Registry::get_instance()->get_registered( $name );
			return is_array( $found ) ? $found : null;
		};
		$order   = array();
		$refused = array();
		foreach ( array_values( $names ) as $pick => $name ) {
			$pattern = is_string( $name ) && '' !== $name ? $resolve( $name ) : null;
			// A pattern hidden from the inserter is not offered, so it is not a
			// pick (A1), unless it is a family's derived member: a Layout option
			// of a base that is offered, classified as that base (spec
			// 2026-10-17-pattern-families §3.12, §14 F-G).
			if ( is_array( $pattern ) && false === ( $pattern['inserter'] ?? true ) ) {
				$family  = self::family_of( (string) $name, $tax );
				$base    = is_array( $family ) && 'derived' === $family['kind'] && ! $family['renamed'] ? $resolve( $family['base'] ) : null;
				$pattern = is_array( $base ) && false !== ( $base['inserter'] ?? true ) ? $base : null;
			}
			if ( ! is_array( $pattern ) ) {
				$refused[] = array(
					'name' => is_string( $name ) ? $name : '',
					'code' => 'blocklane_pattern_unknown',
				);
				continue;
			}
			$class = self::classify( $pattern, $tax );
			if ( $class instanceof \WP_Error ) {
				$refused[] = array(
					'name' => $name,
					'code' => $class->get_error_code(),
				);
				continue;
			}
			$order[] = array(
				'name'  => $name,
				'entry' => $class['entry'],
				'slot'  => $class['slot'],
				'rank'  => array( self::SLOT_RANK[ $class['slot'] ], $class['rank'][0], $class['rank'][1] ),
				'pick'  => $pick,
			);
		}
		usort( $order, static fn( array $a, array $b ): int => array( $a['rank'], $a['pick'] ) <=> array( $b['rank'], $b['pick'] ) );
		return array(
			'order'   => $order,
			'refused' => $refused,
		);
	}

	/**
	 * The taxonomy's labels, translated (the generated taxonomy-i18n.php,
	 * required after init): groups, entries and descriptors by id or term,
	 * leads by their untranslated text, features by id. A feature that is a
	 * catalog unit takes the catalog's label (§13 F11), read through the
	 * public API. A label the generated file lacks (a stale build) is the
	 * untranslated one, never absent.
	 *
	 * Layout values (spec 2026-10-17-pattern-families §3.3) by id.
	 *
	 * @return array{groups: array<string, string>, entries: array<string, string>, leads: array<string, string>, descriptors: array<string, string>, features: array<string, string>, layout_values: array<string, string>}|\WP_Error
	 */
	public static function labels(): array|\WP_Error {
		$tax = self::load();
		if ( $tax instanceof \WP_Error ) {
			return $tax;
		}
		$file = __DIR__ . '/' . self::I18N_FILE;
		$i18n = is_readable( $file ) ? require $file : null;
		if ( ! is_array( $i18n ) ) {
			return new \WP_Error( 'blocklane_taxonomy_i18n_unreadable', 'The pattern taxonomy translations file cannot be read.', array( 'file' => self::I18N_FILE ) );
		}
		$catalog = blocklane_pro_api_catalog();
		$out     = array(
			'groups'        => array(),
			'entries'       => array(),
			'leads'         => array(),
			'descriptors'   => array(),
			'features'      => array(),
			'layout_values' => array(),
		);
		foreach ( $tax['layout_values'] as $id => $label ) {
			$out['layout_values'][ $id ] = (string) ( $i18n['layout_values'][ $id ] ?? $label );
		}
		foreach ( $tax['groups'] as $id => $group ) {
			$out['groups'][ $id ] = (string) ( $i18n['groups'][ $id ] ?? $group['label'] );
		}
		foreach ( $tax['entries'] as $id => $entry ) {
			$out['entries'][ $id ] = (string) ( $i18n['entries'][ $id ] ?? $entry['label'] );
			foreach ( $entry['leads'] as $lead ) {
				$out['leads'][ $lead ] = (string) ( $i18n['leads'][ $lead ] ?? $lead );
			}
		}
		foreach ( array_keys( $tax['descriptors'] ) as $term ) {
			$out['descriptors'][ $term ] = (string) ( $i18n['descriptors'][ $term ] ?? self::descriptor_label( $term ) );
		}
		foreach ( $tax['features'] as $id => $feature ) {
			$unit                     = $feature['unit'] ?? null;
			$out['features'][ $id ] = is_string( $unit )
				? (string) ( $catalog[ $unit ]['label'] ?? $id )
				: (string) ( $i18n['features'][ $id ] ?? $feature['label'] );
		}
		return $out;
	}

	/**
	 * family_of() for a consumer: labels translated (labels()), ops dropped
	 * (they are data for Pro's applier and capture, never for a consumer).
	 * After init.
	 *
	 * @param string $name A pattern name.
	 * @return array<string, mixed>|\WP_Error|null
	 */
	public static function family_labeled( string $name ): array|\WP_Error|null {
		$family = self::family_of( $name );
		return is_array( $family ) ? self::labeled( $family ) : $family;
	}

	/**
	 * families() for a consumer, as family_labeled() shapes one. After init.
	 *
	 * @return array<string, array<string, mixed>>|\WP_Error
	 */
	public static function families_labeled(): array|\WP_Error {
		$families = self::families();
		return $families instanceof \WP_Error ? $families : array_map( array( self::class, 'labeled' ), $families );
	}

	/**
	 * A family with translated option labels and no ops.
	 *
	 * @param array<string, mixed> $family A family_of() or families() entry.
	 * @return array<string, mixed>
	 */
	private static function labeled( array $family ): array {
		$labels = self::labels();
		$names  = $labels instanceof \WP_Error ? array() : $labels['layout_values'];
		foreach ( $family['options'] as $i => $option ) {
			unset( $option['ops'] );
			$option['label']         = (string) ( $names[ $option['value'] ] ?? $option['label'] );
			$family['options'][ $i ] = $option;
		}
		return $family;
	}

	/**
	 * A descriptor term as a label: its hyphens are spaces ("Site-map" reads
	 * "Site map"). The generator writes these msgids; labels() falls back to
	 * this for a stale build.
	 *
	 * @param string $term A descriptor term.
	 */
	public static function descriptor_label( string $term ): string {
		return str_replace( '-', ' ', $term );
	}

	/**
	 * The normalizer's tables from decoded data.
	 *
	 * @param array<mixed> $data Decoded taxonomy.json.
	 * @return array{min_stem: int, exceptions: list<string>, irregular: array<string, string>, fold: array<string, string>}
	 */
	public static function tables_of( array $data ): array {
		$norm = (array) ( $data['normalizer'] ?? array() );
		return array(
			'min_stem'   => (int) ( $norm['min_stem'] ?? 3 ),
			'exceptions' => array_values( array_map( 'strval', (array) ( $norm['exceptions'] ?? array() ) ) ),
			'irregular'  => array_map( 'strval', (array) ( $norm['irregular'] ?? array() ) ),
			'fold'       => array_map( 'strval', (array) ( $norm['fold'] ?? array() ) ),
		);
	}

	/**
	 * Compile valid data: the shape every other method reads.
	 *
	 * @param array<mixed> $data Decoded, valid taxonomy.json.
	 * @return array<string, mixed>
	 */
	private static function build( array $data ): array {
		$tax = array(
			'data'        => $data,
			'tables'      => self::tables_of( $data ),
			'groups'      => array(),
			'entries'     => array(),
			'types'       => array(),
			'fallback'    => array(),
			'descriptors' => array(),
			'features'    => array(),
			'index'       => array(),
			'surfaces'    => array(),
		);
		$add = static function ( string $kind, string $term, string $surface ) use ( &$tax ): void {
			$tax['index'][ self::phrase( $surface, $tax['tables'] ) ] = array( $term, $kind );
			$tax['surfaces'][ "{$kind}:{$term}" ][]                    = $surface;
		};
		foreach ( $data['groups'] as $g => $group ) {
			$tax['groups'][ $group['id'] ] = array(
				'label'   => $group['label'],
				'index'   => $g,
				'entries' => array_column( $group['entries'], 'id' ),
			);
			foreach ( $group['entries'] as $e => $entry ) {
				$leads = isset( $entry['leads'] ) ? array_values( $entry['leads'] ) : array( $entry['lead'] );
				$id    = $entry['id'];

				$tax['entries'][ $id ] = array(
					'group'       => $group['id'],
					'rank'        => array( $g, $e ),
					'label'       => $entry['label'],
					'leads'       => $leads,
					'type'        => $entry['type'] ?? null,
					'slot'        => $entry['slot'],
					'structural'  => $entry['structural'] ?? null,
					'categories'  => array_values( $entry['categories'] ?? array() ),
				);
				if ( isset( $entry['type'] ) ) {
					$tax['types'][ $entry['type'] ] = $id;
					$add( 'entry', $id, $entry['type'] );
				}
				foreach ( $entry['categories'] ?? array() as $cat ) {
					$tax['fallback'][ $cat ] = $id;
				}
				foreach ( array_merge( array( $id, $entry['label'] ), $leads, $entry['synonyms'] ?? array() ) as $surface ) {
					$add( 'entry', $id, $surface );
				}
			}
		}
		foreach ( $data['descriptors'] as $descriptor ) {
			$term = is_string( $descriptor ) ? $descriptor : $descriptor['term'];

			$tax['descriptors'][ $term ] = is_array( $descriptor ) ? array_values( $descriptor['synonyms'] ?? array() ) : array();
			foreach ( array_merge( array( $term ), $tax['descriptors'][ $term ] ) as $surface ) {
				$add( 'descriptor', $term, $surface );
			}
		}
		foreach ( $data['features'] as $feature ) {
			$tax['features'][ $feature['id'] ] = $feature;
			foreach ( array_merge( array( $feature['id'] ), isset( $feature['label'] ) ? array( $feature['label'] ) : array(), $feature['synonyms'] ?? array() ) as $surface ) {
				$add( 'feature', $feature['id'], $surface );
			}
		}
		foreach ( $tax['surfaces'] as $key => $list ) {
			$tax['surfaces'][ $key ] = array_values( array_unique( $list ) );
		}
		// Families (spec 2026-10-17-pattern-families §3.3): one compiled
		// entry per family, and every option's pattern name => [ id, value ].
		$tax['layout_values'] = array_column( $data['layout_values'], 'label', 'id' );
		$tax['layout_ops']    = $data['layout_ops'];
		$tax['families']      = array();
		$tax['members']       = array();
		$tax['renamed']       = $data['renamed'];
		foreach ( $data['families'] as $family ) {
			$id      = $family['id'];
			$options = array(
				array(
					'value'   => $family['base_value'],
					'pattern' => $family['base'],
					'kind'    => 'base',
				),
			);
			foreach ( $family['derived'] ?? array() as $member ) {
				$options[] = array(
					'value'   => $member['value'],
					'pattern' => self::member_name( $family['base'], $member['value'] ),
					'kind'    => 'derived',
					'ops'     => $member['ops'],
				);
			}
			foreach ( $family['siblings'] ?? array() as $sibling ) {
				$options[] = array(
					'value'   => $sibling['value'],
					'pattern' => $sibling['pattern'],
					'kind'    => 'sibling',
				);
			}
			foreach ( $options as $i => $option ) {
				$options[ $i ]['label']               = $tax['layout_values'][ $option['value'] ];
				$tax['members'][ $option['pattern'] ] = array( $id, $option['value'] );
			}
			$tax['families'][ $id ] = array(
				'id'         => $id,
				'base'       => $family['base'],
				'base_value' => $family['base_value'],
				'options'    => $options,
			);
		}
		return $tax;
	}

	/**
	 * Steps 1 and 2, then the synonym index (step 3).
	 *
	 * @param string               $text Any text.
	 * @param array<string, mixed> $tax  A compiled taxonomy.
	 * @return array{phrase: string, term: ?string, kind: ?string}
	 */
	private static function lookup( string $text, array $tax ): array {
		$phrase = self::phrase( $text, $tax['tables'] );
		$hit    = $tax['index'][ $phrase ] ?? null;
		return array(
			'phrase' => $phrase,
			'term'   => null === $hit ? null : $hit[0],
			'kind'   => null === $hit ? null : $hit[1],
		);
	}

	/**
	 * Structural steps 1–3: [entry id, lead] or null.
	 *
	 * @param array<string, mixed> $pattern A registry entry.
	 * @param string               $area    Its area_of().
	 * @param array<string, mixed> $tax     A compiled taxonomy.
	 * @return array{0: string, 1: string}|null
	 */
	private static function structural( array $pattern, string $area, array $tax ): ?array {
		$templates = self::strings( $pattern['templateTypes'] ?? null );
		$full_page = null;
		foreach ( $tax['entries'] as $id => $entry ) {
			$s = $entry['structural'];
			if ( ! is_array( $s ) ) {
				continue;
			}
			if ( '' !== $area && ( $s['area'] ?? null ) === $area ) {
				return array( $id, $entry['leads'][0] );
			}
			if ( isset( $s['template_types'] ) ) {
				foreach ( $s['template_types'] as $i => $type ) {
					if ( '' === $area && in_array( $type, $templates, true ) ) {
						return array( $id, $entry['leads'][ $i ] ?? $entry['leads'][0] );
					}
				}
			}
			if ( true === ( $s['full_page'] ?? null ) ) {
				$full_page = array( $id, $entry['leads'][0] );
			}
		}
		return '' === $area && null !== $full_page && self::is_full_page( $pattern ) ? $full_page : null;
	}

	/**
	 * A classification's shape.
	 *
	 * @param array<string, mixed> $tax      A compiled taxonomy.
	 * @param string               $id       The entry id.
	 * @param string               $source   structural, type, normalized or category.
	 * @param string|null          $lead     The lead, or null for the entry's first.
	 * @param string               $area     The pattern's area_of().
	 * @param list<string>         $keywords The pattern's keywords.
	 * @return array{group: string, entry: string, slot: string, rank: array{0: int, 1: int}, lead: string, area: string, source: string, descriptors: list<string>}
	 */
	private static function shape( array $tax, string $id, string $source, ?string $lead, string $area, array $keywords ): array {
		$entry       = $tax['entries'][ $id ];
		$descriptors = array();
		foreach ( $keywords as $keyword ) {
			$n = self::lookup( $keyword, $tax );
			if ( 'descriptor' === $n['kind'] ) {
				$descriptors[ (string) $n['term'] ] = true;
			}
		}
		return array(
			'group'       => $entry['group'],
			'entry'       => $id,
			'slot'        => $entry['slot'],
			'rank'        => $entry['rank'],
			'lead'        => $lead ?? $entry['leads'][0],
			'area'        => $area,
			'source'      => $source,
			'descriptors' => array_keys( $descriptors ),
		);
	}

	/**
	 * Fail a synonym that adds nothing: its phrase is already one of its
	 * own term's other forms (a dead synonym, the dead-exception rule's
	 * sibling).
	 *
	 * @param callable             $fail     The problem sink.
	 * @param string               $kind     entry, descriptor or feature.
	 * @param string               $term     The term.
	 * @param array<mixed>         $synonyms Its synonyms.
	 * @param array<mixed>         $own      Its other surface forms.
	 * @param array<string, mixed> $tables   The normalizer's tables.
	 */
	private static function redundant( callable $fail, string $kind, string $term, array $synonyms, array $own, array $tables ): void {
		$seen = array();
		foreach ( $own as $form ) {
			if ( is_string( $form ) && '' !== $form ) {
				$seen[ self::phrase( $form, $tables ) ] = true;
			}
		}
		foreach ( $synonyms as $synonym ) {
			if ( ! is_string( $synonym ) ) {
				continue;
			}
			$p = self::phrase( $synonym, $tables );
			if ( isset( $seen[ $p ] ) ) {
				$fail( 'dead', "{$kind} {$term} synonym \"{$synonym}\" normalizes to \"{$p}\", a form the term already has" );
			}
			$seen[ $p ] = true;
		}
	}

	/**
	 * Fail vocabulary that is not printable ASCII (spec §4: the vocabulary
	 * is ASCII so both twins and every theme gate read it the same way).
	 *
	 * @param callable $fail  The problem sink.
	 * @param string   $where What the string is.
	 * @param mixed    $value The string.
	 */
	private static function vocabulary( callable $fail, string $where, mixed $value ): void {
		if ( ! is_string( $value ) || '' === trim( $value ) || 1 !== preg_match( '/^[\x20-\x7E]+$/', $value ) ) {
			$fail( 'ascii', "{$where} " . json_encode( $value ) . ' is not non-empty printable ASCII' );
		}
	}

	/**
	 * The strings of a list, or none for anything that is not an array.
	 *
	 * @param mixed $value A registry property.
	 * @return list<string>
	 */
	private static function strings( mixed $value ): array {
		return is_array( $value ) ? array_values( array_filter( $value, 'is_string' ) ) : array();
	}

	/**
	 * Whether a value is a list of strings.
	 *
	 * @param mixed $value Any value.
	 */
	private static function is_list_of_strings( mixed $value ): bool {
		return is_array( $value ) && array_is_list( $value ) && array() === array_filter( $value, static fn( mixed $v ): bool => ! is_string( $v ) );
	}

	/**
	 * Whether a value is a string-keyed map of strings.
	 *
	 * @param mixed $value Any value.
	 */
	private static function is_map_of_strings( mixed $value ): bool {
		if ( ! is_array( $value ) ) {
			return false;
		}
		foreach ( $value as $k => $v ) {
			if ( ! is_string( $k ) || ! is_string( $v ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether a value is a slug (an id, a page slug).
	 *
	 * @param mixed $value Any value.
	 */
	private static function is_slug( mixed $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value );
	}

	/**
	 * Whether a value is one lowercase ASCII word.
	 *
	 * @param mixed $value Any value.
	 */
	private static function ascii_word( mixed $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/^[a-z]+$/', $value );
	}
}
