<?php
/**
 * Everything the main file used to declare — moved here so the coexistence
 * guard can actually stop it.
 *
 * WHY THIS FILE EXISTS. PHP binds a top-level `function` when it COMPILES the
 * file, before the file's first statement runs. So the guard at the top of
 * blocklane-pro.php — `if ( defined( 'BLOCKLANE_PRO_FILE' ) ) { return; }` —
 * could never prevent a function declared further down that same file: by the
 * time the `return` executed, the declaration had already happened at compile
 * time. Two editions of one tree carry the same function names, so the loser
 * fataled with "Cannot redeclare blocklane_pro_classmap()" the moment both
 * were active. A `return` stops statements; only never compiling the file
 * stops declarations.
 *
 * `require_once` IS a statement, so the guard's `return` prevents it, and
 * nothing in here is ever compiled in the standing-down edition. That is the
 * whole reason for the split — keep it. The invariant is enforced by
 * bin/generate-edition.php, which refuses a main file containing any top-level
 * declaration (`function`, `class`, `interface`, `trait`, `enum`).
 *
 * The loser requires at most ONE file: a copy that loads before the winner
 * (an abnormal folder order) requires inc/edition-identity.php — hook-free,
 * declaring nothing at its top level — to find the higher build and step
 * aside; the normal loser requires nothing, and either may define one
 * constant on its way out. This file is never compiled in a loser, and the
 * sibling hold/installer (inc/edition-sibling.php) is a Pro-only unit the
 * free zip does not carry at all.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The one door to the error log (debug-only), before anything that logs.
require_once __DIR__ . '/log.php';

/**
 * The plugin's ONE loading regime: every first-party class under the
 * blocklane_pro namespace is found through the committed, generated
 * inc/classmap.php (bin/generate-classmap.php). No hand-placed require of a
 * class file exists anywhere in the plugin except the five exceptions
 * bin/wiring-check.php allowlists (inc/license/lifecycle.php's License,
 * uninstall.php's Bake_Reaper and File_Ops, Advanced's deferred
 * Svg_Image_Editor, the content-rewrite ability), so load
 * order is no longer a per-call-site
 * fact. A miss is a defect, never a soft skip: the class is logged by name and
 * PHP fails at the referencing line. A class_exists() with autoload=true would
 * merely read false after the log, which is why every first-party probe passes
 * `false` (the same script gates that). External callers — the Patterns
 * plugin's Supabase\Client probes License — pass `false` under the same rule
 * (its bin/pro-probe-check.php gates that) and rely on License's eager
 * require below; a lazy License breaks both. Stale maps cannot merge (Quality
 * CI) or ship (the dist chain).
 *
 * @return array<class-string, string> FQCN => path relative to the plugin dir.
 */
function blocklane_pro_classmap(): array {
	/** @var array<class-string, string>|null $map */
	static $map = null;
	if ( null === $map ) {
		// No fallback on purpose: an unreadable map must fatal here, not
		// degrade to "every class misses".
		$map = require BLOCKLANE_PRO_PATH . '/inc/classmap.php';
	}
	return $map;
}

/**
 * spl_autoload callback. Answers only for our namespace; any other prefix is
 * another autoloader's business and costs one str_starts_with().
 *
 * @param string $class Fully-qualified name, no leading backslash.
 * @return void
 */
function blocklane_pro_autoload( string $class ): void {
	if ( ! str_starts_with( $class, 'blocklane_pro\\' ) ) {
		return;
	}
	$map = blocklane_pro_classmap();
	if ( ! isset( $map[ $class ] ) ) {
		// A classmap miss is a shipped-build defect, but a class_exists()
		// probe for a class this edition does not carry misses the same way
		// on every request, so this stays a debug line (WP_DEBUG only).
		blocklane_pro_log( 'Blocklane: autoload miss for class ' . $class . ' - not in inc/classmap.php. Regenerate with `php bin/generate-classmap.php`; a stale map cannot pass CI or the dist build.' );
		return;
	}
	// require_once: a file that declares two classes is never included twice;
	// a mapped-but-missing file fails here, naming the path.
	require_once BLOCKLANE_PRO_PATH . '/' . $map[ $class ];
}
spl_autoload_register( 'blocklane_pro_autoload' );

