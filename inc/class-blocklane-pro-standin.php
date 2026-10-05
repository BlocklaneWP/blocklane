<?php

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Standin — the one door every block-editor stand-in goes through.
 *
 * WHAT A STAND-IN IS. A data-preservation unit (Amendment 14 of the
 * free-edition build-target spec): while a Pro-authored block is not
 * registered on the server, a small editor bundle registers it CLIENT-SIDE
 * with `inserter: false`, so an editor opening the page sees an intentional,
 * read-only block whose saved markup round-trips byte for byte, instead of
 * core's missing-block placeholder. Two exist, inc/mega-menu-standin/ and
 * inc/form-step-standin/, and both ship in BOTH editions. A stand-in hands a
 * never-paid user no feature; it keeps what a Pro user already built whole.
 *
 * WHY ONE CLASS. The form-step stand-in was written by copying the mega-menu
 * one, so every property of the shape had two definitions and they drifted:
 * which hook prints the alarm (#909), what the load guard is called (#910),
 * what the inspector says and to whom (#915), whether the translations call
 * is guarded (#931), which predicate gates the two hooks (#1039). A stand-in
 * runtime is now an ABSPATH guard and ONE call, Standin::register( $def ),
 * whose def names only what is that stand-in's own: the block, its script
 * handle, its runtime file, the unit whose presence registers the real
 * block, the module that boots it behind the block-theme gate (null when
 * none does), an optional extra gate, and the inspector's copy. Every
 * behavior below is this class's, once. A third stand-in adds zero hooks,
 * and a stand-in built any other way fails bin/standin-shape-check.php
 * (wiring check 8): a `kind: standin` unit's runtime may declare nothing,
 * may call nothing at file scope but defined() and one register() call on
 * this class, and may reach none of the doors below, not even in a closure.
 *
 * THE DOORS, named.
 * 1. The registry: wanted() is false while WP_Block_Type_Registry says the
 *    block is registered. Under Pro, Block_Suite::register() runs on init 10
 *    and both hooks below fire after init, so this reads a populated
 *    registry. The registry is the AUTHORITY: a stand-in never registers its
 *    block on the server (a second registrar on one name races the real one,
 *    and the losing order fails silently), so this is a read of the real
 *    registration, never of its own.
 * 2. The extra gate: a def's `wanted` closure, read after the registry.
 *    Form-step's is the forms toggle (the suite registers nothing while it
 *    is off). ONE predicate, wanted(), for both hooks, so the enqueue and the
 *    alarm can never disagree about when a stand-in is wanted.
 * 3. Safe mode: register() returns at once under BLOCKLANE_PRO_SAFE_MODE,
 *    storing nothing and hooking nothing. The editor then shows core's
 *    placeholder, and core keeps an unregistered block whole
 *    (originalContent and attributes) until Attempt Recovery or Convert to
 *    HTML.
 * 4. The enqueue, on enqueue_block_editor_assets: the built asset file names
 *    the bundle's dependencies. An unbuilt checkout gets a wp_trigger_error()
 *    on the editor request (silent without WP_DEBUG; door 6 is the alarm
 *    that is not), never a silent return, which would be indistinguishable
 *    from "the real block is in charge".
 * 5. The translations: wp_set_script_translations(), called unguarded (the
 *    floor is WordPress 7.1 and the function is @since 5.0), so a language
 *    pack reaches the bundle's strings.
 * 6. The visible alarm: check_all(), on admin_init, hooks notices() on
 *    all_admin_notices when any wanted stand-in is unbuilt. Core fires that
 *    hook on every admin screen, network, user and site alike
 *    (wp-admin/admin-header.php), where each legacy notice hook fires on one
 *    kind of screen only. One notice-warning, for manage_options, naming the
 *    directories however many are unbuilt. A shipped zip cannot reach it:
 *    bin/dist-check.php lists every stand-in bundle as a sentinel of both
 *    editions' zips.
 * 7. The JS belt, in each bundle: registerBlockType() only when
 *    getBlockType() has nothing, so an older theme's copy of a bundle, still
 *    enqueued during an upgrade window, never registers the block twice.
 * 8. The inspector's copy, localized: enqueue() adds, before the bundle,
 *    `window.blocklaneProStandin[ <block> ] = { reason, text }`, and every
 *    bundle reads it through ONE reader, inc/shared/standin-note.js. The
 *    copy is defined ONCE, in PHP (inc/shared/README.md rule 4), so the
 *    free-edition battery job asserts the free sentence itself (E44f).
 *
 * WHO READS THE COPY. reason() answers in AUDIENCE order: `edition` when the
 * running build does not carry the owner unit (a free user hears "Blocklane
 * Pro", never "block theme": under free the classic-theme test never runs);
 * else `classic_theme` when the owner boots behind the block-theme gate
 * (`module` is not null) and the theme is classic; else `not_running` — the
 * owner is present and nothing else explains it (a blocklane_pro_load_module
 * veto, a boot-phase miss, a block manifest Block_Suite skipped, a torn
 * content row). Those four collapse into one because the honest sentence is
 * the same for all ("not running on this site") and the loader's own notices
 * already say which. reasons_for() is REASONS minus `classic_theme` when
 * `module` is null (an owner that is a content row cannot be behind the
 * theme gate), and text() refuses a strings table that does not cover
 * EXACTLY those reasons, with a non-empty sentence for each — a reason with
 * no copy, or copy for a reason that cannot happen, is a LogicException on
 * the first editor load and in both CI battery jobs (E44d).
 *
 * THE JUST-IN-TIME RULE. A def's `strings` is a CLOSURE, evaluated by text()
 * inside enqueue_block_editor_assets, never at plugins_loaded, when the
 * runtime's register() runs. A __() before after_setup_theme is WordPress
 * 6.7+'s "translation loading … was triggered too early" notice wherever a
 * translation file exists, and the free edition has no
 * load_plugin_textdomain() at all: just-in-time loading IS its path.
 *
 * REFUSED, typed (LogicException, a source defect): a def missing a key,
 * carrying a mistyped or an unknown one; a strings table that does not cover
 * exactly the reachable reasons (text()); an owner that is not a manifest unit
 * (Edition::has() throws at registration, not on the first editor load); and
 * a SECOND registration of a block. A stand-in runtime is required once, by
 * require_once from Modules::content(), so a second register() is a source
 * defect, and that refusal is why the runtimes need no single-load constant:
 * they declare nothing a second require could redeclare.
 *
 * LOADING. A classmap class on a core path, listed in each stand-in row's
 * `classes` in Modules::content(): the loader proves it mapped, present and
 * declared before either runtime is required, so a torn deploy missing this
 * file is a typed Module_Miss (CLASS_FILE_MISSING, or CLASS_UNMAPPED) for
 * each stand-in row: logged, shown to administrators, the runtimes never
 * required, the editor on core's placeholder, no fatal. The standing-down
 * edition never registers its autoloader, so this file is loaded once and
 * declares its class at top level like every other class file.
 *
 * OUTSIDE, written down beside it. The Module_Miss content-phase sentence says
 * saved content "will not render" for a torn stand-in row, which overstates
 * for an editor-only unit (the phase's shared wording, recorded by the
 * 2026-09-20 spec §7.5). A def the edition battery registers (E44e) lingers
 * for that request.
 *
 * @package blocklane_pro
 */

/**
 * @phpstan-type Standin_Def array{block: string, handle: string, file: string, owner: string, module: string|null, wanted: \Closure|null, strings: \Closure}
 */
final class Standin {

	/** The running build does not carry the owner unit. */
	public const REASON_EDITION = 'edition';

	/** The owner boots behind the block-theme gate, and the theme is classic. */
	public const REASON_CLASSIC = 'classic_theme';

	/**
	 * The owner is present and nothing else explains it: a load_module veto,
	 * a boot-phase miss, a skipped block manifest, a torn content row.
	 */
	public const REASON_NOT_RUNNING = 'not_running';

	/** Every reason, in the audience order reason() tests them. */
	public const REASONS = array( self::REASON_EDITION, self::REASON_CLASSIC, self::REASON_NOT_RUNNING );

	/**
	 * Every key a def carries, and what its value must be. No key is
	 * optional: a def names every property explicitly, `null` included.
	 */
	private const KEYS = array(
		'block'   => 'a non-empty string (the block name)',
		'handle'  => 'a non-empty string (the script handle)',
		'file'    => 'a non-empty string (the runtime file, __FILE__)',
		'owner'   => 'a non-empty string (the manifest unit whose presence registers the real block)',
		'module'  => 'a non-empty string (the module slug that boots the real block behind the block-theme gate), or null',
		'wanted'  => 'a Closure returning bool, or null',
		'strings' => 'a Closure returning reason => sentence, called at enqueue',
	);

	/**
	 * Block name => def, in registration order.
	 *
	 * @var array<string, Standin_Def>
	 */
	private static array $defs = array();

	/** Whether enqueue_all() and check_all() are hooked. */
	private static bool $hooked = false;

	/**
	 * The unbuilt stand-in directories check_all() found, relative to the
	 * plugin directory.
	 *
	 * @var list<string>
	 */
	private static array $unbuilt = array();

	/**
	 * Register a stand-in: the ONE call a stand-in runtime makes.
	 *
	 * @param array<mixed> $def The stand-in's def (see KEYS).
	 * @throws \LogicException A malformed def, an unknown owner unit, or a block registered twice.
	 */
	public static function register( array $def ): void {
		if ( defined( 'BLOCKLANE_PRO_SAFE_MODE' ) && BLOCKLANE_PRO_SAFE_MODE ) {
			return; // Door 3: safe mode silences every Blocklane surface, this one included.
		}
		$def = self::validate( $def );
		// Throws for an id the manifest does not know, so a typo'd owner fails
		// here, at plugins_loaded, rather than reading false on every request.
		Edition::has( $def['owner'] );
		if ( isset( self::$defs[ $def['block'] ] ) ) {
			throw new \LogicException( esc_html( "Standin: {$def['block']} is already registered — a stand-in runtime loads once (require_once); a second registration is a source defect" ) );
		}
		self::$defs[ $def['block'] ] = $def;
		if ( ! self::$hooked ) {
			self::$hooked = true;
			add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_all' ) );
			add_action( 'admin_init', array( __CLASS__, 'check_all' ) );
		}
	}

	/**
	 * Every registered stand-in, block name => def, in registration order.
	 *
	 * @return array<string, Standin_Def>
	 */
	public static function defs(): array {
		return self::$defs;
	}

	/**
	 * Whether a stand-in has a job on this request (doors 1 and 2): the real
	 * block is not registered on the server, and the def's extra gate, if it
	 * has one, is open.
	 *
	 * @param Standin_Def $def
	 */
	public static function wanted( array $def ): bool {
		if ( \WP_Block_Type_Registry::get_instance()->is_registered( $def['block'] ) ) {
			return false; // The real block is in charge; the stand-in stays away.
		}
		return null === $def['wanted'] || true === ( $def['wanted'] )();
	}

	/**
	 * Enqueue one stand-in's editor bundle (doors 4 and 5).
	 *
	 * @param Standin_Def $def
	 * @return bool True when the bundle was enqueued.
	 */
	public static function enqueue( array $def ): bool {
		if ( ! self::wanted( $def ) ) {
			return false;
		}
		$dir        = dirname( $def['file'] );
		$asset_file = $dir . '/build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			wp_trigger_error(
				__METHOD__,
				sprintf(
					'The editor stand-in in %1$s is not built: %2$s is missing. Run `npm ci && npm run build` in the plugin directory (a development checkout), or reinstall the plugin.',
					self::rel( $dir ),
					self::rel( $asset_file )
				),
				E_USER_WARNING
			);
			return false;
		}
		$asset = include $asset_file;

		wp_enqueue_script(
			$def['handle'],
			plugins_url( 'build/index.js', $def['file'] ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( $def['handle'], 'blocklane', BLOCKLANE_PRO_PATH . '/languages' );

		// Door 8: the inspector's copy, for this block, beside any other
		// stand-in's entry. text() runs HERE, inside the editor hook: the
		// just-in-time rule.
		$reason = self::reason( $def );
		wp_add_inline_script(
			$def['handle'],
			'window.blocklaneProStandin = Object.assign( window.blocklaneProStandin || {}, ' . wp_json_encode(
				array(
					$def['block'] => array(
						'reason' => $reason,
						'text'   => self::text( $def, $reason ),
					),
				)
			) . ' );',
			'before'
		);

		return true;
	}

	/**
	 * The reasons a def can be read in: REASONS, minus `classic_theme` when
	 * the owner is not booted by a module behind the block-theme gate.
	 *
	 * @param Standin_Def $def
	 * @return list<string>
	 */
	public static function reasons_for( array $def ): array {
		return null === $def['module']
			? array( self::REASON_EDITION, self::REASON_NOT_RUNNING )
			: self::REASONS;
	}

	/**
	 * Why the real block is not registered on this request, in audience order
	 * (see WHO READS THE COPY above).
	 *
	 * @param Standin_Def $def
	 * @param bool|null   $block_theme Whether the theme is a block theme; null asks
	 *                                 wp_is_block_theme(). Injectable for the battery.
	 */
	public static function reason( array $def, ?bool $block_theme = null ): string {
		if ( ! Edition::has( $def['owner'] ) ) {
			return self::REASON_EDITION;
		}
		if ( null !== $def['module'] && ! ( $block_theme ?? wp_is_block_theme() ) ) {
			return self::REASON_CLASSIC;
		}
		return self::REASON_NOT_RUNNING;
	}

	/**
	 * The inspector's sentence for one reason. Evaluates the def's strings
	 * closure (the just-in-time rule) and refuses a table that does not cover
	 * exactly reasons_for( $def ), each with a non-empty sentence.
	 *
	 * @param Standin_Def $def
	 * @throws \LogicException When the table and the reachable reasons disagree, or the reason is not reachable.
	 */
	public static function text( array $def, string $reason ): string {
		$want    = self::reasons_for( $def );
		$strings = ( $def['strings'] )();
		$got     = is_array( $strings ) ? array_map( 'strval', array_keys( $strings ) ) : array();
		$sorted  = array( $want, $got );
		sort( $sorted[0] );
		sort( $sorted[1] );
		$blank = is_array( $strings )
			? array_keys( array_filter( $strings, static fn( $sentence ): bool => ! is_string( $sentence ) || '' === trim( $sentence ) ) )
			: array();
		if ( ! is_array( $strings ) || $sorted[0] !== $sorted[1] || array() !== $blank ) {
			throw new \LogicException(
				esc_html(
					"Standin: {$def['block']} strings must cover exactly [" . implode( ', ', $want ) . '] — got [' . implode( ', ', $got ) . ']'
					. ( array() !== $blank ? ', empty: [' . implode( ', ', array_map( 'strval', $blank ) ) . ']' : '' )
				)
			);
		}
		if ( ! in_array( $reason, $want, true ) ) {
			throw new \LogicException( esc_html( "Standin: {$def['block']} has no reason [{$reason}]; its reasons are [" . implode( ', ', $want ) . ']' ) );
		}
		return (string) $strings[ $reason ];
	}

	/**
	 * enqueue_block_editor_assets callback: every registered stand-in.
	 */
	public static function enqueue_all(): void {
		foreach ( self::$defs as $def ) {
			self::enqueue( $def );
		}
	}

	/**
	 * Whether a stand-in is wanted and its bundle is not built: the state the
	 * alarm exists for (door 6).
	 *
	 * @param Standin_Def $def
	 */
	public static function check( array $def ): bool {
		return self::wanted( $def ) && ! file_exists( dirname( $def['file'] ) . '/build/index.asset.php' );
	}

	/**
	 * admin_init callback (every wp-admin request, after init has let Pro
	 * register its blocks): collect the unbuilt stand-ins and, when there is
	 * one, hook the notice on all_admin_notices.
	 */
	public static function check_all(): void {
		self::$unbuilt = array();
		foreach ( self::$defs as $def ) {
			if ( self::check( $def ) ) {
				self::$unbuilt[] = self::rel( dirname( $def['file'] ) );
			}
		}
		if ( array() !== self::$unbuilt ) {
			add_action( 'all_admin_notices', array( __CLASS__, 'notices' ) );
		}
	}

	/**
	 * all_admin_notices callback: one notice for every unbuilt stand-in,
	 * shown to whoever can act on it.
	 */
	public static function notices(): void {
		if ( array() === self::$unbuilt || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: the unbuilt stand-in directories, comma-separated (for example inc/mega-menu-standin). */
					_n(
						'Blocklane: the editor stand-in in %s is not built (its build/ directory is missing). On a development checkout run `npm ci && npm run build` in the plugin directory; on a live site reinstall the plugin.',
						'Blocklane: the editor stand-ins in %s are not built (their build/ directories are missing). On a development checkout run `npm ci && npm run build` in the plugin directory; on a live site reinstall the plugin.',
						count( self::$unbuilt ),
						'blocklane'
					),
					implode( ', ', self::$unbuilt )
				)
			)
		);
	}

	/**
	 * The def, proven whole: every key present, typed, and nothing else.
	 *
	 * @param array<mixed> $def
	 * @return Standin_Def
	 * @throws \LogicException Naming the block and the key.
	 */
	private static function validate( array $def ): array {
		$block = isset( $def['block'] ) && is_string( $def['block'] ) && '' !== $def['block'] ? $def['block'] : '(unnamed)';
		foreach ( self::KEYS as $key => $want ) {
			$value = $def[ $key ] ?? null;
			$ok    = match ( $key ) {
				'wanted'  => null === $value || $value instanceof \Closure,
				'strings' => $value instanceof \Closure,
				'module'  => null === $value || ( is_string( $value ) && '' !== $value ),
				default   => is_string( $value ) && '' !== $value,
			};
			if ( ! array_key_exists( $key, $def ) || ! $ok ) {
				throw new \LogicException( esc_html( "Standin: {$block} def is missing {$key}: {$want}" ) );
			}
		}
		foreach ( array_keys( $def ) as $key ) {
			if ( ! isset( self::KEYS[ $key ] ) ) {
				throw new \LogicException( esc_html( "Standin: {$block} def carries an unknown key " . (string) $key ) );
			}
		}
		/** @var Standin_Def $def */
		return $def;
	}

	/**
	 * A path relative to the plugin directory, for a message; a path outside
	 * it (the battery's throwaway def) is returned whole.
	 */
	private static function rel( string $path ): string {
		$base = BLOCKLANE_PRO_PATH . '/';
		return str_starts_with( $path, $base ) ? substr( $path, strlen( $base ) ) : $path;
	}
}
