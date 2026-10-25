<?php
/**
 * Blocklane Forms — runtime (canonical source).
 *
 * The form-block suite: a form wrapper plus field, submit, and notification
 * blocks, all dynamic (server-rendered, no static save — no deprecation debt).
 * Blocks live under the brand-level `blocklane/` namespace deliberately —
 * block names live in saved content, so they must stay portable to any future
 * packaging (see docs/specs/forms.md, Decision 7).
 *
 * Like the Content Types and SEO runtimes, rendering forms is CONTENT, so this
 * file loads OUTSIDE the module boot (see blocklane_pro_run_plugin) — safe
 * mode or a disabled module must never blank a live site's contact form.
 * Editor bundles ride the block registration unconditionally too: a missing
 * block turns saved content into "unsupported block" noise. The management
 * surfaces are the dashboard inbox + management REST, behind their own
 * capability checks (the license gates nothing at runtime).
 *
 * Two of the suite's blocks are CONTRIBUTING UNITS of Pro (edition-manifest
 * rule 6, spec 2026-09-24): form-step and form-file. The shipped table
 * BLOCKLANE_PRO_FORMS_BLOCKS stays whole in both editions; the one view of
 * what THIS edition registers is blocklane_pro_forms_known_blocks(), and
 * every door reads it — the registrar, the collector (a suite block the
 * edition does not register is a layout wrapper whose posted name is
 * dropped, never trapped), has_required(), form/shell.php's stepped test
 * and the editor bridge. The file field's write side lives with its unit
 * (inc/forms/file-upload/runtime.php) and registers through the field-type
 * seam below; the step's editor stand-in is inc/form-step-standin/.
 *
 * Membership is not shape. Which blocks this edition carries says nothing
 * about what a block IS, and three doors needed the second answer and each
 * invented it: BLOCKLANE_PRO_FORMS_FIELDS is the shipped table of which
 * suite blocks POST a value and how their row is derived, and
 * blocklane_pro_forms_field_kind() is its one reader (#1020, #1022).
 *
 * Self-contained by construction: it assumes no module has booted, and the
 * two side-effect-free plugin classes it uses — the toggle reader and the
 * block registrar — are resolved by the classmap autoloader, registered at
 * plugin-file scope before any runtime loads, so load order can never trip it. (The rule's old letter — core WordPress only
 * — existed so the file could be copied into a must-use plugin; that copying
 * was retired in 2026-08.) All declarations live inside one
 * `if ( ! function_exists() )` block so a second copy of the file is a clean
 * no-op.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Single-load guard. Required once from the Modules manifest; kept because a
// leftover pre-2026-08 mu copy on an upgrading site would load first.
if ( defined( 'BLOCKLANE_PRO_FORMS_RUNTIME_LOADED' ) ) {
	return;
}
define( 'BLOCKLANE_PRO_FORMS_RUNTIME_LOADED', true );

// Safe mode silences every Blocklane Pro surface, the generated runtimes included.
if ( defined( 'BLOCKLANE_PRO_SAFE_MODE' ) && BLOCKLANE_PRO_SAFE_MODE ) {
	return;
}

// The Advanced toggle (ships OFF). Read through the one toggle reader, which
// resolves the stored row without loading the Advanced class: an absent row
// or key means the shipped default, and there is no second source to consult.
if ( ! \blocklane_pro\Content_Toggle::on( 'forms' ) ) {
	return;
}

if ( ! function_exists( 'blocklane_pro_forms_register_blocks' ) ) {

	/** The block suite, in registration order. Dir names under build/. */
	define(
		'BLOCKLANE_PRO_FORMS_BLOCKS',
		array( 'form', 'form-step', 'form-input', 'form-textarea', 'form-select', 'form-group', 'form-option', 'form-file', 'form-submit-button', 'form-notification' )
	);

	/**
	 * The suite's FIELD blocks — the blocks that POST a value — and how each
	 * derives its schema row. 'internal': hardcoded in this file, and the
	 * field-type seam cannot override it. 'seam': registered through
	 * blocklane_pro_forms_field_types by the unit that carries it (form-file
	 * by block:form-file). A suite block absent from this table posts nothing:
	 * form and form-step are wrappers, form-option, form-submit-button and
	 * form-notification are controls.
	 *
	 * Whole in both editions, like BLOCKLANE_PRO_FORMS_BLOCKS, and for the
	 * same reason: the free collector must know that a Pro FIELD block WOULD
	 * have posted a value — so its name is dropped rather than trapped as
	 * tampering — while a Pro WRAPPER never posts one and must contribute no
	 * name at all. Which of these THIS edition registers is a different
	 * question, and blocklane_pro_forms_known_blocks() is the view of that.
	 *
	 * Read only by blocklane_pro_forms_field_kind(); a fetch anywhere else is
	 * a PHPStan error, blocklane.chokepointMember.
	 */
	define(
		'BLOCKLANE_PRO_FORMS_FIELDS',
		array(
			'form-input'    => 'internal',
			'form-textarea' => 'internal',
			'form-select'   => 'internal',
			'form-group'    => 'internal',
			'form-file'     => 'seam',
		)
	);

	/**
	 * What KIND of field block a name is — the one answer to "does this block
	 * post a value, and where does its schema row come from?".
	 *
	 * 'internal' — a field whose row and validator are hardcoded in this file.
	 * 'seam'     — a field whose row arrives through blocklane_pro_forms_field_types.
	 * null       — not a suite FIELD block: a wrapper (form, form-step), a
	 *              control (form-option, form-submit-button, form-notification),
	 *              or any name outside the blocklane/ namespace.
	 *
	 * Three readers, each of which answered by complement before this existed
	 * and so disagreed: blocklane_pro_forms_field_blocks() ("which blocks I
	 * register post a value"), the collector's unknown-suite branch ("would
	 * this block have posted a name?") and the seam guard ("may a filter claim
	 * this slug?"). The last two are why it matters — a wrapper admitted as a
	 * field becomes a schema leaf that erases every input inside it, and a
	 * wrapper counted as a bypass name narrows the tamper trap (#1020, #1022).
	 *
	 * @param string $block_name Full block name, e.g. 'blocklane/form-input'.
	 * @return string|null 'internal', 'seam', or null.
	 */
	function blocklane_pro_forms_field_kind( string $block_name ): ?string {
		if ( 0 !== strpos( $block_name, 'blocklane/' ) ) {
			return null;
		}
		$slug = substr( $block_name, strlen( 'blocklane/' ) );
		$kind = BLOCKLANE_PRO_FORMS_FIELDS[ $slug ] ?? null;

		return is_string( $kind ) ? $kind : null;
	}

	/**
	 * The blocks THIS EDITION registers — the one view of BLOCKLANE_PRO_FORMS_BLOCKS.
	 *
	 * The constant is the SHIPPED suite in registration order, whole in both
	 * editions: the skew notice must know what a Pro build expects so a stale
	 * build is announced (Block_Suite::register), and a glob of build/ could
	 * not tell "absent by edition" from "absent by a stale build". This view
	 * subtracts every block whose contributing unit this edition does not
	 * carry (edition-manifest.json rule 6: block:form-step, block:form-file)
	 * — AND every block whose unit this edition carries but whose runtime did
	 * not load. Edition::has() answers what the BUILD carries; only the loader
	 * knows what survived the deploy. Without the second question a torn Pro
	 * install registered blocklane/form-file whose render calls a function
	 * declared only in the missing file — a white screen, where the loader's
	 * own notice promises the block "will not render" (#1023).
	 *
	 * Readers: the registrar, has_required() and field_blocks(), the field
	 * collector, the field-type seam's guard, form/shell.php's stepped test,
	 * and the editor bridge. A fetch of the constant or a call of
	 * Edition::contributor() anywhere else is a PHPStan error,
	 * blocklane.chokepointMember — a door that reads the shipped table
	 * re-derives what this view already answers.
	 *
	 * @return list<string> Slugs, registration order.
	 */
	function blocklane_pro_forms_known_blocks(): array {
		$known = array();
		foreach ( BLOCKLANE_PRO_FORMS_BLOCKS as $slug ) {
			$unit = \blocklane_pro\Edition::contributor( 'blocks', 'blocklane/' . $slug );
			if ( null !== $unit
				&& ( ! \blocklane_pro\Edition::has( $unit ) || \blocklane_pro\Modules::content_missed( $unit ) ) ) {
				continue;
			}
			$known[] = $slug;
		}
		return $known;
	}

	/**
	 * The FIELD blocks this edition can put in a schema, full names: every
	 * block this edition registers whose kind is 'internal', plus every type
	 * registered through the blocklane_pro_forms_field_types seam (the file
	 * field is one of those — Pro's inc/forms/file-upload/runtime.php
	 * registers it, so it is here exactly when that unit is). One list for
	 * has_required(), the collector's condition attachment and the editor
	 * bridge, so the canvas's required-notice and the front's agree.
	 *
	 * @param array<string, array<string, mixed>>|null $registry The already-fetched
	 *        field-type registry, so a caller that holds one costs no second
	 *        apply_filters run; null fetches it.
	 * @return list<string> Block names, e.g. 'blocklane/form-input'.
	 */
	function blocklane_pro_forms_field_blocks( ?array $registry = null ): array {
		$out = array();
		foreach ( blocklane_pro_forms_known_blocks() as $slug ) {
			if ( 'internal' === blocklane_pro_forms_field_kind( 'blocklane/' . $slug ) ) {
				$out[] = 'blocklane/' . $slug;
			}
		}
		foreach ( array_keys( $registry ?? blocklane_pro_forms_field_type_registry() ) as $ext ) {
			if ( ! in_array( $ext, $out, true ) ) {
				$out[] = $ext;
			}
		}
		return $out;
	}

	/**
	 * The canonical form-input `type` whitelist — the single source shared by
	 * schema derivation and the field render (which each used to hand-copy it).
	 * Mirrors form-input/block.json's enum.
	 */
	define(
		'BLOCKLANE_PRO_FORMS_INPUT_TYPES',
		array( 'text', 'email', 'tel', 'url', 'number', 'date', 'hidden', 'checkbox' )
	);

	/**
	 * Register every forms block from its built block.json beside this file,
	 * through the one registrar. Registry-idempotent, so init re-runs and the
	 * second registration call are both no-ops. A missing manifest is
	 * announced by the registrar's admin notice with the consequence supplied
	 * here (the server schema no longer matches the form, so submissions lose
	 * the fields that block carried); the v3 history lives in the registrar's
	 * docblock.
	 *
	 * @return void
	 */
	function blocklane_pro_forms_register_blocks() {
		\blocklane_pro\Block_Suite::register(
			'forms',
			'blocklane',
			__DIR__ . '/build',
			blocklane_pro_forms_known_blocks(), // the view, never the shipped table (rule 6)
			'npm run build:forms',
			__( 'Forms', 'blocklane' ),
			__( 'Saved forms render without those blocks, and their submissions are refused, until the assets are rebuilt.', 'blocklane' )
		);
	}
	add_action( 'init', 'blocklane_pro_forms_register_blocks' );

	/**
	 * The Form block's "Inline" style: a single-field form (one control plus
	 * submit, a newsletter signup) on one row inside one border, the button
	 * dropping below on a narrow container. Any other form ignores it and
	 * keeps the stacked layout (form/style.scss). Both editions: the style is
	 * the form module's, not a contributing unit's.
	 *
	 * @return void
	 */
	function blocklane_pro_forms_register_block_styles(): void {
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'blocklane/form' ) ) {
			return; // No form block (a missing build): no style to offer.
		}
		register_block_style(
			'blocklane/form',
			array(
				'name'  => 'inline',
				'label' => __( 'Inline', 'blocklane' ),
			)
		);
	}
	add_action( 'init', 'blocklane_pro_forms_register_block_styles', 11 );

	/**
	 * Resolve field names the ONE way — server-side.
	 *
	 * The editor needs the submit name a field will get so its pickers and
	 * its duplicate-name heal reason about the same string the server
	 * validates against. That name used to be re-derived in JS by a
	 * hand-maintained twin of blocklane_pro_forms_field_name(), and the twin
	 * was built from different primitives (cleanForSlug vs sanitize_title).
	 * They disagreed on every non-Latin label and on German: 名前 resolved to
	 * %e5%90%8d%e5%89%8d here and to 名前 there, so a rule authored in the
	 * editor referenced a field the schema does not have.
	 *
	 * So the editor asks instead. One transformer, no copy to keep in sync —
	 * the house rule that values both sides need are defined once, in PHP.
	 *
	 * @return void
	 */
	function blocklane_pro_forms_register_name_route() {
		register_rest_route(
			'blocklane-pro/v1',
			'/forms/field-names',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => 'blocklane_pro_forms_resolve_names',
				// Editing capability, not a management one: this is editor
				// support for anyone who can place a form block, and it
				// returns nothing but a slug of what the caller sent.
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'fields' => array(
						'required' => true,
						'type'     => 'array',
					),
				),
			)
		);
	}
	add_action( 'rest_api_init', 'blocklane_pro_forms_register_name_route' );

	/**
	 * POST blocklane-pro/v1/forms/field-names
	 *
	 * Body: { fields: [ { name, label, fallback }, … ] }
	 * Returns: { names: [ 'resolved', … ] } in the same order.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	function blocklane_pro_forms_resolve_names( $request ) {
		$rows  = (array) $request->get_param( 'fields' );
		$names = array();

		// Bounded: a form with more fields than this is not a form, and the
		// editor batches one request per form.
		foreach ( array_slice( $rows, 0, 200 ) as $row ) {
			$row     = is_array( $row ) ? $row : array();
			$names[] = blocklane_pro_forms_field_name(
				array(
					'name'  => isset( $row['name'] ) ? (string) $row['name'] : '',
					'label' => isset( $row['label'] ) ? (string) $row['label'] : '',
				),
				isset( $row['fallback'] ) && '' !== (string) $row['fallback'] ? (string) $row['fallback'] : 'field'
			);
		}

		return rest_ensure_response( array( 'names' => $names ) );
	}

	/**
	 * A "Forms" inserter category so the suite's tiles read as one feature.
	 * Appended after core's categories; the inserter hides it while empty.
	 *
	 * @param array $categories Registered block categories.
	 * @return array
	 */
	function blocklane_pro_forms_block_category( $categories ) {
		foreach ( $categories as $category ) {
			if ( 'blocklane-forms' === $category['slug'] ) {
				return $categories;
			}
		}
		$categories[] = array(
			'slug'  => 'blocklane-forms',
			'title' => __( 'Forms', 'blocklane' ),
		);

		return $categories;
	}
	add_filter( 'block_categories_all', 'blocklane_pro_forms_block_category' );

	/**
	 * The raw Forms settings option — the one read point every runtime
	 * consumer (Turnstile state, upload caps, retention) goes through.
	 * Bake-safe: reads the option directly, no Forms class involved.
	 *
	 * @return array
	 */
	function blocklane_pro_forms_settings() {
		$settings = get_option( 'blocklane_pro_forms', array() );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * The built-in site-default values, option-shaped (snake_case) — THE
	 * single source of the module's built-in submission config. Both other
	 * shapes derive from this table: the option schema (Forms::defaults())
	 * consumes it directly, and the resolver's attr-shaped fallbacks
	 * (config_fallbacks() below) map it key for key — so a changed
	 * built-in can never diverge between what the Settings screen shows
	 * and what the runtime resolves at submit time.
	 *
	 * @return array
	 */
	function blocklane_pro_forms_default_settings() {
		return array(
			'recipients'         => '',
			'subject'            => '',
			'success_action'     => 'message',
			'redirect_url'       => '',
			'store_submissions'  => true,
			'notify_admin'       => true,
			'auto_responder'     => array(
				'enabled' => false,
				'subject' => '',
				'message' => '',
			),
			'replace_on_success' => false,
		);
	}

	/**
	 * The built-in submission-config fallbacks, attr-shaped — what a form
	 * resolves to when neither the form nor the site-wide defaults set a
	 * value. These mirror the pre-v2 block.json defaults, so a site that
	 * never touches the defaults UI behaves exactly as before. Purely a
	 * key-shape mapping of default_settings() above — the values live
	 * there, once.
	 *
	 * @return array
	 */
	function blocklane_pro_forms_config_fallbacks() {
		$defaults = blocklane_pro_forms_default_settings();

		return array(
			'recipients'       => $defaults['recipients'],
			'subject'          => $defaults['subject'],
			'successAction'    => $defaults['success_action'],
			'redirectUrl'      => $defaults['redirect_url'],
			'storeSubmissions' => $defaults['store_submissions'],
			'notifyAdmin'      => $defaults['notify_admin'],
			'autoResponder'    => $defaults['auto_responder'],
			'replaceOnSuccess' => $defaults['replace_on_success'],
		);
	}

	/**
	 * The site-wide form defaults: the `defaults` sub-array of the module
	 * option (snake_case, written by the Forms screen) normalized over the
	 * built-in fallbacks, returned attr-shaped. Bake-safe by construction —
	 * this reads the same option the runtime already reads for
	 * Turnstile, so a changed default reaches baked / license-lapsed forms
	 * with no snapshot plumbing. Values are re-sanitized here because the
	 * option is reachable outside the Forms screen (CLI, direct writes).
	 *
	 * @return array
	 */
	function blocklane_pro_forms_form_defaults() {
		$settings = blocklane_pro_forms_settings();
		$stored   = isset( $settings['defaults'] ) && is_array( $settings['defaults'] ) ? $settings['defaults'] : array();
		$out      = blocklane_pro_forms_config_fallbacks();

		if ( isset( $stored['recipients'] ) ) {
			$out['recipients'] = sanitize_text_field( (string) $stored['recipients'] );
		}
		if ( isset( $stored['subject'] ) ) {
			$out['subject'] = sanitize_text_field( (string) $stored['subject'] );
		}
		if ( isset( $stored['success_action'] ) && in_array( $stored['success_action'], array( 'message', 'redirect' ), true ) ) {
			$out['successAction'] = (string) $stored['success_action'];
		}
		if ( isset( $stored['redirect_url'] ) ) {
			$out['redirectUrl'] = esc_url_raw( (string) $stored['redirect_url'] );
		}
		if ( isset( $stored['store_submissions'] ) ) {
			$out['storeSubmissions'] = (bool) $stored['store_submissions'];
		}
		if ( isset( $stored['notify_admin'] ) ) {
			$out['notifyAdmin'] = (bool) $stored['notify_admin'];
		}
		if ( isset( $stored['auto_responder'] ) && is_array( $stored['auto_responder'] ) ) {
			$auto                 = $stored['auto_responder'];
			$out['autoResponder'] = array(
				'enabled' => ! empty( $auto['enabled'] ),
				'subject' => isset( $auto['subject'] ) ? sanitize_text_field( (string) $auto['subject'] ) : '',
				'message' => isset( $auto['message'] ) ? sanitize_textarea_field( (string) $auto['message'] ) : '',
			);
		}
		if ( isset( $stored['replace_on_success'] ) ) {
			$out['replaceOnSuccess'] = (bool) $stored['replace_on_success'];
		}

		return $out;
	}

	/**
	 * Resolve a form's effective submission config, LIVE: per-form override →
	 * site-wide default → built-in fallback. The one helper every consumer
	 * goes through — the submit handler resolves once and everything
	 * downstream (storage, emails, the success payload, the fake-success
	 * branches) sees resolved values; never fork the chain (the render↔schema
	 * invariant, extended to config).
	 *
	 * Inherit markers: strings inherit on '' (their pre-v2 defaults, so
	 * existing content is backward-compatible with no migration); the
	 * toggles are tri-state — only an explicit boolean is an override, an
	 * absent attribute inherits (their block.json defaults were removed for
	 * exactly this; pre-v2 content that carries an explicit false keeps it).
	 *
	 * @param array $form_attrs The form block's parsed attributes.
	 * @return array The attributes with every config key resolved.
	 */
	function blocklane_pro_forms_resolve_config( $form_attrs ) {
		$attrs    = is_array( $form_attrs ) ? $form_attrs : array();
		$defaults = blocklane_pro_forms_form_defaults();

		foreach ( array( 'recipients', 'subject', 'redirectUrl' ) as $key ) {
			if ( ! isset( $attrs[ $key ] ) || ! is_string( $attrs[ $key ] ) || '' === trim( $attrs[ $key ] ) ) {
				$attrs[ $key ] = $defaults[ $key ];
			}
		}
		if ( ! isset( $attrs['successAction'] ) || ! in_array( $attrs['successAction'], array( 'message', 'redirect' ), true ) ) {
			$attrs['successAction'] = $defaults['successAction'];
		}
		foreach ( array( 'storeSubmissions', 'notifyAdmin', 'replaceOnSuccess' ) as $key ) {
			if ( ! isset( $attrs[ $key ] ) || ! is_bool( $attrs[ $key ] ) ) {
				$attrs[ $key ] = $defaults[ $key ];
			}
		}
		$auto                   = isset( $attrs['autoResponder'] ) && is_array( $attrs['autoResponder'] ) ? $attrs['autoResponder'] : array();
		$attrs['autoResponder'] = array(
			'enabled' => isset( $auto['enabled'] ) && is_bool( $auto['enabled'] ) ? $auto['enabled'] : $defaults['autoResponder']['enabled'],
			'subject' => isset( $auto['subject'] ) && is_string( $auto['subject'] ) && '' !== trim( $auto['subject'] ) ? $auto['subject'] : $defaults['autoResponder']['subject'],
			'message' => isset( $auto['message'] ) && is_string( $auto['message'] ) && '' !== trim( $auto['message'] ) ? $auto['message'] : $defaults['autoResponder']['message'],
		);

		return $attrs;
	}

	/**
	 * Tell the editor whether Turnstile is active so the submit-button canvas
	 * can mirror the widget footprint (the 1:1 contract — the front grows a
	 * challenge box, so the canvas must show one). An inline global on the
	 * block's editor script, NOT block_editor_settings_all: the post editor
	 * whitelists which settings reach getSettings(), so custom keys are
	 * silently dropped there.
	 */
	function blocklane_pro_forms_editor_state() {
		$turnstile = blocklane_pro_forms_turnstile();

		// Which suite blocks THIS EDITION registers, and which of them are
		// field blocks — the editor's required-notice, inserter order, step
		// detection and submit lock read these instead of literals, so the
		// canvas never shows a step or a file field the server would not
		// register, and a Pro-authored form's lock attribute is left alone
		// under free (rule 6).
		wp_add_inline_script(
			'blocklane-form-editor-script',
			'window.blocklaneProForms = window.blocklaneProForms || {};'
			. 'window.blocklaneProForms.blocks = ' . wp_json_encode( blocklane_pro_forms_known_blocks() ) . ';'
			. 'window.blocklaneProForms.fieldBlocks = ' . wp_json_encode( blocklane_pro_forms_field_blocks() ) . ';'
			// Test mode (spec forms.md v4 TM-C, F8): whether a save by this
			// user keeps a form's testMode. The editor hides the toggle
			// without it; blocklane_pro_forms_strip_test_mode() is the
			// enforcement, this only keeps the UI honest about it.
			. 'window.blocklaneProForms.canTestMode = ' . wp_json_encode( blocklane_pro_forms_can_test_mode() ) . ';',
			'before'
		);

		wp_add_inline_script(
			'blocklane-form-editor-script',
			'window.blocklaneProForms = window.blocklaneProForms || {};' .
			'window.blocklaneProForms.turnstile = ' . wp_json_encode(
				array(
					'active'     => $turnstile['active'],
					'testMode'   => $turnstile['test_mode'],
					'size'       => $turnstile['size'],
					'theme'      => $turnstile['theme'],
					'appearance' => $turnstile['appearance'],
				)
			) . ';',
			'before'
		);

		// The resolved site-wide defaults, for the form inspector's
		// placeholders and "Default" hints (an inherited value is never a
		// surprise). Recipients resolves one display step further — an empty
		// site default falls through to the admin email at send time, and
		// the placeholder must show where mail will actually go. But the
		// addresses are manage_options-grade data (the admin email and the
		// site's lead routing), and this bridge reaches EVERY user who can
		// open the editor — lower roles get the tri-state hints without
		// the addresses themselves.
		$forms_defaults = blocklane_pro_forms_form_defaults();
		if ( ! current_user_can( 'manage_options' ) ) {
			$forms_defaults['recipients'] = '';
		} elseif ( '' === $forms_defaults['recipients'] ) {
			$forms_defaults['recipients'] = sanitize_email( (string) get_option( 'admin_email' ) );
		}

		wp_add_inline_script(
			'blocklane-form-editor-script',
			'window.blocklaneProForms = window.blocklaneProForms || {};' .
			'window.blocklaneProForms.defaults = ' . wp_json_encode( $forms_defaults ) . ';',
			'before'
		);

		/**
		 * THE PARENT'S EDITOR DOOR. A contributing unit that needs to add to
		 * window.blocklaneProForms hooks THIS, never enqueue_block_editor_assets
		 * — a core hook fires whether or not this file loaded, so a contributor
		 * that hooks one reaches into a parent that may not be there and fatals
		 * every editor load (#1023). Same request, same priority (this runs at
		 * enqueue_block_editor_assets 10), same script handle; without the
		 * parent the action simply never fires. The shape is
		 * blocklane_pro_popups_front_enqueued's. Asserted by
		 * bin/contributor-scope-check.php, wiring check 7.
		 */
		do_action( 'blocklane_pro_forms_editor_bridged' );
	}
	add_action( 'enqueue_block_editor_assets', 'blocklane_pro_forms_editor_state' );

	/**
	 * Resolve a field's submit name: the explicit name attribute when set,
	 * otherwise a slug of the label, with a stable fallback. Mirrored in
	 * inc/forms/src/shared/field-name.js — keep the two in sync.
	 *
	 * This is a PURE function of the field's own attributes — no cross-field
	 * state. Render and schema derivation both call it, so the rendered names
	 * and the validated schema are identical by construction, whatever the
	 * render order or nesting (a static per-request dedup registry used to
	 * live here and diverged whenever a form rendered twice in one request —
	 * silent submission loss). Two fields that resolve to the same name is an
	 * authoring collision: the editor surfaces it (form/edit.js), the last
	 * value wins on both sides, and nothing is silently mis-classified.
	 *
	 * @param array  $attributes Block attributes (name, label).
	 * @param string $fallback   Name used when both are empty.
	 * @return string
	 */
	function blocklane_pro_forms_field_name( $attributes, $fallback = 'field' ) {
		$name = isset( $attributes['name'] ) ? sanitize_title( (string) $attributes['name'] ) : '';
		if ( '' === $name && isset( $attributes['label'] ) ) {
			$name = sanitize_title( wp_strip_all_tags( (string) $attributes['label'] ) );
		}
		// The module's own posted keys are reserved: a field named `_bl_url`
		// would collide with the view module's appended meta (last-wins to
		// the meta value — silently wrong data in the inbox and emails), and
		// the honeypot/captcha names are consumed before validation. Reserved
		// names fall back — the SAME rule, byte for byte, as the
		// shared/field-name.js mirror (deliberately the unfiltered defaults:
		// the mirror cannot see a PHP filter).
		if ( 0 === strpos( $name, '_bl_' ) || 'bl_contact_me' === $name || 'cf-turnstile-response' === $name ) {
			$name = '';
		}

		return '' !== $name ? $name : $fallback;
	}

	/**
	 * The id attribute pairing a control with its label.
	 *
	 * @param string $form_id Form id from block context.
	 * @param string $name    Unique field name.
	 * @return string
	 */
	function blocklane_pro_forms_field_id( $form_id, $name ) {
		return 'blf-' . ( '' !== $form_id ? $form_id : 'form' ) . '-' . $name;
	}

	/**
	 * Build the form-level input-style custom properties from the form block's
	 * inputStyles attribute. Fields consume the variables with fallbacks (see
	 * form/style.scss), so one setting styles every control — and the same
	 * string renders in the editor canvas (shared/input-styles.js mirror).
	 *
	 * @param array $styles inputStyles attribute value.
	 * @return string Safe CSS declarations ('' when nothing is set).
	 */
	function blocklane_pro_forms_input_style_vars( $styles ) {
		if ( ! is_array( $styles ) ) {
			return '';
		}
		$map          = array(
			'background'  => '--blocklane-form--input-background',
			'text'        => '--blocklane-form--input-text',
			'border'      => '--blocklane-form--input-border',
			'focusBorder' => '--blocklane-form--input-focus-border',
			'radius'      => '--blocklane-form--input-radius',
			'asterisk'    => '--blocklane-form--asterisk',
		);
		$declarations = array();
		foreach ( $map as $key => $property ) {
			if ( empty( $styles[ $key ] ) || ! is_string( $styles[ $key ] ) ) {
				continue;
			}
			$value = trim( $styles[ $key ] );
			if ( '' === $value || preg_match( '/[;{}]/', $value ) ) {
				continue;
			}
			$declarations[] = $property . ':' . $value;
		}

		return $declarations ? implode( ';', $declarations ) . ';' : '';
	}

	/**
	 * The common render preamble every labeled field control needs, as VALUES
	 * the template escapes at its sink (spec 2026-10-06 §4.2): the resolved
	 * name/id, the label, the required flag, the label class, the control's
	 * CSS declarations, the wrapper's attribute ARGS (the template calls
	 * get_block_wrapper_attributes() with them, inside the render) and the
	 * visibility rule's JSON. It returns no pre-built HTML: a template that
	 * echoed a fragment built here proved its safety by a comment, which is
	 * the class the wordpress.org review rejected. Callers: input, textarea,
	 * select, group, and Pro's file field.
	 *
	 * @param array<string, mixed> $attributes       Block attributes.
	 * @param \WP_Block            $block            Block instance (for formId context).
	 * @param string               $type_class       The is-type-* modifier for the wrapper.
	 * @param bool                 $style_on_wrapper Merge the control style into the wrapper
	 *                                               instead (fields with no styleable control
	 *                                               — the consent checkbox styles its row).
	 *                                               The edit views mirror the same split.
	 * @return array{form_id: string, name: string, id: string, label: string, required: bool, label_class: string, control_css: string, wrapper_args: array<string, string>, cond_json: string}
	 */
	function blocklane_pro_forms_field_render_base( array $attributes, \WP_Block $block, string $type_class, bool $style_on_wrapper = false ): array {
		$form_id  = isset( $block->context['blocklane/formId'] ) ? (string) $block->context['blocklane/formId'] : '';
		$name     = blocklane_pro_forms_field_name( $attributes );
		$required = ! empty( $attributes['required'] );
		$style    = blocklane_pro_forms_control_style( $attributes );

		$wrapper_args = array(
			'class' => 'blocklane-form__field ' . $type_class . ( $required ? ' is-required' : '' ),
		);
		// Conditional visibility (v3): the wrapper carries its own rule +
		// name so view.js can evaluate live with ZERO markup knowledge of
		// individual field types — one attribute from the one base builder,
		// mirroring the server resolver's rule exactly (the render↔schema
		// discipline applied to visibility). The JSON is also returned on its
		// own, for the one render path that emits no wrapper at all: a hidden
		// input, whose template prints it on the input itself.
		$condition = blocklane_pro_forms_field_condition( $attributes );
		$cond_json = null === $condition ? '' : (string) wp_json_encode( $condition );
		if ( '' !== $cond_json ) {
			$wrapper_args['data-bl-cond'] = $cond_json;
			$wrapper_args['data-bl-name'] = $name;
		}
		if ( $style_on_wrapper && '' !== $style ) {
			$wrapper_args['style'] = $style;
			$style                 = '';
		}

		return array(
			'form_id'      => $form_id,
			'name'         => $name,
			'id'           => blocklane_pro_forms_field_id( $form_id, $name ),
			'label'        => isset( $attributes['label'] ) ? (string) $attributes['label'] : '',
			'required'     => $required,
			// Hidden labels stay in the DOM for assistive tech — the class
			// clips them visually (style.scss); render + canvas mirror it.
			'label_class'  => 'blocklane-form__label' . ( ! empty( $attributes['hideLabel'] ) ? ' is-visually-hidden' : '' ),
			'control_css'  => $style,
			'wrapper_args' => $wrapper_args,
			'cond_json'    => $cond_json,
		);
	}

	/**
	 * Inline CSS for a field's inner control from its border/color supports.
	 *
	 * The input/textarea/select blocks skip-serialize border + color (block.json)
	 * so those don't land on the wrapper div; this rebuilds them onto the actual
	 * control — the same "styles hit the real input" contract as the form-level
	 * Input Styles. Custom values go through the style engine; named presets map
	 * to their CSS custom properties (the has-*-color classes are skipped too).
	 *
	 * @param array $attributes Block attributes.
	 * @return string Safe CSS declarations ('' when nothing is set).
	 */
	function blocklane_pro_forms_control_style( $attributes ) {
		$css   = '';
		$style = isset( $attributes['style'] ) && is_array( $attributes['style'] ) ? $attributes['style'] : array();

		$subset = array();
		if ( isset( $style['border'] ) ) {
			$subset['border'] = $style['border'];
		}
		if ( isset( $style['color'] ) ) {
			$subset['color'] = $style['color'];
		}
		if ( $subset && function_exists( 'wp_style_engine_get_styles' ) ) {
			$engine = wp_style_engine_get_styles( $subset );
			if ( ! empty( $engine['css'] ) ) {
				$css .= $engine['css'];
				if ( '' !== $css && ';' !== substr( $css, -1 ) ) {
					$css .= ';';
				}
			}
		}

		$presets = array(
			'backgroundColor' => 'background-color',
			'textColor'       => 'color',
			'borderColor'     => 'border-color',
		);
		foreach ( $presets as $attr => $property ) {
			if ( ! empty( $attributes[ $attr ] ) ) {
				$css .= $property . ':var(--wp--preset--color--' . sanitize_key( $attributes[ $attr ] ) . ');';
			}
		}

		return $css;
	}

	/**
	 * Resolve one choice's SUBMITTED value from its form-option attributes —
	 * the ONE rule every choice surface shares (select render + schema via
	 * select_options; group row render; group schema derivation). The
	 * resolved value passes through sanitize_text_field: posted values are
	 * always sanitized on receipt, so anything the render emits must be
	 * byte-identical to what the server will accept — an authored value
	 * with collapsible whitespace could otherwise render and select fine
	 * yet never validate. A value that sanitizes away entirely falls back
	 * to the (stripped, sanitized) label.
	 *
	 * @param array $attributes form-option attributes ({label, value}).
	 * @return string '' when neither value nor label survives.
	 */
	function blocklane_pro_forms_option_value( $attributes ) {
		$value = isset( $attributes['value'] ) ? sanitize_text_field( (string) $attributes['value'] ) : '';
		if ( '' === $value ) {
			$label = isset( $attributes['label'] ) ? (string) $attributes['label'] : '';
			$value = sanitize_text_field( wp_strip_all_tags( $label ) );
		}

		return $value;
	}

	/**
	 * Resolve a dropdown's option list from form-option attribute arrays.
	 *
	 * ONE helper feeds BOTH the front render (form-select/render.php) and
	 * schema derivation (the form-select case in collect_fields) — the
	 * render <-> schema invariant for selects lives here. Labels are RichText
	 * and may carry markup a real <option> cannot show, so labels are
	 * stripped; an option with an empty stripped label is dropped on both
	 * sides; the submitted value resolves through
	 * blocklane_pro_forms_option_value() (label fallback + the
	 * sanitize_text_field byte-parity rule).
	 *
	 * @param array $option_attrs_list Arrays of form-option attributes
	 *                                 ({label, value}) — from inner blocks
	 *                                 or the legacy options attribute.
	 * @return array Arrays of {label, value}, labels non-empty strings.
	 */
	function blocklane_pro_forms_select_options( $option_attrs_list ) {
		$options = array();
		foreach ( (array) $option_attrs_list as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}
			$label = wp_strip_all_tags( isset( $option['label'] ) ? (string) $option['label'] : '' );
			if ( '' === $label ) {
				continue;
			}
			$options[] = array(
				'label' => $label,
				'value' => blocklane_pro_forms_option_value( $option ),
			);
		}

		return $options;
	}

	/**
	 * Whether any field inside a form's parsed inner blocks is required —
	 * drives the form-top "required fields" notice.
	 *
	 * The field list is computed ONCE per walk and passed down, not fetched
	 * per block: it runs an apply_filters, and this recurses over every block
	 * of every form on the page (#1035). Not memoized — filters resolve at
	 * call time by contract.
	 *
	 * @param array $inner_blocks Parsed inner blocks (parsed_block format).
	 * @param list<string>|null $field_blocks The walk's field list; null
	 *        computes it (the entry call).
	 * @return bool
	 */
	function blocklane_pro_forms_has_required( $inner_blocks, ?array $field_blocks = null ): bool {
		if ( null === $field_blocks ) {
			$field_blocks = blocklane_pro_forms_field_blocks();
		}
		foreach ( (array) $inner_blocks as $inner ) {
			if ( ! is_array( $inner ) ) {
				continue;
			}
			$name = isset( $inner['blockName'] ) ? $inner['blockName'] : '';
			// Only a field block THIS EDITION registers can promise a required
			// value; a contributor's block under free renders nothing and must
			// not put an asterisk claim on the form (rule 6).
			if ( in_array( $name, $field_blocks, true )
				&& ! empty( $inner['attrs']['required'] ) ) {
				return true;
			}
			if ( ! empty( $inner['innerBlocks'] ) && blocklane_pro_forms_has_required( $inner['innerBlocks'], $field_blocks ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The honeypot field name. Deliberately shaped like a real field — bots
	 * fill plausible-looking inputs; humans never see it.
	 *
	 * @return string
	 */
	function blocklane_pro_forms_honeypot_name() {
		/**
		 * Filter the honeypot field name.
		 *
		 * @param string $name Field name (default 'bl_contact_me').
		 */
		return apply_filters( 'blocklane_pro_forms_honeypot_name', 'bl_contact_me' );
	}

	/**
	 * The time-trap arming delay in milliseconds — how long after load the
	 * view module marks the submission human. A submit before this elapses is
	 * classified as a bot. Filterable so sites with unusually fast (or slow)
	 * genuine visitors can tune it; rendered onto the form for the view module.
	 *
	 * @return int
	 */
	function blocklane_pro_forms_time_trap_delay() {
		/**
		 * Filter the time-trap arming delay (ms, default 3000).
		 *
		 * @param int $delay Milliseconds.
		 */
		return max( 0, (int) apply_filters( 'blocklane_pro_forms_time_trap_delay', 3000 ) );
	}

	/**
	 * Cloudflare Turnstile state (v1.1). Reads the module option directly —
	 * this must work without the Forms class, which the runtime never loads.
	 * Turnstile is ACTIVE only when the toggle is on AND both keys are set:
	 * a half-configured Turnstile degrades to "no captcha", never to "no
	 * submissions" (the dashboard surfaces the misconfiguration).
	 *
	 * Test mode (the exception to the both-keys rule): when
	 * turnstile_test_mode is on, Cloudflare's official dummy keys are swapped
	 * in HERE — the one seam render and verify both resolve through — so the
	 * widget renders and every check passes with no keys configured. Stored
	 * keys are ignored (not touched) while it's on; the dashboard banners own
	 * making the state loud, this resolver never does anything silent beyond
	 * the swap itself.
	 *
	 * @return array { active: bool, test_mode: bool, site_key: string, secret_key: string }
	 */
	function blocklane_pro_forms_turnstile() {
		$settings   = blocklane_pro_forms_settings();
		$site_key   = isset( $settings['turnstile_site_key'] ) ? trim( (string) $settings['turnstile_site_key'] ) : '';
		$secret_key = blocklane_pro_forms_decrypt_secret( isset( $settings['turnstile_secret_key'] ) ? trim( (string) $settings['turnstile_secret_key'] ) : '' );
		$test_mode  = ! empty( $settings['turnstile_enabled'] ) && ! empty( $settings['turnstile_test_mode'] );
		if ( $test_mode ) {
			// The visible always-pass pair: the widget renders (with
			// Cloudflare's own "testing only" notice) and siteverify accepts
			// every token — WYSIWYG for the front end, zero setup.
			$site_key   = '1x00000000000000000000AA';
			$secret_key = '1x0000000000000000000000000000000AA';
		}
		$active     = $test_mode || ( ! empty( $settings['turnstile_enabled'] ) && '' !== $site_key && '' !== $secret_key );
		$theme      = isset( $settings['turnstile_theme'] ) ? (string) $settings['turnstile_theme'] : 'auto';
		$theme      = in_array( $theme, array( 'auto', 'light', 'dark' ), true ) ? $theme : 'auto';
		$appearance = isset( $settings['turnstile_appearance'] ) ? (string) $settings['turnstile_appearance'] : 'always';
		$appearance = in_array( $appearance, array( 'always', 'interaction-only' ), true ) ? $appearance : 'always';
		$size       = isset( $settings['turnstile_size'] ) ? (string) $settings['turnstile_size'] : 'normal';
		$size       = in_array( $size, array( 'normal', 'flexible', 'compact' ), true ) ? $size : 'normal';

		// Widget language follows the SITE locale, not the visitor's browser
		// (Turnstile's 'auto') — the widget should read like the page around
		// it. Turnstile distinguishes some languages by region (zh-cn vs
		// zh-tw, pt vs pt-br), so those locales map explicitly before the
		// two-letter truncation; filterable for multilingual setups.
		$locale   = (string) get_locale();
		$regional = array(
			'zh_CN' => 'zh-cn',
			'zh_SG' => 'zh-cn',
			'zh_TW' => 'zh-tw',
			'zh_HK' => 'zh-tw',
			'pt_BR' => 'pt-br',
		);
		if ( isset( $regional[ $locale ] ) ) {
			$language = $regional[ $locale ];
		} else {
			$language = strtolower( substr( $locale, 0, 2 ) );
			$language = preg_match( '/^[a-z]{2}$/', $language ) ? $language : 'auto';
		}
		/**
		 * Filter the Turnstile widget language ('auto' or an ISO 639-1 code,
		 * optionally region-qualified like 'zh-cn').
		 *
		 * @param string $language Resolved language.
		 */
		$language = apply_filters( 'blocklane_pro_forms_turnstile_language', $language );
		$language = is_string( $language ) && preg_match( '/^(auto|[a-z]{2}(-[a-z]{2})?)$/', $language ) ? $language : 'auto';

		/**
		 * Filter the resolved Turnstile state. Note the widget TYPE (Managed /
		 * Non-Interactive / Invisible) is a property of the site key, set on
		 * the widget in the Cloudflare dashboard — not controllable here.
		 *
		 * @param array $state { active, test_mode, site_key, secret_key, theme, appearance, size }
		 */
		return apply_filters(
			'blocklane_pro_forms_turnstile',
			array(
				'active'     => $active,
				'test_mode'  => $test_mode,
				'site_key'   => $site_key,
				'secret_key' => $secret_key,
				'theme'      => $theme,
				'appearance' => $appearance,
				'size'       => $size,
				'language'   => $language,
			)
		);
	}

	/**
	 * A field's sanitized visibility condition (v3 conditional logic), or
	 * null when it has none. One rule per field on purpose (simple-first):
	 * { field, operator, value } — show this field only when the referenced
	 * field's value satisfies the operator.
	 *
	 * @param array $attrs Field attributes.
	 * @return array|null
	 */
	function blocklane_pro_forms_field_condition( $attrs ) {
		$cond = isset( $attrs['condition'] ) && is_array( $attrs['condition'] ) ? $attrs['condition'] : null;
		if ( ! $cond || empty( $cond['enabled'] ) ) {
			return null;
		}
		$field    = isset( $cond['field'] ) ? sanitize_text_field( (string) $cond['field'] ) : '';
		$operator = isset( $cond['operator'] ) ? (string) $cond['operator'] : 'is';
		if ( '' === $field
			|| ! in_array( $operator, array( 'is', 'is_not', 'contains', 'empty', 'not_empty', 'gt', 'lt' ), true ) ) {
			return null;
		}

		return array(
			'field'    => $field,
			'operator' => $operator,
			'value'    => isset( $cond['value'] ) ? sanitize_text_field( (string) $cond['value'] ) : '',
		);
	}

	/**
	 * Normalize a value before comparing it. MIRRORED in view.js (condNorm).
	 *
	 * Every run of whitespace — the newlines a textarea carries, the double
	 * space someone types, the NBSP a browser pastes in — collapses to one
	 * space, then trims. Listed explicitly rather than left to each language's
	 * defaults, because JS trim() strips U+00A0 and PHP trim() does not: that
	 * single difference is enough to make the two evaluators disagree, and a
	 * disagreement here silently discards the visitor's answer.
	 *
	 * Deliberately does NOT strip tags. The server used to run ref values
	 * through sanitize_text_field() while the client compared the raw DOM
	 * value; the values here are only ever COMPARED — never stored, never
	 * echoed — so sanitizing bought nothing and cost agreement.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	function blocklane_pro_forms_cond_norm( $value ) {
		$value    = (string) $value;
		// ONE explicit class, spelled out identically in view.js. Neither
		// language's \s defines the set, because they disagree and both drift:
		// PHP's /u enables PCRE2_UCP so \s there is the full Unicode
		// White_Space set (it adds U+0085, and U+180E on current PCRE2),
		// while JS \s omits those two and adds U+FEFF. Letting \s stand on
		// either side is what split the two evaluators twice already. Written
		// out, the mirror is a literal string you can compare by eye.
		$collapsed = preg_replace( '/[\t\n\x{000B}\f\r \x{0085}\x{00A0}\x{1680}\x{180E}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]+/u', ' ', $value );

		// preg_replace returns null on invalid UTF-8; comparing the raw value
		// is better than comparing nothing.
		return trim( null === $collapsed ? $value : $collapsed );
	}

	/**
	 * Case-fold for comparison. MIRRORED in view.js (condFold).
	 *
	 * mb_strtolower, not strtolower: strcasecmp/stripos fold ASCII bytes only,
	 * so "CAFÉ" and "café" matched in the browser (JS toLowerCase is Unicode)
	 * and never on the server. Falls back to strtolower where mbstring is
	 * absent — still better than a byte compare, and the two sides then agree
	 * for every ASCII input, which is the overwhelming majority.
	 *
	 * @param string $value Normalized value.
	 * @return string
	 */
	function blocklane_pro_forms_cond_fold( $value ) {
		$folded = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );

		// Final sigma folds to plain sigma on both sides. mb_strtolower only
		// learned this in PHP 8.3 while JS toLowerCase has always done it, so
		// without the explicit map the two evaluators disagree on any Greek
		// word ending in Σ — on exactly the PHP versions this plugin still
		// supports. Doing it here removes the version dependence entirely.
		return str_replace( "\xCF\x82", "\xCF\x83", $folded );
	}

	/**
	 * Whether a normalized value is a number for gt/lt. MIRRORED in view.js.
	 *
	 * An explicit decimal pattern on BOTH sides rather than is_numeric here and
	 * parseFloat there: parseFloat accepts a numeric PREFIX ("12 units" -> 12,
	 * "1,5" -> 1) and is_numeric does not, so the browser showed a field the
	 * server then treated as hidden and discarded. It also settles the
	 * multi-value case — a checkbox group joins to "7, 3", which both sides now
	 * agree is not a number.
	 *
	 * @param string $value Normalized value.
	 * @return bool
	 */
	function blocklane_pro_forms_cond_is_num( $value ) {
		return '' !== $value && 1 === preg_match( '/^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?$/', $value );
	}

	/**
	 * Evaluate one condition against a referenced field's effective value.
	 * Mirrored VERBATIM in view.js (evaluateCondition) — the two must stay
	 * in sync or live show/hide and server enforcement disagree.
	 *
	 * @param array        $cond      Sanitized condition.
	 * @param string|array $ref_value The referenced field's effective value.
	 * @return bool Whether the owning field SHOWS.
	 */
	function blocklane_pro_forms_condition_passes( $cond, $ref_value ) {
		$values = is_array( $ref_value ) ? array_map( 'strval', $ref_value ) : array( (string) $ref_value );

		// An empty array and an absent value must read the same. A checkable
		// group with nothing ticked arrives as [] in the browser and as ''
		// here; without this, "is ''" was TRUE server-side and FALSE
		// client-side — and an empty target is the shipped default for a new
		// rule, so this was the most reachable divergence of all.
		if ( ! $values ) {
			$values = array( '' );
		}

		$values = array_map( 'blocklane_pro_forms_cond_norm', $values );
		$joined = blocklane_pro_forms_cond_norm( implode( ', ', $values ) );
		$target = blocklane_pro_forms_cond_norm( $cond['value'] );

		$folded_values = array_map( 'blocklane_pro_forms_cond_fold', $values );
		$folded_joined = blocklane_pro_forms_cond_fold( $joined );
		$folded_target = blocklane_pro_forms_cond_fold( $target );

		switch ( $cond['operator'] ) {
			case 'is':
				return in_array( $folded_target, $folded_values, true );
			case 'is_not':
				return ! in_array( $folded_target, $folded_values, true );
			case 'contains':
				return '' !== $target && false !== strpos( $folded_joined, $folded_target );
			case 'empty':
				return '' === $joined;
			case 'not_empty':
				return '' !== $joined;
			case 'gt':
			case 'lt':
				if ( ! blocklane_pro_forms_cond_is_num( $joined ) || ! blocklane_pro_forms_cond_is_num( $target ) ) {
					return false;
				}
				return 'gt' === $cond['operator'] ? (float) $joined > (float) $target : (float) $joined < (float) $target;
		}

		return true;
	}

	/**
	 * Resolve which schema fields are HIDDEN for this submission (v3).
	 *
	 * The rule, mirrored in view.js: a hidden field's effective value is ''
	 * (its posted value is untrusted — hiding is why it must not smuggle
	 * data in); conditions are evaluated against effective values in schema
	 * order, and passes repeat — only ever ADDING to the hidden set — until
	 * stable (monotone, so chains resolve deterministically and cycles
	 * cannot oscillate). A condition referencing an unknown field reads ''.
	 *
	 * @param array $schema Field schemas keyed by name.
	 * @param array $params Posted params.
	 * @return array Hidden field names (values as keys for O(1) lookup).
	 */
	function blocklane_pro_forms_resolve_visibility( $schema, $params ) {
		$hidden = array();
		$count  = count( $schema );

		for ( $pass = 0; $pass <= $count; $pass++ ) {
			$changed = false;
			foreach ( $schema as $name => $field ) {
				if ( isset( $hidden[ $name ] ) || empty( $field['condition'] ) ) {
					continue;
				}
				$cond = $field['condition'];
				$ref  = $cond['field'];

				$ref_value = '';
				if ( ! isset( $hidden[ $ref ] ) && isset( $schema[ $ref ] ) ) {
					if ( 'hidden' === ( isset( $schema[ $ref ]['type'] ) ? $schema[ $ref ]['type'] : '' ) ) {
						// A hidden field's value is AUTHORED, not visitor input.
						// The validation loop already treats the block's own
						// value as authoritative and distrusts what was posted;
						// a rule READING one has to follow the same authority,
						// or a visitor editing the hidden input in devtools
						// steers which fields the server thinks are active —
						// and hiding a required field is how you skip it.
						$ref_value = isset( $schema[ $ref ]['value'] ) ? (string) $schema[ $ref ]['value'] : '';
					} elseif ( isset( $params[ $ref ] ) ) {
						// Unslashed but NOT sanitized: these values are only
						// compared, never stored or echoed from here, and
						// sanitize_text_field collapsed whitespace and stripped
						// tags on this side only — the exact split that made the
						// two evaluators disagree.
						// blocklane_pro_forms_cond_norm() normalizes both sides.
						$ref_value = is_array( $params[ $ref ] )
							? array_map( static function ( $v ) {
								return wp_unslash( (string) $v );
							}, $params[ $ref ] )
							: wp_unslash( (string) $params[ $ref ] );
					}
				}

				if ( ! blocklane_pro_forms_condition_passes( $cond, $ref_value ) ) {
					$hidden[ $name ] = true;
					$changed         = true;
				}
			}
			if ( ! $changed ) {
				break;
			}
		}

		return $hidden;
	}

	/**
	 * Form availability (v3 per-form gates): schedule window + login
	 * requirement, resolved from the form's own attributes. Enforced in
	 * BOTH render (closed forms never put fields in the DOM) and the
	 * submission handler (the enforcement — a cached page may show a stale
	 * open form past its close; the server gate is what counts). Closed
	 * answers are HONEST: availability is form state, not bot detection —
	 * a real visitor at a closed form must learn it's closed.
	 *
	 * @param array $attrs Form attributes (defaults merged).
	 * @return array { open: bool, message: string }
	 */
	function blocklane_pro_forms_availability( $attrs ) {
		$message = isset( $attrs['closedMessage'] ) && '' !== trim( (string) $attrs['closedMessage'] )
			? sanitize_text_field( (string) $attrs['closedMessage'] )
			: __( 'This form is not currently accepting submissions.', 'blocklane' );

		if ( ! empty( $attrs['requireLogin'] ) && ! is_user_logged_in() ) {
			return array(
				'open'    => false,
				'message' => $message,
			);
		}

		if ( ! empty( $attrs['scheduleEnabled'] ) ) {
			$zone  = wp_timezone();
			$now   = new \DateTimeImmutable( 'now', $zone );
			$start = isset( $attrs['scheduleStart'] ) ? trim( (string) $attrs['scheduleStart'] ) : '';
			$end   = isset( $attrs['scheduleEnd'] ) ? trim( (string) $attrs['scheduleEnd'] ) : '';
			// Unparseable datetimes fail toward OPEN — a typo'd window must
			// not silently close a working form (the inspector validates the
			// format; this is the belt for content edited by other means).
			if ( '' !== $start ) {
				$start_at = date_create_immutable( $start, $zone );
				if ( $start_at && $now < $start_at ) {
					return array(
						'open'    => false,
						'message' => $message,
					);
				}
			}
			if ( '' !== $end ) {
				$end_at = date_create_immutable( $end, $zone );
				if ( $end_at && $now > $end_at ) {
					return array(
						'open'    => false,
						'message' => $message,
					);
				}
			}
		}

		return array(
			'open'    => true,
			'message' => '',
		);
	}

	/**
	 * Claim a short-lived lock for one form + unique-field + value.
	 *
	 * The uniqueness probe reads, and the row is inserted later by the
	 * storage listener — so two requests carrying the same value can both
	 * pass the probe before either has written, and both get stored. This
	 * closes the window.
	 *
	 * INSERT IGNORE straight into the options table, NOT add_option(): core
	 * implements add_option as INSERT ... ON DUPLICATE KEY UPDATE, so a
	 * concurrent duplicate becomes an UPDATE with truthy affected-rows and
	 * BOTH racers believe they won. Version_Migration learned this the same
	 * way; this is the same technique.
	 *
	 * Three outcomes, because "could not take the lock" and "someone else holds
	 * it" must not be confused — the first is our problem, the second is the
	 * caller's answer:
	 *
	 *   string  claimed; release this key.
	 *   false   a LIVE holder — treat as the duplicate it is about to become.
	 *   null    the lock could not be operated (a DB error). FAIL OPEN: a
	 *           mutex that cannot be taken must never reject a genuine
	 *           submission, and the probe below still catches real duplicates.
	 *
	 * @param string $form_id    Form id.
	 * @param string $field_name Unique field.
	 * @param string $value      Submitted value.
	 * @return string|false|null Key on claim, false if held, null on error.
	 */
	function blocklane_pro_forms_claim_unique_lock( $form_id, $field_name, $value ) {
		global $wpdb;

		$key = 'blocklane_pro_forms_ulock_' . md5( $form_id . '|' . $field_name . '|' . strtolower( $value ) );
		$now = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a mutex; the options cache defeats the point.
		$claimed = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'no' )",
				$key,
				(string) $now
			)
		);

		if ( $claimed ) {
			return $key;
		}

		// INSERT IGNORE returns 0 both when the row exists AND on a swallowed
		// error, so a zero alone says nothing. Read the row: present means a
		// real holder, absent means the insert failed for another reason and
		// we fail open rather than reject someone's form.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reading the live lock row.
		$held = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
		if ( null === $held ) {
			return null;
		}

		// A stale row (a request that died mid-flight) must not lock the value
		// out forever — 30s is far longer than a submission.
		$held = (int) $held;
		if ( $held && ( $now - $held ) < 30 ) {
			return false;
		}

		// Delete ONLY the row we observed. An unconditional delete let two
		// reclaimers both succeed and both re-INSERT, which is exactly the
		// duplicate this lock exists to prevent.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- conditional reclaim; one winner.
		$reclaimed = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				$key,
				(string) $held
			)
		);
		if ( ! $reclaimed ) {
			// Someone else reclaimed first — they hold it now.
			return false;
		}

		// The options cache still holds the row we just deleted behind WP's
		// back; without this a later get_option() would serve a ghost.
		wp_cache_delete( $key, 'options' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one winner among reclaimers.
		return $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'no' )",
				$key,
				(string) $now
			)
		) ? $key : null;
	}

	/**
	 * Whether a validated value already exists for this form's unique field
	 * (v3 unique entry). search_text prefilters (it holds every submitted
	 * value), then the stored field snapshots confirm the exact field —
	 * case-insensitive, matching email semantics, which is the typical
	 * unique key. Bounded scan: newest 500 candidate rows.
	 *
	 * @param string $form_id    Form id.
	 * @param string $field_name Schema field name.
	 * @param string $value      Validated submitted value.
	 * @return bool
	 */
	function blocklane_pro_forms_is_duplicate_entry( $form_id, $field_name, $value ) {
		global $wpdb;
		$table = blocklane_pro_forms_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own submissions table; a duplicate check must read what is actually stored.
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT fields FROM %i WHERE form_id = %s AND search_text LIKE %s ORDER BY id DESC LIMIT 500',
				$table,
				sanitize_title( $form_id ),
				'%' . $wpdb->esc_like( $value ) . '%'
			)
		);
		foreach ( (array) $rows as $json ) {
			$stored = json_decode( (string) $json, true );
			foreach ( (array) $stored as $field ) {
				if ( is_array( $field )
					&& isset( $field['name'], $field['value'] )
					&& $field['name'] === $field_name
					&& is_string( $field['value'] )
					&& 0 === strcasecmp( trim( $field['value'] ), $value ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Secrets at rest (v3): sodium secretbox keyed from the WP auth salts,
	 * stored as "bl1:<base64(nonce + ciphertext)>". Meaningful against
	 * DB-only exposure (SQLi, dumps, backups) — the salts live in
	 * wp-config, not the database. Consequences owned on purpose:
	 * rotating AUTH_KEY/SECURE_AUTH_KEY orphans the ciphertext, decrypt
	 * then returns '' and Turnstile degrades to "key missing" with its
	 * banner prompting re-entry — honest surfacing, never a fatal. No
	 * sodium (exotic PHP builds) → values pass through as plaintext.
	 */
	function blocklane_pro_forms_secret_key_material() {
		$salt = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' );
		if ( '' === $salt ) {
			return '';
		}

		return hash( 'sha256', 'blocklane-pro-forms-secret|' . $salt, true );
	}

	/**
	 * Encrypt a secret for storage. '' and already-encrypted values pass
	 * through; missing sodium or salts → plaintext (never fatal).
	 *
	 * @param string $value Plaintext secret.
	 * @return string
	 */
	function blocklane_pro_forms_encrypt_secret( $value ) {
		$value = (string) $value;
		if ( '' === $value || 0 === strpos( $value, 'bl1:' ) || ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return $value;
		}
		$key = blocklane_pro_forms_secret_key_material();
		if ( '' === $key ) {
			return $value;
		}
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $value, $nonce, $key );

		return 'bl1:' . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary-safe storage encoding, not obfuscation.
	}

	/**
	 * Decrypt a stored secret. Unprefixed values pass through (plaintext
	 * predates encryption — migrates on next save); undecryptable
	 * ciphertext (rotated salts, corruption) returns '' so the feature
	 * reads unconfigured instead of sending garbage to a verifier.
	 *
	 * @param string $value Stored value.
	 * @return string
	 */
	function blocklane_pro_forms_decrypt_secret( $value ) {
		$value = (string) $value;
		if ( 0 !== strpos( $value, 'bl1:' ) ) {
			return $value;
		}
		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return '';
		}
		$key = blocklane_pro_forms_secret_key_material();
		$raw = base64_decode( substr( $value, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- storage decoding.
		if ( '' === $key || false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, $key );

		return false === $plain ? '' : $plain;
	}

	/**
	 * Whether a value is one of Cloudflare's dummy/test Turnstile keys —
	 * the family test mode supplies on purpose. Pattern-matched, not a
	 * hardcoded list: every documented dummy site key is [123]x + twenty
	 * zeros + two hex letters, every dummy secret is [123]x + thirty-one
	 * zeros + AA.
	 *
	 * Dummies are FORBIDDEN in the stored key fields (settings sanitize
	 * strips them on every save): test mode owns dummy usage entirely, so a
	 * stored dummy is never legitimate config — only pre-test-mode residue.
	 *
	 * @param string $key Key value to test.
	 * @return bool
	 */
	function blocklane_pro_forms_turnstile_is_dummy_key( $key ) {
		$key = trim( (string) $key );

		return (bool) preg_match( '/^[123]x0{20}[A-F]{2}$/i', $key )
			|| (bool) preg_match( '/^[123]x0{31}AA$/i', $key );
	}

	/**
	 * Whether the STORED Turnstile keys are Cloudflare's dummies. With test
	 * mode OFF these accept (or reject) every visitor regardless of
	 * humanity, so a site that launches carrying them has no real spam
	 * protection; the Forms screen banners on this. Sanitize purges dummies
	 * on every save, so this only fires for pre-test-mode stored residue
	 * that hasn't been re-saved yet.
	 *
	 * @return bool
	 */
	function blocklane_pro_forms_turnstile_test_keys_stored() {
		$settings = blocklane_pro_forms_settings();

		return blocklane_pro_forms_turnstile_is_dummy_key( isset( $settings['turnstile_site_key'] ) ? $settings['turnstile_site_key'] : '' )
			|| blocklane_pro_forms_turnstile_is_dummy_key( isset( $settings['turnstile_secret_key'] ) ? $settings['turnstile_secret_key'] : '' );
	}

	/**
	 * The widget action for a form — the formId squeezed into Turnstile's
	 * action charset (alphanumeric/_/-, max 32). Rendered on the widget AND
	 * compared against siteverify's echo, so a token solved on one form
	 * cannot be replayed against another; it also gives per-form solve
	 * analytics in the Cloudflare dashboard. Render and verify MUST resolve
	 * through this one helper (the render↔verify invariant).
	 *
	 * @param string $form_id Form id ('' falls back to the generic action).
	 * @return string
	 */
	function blocklane_pro_forms_turnstile_action( $form_id ) {
		// sanitize_title FIRST (idempotent): the render side receives the raw
		// formId attribute via context while the server receives the already-
		// sanitized posted id — normalizing inside the helper keeps the two
		// derivations identical by construction.
		$action = sanitize_title( (string) $form_id );
		$action = preg_replace( '/[^a-zA-Z0-9_-]/', '-', $action );
		$action = substr( $action, 0, 32 );

		return '' !== $action ? $action : 'blocklane-form';
	}

	/**
	 * Verify a Turnstile token against Cloudflare's siteverify endpoint.
	 *
	 * Failure policy (the render↔schema lesson applied to captcha): a REAL
	 * submission must never be silently swallowed, so unlike the honeypot and
	 * time-trap this layer answers honestly — the caller returns a visible
	 * 400 and the widget resets. Token problems (missing/expired/duplicate —
	 * tokens live 300s and are single-use) fail CLOSED; transport failures
	 * and secret misconfiguration fail OPEN (our problem must not become the
	 * visitor's), filterable via blocklane_pro_forms_turnstile_fail_open.
	 *
	 * @param string $token           The posted cf-turnstile-response token.
	 * @param string $secret          The Turnstile secret key.
	 * @param string $expected_action The widget action this form renders
	 *                                (blocklane_pro_forms_turnstile_action);
	 *                                '' skips the check.
	 * @return array { pass: bool, reason: string }
	 */
	function blocklane_pro_forms_turnstile_verify( $token, $secret, $expected_action = '' ) {
		if ( '' === $token ) {
			return array(
				'pass'   => false,
				'reason' => 'missing-token',
			);
		}

		// Cloudflare Turnstile is an opt-in, documented service (readme FAQ
		// "Does this plugin contact any external services?"): this request is
		// made only when the site owner has enabled Turnstile and entered their
		// own keys, and it carries the widget token, the owner's secret key and
		// the submitter's IP — never form field values. Guideline 8 permits
		// service calls; the sniff cannot tell a service from an offload.
		$response = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify', // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- documented opt-in service, see the readme FAQ.
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => \blocklane_pro\Helper::client_address( 'forms' ),
				),
			)
		);

		$body  = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		$codes = is_array( $body ) && isset( $body['error-codes'] ) && is_array( $body['error-codes'] ) ? $body['error-codes'] : array();

		/**
		 * Filter whether Turnstile fails open when verification itself breaks
		 * (Cloudflare unreachable, or the secret key is wrong).
		 *
		 * @param bool   $fail_open Default true.
		 * @param string $reason    'unreachable' or 'misconfigured'.
		 */
		// Secret misconfiguration is classified by error CODES before the
		// status gate: Cloudflare answers invalid-input-secret with HTTP 400
		// (a real verdict body on a non-200), which the status-first order
		// used to lump in with 'unreachable'.
		if ( array_intersect( array( 'invalid-input-secret', 'missing-input-secret' ), $codes ) ) {
			return array(
				'pass'   => (bool) apply_filters( 'blocklane_pro_forms_turnstile_fail_open', true, 'misconfigured' ),
				'reason' => 'misconfigured',
			);
		}

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || ! is_array( $body ) ) {
			return array(
				'pass'   => (bool) apply_filters( 'blocklane_pro_forms_turnstile_fail_open', true, 'unreachable' ),
				'reason' => 'unreachable',
			);
		}

		if ( ! empty( $body['success'] ) ) {
			// The action echo binds the token to THIS form's widget — a token
			// solved on another form fails closed. Only enforced when the
			// response carries an action: Cloudflare's dummy test keys (and
			// tokens minted before an action existed) omit it, and an absent
			// echo proves nothing either way.
			$echoed = isset( $body['action'] ) ? (string) $body['action'] : '';
			if ( '' !== $expected_action && '' !== $echoed && $echoed !== $expected_action ) {
				return array(
					'pass'   => false,
					'reason' => 'action-mismatch',
				);
			}

			return array(
				'pass'   => true,
				'reason' => 'verified',
			);
		}

		$codes = isset( $body['error-codes'] ) && is_array( $body['error-codes'] ) ? $body['error-codes'] : array();
		if ( array_intersect( array( 'invalid-input-secret', 'missing-input-secret' ), $codes ) ) {
			return array(
				'pass'   => (bool) apply_filters( 'blocklane_pro_forms_turnstile_fail_open', true, 'misconfigured' ),
				'reason' => 'misconfigured',
			);
		}

		return array(
			'pass'   => false,
			'reason' => 'rejected',
		);
	}

	/**
	 * The "Turnstile is broken" flag: set when verification fails for a
	 * CONFIGURATION reason (rejected secret, Cloudflare unreachable) — the
	 * moment the module silently fails open — so the Forms screen can say
	 * so. Real visitor traffic is the detector (no polling): the first
	 * config-failed verification raises it, and any verdict that proves the
	 * config healthy clears it. The settings-save probe (controller) feeds
	 * the same flag, so both detectors drive one banner. The week-long
	 * expiry is only a backstop for sites that never submit again.
	 *
	 * @param string $reason 'misconfigured' or 'unreachable'.
	 */
	function blocklane_pro_forms_turnstile_flag_broken( $reason ) {
		set_transient(
			'blocklane_pro_forms_turnstile_broken',
			array(
				'reason' => (string) $reason,
				'time'   => time(),
			),
			WEEK_IN_SECONDS
		);
	}

	/**
	 * Clear the broken flag — a healthy verification proves the config.
	 */
	function blocklane_pro_forms_turnstile_clear_broken() {
		delete_transient( 'blocklane_pro_forms_turnstile_broken' );
	}

	/**
	 * The current broken flag.
	 *
	 * @return array|false { reason, time } or false when healthy.
	 */
	function blocklane_pro_forms_turnstile_broken() {
		$broken = get_transient( 'blocklane_pro_forms_turnstile_broken' );

		return is_array( $broken ) ? $broken : false;
	}

	/*
	 * ------------------------------------------------------------------
	 * Submission pipeline (Phase 2).
	 *
	 * The public submit route lives on the BRAND namespace `blocklane/v1`,
	 * not `blocklane-pro/v1`. Historically the pro namespace was
	 * license-gated and a visitor's submission is CONTENT that had to
	 * survive a lapsed key; the gate is gone (nothing gates on the license
	 * at runtime), but the split stays — the pro namespace is management
	 * surface behind manage_options, and a public route does not belong on
	 * it. Management REST (inbox, Phase 4) stays on the pro namespace.
	 * ------------------------------------------------------------------
	 */

	/**
	 * Register the public submission route.
	 */
	function blocklane_pro_forms_register_rest() {
		register_rest_route(
			'blocklane/v1',
			'/forms/submissions',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => '__return_true',
				'callback'            => 'blocklane_pro_forms_handle_submission',
			)
		);
	}
	add_action( 'rest_api_init', 'blocklane_pro_forms_register_rest' );

	/**
	 * The success payload. Fake successes (bot-classified rejections) return
	 * EXACTLY this shape so rejection is indistinguishable from acceptance.
	 *
	 * @param array $form_attrs The form block's attributes.
	 * @return WP_REST_Response
	 */
	function blocklane_pro_forms_success_response( $form_attrs ) {
		$action = isset( $form_attrs['successAction'] ) && 'redirect' === $form_attrs['successAction'] ? 'redirect' : 'message';
		// Replace-form-with-message (v2) rides the response, not a
		// render-time data attribute: the handler resolves config live, so a
		// cached page can never carry a stale value — and fake successes
		// carry the same resolved flag. Computed BEFORE the empty-redirect
		// fallback below, mirroring the UI model: both editing surfaces only
		// offer the replace toggle while the resolved action is "message",
		// so a redirect that quietly falls back must not also replace —
		// behavior no editing surface can display is undebuggable.
		$replace  = 'message' === $action && ! empty( $form_attrs['replaceOnSuccess'] );
		$redirect = '';
		if ( 'redirect' === $action && ! empty( $form_attrs['redirectUrl'] ) ) {
			$redirect = esc_url_raw( (string) $form_attrs['redirectUrl'] );
		}
		if ( '' === $redirect ) {
			$action = 'message';
		}

		return rest_ensure_response(
			array(
				'success'     => true,
				'action'      => $action,
				'redirectUrl' => $redirect,
				'replace'     => $replace,
			)
		);
	}

	/**
	 * The submission rate limiter's key for this client, through the one
	 * derivation every public throttle shares (Helper::client_key(): the
	 * `blocklane_pro_client_ip` filter for the 'forms' door, IPv6 collapsed to
	 * its /64, HMAC with the auth salt); null when the request carries no
	 * address.
	 */
	function blocklane_pro_forms_rate_key(): ?string {
		return \blocklane_pro\Helper::client_key( 'blocklane_pro_forms_rl_', 'forms' );
	}

	/**
	 * Fixed-window rate limit per client key (windows on the clock). Returns
	 * true while within the limit, and the attempt is counted; the count and
	 * the decision are one step of the attempt meter,
	 * \blocklane_pro\Helper::reserve_attempt() (spec 2026-10-12, atomic
	 * throttle door), so simultaneous submissions cannot pass the limit. A
	 * request with no address cannot be counted and is allowed. A meter that
	 * cannot count refuses the submission and logs it (fail closed).
	 */
	function blocklane_pro_forms_within_rate_limit(): bool {
		$key = blocklane_pro_forms_rate_key();
		if ( null === $key ) {
			return true;
		}
		/**
		 * Filter the submission rate limit (attempts per window per IP).
		 *
		 * @param int $limit Attempts (default 5).
		 */
		$limit = max( 1, (int) apply_filters( 'blocklane_pro_forms_rate_limit', 5 ) );
		/**
		 * Filter the rate-limit window in seconds.
		 *
		 * @param int $window Seconds (default 60).
		 */
		$window = max( 10, (int) apply_filters( 'blocklane_pro_forms_rate_window', 60 ) );

		$within = \blocklane_pro\Helper::reserve_attempt( $key, $limit, $window );
		if ( is_wp_error( $within ) ) {
			blocklane_pro_log( 'Blocklane: the contact form limit could not count a submission (' . $within->get_error_code() . '); it was refused.' );
			return false;
		}
		return $within;
	}

	/*
	 * ------------------------------------------------------------------
	 * A form's DEFINITION and its BINDING (spec 2026-10-06-forms-form-
	 * ownership §4.3). The definition is what the submission pipeline reads
	 * from a form: its form-level attributes and its derived field schema.
	 * The binding is a token the form shell mints over it on every render
	 * (`<nonce>.<HMAC>`, v2) and prints as `_bl_bind`, so the public route can
	 * tell which live definition the server rendered for the visitor's page
	 * instead of looking the formId up.
	 * ------------------------------------------------------------------
	 */

	/**
	 * The form attributes the submission pipeline reads: routing, storage,
	 * mail, the success action, the gates and the unique-entry rule. Every one
	 * is signed by the binding. Read only by blocklane_pro_forms_definition();
	 * a fetch anywhere else is a PHPStan error, blocklane.chokepointMember.
	 */
	define(
		'BLOCKLANE_PRO_FORMS_BOUND_ATTRS',
		array( 'formId', 'formName', 'recipients', 'subject', 'successAction', 'redirectUrl', 'storeSubmissions', 'notifyAdmin', 'autoResponder', 'replaceOnSuccess', 'scheduleEnabled', 'scheduleStart', 'scheduleEnd', 'requireLogin', 'closedMessage', 'uniqueField', 'uniqueMessage' )
	);

	/**
	 * The form attributes only the render reads. Not signed: a presentational
	 * edit keeps every cached page's binding valid. With the bound set it is
	 * exactly the block's own attributes in block.json, and the review
	 * battery's WB14 fails a block.json attribute that is in neither, so a new
	 * attribute cannot arrive without this decision. Nothing in the shipped
	 * code reads it (PHPStan blocklane.chokepointMember fails any fetch): it
	 * is the classification, not a door.
	 */
	define(
		'BLOCKLANE_PRO_FORMS_PRESENTATION_ATTRS',
		array( 'inputStyles', 'showProgress', 'showRequiredNotice' )
	);

	/**
	 * The form attributes that decide WHETHER a validated submission is kept,
	 * never how it is routed, each with its MEET rule (spec forms.md v4 TM-A;
	 * security read F3, F4). Deliberately outside the binding: the MAC covers
	 * `attrs` and `schema` only, so adding a decision attribute changes no
	 * binding and no cached page's submissions re-route to the site defaults
	 * when it ships. The definition carries them as its fourth key,
	 * `decision`. The third class beside BOUND and PRESENTATION: WB14 fails a
	 * block.json attribute in none of the three, or in two.
	 * - testMode, unanimous: an attested copy in test mode decides a test
	 *   only when no other live copy the same binding verifies is live
	 *   (resolve()); an unsigned submission is a test only when EVERY live
	 *   copy is in test mode. Any doubt stores.
	 * Read only by blocklane_pro_forms_definition() and
	 * blocklane_pro_forms_verdict(); a fetch anywhere else is a PHPStan
	 * error, blocklane.chokepointMember.
	 */
	define(
		'BLOCKLANE_PRO_FORMS_DECISION_MEET',
		array(
			'testMode' => 'unanimous',
		)
	);

	/**
	 * How each bound attribute is decided — the MEET (spec 2026-10-06-forms-
	 * form-ownership §16.4.5, decisions D-R3/D-R5, security reads S-R1 and
	 * S-R9). Every bound attribute is classified exactly once (WB14 fails a
	 * BOUND key with no rule or a rule outside the vocabulary). For a
	 * submission whose binding verified one copy (attested) every rule takes
	 * that copy's own value; for one decided by EVERY live copy (unattested):
	 * - identity: never a setting (the formId itself);
	 * - unanimous: the value when every copy agrees, else none;
	 * - default: none — the site default fills it (routing never travels
	 *   from a copy the server could not attest, decision D1);
	 * - opt_out: false when every copy resolves to false (explicitly, or by
	 *   inheriting a false site default), else true (D3);
	 * - narrow: false when every copy says false, else the site default — a
	 *   copy can narrow storage, never widen it past the site's own opt-out
	 *   (S-R9);
	 * - off: off (the auto-responder mails a caller-named address with text
	 *   no page attested, D-R5);
	 * - gate: every copy's gate applies, each tuple once (D-R3);
	 * - agree: the value when every copy names the same one, else the
	 *   submission is refused (S-R1: a planted copy can never arm the
	 *   duplicate probe on its own).
	 * Read only by blocklane_pro_forms_verdict(); a fetch anywhere else is a
	 * PHPStan error, blocklane.chokepointMember.
	 */
	define(
		'BLOCKLANE_PRO_FORMS_MEET',
		array(
			'formId'           => 'identity',
			'formName'         => 'unanimous',
			'recipients'       => 'default',
			'subject'          => 'default',
			'successAction'    => 'default',
			'redirectUrl'      => 'default',
			'replaceOnSuccess' => 'default',
			'closedMessage'    => 'default',
			'uniqueMessage'    => 'default',
			'storeSubmissions' => 'narrow',
			'notifyAdmin'      => 'opt_out',
			'autoResponder'    => 'off',
			'requireLogin'     => 'gate',
			'scheduleEnabled'  => 'gate',
			'scheduleStart'    => 'gate',
			'scheduleEnd'      => 'gate',
			'uniqueField'      => 'agree',
		)
	);

	/**
	 * A value in a canonical shape: every associative array ksorted (by key,
	 * as strings), recursively; a list keeps its order. Two definitions that
	 * differ only in key order canonicalize to the same bytes.
	 *
	 * @param mixed $value Any value.
	 * @return mixed
	 */
	function blocklane_pro_forms_canonical( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = blocklane_pro_forms_canonical( $item );
		}
		if ( ! array_is_list( $value ) ) {
			ksort( $value, SORT_STRING );
		}
		return $value;
	}

	/**
	 * A form's definition: what the submission pipeline reads from it.
	 *
	 * `attrs` holds the bound attributes, in constant order, as core's own
	 * validator prepares them for the render (the registered type's
	 * prepare_attributes_for_render() over the block's RAW parsed attributes:
	 * an invalid value is dropped, a default filled), an absent one as null.
	 * `schema` and `ignored` are blocklane_pro_forms_derive_schema()'s, in
	 * the order the handler validates and stores them. `decision` holds the
	 * BLOCKLANE_PRO_FORMS_DECISION_MEET attributes, each strictly `true ===`
	 * its prepared value (core validates a boolean attribute but does not
	 * cast it, so a stored "false" string is false; F7). The binding's MAC
	 * never reads `decision` (F4).
	 *
	 * A PURE FUNCTION OF STORED CONTENT: the block's own attributes, its
	 * inner blocks and the synced patterns they reference. Nothing the
	 * environment decides (an upload cap, a filter's answer) may reach it —
	 * the field-type seam's `collect` rule — or the binding would change
	 * under every cached page whenever the environment did (sweep-1003).
	 *
	 * The render (the form shell, through blocklane_pro_forms_render_binding())
	 * and the resolver (blocklane_pro_forms_resolve()) both feed it a parsed
	 * block, so the two sides compute the same definition from the same
	 * stored bytes. A filter that rewrites the form's parsed block at render
	 * (render_block_data on the form or an ancestor, a hooked block inside
	 * it) makes the render's binding one no stored copy verifies: every
	 * submission of that form then arrives unsigned, for good (spec
	 * 2026-10-06-forms-form-ownership NP16).
	 *
	 * @param array<string, mixed> $form_block A parsed blocklane/form block.
	 * @param int|null             $pool       Synced-pattern descents left to the caller's examination, spent by this
	 *                                         walk (resolve()'s pool); null lends the walk BLOCKLANE_PRO_FORMS_REF_CEILING.
	 * @return array{attrs: array<string, mixed>, schema: array<string, array<string, mixed>>, ignored: list<string>, decision: array<string, bool>}|\WP_Error
	 *         blocklane_forms_unregistered when the form block type is not registered;
	 *         blocklane_forms_ref_ceiling when the walk needs more synced patterns than it may read.
	 */
	function blocklane_pro_forms_definition( array $form_block, ?int &$pool = null ): array|\WP_Error {
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( 'blocklane/form' );
		if ( ! $type instanceof \WP_Block_Type ) {
			return new \WP_Error( 'blocklane_forms_unregistered', 'The blocklane/form block type is not registered.' );
		}
		$raw      = isset( $form_block['attrs'] ) && is_array( $form_block['attrs'] ) ? $form_block['attrs'] : array();
		$prepared = $type->prepare_attributes_for_render( $raw );
		$attrs    = array();
		foreach ( BLOCKLANE_PRO_FORMS_BOUND_ATTRS as $key ) {
			$attrs[ $key ] = blocklane_pro_forms_canonical( $prepared[ $key ] ?? null );
		}
		$decision = array();
		foreach ( array_keys( BLOCKLANE_PRO_FORMS_DECISION_MEET ) as $key ) {
			$decision[ $key ] = true === ( $prepared[ $key ] ?? null );
		}
		$ignored = array();
		$schema  = blocklane_pro_forms_derive_schema( $form_block, $ignored, $pool );
		if ( is_wp_error( $schema ) ) {
			return $schema;
		}

		return array(
			'attrs'    => $attrs,
			'schema'   => $schema,
			'ignored'  => array_values( array_unique( array_map( 'strval', $ignored ) ) ),
			'decision' => $decision,
		);
	}

	/**
	 * The binding's MAC for one definition and one nonce: 64 lowercase hex
	 * characters, an HMAC-SHA256 keyed by a key derived from wp_salt( 'auth' )
	 * for this purpose alone, over the version (2), the blog, the sanitized
	 * formId, the nonce, the bound attributes and the canonical field schema
	 * (each row ksorted, labels and authored values included: a copy that
	 * differs in anything that reaches storage or mail is a different
	 * definition). Keyed, so nothing printed says anything about the
	 * recipients or any other bound value to someone without the salt.
	 *
	 * Called only in this file (PHPStan blocklane.chokepointCall): the shell
	 * mints through blocklane_pro_forms_render_binding() and the resolver
	 * checks through blocklane_pro_forms_binding_verifies().
	 *
	 * @param string                                                                       $form_id    The sanitize_title()d formId.
	 * @param string                                                                       $nonce      The token's nonce, 32 lowercase hex characters.
	 * @param array{attrs: array<string, mixed>, schema: array<string, array<string, mixed>>} $definition A definition.
	 * @return string|\WP_Error blocklane_forms_binding_json when the message cannot be encoded.
	 */
	function blocklane_pro_forms_binding_mac( string $form_id, string $nonce, array $definition ): string|\WP_Error {
		$key     = hash_hmac( 'sha256', 'blocklane-forms-binding/2', wp_salt( 'auth' ), true );
		$message = wp_json_encode( array( 2, get_current_blog_id(), $form_id, $nonce, $definition['attrs'], blocklane_pro_forms_canonical( $definition['schema'] ) ) );
		if ( false === $message ) {
			return new \WP_Error( 'blocklane_forms_binding_json', 'The form definition cannot be encoded.' );
		}
		return hash_hmac( 'sha256', $message, $key );
	}

	/**
	 * The binding the form shell prints for the form it is rendering, minted
	 * fresh for this render: `<nonce>.<mac>`, 16 random bytes as 32 lowercase
	 * hex characters and blocklane_pro_forms_binding_mac() over them. The
	 * shell's only door to the binding.
	 *
	 * WHY A NONCE (#1986). Without one the binding was a pure function of the
	 * definition, printed in public HTML: anyone able to render a form of
	 * their own (a Contributor's draft preview) could reproduce a victim's
	 * schema, set one bound attribute to a guess, and compare the printed
	 * value with the victim page's to confirm the guess (recipients, the
	 * auto-responder). With a nonce no two renders print the same value, so
	 * there is nothing to compare; only timing remains (spec §7, ND7). It is
	 * still NOT a nonce in the WordPress sense and NOT authentication: no
	 * time or user part, so a cached page keeps a valid one, and identical
	 * copies still tie (each verifies the other's token). A token selects
	 * among the LIVE definitions the server would render; it grants nothing.
	 *
	 * WHY random_bytes() (#2090). wp_generate_password() is pluggable and
	 * passes the public `random_password` filter, so a password-policy plugin
	 * (symbols appended, a longer length) made every printed nonce one the
	 * verifier refuses, and every bound form fell silently to the unsigned
	 * MEET. The nonce's grammar is now this function's own: random_bytes(),
	 * which no plugin can filter, as lowercase hex, the exact shape
	 * blocklane_pro_forms_binding_verifies() reads. random_bytes() throws when
	 * the system has no source of randomness (an \Exception on PHP 8.1, a
	 * \Random\RandomException, its subclass, from 8.2); that is a typed
	 * refusal here, blocklane_forms_binding_nonce, which the shell already
	 * handles by printing no binding, the same as any definition error.
	 *
	 * @param string               $form_id      The sanitize_title()d formId.
	 * @param array<string, mixed> $parsed_block The form's parsed block, as the renderer holds it.
	 * @return string|\WP_Error blocklane_forms_binding_nonce when no randomness is available; a definition or MAC error.
	 */
	function blocklane_pro_forms_render_binding( string $form_id, array $parsed_block ): string|\WP_Error {
		$definition = blocklane_pro_forms_definition( $parsed_block );
		if ( is_wp_error( $definition ) ) {
			return $definition;
		}
		try {
			$nonce = bin2hex( random_bytes( 16 ) );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'blocklane_forms_binding_nonce', 'No source of randomness for the form binding: ' . $e->getMessage() );
		}
		$mac = blocklane_pro_forms_binding_mac( $form_id, $nonce, $definition );
		return is_wp_error( $mac ) ? $mac : $nonce . '.' . $mac;
	}

	/**
	 * Whether a posted token is a binding of this definition: the one
	 * verifier. A token is exactly `<32 lowercase hex>.<64 lowercase hex>`,
	 * the shape blocklane_pro_forms_render_binding() mints; anything else — the unsigned v1 value 1.0.2's development builds
	 * printed (no release carried it; decision D11), uppercase, a missing dot,
	 * a wrong length — verifies nothing and reads as no binding. The MAC is
	 * compared with hash_equals().
	 *
	 * @param string                                                                       $form_id    The sanitize_title()d formId.
	 * @param array{attrs: array<string, mixed>, schema: array<string, array<string, mixed>>} $definition A definition.
	 * @param string                                                                       $token      The posted _bl_bind.
	 */
	function blocklane_pro_forms_binding_verifies( string $form_id, array $definition, string $token ): bool {
		if ( 1 !== preg_match( '/\A([0-9a-f]{32})\.([0-9a-f]{64})\z/', $token, $parts ) ) {
			return false;
		}
		$mac = blocklane_pro_forms_binding_mac( $form_id, $parts[1], $definition );
		return ! is_wp_error( $mac ) && hash_equals( $mac, $parts[2] );
	}

	/**
	 * The post statuses a copy of a form can be served from: the SQL
	 * prefilter of both holder queries (blocklane_pro_forms_candidate_rows()
	 * and blocklane_pro_forms_source_rows()) and the re-check in
	 * blocklane_pro_forms_audience() read this one list (a memoized candidate
	 * can have left it since), so one change reaches all three. Read only by
	 * those three; a fetch anywhere else is a PHPStan error,
	 * blocklane.chokepointMember.
	 */
	define( 'BLOCKLANE_PRO_FORMS_LIVE_STATUSES', array( 'publish', 'private' ) );

	/**
	 * Candidate rows fetched per formId: more than the examination cap on
	 * purpose. Only the ORDER uses the extra rows — the SQL sorts site chrome
	 * and trusted authors first, so a flood of untrusted posts is what the
	 * limit trims — while completeness needs every fetched row examined, so a
	 * formId with more candidates than the cap is never complete (there is no
	 * separate overflow flag).
	 */
	define( 'BLOCKLANE_PRO_FORMS_CANDIDATE_LIMIT', 500 );

	/** Posts examined per request (spec §16.4.4): the bound on parse work. */
	define( 'BLOCKLANE_PRO_FORMS_EXAMINE_CAP', 50 );

	/**
	 * Copies of one formId taken from one post (security read S-R2): a post
	 * holding more is examined only this far, and the examination is then
	 * incomplete.
	 */
	define( 'BLOCKLANE_PRO_FORMS_COPIES_CAP', 10 );

	/**
	 * Distinct synced patterns one definition may reference (security read
	 * S-R2; spec §19, F06). blocklane_pro_forms_collect_fields() descends
	 * each `wp_block` ref once per walk, and a walk that needs one more than
	 * this is REFUSED: the definition is a `blocklane_forms_ref_ceiling`
	 * error, never a schema with fields missing. The shell then prints no
	 * binding and resolve() cannot examine that copy, so its submissions get
	 * an honest 409, never a fake success.
	 *
	 * Core sets no such limit to mirror: blocks/block.php renders every
	 * published, password-less pattern and halts only a cycle (an ancestor
	 * repeat), at any depth. The number is this plugin's cost bound. One
	 * resolve() lends its walks a pool of BLOCKLANE_PRO_FORMS_EXAMINE_CAP ×
	 * this many descents (5,000), the request bound the security read
	 * accepted (50 posts × 10 forms × 10 refs), so the ceiling could rise
	 * tenfold without raising it. A real form is far below it: one pattern
	 * per field would be a hundred-field form, and every shipped pattern
	 * references none. Every distinct id looked up counts, a draft or a
	 * pattern holding no field included.
	 */
	define( 'BLOCKLANE_PRO_FORMS_REF_CEILING', 100 );

	/**
	 * Whether a post's copies of a form are rendered, and to whom:
	 * `public` (anyone can be shown it), `restricted` (only a reader the
	 * post admits: private, password-protected, or a type that is not
	 * publicly viewable) or `none` (nothing renders it). The one definition
	 * of "live"; a `bound` verdict may match a public or a restricted copy,
	 * and an unsigned delivery needs a public one (decision D-R2).
	 *
	 * MIRROR CONTRACT — each row is the rule the renderer applies:
	 * - wp_block: core blocks/block.php:42 renders a synced pattern only
	 *   when it is published and has no password;
	 * - wp_template, wp_template_part: core blocks/template-part.php:25-36
	 *   renders a published part of the ACTIVE theme (the wp_theme term),
	 *   and a template is resolved the same way;
	 * - BLOCKLANE_PRO_POPUPS_CPT: the popups runtime renders a published
	 *   popup only (inc/popups/runtime.php, the footer render and the
	 *   bound-opener pass);
	 * - any other type: what a visitor can be shown — publicly viewable and
	 *   without a password is public; private, password-protected, or a type
	 *   that is not publicly viewable is restricted (a type no longer
	 *   registered included: is_post_publicly_viewable() answers false).
	 * A status outside BLOCKLANE_PRO_FORMS_LIVE_STATUSES is never served.
	 *
	 * @param \WP_Post $post A post.
	 * @return string 'public', 'restricted' or 'none'.
	 */
	function blocklane_pro_forms_audience( \WP_Post $post ): string {
		if ( ! in_array( $post->post_status, BLOCKLANE_PRO_FORMS_LIVE_STATUSES, true ) ) {
			return 'none';
		}
		$published = 'publish' === $post->post_status;
		switch ( $post->post_type ) {
			case 'wp_block':
				return $published && '' === (string) $post->post_password ? 'public' : 'none';
			case 'wp_template':
			case 'wp_template_part':
				return $published && has_term( get_stylesheet(), 'wp_theme', $post ) ? 'public' : 'none';
		}
		if ( defined( 'BLOCKLANE_PRO_POPUPS_CPT' ) && BLOCKLANE_PRO_POPUPS_CPT === $post->post_type ) {
			return $published ? 'public' : 'none';
		}
		return is_post_publicly_viewable( $post ) && '' === (string) $post->post_password ? 'public' : 'restricted';
	}

	/**
	 * The site-chrome post types: those whose edit capability is
	 * edit_theme_options or manage_options (templates, template parts,
	 * popups…). Authors cannot write them, so the candidate SQL orders them
	 * before any post an insider can publish. The one derivation, read by
	 * the candidate SQL, the chrome-refs scan and the memo forgetter.
	 *
	 * @return list<string>
	 */
	function blocklane_pro_forms_chrome_types(): array {
		$chrome = array();
		foreach ( get_post_types( array(), 'objects' ) as $type ) {
			if ( in_array( $type->cap->edit_posts ?? '', array( 'edit_theme_options', 'manage_options' ), true ) ) {
				$chrome[] = (string) $type->name;
			}
		}
		return $chrome;
	}

	/**
	 * The synced-pattern ids a post's content references, read from the
	 * stored markup without parsing it (core/block's `ref`).
	 *
	 * @param string $content Post content.
	 * @return list<int>
	 */
	function blocklane_pro_forms_refs_named( string $content ): array {
		$refs = array();
		if ( false === strpos( $content, '<!-- wp:block ' ) || ! preg_match_all( '/<!-- wp:block (\{.*?\}) \/?-->/', $content, $found ) ) {
			return $refs;
		}
		foreach ( $found[1] as $json ) {
			$attrs = json_decode( $json, true );
			if ( is_array( $attrs ) && isset( $attrs['ref'] ) && is_numeric( $attrs['ref'] ) && (int) $attrs['ref'] > 0 ) {
				$refs[] = (int) $attrs['ref'];
			}
		}
		return array_values( array_unique( $refs ) );
	}

	/**
	 * The synced patterns some stored content places, and the patterns those
	 * place, level by level (spec §19: F03 for chrome, F01 for the visitor's
	 * page). Refs are read from the markup (blocklane_pro_forms_refs_named(),
	 * never parsed) and followed by the schema walk's rules: a pattern is
	 * descended only when core would render it (published, no password),
	 * each id once (the result is the visited set), and at most $max ids in
	 * all, at one query by primary key per level. Ordering only: a placement
	 * past $max is not refused, its copies keep their author's tier.
	 *
	 * @param list<string> $contents The placing posts' stored content.
	 * @param int          $max      The most pattern ids to return.
	 * @return list<int> Pattern ids, nearest level first.
	 */
	function blocklane_pro_forms_placed( array $contents, int $max ): array {
		global $wpdb;
		$refs = array();
		while ( array() !== $contents ) {
			$level = array();
			foreach ( $contents as $content ) {
				$level = array_merge( $level, blocklane_pro_forms_refs_named( (string) $content ) );
			}
			$level = array_slice( array_values( array_diff( array_unique( $level ), $refs ) ), 0, max( 0, $max - count( $refs ) ) );
			if ( array() === $level ) {
				break;
			}
			$refs     = array_merge( $refs, $level );
			$contents = (array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- by primary key, at most $max ids in all; the callers memoize or bound it.
				$wpdb->prepare(
					"SELECT post_content FROM {$wpdb->posts} WHERE ID IN ( " . implode( ', ', array_fill( 0, count( $level ), '%d' ) ) . " ) AND post_type = 'wp_block' AND post_status = 'publish' AND post_password = '' AND post_content LIKE %s",
					array_merge( $level, array( '%' . $wpdb->esc_like( '<!-- wp:block ' ) . '%' ) )
				)
			);
		}
		return $refs;
	}

	/**
	 * The synced patterns site chrome places (security read S-R4; spec §19,
	 * F03): blocklane_pro_forms_placed() over every published chrome post
	 * that references one, at most BLOCKLANE_PRO_FORMS_REF_CEILING, so a form
	 * in a pattern nested inside a footer pattern is examined with the
	 * chrome that places it. Memoized in the object cache for a minute (one
	 * key, never keyed by a caller); saving or deleting a chrome post or a
	 * synced pattern forgets it. A memo miss costs the chrome scan and one
	 * query by primary key per nesting level.
	 *
	 * @return list<int>
	 */
	function blocklane_pro_forms_chrome_refs(): array {
		$refs = wp_cache_get( 'placed', 'blocklane_pro_forms_chrome_refs' );
		if ( is_array( $refs ) ) {
			return $refs;
		}
		global $wpdb;
		$chrome   = blocklane_pro_forms_chrome_types();
		$chrome[] = ''; // Never an empty IN list.
		$contents = (array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- memoized in the object cache below.
			$wpdb->prepare(
				"SELECT post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s AND post_type IN ( " . implode( ', ', array_fill( 0, count( $chrome ), '%s' ) ) . ' )',
				array_merge( array( '%' . $wpdb->esc_like( '<!-- wp:block ' ) . '%' ), $chrome )
			)
		);
		$refs = blocklane_pro_forms_placed( array_map( 'strval', $contents ), BLOCKLANE_PRO_FORMS_REF_CEILING );
		wp_cache_set( 'placed', $refs, 'blocklane_pro_forms_chrome_refs', MINUTE_IN_SECONDS );
		return $refs;
	}

	/**
	 * Users in the trusted tier at most (spec §19, F04): the bound on the
	 * candidate SQL's `post_author IN ( … )` list and on each get_users()
	 * that builds it. A site with more such users ranks the rest's copies
	 * with the untrusted tier: an order, never a verdict (D-R6).
	 */
	define( 'BLOCKLANE_PRO_FORMS_TRUSTED_CAP', 1000 );

	/**
	 * The trusted tier's authors (security read S-R3): users holding an
	 * edit-others capability of some post type outside chrome, as get_users()
	 * reads role capabilities, at most BLOCKLANE_PRO_FORMS_TRUSTED_CAP of
	 * them, lowest ids first. A memo miss runs one get_users() per distinct
	 * edit-others capability (posts and pages, plus any custom type with its
	 * own), each bounded by the cap.
	 *
	 * Memoized in the object cache for a minute under one fixed key, never
	 * keyed by a caller, like blocklane_pro_forms_chrome_refs(); a role
	 * change forgets it (blocklane_pro_forms_forget_trusted()). Without a
	 * persistent object cache the memo lives for one request, which NP17
	 * names.
	 *
	 * @return list<int>
	 */
	function blocklane_pro_forms_trusted_authors(): array {
		$trusted = wp_cache_get( 'trusted', 'blocklane_pro_forms_trusted' );
		if ( is_array( $trusted ) ) {
			return $trusted;
		}
		$chrome = blocklane_pro_forms_chrome_types();
		$caps   = array();
		foreach ( get_post_types( array(), 'objects' ) as $type ) {
			$cap = (string) ( $type->cap->edit_others_posts ?? '' );
			if ( '' !== $cap && ! in_array( (string) $type->name, $chrome, true ) ) {
				$caps[ $cap ] = true;
			}
		}
		$trusted = array();
		foreach ( array_keys( $caps ) as $cap ) {
			$trusted = array_merge(
				$trusted,
				array_map(
					'intval',
					(array) get_users(
						array(
							'capability' => $cap,
							'fields'     => 'ID',
							'orderby'    => 'ID',
							'number'     => BLOCKLANE_PRO_FORMS_TRUSTED_CAP,
						)
					)
				)
			);
		}
		$trusted = array_values( array_unique( $trusted ) );
		sort( $trusted, SORT_NUMERIC );
		$trusted = array_slice( $trusted, 0, BLOCKLANE_PRO_FORMS_TRUSTED_CAP );
		wp_cache_set( 'trusted', $trusted, 'blocklane_pro_forms_trusted', MINUTE_IN_SECONDS );
		return $trusted;
	}

	/**
	 * Forget the trusted tier's memo when a user's roles change: a role
	 * added or removed, or a user deleted. WP_User::set_role() fires
	 * add_user_role and remove_user_role for every role it changes, and
	 * wp_insert_user() always calls set_role(), so those two hooks cover
	 * set_user_role and user_register as well; hooking either would add a
	 * door no change reaches alone. Deletion is deleted_user, after the row
	 * and its roles are gone, so nothing can re-warm the memo with them in
	 * between (delete_user fires before). A capability granted to a role or
	 * a user directly is seen when the minute's memo lapses; the tier is an
	 * order, never a verdict.
	 */
	function blocklane_pro_forms_forget_trusted(): void {
		wp_cache_delete( 'trusted', 'blocklane_pro_forms_trusted' );
	}
	add_action( 'add_user_role', 'blocklane_pro_forms_forget_trusted', 10, 0 );
	add_action( 'remove_user_role', 'blocklane_pro_forms_forget_trusted', 10, 0 );
	add_action( 'deleted_user', 'blocklane_pro_forms_forget_trusted', 10, 0 );

	/**
	 * The two LIKE patterns a post must match to hold a copy of a formId:
	 * the form block's own comment (`<!-- wp:blocklane/form ` with its
	 * trailing space, so form-input and its siblings never match; the needle
	 * blocklane_pro_forms_saved() uses) and the formId attribute. Read by
	 * both holder queries, the candidate scan and the source fetch, so the
	 * two cannot disagree on what a holder is.
	 *
	 * @param string $form_id A sanitize_title()d formId.
	 * @return list<string>
	 */
	function blocklane_pro_forms_holder_likes( string $form_id ): array {
		global $wpdb;
		return array( '%' . $wpdb->esc_like( '<!-- wp:blocklane/form ' ) . '%', '%' . $wpdb->esc_like( '"formId":"' . $form_id . '"' ) . '%' );
	}

	/**
	 * The posts that may hold a copy of a formId, as rows: id and author.
	 * One content scan with the two needles
	 * (blocklane_pro_forms_holder_likes()) over the live statuses, at most
	 * BLOCKLANE_PRO_FORMS_CANDIDATE_LIMIT rows, in three tiers, each by ID:
	 * 0. site chrome (blocklane_pro_forms_chrome_types()) and the synced
	 *    patterns chrome places (blocklane_pro_forms_chrome_refs(), S-R4);
	 * 1. posts by a trusted author (blocklane_pro_forms_trusted_authors();
	 *    security read S-R3: the trust tier is in the SQL, so the limit
	 *    trims only the untrusted tier);
	 * 2. the rest.
	 * The ORDER decides only which copies fit the limit and the examination
	 * budget, never whose settings route a submission: capability decides
	 * budget, never routing (decision D-R6; the rejected capability router
	 * stays rejected, M-WB7).
	 *
	 * A row is a CANDIDATE, never a verdict: every one is re-read and
	 * re-checked (blocklane_pro_forms_audience(), the needle, a real form)
	 * when it is examined. Memoized in the object cache for a minute
	 * (security read S2: the public route must not repeat a full-content scan
	 * per request), only when not empty (a miss is the miss cache's
	 * business), and forgotten by blocklane_pro_forms_saved() for every
	 * formId a saved or deleted post carries. The group is not the 1.0.2
	 * holder-id group, so the older list<int> shape is never read back.
	 *
	 * @param string $form_id A sanitize_title()d formId.
	 * @return list<array{id: int, author: int}>
	 */
	function blocklane_pro_forms_candidate_rows( string $form_id ): array {
		$rows = wp_cache_get( $form_id, 'blocklane_pro_forms_candidates' );
		if ( is_array( $rows ) ) {
			return $rows;
		}
		global $wpdb;
		$chrome    = blocklane_pro_forms_chrome_types();
		$placed    = blocklane_pro_forms_chrome_refs();
		$trusted   = blocklane_pro_forms_trusted_authors();
		$chrome[]  = ''; // Never an empty IN list.
		$placed[]  = 0;
		$trusted[] = -1;
		$found     = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a content search; the rows are memoized in the object cache below.
			$wpdb->prepare(
				"SELECT ID, post_author FROM {$wpdb->posts}
				WHERE post_status IN ( " . implode( ', ', array_fill( 0, count( BLOCKLANE_PRO_FORMS_LIVE_STATUSES ), '%s' ) ) . ' )
				AND post_content LIKE %s
				AND post_content LIKE %s
				ORDER BY CASE WHEN post_type IN ( ' . implode( ', ', array_fill( 0, count( $chrome ), '%s' ) ) . ' ) OR ID IN ( ' . implode( ', ', array_fill( 0, count( $placed ), '%d' ) ) . ' ) THEN 0 WHEN post_author IN ( ' . implode( ', ', array_fill( 0, count( $trusted ), '%d' ) ) . ' ) THEN 1 ELSE 2 END, ID ASC
				LIMIT %d',
				array_merge(
					BLOCKLANE_PRO_FORMS_LIVE_STATUSES,
					blocklane_pro_forms_holder_likes( $form_id ),
					$chrome,
					$placed,
					$trusted,
					array( BLOCKLANE_PRO_FORMS_CANDIDATE_LIMIT )
				)
			),
			ARRAY_A
		);
		$rows = array();
		foreach ( $found as $row ) {
			$rows[] = array(
				'id'     => (int) $row['ID'],
				'author' => (int) $row['post_author'],
			);
		}
		if ( array() !== $rows ) {
			wp_cache_set( $form_id, $rows, 'blocklane_pro_forms_candidates', MINUTE_IN_SECONDS );
		}
		return $rows;
	}

	/**
	 * The post a submission's `_bl_source` may name (#2030): the post, when
	 * it exists, is_post_publicly_viewable() holds and it has no password —
	 * a post an anonymous visitor can be shown as the page (decision D9 a);
	 * else null. The caller chooses the id and nothing else may be read from
	 * it first: both readers of `_bl_source` take this door's answer, the
	 * examination order (blocklane_pro_forms_source_rows()) and the lead's
	 * provenance in the submission handler, so a draft, private, trashed,
	 * password-protected or internal post's stored content is never walked
	 * and never named, and one predicate answers for both.
	 *
	 * @param int $source_id The posted _bl_source (0 when absent).
	 * @return \WP_Post|null
	 */
	function blocklane_pro_forms_source_post( int $source_id ): ?\WP_Post {
		$post = $source_id > 0 ? \WP_Post::get_instance( $source_id ) : false;
		if ( ! $post instanceof \WP_Post || ! is_post_publicly_viewable( $post ) || '' !== (string) $post->post_password ) {
			return null;
		}
		return $post;
	}

	/**
	 * The posts the request itself names, by primary key (spec §19, F01):
	 * the source post blocklane_pro_forms_source_post() admitted for the
	 * posted `_bl_source` (#2030: none for a post nobody may be shown, so its
	 * content is never walked) and at most BLOCKLANE_PRO_FORMS_EXAMINE_CAP - 1
	 * of the synced patterns it places, nested ones included
	 * (blocklane_pro_forms_placed()), each kept only when it passes the
	 * candidate SQL's own prefilter — a live status and both needles
	 * (blocklane_pro_forms_holder_likes()). The source first, then its
	 * patterns, nearest level first.
	 *
	 * The candidate SQL cannot know them: its rows are memoized per formId,
	 * so they are ranked by author and trimmed by its LIMIT before any
	 * request is seen, and an on-page copy by an untrusted author could be
	 * trimmed by 500 older untrusted copies. Fetched here, outside the
	 * limit, the visitor's own page is always examined first, so a signed
	 * visitor is never refused for copies someone else planted. Ordering
	 * only (G3): every id is re-read and re-checked when it is examined.
	 *
	 * One query by primary key per nesting level the page places, and one
	 * for the prefilter, only for a formId that has candidates (an unknown id
	 * never gets here) and a source the door admitted.
	 *
	 * @param string        $form_id A sanitize_title()d formId.
	 * @param \WP_Post|null $source  blocklane_pro_forms_source_post()'s answer.
	 * @return list<int> Post ids.
	 */
	function blocklane_pro_forms_source_rows( string $form_id, ?\WP_Post $source ): array {
		if ( null === $source ) {
			return array();
		}
		global $wpdb;
		$named = array_merge( array( (int) $source->ID ), blocklane_pro_forms_placed( array( (string) $source->post_content ), BLOCKLANE_PRO_FORMS_EXAMINE_CAP - 1 ) );
		$found = array_map(
			'intval',
			(array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- by primary key, at most fifty ids; never memoized, it is the request's.
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					WHERE ID IN ( " . implode( ', ', array_fill( 0, count( $named ), '%d' ) ) . ' )
					AND post_status IN ( ' . implode( ', ', array_fill( 0, count( BLOCKLANE_PRO_FORMS_LIVE_STATUSES ), '%s' ) ) . ' )
					AND post_content LIKE %s
					AND post_content LIKE %s',
					array_merge( $named, BLOCKLANE_PRO_FORMS_LIVE_STATUSES, blocklane_pro_forms_holder_likes( $form_id ) )
				)
			)
		);
		return array_values( array_intersect( $named, $found ) );
	}

	/**
	 * The order the candidates are examined in: the request's own posts
	 * (blocklane_pro_forms_source_rows(), whether or not the candidate SQL
	 * kept them) first, then every candidate row in the SQL's order (chrome
	 * and the patterns chrome places, trusted authors, the rest), each id
	 * once.
	 *
	 * THE ORDER DECIDES ONLY TWO THINGS (guarantee G3): which copies fit the
	 * examination budget, and which of several IDENTICAL copies a `bound`
	 * verdict names. Nothing a caller posts can make it choose whose
	 * settings route a submission.
	 *
	 * @param list<array{id: int, author: int}> $rows  blocklane_pro_forms_candidate_rows().
	 * @param list<int>                         $first blocklane_pro_forms_source_rows().
	 * @return list<int> Post ids.
	 */
	function blocklane_pro_forms_examination_order( array $rows, array $first ): array {
		$ids = array_column( $rows, 'id' );
		return array_values( array_unique( array_merge( $first, $ids ) ) );
	}

	/**
	 * The copies of a formId one post holds: one item per form, in tree
	 * order, each the post, its audience and the form's definition — no
	 * parsed block and no raw attributes, so nothing downstream can act on
	 * bytes the binding did not sign (F03). Nothing when the post is not
	 * rendered (audience `none`), does not carry the formId, or holds no real
	 * form with it. At most BLOCKLANE_PRO_FORMS_COPIES_CAP copies; a post
	 * holding more says so (`complete` false), and so does a copy whose
	 * definition cannot be computed (its walk refused past the ref ceiling or
	 * the pool, F06): a copy nobody examined is never left out of a verdict
	 * as if it were absent.
	 *
	 * @param \WP_Post $post    A candidate.
	 * @param string   $form_id A sanitize_title()d formId.
	 * @param int|null $pool    The examination's synced-pattern descents left (resolve()'s pool), spent here; null lends
	 *                          each definition the ceiling.
	 * @return array{copies: list<array{post_id: int, audience: string, definition: array{attrs: array<string, mixed>, schema: array<string, array<string, mixed>>, ignored: list<string>, decision: array<string, bool>}}>, complete: bool}
	 */
	function blocklane_pro_forms_copies_in( \WP_Post $post, string $form_id, ?int &$pool = null ): array {
		$audience = blocklane_pro_forms_audience( $post );
		$content  = (string) $post->post_content;
		if ( 'none' === $audience || false === stripos( $content, '"formId":"' . $form_id . '"' ) ) {
			return array(
				'copies'   => array(),
				'complete' => true,
			);
		}
		$forms    = blocklane_pro_forms_forms_in( parse_blocks( $content ), $form_id );
		$copies   = array();
		$complete = count( $forms ) <= BLOCKLANE_PRO_FORMS_COPIES_CAP;
		foreach ( array_slice( $forms, 0, BLOCKLANE_PRO_FORMS_COPIES_CAP ) as $form ) {
			$definition = blocklane_pro_forms_definition( $form, $pool );
			if ( is_wp_error( $definition ) ) {
				$complete = false;
				continue;
			}
			$copies[] = array(
				'post_id'    => (int) $post->ID,
				'audience'   => $audience,
				'definition' => $definition,
			);
		}
		return array(
			'copies'   => $copies,
			'complete' => $complete,
		);
	}

	/**
	 * Every blocklane/form in a parsed block tree whose sanitized formId is
	 * the given one, in tree order. A quoted formId (a code block, a
	 * paragraph) is not a form and is never returned.
	 *
	 * @param array<int|string, mixed> $blocks  Parsed blocks.
	 * @param string                   $form_id A sanitize_title()d formId.
	 * @return list<array<string, mixed>>
	 */
	function blocklane_pro_forms_forms_in( array $blocks, string $form_id ): array {
		$found = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( 'blocklane/form' === ( $block['blockName'] ?? '' ) && isset( $block['attrs']['formId'] ) && is_string( $block['attrs']['formId'] ) && '' !== $block['attrs']['formId'] && sanitize_title( $block['attrs']['formId'] ) === $form_id ) {
				$found[] = $block;
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$found = array_merge( $found, blocklane_pro_forms_forms_in( $block['innerBlocks'], $form_id ) );
			}
		}
		return $found;
	}

	/**
	 * The names a submission posts: every body key except module meta (the
	 * reserved `_bl_` prefix, skipped by PREFIX so a newer view.js's meta is
	 * never trapped), the honeypot and the Turnstile token, plus every file
	 * key.
	 *
	 * @param array<int|string, mixed> $params Body params.
	 * @param array<int|string, mixed> $files  File params.
	 * @return list<string>
	 */
	function blocklane_pro_forms_posted_names( array $params, array $files ): array {
		$meta  = array( blocklane_pro_forms_honeypot_name(), 'cf-turnstile-response' );
		$names = array();
		foreach ( array_keys( $params ) as $key ) {
			$key = (string) $key;
			if ( 0 === strpos( $key, '_bl_' ) || in_array( $key, $meta, true ) ) {
				continue;
			}
			$names[] = $key;
		}
		foreach ( array_keys( $files ) as $key ) {
			$names[] = (string) $key;
		}
		return array_values( array_unique( $names ) );
	}

	/**
	 * The posted names a definition does not accept: the one tamper test.
	 * A name in the definition's schema is a field; a name in its `ignored`
	 * list is a field of a suite block this edition does not register (a
	 * page cached under the other edition still posts it), dropped rather
	 * than trapped. Anything else is unknown.
	 *
	 * @param array{schema: array<string, mixed>, ignored: list<string>} $definition A definition (or a resolution).
	 * @param list<string>                                                $posted     blocklane_pro_forms_posted_names().
	 * @return list<string>
	 */
	function blocklane_pro_forms_unknown_names( array $definition, array $posted ): array {
		$unknown = array();
		foreach ( $posted as $name ) {
			if ( ! array_key_exists( $name, $definition['schema'] ) && ! in_array( $name, $definition['ignored'], true ) ) {
				$unknown[] = $name;
			}
		}
		return $unknown;
	}

	/**
	 * A submission that trips a bot trap: the honeypot filled, or the
	 * time-trap token missing (no JS, or a submit faster than the arm delay).
	 * The honeypot compares against the empty string rather than !empty(): a
	 * bot that stuffs '0' into every input must not read as human. A
	 * honeypot posted as anything but a scalar (`hp[]=x`) is filled: a
	 * browser never posts the text input that way (F02).
	 *
	 * @param array<int|string, mixed> $params Body params.
	 */
	function blocklane_pro_forms_is_bot( array $params ): bool {
		$honeypot = blocklane_pro_forms_honeypot_name();
		$filled   = isset( $params[ $honeypot ] ) && ( ! is_scalar( $params[ $honeypot ] ) || '' !== trim( (string) $params[ $honeypot ] ) );
		return $filled || ! isset( $params['_bl_time'] ) || '1' !== $params['_bl_time'];
	}

	/**
	 * The one verdict builder: what a submission decided by $copies may
	 * route, gate and validate by. Every value comes from the copies'
	 * definitions — the prepared bytes the binding signs, never raw block
	 * attributes (F03) — and each attribute by its BLOCKLANE_PRO_FORMS_MEET
	 * rule; a null (inherit) is never a value. `attrs` carries no gate or
	 * unique key: those travel as `gates` (each a requireLogin/schedule
	 * tuple the handler checks) and `unique` (the field names to hold
	 * unique). Unattested, the schema is the first public copy's (every copy
	 * shares it canonically by then) with no hidden field as Reply-To, and
	 * `source_id` is 0: no copy is THE copy (the viewing page stays in the
	 * payload's origin_id). `test` is the BLOCKLANE_PRO_FORMS_DECISION_MEET
	 * meet of the copies' `decision` (testMode, unanimous: true only when
	 * every copy weighed is in test mode; spec forms.md v4 TM-A, F3).
	 *
	 * @param list<array{post_id: int, audience: string, definition: array{attrs: array<string, mixed>, schema: array<string, array<string, mixed>>, ignored: list<string>, decision: array<string, bool>}}> $copies   One copy (attested) or every live copy (unattested), public first among equals.
	 * @param bool                                                                                                                                                                                      $attested Whether a binding verified $copies[0].
	 * @return array{attrs: array<string, mixed>, gates: list<array<string, mixed>>, unique: list<string>, schema: array<string, array<string, mixed>>, ignored: list<string>, source_id: int, test: bool}|\WP_Error
	 *         blocklane_forms_stale when unattested copies disagree on an `agree` attribute.
	 */
	function blocklane_pro_forms_verdict( array $copies, bool $attested ): array|\WP_Error {
		$copies = $attested ? array( $copies[0] ) : $copies;
		$attrs  = array();
		$unique = array();
		$gates  = array();
		foreach ( $copies as $copy ) {
			$gate = array();
			foreach ( BLOCKLANE_PRO_FORMS_MEET as $key => $rule ) {
				if ( 'gate' === $rule ) {
					$gate[ $key ] = $copy['definition']['attrs'][ $key ] ?? null;
				}
			}
			$gates[ (string) wp_json_encode( $gate ) ] = $gate;
		}
		foreach ( BLOCKLANE_PRO_FORMS_MEET as $key => $rule ) {
			$values    = array_map( static fn( array $copy ): mixed => $copy['definition']['attrs'][ $key ] ?? null, $copies );
			$same      = 1 === count( array_unique( array_map( static fn( mixed $value ): string => (string) wp_json_encode( $value ), $values ) ) );
			$all_false = array() === array_filter( $values, static fn( mixed $value ): bool => false !== $value );
			if ( 'agree' === $rule ) {
				if ( ! $same ) {
					return new \WP_Error( 'blocklane_forms_stale', 'The live copies of this form disagree on ' . $key . '.' );
				}
				if ( is_string( $values[0] ) && '' !== $values[0] ) {
					$unique[] = $values[0];
				}
				continue;
			}
			if ( in_array( $rule, array( 'identity', 'gate' ), true ) ) {
				continue;
			}
			if ( $attested ) {
				if ( null !== $values[0] ) {
					$attrs[ $key ] = $values[0];
				}
				continue;
			}
			switch ( $rule ) {
				case 'unanimous':
					if ( $same && null !== $values[0] ) {
						$attrs[ $key ] = $values[0];
					}
					break;
				case 'opt_out':
					// A copy that inherits (null) resolves to the site default,
					// which resolve_config() fills when nothing is set here.
					if ( in_array( true, $values, true ) ) {
						$attrs[ $key ] = true;
					} elseif ( $all_false ) {
						$attrs[ $key ] = false;
					}
					break;
				case 'narrow':
					if ( $all_false ) {
						$attrs[ $key ] = false;
					}
					break;
				case 'off':
					$attrs[ $key ] = array( 'enabled' => false );
					break;
			}
		}
		$decision = array();
		foreach ( array_keys( BLOCKLANE_PRO_FORMS_DECISION_MEET ) as $key ) {
			// Every decision rule is 'unanimous' (WB14 pins the vocabulary):
			// true only when every copy weighed says so, strictly.
			$decision[ $key ] = array() === array_filter( $copies, static fn( array $copy ): bool => true !== ( $copy['definition']['decision'][ $key ] ?? null ) );
		}
		$first  = $copies[0];
		$schema = $first['definition']['schema'];
		if ( ! $attested ) {
			foreach ( $copies as $copy ) {
				if ( 'public' === $copy['audience'] ) {
					$first = $copy;
					break;
				}
			}
			$schema = $first['definition']['schema'];
			foreach ( $schema as $name => $row ) {
				if ( 'hidden' === ( $row['type'] ?? '' ) ) {
					$schema[ $name ]['isReplyTo'] = false;
				}
			}
		}
		return array(
			'attrs'     => $attrs,
			'gates'     => array_values( $gates ),
			'unique'    => $unique,
			'schema'    => $schema,
			'ignored'   => $first['definition']['ignored'],
			'source_id' => $attested ? (int) $first['post_id'] : 0,
			'test'      => $decision['testMode'],
		);
	}

	/**
	 * The one resolution door: which settings decide this submission?
	 *
	 * THE GUARANTEE (spec 2026-10-06-forms-form-ownership §16.4.2).
	 * - G1 (identity). A submission is routed by a copy's own settings only
	 *   when its `_bl_bind` verifies against a copy that renders, to anyone —
	 *   a password-protected or private holder's included (`bound`;
	 *   blocklane_pro_forms_binding_verifies(), the one verifier). The binding
	 *   is the only door that chooses a copy: two copies tie only when their
	 *   definitions are identical, and identical definitions route
	 *   identically.
	 * - G2 (unsigned). Any other submission is decided by the MEET of EVERY
	 *   live copy (BLOCKLANE_PRO_FORMS_MEET, blocklane_pro_forms_verdict()):
	 *   routing from the site defaults; every gate any copy sets; a unique
	 *   field only when every copy names it; the storage and admin-mail
	 *   opt-outs only when every copy opts out (storage never past the site's
	 *   own opt-out); no auto-response; one schema every copy shares, with no
	 *   Reply-To from a hidden field (`shared`). It is refused with an honest
	 *   `stale` (the handler's 409, after the bot traps) when the copies
	 *   disagree on the schema or on the unique field, when no copy is
	 *   public, when the posted names are not the schema's, or when not every
	 *   live copy can be examined: past the examination budget or a post's
	 *   copy cap, or a copy whose schema walk needs more synced patterns than
	 *   BLOCKLANE_PRO_FORMS_REF_CEILING or the request's pool allows (F06).
	 * - G3 (order never decides). The examination order and `_bl_source`
	 *   decide only which copies fit the budget and which of several
	 *   identical copies a `bound` verdict names. An incomplete examination
	 *   fails closed; no verdict depends on a prior request.
	 * - G4 (no state). No write query, no option read by formId, no cached
	 *   verdict, no durable state. The only caches are in the object cache:
	 *   the miss (written only when no candidate exists at all), the
	 *   candidate rows (re-checked on every call, forgotten on save and
	 *   delete), and the two site-wide sets the candidate SQL orders by, each
	 *   under one fixed key (the patterns chrome places, the trusted authors).
	 * - G5 (no content writes). No forms code rewrites a formId or any post
	 *   content. (The Test-mode strip, blocklane_pro_forms_strip_test_mode(),
	 *   removes an attribute from content a user is saving, before it is
	 *   written; it never rewrites stored content.)
	 * - G6 (test mode, spec forms.md v4 TM-A). The binding does not sign
	 *   `decision`, so copies identical but for testMode verify the same
	 *   binding. A verified copy in test mode decides a test only when the
	 *   examination completes without another verifying copy that is live;
	 *   a live one found is the verdict, an incomplete examination stores.
	 * Outside the guarantee, by name (spec §8, §16.8): NP1 (own routing for
	 * unsigned submissions), NP6 (a form with no live copy left
	 * fake-succeeds), NP10 (a caller replaying a live copy's binding), NP11
	 * (previews), NP13–NP25 (the 409 cases, the examination budget, the only
	 * remaining copy, render-time rewriters, the cost, unused parts of the
	 * active theme, the binding as a TIMING oracle — narrowed by #1986: a
	 * token is minted fresh on every render, so no two printed values can be
	 * compared, and what remains is the examination's time, rate-limited per
	 * client (ND7) — ids sanitize_title()
	 * rewrites, `source_id` 0, the payload, more than fifty legitimate
	 * copies, free-only rows, logged-in unsigned visitors).
	 *
	 * Context-free (no current user, no request, no is_admin) and read-only.
	 * The binding is not authentication: an anonymous caller who replays one
	 * chooses which LIVE definition validates their own submission, which is
	 * what submitting on that page does.
	 *
	 * @param string       $form_id   A sanitize_title()d formId.
	 * @param string       $binding   The posted _bl_bind ('' when absent).
	 * @param int          $source_id The posted _bl_source (0 when absent): the examination's first post, never a verdict.
	 * @param list<string> $posted    blocklane_pro_forms_posted_names().
	 * @return array{attrs: array<string, mixed>, gates: list<array<string, mixed>>, unique: list<string>, schema: array<string, array<string, mixed>>, ignored: list<string>, source_id: int, test: bool, resolution: string, copies: int}|\WP_Error
	 *         blocklane_forms_unknown (no live copy), blocklane_forms_stale
	 *         (copies the submission cannot be decided by), blocklane_forms_
	 *         unavailable (the form block is not registered).
	 */
	function blocklane_pro_forms_resolve( string $form_id, string $binding, int $source_id, array $posted ): array|\WP_Error {
		// A miss remembered in the object cache: an unknown formId (spam
		// against the public route) must not run a content scan on every hit.
		if ( '' === $form_id || false !== wp_cache_get( $form_id, 'blocklane_pro_forms_loc_miss' ) ) {
			return new \WP_Error( 'blocklane_forms_unknown', 'No live form carries this formId.' );
		}
		// Without the block type no definition can be computed: an honest
		// refusal, never a fake success (sweep-1005).
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( 'blocklane/form' ) ) {
			return new \WP_Error( 'blocklane_forms_unavailable', 'The blocklane/form block type is not registered.' );
		}
		$rows = blocklane_pro_forms_candidate_rows( $form_id );
		if ( array() === $rows ) {
			// Remember the miss briefly — in the object cache only, so a formId
			// that matches nothing writes nothing durable.
			wp_cache_set( $form_id, 1, 'blocklane_pro_forms_loc_miss', MINUTE_IN_SECONDS );
			return new \WP_Error( 'blocklane_forms_unknown', 'No live form carries this formId.' );
		}
		$seen     = array();
		$complete = true;
		$examined = 0;
		$pool     = BLOCKLANE_PRO_FORMS_EXAMINE_CAP * BLOCKLANE_PRO_FORMS_REF_CEILING;
		$bound    = null; // A verifying copy in test mode, held while a live twin is sought (G6).
		foreach ( blocklane_pro_forms_examination_order( $rows, blocklane_pro_forms_source_rows( $form_id, blocklane_pro_forms_source_post( $source_id ) ) ) as $id ) {
			if ( BLOCKLANE_PRO_FORMS_EXAMINE_CAP === $examined ) {
				$complete = false;
				break;
			}
			++$examined;
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post ) {
				continue; // Gone since the rows were memoized; it still counted.
			}
			$found    = blocklane_pro_forms_copies_in( $post, $form_id, $pool );
			$complete = $complete && $found['complete'];
			foreach ( $found['copies'] as $copy ) {
				if ( '' !== $binding && blocklane_pro_forms_binding_verifies( $form_id, $copy['definition'], $binding ) ) {
					$verdict = blocklane_pro_forms_verdict( array( $copy ), true );
					if ( is_wp_error( $verdict ) || ! $verdict['test'] ) {
						return is_wp_error( $verdict ) ? $verdict : $verdict + array(
							'resolution' => 'bound',
							'copies'     => 1,
						);
					}
					$bound = $bound ?? $verdict;
					continue;
				}
				$seen[] = $copy;
			}
		}
		if ( null !== $bound ) {
			// Every verifying copy weighed was in test mode. Only a complete
			// examination proves no live twin exists; otherwise it stores.
			return array( 'test' => $complete && $bound['test'] ) + $bound + array(
				'resolution' => 'bound',
				'copies'     => 1,
			);
		}
		if ( ! $complete ) {
			return new \WP_Error( 'blocklane_forms_stale', 'Not every live copy of this form could be examined.' );
		}
		if ( array() === $seen ) {
			return new \WP_Error( 'blocklane_forms_unknown', 'No live form carries this formId.' );
		}
		$public = array_values( array_filter( $seen, static fn( array $copy ): bool => 'public' === $copy['audience'] ) );
		if ( array() === $public ) {
			return new \WP_Error( 'blocklane_forms_stale', 'No public copy of this form exists.' );
		}
		$shape = static function ( array $copy ): string {
			$ignored = $copy['definition']['ignored'];
			sort( $ignored, SORT_STRING );
			return (string) wp_json_encode( array( blocklane_pro_forms_canonical( $copy['definition']['schema'] ), $ignored ) );
		};
		foreach ( $seen as $copy ) {
			if ( $shape( $copy ) !== $shape( $public[0] ) ) {
				return new \WP_Error( 'blocklane_forms_stale', 'The live copies of this form have different fields.' );
			}
		}
		if ( array() !== blocklane_pro_forms_unknown_names( $public[0]['definition'], $posted ) ) {
			return new \WP_Error( 'blocklane_forms_stale', 'No live copy of this form accepts the posted names.' );
		}
		$verdict = blocklane_pro_forms_verdict( $seen, false );
		if ( is_wp_error( $verdict ) ) {
			return $verdict;
		}
		return $verdict + array(
			'resolution' => 'shared',
			'copies'     => count( $seen ),
		);
	}

	/**
	 * Every formId a parsed block tree carries, sanitized the way the
	 * submission handler sanitizes a posted one (sanitize_title), in tree
	 * order, each once.
	 *
	 * @param array<int, mixed> $blocks Parsed blocks.
	 * @return list<string>
	 */
	function blocklane_pro_forms_form_ids( array $blocks ): array {
		$ids = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( 'blocklane/form' === ( $block['blockName'] ?? '' ) && isset( $block['attrs']['formId'] ) && is_string( $block['attrs']['formId'] ) && '' !== $block['attrs']['formId'] ) {
				$ids[] = sanitize_title( $block['attrs']['formId'] );
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$ids = array_merge( $ids, blocklane_pro_forms_form_ids( $block['innerBlocks'] ) );
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * save_post and deleted_post: forget the remembered miss and the
	 * memoized candidate rows for every formId the post carries, so a form
	 * published a moment after a spam submission named its id resolves on
	 * the very next submission (security read F4), a copy published, edited
	 * or deleted since the rows were memoized is weighed on the next one, and
	 * a deleted copy stops counting toward the examination budget (S-R8).
	 * Saving or deleting site chrome or a synced pattern also forgets the
	 * synced patterns chrome places (S-R4, F03: a pattern can place
	 * another). It reads the post's content and deletes object-cache
	 * entries; it writes nothing else and never touches content (G5).
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	function blocklane_pro_forms_saved( int $post_id, \WP_Post $post ): void {
		unset( $post_id );
		if ( 'wp_block' === $post->post_type || in_array( $post->post_type, blocklane_pro_forms_chrome_types(), true ) ) {
			wp_cache_delete( 'placed', 'blocklane_pro_forms_chrome_refs' );
		}
		if ( ! str_contains( (string) $post->post_content, 'wp:blocklane/form ' ) ) {
			return;
		}
		foreach ( blocklane_pro_forms_form_ids( parse_blocks( (string) $post->post_content ) ) as $form_id ) {
			wp_cache_delete( $form_id, 'blocklane_pro_forms_loc_miss' );
			wp_cache_delete( $form_id, 'blocklane_pro_forms_candidates' );
		}
	}
	add_action( 'save_post', 'blocklane_pro_forms_saved', 10, 2 );
	add_action( 'deleted_post', 'blocklane_pro_forms_saved', 10, 2 );

	/*
	 * ------------------------------------------------------------------
	 * Test mode (spec forms.md v4, TM-A to TM-C). A form whose stored
	 * `testMode` is strictly true runs every check on a submission and
	 * answers with its real message, but keeps nothing: the handler returns
	 * before the blocklane_pro_forms_submission action. Turning a form's
	 * submissions off is a data-loss switch, so only a user who can edit
	 * others' posts may keep it on a save; everyone else's saves strip it.
	 * ------------------------------------------------------------------
	 */

	/** The option counting each test-mode form's dropped submissions (no PII). */
	define( 'BLOCKLANE_PRO_FORMS_TEST_DROPS_OPTION', 'blocklane_pro_forms_test_drops' );

	/** The transient caching the admin notice's list of test-mode holders. */
	define( 'BLOCKLANE_PRO_FORMS_TEST_HOLDERS_TRANSIENT', 'blocklane_pro_forms_test_holders' );

	/**
	 * What a test success says (spec forms.md v4 TM-B), the one answer the
	 * handler's test branch and the device's simulation (TM-D) share. Never
	 * `action: 'redirect'` (the §40 security read, F6: view.js follows a
	 * redirect payload with window.location.assign()): a redirect form shows
	 * its success notice instead, and its stored replace flag, which no
	 * editor shows for a redirect form, does not apply either.
	 *
	 * @param array<string, mixed> $form_attrs The form's resolved attributes.
	 */
	function blocklane_pro_forms_test_answer( array $form_attrs ): \WP_REST_Response {
		if ( 'redirect' === ( $form_attrs['successAction'] ?? '' ) ) {
			$form_attrs = array_merge(
				$form_attrs,
				array(
					'successAction'    => 'message',
					'redirectUrl'      => '',
					'replaceOnSuccess' => false,
				)
			);
		}
		return rest_ensure_response( blocklane_pro_forms_success_response( $form_attrs ) );
	}

	/**
	 * Whether a form rendered now simulates its submission, and how it
	 * answers (spec forms.md TM-D): null to submit for real, else
	 * 'message' or 'replace', test mode's own answer for the form (a
	 * redirect form answers 'message'). Decided from a code-level event and
	 * a filter, never from anything a request carries.
	 *
	 * Filter `blocklane_pro_forms_simulate`: A DATA-LOSS SWITCH FOR PREVIEWS
	 * ONLY, never a convenience. Strictly `true` turns it on. Its default is
	 * whether the interactive device preview fired
	 * `blocklane_pro_pattern_preview_interactive` for this request. A form
	 * rendered under `true` carries `data-bl-simulate`, and the view module
	 * acts on it only in an opaque-origin (sandboxed) document: the device,
	 * or a real page this site embeds in a sandboxed iframe, whose
	 * submissions are then dropped behind the success message. On a page a
	 * visitor opens, the form submits for real whatever this filter
	 * returns. Render-only: the submission handler never reads it.
	 *
	 * @param array<string, mixed> $attributes The form block's attributes.
	 * @return 'message'|'replace'|null
	 */
	function blocklane_pro_forms_simulation( array $attributes ): ?string {
		/**
		 * Whether forms rendered now simulate their submission (see above).
		 *
		 * @param bool $simulate Whether the interactive device preview is rendering.
		 */
		if ( true !== apply_filters( 'blocklane_pro_forms_simulate', did_action( 'blocklane_pro_pattern_preview_interactive' ) > 0 ) ) {
			return null;
		}
		$answer = blocklane_pro_forms_test_answer( blocklane_pro_forms_resolve_config( $attributes ) )->get_data();
		return true === ( is_array( $answer ) ? ( $answer['replace'] ?? null ) : null ) ? 'replace' : 'message';
	}

	/**
	 * The line a simulated success appends inside the form's success
	 * message (spec forms.md TM-D; Garrett, 2026-10-17): one constant,
	 * translatable string, carried to the view module in the Interactivity
	 * config only when a form simulates.
	 */
	function blocklane_pro_forms_simulated_line(): string {
		return __( 'This was a simulated submission. Nothing was sent.', 'blocklane' );
	}

	/**
	 * Whether the current user's saves keep a form's testMode: the one
	 * answer, read by the strip filter, the editor bridge, the front badge
	 * and the notice. User 0 (WP-CLI without --user, cron) cannot, so its
	 * saves strip.
	 */
	function blocklane_pro_forms_can_test_mode(): bool {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * Core's block-delimiter grammar (WP_Block_Parser::next_token()), so
	 * the strip reads every delimiter exactly as parse_blocks() will.
	 */
	define( 'BLOCKLANE_PRO_FORMS_DELIMITER_RE', '/<!--\s+(?P<closer>\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\/)?(?P<name>[a-z][a-z0-9_-]*)\s+(?P<attrs>{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?}\s+)?(?P<void>\/)?-->/s' );

	/**
	 * Content with the testMode attribute removed from every blocklane/form
	 * opening delimiter that carries the key, at any depth (spec forms.md v4
	 * TM-C; security read F1). Each delimiter's attributes are decoded the
	 * way parse_blocks() decodes them, so a \u-escaped key and a duplicate
	 * key are the key it reads; the key's PRESENCE strips, whatever its
	 * value. A changed delimiter is rewritten through core's own
	 * serialize_block_attributes(); every other byte is kept.
	 *
	 * @param string $content Unslashed post content.
	 * @return string The content, unchanged when no delimiter carried the key.
	 */
	function blocklane_pro_forms_without_test_mode( string $content ): string {
		if ( ! str_contains( $content, 'wp:blocklane/form' ) ) {
			return $content;
		}
		$out = preg_replace_callback(
			BLOCKLANE_PRO_FORMS_DELIMITER_RE,
			static function ( array $m ): string {
				// PCRE leaves trailing groups that did not take part out of
				// $m, so a delimiter with no attributes has no attrs key (and
				// no void key) at all.
				$raw = $m['attrs'] ?? '';
				if ( '' !== $m['closer'] || 'blocklane/' !== $m['namespace'] || 'form' !== $m['name'] || '' === $raw ) {
					return $m[0];
				}
				$attrs = json_decode( $raw, true );
				if ( ! is_array( $attrs ) || ! array_key_exists( 'testMode', $attrs ) ) {
					return $m[0];
				}
				unset( $attrs['testMode'] );
				$void = isset( $m['void'] ) && '' !== $m['void'] ? '/' : '';
				return array() === $attrs
					? '<!-- wp:blocklane/form ' . $void . '-->'
					: '<!-- wp:blocklane/form ' . serialize_block_attributes( $attrs ) . ' ' . $void . '-->';
			},
			$content
		);
		return is_string( $out ) ? $out : $content;
	}

	/**
	 * wp_insert_post_data: a save by a user who may not keep test mode
	 * leaves no form in test mode (spec forms.md v4 TM-C). Every post type,
	 * every door that saves through wp_insert_post() (the editor, REST,
	 * abilities, revisions, autosaves, WP-CLI). The content arrives slashed
	 * (core slashes it for the database): unslashed to read, slashed back
	 * only when changed (security read F2).
	 *
	 * @param array<string, mixed> $data Slashed post data.
	 * @return array<string, mixed>
	 */
	function blocklane_pro_forms_strip_test_mode( array $data ): array {
		if ( ! isset( $data['post_content'] ) || ! is_string( $data['post_content'] ) || blocklane_pro_forms_can_test_mode() ) {
			return $data;
		}
		$content  = wp_unslash( $data['post_content'] );
		$stripped = blocklane_pro_forms_without_test_mode( $content );
		if ( $stripped !== $content ) {
			$data['post_content'] = wp_slash( $stripped );
		}
		return $data;
	}
	add_filter( 'wp_insert_post_data', 'blocklane_pro_forms_strip_test_mode', 10, 1 );

	/**
	 * blocklane_pro_forms_test_submission: count a test-mode form's dropped
	 * submission (a count and the last time, keyed by formId; no field value
	 * or address). The formId is one resolve() found in stored content, so
	 * the keys are bounded by the site's own forms.
	 *
	 * @param string $form_id A resolved formId.
	 */
	function blocklane_pro_forms_count_test_drop( string $form_id ): void {
		$drops = get_option( BLOCKLANE_PRO_FORMS_TEST_DROPS_OPTION, array() );
		$drops = is_array( $drops ) ? $drops : array();
		$prior = isset( $drops[ $form_id ]['count'] ) ? absint( $drops[ $form_id ]['count'] ) : 0;

		$drops[ $form_id ] = array(
			'count' => $prior + 1,
			'last'  => time(),
		);
		update_option( BLOCKLANE_PRO_FORMS_TEST_DROPS_OPTION, $drops, false );
	}
	add_action( 'blocklane_pro_forms_test_submission', 'blocklane_pro_forms_count_test_drop', 10, 1 );

	/**
	 * The forms in test mode in a parsed block tree, strictly true, at any
	 * depth: each one's sanitized formId => its name, added to $found.
	 *
	 * @param array<int, mixed>                  $blocks Parsed blocks.
	 * @param array<string, array{name: string}> $found  Filled.
	 */
	function blocklane_pro_forms_test_forms_in( array $blocks, array &$found ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( 'blocklane/form' === ( $block['blockName'] ?? '' ) && true === ( $block['attrs']['testMode'] ?? null ) ) {
				$id           = isset( $block['attrs']['formId'] ) && is_string( $block['attrs']['formId'] ) ? sanitize_title( $block['attrs']['formId'] ) : '';
				$found[ $id ] = array( 'name' => isset( $block['attrs']['formName'] ) && is_string( $block['attrs']['formName'] ) ? $block['attrs']['formName'] : '' );
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				blocklane_pro_forms_test_forms_in( $block['innerBlocks'], $found );
			}
		}
	}

	/**
	 * Every live holder of a form in test mode: any post type, any status in
	 * BLOCKLANE_PRO_FORMS_LIVE_STATUSES (a private holder's form is served
	 * too; security read F6). The SQL prefilters on the form's delimiter;
	 * each row is then parsed, so nested forms count and a stored "false"
	 * does not. Cached for ten minutes (a database restore fires no hook);
	 * save_post and deleted_post forget it sooner.
	 *
	 * @return list<array{post_id: int, forms: array<string, array{name: string}>}>
	 */
	function blocklane_pro_forms_test_holders(): array {
		$cached = get_transient( BLOCKLANE_PRO_FORMS_TEST_HOLDERS_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$statuses = BLOCKLANE_PRO_FORMS_LIVE_STATUSES;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a content scan core has no API for (any post type, by block delimiter); cached in the transient above.
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts}
				WHERE post_status IN ( " . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ' )
				AND post_content LIKE %s
				ORDER BY ID DESC
				LIMIT 500',
				array_merge( $statuses, array( '%' . $wpdb->esc_like( '<!-- wp:blocklane/form ' ) . '%' ) )
			),
			ARRAY_A
		);
		$holders = array();
		foreach ( (array) $rows as $row ) {
			$found = array();
			blocklane_pro_forms_test_forms_in( parse_blocks( (string) $row['post_content'] ), $found );
			if ( array() !== $found ) {
				$holders[] = array(
					'post_id' => (int) $row['ID'],
					'forms'   => $found,
				);
			}
		}
		set_transient( BLOCKLANE_PRO_FORMS_TEST_HOLDERS_TRANSIENT, $holders, 10 * MINUTE_IN_SECONDS );
		return $holders;
	}

	/**
	 * save_post and deleted_post: forget the test-mode holder list when the
	 * post holds a form now or was a listed holder.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	function blocklane_pro_forms_test_holders_saved( int $post_id, \WP_Post $post ): void {
		$cached = get_transient( BLOCKLANE_PRO_FORMS_TEST_HOLDERS_TRANSIENT );
		$listed = is_array( $cached ) && in_array( $post_id, array_map( static fn( $holder ): int => is_array( $holder ) ? (int) ( $holder['post_id'] ?? 0 ) : 0, $cached ), true );
		if ( $listed || str_contains( (string) $post->post_content, 'wp:blocklane/form ' ) ) {
			delete_transient( BLOCKLANE_PRO_FORMS_TEST_HOLDERS_TRANSIENT );
		}
	}
	add_action( 'save_post', 'blocklane_pro_forms_test_holders_saved', 10, 2 );
	add_action( 'deleted_post', 'blocklane_pro_forms_test_holders_saved', 10, 2 );

	/**
	 * admin_notices: on the Dashboard, every post list and the plugin's own
	 * screens, tell a user who can turn test mode off which forms are in it
	 * and how many submissions each has not kept (spec forms.md v4 TM-C).
	 */
	function blocklane_pro_forms_test_mode_notice(): void {
		if ( ! blocklane_pro_forms_can_test_mode() || ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen instanceof \WP_Screen || ! ( 'dashboard' === $screen->base || 'edit' === $screen->base || str_contains( (string) $screen->id, 'blocklane' ) ) ) {
			return;
		}
		$holders = blocklane_pro_forms_test_holders();
		if ( array() === $holders ) {
			return;
		}
		$drops = get_option( BLOCKLANE_PRO_FORMS_TEST_DROPS_OPTION, array() );
		$drops = is_array( $drops ) ? $drops : array();
		$items = array();
		foreach ( $holders as $holder ) {
			$title = get_the_title( $holder['post_id'] );
			$title = '' !== $title ? $title : __( '(no title)', 'blocklane' );
			$link  = current_user_can( 'edit_post', $holder['post_id'] ) ? (string) get_edit_post_link( $holder['post_id'], 'raw' ) : '';
			foreach ( $holder['forms'] as $form_id => $form ) {
				$items[] = array(
					'title' => $title,
					'link'  => $link,
					'form'  => '' !== $form['name'] ? $form['name'] : (string) $form_id,
					'count' => isset( $drops[ $form_id ]['count'] ) ? absint( $drops[ $form_id ]['count'] ) : 0,
				);
			}
		}
		echo '<div class="notice notice-warning blocklane-forms-test-mode"><p><strong>';
		echo esc_html(
			sprintf(
				/* translators: %d: number of forms in test mode. */
				_n( '%d form is in test mode, so its submissions aren’t saved or sent.', '%d forms are in test mode, so their submissions aren’t saved or sent.', count( $items ), 'blocklane' ),
				count( $items )
			)
		);
		echo '</strong></p><ul>';
		foreach ( $items as $item ) {
			echo '<li>';
			echo '' !== $item['link'] ? '<a href="' . esc_url( $item['link'] ) . '">' . esc_html( $item['title'] ) . '</a>' : esc_html( $item['title'] );
			echo ': ' . esc_html(
				sprintf(
					/* translators: 1: form name, 2: number of submissions not kept. */
					_n( '“%1$s”, %2$d submission not kept', '“%1$s”, %2$d submissions not kept', $item['count'], 'blocklane' ),
					$item['form'],
					$item['count']
				)
			);
			echo '</li>';
		}
		echo '</ul></div>';
	}
	add_action( 'admin_notices', 'blocklane_pro_forms_test_mode_notice' );

	/**
	 * Merge a parsed block's saved attributes over its block.json defaults.
	 *
	 * The Gutenberg serializer omits any attribute whose value equals its
	 * default, so parse_blocks() returns a partial attrs map — a Dropdown that
	 * only had its label set arrives with NO `options` key at all. The render
	 * side gets defaults applied for free (WP_Block does this before calling
	 * render.php); schema derivation reads raw parsed attrs, so it must merge
	 * the same defaults or it validates against the wrong (empty) shape.
	 *
	 * @param string $block_name Full block name.
	 * @param array  $attrs      Raw parsed attributes.
	 * @return array Attributes with block.json defaults filled in.
	 */
	function blocklane_pro_forms_attrs_with_defaults( $block_name, $attrs ) {
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
		if ( ! $type || empty( $type->attributes ) ) {
			return $attrs;
		}
		$defaults = array();
		foreach ( $type->attributes as $key => $schema ) {
			if ( array_key_exists( 'default', $schema ) ) {
				$defaults[ $key ] = $schema['default'];
			}
		}

		return array_merge( $defaults, is_array( $attrs ) ? $attrs : array() );
	}

	/**
	 * Derive the canonical field schema from a parsed form block — the exact
	 * name resolution the render uses, computed live at submit time. No cached
	 * schema, nothing to drift. Keyed by field name; a name collision means
	 * the later field wins (matching the browser's last-value-wins POST and
	 * the render, which also uses pure names).
	 *
	 * A walk that needs more synced patterns than it may read (the ceiling,
	 * or what is left of the caller's pool) is refused whole: a schema
	 * missing the fields core still renders would trap their names as
	 * tampering and fake-succeed a real visitor (spec §19, F06).
	 *
	 * @param array<string, mixed> $form_block Parsed blocklane/form block.
	 * @param list<string>|null    $ignored    Filled with the posted names of suite blocks this
	 *                                         edition does not register (rule 6); null skips it.
	 * @param int|null             $pool       Synced-pattern descents left to the caller, spent by this walk; null
	 *                                         lends it BLOCKLANE_PRO_FORMS_REF_CEILING.
	 * @return array<string, array<string, mixed>>|\WP_Error Field schemas keyed by field name;
	 *         blocklane_forms_ref_ceiling when the walk is refused.
	 */
	function blocklane_pro_forms_derive_schema( array $form_block, ?array &$ignored = null, ?int &$pool = null ): array|\WP_Error {
		$fields = array();
		$ctx    = null;
		$lent   = null === $pool ? BLOCKLANE_PRO_FORMS_REF_CEILING : min( BLOCKLANE_PRO_FORMS_REF_CEILING, max( 0, $pool ) );
		blocklane_pro_forms_collect_fields( isset( $form_block['innerBlocks'] ) && is_array( $form_block['innerBlocks'] ) ? $form_block['innerBlocks'] : array(), $fields, $ignored, $ctx, $lent );
		if ( null !== $pool ) {
			$pool -= $lent - $ctx['refs'];
		}
		if ( $ctx['refused'] ) {
			return new \WP_Error( 'blocklane_forms_ref_ceiling', 'The form references more synced patterns than one examination reads.' );
		}

		return $fields;
	}

	/**
	 * Registered field types (the v3 extension seam) — how a field block
	 * plugs into schema derivation, validation and storage without editing
	 * this file. Pro's own file field registers through it
	 * (inc/forms/file-upload/runtime.php, the block:form-file unit), so the
	 * seam is what makes the write-side upload pipeline a unit the free build
	 * can leave out; a companion add-on registers the same way. Filter shape:
	 *
	 *   add_filter( 'blocklane_pro_forms_field_types', function ( $types ) {
	 *       $types['my-addon/my-field'] = array(
	 *           // Schema row for one parsed block, or null to skip it.
	 *           // MUST include 'name' (use blocklane_pro_forms_field_name()).
	 *           // Called with the defaults-merged attributes and the parsed
	 *           // block; PHP passes extra arguments to a userland callable
	 *           // harmlessly, so a collector that needs only the attributes
	 *           // may declare one parameter.
	 *           //
	 *           // A PURE FUNCTION OF THE BLOCK'S STORED ATTRIBUTES. The row
	 *           // is part of the form's definition, which the form's binding
	 *           // (_bl_bind) signs: anything the environment decides — an
	 *           // upload cap, a filter's answer, the request, the user —
	 *           // belongs in `validate`, computed at call time. A row that
	 *           // varies with its context changes the binding under every
	 *           // cached page, and every submission of the form then arrives
	 *           // unsigned (spec 2026-10-06-forms-form-ownership NP16).
	 *           'collect'  => function ( array $attrs, array $block ) { … },
	 *           // Same contract as the internal validator:
	 *           // return array( 'value' => mixed, 'error' => string ), plus
	 *           // 'pending' (a list the persist step consumes) for a type
	 *           // that posts files.
	 *           'validate' => function ( $raw, array $schema ) { … },
	 *           // Optional. 'files': validate receives the field's $_FILES
	 *           // entry instead of its posted value.
	 *           'source'   => 'params' | 'files',
	 *           // Optional. Called after the WHOLE submission validated with
	 *           // the fields by reference. A files-source row carries `ext`
	 *           // (the block name that owns it): claim the rows whose `ext`
	 *           // is yours, move their 'pending' into 'files' + 'value', and
	 *           // LEAVE EVERY OTHER ROW UNTOUCHED — every registered handler
	 *           // is handed the whole list, in registry order, and the base
	 *           // strips leftover 'pending' only after all of them ran. Never
	 *           // claim by `type`: another type may say 'file' too (#1018).
	 *           // Return whether all of your rows persisted (false fails the
	 *           // submission, honestly). persist and discard are meaningful
	 *           // for `source => 'files'` only: a params-source row carries
	 *           // neither `ext` nor `pending`, so nothing can claim it.
	 *           //
	 *           // STORE UNDER blocklane_pro_forms_upload_root() AND RECORD
	 *           // EACH FILE AS files[].stored RELATIVE TO IT. That snapshot
	 *           // shape is how the row is served (the download route), listed
	 *           // (the inbox), deleted, purged — and, when a LATER handler
	 *           // refuses, rolled back: the base reaps every recorded path and
	 *           // strips `files`, so a refusal leaves no orphan. Bytes written
	 *           // anywhere else are outside the module's stewardship in all
	 *           // four senses. Handlers still return bool; the base translates
	 *           // a refusal into the typed failure its caller reports.
	 *           'persist'  => function ( array &$fields ): bool { … },
	 *           // Optional. Storage is opted out: given one row still
	 *           // carrying 'pending', return the row as it should be mailed
	 *           // (no bytes kept) when its `ext` is yours, or null otherwise.
	 *           'discard'  => function ( array $field ): ?array { … },
	 *           // A files-source row's `ext` — and any key a handler writes on
	 *           // a row — is stored with the submission and served by the
	 *           // inbox REST route: part of the stored-row contract, so a key
	 *           // that changes shape changes NAME (#1049).
	 *       );
	 *       return $types;
	 *   } );
	 *
	 * WHICH `blocklane/` SLUGS A FILTER MAY CLAIM. Exactly the suite blocks
	 * whose kind is 'seam' (BLOCKLANE_PRO_FORMS_FIELDS, read through
	 * blocklane_pro_forms_field_kind) AND that this edition registers
	 * (blocklane_pro_forms_known_blocks). So: an internal field is refused
	 * (its invariants stay hardcoded); a WRAPPER or a control — form,
	 * form-step, form-option, form-submit-button, form-notification — is
	 * refused, because a registered type is a schema LEAF and admitting a
	 * wrapper erases every field inside it from the schema while the render
	 * still emits them (#1022); and a contributed field this edition does not
	 * carry is refused, because a schema row for a block that renders nothing
	 * requires a value the visitor was never shown. Names outside the
	 * blocklane/ namespace are a third party's own and are not judged here.
	 * Filters resolve at call time, so the runtime sees registrations
	 * normally. Caveat a registrant must own: while its file is not loaded its
	 * fields vanish from the schema — a posted key naming one is then dropped
	 * when the block is a suite block this edition does not register
	 * (blocklane_pro_forms_collect_fields' `$ignored`), and trapped as unknown
	 * otherwise, the same exposure as removing any block.
	 *
	 * @return array<string, array{collect: callable, validate: callable, source?: string, persist?: callable, discard?: callable}>
	 */
	function blocklane_pro_forms_field_type_registry(): array {
		$types    = apply_filters( 'blocklane_pro_forms_field_types', array() );
		$registry = array();
		$known    = null;
		foreach ( (array) $types as $slug => $handlers ) {
			if ( ! is_string( $slug ) || '' === $slug ) {
				continue;
			}
			if ( 0 === strpos( $slug, 'blocklane/' ) ) {
				// A slug of our own suite: seam-kind AND carried here, or no.
				$known = $known ?? blocklane_pro_forms_known_blocks();
				if ( 'seam' !== blocklane_pro_forms_field_kind( $slug )
					|| ! in_array( substr( $slug, strlen( 'blocklane/' ) ), $known, true ) ) {
					continue;
				}
			}
			if ( ! is_array( $handlers )
				|| ! isset( $handlers['collect'], $handlers['validate'] )
				|| ! is_callable( $handlers['collect'] )
				|| ! is_callable( $handlers['validate'] ) ) {
				continue;
			}
			foreach ( array( 'persist', 'discard' ) as $optional ) {
				if ( isset( $handlers[ $optional ] ) && ! is_callable( $handlers[ $optional ] ) ) {
					continue 2;
				}
			}
			$handlers['source'] = isset( $handlers['source'] ) && 'files' === $handlers['source'] ? 'files' : 'params';
			$registry[ $slug ]  = $handlers;
		}

		return $registry;
	}

	/**
	 * Persist every field's `pending` uploads through the type that owns it,
	 * after the WHOLE submission validated: each registered type with a
	 * `persist` handler runs once over the fields (by reference); a handler
	 * that reports failure fails the submission. Anything still `pending`
	 * afterwards — a type with no persist step — is stripped, so no tmp path
	 * reaches storage or mail.
	 *
	 * THE WHOLE STEP IS ONE TRANSACTION. Handlers run in registry order, and
	 * a refusal by handler N leaves handlers 1..N-1 having already written
	 * bytes: a 500 goes back to the visitor, no row is stored, and those
	 * files had no row to be reached, served, deleted or purged through — an
	 * orphan forever, in a private directory nothing sweeps (#1019). So on a
	 * refusal the base reaps through its OWN stewardship: every `files[].stored`
	 * recorded so far is the shape the download route serves, the inbox lists
	 * and delete/purge/uninstall remove, and blocklane_pro_forms_stored_paths()
	 * → blocklane_pro_forms_delete_stored_files() is that walk, confined by
	 * resolve_stored_file(). `pending` and `files` are then stripped from
	 * every row so nothing downstream reads a path that no longer exists.
	 *
	 * No `rollback` seam key: a second thing every handler must get right is a
	 * second door, and the base already owns both the snapshot shape and the
	 * delete primitive. A handler's own partial-move rollback still stands —
	 * it covers the row whose `files` is not yet assigned, and wp_delete_file()
	 * on an already-deleted path is harmless. Bytes written OUTSIDE
	 * blocklane_pro_forms_upload_root() are outside the module's stewardship
	 * in all four senses: unserved, unlisted, undeleted and un-rolled-back.
	 *
	 * The native type is the PHP 8.1 union; the docblock narrows it, so
	 * PHPStan makes every caller handle the failure.
	 *
	 * @param array<int, array<string, mixed>> $fields Self-described fields.
	 * @return true|\WP_Error True when every pending upload persisted;
	 *                        blocklane_pro_forms_persist_failed naming the
	 *                        refusing type's slug otherwise.
	 */
	function blocklane_pro_forms_persist_pending( array &$fields ): bool|\WP_Error {
		foreach ( blocklane_pro_forms_field_type_registry() as $slug => $handlers ) {
			if ( empty( $handlers['persist'] ) ) {
				continue;
			}
			if ( false === call_user_func_array( $handlers['persist'], array( &$fields ) ) ) {
				// Reap what the handlers that already ran stored, then strip
				// both keys: `files` would otherwise point at deleted bytes.
				blocklane_pro_forms_delete_stored_files( blocklane_pro_forms_stored_paths( $fields ) );
				foreach ( $fields as &$blocklane_reaped ) {
					unset( $blocklane_reaped['pending'], $blocklane_reaped['files'] );
				}
				unset( $blocklane_reaped );

				return new \WP_Error( 'blocklane_pro_forms_persist_failed', (string) $slug );
			}
		}
		foreach ( $fields as &$field ) {
			unset( $field['pending'] );
		}
		unset( $field );

		return true;
	}

	/**
	 * Storage is opted out: hand every field still carrying `pending` to the
	 * type that owns it for its no-storage shape (the file field mails the
	 * names and keeps no bytes), and strip `pending` from all of them.
	 *
	 * @param array<int, array<string, mixed>> $fields Self-described fields.
	 */
	function blocklane_pro_forms_discard_pending( array &$fields ): void {
		$registry = blocklane_pro_forms_field_type_registry();
		foreach ( $fields as &$field ) {
			if ( ! empty( $field['pending'] ) ) {
				foreach ( $registry as $handlers ) {
					if ( empty( $handlers['discard'] ) ) {
						continue;
					}
					$shaped = call_user_func( $handlers['discard'], $field );
					if ( is_array( $shaped ) ) {
						$field = $shaped;
						break;
					}
				}
			}
			unset( $field['pending'] );
		}
		unset( $field );
	}

	/**
	 * Recursive field collector for schema derivation. Descends layout
	 * wrappers AND synced patterns (core/block refs) so fields moved into a
	 * reusable block — the spec's recommended reuse path — are still seen.
	 *
	 * THE SETS ARE COMPUTED ONCE PER WALK, not per recursion and not per block.
	 * The registry is an apply_filters; `known` and `field_blocks` are derived
	 * from it and from the edition view. Recomputing them inside the loop cost
	 * one filter run per block per render AND per submission (#1035). They are
	 * passed down in $ctx instead of memoized in a static: filters resolve at
	 * CALL time by contract (a registrant may add or remove a type mid-request,
	 * and E38g/E38h do exactly that), so a cache that outlived one walk would
	 * be a second source of truth with a different answer.
	 *
	 * The walk's state rides $ctx BY REFERENCE: `visited` (the synced
	 * patterns already descended), `refs` (the descents left) and `refused`
	 * are one walk's, shared by every recursion, or a pattern that
	 * references itself would be descended once per branch. The visited set
	 * alone ends a cycle; `refs` only bounds the cost, and running out of it
	 * REFUSES the walk (`refused`, which derive_schema() turns into an
	 * error) instead of skipping the pattern: core renders that pattern's
	 * fields, so a schema without them would trap a real visitor (F06).
	 *
	 * @param array             $blocks  Parsed blocks.
	 * @param array             $fields  Collected schemas (by reference).
	 * @param list<string>|null $ignored Collects the names of unknown-suite wrappers' fields (rule 6).
	 * @param array{registry: array<string, array<string, mixed>>, known: list<string>, field_blocks: list<string>, visited: array<int, true>, refs: int, refused: bool}|null $ctx
	 *        The walk's sets and state; null computes them (the entry call).
	 *        Recursion passes its own down, by reference.
	 * @param int               $lent    The entry call's synced-pattern descents (derive_schema() lends the
	 *                                   ceiling, or less when the caller's pool is lower); recursion ignores it.
	 */
	function blocklane_pro_forms_collect_fields( $blocks, array &$fields, ?array &$ignored = null, ?array &$ctx = null, int $lent = BLOCKLANE_PRO_FORMS_REF_CEILING ): void {
		if ( null === $ctx ) {
			$blocklane_registry = blocklane_pro_forms_field_type_registry();
			$blocklane_known    = array();
			foreach ( blocklane_pro_forms_known_blocks() as $blocklane_known_slug ) {
				$blocklane_known[] = 'blocklane/' . $blocklane_known_slug;
			}
			$ctx = array(
				'registry'     => $blocklane_registry,
				'known'        => $blocklane_known,
				'field_blocks' => blocklane_pro_forms_field_blocks( $blocklane_registry ),
				'visited'      => array(),
				'refs'         => $lent,
				'refused'      => false,
			);
		}
		$registry = $ctx['registry'];
		$known    = $ctx['known'];

		foreach ( (array) $blocks as $block ) {
			if ( $ctx['refused'] ) {
				return;
			}
			if ( ! is_array( $block ) || empty( $block['blockName'] ) ) {
				continue;
			}

			// External field types (extension seam) resolve before the
			// internal switch; a registered block is a schema leaf — its
			// inner blocks are its own concern, never descended for fields.
			if ( isset( $registry[ $block['blockName'] ] ) ) {
				$blocklane_ext_attrs = blocklane_pro_forms_attrs_with_defaults(
					$block['blockName'],
					isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array()
				);

				// Defaults merged FIRST, exactly as the internal branch does.
				// parse_blocks() strips attributes left at their block.json
				// default, so an add-on reading $block['attrs'] raw sees a
				// required field as optional, or a type as absent — the schema
				// then disagrees with what was rendered, which on this project
				// means submissions are silently dropped. The helper no-ops for
				// blocks it does not know, so an unregistered add-on block is
				// unaffected.
				$row = call_user_func(
					$registry[ $block['blockName'] ]['collect'],
					$blocklane_ext_attrs,
					$block
				);
				if ( is_array( $row ) && ! empty( $row['name'] ) && is_string( $row['name'] ) ) {
					$name = $row['name'];
					unset( $row['name'] );

					// An external field's own Visibility rule. It never reached
					// the schema: this branch returns before the attach below,
					// which only knows the four internal field block names. Meanwhile
					// the render base an add-on is told to reuse DOES emit
					// data-bl-cond — so the browser hid the field and the server,
					// holding no rule for it, still required and validated it.
					$blocklane_ext_cond = blocklane_pro_forms_field_condition( $blocklane_ext_attrs );
					if ( null !== $blocklane_ext_cond ) {
						$row['condition'] = $blocklane_ext_cond;
					}

					// Fill the keys every consumer assumes a schema row has —
					// the email template, the inbox and the CSV all read type
					// and label unguarded, so an add-on that omits them takes
					// those surfaces down rather than degrading.
					$row += array(
						'type'  => 'text',
						'label' => $name,
						'value' => '',
					);

					// The dispatch key validate_field routes back through.
					$row['ext']       = $block['blockName'];
					$row['required']  = ! empty( $row['required'] );
					$row['isReplyTo'] = false;
					$fields[ $name ]  = $row;
				}
				continue;
			}
			$attrs = blocklane_pro_forms_attrs_with_defaults(
				$block['blockName'],
				isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array()
			);

			// A block of the SUITE this edition does not register — a
			// contributor's (form-step, form-file) in a form authored under
			// the other edition — is a LAYOUT WRAPPER here: descend for the
			// fields it may hold, collect nothing for itself, and record the
			// name it WOULD have posted, so a submission from a page cached
			// under the other edition is dropped rather than trapped as
			// tampering (rule 6; handle_submission). Never a schema row: a row
			// for a block that renders nothing is the fake-success shape.
			//
			// The bypass name is recorded only for a block that POSTS one
			// (field_kind is not null). A step is a wrapper: it has a label
			// and no value, so minting a name from its label — or the bare
			// 'field' fallback from an untitled one — put a phantom key on the
			// list and narrowed the tamper trap by exactly that key (#1020).
			if ( str_starts_with( (string) $block['blockName'], 'blocklane/form-' ) && ! in_array( $block['blockName'], $known, true ) ) {
				if ( null !== $ignored && null !== blocklane_pro_forms_field_kind( (string) $block['blockName'] ) ) {
					$ignored[] = blocklane_pro_forms_field_name( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array() );
				}
				if ( ! empty( $block['innerBlocks'] ) ) {
					blocklane_pro_forms_collect_fields( $block['innerBlocks'], $fields, $ignored, $ctx );
				}
				continue;
			}

			// Conditions attach post-switch by key diff, so every internal
			// field case gains them without per-case wiring — but only for
			// LEAF field blocks: a core/block descent adds many fields that
			// must never inherit the wrapper's condition.
			$blocklane_is_field_block = in_array(
				$block['blockName'],
				$ctx['field_blocks'],
				true
			);

			switch ( $block['blockName'] ) {
				case 'blocklane/form-input':
					$type = isset( $attrs['type'] ) && in_array( $attrs['type'], BLOCKLANE_PRO_FORMS_INPUT_TYPES, true ) ? $attrs['type'] : 'text';
					$name = blocklane_pro_forms_field_name( $attrs );

					$fields[ $name ] = array(
						'type'      => $type,
						'label'     => wp_strip_all_tags( isset( $attrs['label'] ) ? (string) $attrs['label'] : '' ),
						'required'  => 'hidden' !== $type && ! empty( $attrs['required'] ),
						'maxlength' => isset( $attrs['maxlength'] ) ? absint( $attrs['maxlength'] ) : 0,
						'min'       => isset( $attrs['min'] ) ? (string) $attrs['min'] : '',
						'max'       => isset( $attrs['max'] ) ? (string) $attrs['max'] : '',
						'step'      => isset( $attrs['step'] ) ? (string) $attrs['step'] : '',
						'isReplyTo' => ! empty( $attrs['isReplyTo'] ),
						'value'     => isset( $attrs['defaultValue'] ) ? (string) $attrs['defaultValue'] : '',
					);
					break;

				case 'blocklane/form-textarea':
					$name = blocklane_pro_forms_field_name( $attrs );

					$fields[ $name ] = array(
						'type'      => 'textarea',
						'label'     => wp_strip_all_tags( isset( $attrs['label'] ) ? (string) $attrs['label'] : '' ),
						'required'  => ! empty( $attrs['required'] ),
						'maxlength' => isset( $attrs['maxlength'] ) ? absint( $attrs['maxlength'] ) : 0,
						'min'       => '',
						'max'       => '',
						'step'      => '',
						'isReplyTo' => false,
						'value'     => '',
					);
					break;

				case 'blocklane/form-select':
					$name = blocklane_pro_forms_field_name( $attrs );

					// Options are form-option children; the options attribute
					// is the legacy fallback for unedited pre-inner-block
					// content. Same source order as render.php.
					$option_attrs_list = array();
					foreach ( (array) ( isset( $block['innerBlocks'] ) ? $block['innerBlocks'] : array() ) as $option_block ) {
						if ( is_array( $option_block ) && 'blocklane/form-option' === ( isset( $option_block['blockName'] ) ? $option_block['blockName'] : '' ) ) {
							// Defaults merged like the parent's attrs above —
							// render.php gets them via WP_Block, so schema
							// must read the same shape.
							$option_attrs_list[] = blocklane_pro_forms_attrs_with_defaults(
								'blocklane/form-option',
								isset( $option_block['attrs'] ) && is_array( $option_block['attrs'] ) ? $option_block['attrs'] : array()
							);
						}
					}
					if ( ! $option_attrs_list && isset( $attrs['options'] ) && is_array( $attrs['options'] ) ) {
						$option_attrs_list = $attrs['options'];
					}

					$options = array();
					foreach ( blocklane_pro_forms_select_options( $option_attrs_list ) as $option ) {
						$options[] = $option['value'];
					}

					$fields[ $name ] = array(
						'type'      => 'select',
						'label'     => wp_strip_all_tags( isset( $attrs['label'] ) ? (string) $attrs['label'] : '' ),
						'required'  => ! empty( $attrs['required'] ),
						'options'   => $options,
						'isReplyTo' => false,
					);
					break;

				case 'blocklane/form-group':
					$name    = blocklane_pro_forms_field_name(
						array(
							'name'  => isset( $attrs['name'] ) ? $attrs['name'] : '',
							'label' => isset( $attrs['legend'] ) ? $attrs['legend'] : '',
						),
						'choices'
					);
					$options = array();
					foreach ( (array) ( isset( $block['innerBlocks'] ) ? $block['innerBlocks'] : array() ) as $option_block ) {
						if ( ! is_array( $option_block ) || 'blocklane/form-option' !== ( isset( $option_block['blockName'] ) ? $option_block['blockName'] : '' ) ) {
							continue;
						}
						$option_attrs = blocklane_pro_forms_attrs_with_defaults(
							'blocklane/form-option',
							isset( $option_block['attrs'] ) && is_array( $option_block['attrs'] ) ? $option_block['attrs'] : array()
						);
						$option_value = blocklane_pro_forms_option_value( $option_attrs );
						if ( '' !== $option_value ) {
							$options[] = $option_value;
						}
					}

					$fields[ $name ] = array(
						'type'      => isset( $attrs['type'] ) && 'checkbox' === $attrs['type'] ? 'checkbox-group' : 'radio-group',
						'label'     => wp_strip_all_tags( isset( $attrs['legend'] ) ? (string) $attrs['legend'] : '' ),
						'required'  => ! empty( $attrs['required'] ),
						'options'   => $options,
						'isReplyTo' => false,
					);
					break;

				case 'core/block':
					// A synced pattern reference: load and descend its content
					// so fields moved into a reusable block are still validated
					// — exactly when core renders it. MIRROR CONTRACT, core
					// blocks/block.php:27-42: a wp_block, published, with no
					// password, and each ref once ($seen_refs there, `visited`
					// here), at any depth, as core. A ref past what the walk
					// may read refuses the walk (BLOCKLANE_PRO_FORMS_REF_CEILING).
					$blocklane_ref_id = ! empty( $attrs['ref'] ) && is_numeric( $attrs['ref'] ) ? absint( $attrs['ref'] ) : 0;
					if ( $blocklane_ref_id > 0 && ! isset( $ctx['visited'][ $blocklane_ref_id ] ) ) {
						if ( $ctx['refs'] < 1 ) {
							$ctx['refused'] = true;
							break;
						}
						$ctx['visited'][ $blocklane_ref_id ] = true;
						--$ctx['refs'];
						$ref = get_post( $blocklane_ref_id );
						if ( $ref && 'wp_block' === $ref->post_type && 'publish' === $ref->post_status && '' === (string) $ref->post_password ) {
							blocklane_pro_forms_collect_fields( parse_blocks( $ref->post_content ), $fields, $ignored, $ctx );
						}
					}
					break;

				default:
					// Layout wrappers (group, columns…) may nest fields.
					if ( ! empty( $block['innerBlocks'] ) ) {
						blocklane_pro_forms_collect_fields( $block['innerBlocks'], $fields, $ignored, $ctx );
					}
			}

			// Attach by NAME, not by diffing the key set. Each of the four internal field
			// blocks writes exactly one $fields[ $name ], and when that name
			// collides with an earlier field the write REPLACES the row rather
			// than adding a key — so the old array_diff() came back empty and
			// the condition landed on nothing, while both wrappers still
			// carried data-bl-cond in the markup. The client hid one, the
			// server (holding a condition-less schema) required it, and the
			// submission failed against a field the visitor could not see.
			if ( $blocklane_is_field_block && isset( $name ) && '' !== (string) $name && isset( $fields[ $name ] ) ) {
				$blocklane_field_cond = blocklane_pro_forms_field_condition( $attrs );
				if ( null !== $blocklane_field_cond ) {
					$fields[ $name ]['condition'] = $blocklane_field_cond;
				} else {
					// A colliding block with no rule must not inherit the
					// replaced row's rule either.
					unset( $fields[ $name ]['condition'] );
				}
			}
		}
	}

	/**
	 * Validate + sanitize one posted value against its field schema.
	 *
	 * @param mixed $raw    Posted value (string, or array for checkbox-group).
	 * @param array $schema Field schema.
	 * @return array array( 'value' => mixed, 'error' => string ) — error '' when valid.
	 */
	function blocklane_pro_forms_validate_field( $raw, $schema ) {
		$required_error = __( 'This field is required.', 'blocklane' );

		// External field types (extension seam) dispatch to their registered
		// validator — same array contract as this function. A schema row
		// whose handler vanished mid-request (add-on race) fails closed as
		// invalid rather than accepting an unvalidated value.
		if ( isset( $schema['ext'] ) ) {
			$registry = blocklane_pro_forms_field_type_registry();
			if ( isset( $registry[ $schema['ext'] ] ) ) {
				$result = call_user_func( $registry[ $schema['ext'] ]['validate'], $raw, $schema );
				if ( is_array( $result ) && array_key_exists( 'value', $result ) ) {
					$out = array(
						'value' => $result['value'],
						'error' => isset( $result['error'] ) ? (string) $result['error'] : '',
					);
					// A file-posting type hands its validated uploads on for
					// the persist step; nothing else reads the key.
					if ( isset( $result['pending'] ) && is_array( $result['pending'] ) ) {
						$out['pending'] = $result['pending'];
					}
					return $out;
				}
			}

			return array(
				'value' => '',
				'error' => __( 'This field could not be validated.', 'blocklane' ),
			);
		}

		// Checkbox groups post arrays; everything else posts strings.
		if ( 'checkbox-group' === $schema['type'] ) {
			$values = array();
			foreach ( (array) $raw as $value ) {
				if ( ! is_string( $value ) ) {
					continue;
				}
				$value = sanitize_text_field( wp_unslash( $value ) );
				if ( '' === $value ) {
					continue;
				}
				if ( ! in_array( $value, $schema['options'], true ) ) {
					return array(
						'value' => array(),
						'error' => __( 'Please choose from the available options.', 'blocklane' ),
					);
				}
				$values[] = $value;
			}
			$values = array_values( array_unique( $values ) );
			if ( $schema['required'] && empty( $values ) ) {
				return array(
					'value' => array(),
					'error' => $required_error,
				);
			}

			return array(
				'value' => $values,
				'error' => '',
			);
		}

		if ( is_array( $raw ) ) {
			$raw = '';
		}
		$value = is_string( $raw ) ? wp_unslash( $raw ) : '';
		$value = 'textarea' === $schema['type'] ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );

		if ( '' === $value ) {
			if ( $schema['required'] ) {
				return array(
					'value' => '',
					'error' => $required_error,
				);
			}

			return array(
				'value' => '',
				'error' => '',
			);
		}

		switch ( $schema['type'] ) {
			case 'email':
				if ( ! is_email( $value ) ) {
					return array(
						'value' => $value,
						'error' => __( 'Please enter a valid email address.', 'blocklane' ),
					);
				}
				$value = sanitize_email( $value );
				break;

			case 'url':
				if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					return array(
						'value' => $value,
						'error' => __( 'Please enter a valid web address.', 'blocklane' ),
					);
				}
				$value = esc_url_raw( $value );
				break;

			case 'number':
				if ( ! is_numeric( $value ) ) {
					return array(
						'value' => $value,
						'error' => __( 'Please enter a number.', 'blocklane' ),
					);
				}
				if ( ( '' !== $schema['min'] && is_numeric( $schema['min'] ) && (float) $value < (float) $schema['min'] )
					|| ( '' !== $schema['max'] && is_numeric( $schema['max'] ) && (float) $value > (float) $schema['max'] ) ) {
					return array(
						'value' => $value,
						'error' => __( 'Please enter a number within the allowed range.', 'blocklane' ),
					);
				}
				// Enforce the authored step (offset from min, or from 0) the
				// same way the browser's native validation does.
				if ( ! empty( $schema['step'] ) && is_numeric( $schema['step'] ) && (float) $schema['step'] > 0 ) {
					$base      = ( '' !== $schema['min'] && is_numeric( $schema['min'] ) ) ? (float) $schema['min'] : 0.0;
					$remainder = fmod( (float) $value - $base, (float) $schema['step'] );
					// Tolerate float error; a non-trivial remainder is off-step.
					if ( abs( $remainder ) > 1e-9 && abs( abs( $remainder ) - (float) $schema['step'] ) > 1e-9 ) {
						return array(
							'value' => $value,
							'error' => __( 'Please enter a valid value.', 'blocklane' ),
						);
					}
				}
				break;

			case 'date':
				$date = \DateTime::createFromFormat( 'Y-m-d', $value );
				if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
					return array(
						'value' => $value,
						'error' => __( 'Please enter a valid date.', 'blocklane' ),
					);
				}
				if ( ( '' !== $schema['min'] && $value < $schema['min'] )
					|| ( '' !== $schema['max'] && $value > $schema['max'] ) ) {
					return array(
						'value' => $value,
						'error' => __( 'Please enter a date within the allowed range.', 'blocklane' ),
					);
				}
				break;

			case 'checkbox':
				// Consent checkbox: the only valid posted value is '1'.
				if ( '1' !== $value ) {
					return array(
						'value' => $value,
						'error' => $required_error,
					);
				}
				break;

			case 'select':
			case 'radio-group':
				if ( ! in_array( $value, $schema['options'], true ) ) {
					return array(
						'value' => $value,
						'error' => __( 'Please choose from the available options.', 'blocklane' ),
					);
				}
				break;
		}

		if ( ! empty( $schema['maxlength'] ) && mb_strlen( $value ) > $schema['maxlength'] ) {
			return array(
				'value' => $value,
				'error' => sprintf(
					/* translators: %d: maximum number of characters. */
					__( 'Please use at most %d characters.', 'blocklane' ),
					$schema['maxlength']
				),
			);
		}

		return array(
			'value' => $value,
			'error' => '',
		);
	}

	/**
	 * The private upload root (uploads/blocklane-forms), created on demand
	 * with direct-access guards. Files here are reachable only through the
	 * capability-checked download route — never by URL (random names are the
	 * nginx-side backstop where .htaccess is ignored).
	 *
	 * A root whose guard files could not be written is REFUSED, not handed
	 * out (#1845): on Apache the .htaccess is the only thing between a
	 * visitor's file and its URL, so a store without it must not take one.
	 * Whether a guard is already in place is the uploads door's question,
	 * not a file_exists() here (#1860): File_Ops::uploads_ensure() refuses a
	 * guard that is a link (to anything, inside uploads or out) or a folder,
	 * rewrites one holding other bytes, and leaves one holding ours alone;
	 * File_Ops::uploads_dir() refuses a root that is a link or not a folder.
	 *
	 * @return string|\WP_Error Absolute path without trailing slash, or why
	 *                          the store cannot take a file now.
	 */
	function blocklane_pro_forms_upload_root(): string|\WP_Error {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return new \WP_Error( 'blocklane_pro_forms_uploads_unavailable', 'The uploads directory is unavailable: ' . (string) $uploads['error'] );
		}
		$root = $uploads['basedir'] . '/blocklane-forms';
		$made = \blocklane_pro\File_Ops::uploads_dir( $root );
		if ( is_wp_error( $made ) ) {
			return new \WP_Error( 'blocklane_pro_forms_root_uncreatable', 'The form upload store is not a folder it can use, so it takes no file: ' . $made->get_error_message() );
		}

		// Guard files in our own upload folder, through the uploads door,
		// which decides whether each is already in place.
		$guards = array(
			'.htaccess' => "# Blocklane Forms uploads — served only via the plugin.\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'index.php' => "<?php // Silence is golden.\n",
		);
		foreach ( $guards as $name => $body ) {
			$written = \blocklane_pro\File_Ops::uploads_ensure( $root . '/' . $name, $body );
			if ( is_wp_error( $written ) ) {
				return new \WP_Error( 'blocklane_pro_forms_unguarded', 'The form upload store has no ' . $name . ' guard, so it takes no file: ' . $written->get_error_message() );
			}
		}

		return $root;
	}

	/**
	 * Resolve a stored upload's relative path to its absolute on-disk
	 * location, confined to the private upload root — the ONE traversal
	 * guard every consumer (download streaming, file deletion) goes
	 * through. realpath collapses traversal, so anything escaping the root
	 * loses the prefix and is refused.
	 *
	 * @param string $relative Path relative to uploads/blocklane-forms.
	 * @return string|false Absolute path of an existing file, or false.
	 */
	function blocklane_pro_forms_resolve_stored_file( $relative ) {
		if ( ! is_string( $relative ) || '' === $relative ) {
			return false;
		}
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return false;
		}
		$root = realpath( $uploads['basedir'] . '/blocklane-forms' );
		if ( false === $root ) {
			return false;
		}
		$path = realpath( $root . '/' . $relative );
		if ( false === $path || 0 !== strpos( $path, $root . DIRECTORY_SEPARATOR ) || ! is_file( $path ) ) {
			return false;
		}

		return $path;
	}

	/**
	 * Delete a set of stored upload paths (relative to the upload root), used
	 * by row deletion and the purge cron so files never outlive their row.
	 *
	 * @param array $stored Relative stored paths.
	 */
	function blocklane_pro_forms_delete_stored_files( $stored ) {
		foreach ( (array) $stored as $relative ) {
			$path = blocklane_pro_forms_resolve_stored_file( $relative );
			if ( false !== $path ) {
				wp_delete_file( $path );
			}
		}
	}

	/**
	 * Collect the stored file paths referenced by a fields snapshot (JSON or
	 * decoded), for cleanup on delete/purge.
	 *
	 * @param string|array $fields The row's fields column (or decoded array).
	 * @return string[] Relative stored paths.
	 */
	function blocklane_pro_forms_stored_paths( $fields ) {
		if ( is_string( $fields ) ) {
			$fields = json_decode( $fields, true );
		}
		$paths = array();
		foreach ( (array) $fields as $field ) {
			if ( ! is_array( $field ) || empty( $field['files'] ) || ! is_array( $field['files'] ) ) {
				continue;
			}
			foreach ( $field['files'] as $file ) {
				if ( is_array( $file ) && ! empty( $file['stored'] ) && is_string( $file['stored'] ) ) {
					$paths[] = $file['stored'];
				}
			}
		}

		return $paths;
	}

	/**
	 * The public submission handler.
	 *
	 * Bot-classified rejections (honeypot, time-trap, unknown form, a bound
	 * payload carrying a name its definition does not accept) return FAKE
	 * SUCCESS — bots learn nothing. Human validation failures return
	 * structured per-field errors. The rate limit returns an honest 429: a
	 * double-clicking human deserves the truth. An unsigned payload the
	 * live copies cannot decide (a page cached before a field was renamed,
	 * copies that disagree, more copies than the budget) gets an honest 409
	 * asking for a reload, and a form whose block type is not registered an
	 * honest 503, each once it has passed the bot traps (decision D2).
	 *
	 * Which settings decide a submission is blocklane_pro_forms_resolve()'s
	 * answer alone; the handler reaches a form's attributes, gates and unique
	 * fields only through it.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	function blocklane_pro_forms_handle_submission( \WP_REST_Request $request ): \WP_REST_Response {
		// A submission's answer is never cacheable: WordPress sends no-cache
		// headers on REST responses only for logged-in requests, and this
		// route is anonymous. POSTs are not cached by HTTP semantics; this
		// says so explicitly for every layer in front of the site (#743).
		nocache_headers();

		if ( ! blocklane_pro_forms_within_rate_limit() ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Too many submissions from your connection. Please wait a minute and try again.', 'blocklane' ),
				),
				429
			);
		}

		$params      = $request->get_body_params();
		$raw_form_id = isset( $params['_bl_form_id'] ) ? (string) $params['_bl_form_id'] : '';
		// A formId is generated nine characters long; one over 100 bytes is
		// no form. Refused on the RAW length, before sanitize_title() or any
		// lookup reads it (F5): fake success, no scan, nothing remembered.
		if ( strlen( $raw_form_id ) > 100 ) {
			return blocklane_pro_forms_success_response( blocklane_pro_forms_resolve_config( array() ) );
		}
		$form_id = '' !== $raw_form_id ? sanitize_title( $raw_form_id ) : '';

		// A multipart upload whose TOTAL size exceeds post_max_size is discarded
		// by PHP before the handler runs — $_POST and $_FILES arrive empty, so
		// the form id is gone. Without this guard the empty payload looks like an
		// unknown form and a real visitor whose files summed over the limit gets
		// the bot fake-success: a silently swallowed submission. The per-file cap
		// can't catch a total that only overflows in aggregate, so a request that
		// declared more than the limit and arrived empty gets an honest error.
		if ( '' === $form_id ) {
			$post_max       = wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) );
			$content_length = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
			if ( $post_max > 0 && $content_length > $post_max ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Your submission was too large to upload. Please attach smaller or fewer files and try again.', 'blocklane' ),
					),
					413
				);
			}
		}

		// The visitor's two statements, read once: the binding the page's
		// form carried (_bl_bind) and the page they were on (_bl_source).
		// resolve() decides what each is worth; the same source id feeds the
		// provenance block below, which judges it on its own terms.
		$binding     = isset( $params['_bl_bind'] ) && is_string( $params['_bl_bind'] ) ? $params['_bl_bind'] : '';
		$source_id   = isset( $params['_bl_source'] ) && is_scalar( $params['_bl_source'] ) ? absint( $params['_bl_source'] ) : 0;
		/**
		 * Widened on purpose: the stubs type every entry as ONE file's
		 * array{name: string, ...}, but a multi-file input (`field[]`) posts
		 * an array in each slot — the part counting below relies on that.
		 *
		 * @var array<string, mixed> $file_params
		 */
		$file_params = $request->get_file_params();
		$posted      = blocklane_pro_forms_posted_names( $params, $file_params );
		$resolved    = '' !== $form_id
			? blocklane_pro_forms_resolve( $form_id, $binding, $source_id, $posted )
			: new \WP_Error( 'blocklane_forms_unknown', 'No formId.' );
		if ( is_wp_error( $resolved ) ) {
			// Live copies exist and cannot decide this unsigned payload: the
			// page was cached before the form changed (or the copies disagree,
			// or there are more than the budget). A human gets the truth (D2) —
			// after the bot traps, so a bot still learns nothing.
			if ( 'blocklane_forms_stale' === $resolved->get_error_code() && ! blocklane_pro_forms_is_bot( $params ) ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'This form has changed since the page loaded. Please reload the page and try again.', 'blocklane' ),
					),
					409
				);
			}
			// The form block is not registered (the skew guard left it out
			// until the assets are rebuilt): no definition can be computed,
			// and a fake success would lose the submission (sweep-1005).
			if ( 'blocklane_forms_unavailable' === $resolved->get_error_code() && ! blocklane_pro_forms_is_bot( $params ) ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'This form is temporarily unavailable. Please try again later.', 'blocklane' ),
					),
					503
				);
			}
			// Unknown form (or a bot on a stale one): fake-success shaped
			// exactly like a no-override form's real success (resolved
			// defaults, not neutral attrs).
			return blocklane_pro_forms_success_response( blocklane_pro_forms_resolve_config( array() ) );
		}

		$form_attrs = $resolved['attrs'];

		// Availability gates (v3) run BEFORE the bot traps: closed-form
		// state is honest form policy, not bot detection, and evaluating it
		// first means a scheduled form never wastes a Cloudflare round-trip.
		// One loop for both verdicts: a bound submission carries its own
		// copy's gate, an unsigned one every live copy's distinct gate
		// (decision D-R3), and the closed message is the copy's own only
		// when attested. The first closed gate answers.
		$blocklane_closed = null;
		$blocklane_open   = false;
		foreach ( $resolved['gates'] as $blocklane_gate ) {
			$blocklane_availability = blocklane_pro_forms_availability( $blocklane_gate + array( 'closedMessage' => $form_attrs['closedMessage'] ?? '' ) );
			if ( $blocklane_availability['open'] ) {
				$blocklane_open = true;
			} elseif ( null === $blocklane_closed ) {
				$blocklane_closed = $blocklane_availability;
			}
		}
		if ( null !== $blocklane_closed ) {
			// An unsigned submission one copy's gate closed while another
			// copy's is open: during a cache window a reload is bound, but
			// under a render-time rewriter every submission is unsigned for
			// good (NP16), so the operator gets a line (S-R6; F09). A form
			// every copy closes (its own schedule ended, its own login
			// required) is the form's policy, not a failure: no line.
			if ( 'shared' === $resolved['resolution'] && $blocklane_open ) {
				blocklane_pro_log_failure( 'Blocklane: an unsigned submission to form "' . $form_id . '" was refused by the login or schedule gate of one of its live copies while another copy is open (live copies examined: ' . $resolved['copies'] . ').' );
			}
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $blocklane_closed['message'],
				),
				403
			);
		}

		// Effective config resolves LIVE on every submission (per-form
		// override → site default → built-in). Resolving here, once, means
		// every consumer downstream — the tamper/bot fake successes, the
		// storeSubmissions gate, the action payload the store/mail listeners
		// read, and the success payload — sees the same resolved values.
		$form_attrs = blocklane_pro_forms_resolve_config( $form_attrs );

		// Honeypot filled, or the time-trap token missing (no JS / sub-3s
		// submit): classify as bot, reveal nothing.
		if ( blocklane_pro_forms_is_bot( $params ) ) {
			return blocklane_pro_forms_success_response( $form_attrs );
		}

		$schema = $resolved['schema'];

		// Unknown field names = a tampered or replayed payload: fake success.
		// blocklane_pro_forms_unknown_names() is the one tamper test: a name
		// the resolved definition's schema holds is a field, a name on its
		// `ignored` list (a field of a suite block this edition does not
		// register, posted by a page cached under the other edition) is
		// dropped, anything else is unknown. Module meta rides the reserved
		// `_bl_` prefix and never counts (blocklane_pro_forms_posted_names()),
		// so a newer view.js's meta is never trapped. A KNOWN name whose
		// posted type differs from the schema is NOT tampering: when a file
		// field and a non-file field resolve to the same name (a last-wins
		// collision), a real browser posts BOTH a value and a file under that
		// name, and the validation loop below reads each field from its own
		// source. For a `bound` submission the binding attests the names, so
		// an unknown one is real tampering; a `shared` one was decided only
		// because the shared schema accepts every posted name.
		if ( array() !== blocklane_pro_forms_unknown_names( $resolved, $posted ) ) {
			return blocklane_pro_forms_success_response( $form_attrs );
		}

		// PHP registers only the first max_file_uploads parts in $_FILES and
		// SILENTLY drops the rest — no UPLOAD_ERR_* code, nothing. Per-field
		// caps allow several multi-file fields to sum past that ceiling, and a
		// fully-dropped optional field validates clean: a success that silently
		// lost files. PHP never registers MORE than the ceiling, so a count AT
		// it means the tail may already be gone — conservatively an honest
		// error, never a fake success with missing attachments. Parts are
		// counted the way PHP counts them — every posted slot per field,
		// including empty (UPLOAD_ERR_NO_FILE) ones — so the threshold aligns
		// with when PHP actually truncates. Parts posted for a field this
		// edition does not carry (its definition's `ignored` names) are counted too, on
		// purpose: PHP dropped them the same way, and an honest 400 beats a
		// fake success that lost files (#1021).
		$max_uploads = (int) ini_get( 'max_file_uploads' );
		if ( $max_uploads > 0 ) {
			$total_parts = 0;
			foreach ( $file_params as $entry ) {
				if ( ! is_array( $entry ) || ! isset( $entry['name'] ) ) {
					continue;
				}
				$total_parts += is_array( $entry['name'] ) ? count( $entry['name'] ) : 1;
			}
			if ( $total_parts >= $max_uploads ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'You attached too many files. Please attach fewer files and try again.', 'blocklane' ),
					),
					400
				);
			}
		}

		// Turnstile (v1.1) — after the free checks (no Cloudflare round-trip
		// for payloads already classified as bots) and before validation.
		// Honest failure, unlike the silent traps: tokens expire in 300s, so
		// a slow-but-real visitor must get a retry, never a swallowed submit.
		$turnstile = blocklane_pro_forms_turnstile();
		if ( $turnstile['active'] ) {
			$token   = isset( $params['cf-turnstile-response'] ) ? trim( (string) $params['cf-turnstile-response'] ) : '';
			$verdict = blocklane_pro_forms_turnstile_verify( $token, $turnstile['secret_key'], blocklane_pro_forms_turnstile_action( $form_id ) );

			// First-failure detector: a config-broken verify fails open
			// silently for the visitor, so record it for the dashboard
			// banner. Any verdict where Cloudflare actually judged the token
			// (verified, rejected, action-mismatch) proves the config healthy
			// and clears the flag; missing-token proves nothing.
			if ( in_array( $verdict['reason'], array( 'misconfigured', 'unreachable' ), true ) ) {
				blocklane_pro_forms_turnstile_flag_broken( $verdict['reason'] );
			} elseif ( in_array( $verdict['reason'], array( 'verified', 'rejected', 'action-mismatch' ), true ) ) {
				blocklane_pro_forms_turnstile_clear_broken();
			}

			if ( empty( $verdict['pass'] ) ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'We could not confirm you are human. Please complete the verification and try again.', 'blocklane' ),
					),
					400
				);
			}
		}

		// Conditional visibility (v3): fields hidden by their rules are
		// EXCLUDED wholesale — required waived, posted value discarded (a
		// hidden field must not smuggle data past its own invisibility),
		// nothing stored or emailed. Same rule view.js applies live.
		$blocklane_hidden = blocklane_pro_forms_resolve_visibility( $schema, $params );

		$errors             = array();
		$fields             = array();
		$blocklane_registry = blocklane_pro_forms_field_type_registry();
		foreach ( $schema as $name => $field_schema ) {
			if ( isset( $blocklane_hidden[ $name ] ) ) {
				continue;
			}
			if ( 'hidden' === $field_schema['type'] ) {
				// Hidden values are authored, not visitor input: the block's
				// value is authoritative, the posted one is ignored.
				$fields[] = array(
					'name'  => $name,
					'label' => '' !== $field_schema['label'] ? $field_schema['label'] : $name,
					'type'  => 'hidden',
					'value' => sanitize_text_field( $field_schema['value'] ),
				);
				continue;
			}

			// A registered type that posts FILES (the file field) validates
			// its $_FILES entry instead of a posted value; its validated
			// uploads ride as `pending` until the persist step.
			$blocklane_ext = isset( $field_schema['ext'], $blocklane_registry[ $field_schema['ext'] ] ) ? $blocklane_registry[ $field_schema['ext'] ] : null;
			if ( null !== $blocklane_ext && 'files' === $blocklane_ext['source'] ) {
				$result = blocklane_pro_forms_validate_field( isset( $file_params[ $name ] ) ? $file_params[ $name ] : array(), $field_schema );
				if ( '' !== $result['error'] ) {
					$errors[ $name ] = $result['error'];
					continue;
				}
				$fields[] = array(
					'name'    => $name,
					'label'   => '' !== $field_schema['label'] ? $field_schema['label'] : $name,
					'type'    => $field_schema['type'],
					'value'   => $result['value'],
					// The type that owns the row: persist and discard handlers
					// claim rows by it (the seam contract above), never by type.
					'ext'     => $field_schema['ext'],
					'pending' => isset( $result['pending'] ) && is_array( $result['pending'] ) ? $result['pending'] : array(),
				);
				continue;
			}

			$raw    = isset( $params[ $name ] ) ? $params[ $name ] : '';
			$result = blocklane_pro_forms_validate_field( $raw, $field_schema );
			if ( '' !== $result['error'] ) {
				$errors[ $name ] = $result['error'];
				continue;
			}
			$fields[] = array(
				'name'  => $name,
				'label' => '' !== $field_schema['label'] ? $field_schema['label'] : $name,
				'type'  => $field_schema['type'],
				'value' => $result['value'],
			);
		}

		if ( $errors ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Please correct the highlighted fields.', 'blocklane' ),
					'errors'  => $errors,
				),
				400
			);
		}

		// Test mode (spec forms.md v4 TM-B): decided by resolve() from the
		// STORED copies alone, strictly true; no request input reaches it.
		// From here a test submission writes nothing: the unique probe below
		// reads without claiming a lock, uploads are discarded, and it
		// returns before the shared-routing line and the submission action.
		$blocklane_test = true === $resolved['test'];

		// Unique entry (v3): one submission per distinct value of the chosen
		// field. Runs on VALIDATED values, and only when storage is on —
		// without rows there is nothing to compare against (the editor
		// surfaces that combination). Honest rejection on the field, like
		// any validation error: the duplicate submitter is a human. The
		// fields come from resolve() alone: the bound copy's own, or the one
		// every live copy names (S-R1) — none when they do not agree.
		$blocklane_unique_store = ! isset( $form_attrs['storeSubmissions'] ) || false !== $form_attrs['storeSubmissions'];
		foreach ( $blocklane_unique_store && function_exists( 'blocklane_pro_forms_is_duplicate_entry' ) ? $resolved['unique'] : array() as $blocklane_unique_field ) {
			$blocklane_unique_field = sanitize_text_field( $blocklane_unique_field );
			$blocklane_unique_value = '';
			foreach ( $fields as $blocklane_unique_probe ) {
				// The editor stopped OFFERING hidden fields and checkbox
				// groups, but an attribute stored before that — or hand-edited
				// — still arrives here. A hidden field's value is the same
				// authored constant for everyone, so "one entry per" it accepts
				// exactly one submission ever and rejects the form forever
				// after. Degrade to gate-off instead of bricking the form.
				if ( isset( $blocklane_unique_probe['type'] ) && 'hidden' === $blocklane_unique_probe['type'] ) {
					continue;
				}
				if ( $blocklane_unique_probe['name'] === $blocklane_unique_field && is_string( $blocklane_unique_probe['value'] ) ) {
					$blocklane_unique_value = trim( $blocklane_unique_probe['value'] );
					break;
				}
			}
			if ( '' === $blocklane_unique_value ) {
				continue;
			}
			// Hold the value for the rest of this request: the probe below reads,
			// but the row is written later by the storage listener, so without
			// this two simultaneous submissions of the same value both pass.
			// A lock we cannot take means someone else is mid-flight with this
			// exact value — treat that as the duplicate it is about to become.
			// A test submission takes no lock (a write): null is the
			// lock's own "could not operate" answer, which leaves the
			// read-only probe to decide.
			$blocklane_unique_lock = $blocklane_test ? null : blocklane_pro_forms_claim_unique_lock( $form_id, $blocklane_unique_field, $blocklane_unique_value );

			// Released at shutdown rather than at any one exit point: by
			// then the row is stored, so the probe itself catches the
			// duplicate and the lock has done its job. Covers every path
			// out of the handler, including a fatal.
			if ( is_string( $blocklane_unique_lock ) && '' !== $blocklane_unique_lock ) {
				$blocklane_unique_lock_key = $blocklane_unique_lock;
				// Priority -10: the lock's job is covering the probe-to-store
				// window, which is over well before shutdown. Releasing
				// ahead of the notification flush (priority 0) means a
				// fatal while sending mail cannot strand it for 30s.
				add_action(
					'shutdown',
					static function () use ( $blocklane_unique_lock_key ) {
						delete_option( $blocklane_unique_lock_key );
					},
					-10
				);
			}

			// Only a LIVE holder rejects. null means the lock could not be
			// operated at all — fail open and let the probe decide, rather
			// than turn a database hiccup into a rejected form.
			if ( false === $blocklane_unique_lock || blocklane_pro_forms_is_duplicate_entry( $form_id, $blocklane_unique_field, $blocklane_unique_value ) ) {
				if ( is_string( $blocklane_unique_lock ) && '' !== $blocklane_unique_lock ) {
					delete_option( $blocklane_unique_lock );
				}
				$blocklane_unique_message = isset( $form_attrs['uniqueMessage'] ) && is_string( $form_attrs['uniqueMessage'] ) && '' !== trim( $form_attrs['uniqueMessage'] )
					? sanitize_text_field( $form_attrs['uniqueMessage'] )
					: __( 'You have already submitted this form.', 'blocklane' );

				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => $blocklane_unique_message,
						'errors'  => array( $blocklane_unique_field => $blocklane_unique_message ),
					),
					400
				);
			}
		}

		// Uploads persist only now — after the WHOLE submission validated —
		// and only when the row will exist to reference them: with storage
		// opted out a kept file would be an orphan nothing can reach, so the
		// names travel in the emails and the bytes are discarded (the editor
		// surfaces this on the field).
		if ( $blocklane_test || ( isset( $form_attrs['storeSubmissions'] ) && false === $form_attrs['storeSubmissions'] ) ) {
			blocklane_pro_forms_discard_pending( $fields );
		} else {
			$blocklane_persisted = blocklane_pro_forms_persist_pending( $fields );
			if ( is_wp_error( $blocklane_persisted ) ) {
				// The refusing type's slug, for the operator: the visitor's
				// message cannot name it, and without it a 500 says only that
				// some upload failed. Earlier handlers' bytes are already
				// reaped by persist_pending; nothing is stored or mailed.
				blocklane_pro_log( 'Blocklane: a form submission was refused — the field type ' . $blocklane_persisted->get_error_message() . ' could not persist its uploads.' );

				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'We could not save your upload. Please try again.', 'blocklane' ),
					),
					500
				);
			}
		}

		if ( $blocklane_test ) {
			/**
			 * A validated submission to a form in test mode, which was not
			 * kept: no row, no mail, no auto-response, no integration (the
			 * blocklane_pro_forms_submission action did not fire). Carries no
			 * field value (spec forms.md v4 TM-B).
			 *
			 * @param string $form_id   Form id.
			 * @param int    $source_id The post holding the bound copy; 0 for a shared submission.
			 */
			do_action( 'blocklane_pro_forms_test_submission', $form_id, $resolved['source_id'] );
			return blocklane_pro_forms_test_answer( $form_attrs );
		}

		$reply_to = '';
		foreach ( $schema as $name => $field_schema ) {
			if ( ! empty( $field_schema['isReplyTo'] ) ) {
				foreach ( $fields as $field ) {
					if ( $field['name'] === $name && is_email( $field['value'] ) ) {
						$reply_to = $field['value'];
					}
				}
			}
		}

		// Lead provenance (v2): the page the visitor was VIEWING — which
		// source_id can't tell (the holder of a synced-pattern or popup form,
		// and 0 for a shared submission). resolve() read _bl_source only as
		// the first post to examine; here the same value is PROVENANCE —
		// worst case a bot misreports its own lead's origin — and it is
		// verified by the same `_bl_source` door,
		// blocklane_pro_forms_source_post() (#2030): a publicly viewable post
		// with no password, or dropped. Publicly viewable, not just status
		// 'publish': internal types (wp_block, wp_navigation, templates…)
		// also carry 'publish', and a bot could inject their names into the
		// inbox origin filter; render.php only stamps singular views, so a
		// genuine origin is always a viewable post. The URL and referrer
		// carry the campaign signal (UTMs) a post id can't; client-supplied,
		// so they are sanitized, length-capped display metadata — never logic.
		$origin_post = blocklane_pro_forms_source_post( $source_id );
		$origin_id   = $origin_post ? (int) $origin_post->ID : 0;

		// The origin URL renders as a LINK in the inbox, labeled by the
		// VERIFIED origin title — an attacker URL under that trusted label
		// would be a phishing primitive. Leads come from this site's own
		// pages by definition, so anything cross-host is dropped
		// (filterable for legitimate multi-host front ends). The referrer
		// is cross-host by nature and only ever rendered as text.
		$origin_url = isset( $params['_bl_url'] ) ? esc_url_raw( substr( (string) $params['_bl_url'], 0, 2000 ) ) : '';
		if ( '' !== $origin_url ) {
			$origin_url_host = strtolower( (string) wp_parse_url( $origin_url, PHP_URL_HOST ) );
			/**
			 * Filter the hosts an origin URL may claim (default: the site's own).
			 *
			 * @param string[] $hosts Allowed hosts, compared case-insensitively.
			 */
			$origin_hosts = (array) apply_filters( 'blocklane_pro_forms_origin_hosts', array( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
			if ( ! in_array( $origin_url_host, array_map( 'strtolower', array_map( 'strval', $origin_hosts ) ), true ) ) {
				$origin_url = '';
			}
		}
		$origin = array(
			'url'      => $origin_url,
			'referrer' => isset( $params['_bl_referrer'] ) ? esc_url_raw( substr( (string) $params['_bl_referrer'], 0, 2000 ) ) : '',
			'title'    => $origin_id ? sanitize_text_field( wp_strip_all_tags( get_the_title( $origin_id ) ) ) : '',
			'type'     => $origin_id ? (string) get_post_type( $origin_id ) : '',
		);

		// A submission the route could not tie to the copy its page showed
		// is delivered to the site defaults; say so for the operator
		// (decision D5: the inbox's source_id 0 marks the row), with how many
		// live copies decided it (S-R6). The door throttles per message, so
		// this is one line a day per form for each copy count (F08).
		if ( 'shared' === $resolved['resolution'] ) {
			blocklane_pro_log_failure( 'Blocklane: a submission to form "' . $form_id . '" could not be matched to the copy its page showed; it went to the site default recipients (live copies examined: ' . $resolved['copies'] . ').' );
		}

		/**
		 * A validated submission. Storage (Phase 3) and email (Phase 3) hook
		 * here — the pipeline seam, Form Block's `form_block_submit_data`
		 * lesson kept as an action.
		 *
		 * @param array $submission {
		 *     @type string $form_id    Form id.
		 *     @type string $form_name  Form name attribute.
		 *     @type int    $source_id  The post holding the bound copy; 0 for a
		 *                              shared submission (no copy is the copy).
		 *     @type string $resolution How the settings were decided: 'bound' (the
		 *                              binding verified a live copy, whose own
		 *                              settings apply) or 'shared' (every live
		 *                              copy decided it; routed by the site
		 *                              defaults).
		 *     @type int    $origin_id  Viewing page id (publicly viewable, 0 when
		 *                              unknown).
		 *     @type array  $origin     Provenance snapshot { url, referrer, title, type }.
		 *     @type array  $fields     array of { name, label, type, value }.
		 *     @type string $reply_to   Validated reply-to email ('' when none).
		 *     @type array  $attrs      The decided attributes, config-resolved; no
		 *                              gate or unique-field key (spec NP22).
		 * }
		 */
		do_action(
			'blocklane_pro_forms_submission',
			array(
				'form_id'    => $form_id,
				'form_name'  => isset( $form_attrs['formName'] ) ? sanitize_text_field( (string) $form_attrs['formName'] ) : '',
				'source_id'  => $resolved['source_id'],
				'resolution' => $resolved['resolution'],
				'origin_id'  => $origin_id,
				'origin'     => $origin,
				'fields'     => $fields,
				'reply_to'   => $reply_to,
				'attrs'      => $form_attrs,
			)
		);

		return blocklane_pro_forms_success_response( $form_attrs );
	}

	/*
	 * ------------------------------------------------------------------
	 * Storage + email (Phase 3) — listeners on the submission seam.
	 * One custom table, self-describing rows (each row snapshots its
	 * fields as { name, label, type, value } JSON, so the inbox renders
	 * labels correctly even after the form is edited). Deliberately not
	 * Gutena's four-table spread.
	 * ------------------------------------------------------------------
	 */

	/** Bump when the CREATE TABLE statement changes (dbDelta reconciles). */
	define( 'BLOCKLANE_PRO_FORMS_DB_VERSION', '3' );

	/**
	 * The submissions table name.
	 *
	 * @return string
	 */
	function blocklane_pro_forms_table() {
		global $wpdb;

		return $wpdb->prefix . 'blocklane_form_submissions';
	}

	/**
	 * Create/upgrade the submissions table when the stored schema version is
	 * behind. Runs on admin_init (cheap version check) and lazily from the
	 * storage listener, so front-only sites (and the future bake) still get
	 * the table on first submission.
	 *
	 * @return bool Whether the table exists.
	 */
	function blocklane_pro_forms_ensure_table() {
		// Once-per-request guard: the version option is read on admin_init AND
		// from every stored submission, inbox call, and ability — a static
		// short-circuit keeps that to one option read per request.
		static $ensured = false;
		if ( $ensured ) {
			return true;
		}
		if ( BLOCKLANE_PRO_FORMS_DB_VERSION === get_option( 'blocklane_pro_forms_db_version' ) ) {
			$ensured = true;
			return true;
		}
		global $wpdb;

		$table           = blocklane_pro_forms_table();
		$charset_collate = $wpdb->get_charset_collate();

		// search_text is a values-only projection of the fields snapshot, so
		// inbox search matches what visitors typed — not the JSON structural
		// tokens ("label", "value", "email"…) present in every row.
		// origin_id/origin (v2) are lead provenance: the page the visitor was
		// VIEWING, distinct from source_id (the holder of the resolved definition — the
		// security anchor, which for a synced-pattern form is the same shared
		// wp_block whatever page converted). origin is a self-describing JSON
		// snapshot { url, referrer, title, type } taken at store time, so the
		// row keeps rendering after the origin page is renamed or deleted —
		// the same doctrine as the fields snapshot.
		// Neither admin_init nor a visitor's submission loads dbDelta().
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE {$table} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	form_id varchar(64) NOT NULL DEFAULT '',
	form_name varchar(191) NOT NULL DEFAULT '',
	source_id bigint(20) unsigned NOT NULL DEFAULT 0,
	origin_id bigint(20) unsigned NOT NULL DEFAULT 0,
	origin longtext NULL,
	fields longtext NOT NULL,
	search_text longtext NULL,
	status varchar(20) NOT NULL DEFAULT 'unread',
	created_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY form_id (form_id),
	KEY origin_id (origin_id),
	KEY status (status),
	KEY created_at (created_at)
) {$charset_collate};"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		if ( ! $exists ) {
			return false;
		}

		// dbDelta covers NEW installs (its CREATE path). Its ALTER reconcile
		// is NOT trusted for upgrades: on the SQLite integration's STRICT
		// tables it dropped the v3 ADD COLUMNs on the floor while SHOW TABLES
		// still passed — which would stamp the version over a broken table
		// and silently lose every stored row to a failing INSERT. Upgrades
		// add EVERY post-v1 column explicitly (engine-agnostic statements;
		// both engines answer SHOW COLUMNS/SHOW INDEX) — search_text (v2)
		// included, because a v1-era table relying on the reconcile would
		// otherwise fail the verify below forever. The index is guarded on
		// its OWN existence, not the column's, so a CREATE INDEX failure
		// after the column landed retries instead of being stamped over.
		// The version is stamped ONLY once every expected column and index
		// actually exists — a failed migration retries on the next request
		// instead of declaring itself done.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- a schema reconcile on the plugin's own table; core has no API for one, and by definition it cannot be cached.
		$columns = wp_list_pluck( (array) $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) ), 'Field' );
		if ( $columns && ! in_array( 'search_text', $columns, true ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN search_text longtext NULL', $table ) );
		}
		if ( $columns && ! in_array( 'origin_id', $columns, true ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN origin_id bigint(20) unsigned NOT NULL DEFAULT 0', $table ) );
		}
		if ( $columns && ! in_array( 'origin', $columns, true ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN origin longtext NULL', $table ) );
		}
		$indexes = wp_list_pluck( (array) $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i', $table ) ), 'Key_name' );
		if ( $columns && ! in_array( 'origin_id', $indexes, true ) ) {
			$wpdb->query( $wpdb->prepare( 'CREATE INDEX origin_id ON %i (origin_id)', $table ) );
		}

		$columns  = wp_list_pluck( (array) $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) ), 'Field' );
		$indexes  = wp_list_pluck( (array) $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i', $table ) ), 'Key_name' );
		// phpcs:enable
		$expected = array( 'id', 'form_id', 'form_name', 'source_id', 'origin_id', 'origin', 'fields', 'search_text', 'status', 'created_at' );
		if ( array_diff( $expected, $columns ) || ! in_array( 'origin_id', $indexes, true ) ) {
			return false;
		}

		update_option( 'blocklane_pro_forms_db_version', BLOCKLANE_PRO_FORMS_DB_VERSION, false );
		$ensured = true;

		return true;
	}
	add_action( 'admin_init', 'blocklane_pro_forms_ensure_table' );

	/**
	 * Store a validated submission (the inbox row — the safety net when
	 * email fails). Opt-out via the form's storeSubmissions attribute.
	 *
	 * @param array $submission Submission payload (see the action doc).
	 */
	function blocklane_pro_forms_store_submission( $submission ) {
		$attrs = isset( $submission['attrs'] ) && is_array( $submission['attrs'] ) ? $submission['attrs'] : array();
		if ( isset( $attrs['storeSubmissions'] ) && false === $attrs['storeSubmissions'] ) {
			return;
		}
		if ( ! blocklane_pro_forms_ensure_table() ) {
			return;
		}
		global $wpdb;

		// Values only (skip hidden), space-joined — what the visitor typed,
		// for the inbox search column.
		$search_parts = array();
		foreach ( (array) $submission['fields'] as $field ) {
			if ( ! is_array( $field ) || 'hidden' === ( isset( $field['type'] ) ? $field['type'] : '' ) ) {
				continue;
			}
			$value = blocklane_pro_forms_field_display_value( $field );
			if ( '' !== $value ) {
				$search_parts[] = $value;
			}
		}

		// The provenance snapshot stores only its non-empty values (NULL when
		// nothing was captured) — display metadata, lean by construction.
		$origin = isset( $submission['origin'] ) && is_array( $submission['origin'] )
			? array_filter( $submission['origin'], 'strlen' )
			: array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table insert.
		$wpdb->insert(
			blocklane_pro_forms_table(),
			array(
				'form_id'     => $submission['form_id'],
				'form_name'   => $submission['form_name'],
				'source_id'   => absint( $submission['source_id'] ),
				'origin_id'   => isset( $submission['origin_id'] ) ? absint( $submission['origin_id'] ) : 0,
				'origin'      => $origin ? wp_json_encode( $origin ) : null,
				'fields'      => wp_json_encode( $submission['fields'] ),
				'search_text' => implode( ' ', $search_parts ),
				'status'      => 'unread',
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}
	add_action( 'blocklane_pro_forms_submission', 'blocklane_pro_forms_store_submission', 10 );

	/**
	 * One field's value as display text (checkbox groups join with commas).
	 *
	 * @param array $field Self-described field.
	 * @return string
	 */
	function blocklane_pro_forms_field_display_value( $field ) {
		$value = isset( $field['value'] ) ? $field['value'] : '';

		return is_array( $value ) ? implode( ', ', $value ) : (string) $value;
	}

	/**
	 * The HTML rows for a submission's fields (shared by both emails).
	 *
	 * @param array $fields Self-described fields.
	 * @return string Escaped HTML.
	 */
	function blocklane_pro_forms_email_rows( $fields ) {
		$rows = '';
		foreach ( (array) $fields as $field ) {
			$value = esc_html( blocklane_pro_forms_field_display_value( $field ) );
			if ( isset( $field['type'] ) && 'textarea' === $field['type'] ) {
				$value = nl2br( $value );
			}
			$rows .= sprintf(
				'<p style="margin:0 0 12px;"><strong>%s</strong><br />%s</p>',
				esc_html( isset( $field['label'] ) ? $field['label'] : '' ),
				$value
			);
		}

		return $rows;
	}

	/**
	 * The From display name for outgoing mail. The ADDRESS is deliberately
	 * left to wp_mail's default (the site's own domain — never the
	 * submitter's address; both reference plugins agree, deliverability
	 * demands it). Only the display name is dressed.
	 *
	 * @return string
	 */
	function blocklane_pro_forms_from_name() {
		$settings = blocklane_pro_forms_settings();
		$name     = isset( $settings['email_from_name'] ) ? trim( (string) $settings['email_from_name'] ) : '';
		if ( '' === $name ) {
			$name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		}

		/**
		 * Filter the From display name on form emails.
		 *
		 * @param string $name Display name (default: the setting, else site title).
		 */
		return apply_filters( 'blocklane_pro_forms_from_name', $name );
	}

	/**
	 * The From ADDRESS on form emails (v3 sender identity). Header-level
	 * only — transport (SMTP/API mailer) stays a per-site decision. Empty
	 * setting = WordPress's default From, exactly as before.
	 *
	 * @return string Address, '' to leave WordPress's default alone.
	 */
	function blocklane_pro_forms_from_address() {
		$settings = blocklane_pro_forms_settings();
		$address  = isset( $settings['email_from_address'] ) ? sanitize_email( (string) $settings['email_from_address'] ) : '';

		/**
		 * Filter the From address on form emails ('' = WordPress default).
		 *
		 * @param string $address Address.
		 */
		$address = apply_filters( 'blocklane_pro_forms_from_address', $address );

		return is_email( $address ) ? $address : '';
	}

	/**
	 * The "form emails are failing" flag — the Turnstile broken flag's
	 * pattern applied to mail. Real traffic is the detector (no polling):
	 * the first form email wp_mail refuses raises it (with the mailer's
	 * error message for the banner), and a successful send on the SAME
	 * channel clears it — so the Forms screen can say "email is failing,
	 * submissions are safe in the inbox" instead of the site owner
	 * discovering it weeks later. The flag is kept PER CHANNEL ('admin' /
	 * 'auto-responder') because the shutdown flush sends both in fixed
	 * order: one shared bit would be last-send-wins — a persistently
	 * failing admin notification masked by a succeeding auto-responder
	 * (the exact silent failure the detector exists to surface), or a
	 * visitor-supplied reply-to the mailer refuses smearing "broken" over
	 * a healthy admin channel. The week-long expiry is only a backstop
	 * for sites that never send again.
	 *
	 * @param string $channel 'admin' or 'auto-responder'.
	 * @param string $message The wp_mail_failed error message ('' when the
	 *                        mailer gave no detail).
	 */
	function blocklane_pro_forms_mail_flag_broken( $channel, $message ) {
		$broken = blocklane_pro_forms_mail_broken();
		$broken = is_array( $broken ) ? $broken : array();

		$broken[ (string) $channel ] = array(
			'message' => sanitize_text_field( (string) $message ),
			'time'    => time(),
		);
		set_transient( 'blocklane_pro_forms_mail_broken', $broken, WEEK_IN_SECONDS );
	}

	/**
	 * Clear the mail-broken flag for one channel — a successful send
	 * proves that channel's path — or entirely when the channel is ''.
	 * The settings save also clears a channel the owner turns OFF
	 * site-wide: with no sends left to clear it, a stale flag would pin
	 * the banner for its full backstop week.
	 *
	 * @param string $channel 'admin', 'auto-responder', or '' for all.
	 */
	function blocklane_pro_forms_mail_clear_broken( $channel = '' ) {
		if ( '' === $channel ) {
			delete_transient( 'blocklane_pro_forms_mail_broken' );
			return;
		}
		$broken = blocklane_pro_forms_mail_broken();
		if ( ! is_array( $broken ) || ! isset( $broken[ $channel ] ) ) {
			return;
		}
		unset( $broken[ $channel ] );
		if ( $broken ) {
			set_transient( 'blocklane_pro_forms_mail_broken', $broken, WEEK_IN_SECONDS );
		} else {
			delete_transient( 'blocklane_pro_forms_mail_broken' );
		}
	}

	/**
	 * The current mail-broken flag.
	 *
	 * @return array|false channel => { message, time }, false when healthy.
	 */
	function blocklane_pro_forms_mail_broken() {
		$broken = get_transient( 'blocklane_pro_forms_mail_broken' );
		if ( ! is_array( $broken ) || ! $broken ) {
			return false;
		}
		// A pre-channel flag (the first detector iteration) reads as the
		// admin channel — the owner-critical one it was built to watch.
		if ( isset( $broken['message'] ) ) {
			return array( 'admin' => $broken );
		}

		return $broken;
	}

	/**
	 * Send one HTML email with the module's From-name treatment.
	 *
	 * Every form email routes through here, so this is also where the
	 * mail-broken flag is kept honest: a refused send raises the channel's
	 * flag (capturing core's wp_mail_failed error for the banner), a
	 * successful send clears the same channel. Scoped to OUR sends on
	 * purpose — the banner claims form emails are failing, so another
	 * plugin's mail must not drive it.
	 *
	 * @param string|array $to      Recipient(s).
	 * @param string       $subject Subject.
	 * @param string       $body    Inner HTML.
	 * @param array        $headers Extra headers.
	 * @param string       $channel Flag channel: 'admin' or 'auto-responder'.
	 * @return bool Whether wp_mail reported success.
	 */
	function blocklane_pro_forms_send_mail( $to, $subject, $body, $headers = array(), $channel = 'admin' ) {
		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		$mail_error = '';
		$capture    = function ( $error ) use ( &$mail_error ) {
			$mail_error = is_wp_error( $error ) ? $error->get_error_message() : '';
		};

		$from_name    = 'blocklane_pro_forms_from_name';
		$from_address = static function ( $default_from ) {
			$address = blocklane_pro_forms_from_address();

			return '' !== $address ? $address : $default_from;
		};
		add_filter( 'wp_mail_from_name', $from_name );
		add_filter( 'wp_mail_from', $from_address );
		add_action( 'wp_mail_failed', $capture );
		$sent = wp_mail( $to, $subject, $body, $headers );
		remove_action( 'wp_mail_failed', $capture );
		remove_filter( 'wp_mail_from', $from_address );
		remove_filter( 'wp_mail_from_name', $from_name );

		if ( $sent ) {
			blocklane_pro_forms_mail_clear_broken( $channel );
		} else {
			blocklane_pro_forms_mail_flag_broken( $channel, $mail_error );
		}

		return $sent;
	}

	/**
	 * Queue a validated submission's emails to send AFTER the HTTP response.
	 *
	 * Two reasons: the visitor shouldn't wait on SMTP, and sending inline made
	 * a genuine acceptance measurably slower than a bot-classified fake success
	 * (a timing oracle for the honeypot/time-trap). Storage still runs inline
	 * (priority 10) — it's the safety net and it's one fast INSERT — so the
	 * remaining timing delta is negligible.
	 *
	 * @param array $submission Submission payload.
	 */
	function blocklane_pro_forms_enqueue_mail( $submission ) {
		static $queue = null;

		if ( null === $queue ) {
			$queue = array();
			add_action( 'shutdown', 'blocklane_pro_forms_flush_mail', 0 );
		}
		$GLOBALS['blocklane_pro_forms_mail_queue'][] = $submission;
	}
	add_action( 'blocklane_pro_forms_submission', 'blocklane_pro_forms_enqueue_mail', 20 );

	/**
	 * Send the queued emails once the response is on its way. Flushes the
	 * client connection first when the SAPI supports it, so mail latency never
	 * reaches the visitor.
	 */
	function blocklane_pro_forms_flush_mail() {
		$queue = isset( $GLOBALS['blocklane_pro_forms_mail_queue'] ) ? (array) $GLOBALS['blocklane_pro_forms_mail_queue'] : array();
		if ( ! $queue ) {
			return;
		}
		$GLOBALS['blocklane_pro_forms_mail_queue'] = array();

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}

		foreach ( $queue as $submission ) {
			blocklane_pro_forms_notify_admin( $submission );
			blocklane_pro_forms_auto_respond( $submission );
		}
	}

	/**
	 * The admin notification email. Reply-To is the visitor's flagged email
	 * field, so "Reply" in the recipient's client just works.
	 *
	 * @param array $submission Submission payload.
	 */
	function blocklane_pro_forms_notify_admin( $submission ) {
		$attrs = isset( $submission['attrs'] ) && is_array( $submission['attrs'] ) ? $submission['attrs'] : array();
		if ( isset( $attrs['notifyAdmin'] ) && false === $attrs['notifyAdmin'] ) {
			return;
		}

		$recipients = array();
		if ( ! empty( $attrs['recipients'] ) ) {
			foreach ( explode( ',', (string) $attrs['recipients'] ) as $address ) {
				$address = sanitize_email( trim( $address ) );
				if ( $address && is_email( $address ) ) {
					$recipients[] = $address;
				}
			}
		}
		if ( ! $recipients ) {
			$recipients = array( get_option( 'admin_email' ) );
		}

		$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$form_name = '' !== $submission['form_name'] ? $submission['form_name'] : __( 'Form', 'blocklane' );
		$subject   = ! empty( $attrs['subject'] )
			? sanitize_text_field( (string) $attrs['subject'] )
			: sprintf(
				/* translators: 1: form name, 2: site title. */
				__( 'New submission: %1$s — %2$s', 'blocklane' ),
				$form_name,
				$site_name
			);

		$headers = array();
		// Re-validated at point of use: the pipeline only sets reply_to from
		// an is_email()-validated field, but the seam is a public action and
		// is_email() also rejects CR/LF (header injection).
		if ( '' !== $submission['reply_to'] && is_email( $submission['reply_to'] ) ) {
			$headers[] = 'Reply-To: ' . sanitize_email( $submission['reply_to'] );
		}

		$body = sprintf(
			'<h2 style="margin:0 0 16px;">%s</h2>%s<p style="margin:16px 0 0;color:#757575;font-size:12px;">%s</p>',
			esc_html( $subject ),
			blocklane_pro_forms_email_rows( $submission['fields'] ),
			esc_html(
				sprintf(
					/* translators: 1: form name, 2: site title. */
					__( 'Sent by the “%1$s” form on %2$s.', 'blocklane' ),
					$form_name,
					$site_name
				)
			)
		);

		if ( ! blocklane_pro_forms_send_mail( $recipients, $subject, $body, $headers, 'admin' ) ) {
			/**
			 * Fires when a form email fails to send. The stored row is the
			 * safety net; nothing is lost.
			 *
			 * @param string $which      'admin' or 'auto-responder'.
			 * @param array  $submission Submission payload.
			 */
			do_action( 'blocklane_pro_forms_mail_failed', 'admin', $submission );
		}
	}

	/**
	 * The auto-responder (opt-in per form, default off): a confirmation to
	 * the submitter's reply-to address, with the submitted values appended.
	 *
	 * @param array $submission Submission payload.
	 */
	function blocklane_pro_forms_auto_respond( $submission ) {
		$attrs = isset( $submission['attrs'] ) && is_array( $submission['attrs'] ) ? $submission['attrs'] : array();
		$auto  = isset( $attrs['autoResponder'] ) && is_array( $attrs['autoResponder'] ) ? $attrs['autoResponder'] : array();
		if ( empty( $auto['enabled'] ) || '' === $submission['reply_to'] || ! is_email( $submission['reply_to'] ) ) {
			return;
		}

		$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$subject   = ! empty( $auto['subject'] )
			? sanitize_text_field( (string) $auto['subject'] )
			: sprintf(
				/* translators: %s: site title. */
				__( 'We received your message — %s', 'blocklane' ),
				$site_name
			);

		$message = ! empty( $auto['message'] )
			? nl2br( esc_html( sanitize_textarea_field( (string) $auto['message'] ) ) )
			: esc_html__( 'Thanks for getting in touch! Here is a copy of what you sent — we will get back to you soon.', 'blocklane' );

		$body = sprintf(
			'<p style="margin:0 0 16px;">%s</p><hr style="border:0;border-top:1px solid #e7e7e7;margin:16px 0;" />%s',
			$message,
			blocklane_pro_forms_email_rows( $submission['fields'] )
		);

		if ( ! blocklane_pro_forms_send_mail( $submission['reply_to'], $subject, $body, array(), 'auto-responder' ) ) {
			do_action( 'blocklane_pro_forms_mail_failed', 'auto-responder', $submission );
		}
	}

	/**
	 * Keep the daily purge event scheduled only while a retention window is
	 * set (purge_days > 0). The default is 0 = keep forever, so a site that
	 * never sets retention carries no cron entry (and toggling back to 0
	 * clears it). Runs on admin_init off the settings the inbox writes.
	 */
	function blocklane_pro_forms_sync_purge_schedule() {
		$settings  = blocklane_pro_forms_settings();
		$days      = isset( $settings['purge_days'] ) ? absint( $settings['purge_days'] ) : 0;
		$scheduled = wp_next_scheduled( 'blocklane_pro_forms_purge' );

		if ( $days > 0 && ! $scheduled ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'blocklane_pro_forms_purge' );
		} elseif ( 0 === $days && $scheduled ) {
			wp_unschedule_event( $scheduled, 'blocklane_pro_forms_purge' );
		}
	}
	add_action( 'admin_init', 'blocklane_pro_forms_sync_purge_schedule' );

	/*
	 * ------------------------------------------------------------------
	 * Starter patterns (Phase 5). Deliberately WITHOUT a formId — the form
	 * edit component generates one on mount, so every insertion is unique
	 * by construction. Patterns replace the references' wizard/variation
	 * pickers: the form block's empty state offers these.
	 * ------------------------------------------------------------------
	 */

	/**
	 * The "Forms" pattern category. The three starter patterns (Form:
	 * Contact, Form: Newsletter signup, Form: RSVP) are bundled patterns now
	 * (inc/bundled-patterns/patterns/form-*.php, written by the theme repo's
	 * capture door), registered by the bundled-patterns registrar on init 20
	 * when their blocks are registered; they used to be inline PHP strings
	 * here, which no gate proved against the editor's bytes. The category
	 * they file under stays this module's: registered on init 10, before
	 * them, and only while the suite's blocks are whole.
	 */
	function blocklane_pro_forms_register_pattern_category(): void {
		if ( \blocklane_pro\Block_Suite::missing( 'forms' ) ) {
			return;
		}
		register_block_pattern_category(
			'blocklane-forms',
			array( 'label' => __( 'Forms', 'blocklane' ) )
		);
	}
	add_action( 'init', 'blocklane_pro_forms_register_pattern_category' );

	/**
	 * The purge handler. Works in bounded batches — the first purge of a
	 * long-lived site (or a purge_days drop) can doom tens of thousands of
	 * rows, and WP-Cron usually runs piggybacked on a front-end request, so
	 * neither the fields snapshots nor the path list may grow unbounded.
	 * Uploads die only with rows that actually died: each batch deletes by
	 * explicit id list and touches the files only when that DELETE succeeded
	 * (the same order Forms::delete keeps) — a failed query leaves the rows,
	 * and their files, for the next run.
	 */
	function blocklane_pro_forms_run_purge() {
		$settings = blocklane_pro_forms_settings();
		$days     = isset( $settings['purge_days'] ) ? absint( $settings['purge_days'] ) : 0;
		if ( ! $days || ! blocklane_pro_forms_ensure_table() ) {
			return;
		}
		global $wpdb;
		$table  = blocklane_pro_forms_table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$batch  = 200;

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own submissions table; a purge must read what is actually stored.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id, fields FROM %i WHERE created_at < %s ORDER BY id ASC LIMIT %d',
					$table,
					$cutoff,
					$batch
				),
				ARRAY_A
			);
			if ( ! $rows ) {
				break;
			}

			$ids = array_map( 'absint', wp_list_pluck( $rows, 'id' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own submissions table; core has no API for it.
			$deleted = $wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
					array_merge( array( $table ), $ids )
				)
			);
			if ( false === $deleted ) {
				break;
			}

			$stored = array();
			foreach ( $rows as $row ) {
				foreach ( blocklane_pro_forms_stored_paths( $row['fields'] ) as $path ) {
					$stored[] = $path;
				}
			}
			blocklane_pro_forms_delete_stored_files( $stored );
		} while ( count( $rows ) === $batch );
	}
	add_action( 'blocklane_pro_forms_purge', 'blocklane_pro_forms_run_purge' );
}