// The winner's hold on the free plugin's row, and the installer that fetches
// it from wordpress.org, live in the Pro-only `sibling` unit
// (inc/sibling/lifecycle.php, the first Modules::lifecycle() row) — never in
// this file, because this file ships in the free zip and the directory build
// may carry no code that installs or (de)activates plugins.

// THE SHADOW NOTICE — the winner's one line about an abnormal loser. A copy
// of this tree that loaded after the winner and did NOT rank below it (a
// second copy of the same edition, or a higher edition whose own edition file
// the winner could not read) defines BLOCKLANE_PRO_SHADOWED in its main
// file's guard and registers nothing. The constant is read AT RENDER TIME:
// the loser's file scope runs after this file's, in the same request, so it
// does not exist yet when this hook is registered. The normal free-under-Pro
// case defines nothing and this prints nothing.
add_action( is_multisite() ? 'network_admin_notices' : 'admin_notices', 'blocklane_pro_admin_notice_shadowed' );

function blocklane_pro_admin_notice_shadowed(): void {
	if ( ! defined( 'BLOCKLANE_PRO_SHADOWED' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>' . esc_html(
		sprintf(
			/* translators: 1: this plugin's name, 2: the other copy's directory name. */
			__( '%1$s: a second Blocklane plugin at %2$s/ is active but not running, because this one already is. Deactivate or delete one of them.', 'blocklane' ),
			blocklane_pro\Branding::plugin_name(),
			basename( dirname( BLOCKLANE_PRO_SHADOWED ) )
		)
	) . '</p></div>';
}

/**
 * Seed empty autoloaded rows once per version, for the rows a unit READS on
 * every request.
 *
 * An absent row costs a miss query on every request; an empty autoloaded row
 * costs nothing, and every store normalizes an empty array exactly like an
 * absent row. The same six-line idiom was hand-copied into four lifecycle
 * files (#850); this is the one body. Add-if-null only: a row that exists is
 * never touched, so the listener is idempotent and edition-agnostic (the
 * listener rule in Version_Migration's docblock).
 *
 * @param string ...$options Option names to seed.
 * @return void
 */
function blocklane_pro_seed_options( string ...$options ): void {
	foreach ( $options as $option ) {
		if ( null === get_option( $option, null ) ) {
			add_option( $option, array(), '', true );
		}
	}
}

/**
 * Register the seed above as a blocklane_pro_version_changed listener.
 *
 * Called at file scope by a unit's lifecycle file — the unit that READS the
 * row (the seed belongs to the reader, because the miss query is the
 * reader's cost, #849), never by an unrelated unit both editions happen to
 * carry and never by the per-request render path.
 *
 * @param string ...$options Option names to seed.
 * @return void
 */
function blocklane_pro_seed_on_version_change( string ...$options ): void {
	add_action(
		'blocklane_pro_version_changed',
		static function () use ( $options ): void {
			blocklane_pro_seed_options( ...$options );
		}
	);
}

// Each unit's lifecycle: constants, eager class loads, cron, deactivation
// hooks, and the version_changed listeners that do once-per-version
// housekeeping. AT FILE SCOPE, before any hook — License must be declared
// before the Patterns plugin's class_exists( …, false ) probe can read it,
// and that plugin loads first. See Modules::lifecycle().
blocklane_pro\Modules::boot_lifecycle();

add_action( 'plugins_loaded', 'blocklane_pro_run_plugin' );

function blocklane_pro_run_plugin() {
	// Content first, before any theme check — see blocklane_pro_boot_content().
	blocklane_pro_boot_content();

	// Obligations next, ALSO before any theme check — see blocklane_pro_boot_lifecycle().
	blocklane_pro_boot_lifecycle();

	// The editing surface needs a block theme: the extensions enhance core
	// blocks in the block editor, the Menu Designer works in template parts,
	// and the dashboard's whole model is Site Editor shaped. It does NOT need
	// any PARTICULAR theme — the companion theme is free, so requiring it
	// never protected anything; what people pay for is the license key —
	// installs, updates and support, enforced by the update gateway, not by
	// anything in the running plugin.
	if ( ! wp_is_block_theme() ) {
		add_action( is_multisite() ? 'network_admin_notices' : 'admin_notices', 'blocklane_pro_admin_notice_requires_block_theme' );
		return;
	}

	// Below the gate on purpose: Settings, Helper, Block_Branding and Modules
	// are the editing surface. The dashboard + core always load, so you can
	// never lock yourself out of the admin UI.
	blocklane_pro\Settings::get_instance();
	blocklane_pro\Helper::get_instance();
	blocklane_pro\Block_Branding::boot();

	// The notice for blocklane_pro_scan_stale_forks() (which runs in the
	// lifecycle boot, on every theme — only the notice is Site-Editor-bound
	// and stays gated here): dismissible via a nonce'd link (the dismissal
	// must survive page loads; a later version-change rescan re-raises it
	// only if the forks still carry the dead slugs). An 'error' state is
	// NOT dismissible — an unresolvable scan staying visible is the point.
	add_action(
		'admin_init',
		static function () {
			if (
				isset( $_GET['blocklane_pro_dismiss_stale_forks'] )
				&& current_user_can( 'manage_options' )
				&& check_admin_referer( 'blocklane_pro_dismiss_stale_forks' )
			) {
				delete_option( 'blocklane_pro_ct_stale_template_forks' );
				wp_safe_redirect( remove_query_arg( array( 'blocklane_pro_dismiss_stale_forks', '_wpnonce' ) ) );
				exit;
			}
		}
	);
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$blocklane_pro_stored = get_option( 'blocklane_pro_ct_stale_template_forks' );
			if ( ! is_array( $blocklane_pro_stored ) || ! $blocklane_pro_stored ) {
				return;
			}
			// Wrapped state since the #157/#148 fix; a bare list is a row
			// written by a pre-0.9.0 scan and reads as findings.
			$blocklane_pro_wrapped = isset( $blocklane_pro_stored['state'] );
			$blocklane_pro_state   = $blocklane_pro_wrapped ? (string) $blocklane_pro_stored['state'] : 'found';
			$blocklane_pro_stale   = $blocklane_pro_wrapped
				? array_map( 'strval', (array) ( $blocklane_pro_stored['findings'] ?? array() ) )
				: array_map( 'strval', $blocklane_pro_stored );

			if ( 'error' === $blocklane_pro_state ) {
				// No Dismiss link: a scan that could not finish must stay
				// visible until the admin_init retry resolves it.
				echo '<div class="notice notice-warning"><p>';
				printf(
					/* translators: %s: plugin name. */
					esc_html__( '%s could not finish checking your customized templates for retired presets; it will retry automatically.', 'blocklane' ),
					esc_html( blocklane_pro\Branding::plugin_name() )
				);
				echo '</p></div>';
				return;
			}
			if ( 'found' !== $blocklane_pro_state || ! $blocklane_pro_stale ) {
				return;
			}
			$blocklane_pro_dismiss = wp_nonce_url(
				add_query_arg( 'blocklane_pro_dismiss_stale_forks', '1' ),
				'blocklane_pro_dismiss_stale_forks'
			);
			echo '<div class="notice notice-warning"><p>';
			printf(
				/* translators: 1: plugin name, 2: comma-separated template and template-part names. */
				esc_html__( '%1$s: these customized templates and template parts still use text and spacing presets retired by the current theme, so their sizes no longer apply: %2$s. Open each in the Site Editor and reapply current sizes, or reset it to its default.', 'blocklane' ),
				esc_html( blocklane_pro\Branding::plugin_name() ),
				esc_html( implode( ', ', $blocklane_pro_stale ) )
			);
			echo ' <a href="' . esc_url( $blocklane_pro_dismiss ) . '">' . esc_html__( 'Dismiss', 'blocklane' ) . '</a>';
			echo '</p></div>';
		}
	);

	// Boot each enabled feature module's runtime from the manifest (see the
	// Modules class). Safe mode / the blocklane_pro_load_module filter gate them.
	blocklane_pro\Modules::boot();
}

