<?php
/**
 * Feature-module manifest — the single source of truth for the plugin's gated
 * feature modules (the dashboard screens that have their own runtime + REST).
 *
 * To add a feature module you add ONE entry here: its runtimes (function
 * files), its classes (loaded eagerly through the classmap), its boot class
 * (instantiated on load) and/or its REST controller (registered on
 * rest_api_init). Both blocklane-pro.php (boot) and Settings::register_rest_routes()
 * (REST) drive off this list, so a module is wired in one place.
 *
 * Each module is gated two ways: define BLOCKLANE_PRO_SAFE_MODE to disable them
 * all for troubleshooting, or return false from the `blocklane_pro_load_module`
 * filter to disable one. A disabled module loads neither its runtime nor its
 * REST routes, while the dashboard shell + core (Settings/Helper) always load —
 * so you can never lock yourself out of the admin UI. The license is NOT a
 * gate: a key buys installs, updates and support, and every module works
 * identically with or without one (see the License class docblock).
 *
 * Not listed here: the four hand-wired service controllers (license,
 * scripts, child-theme, abilities), registered by
 * Settings::register_rest_routes(); each implements Rest_Registrable like the
 * manifest's controllers, so every REST controller carries the one contract.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Modules {

	/**
	 * Modules skipped at boot — nothing of them was required or instantiated —
	 * keyed by slug. register_rest() consults it: a module that did not boot
	 * never gets routes.
	 *
	 * @var array<string, Module_Miss>
	 */
	private static array $skipped = array();

	/**
	 * Modules that booted whole but whose REST controller could not be loaded
	 * at rest_api_init, keyed by slug. Its own slot and its own wording: a
	 * routes miss leaves a booted module booted (#542).
	 *
	 * @var array<string, Module_Miss>
	 */
	private static array $unrouted = array();

	/**
	 * Content runtimes that did not load, keyed by unit id. Its own slot and
	 * its own sentence: the module's management side is unaffected and may be
	 * running fine, while content the user already built stops rendering.
	 *
	 * @var array<string, Module_Miss>
	 */
	private static array $content_missed = array();

	/**
	 * Lifecycle files that did not load, keyed by unit id. Its own slot for
	 * the opposite reason: nothing renders differently, so without this the
	 * miss would be entirely silent.
	 *
	 * @var array<string, Module_Miss>
	 */
	private static array $lifecycle_missed = array();
	/**
	 * Extensions whose ADMIN runtime file was missing (PHASE_ADMIN, #875).
	 *
	 * @var array<string, Module_Miss>
	 */
	private static array $admin_missed = array();

	/** Whether the module admin notice is hooked (one callback prints every list). */
	private static bool $notice_hooked = false;

	/**
	 * The manifest. `runtimes` = the module's non-class files (function files
	 * with file-scope hooks), required in the order listed, relative to /inc.
	 * `classes` = the module's BOOT-TIME CLOSURE other than `boot`/`controller`:
	 * every first-party class its boot reaches before plugins_loaded returns
	 * (the constructor and what the constructor constructs), loaded EAGERLY
	 * through the classmap when the module boots — invariant B: a loaded-state
	 * probe, class_exists( X, false ), must never answer false merely because
	 * nothing referenced X yet in this request. `boot` = a Bootable whose
	 * get_instance() runs on load; `controller` = a Rest_Registrable
	 * instantiated on rest_api_init. Either may be null.
	 *
	 * WHOLE OR ABSENT. boot_module() proves a module whole before anything of
	 * it loads: every runtime is a file, every class in `classes ∪ {boot}` is
	 * declared or mapped to a file that exists (phase A); then each mapped
	 * file is required and must actually declare its class (phase B — a stale
	 * map is caught here, before any runtime hook attaches); only then are the
	 * runtimes required and the boot class instantiated (phase C). Classes
	 * load BEFORE runtimes on purpose — a phase-B miss leaves no hook behind.
	 * The residual: the class files phase B included before a miss stay
	 * declared (PHP cannot unload them), so a probe that reads "declared" as
	 * "booted" can answer yes for a refused module — list a probe-read class
	 * LAST in `classes` so a sibling's miss leaves it undeclared.
	 * Outside the guarantee, and documented as such: interfaces and parent
	 * classes an `implements`/`extends` clause autoloads during phase B
	 * (plugin-wide files, not module files — a missing one is the autoloader's
	 * fatal naming the path); classes first reached
	 * from later hooks (the Abilities\Manage_* classes at wp_abilities_api_init);
	 * Extensions_Handler::load_extensions()'s conditional function-file
	 * requires; and the CONTENT RUNTIMES' file-scope classes (Content_Toggle at
	 * plugins_loaded in seo, forms and carousel; Block_Suite at init in
	 * carousel and forms). The content runtimes THEMSELVES are inside:
	 * content() is the table boot_content() drives through boot_module() and
	 * record(), so a missing runtime file is a typed Module_Miss with its own
	 * notice, never a fatal (built 2026-09-05, DEFERRED-WORK #508; the
	 * paragraph that said otherwise was rewritten 2026-09-25, #906). What stays
	 * OUTSIDE: every class blocklane-pro.php itself reaches at file scope or on
	 * plugins_loaded before Modules runs, and the file-scope classes named
	 * above — a torn deploy missing one of those is the autoloader's fatal
	 * naming the class (safe mode rescues those doors). Recovery mode emails
	 * and does not pause a plugin outside its own session; the remedy is
	 * restoring the file or renaming the plugin directory.
	 *
	 * ITERATION ORDER IS LOAD-BEARING: `advanced` must precede `seo`, `forms`,
	 * `ai-mcp` and `ai-tools`. boot_on() consults the Advanced class for their
	 * boot toggles and falls back to the stored option when it is not loaded;
	 * reordering these entries changes which branch answers.
	 *
	 * @return array<string, array{runtimes: list<string>, classes: list<class-string>, boot: class-string<Bootable>|null, controller: class-string<Rest_Registrable>|null}>
	 */
	public static function all() {
		return array(
			'extensions'     => array(
				'runtimes'   => array(),
				'classes'    => array(),
				'boot'       => Extensions_Handler::class,
				'controller' => Extensions_Controller::class,
			),
			'dynamic-values' => array(
				'runtimes'   => array(
					// The rendering surfaces (bindings source, shortcode, inline
					// tokens) are a function file; blocklane_pro_boot_content()
					// already loaded it license-ungated, so this is a no-op that
					// keeps the manifest honest about what the module needs.
					'dynamic-values/mu-runtime.php',
				),
				'classes'    => array( Dynamic_Values::class ),
				'boot'       => Dynamic_Values_Integration::class,
				'controller' => Dynamic_Values_Controller::class,
			),
			'content-types'  => array(
				'runtimes'   => array(),
				'classes'    => array( Slug_Guard::class, Content_Types::class, Taxonomies::class, Wipe_Runner::class ),
				'boot'       => Content_Types_Integration::class,
				'controller' => Content_Types_Controller::class,
			),
			'menu-designer'  => array(
				'runtimes'   => array(
					// The front-end surfaces (the mega-menu block, the menu
					// template part area, the mobile-menu replacement, the
					// preview endpoint) are a function file with file-scope
					// hooks; this is its only loader (site chrome boots behind
					// the block-theme check by doctrine).
					'menu-designer/runtime.php',
				),
				'classes'    => array(),
				'boot'       => Menu_Designer_Integration::class,
				'controller' => null,
			),
			'popups'         => array(
				'runtimes'   => array(
					// The CPT + render surfaces are a function file with
					// file-scope hooks; this is its only loader (popups are
					// site chrome, booted behind the block-theme check).
					'popups/runtime.php',
				),
				'classes'    => array(),
				'boot'       => Popups_Integration::class,
				'controller' => Popups_Controller::class,
			),
			'site-lock'      => array(
				'runtimes'   => array(),
				// Site_Lock LAST on purpose: five class_exists( Site_Lock, false )
				// probes read "declared" as "the lock is live", and phase B declares
				// classes in this order — a stale-map miss on a sibling then leaves
				// Site_Lock undeclared and every probe honest. (A miss on the boot
				// class itself still leaves the classes declared: the residual the
				// manifest docblock records.)
				'classes'    => array( Site_Lock_Cache::class, Site_Lock_Theme_Switch::class, Site_Lock::class ),
				'boot'       => Site_Lock_Gate::class,
				'controller' => Site_Lock_Controller::class,
			),
			'security'       => array(
				'runtimes'   => array(),
				// The boot-time closure: Security's constructor runs apply(),
				// whose get() derives known(), which asks Edition which unit
				// contributes each key (the forms content row's precedent).
				'classes'    => array( Edition::class ),
				'boot'       => Security::class,
				'controller' => Security_Controller::class,
			),
			'advanced'       => array(
				'runtimes'   => array(),
				// The boot-time closure: Advanced's constructor constructs these
				// when their toggles are on. Listed (and so preflighted and
				// eager-loaded) whether or not the toggle is on: "whole" must not
				// depend on which toggles a site has flipped (#543).
				'classes'    => array( Comments_Disabler::class, Blog_Features_Disabler::class ),
				'boot'       => Advanced::class,
				'controller' => Advanced_Controller::class,
			),
			'seo'            => array(
				// The emission runtime is NOT here — inc/seo/runtime.php loads
				// license-ungated from blocklane-pro.php (content philosophy,
				// like Content Types). This module is only the management
				// side: the settings store + REST for the SEO screen, and the
				// SEO abilities.
				'runtimes'   => array(),
				'classes'    => array( Seo_Import::class ),
				'boot'       => Seo::class,
				'controller' => Seo_Controller::class,
			),
			'forms'          => array(
				// The block suite + submission runtime is NOT here —
				// inc/forms/runtime.php loads license-ungated from
				// blocklane-pro.php (content philosophy, like SEO). This
				// module is only the management side: the settings store +
				// submissions REST for the inbox screen.
				'runtimes'   => array(),
				'classes'    => array(),
				'boot'       => Forms::class,
				'controller' => Forms_Controller::class,
			),
			'ai-mcp'   => array(
				// The boot-time closure: the loader's constructor constructs
				// the index, the two validators and the preview handler, and
				// boots Editor_Refresh, unconditionally — so they are listed,
				// preflighted and eager-loaded with it (#543). The ability
				// classes (Abilities\Manage_*) are first reached at
				// wp_abilities_api_init and stay outside the guarantee. The
				// MCP-side controller (adapter detection + skill download) is
				// wired by Settings like the other management controllers.
				'runtimes'   => array(),
				'classes'    => array( Abilities\Pattern_Index::class, Abilities\Validators\Block_Validator::class, Abilities\Validators\Design_Linter::class, Abilities\Preview\Preview_Handler::class, Abilities\Editor_Refresh::class ),
				'boot'       => Abilities\Loader::class,
				'controller' => null,
			),
			'ai-tools' => array(
				// In-editor free-form rewrite. The loader registers the
				// content-rewrite ability on wp_abilities_api_init; the editor
				// UI ships in the extensions bundle, gated on the localized
				// availability flag this runtime supplies (the route string
				// belongs to this unit, not to the handler every edition ships).
				'runtimes'   => array( 'abilities/ai-tools/editor-config.php' ),
				'classes'    => array(),
				'boot'       => Ai_Tools\Loader::class,
				'controller' => null,
			),
		);
	}

	/**
	 * THE CONTENT RUNTIMES — the render path, required before the theme gate.
	 *
	 * These six requires sat outside every guarantee this class makes until
	 * this table existed: blocklane_pro_boot_content() required them with no
	 * check at all, so a torn deploy missing any one of them was a fatal on
	 * EVERY request, every theme, every SAPI. The bootstrap's own docblock
	 * argued that a file_exists guard would be worse (a saved carousel
	 * rendering as nothing with no notice), and it was right — but the third
	 * option was always available and merely unbuilt: the same typed preflight
	 * the modules already get. That is what this is (DEFERRED-WORK #508).
	 *
	 * Same row shape as all(), so boot_module() drives both. `boot` is null
	 * throughout: content runtimes are function files with file-scope hooks,
	 * never Bootable singletons.
	 *
	 * ONE ORDER IS LOAD-BEARING, and only one: content-types registers the
	 * post types that dynamic-values' bindings and the seo runtime then read.
	 * Nothing else here depends on the order of the rows.
	 *
	 * In particular the CONTRIBUTING UNITS (popups:pro, block:form-file,
	 * security:auto-update-plugins, security:auto-update-themes) sit above
	 * their parents ON PURPOSE and are not to be reordered. A contributor
	 * touches its parent only inside callbacks, on the parent's own hooks, so
	 * it is safe by CONTRACT rather than by order — and reordering could not
	 * make it safe anyway: popups:pro's and the two security rows' parents
	 * are not content rows at all (inc/popups/runtime.php is a Modules::all()
	 * runtime, Security a Modules::all() boot class, both booted after the
	 * theme gate), so under a classic theme they never load.
	 * Contributor-first is the better failure mode: a violation of the
	 * contract surfaces as a fatal at plugins_loaded under Pro instead of
	 * hiding behind a lucky order. The contract is asserted by
	 * bin/contributor-scope-check.php, wiring check 7 (#1046).
	 *
	 * Keyed by MANIFEST UNIT ID, not module slug — the edition filter reads
	 * these keys, and a unit can hold `content` in free while its management
	 * half stays pro (content-types and dynamic-values do exactly that, so a
	 * site keeps rendering what Pro built after Pro is deactivated).
	 *
	 * @return array<string, array{runtimes: list<string>, classes: list<class-string>, boot: class-string<Bootable>|null, controller: class-string<Rest_Registrable>|null}>
	 */
	public static function content(): array {
		return array(
			'runtime:content-types'  => array(
				'runtimes'   => array( 'content-types/mu-runtime.php' ),
				'classes'    => array(),
				'boot'       => null,
				'controller' => null,
			),
			'runtime:dynamic-values' => array(
				'runtimes'   => array( 'dynamic-values/mu-runtime.php' ),
				'classes'    => array(),
				'boot'       => null,
				'controller' => null,
			),
			// The mega menu's editor stand-in: data preservation in BOTH
			// editions (Amendment 14); inert under Pro by the server registry.
			// Its runtime is one Standin::register() call, so the helper is in
			// the row's preflight closure: a torn deploy missing it is a typed
			// Module_Miss, never a fatal. A `kind: standin` unit's row must
			// name its own <dir>/runtime.php (bin/standin-shape-check.php,
			// wiring check 8, reads these rows by check 7's grammar).
			'runtime:mega-menu-standin' => array(
				'runtimes'   => array( 'mega-menu-standin/runtime.php' ),
				'classes'    => array( Standin::class ),
				'boot'       => null,
				'controller' => null,
			),
			// The form step's editor stand-in: data preservation in BOTH
			// editions (spec 2026-09-24 D4); inert under Pro by the server
			// registry, inert in either edition while the forms toggle is off.
			// One Standin::register() call, the helper in the closure as above.
			'runtime:form-step-standin' => array(
				'runtimes'   => array( 'form-step-standin/runtime.php' ),
				'classes'    => array( Content_Toggle::class, Standin::class ),
				'boot'       => null,
				'controller' => null,
			),
			// The popups module's Pro contributions (manifest rule 6): the
			// file that fills the three registries popups/runtime.php reads.
			// Pro-only by edition; gated on the popups toggle like its parent.
			'popups:pro'                => array(
				'runtimes'   => array( 'popups/pro/runtime.php' ),
				'classes'    => array( Content_Toggle::class ),
				'boot'       => null,
				'controller' => null,
			),
			// The forms module's file upload field, write side (manifest rule
			// 6): registers blocklane/form-file through the field-type seam.
			// Pro-only by edition; gated on the forms toggle like its parent.
			'block:form-file'           => array(
				'runtimes'   => array( 'forms/file-upload/runtime.php' ),
				'classes'    => array( Content_Toggle::class ),
				'boot'       => null,
				'controller' => null,
			),
			// The security module's two Pro settings (manifest rule 6): each
			// file hooks blocklane_pro_security_enforce and enforces its own
			// key. Pro-only by edition.
			'security:auto-update-plugins' => array(
				'runtimes'   => array( 'security/auto-update-plugins/runtime.php' ),
				'classes'    => array(),
				'boot'       => null,
				'controller' => null,
			),
			'security:auto-update-themes'  => array(
				'runtimes'   => array( 'security/auto-update-themes/runtime.php' ),
				'classes'    => array(),
				'boot'       => null,
				'controller' => null,
			),
			// The icon collection's registrar: data preservation in BOTH
			// editions (Amendment 15). Placed core/icon blocks naming
			// blocklane-pro/* keep rendering whichever edition is installed —
			// the picker and the cloud routes stay Pro.
			'runtime:icon-collection'   => array(
				'runtimes'   => array( 'icon-collection/runtime.php' ),
				'classes'    => array(),
				'boot'       => null,
				'controller' => null,
			),
			'module:seo'            => array(
				'runtimes'   => array( 'seo/runtime.php' ),
				'classes'    => array( Content_Toggle::class ),
				'boot'       => null,
				'controller' => null,
			),
			'module:forms'          => array(
				'runtimes'   => array( 'forms/runtime.php' ),
				// Edition: the registrar's view (known_blocks) asks it at init.
				'classes'    => array( Content_Toggle::class, Block_Suite::class, Edition::class ),
				'boot'       => null,
				'controller' => null,
			),
			'module:carousel'       => array(
				'runtimes'   => array( 'carousel/runtime.php' ),
				'classes'    => array( Content_Toggle::class, Block_Suite::class ),
				'boot'       => null,
				'controller' => null,
			),
			'module:extensions'     => array(
				'runtimes'   => array( 'extensions/loader/frontend-loader.php' ),
				'classes'    => array(),
				'boot'       => null,
				'controller' => null,
			),
			'service:scripts'       => array(
				'runtimes'   => array( 'scripts/runtime.php' ),
				'classes'    => array( Scripts::class ),
				'boot'       => null,
				'controller' => null,
			),
		);
	}

	/**
	 * THE UNIT LIFECYCLE FILES — each unit's work outside its own screen and
	 * routes: constants, eager class loads, cron, deactivation hooks, and the
	 * `blocklane_pro_version_changed` listeners that do once-per-version
	 * housekeeping.
	 *
	 * Unit id => path relative to /inc. ORDER IS LOAD-BEARING and follows the
	 * manifest's `requires` closure: `gateway` defines the constants `updates`
	 * builds its manifest URL from, and `license` must have required its class
	 * before `updates` probes for it with class_exists( …, false ).
	 *
	 * WHY FILE SCOPE, not plugins_loaded. The whole point of loading these
	 * early is that License is declared BEFORE ANY HOOK FIRES: the Patterns
	 * plugin's Supabase\Client::pro_license_declared() probes for it without
	 * autoloading, its docblock names this eagerness as the reason the probe
	 * is sound, and that plugin sorts BEFORE this one in active_plugins — so a
	 * plugins_loaded require would land after its file scope. Every one of its
	 * call sites is request-time, so file scope here is early enough; nothing
	 * later is. Loading here also puts every version_changed listener in place
	 * before Version_Migration::boot() fires the hook, which is the atomicity
	 * constraint the bootstrap already documented.
	 *
	 * THE SEED RULE. The seed for an option row lives in a lifecycle file of
	 * the unit that READS the row on every request — the miss query is the
	 * reader's cost — never in the runtime itself (the render path does not
	 * write) and never in an unrelated unit both editions happen to carry.
	 * That is why runtime:content-types, a shim both editions ship, has a
	 * lifecycle file of its own here: the seeds for the two definition rows
	 * it reads used to sit in module:content-types' file, which only Pro
	 * carries, and a free-only site paid two miss queries forever (#849).
	 * The one seeder body is blocklane_pro_seed_options() in bootstrap.php.
	 *
	 * @return array<string, string>
	 */
	public static function lifecycle(): array {
		return array(
			// The winner's hold on the free plugin's row and the wordpress.org
			// installer: FIRST, so the hold's option filters are registered
			// before any hook fires (where bootstrap.php used to put them).
			// Pro-only by manifest — the free zip carries neither file.
			'sibling'                    => 'sibling/lifecycle.php',
			// Translations, for the edition that is not in the directory — the
			// free build does not carry this file at all, because Plugin Check
			// flags the load_plugin_textdomain() CALL whether or not it runs.
			'service:textdomain'         => 'textdomain.php',
			// The gateway/license/updates chain first: `requires` orders it.
			'gateway'                    => 'gateway/constants.php',
			'license'                    => 'license/lifecycle.php',
			'updates'                    => 'updates/lifecycle.php',
			// Then each feature unit's own once-per-version housekeeping and
			// hook registrations. Order among these does not matter: every one
			// registers a blocklane_pro_version_changed listener or a cron
			// listener, and the hook fires long after all of them are in place
			// (Version_Migration runs at init 5).
			'runtime:content-types'      => 'content-types/runtime-lifecycle.php',
			'module:content-types'       => 'content-types/lifecycle.php',
			'module:seo'                 => 'seo/lifecycle.php',
			'module:popups'              => 'popups/lifecycle.php',
			'module:ai-mcp'              => 'abilities/lifecycle.php',
			'extension:class-manager'    => 'extensions/loader/class-manager/lifecycle.php',
			'extension:animation-designer' => 'extensions/loader/animation/lifecycle.php',
			'module:extensions'          => 'extensions/lifecycle.php',
			'module:security'            => 'security/lifecycle.php',
			'module:site-lock'           => 'site-lock/lifecycle.php',
			'service:scripts'            => 'scripts/lifecycle.php',
			'service:child-theme'        => 'child-theme-tool/lifecycle.php',
		);
	}

	/**
	 * Require every lifecycle file this edition carries (call at plugin-file
	 * scope, before any hook).
	 *
	 * A missing file is a typed Module_Miss in the lifecycle phase, not a
	 * fatal and not a silent skip: the consequence of a miss here is invisible
	 * by nature — nothing renders differently, a migration simply never ran —
	 * which is exactly why it needs its own slot and its own sentence.
	 *
	 * @return void
	 */
	public static function boot_lifecycle(): void {
		foreach ( self::lifecycle() as $unit => $rel ) {
			if ( ! Edition::has_role( $unit, 'lifecycle' ) ) {
				continue;
			}
			if ( ! is_file( BLOCKLANE_PRO_PATH . '/inc/' . $rel ) ) {
				self::record( new Module_Miss( $unit, Module_Miss::PHASE_LIFECYCLE, Module_Miss::RUNTIME_MISSING, $rel ) );
				continue;
			}
			require_once BLOCKLANE_PRO_PATH . '/inc/' . $rel;
		}
	}

	/**
	 * Whether a unit's lifecycle file loaded this request: the edition gives
	 * the unit the lifecycle role, the file is in the lifecycle table, and
	 * boot_lifecycle() recorded no miss for it.
	 *
	 * @param string $unit Manifest unit id.
	 */
	public static function lifecycle_loaded( string $unit ): bool {
		return isset( self::lifecycle()[ $unit ] ) && Edition::has_role( $unit, 'lifecycle' ) && ! isset( self::$lifecycle_missed[ $unit ] );
	}

	/**
	 * Boot every content runtime this edition carries (call on plugins_loaded,
	 * BEFORE the theme gate).
	 *
	 * Safe mode is deliberately NOT a gate here: each runtime checks it itself
	 * and returns (the two editor stand-ins inside Standin::register(), the one
	 * call their files make), which is the shipped contract, and several of
	 * them declare functions other code calls whether or not they do any work.
	 * Skipping the require would change that, and this commit moves code
	 * without changing what it does.
	 *
	 * @return void
	 */
	public static function boot_content(): void {
		foreach ( self::content() as $unit => $def ) {
			if ( ! Edition::has_role( $unit, 'content' ) ) {
				continue;
			}
			// A content row is a runtime FILE, never a class: boot_module()
			// would instantiate a named boot class before the theme gate and
			// with no toggle check. A row that names one is a source defect,
			// like a malformed manifest — refuse it loudly (#841).
			if ( null !== ( $def['boot'] ?? null ) ) {
				throw new \LogicException( esc_html( "Modules::content() row '{$unit}' names a boot class; content rows carry runtime files only." ) );
			}
			$miss = self::boot_module( $unit, $def, null, Module_Miss::PHASE_CONTENT );
			if ( null !== $miss ) {
				self::record( $miss );
			}
		}
	}

	/**
	 * Module slug => the Advanced toggle that gates the whole module's boot()
	 * (not just a management screen). Off means the module's runtime never
	 * loads at all. AI Abilities is opt-in — it grants an AI client write
	 * access to the site — so its registration is gated here, defaulting off.
	 *
	 * A typo here fails OPEN (boot_on() treats an unknown slug as "no toggle"),
	 * so the wiring is asserted by bin/toggle-wiring-check.php — keys are module
	 * slugs, values are Advanced toggle slugs, every opt-in screen has a row —
	 * which bin/wiring-check.php runs on every push and every dist build
	 * (`npm run check:wiring`). That gate is the one check: a WP_DEBUG runtime
	 * copy used to duplicate it with weaker coverage and a _doing_it_wrong()
	 * that could fire before headers (#502, #503).
	 */
	const BOOT_TOGGLES = array(
		'ai-mcp'   => 'ai-mcp',
		'ai-tools' => 'ai-tools',
		// SEO's management side (settings store, abilities, importer + its
		// CLI command) is an opt-in like the AI surfaces — merely loading it
		// registers write access (abilities, WP-CLI). The emission runtime
		// is license-ungated and gates itself on the same toggle.
		'seo'      => 'seo',
		// Forms mirrors SEO: the block suite + submission runtime is
		// license-ungated and self-gates on this toggle; the management
		// side (store + inbox REST) only loads while opted in.
		'forms'    => 'forms',
	);

	/**
	 * A toggle read straight off the stored option — the degraded-state
	 * fallback for when the Advanced class isn't loaded. This is the same
	 * source the license-ungated runtimes read through Content_Toggle, so the
	 * management/boot gates and the emission gates can never disagree: a
	 * stored ON keeps the feature's controls reachable, an absent or off
	 * store means off. Not routed through Content_Toggle itself: this serves
	 * ai-mcp and ai-tools too, which are not content toggles.
	 *
	 * @param string $toggle Toggle slug.
	 * @return bool
	 */
	private static function toggle_on_stored( $toggle ) {
		$stored = get_option( 'blocklane_pro_advanced', null );

		return is_array( $stored ) && ! empty( $stored[ $toggle ] );
	}

	/**
	 * Whether a module's boot is toggled on. True for modules with no boot
	 * toggle. When Advanced isn't loaded, this falls back to the STORED
	 * toggle value rather than a blanket answer: an opt-in that was never
	 * turned on stays off (absent store = off), while a stored ON can't
	 * strand a feature the front-end runtime is still honoring.
	 *
	 * @param string $slug Module slug.
	 * @return bool
	 */
	private static function boot_on( $slug ) {
		if ( ! isset( self::BOOT_TOGGLES[ $slug ] ) ) {
			return true;
		}
		if ( ! class_exists( Advanced::class, false ) ) {
			return self::toggle_on_stored( self::BOOT_TOGGLES[ $slug ] );
		}
		return Advanced::is_on( self::BOOT_TOGGLES[ $slug ] );
	}

	/**
	 * Whether a module should load. Off when safe mode is on, or when the
	 * blocklane_pro_load_module filter vetoes this slug. The license is not
	 * consulted — no module gates on it.
	 *
	 * @param string $slug
	 * @return bool
	 */
	public static function is_enabled( $slug ) {
		if ( defined( 'BLOCKLANE_PRO_SAFE_MODE' ) && BLOCKLANE_PRO_SAFE_MODE ) {
			return false;
		}
		/**
		 * Filter whether a Pro feature module loads. Return false to disable one.
		 *
		 * @param bool   $load Whether to load the module (default true).
		 * @param string $slug Module slug.
		 */
		return (bool) apply_filters( 'blocklane_pro_load_module', true, $slug );
	}

	/**
	 * Phase A — existence, before anything loads: for each class not yet
	 * declared, it must be in the map and its file must exist. First miss
	 * returns; nothing has been included.
	 *
	 * @param list<class-string>          $classes
	 * @param array<class-string, string> $map     The classmap.
	 */
	private static function locate( string $slug, string $phase, array $classes, array $map ): ?Module_Miss {
		foreach ( $classes as $class ) {
			if ( class_exists( $class, false ) ) {
				continue;
			}
			$rel = $map[ $class ] ?? null;
			if ( null === $rel ) {
				return new Module_Miss( $slug, $phase, Module_Miss::CLASS_UNMAPPED, $class );
			}
			if ( ! is_file( BLOCKLANE_PRO_PATH . '/' . $rel ) ) {
				return new Module_Miss( $slug, $phase, Module_Miss::CLASS_FILE_MISSING, $class, $rel );
			}
		}
		return null;
	}

	/**
	 * Phase B — the eager load, verified: require each mapped file (the same
	 * expression as the autoloader) and confirm it declared its class. Class
	 * files declare and do nothing else (the generator's rule), so nothing
	 * hooks here; a file that declares another name is a stale map. Declaring
	 * a class DOES autoload what its `implements`/`extends` clause names —
	 * the plugin's interfaces and parents, outside the module closure and not
	 * existence-checked by phase A; a missing one is the autoloader's fatal
	 * naming the path, never a silent skip.
	 *
	 * @param list<class-string>          $classes Every class already passed locate().
	 * @param array<class-string, string> $map
	 */
	private static function load_classes( string $slug, string $phase, array $classes, array $map ): ?Module_Miss {
		foreach ( $classes as $class ) {
			if ( class_exists( $class, false ) ) {
				continue;
			}
			$rel = $map[ $class ];
			require_once BLOCKLANE_PRO_PATH . '/' . $rel;
			if ( ! class_exists( $class, false ) ) {
				return new Module_Miss( $slug, $phase, Module_Miss::CLASS_UNDECLARED, $class, $rel );
			}
		}
		return null;
	}

	/**
	 * Boot one module: prove it whole (phases A and B), then require its
	 * runtimes and instantiate its boot class (phase C). Returns the first
	 * miss, with nothing of the module loaded when the miss is in phase A and
	 * no runtime required when it is in phase B; null when the module booted.
	 *
	 * @internal Public for bin/modules-battery.php, which injects a map.
	 *
	 * @param array{runtimes: list<string>, classes: list<class-string>, boot: class-string<Bootable>|null, controller: class-string<Rest_Registrable>|null} $def
	 * @param array<class-string, string>|null $map The classmap; null reads the committed one.
	 * @param string                           $phase The phase a miss belongs to — PHASE_BOOT for
	 *                                                the module manifest, PHASE_CONTENT for the
	 *                                                content table. The mechanics are identical;
	 *                                                only the slot and the sentence differ.
	 */
	public static function boot_module( string $slug, array $def, ?array $map = null, string $phase = Module_Miss::PHASE_BOOT ): ?Module_Miss {
		$map = $map ?? \blocklane_pro_classmap();
		foreach ( $def['runtimes'] as $rel ) {
			if ( ! is_file( BLOCKLANE_PRO_PATH . '/inc/' . $rel ) ) {
				return new Module_Miss( $slug, $phase, Module_Miss::RUNTIME_MISSING, $rel );
			}
		}
		$needed = $def['classes'];
		if ( null !== $def['boot'] ) {
			$needed[] = $def['boot'];
		}
		$miss = self::locate( $slug, $phase, $needed, $map ) ?? self::load_classes( $slug, $phase, $needed, $map );
		if ( null !== $miss ) {
			return $miss;
		}
		foreach ( $def['runtimes'] as $rel ) {
			require_once BLOCKLANE_PRO_PATH . '/inc/' . $rel;
		}
		if ( null !== $def['boot'] ) {
			$def['boot']::get_instance();
		}
		return null;
	}

	/**
	 * Register one booted module's REST routes: prove the controller whole
	 * (phases A and B, phase `routes`), then instantiate and register. A
	 * module with no controller returns null without doing anything.
	 *
	 * @internal Public for bin/modules-battery.php, which injects a map.
	 *
	 * @param array{runtimes: list<string>, classes: list<class-string>, boot: class-string<Bootable>|null, controller: class-string<Rest_Registrable>|null} $def
	 * @param array<class-string, string>|null $map The classmap; null reads the committed one.
	 */
	public static function route_module( string $slug, array $def, ?array $map = null ): ?Module_Miss {
		if ( null === $def['controller'] ) {
			return null;
		}
		$map        = $map ?? \blocklane_pro_classmap();
		$controller = $def['controller'];
		$miss       = self::locate( $slug, Module_Miss::PHASE_ROUTES, array( $controller ), $map ) ?? self::load_classes( $slug, Module_Miss::PHASE_ROUTES, array( $controller ), $map );
		if ( null !== $miss ) {
			return $miss;
		}
		( new $controller() )->register_routes();
		return null;
	}

	/**
	 * Record a miss in its phase's slot, log it, and hook the admin notice
	 * once. Own state and own hook flag — a shared slot with the block
	 * registrar's notice would let one alarm mark the other done.
	 *
	 * @internal Public for bin/modules-battery.php.
	 */
	public static function record( Module_Miss $miss ): void {
		switch ( $miss->phase ) {
			case Module_Miss::PHASE_ROUTES:
				self::$unrouted[ $miss->slug ] = $miss;
				break;
			case Module_Miss::PHASE_CONTENT:
				self::$content_missed[ $miss->slug ] = $miss;
				break;
			case Module_Miss::PHASE_LIFECYCLE:
				self::$lifecycle_missed[ $miss->slug ] = $miss;
				break;
			case Module_Miss::PHASE_ADMIN:
				self::$admin_missed[ $miss->slug ] = $miss;
				break;
			default:
				self::$skipped[ $miss->slug ] = $miss;
		}
		// A module that cannot load is a shipped-build defect: the log line is
		// written whatever WP_DEBUG says, and the admin notice below is the
		// wp-admin half.
		blocklane_pro_log_failure( 'Blocklane: ' . $miss->describe() ); // A log line is not a product name; both editions write this prefix.
		if ( is_admin() && ! self::$notice_hooked ) {
			self::$notice_hooked = true;
			add_action( 'admin_notices', array( __CLASS__, 'module_notices' ) );
		}
	}

	/**
	 * Whether a content runtime this edition carries FAILED TO LOAD.
	 *
	 * The loader's answer to "is this unit actually here?", for a parent that
	 * registers a contributing unit's value. Edition::has() answers whether
	 * the BUILD carries the unit; it cannot answer whether the file survived
	 * the deploy. A torn Pro install missing inc/forms/file-upload/runtime.php
	 * records the miss here and continues, and the forms registrar — reading
	 * has() alone — still registered blocklane/form-file, whose render calls a
	 * function declared only in the missing file: a white screen, where the
	 * admin notice promises the block "will not render" (#1023).
	 *
	 * Public and typed rather than a read of misses(): misses() is @internal
	 * for the battery and hands out the whole map by phase, which is a second
	 * shape for every caller to interpret. One predicate, one meaning.
	 *
	 * @param string $unit Manifest unit id, e.g. 'block:form-file'.
	 * @return bool True when the unit has a recorded PHASE_CONTENT miss.
	 */
	public static function content_missed( string $unit ): bool {
		return isset( self::$content_missed[ $unit ] );
	}

	/**
	 * Every recorded miss, by phase.
	 *
	 * @internal Public for bin/modules-battery.php.
	 *
	 * @return array{boot: array<string, Module_Miss>, routes: array<string, Module_Miss>, content: array<string, Module_Miss>, lifecycle: array<string, Module_Miss>}
	 */
	public static function misses(): array {
		return array(
			'boot'      => self::$skipped,
			'routes'    => self::$unrouted,
			'content'   => self::$content_missed,
			'lifecycle' => self::$lifecycle_missed,
			'admin'     => self::$admin_missed,
		);
	}

	/**
	 * The translated reason clause for a miss, by kind.
	 */
	private static function reason( Module_Miss $miss ): string {
		switch ( $miss->kind ) {
			case Module_Miss::RUNTIME_MISSING:
				/* translators: %s: a file path inside the plugin. */
				return sprintf( __( 'the file %s is missing', 'blocklane' ), 'inc/' . $miss->subject );
			case Module_Miss::CLASS_UNMAPPED:
				/* translators: %s: a PHP class name. */
				return sprintf( __( 'the class %s is not in inc/classmap.php', 'blocklane' ), $miss->subject );
			case Module_Miss::CLASS_FILE_MISSING:
				/* translators: 1: a PHP class name, 2: a file path inside the plugin. */
				return sprintf( __( 'the file %2$s for the class %1$s is missing', 'blocklane' ), $miss->subject, (string) $miss->path );
			default:
				/* translators: 1: a PHP class name, 2: a file path inside the plugin. */
				return sprintf( __( 'the file %2$s no longer declares the class %1$s (inc/classmap.php is stale)', 'blocklane' ), $miss->subject, (string) $miss->path );
		}
	}

	/**
	 * admin_notices callback: one notice-error per miss, for administrators
	 * only, each phase in its own words (see notice_text()).
	 *
	 * @return void
	 */
	public static function module_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		foreach ( self::misses() as $phase_misses ) {
			foreach ( $phase_misses as $slug => $miss ) {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html( self::notice_text( $miss, (string) $slug ) )
				);
			}
		}
	}

	/**
	 * One miss, in this phase's own words. Each sentence names the CONSEQUENCE
	 * the admin actually faces, because the four are not the same failure: a
	 * boot miss means the feature is gone, a routes miss means only its screen
	 * is, a content miss means pages render wrong, and a lifecycle miss means
	 * a migration silently did not run.
	 *
	 * The message literals stay inline (not in a table) so gettext can
	 * extract them.
	 */
	private static function notice_text( Module_Miss $miss, string $slug ): string {
		$reason = self::reason( $miss );
		switch ( $miss->phase ) {
			case Module_Miss::PHASE_ROUTES:
				return sprintf(
					/* translators: 1: plugin name, 2: module slug, 3: the reason clause (e.g. "the class X is not in inc/classmap.php"). */
					__( '%1$s: the %2$s module is running, but its REST routes did not register — %3$s. Its dashboard screen will not work until the plugin is reinstalled.', 'blocklane' ),
					Branding::plugin_name(),
					$slug,
					$reason
				);
			case Module_Miss::PHASE_CONTENT:
				return sprintf(
					/* translators: 1: plugin name, 2: unit id, 3: the reason clause. */
					__( '%1$s: the %2$s content runtime did not load — %3$s. Content you have already built with it will not render until the plugin is reinstalled.', 'blocklane' ),
					Branding::plugin_name(),
					$slug,
					$reason
				);
			case Module_Miss::PHASE_LIFECYCLE:
				return sprintf(
					/* translators: 1: plugin name, 2: unit id, 3: the reason clause. */
					__( '%1$s: the %2$s lifecycle file did not load — %3$s. Its once-per-version housekeeping was skipped and will run by itself once the file is back; reinstall the plugin to restore it.', 'blocklane' ),
					Branding::plugin_name(),
					$slug,
					$reason
				);
			case Module_Miss::PHASE_ADMIN:
				return sprintf(
					/* translators: 1: plugin name, 2: unit id, 3: the reason clause. */
					__( '%1$s: the %2$s extension\'s editor controls did not load — %3$s. Content you have already built with it still renders, but it cannot be edited until the plugin is reinstalled.', 'blocklane' ),
					Branding::plugin_name(),
					$slug,
					$reason
				);
			default:
				return sprintf(
					/* translators: 1: plugin name, 2: module slug, 3: the reason clause. */
					__( '%1$s: the %2$s module did not load — %3$s. The plugin\'s files are incomplete; reinstall the plugin. In development, run php bin/generate-classmap.php.', 'blocklane' ),
					Branding::plugin_name(),
					$slug,
					$reason
				);
		}
	}

	/**
	 * Boot each enabled module's runtime (call on plugins_loaded): the edition
	 * filter, the gate, then boot_module(), then record() for a miss.
	 *
	 * The edition filter comes FIRST and is not optional. This table is shared
	 * by both editions; a module the free build does not carry has had its
	 * whole directory removed by bin/generate-edition.php, so reaching
	 * boot_module() for it produces a Module_Miss — a user-visible "did not
	 * load ... reinstall the plugin" notice about a module that was never
	 * meant to be there. All four phases filter the same way — boot(),
	 * boot_content(), boot_lifecycle() and register_rest() — and the battery's
	 * E8b drives rest_api_init to prove the routes phase does too.
	 *
	 * A module row's unit id is `module:` + its slug, and the battery's E7
	 * asserts every row names a real unit, so has_role() cannot be asked about
	 * an unknown one.
	 *
	 * @return void
	 */
	public static function boot(): void {
		foreach ( self::all() as $slug => $def ) {
			if ( ! Edition::has_role( 'module:' . $slug, 'boot' ) ) {
				continue;
			}
			if ( ! self::is_enabled( $slug ) || ! self::boot_on( $slug ) ) {
				continue;
			}
			$miss = self::boot_module( $slug, $def );
			if ( null !== $miss ) {
				self::record( $miss );
			}
		}
	}

	/**
	 * Module slug => the Advanced "Site Tools" toggle that gates its MANAGEMENT
	 * screen. Off hides the dashboard screen (Settings::screens_manifest) and
	 * skips its controller here, so the editing API is unreachable — but boot()
	 * still runs, so the front-end runtime (CPTs, bindings, the lock gate) is
	 * untouched. The toggle slug matches the module slug for these.
	 */
	const SCREEN_TOGGLES = array(
		'content-types'  => 'content-types',
		'dynamic-values' => 'dynamic-values',
		'site-lock'      => 'site-privacy',
		'seo'            => 'seo',
		'forms'          => 'forms',
	);

	/**
	 * The screen toggles that are OPT-INS (ship OFF). A default-on tool
	 * screen fails OPEN when the Advanced store is unreachable — never
	 * strand a live feature's controls — while an opt-in falls back to its
	 * STORED value instead (see toggle_on_stored()). Add new opt-in screens
	 * here; the gating below is data-driven.
	 */
	const OPT_IN_SCREENS = array( 'seo', 'forms' );

	/**
	 * Whether a module's management surface (screen + REST) is toggled on.
	 * True for modules with no screen toggle. Fails open when Advanced isn't
	 * loaded. Site Visibility stays on whenever a lock is live — its control
	 * must never be unreachable while enforcing.
	 *
	 * @param string $slug Module slug.
	 * @return bool
	 */
	private static function management_on( $slug ) {
		if ( ! isset( self::SCREEN_TOGGLES[ $slug ] ) ) {
			return true;
		}
		if ( 'site-lock' === $slug
			&& class_exists( Site_Lock::class, false )
			&& Site_Lock::is_enabled() ) {
			return true;
		}
		if ( ! class_exists( Advanced::class, false ) ) {
			// Opt-ins (ship OFF) fall back to the stored toggle — absence
			// means off, but a stored ON keeps management reachable while
			// the runtime is still emitting. Default-on tools fail open.
			if ( in_array( $slug, self::OPT_IN_SCREENS, true ) ) {
				return self::toggle_on_stored( self::SCREEN_TOGGLES[ $slug ] );
			}
			return true;
		}
		return Advanced::is_on( self::SCREEN_TOGGLES[ $slug ] );
	}

	/**
	 * Register each enabled module's REST routes (call on rest_api_init).
	 * Loads the controller and nothing else, under the same gates as boot()
	 * — the EDITION filter first, then enabled, boot toggle, management
	 * toggle. This was the one loader phase without the edition filter: in
	 * the free build every rest_api_init recorded a routes miss for the two
	 * Pro-only management modules (their controllers are not in the filtered
	 * classmap), which logged two lines per REST request and printed two
	 * "reinstall the plugin" notices on every block-editor load (#837).
	 *
	 * @return void
	 */
	public static function register_rest(): void {
		foreach ( self::all() as $slug => $def ) {
			if ( ! Edition::has_role( 'module:' . $slug, 'boot' ) ) {
				continue; // The edition filter first, exactly as boot().
			}
			if ( isset( self::$skipped[ $slug ] ) ) {
				continue; // Never routes for a module that did not boot.
			}
			if ( ! self::is_enabled( $slug ) ) {
				continue;
			}
			// Boot-toggled modules that are off must not load AT ALL here:
			// rest_api_init fires during block-editor preload, and merely
			// defining a class flips class_exists( …, false ) probes
			// elsewhere (e.g. the AI Tools editor-button gate).
			if ( ! self::boot_on( $slug ) ) {
				continue;
			}
			// The management controller is gated by the screen toggle; boot()
			// (front-end runtime) is not.
			if ( ! self::management_on( $slug ) ) {
				continue;
			}
			$miss = self::route_module( $slug, $def );
			if ( null !== $miss ) {
				self::record( $miss );
			}
		}
	}
}