/**
 * Boot the plugin's OBLIGATIONS — the lifecycle machinery whose work exists
 * whatever theme is active. The block-theme gate in blocklane_pro_run_plugin()
 * may withhold SURFACES (UI, editor assets, the modules' REST controllers,
 * feature modules); it must never withhold obligations — and the lifecycle
 * units' OWN REST routes (license, scripts, abilities, the child-theme tool)
 * register above the gate, each behind its own toggle read fail-closed from
 * the stored row (#873): a baked mu-plugin executes on classic themes, a
 * wipe job's rows sit in the options table on classic themes, and a version
 * bump happened whatever theme is active. Everything here ran below the gate
 * until #142/#155 proved that starves it silently.
 *
 * ATOMICITY CONSTRAINT: Version_Migration::maybe_run_after_update() advances
 * the stamp even when listeners fail (deliberate — see that class). So the
 * trigger and ALL blocklane_pro_version_changed listeners live together on
 * this side of the gate: hoisting the trigger while leaving any listener
 * gated would stamp past it permanently on classic themes.
 *
 * @return void
 */
function blocklane_pro_boot_lifecycle() {
	blocklane_pro\Version_Migration::boot();

	// Forked CT single templates / parts that predate the 0.7.0 design system
	// keep retired preset slugs. Detect + tell, never touch (#115); the scan
	// runs on every theme (the finding is about stored rows), the NOTICE
	// renders only where the Site Editor exists (below the gate). The
	// admin_init retry drains the 'error' state: the version stamp advances
	// past failed listeners by design, so version_changed can never be the
	// retry mechanism (#148's class, applied here per #157).
	add_action( 'blocklane_pro_version_changed', 'blocklane_pro_scan_stale_forks' );

	// A theme switch changes the scan's VANTAGE (it is scoped to the active
	// stylesheet's wp_theme term), so a verdict recorded under one theme says
	// nothing under the next — a classic-theme upgrade would otherwise
	// false-certify Blocklane's own forks clean forever (#166). The scan
	// cannot run HERE: after_switch_theme fires before init registers the
	// wp_theme taxonomy, and a tax_query against an unregistered taxonomy
	// matches nothing — the exact blind-scan this flag exists to prevent.
	// Flag now, rescan on the next capable admin load below.
	add_action(
		'after_switch_theme',
		static function () {
			update_option( 'blocklane_pro_ct_stale_forks_rescan', 1, false );
		}
	);
	add_action(
		'admin_init',
		static function () {
			// Capability-gated: the retry re-runs a bounded but real scan,
			// and heartbeat/admin-ajax from low-cap users must not pay for
			// it on every request while an error state persists (#173).
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$blocklane_pro_forks_state  = get_option( 'blocklane_pro_ct_stale_template_forks' );
			$blocklane_pro_forks_rescan = (bool) get_option( 'blocklane_pro_ct_stale_forks_rescan' );
			if ( $blocklane_pro_forks_rescan
				|| ( is_array( $blocklane_pro_forks_state ) && 'error' === ( $blocklane_pro_forks_state['state'] ?? '' ) ) ) {
				delete_option( 'blocklane_pro_ct_stale_forks_rescan' );
				blocklane_pro_scan_stale_forks();
			}
		}
	);

	// Stale baked runtimes: reap once per version change. The list and the
	// sweep live in Bake_Reaper — ONE body, shared verbatim with
	// uninstall.php (#155, #156); per-node provenance per #154.
	add_action( 'blocklane_pro_version_changed', array( 'blocklane_pro\\Bake_Reaper', 'reap' ) );

}

/**
 * Scan for forked templates AND template parts carrying preset slugs the
 * 0.7.0 design system retired (fontSize "hero", spacing "huge"/"xx-large" —
 * where none of them resolve, type scale and rhythm silently collapse).
 *
 * Scope is provenance and resolution, not filename shape (#157):
 * - only forks carrying the ACTIVE stylesheet's wp_theme term can render
 *   (core's own ownership model), so only those are scanned — a stale fork
 *   belonging to another installed theme can degrade nothing;
 * - each marker group applies only while its slug does NOT resolve in the
 *   merged settings — a foreign theme that defines fontSize "hero" makes
 *   those references healthy, and flagging them would be the false positive.
 * The old scan looked at wp_template only, filtered to single-*, unscoped to
 * theme — three ways to certify a degraded site clean.
 *
 * Outcome states in blocklane_pro_ct_stale_template_forks:
 * array{state:'found',findings:string[]} | array{state:'error'} | absent
 * (= clean; absence is safe here because the trigger is version_changed +
 * the admin_init error retry, not option absence). A failed scan must stay
 * visibly unresolved — deleting the option on error certified degraded
 * sites clean (#148's class).
 *
 * @return void
 */
function blocklane_pro_scan_stale_forks() {
	global $wpdb;

	// Resolution guard: drop marker groups whose slug resolves.
	$blocklane_pro_settings   = function_exists( 'wp_get_global_settings' ) ? wp_get_global_settings() : array();
	$blocklane_pro_font_slugs = array();
	foreach ( (array) ( $blocklane_pro_settings['typography']['fontSizes'] ?? array() ) as $blocklane_pro_origin ) {
		foreach ( (array) $blocklane_pro_origin as $blocklane_pro_size ) {
			if ( is_array( $blocklane_pro_size ) && isset( $blocklane_pro_size['slug'] ) ) {
				$blocklane_pro_font_slugs[] = (string) $blocklane_pro_size['slug'];
			}
		}
	}
	$blocklane_pro_space_slugs = array();
	foreach ( (array) ( $blocklane_pro_settings['spacing']['spacingSizes'] ?? array() ) as $blocklane_pro_origin ) {
		foreach ( (array) $blocklane_pro_origin as $blocklane_pro_size ) {
			if ( is_array( $blocklane_pro_size ) && isset( $blocklane_pro_size['slug'] ) ) {
				$blocklane_pro_space_slugs[] = (string) $blocklane_pro_size['slug'];
			}
		}
	}

	$blocklane_pro_markers = array();
	if ( ! in_array( 'hero', $blocklane_pro_font_slugs, true ) ) {
		$blocklane_pro_markers[] = '"fontSize":"hero"';
	}
	foreach ( array( 'huge', 'xx-large' ) as $blocklane_pro_slug ) {
		if ( in_array( $blocklane_pro_slug, $blocklane_pro_space_slugs, true ) ) {
			continue;
		}
		$blocklane_pro_markers[] = 'spacing--' . $blocklane_pro_slug;
		// blockGap is the one spacing carrier with NO HTML serialization —
		// it renders via a server-generated rule, so a fork whose only dead
		// reference is blockGap matches neither CSS-var marker. The trailing
		// quote keeps a future huge-2 slug unmatched.
		$blocklane_pro_markers[] = 'var:preset|spacing|' . $blocklane_pro_slug . '"';
	}

	if ( ! $blocklane_pro_markers ) {
		delete_option( 'blocklane_pro_ct_stale_template_forks' );
		return; // Every group resolves — nothing can be degraded.
	}

	$blocklane_pro_stale = array();
	$blocklane_pro_forks = get_posts(
		array(
			'post_type'   => array( 'wp_template', 'wp_template_part' ),
			'post_status' => array( 'publish', 'draft' ),
			// Covers the doubled population (templates AND parts, #188);
			// a site carrying more forks than this is out of scan scope.
			'numberposts' => 200,
			// Core's ownership model: a fork renders only when it carries
			// the active stylesheet's wp_theme term (get_block_templates).
			'tax_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded scan, once per version change.
				array(
					'taxonomy' => 'wp_theme',
					'field'    => 'name',
					'terms'    => get_stylesheet(),
				),
			),
		)
	);
	if ( '' !== $wpdb->last_error ) {
		update_option( 'blocklane_pro_ct_stale_template_forks', array( 'state' => 'error' ), false );
		return;
	}
	foreach ( $blocklane_pro_forks as $blocklane_pro_fork ) {
		$blocklane_pro_content = (string) $blocklane_pro_fork->post_content;
		foreach ( $blocklane_pro_markers as $blocklane_pro_marker ) {
			if ( false !== strpos( $blocklane_pro_content, $blocklane_pro_marker ) ) {
				$blocklane_pro_stale[] = '' !== $blocklane_pro_fork->post_title
					? $blocklane_pro_fork->post_title
					: $blocklane_pro_fork->post_name;
				break;
			}
		}
	}
	if ( $blocklane_pro_stale ) {
		update_option(
			'blocklane_pro_ct_stale_template_forks',
			array(
				'state'    => 'found',
				'findings' => $blocklane_pro_stale,
			),
			false
		);
	} else {
		delete_option( 'blocklane_pro_ct_stale_template_forks' );
	}
}

/**
 * Boot the content-rendering runtimes.
 *
 * Called before any theme check, and gated by nothing but the edition, safe
 * mode and each runtime's own Advanced toggle. These render what the user has ALREADY
 * built, and the rule is the same one the license gate obeys a few lines
 * down: content must never depend on plugin state the user did not choose.
 *
 * Theme state is exactly that kind of dependency. Until 2026-08 these ran
 * from generated mu-plugins, which loaded outside the plugin and therefore
 * outside its theme guard, so switching themes never took someone's content
 * types off the air. Removing the bake quietly moved them inside the guard;
 * calling them here restores the property deliberately rather than by
 * accident of where the code lived.
 *
 * What is NOT here: popups and the Menu Designer, which the module manifest
 * boots behind the theme check. That is the existing doctrine, not an
 * oversight — those are site chrome ("Popups are site chrome, not
 * content"), and the mega menu
 * is block-theme machinery by construction.
 *
 * The GUARANTEE, and its closure: every runtime in Modules::content() is
 * proven present before any of them loads, and a miss is a typed Module_Miss
 * in its own phase — logged, recorded, and shown to administrators in that
 * phase's own words. Until 2026-09 these six requires had no check at all, so
 * a torn deploy missing one was a fatal on every request, every theme and
 * every SAPI. The docblock here used to argue that the only alternative was a
 * file_exists guard, and that such a guard would be the silent-miss path (a
 * saved carousel rendering as nothing with no notice). That was true of THAT
 * alternative; the third option — the same typed preflight the modules
 * already had — was simply unbuilt, and is what runs now (DEFERRED-WORK #508).
 *
 * What remains outside the closure, written down beside it: the classes a
 * runtime reaches at file scope beyond its row's `classes` list, and anything
 * blocklane-pro.php itself reaches before this call. Those still fail as the
 * autoloader's contract says — a fatal naming the class or path, never a
 * silent skip.
 *
 * Safe mode is not a gate here, deliberately: each runtime checks it itself
 * and returns (the two editor stand-ins inside Standin::register(), the one
 * call their files make), which is the shipped contract, and several declare
 * functions other code calls whether or not they do any work.
 *
 * The mega menu's editor stand-in (runtime:mega-menu-standin) is content —
 * data preservation carried by both editions — and boots here, before the
 * theme gate; the real mega menu block boots behind that gate as before and
 * the stand-in yields to it through the server registry. Both stand-ins (the
 * form step's is runtime:form-step-standin) go through one door,
 * blocklane_pro\Standin, which owns their hooks, their alarm and their
 * registry read.
 *
 * The icon collection's registrar (runtime:icon-collection) boots here for the
 * same reason: a core/icon block naming blocklane-pro/* must keep rendering
 * under either edition, whatever the Icon Library extension's toggle says.
 *
 * @return void
 */
function blocklane_pro_boot_content() {
	blocklane_pro\Modules::boot_content();
}

/**
 * The one theme notice: a block theme is required, any block theme.
 *
 * Replaces the pair that demanded the companion theme by slug and version.
 * Anything the user has already built keeps rendering while this shows —
 * blocklane_pro_boot_content() runs before the check.
 */
function blocklane_pro_admin_notice_requires_block_theme() {
	$message = sprintf(
		/* translators: 1: plugin name, 2: link to manage themes */
		__( '%1$s needs a block theme. Activate any block theme to use its editing tools. %2$s', 'blocklane' ),
		esc_html( blocklane_pro\Branding::plugin_name() ),
		'<a href="' . esc_url( admin_url( 'themes.php' ) ) . '">' . esc_html__( 'Manage themes', 'blocklane' ) . '</a>'
	);

	echo wp_kses_post( '<div class="notice notice-error"><p>' . $message . '</p></div>' );
}


