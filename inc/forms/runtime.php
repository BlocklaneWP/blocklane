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
	 * The canonical form-input `type` whitelist — the single source shared by
	 * schema derivation and the field render (which each used to hand-copy it).
	 * Mirrors form-input/block.json's enum.
	 */
	define(
		'BLOCKLANE_PRO_FORMS_INPUT_TYPES',
		array( 'text', 'email', 'tel', 'url', 'number', 'date', 'hidden', 'checkbox' )
	);

	/**
	 * Upload type groups: the authoring surface is three toggles, not a raw
	 * accept string. ext => mime, aligned with wp_get_mime_types(). Extensible
	 * via the blocklane_pro_forms_file_types filter (see the constraints
	 * helper) — but never past the hard deny list below.
	 */
	define(
		'BLOCKLANE_PRO_FORMS_FILE_TYPES',
		array(
			'images'    => array(
				'jpg'  => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'png'  => 'image/png',
				'gif'  => 'image/gif',
				'webp' => 'image/webp',
			),
			'documents' => array(
				'pdf'  => 'application/pdf',
				'doc'  => 'application/msword',
				'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'xls'  => 'application/vnd.ms-excel',
				'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'ppt'  => 'application/vnd.ms-powerpoint',
				'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
				'odt'  => 'application/vnd.oasis.opendocument.text',
				'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
				'txt'  => 'text/plain',
				'rtf'  => 'application/rtf',
				'csv'  => 'text/csv',
			),
			'archives'  => array(
				'zip' => 'application/zip',
			),
		)
	);

	/**
	 * Extensions that are NEVER accepted, whatever the groups or the filter
	 * say — executable/markup surfaces (Form Block's CVE class). Deliberately
	 * NOT filterable.
	 */
	define(
		'BLOCKLANE_PRO_FORMS_FILE_DENY',
		array( 'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar', 'shtml', 'html', 'htm', 'xhtml', 'js', 'mjs', 'svg', 'svgz', 'xml', 'exe', 'com', 'bat', 'cmd', 'sh', 'cgi', 'pl', 'py', 'asp', 'aspx', 'jsp' )
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
			BLOCKLANE_PRO_FORMS_BLOCKS,
			'npm run build:forms',
			__( 'Forms', 'blocklane' ),
			__( 'Saved forms render without those blocks, and submissions silently drop the fields they carry, until the assets are rebuilt.', 'blocklane' )
		);
	}
	add_action( 'init', 'blocklane_pro_forms_register_blocks' );

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

		// The effective per-file ceiling, so the file field's canvas hint
		// shows exactly what render.php will enforce (1:1) — the same
		// helper file_constraints folds each field's own cap under.
		$forms_file_cap = blocklane_pro_forms_upload_ceiling();

		// The effective type map (blocklane_pro_forms_file_types applied,
		// deny-filtered) as group => { label, exts }, so the inspector's
		// Allowed-types toggles and the canvas hint reflect filter-added
		// groups instead of a hardcoded JS copy.
		/**
		 * Filter the editor labels for upload type groups. Groups without a
		 * label here fall back to a humanized key.
		 *
		 * @param array $labels group => label.
		 */
		$forms_type_labels = (array) apply_filters(
			'blocklane_pro_forms_file_type_labels',
			array(
				'images'    => __( 'Images', 'blocklane' ),
				'documents' => __( 'Documents', 'blocklane' ),
				'archives'  => __( 'Archives (zip)', 'blocklane' ),
			)
		);
		$forms_file_types = array();
		foreach ( blocklane_pro_forms_file_type_map() as $forms_group => $forms_exts ) {
			$forms_file_types[ $forms_group ] = array(
				'label' => isset( $forms_type_labels[ $forms_group ] )
					? (string) $forms_type_labels[ $forms_group ]
					: ucwords( str_replace( array( '-', '_' ), ' ', $forms_group ) ),
				'exts'  => array_keys( $forms_exts ),
			);
		}

		wp_add_inline_script(
			'blocklane-form-file-editor-script',
			'window.blocklaneProForms = window.blocklaneProForms || {};' .
			'window.blocklaneProForms.maxUpload = ' . $forms_file_cap . ';' .
			'window.blocklaneProForms.fileTypes = ' . wp_json_encode( $forms_file_types ) . ';',
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
	 * The common render preamble every labeled field control needs: the
	 * resolved name/id, the label, required state + asterisk, the wrapper
	 * attributes, and the skip-serialized control style. Extracted so the
	 * input/textarea/select renders stop hand-copying it.
	 *
	 * @param array    $attributes       Block attributes.
	 * @param WP_Block $block            Block instance (for formId context).
	 * @param string   $type_class       The is-type-* modifier for the wrapper.
	 * @param bool     $style_on_wrapper Merge the control style into the wrapper
	 *                                   instead (fields with no styleable control
	 *                                   — the consent checkbox styles its row).
	 *                                   The edit views mirror the same split.
	 * @return array
	 */
	function blocklane_pro_forms_field_render_base( $attributes, $block, $type_class, $style_on_wrapper = false ) {
		$form_id  = isset( $block->context['blocklane/formId'] ) ? (string) $block->context['blocklane/formId'] : '';
		$name     = blocklane_pro_forms_field_name( $attributes );
		$required = ! empty( $attributes['required'] );
		$style    = blocklane_pro_forms_control_style( $attributes );

		$wrapper_extra = array(
			'class' => 'blocklane-form__field ' . $type_class . ( $required ? ' is-required' : '' ),
		);
		// Conditional visibility (v3): the wrapper carries its own rule +
		// name so view.js can evaluate live with ZERO markup knowledge of
		// individual field types — one attribute from the one base builder,
		// mirroring the server resolver's rule exactly (the render↔schema
		// discipline applied to visibility).
		$blocklane_field_cond = blocklane_pro_forms_field_condition( $attributes );
		$blocklane_cond_attr  = '';
		if ( null !== $blocklane_field_cond ) {
			$wrapper_extra['data-bl-cond'] = wp_json_encode( $blocklane_field_cond );
			$wrapper_extra['data-bl-name'] = $name;

			// Also returned standalone, for the one render path that emits no
			// wrapper at all: a hidden input. Without it that field's rule was
			// server-enforced and invisible to view.js.
			$blocklane_cond_attr = sprintf(
				' data-bl-cond="%s" data-bl-name="%s"',
				esc_attr( (string) wp_json_encode( $blocklane_field_cond ) ),
				esc_attr( $name )
			);
		}
		if ( $style_on_wrapper && '' !== $style ) {
			$wrapper_extra['style'] = $style;
			$style                  = '';
		}

		return array(
			'form_id'       => $form_id,
			'name'          => $name,
			'id'            => blocklane_pro_forms_field_id( $form_id, $name ),
			'label'         => isset( $attributes['label'] ) ? (string) $attributes['label'] : '',
			'required'      => $required,
			'required_mark' => $required ? '<span class="blocklane-form__required" aria-hidden="true">*</span>' : '',
			// Hidden labels stay in the DOM for assistive tech — the class
			// clips them visually (style.scss); render + canvas mirror it.
			'label_class'   => 'blocklane-form__label' . ( ! empty( $attributes['hideLabel'] ) ? ' is-visually-hidden' : '' ),
			'style_attr'    => '' !== $style ? ' style="' . esc_attr( $style ) . '"' : '',
			'wrapper'       => get_block_wrapper_attributes( $wrapper_extra ),
			'cond_attr'     => $blocklane_cond_attr,
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
	 * @param array $inner_blocks Parsed inner blocks (parsed_block format).
	 * @return bool
	 */
	function blocklane_pro_forms_has_required( $inner_blocks ) {
		foreach ( (array) $inner_blocks as $inner ) {
			if ( ! is_array( $inner ) ) {
				continue;
			}
			$name = isset( $inner['blockName'] ) ? $inner['blockName'] : '';
			if ( in_array( $name, array( 'blocklane/form-input', 'blocklane/form-textarea', 'blocklane/form-select', 'blocklane/form-group', 'blocklane/form-file' ), true )
				&& ! empty( $inner['attrs']['required'] ) ) {
				return true;
			}
			if ( ! empty( $inner['innerBlocks'] ) && blocklane_pro_forms_has_required( $inner['innerBlocks'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The effective per-file upload ceiling in bytes: the server limit
	 * (wp_max_upload_size, 8 MB fallback) clamped by the site-wide cap from
	 * the Forms settings (0 = no site cap). file_constraints() folds this
	 * under each field's own max; the editor maxUpload bridge reads it
	 * directly — one helper, so the canvas hint and the enforced cap
	 * cannot drift. (Note file_constraints([]) is NOT this value: that is
	 * the per-field default cap, already folded under the ceiling.)
	 *
	 * @return int Bytes.
	 */
	function blocklane_pro_forms_upload_ceiling() {
		$ceiling = (int) wp_max_upload_size();
		$ceiling = $ceiling > 0 ? $ceiling : 8 * MB_IN_BYTES;

		$settings = blocklane_pro_forms_settings();
		$site_mb  = isset( $settings['max_upload_mb'] ) ? absint( $settings['max_upload_mb'] ) : 0;
		if ( $site_mb > 0 ) {
			$ceiling = min( $ceiling, $site_mb * MB_IN_BYTES );
		}

		return $ceiling;
	}

	/**
	 * The effective upload type groups: the filterable map with the deny
	 * list and extension shape enforced — the ONE source file_constraints
	 * AND the editor bridge read, so the inspector toggles, the canvas
	 * hint, and the enforced whitelist cannot diverge. Groups left with no
	 * surviving extension are dropped (a toggle that accepts nothing).
	 *
	 * @return array group => ext => mime.
	 */
	function blocklane_pro_forms_file_type_map() {
		/**
		 * Filter the upload type groups (group => ext => mime). Additions
		 * still pass through the non-filterable deny list, and they reach
		 * the editor's Allowed-types toggles via the editor_state bridge.
		 *
		 * @param array $types Group map (BLOCKLANE_PRO_FORMS_FILE_TYPES).
		 */
		$types = (array) apply_filters( 'blocklane_pro_forms_file_types', BLOCKLANE_PRO_FORMS_FILE_TYPES );

		$map = array();
		foreach ( $types as $group => $exts ) {
			if ( ! is_string( $group ) || ! is_array( $exts ) ) {
				continue;
			}
			$clean = array();
			foreach ( $exts as $ext => $mime ) {
				$ext = strtolower( (string) $ext );
				if ( preg_match( '/^[a-z0-9]{1,10}$/', $ext ) && ! in_array( $ext, BLOCKLANE_PRO_FORMS_FILE_DENY, true ) ) {
					$clean[ $ext ] = (string) $mime;
				}
			}
			if ( $clean ) {
				$map[ $group ] = $clean;
			}
		}

		return $map;
	}

	/**
	 * Resolve a file field's effective constraints from its attributes — the
	 * ONE source both render.php and schema derivation consume (the render↔
	 * schema invariant: a divergence here silently mis-validates uploads).
	 *
	 * @param array $attrs Block attributes (defaults merged).
	 * @return array {
	 *     @type array  $groups    Valid group keys.
	 *     @type array  $mimes     ext => mime whitelist (deny list stripped).
	 *     @type string $accept    The accept attribute value (".pdf,.jpg,…").
	 *     @type int    $max_size  Per-file cap in bytes (authored, server-capped).
	 *     @type int    $max_files Max file count (1 unless multiple).
	 *     @type bool   $multiple  Whether the input accepts several files.
	 * }
	 */
	function blocklane_pro_forms_file_constraints( $attrs ) {
		$types = blocklane_pro_forms_file_type_map();

		$groups = array();
		foreach ( (array) ( isset( $attrs['accept'] ) ? $attrs['accept'] : array() ) as $group ) {
			if ( is_string( $group ) && isset( $types[ $group ] ) ) {
				$groups[] = $group;
			}
		}
		if ( ! $groups ) {
			$groups = array_values( array_intersect( array( 'images', 'documents' ), array_keys( $types ) ) );
		}

		$mimes = array();
		foreach ( $groups as $group ) {
			foreach ( $types[ $group ] as $ext => $mime ) {
				$mimes[ $ext ] = $mime;
			}
		}

		$ceiling  = blocklane_pro_forms_upload_ceiling();
		$max_mb   = isset( $attrs['maxSize'] ) ? absint( $attrs['maxSize'] ) : 0;
		$max_size = $max_mb > 0 ? min( $max_mb * MB_IN_BYTES, $ceiling ) : min( 8 * MB_IN_BYTES, $ceiling );

		$multiple  = ! empty( $attrs['multiple'] );
		$max_files = $multiple ? min( 10, max( 1, isset( $attrs['maxFiles'] ) ? absint( $attrs['maxFiles'] ) : 3 ) ) : 1;

		return array(
			'groups'    => $groups,
			'mimes'     => $mimes,
			'accept'    => $mimes ? '.' . implode( ',.', array_keys( $mimes ) ) : '',
			'max_size'  => $max_size,
			'max_files' => $max_files,
			'multiple'  => $multiple,
		);
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
					'remoteip' => blocklane_pro_forms_client_ip(),
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
	 * The client IP for rate limiting. Hashed before use, never stored.
	 *
	 * @return string
	 */
	function blocklane_pro_forms_client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filter the client IP used for submission rate limiting (e.g. to
		 * read a trusted proxy header instead).
		 *
		 * @param string $ip Client IP.
		 */
		return apply_filters( 'blocklane_pro_forms_client_ip', $ip );
	}

	/**
	 * Sliding-window rate limit per hashed IP. Returns true while within the
	 * limit and counts the attempt.
	 *
	 * @return bool
	 */
	function blocklane_pro_forms_within_rate_limit() {
		$ip = blocklane_pro_forms_client_ip();
		if ( '' === $ip ) {
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

		$key   = 'blocklane_pro_forms_rl_' . md5( wp_salt( 'nonce' ) . $ip );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, $window );

		return true;
	}

	/**
	 * Locate the source document that literally CONTAINS a form and return its
	 * parsed form block. Resolution is deterministic by content — the formId is
	 * searched across published posts and the lowest-ID match (its true home)
	 * wins — and the viewing page (`_bl_source`) is deliberately NOT trusted:
	 * it is public and attacker-controllable, so honoring it would let anyone
	 * who can publish a post carrying the same formId + their own recipients
	 * hijack the form's submissions (and poison the shared location cache for
	 * every visitor). For a form living in a synced pattern or template part,
	 * only the wp_block/wp_template_part row contains the formId literal, so
	 * the deterministic search lands on it, not on any page embedding it.
	 *
	 * A form placed in a theme TEMPLATE FILE that was never customized has no
	 * database row and cannot be located — place forms in content or synced
	 * patterns.
	 *
	 * @param string $form_id Form id.
	 * @return array|null array( 'form' => parsed block, 'source_id' => int ) or null.
	 */
	function blocklane_pro_forms_locate_form( $form_id ) {
		$needle    = '"formId":"' . $form_id . '"';
		$cache_key = 'blocklane_pro_forms_loc_' . $form_id;

		// Negative cache: an unknown formId (spam against the public route)
		// must not run a full-table scan on every hit.
		$cached = get_transient( $cache_key );
		if ( 'none' === $cached ) {
			return null;
		}

		// Positive fast path: the cached post still hosts the form → return
		// without touching wp_posts. Revalidated so an edited/deleted source
		// falls through to a fresh search.
		if ( $cached ) {
			$post = get_post( absint( $cached ) );
			if ( $post && in_array( $post->post_status, array( 'publish', 'private' ), true )
				&& false !== strpos( $post->post_content, $needle ) ) {
				$form = blocklane_pro_forms_find_form_block( parse_blocks( $post->post_content ), $form_id );
				if ( $form ) {
					return array(
						'form'      => $form,
						'source_id' => (int) $post->ID,
					);
				}
			}
		}

		global $wpdb;
		$found = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- locate-by-content; transient caches both hits and misses.
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_status IN ( 'publish', 'private' )
				AND post_type NOT IN ( 'revision', 'auto-draft' )
				AND post_content LIKE %s
				ORDER BY ID ASC
				LIMIT 5",
				'%' . $wpdb->esc_like( $needle ) . '%'
			)
		);

		foreach ( array_map( 'absint', (array) $found ) as $candidate_id ) {
			$post = get_post( $candidate_id );
			if ( ! $post || false === strpos( $post->post_content, $needle ) ) {
				continue;
			}
			$form = blocklane_pro_forms_find_form_block( parse_blocks( $post->post_content ), $form_id );
			if ( $form ) {
				set_transient( $cache_key, $candidate_id, DAY_IN_SECONDS );

				return array(
					'form'      => $form,
					'source_id' => $candidate_id,
				);
			}
		}

		// Cache the miss briefly so a formId that matches nothing can't be used
		// to amplify full-table scans.
		set_transient( $cache_key, 'none', 5 * MINUTE_IN_SECONDS );

		return null;
	}

	/**
	 * Find a form block with a matching formId in a parsed block tree.
	 *
	 * @param array  $blocks  Parsed blocks.
	 * @param string $form_id Form id.
	 * @return array|null The parsed form block.
	 */
	function blocklane_pro_forms_find_form_block( $blocks, $form_id ) {
		foreach ( (array) $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( isset( $block['blockName'] ) && 'blocklane/form' === $block['blockName']
				&& isset( $block['attrs']['formId'] ) && $block['attrs']['formId'] === $form_id ) {
				return $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = blocklane_pro_forms_find_form_block( $block['innerBlocks'], $form_id );
				if ( $found ) {
					return $found;
				}
			}
		}

		return null;
	}

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
	 * @param array $form_block Parsed blocklane/form block.
	 * @return array Field schemas keyed by field name.
	 */
	function blocklane_pro_forms_derive_schema( $form_block ) {
		$fields = array();
		blocklane_pro_forms_collect_fields( isset( $form_block['innerBlocks'] ) ? $form_block['innerBlocks'] : array(), $fields );

		return $fields;
	}

	/**
	 * Registered EXTERNAL field types (v3 extension seam) — how a companion
	 * add-on plugs its field blocks into schema derivation + validation
	 * without editing core. Filter shape:
	 *
	 *   add_filter( 'blocklane_pro_forms_field_types', function ( $types ) {
	 *       $types['my-addon/my-field'] = array(
	 *           // Schema row for one parsed block, or null to skip it.
	 *           // MUST include 'name' (use blocklane_pro_forms_field_name()).
	 *           'collect'  => function ( array $attrs, array $block ) { … },
	 *           // Same contract as the internal validator:
	 *           // return array( 'value' => mixed, 'error' => string ).
	 *           'validate' => function ( $raw, array $schema ) { … },
	 *       );
	 *       return $types;
	 *   } );
	 *
	 * Internal `blocklane/` types never route through this (their invariants
	 * stay hardcoded), and the filter cannot override them. File-posting
	 * external types are NOT supported by this seam yet — external validate
	 * receives the posted VALUE only. Filters resolve at call time, so the
	 * runtime sees add-on registrations normally. Caveat an add-on must own:
	 * while it is deactivated its
	 * fields vanish from the schema, so a stale cached page posting them
	 * trips the unknown-field trap — the same exposure as removing any block.
	 *
	 * @return array<string, array{collect: callable, validate: callable}>
	 */
	function blocklane_pro_forms_field_type_registry() {
		$types    = apply_filters( 'blocklane_pro_forms_field_types', array() );
		$registry = array();
		foreach ( (array) $types as $slug => $handlers ) {
			if ( ! is_string( $slug ) || '' === $slug || 0 === strpos( $slug, 'blocklane/' ) ) {
				continue;
			}
			if ( ! is_array( $handlers )
				|| ! isset( $handlers['collect'], $handlers['validate'] )
				|| ! is_callable( $handlers['collect'] )
				|| ! is_callable( $handlers['validate'] ) ) {
				continue;
			}
			$registry[ $slug ] = $handlers;
		}

		return $registry;
	}

	/**
	 * Recursive field collector for schema derivation. Descends layout
	 * wrappers AND synced patterns (core/block refs) so fields moved into a
	 * reusable block — the spec's recommended reuse path — are still seen.
	 *
	 * @param array $blocks Parsed blocks.
	 * @param array $fields Collected schemas (by reference).
	 * @param int   $depth  Recursion guard for synced-pattern cycles.
	 */
	function blocklane_pro_forms_collect_fields( $blocks, array &$fields, $depth = 0 ) {
		$registry = blocklane_pro_forms_field_type_registry();

		foreach ( (array) $blocks as $block ) {
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
					// which only knows the five internal block names. Meanwhile
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

			// Conditions attach post-switch by key diff, so every internal
			// field case gains them without per-case wiring — but only for
			// LEAF field blocks: a core/block descent adds many fields that
			// must never inherit the wrapper's condition.
			$blocklane_is_field_block = in_array(
				$block['blockName'],
				array( 'blocklane/form-input', 'blocklane/form-textarea', 'blocklane/form-select', 'blocklane/form-group', 'blocklane/form-file' ),
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

				case 'blocklane/form-file':
					// Default fallback, NOT a custom one: field_render_base
					// resolves the same way — the two must never diverge.
					$name        = blocklane_pro_forms_field_name( $attrs );
					$constraints = blocklane_pro_forms_file_constraints( $attrs );

					$fields[ $name ] = array(
						'type'      => 'file',
						'label'     => wp_strip_all_tags( isset( $attrs['label'] ) ? (string) $attrs['label'] : '' ),
						'required'  => ! empty( $attrs['required'] ),
						'mimes'     => $constraints['mimes'],
						'max_size'  => $constraints['max_size'],
						'max_files' => $constraints['max_files'],
						'isReplyTo' => false,
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
					// so fields moved into a reusable block are still validated.
					if ( $depth < 5 && ! empty( $attrs['ref'] ) ) {
						$ref = get_post( absint( $attrs['ref'] ) );
						if ( $ref && 'wp_block' === $ref->post_type ) {
							blocklane_pro_forms_collect_fields( parse_blocks( $ref->post_content ), $fields, $depth + 1 );
						}
					}
					break;

				default:
					// Layout wrappers (group, columns…) may nest fields.
					if ( ! empty( $block['innerBlocks'] ) ) {
						blocklane_pro_forms_collect_fields( $block['innerBlocks'], $fields, $depth );
					}
			}

			// Attach by NAME, not by diffing the key set. Each of the five field
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
					return array(
						'value' => $result['value'],
						'error' => isset( $result['error'] ) ? (string) $result['error'] : '',
					);
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
	 * Normalize one $_FILES entry to a list of single-file arrays. PHP shapes
	 * `name[]` uploads as parallel arrays; a single input posts flat keys.
	 *
	 * @param array $entry One $_FILES member.
	 * @return array[] Each { name, type, tmp_name, error, size }.
	 */
	function blocklane_pro_forms_normalize_files( $entry ) {
		if ( ! is_array( $entry ) || ! isset( $entry['name'] ) ) {
			return array();
		}

		$files = array();
		if ( is_array( $entry['name'] ) ) {
			foreach ( array_keys( $entry['name'] ) as $i ) {
				// A crafted nested name (photo[a][]) makes these slots arrays,
				// not scalars — no real upload has that shape. Skip the slot
				// (fails closed downstream) instead of casting arrays, which
				// raises attacker-triggerable warnings.
				if ( ! isset( $entry['name'][ $i ] ) || ! is_scalar( $entry['name'][ $i ] ) ) {
					continue;
				}
				$files[] = array(
					'name'     => (string) $entry['name'][ $i ],
					'type'     => isset( $entry['type'][ $i ] ) && is_scalar( $entry['type'][ $i ] ) ? (string) $entry['type'][ $i ] : '',
					'tmp_name' => isset( $entry['tmp_name'][ $i ] ) && is_scalar( $entry['tmp_name'][ $i ] ) ? (string) $entry['tmp_name'][ $i ] : '',
					'error'    => isset( $entry['error'][ $i ] ) && is_scalar( $entry['error'][ $i ] ) ? (int) $entry['error'][ $i ] : UPLOAD_ERR_NO_FILE,
					'size'     => isset( $entry['size'][ $i ] ) && is_scalar( $entry['size'][ $i ] ) ? (int) $entry['size'][ $i ] : 0,
				);
			}
		} else {
			$files[] = array(
				'name'     => (string) $entry['name'],
				'type'     => isset( $entry['type'] ) ? (string) $entry['type'] : '',
				'tmp_name' => isset( $entry['tmp_name'] ) ? (string) $entry['tmp_name'] : '',
				'error'    => isset( $entry['error'] ) ? (int) $entry['error'] : UPLOAD_ERR_NO_FILE,
				'size'     => isset( $entry['size'] ) ? (int) $entry['size'] : 0,
			);
		}

		// A no-file slot (empty input submitted) is not an upload at all.
		return array_values(
			array_filter(
				$files,
				static function ( $file ) {
					return UPLOAD_ERR_NO_FILE !== $file['error'];
				}
			)
		);
	}

	/**
	 * Validate one file field's uploads against its schema. Hardened per the
	 * Form Block CVE class: the client filename is treated as hostile display
	 * data (never a path), types must pass BOTH the extension whitelist and
	 * finfo content sniffing (wp_check_filetype_and_ext), and the deny list
	 * is checked last, non-filterably.
	 *
	 * @param array $entry  Raw $_FILES member for this field ([] when absent).
	 * @param array $schema Field schema (type 'file').
	 * @return array { files: array[], error: string } — pending files carry
	 *               { name, tmp_name, size, type } for persist().
	 */
	function blocklane_pro_forms_validate_file_field( $entry, $schema ) {
		$files = blocklane_pro_forms_normalize_files( $entry );

		if ( ! $files ) {
			return array(
				'files' => array(),
				'error' => ! empty( $schema['required'] ) ? __( 'This field is required.', 'blocklane' ) : '',
			);
		}

		if ( count( $files ) > (int) $schema['max_files'] ) {
			return array(
				'files' => array(),
				'error' => sprintf(
					/* translators: %d: maximum number of files. */
					_n( 'Please attach at most %d file.', 'Please attach at most %d files.', (int) $schema['max_files'], 'blocklane' ),
					(int) $schema['max_files']
				),
			);
		}

		$mimes     = is_array( $schema['mimes'] ) ? $schema['mimes'] : array();
		$validated = array();
		foreach ( $files as $file ) {
			if ( in_array( $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ) {
				return array(
					'files' => array(),
					'error' => __( 'A file is too large to upload.', 'blocklane' ),
				);
			}
			if ( UPLOAD_ERR_OK !== $file['error'] || '' === $file['tmp_name'] ) {
				return array(
					'files' => array(),
					'error' => __( 'A file failed to upload. Please try again.', 'blocklane' ),
				);
			}
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- tmp path from PHP's own upload handling, verified here.
			if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
				return array(
					'files' => array(),
					'error' => __( 'A file failed to upload. Please try again.', 'blocklane' ),
				);
			}
			// An empty file is its own case — telling a visitor their 0-byte
			// file "must be smaller than 8 MB" is nonsense. Client parity via
			// data-bl-error-empty in render.php.
			if ( $file['size'] <= 0 ) {
				return array(
					'files' => array(),
					'error' => __( 'An attached file is empty. Please choose a different file.', 'blocklane' ),
				);
			}
			if ( $file['size'] > (int) $schema['max_size'] ) {
				return array(
					'files' => array(),
					'error' => sprintf(
						/* translators: %s: maximum file size, e.g. "8 MB". */
						__( 'Each file must be smaller than %s.', 'blocklane' ),
						size_format( (int) $schema['max_size'] )
					),
				);
			}

			$display_name = sanitize_file_name( wp_unslash( $file['name'] ) );
			if ( '' === $display_name ) {
				$display_name = 'file';
			}

			$check = wp_check_filetype_and_ext( $file['tmp_name'], $display_name, $mimes );
			if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
				return array(
					'files' => array(),
					'error' => __( 'This file type is not accepted.', 'blocklane' ),
				);
			}
			if ( ! empty( $check['proper_filename'] ) ) {
				$display_name = $check['proper_filename'];
			}
			$ext = strtolower( (string) $check['ext'] );
			if ( ! isset( $mimes[ $ext ] ) || in_array( $ext, BLOCKLANE_PRO_FORMS_FILE_DENY, true ) ) {
				return array(
					'files' => array(),
					'error' => __( 'This file type is not accepted.', 'blocklane' ),
				);
			}

			$validated[] = array(
				'name'     => $display_name,
				'ext'      => $ext,
				'type'     => (string) $check['type'],
				'size'     => (int) $file['size'],
				'tmp_name' => $file['tmp_name'],
			);
		}

		return array(
			'files' => $validated,
			'error' => '',
		);
	}

	/**
	 * The private upload root (uploads/blocklane-forms), created on demand
	 * with direct-access guards. Files here are reachable only through the
	 * capability-checked download route — never by URL (random names are the
	 * nginx-side backstop where .htaccess is ignored).
	 *
	 * @return string Absolute path without trailing slash, '' on failure.
	 */
	function blocklane_pro_forms_upload_root() {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}
		$root = $uploads['basedir'] . '/blocklane-forms';
		if ( ! is_dir( $root ) && ! wp_mkdir_p( $root ) ) {
			return '';
		}

		$htaccess = $root . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- guard file in our own dir.
			file_put_contents( $htaccess, "# Blocklane Forms uploads — served only via the plugin.\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		}
		$index = $root . '/index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- guard file in our own dir.
			file_put_contents( $index, "<?php // Silence is golden.\n" );
		}

		return $root;
	}

	/**
	 * The upload_dir override that puts one attachment in the form store.
	 *
	 * A factory rather than an inline closure so the battery can apply it to
	 * wp_upload_dir() and assert where it points without performing an upload.
	 *
	 * url/baseurl are blanked deliberately: the store is private (the root
	 * carries .htaccess and index.php guards) and nothing may hand out a direct
	 * URL to it — attachments are streamed by the download route, which checks
	 * capability first.
	 *
	 * @param string $root      Absolute path of the form upload root.
	 * @param string $month_dir 'YYYY/MM'.
	 * @return \Closure
	 */
	function blocklane_pro_forms_upload_dir_override( string $root, string $month_dir ): \Closure {
		return static function ( $dirs ) use ( $root, $month_dir ) {
			$dirs['basedir'] = $root;
			$dirs['subdir']  = '/' . $month_dir;
			$dirs['path']    = $root . '/' . $month_dir;
			$dirs['url']     = '';
			$dirs['baseurl'] = '';
			$dirs['error']   = false;
			return $dirs;
		};
	}

	/**
	 * Store one validated upload, through WordPress rather than around it.
	 *
	 * This used to be move_uploaded_file() — a forbidden function on
	 * wordpress.org, and the rule is not arbitrary: going through
	 * wp_handle_upload() is what puts an attachment under the site's OWN upload
	 * policy. A max-size plugin, the Advanced screen's SVG sanitizer, an image
	 * optimizer — all of them hook the upload pipeline, and a form attachment
	 * that skipped it was the one file on the site nobody's rules applied to.
	 *
	 * Core still performs the actual move with move_uploaded_file() and still
	 * runs is_uploaded_file() itself before it does, so the guarantee the old
	 * code relied on is not weakened by the change — it is enforced twice now,
	 * once in validation and once in core.
	 *
	 * NOT the media library: no attachment post is created, and the file lands
	 * in the plugin's private store under a random hex name. The upload_dir
	 * override is added immediately before the call and removed in a `finally`,
	 * so a failure cannot leave a filter behind that would redirect the next
	 * plugin's upload into our directory.
	 *
	 * @param array<string, mixed> $file      One entry from validate_file_field's `pending`.
	 * @param string               $root      Absolute path of the form upload root.
	 * @param string               $month_dir 'YYYY/MM'.
	 * @return string|\WP_Error Path relative to $root, or why it could not be stored.
	 */
	function blocklane_pro_forms_store_upload( array $file, string $root, string $month_dir ): string|\WP_Error {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$ext  = (string) $file['ext'];
		$type = (string) $file['type'];

		// wp_handle_upload() takes $file BY REFERENCE, so this must be a
		// variable — an array literal is a fatal, not a notice.
		$upload = array(
			'name'     => (string) $file['name'],
			'type'     => $type,
			'tmp_name' => (string) $file['tmp_name'],
			'size'     => (int) $file['size'],
			'error'    => 0,
		);

		$override = blocklane_pro_forms_upload_dir_override( $root, $month_dir );
		add_filter( 'upload_dir', $override, PHP_INT_MAX );
		try {
			$result = wp_handle_upload(
				$upload,
				array(
					// No $_POST['action'] here — this is our own submit route.
					'test_form' => false,
					// The allowlist is the ONE type validation already resolved
					// for this exact file, so the second check cannot disagree
					// with the first by drifting apart from it.
					'mimes'                    => array( $ext => $type ),
					'unique_filename_callback' => static function ( $dir, $name, $extension ) {
						return bin2hex( random_bytes( 16 ) ) . $extension;
					},
				),
				$month_dir
			);
		} finally {
			remove_filter( 'upload_dir', $override, PHP_INT_MAX );
		}

		if ( ! is_array( $result ) || isset( $result['error'] ) ) {
			return new \WP_Error(
				'blocklane_pro_forms_store_failed',
				is_array( $result ) ? (string) $result['error'] : 'wp_handle_upload() returned nothing.'
			);
		}

		// A site filter (pre_move_uploaded_file, another upload_dir at a higher
		// priority) can relocate the file. That would put a submission's
		// attachment outside the private store the download route serves from
		// and possibly somewhere web-readable, so it is refused rather than
		// recorded: remove it and fail the submission.
		$stored_path = wp_normalize_path( (string) $result['file'] );
		$expected    = wp_normalize_path( $root . '/' . $month_dir );
		if ( dirname( $stored_path ) !== $expected ) {
			wp_delete_file( $stored_path );
			return new \WP_Error(
				'blocklane_pro_forms_store_relocated',
				'The attachment was written outside the form store (' . dirname( $stored_path ) . ').'
			);
		}

		return $month_dir . '/' . basename( $stored_path );
	}

	/**
	 * Move a submission's validated uploads into the private store. Runs only
	 * after the WHOLE submission validated — all-or-nothing: any failure rolls
	 * back the files already moved and the visitor gets an honest retry.
	 *
	 * Stored names are random hex + the validated extension; the client name
	 * survives only as display metadata inside the row snapshot.
	 *
	 * @param array $fields Self-described fields (by reference) — file fields
	 *                      carry `pending` from validate_file_field.
	 * @return bool Whether every pending file was persisted.
	 */
	function blocklane_pro_forms_persist_files( &$fields ) {
		$root   = '';
		$moved  = array();
		$failed = false;

		foreach ( $fields as &$field ) {
			if ( 'file' !== $field['type'] || empty( $field['pending'] ) ) {
				unset( $field['pending'] );
				continue;
			}

			if ( '' === $root ) {
				$root = blocklane_pro_forms_upload_root();
				if ( '' === $root ) {
					$failed = true;
					break;
				}
			}

			// No wp_mkdir_p here: wp_handle_upload() applies our upload_dir
			// override and then creates the directory itself. File permissions
			// come from the parent directory the same way, so the old
			// chmod( 0644 ) goes too.
			$month_dir = gmdate( 'Y/m' );

			$stored_files = array();
			$display      = array();
			foreach ( $field['pending'] as $file ) {
				$stored = blocklane_pro_forms_store_upload( $file, $root, $month_dir );
				if ( is_wp_error( $stored ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- a refused attachment fails the whole submission; without this the reason is invisible.
					error_log( 'Blocklane Forms: could not store an attachment - ' . $stored->get_error_message() );
					$failed = true;
					break 2;
				}
				$moved[]        = $root . '/' . $stored;
				$stored_files[] = array(
					'name'   => $file['name'],
					'stored' => $stored,
					'size'   => $file['size'],
					'type'   => $file['type'],
				);
				$display[]      = $file['name'] . ' (' . size_format( $file['size'] ) . ')';
			}

			$field['files'] = $stored_files;
			$field['value'] = $display;
			unset( $field['pending'] );
		}
		unset( $field );

		if ( $failed ) {
			// A move failed partway through: delete the files already moved and
			// strip every pending so no tmp path reaches storage or mail.
			foreach ( $moved as $path ) {
				wp_delete_file( $path );
			}
			foreach ( $fields as &$field ) {
				unset( $field['pending'] );
			}
			unset( $field );

			return false;
		}

		// No file field (or every move succeeded): a file-less submission is a
		// success. The previous '' === $root test treated an unset root as
		// failure, so it 500'd every form without a file field.
		return true;
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
	 * Bot-classified rejections (honeypot, time-trap, unknown form, unknown
	 * field names) return FAKE SUCCESS — bots learn nothing. Human validation
	 * failures return structured per-field errors. The rate limit returns an
	 * honest 429: a double-clicking human deserves the truth.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	function blocklane_pro_forms_handle_submission( $request ) {
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

		$params  = $request->get_body_params();
		$form_id = isset( $params['_bl_form_id'] ) ? sanitize_title( (string) $params['_bl_form_id'] ) : '';

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

		// _bl_source (the viewing page) is intentionally not consulted — the
		// form is resolved deterministically from content (see locate_form).
		$located = '' !== $form_id ? blocklane_pro_forms_locate_form( $form_id ) : null;
		if ( ! $located ) {
			// Unknown form: fake-success shaped exactly like a no-override
			// form's real success (resolved defaults, not neutral attrs).
			return blocklane_pro_forms_success_response( blocklane_pro_forms_resolve_config( array() ) );
		}

		$form_attrs = isset( $located['form']['attrs'] ) && is_array( $located['form']['attrs'] ) ? $located['form']['attrs'] : array();

		// Availability gates (v3) run BEFORE the bot traps: closed-form
		// state is honest form policy, not bot detection, and evaluating it
		// first means a scheduled form never wastes a Cloudflare round-trip.
		$blocklane_availability = blocklane_pro_forms_availability(
			blocklane_pro_forms_attrs_with_defaults( 'blocklane/form', $form_attrs )
		);
		if ( ! $blocklane_availability['open'] ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $blocklane_availability['message'],
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
		// submit): classify as bot, reveal nothing. The honeypot test compares
		// against the empty string rather than !empty() — a bot that stuffs
		// the string '0' into every input must not read as human.
		$honeypot     = blocklane_pro_forms_honeypot_name();
		$honeypot_val = isset( $params[ $honeypot ] ) ? trim( (string) $params[ $honeypot ] ) : '';
		if ( '' !== $honeypot_val || ! isset( $params['_bl_time'] ) || '1' !== $params['_bl_time'] ) {
			return blocklane_pro_forms_success_response( $form_attrs );
		}

		$schema = blocklane_pro_forms_derive_schema( $located['form'] );

		// Unknown field names = a tampered or replayed payload: fake success.
		// The Turnstile token is meta, not a field (whitelisted even when the
		// feature is off — a stale cached page must not trip the tamper trap).
		// Only a genuinely UNKNOWN name — a posted value or an uploaded file with
		// no matching schema key — trips the trap. A KNOWN name whose posted type
		// differs from the schema is NOT tampering: when a file field and a
		// non-file field resolve to the same name (a last-wins collision), a real
		// browser posts BOTH a value and a file under that name. The validation
		// loop below reads each field from its own source ($file_params for file
		// types, $params otherwise), so the mismatched half is ignored and the
		// collision resolves last-wins — never a silently swallowed submission.
		// Module meta rides the reserved `_bl_` namespace (the field namer
		// refuses those names, so no schema key can ever wear the prefix).
		// ANY posted _bl_* key is therefore skipped by PREFIX, not by list:
		// a newer view.js posting a meta key this (possibly baked, older)
		// runtime predates must be ignored, never trapped — the trap's fake
		// success would silently swallow every genuine visitor for as long
		// as the version skew lasts. Non-prefixed meta stays an exact list.
		$known_meta = array( $honeypot, 'cf-turnstile-response' );
		foreach ( array_keys( $params ) as $key ) {
			if ( 0 === strpos( (string) $key, '_bl_' ) || in_array( $key, $known_meta, true ) ) {
				continue;
			}
			if ( ! isset( $schema[ $key ] ) ) {
				return blocklane_pro_forms_success_response( $form_attrs );
			}
		}
		/**
		 * Widened on purpose: the stubs type every entry as ONE file's
		 * array{name: string, ...}, but a multi-file input (`field[]`) posts
		 * an array in each slot — the part counting below relies on that.
		 *
		 * @var array<string, mixed> $file_params
		 */
		$file_params = $request->get_file_params();
		foreach ( array_keys( $file_params ) as $key ) {
			if ( ! isset( $schema[ $key ] ) ) {
				return blocklane_pro_forms_success_response( $form_attrs );
			}
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
		// with when PHP actually truncates.
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

		$errors = array();
		$fields = array();
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

			if ( 'file' === $field_schema['type'] ) {
				$file_result = blocklane_pro_forms_validate_file_field(
					isset( $file_params[ $name ] ) ? $file_params[ $name ] : array(),
					$field_schema
				);
				if ( '' !== $file_result['error'] ) {
					$errors[ $name ] = $file_result['error'];
					continue;
				}
				$fields[] = array(
					'name'    => $name,
					'label'   => '' !== $field_schema['label'] ? $field_schema['label'] : $name,
					'type'    => 'file',
					'value'   => '',
					'pending' => $file_result['files'],
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

		// Unique entry (v3): one submission per distinct value of the chosen
		// field. Runs on VALIDATED values, and only when storage is on —
		// without rows there is nothing to compare against (the editor
		// surfaces that combination). Honest rejection on the field, like
		// any validation error: the duplicate submitter is a human.
		$blocklane_unique_field = isset( $located['form']['attrs']['uniqueField'] ) ? sanitize_text_field( (string) $located['form']['attrs']['uniqueField'] ) : '';
		if ( '' !== $blocklane_unique_field
			&& ( ! isset( $form_attrs['storeSubmissions'] ) || false !== $form_attrs['storeSubmissions'] )
			&& function_exists( 'blocklane_pro_forms_is_duplicate_entry' ) ) {
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
			// Hold the value for the rest of this request: the probe below reads,
			// but the row is written later by the storage listener, so without
			// this two simultaneous submissions of the same value both pass.
			// A lock we cannot take means someone else is mid-flight with this
			// exact value — treat that as the duplicate it is about to become.
			$blocklane_unique_lock = '';
			if ( '' !== $blocklane_unique_value ) {
				$blocklane_unique_lock = blocklane_pro_forms_claim_unique_lock( $form_id, $blocklane_unique_field, $blocklane_unique_value );

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
			}

			// Only a LIVE holder rejects. null means the lock could not be
			// operated at all — fail open and let the probe decide, rather
			// than turn a database hiccup into a rejected form.
			if ( '' !== $blocklane_unique_value
				&& ( false === $blocklane_unique_lock
					|| blocklane_pro_forms_is_duplicate_entry( $form_id, $blocklane_unique_field, $blocklane_unique_value ) ) ) {
				if ( is_string( $blocklane_unique_lock ) && '' !== $blocklane_unique_lock ) {
					delete_option( $blocklane_unique_lock );
				}
				$blocklane_unique_message = isset( $located['form']['attrs']['uniqueMessage'] ) && '' !== trim( (string) $located['form']['attrs']['uniqueMessage'] )
					? sanitize_text_field( (string) $located['form']['attrs']['uniqueMessage'] )
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
		if ( isset( $form_attrs['storeSubmissions'] ) && false === $form_attrs['storeSubmissions'] ) {
			foreach ( $fields as &$blocklane_field_ref ) {
				if ( 'file' === $blocklane_field_ref['type'] && ! empty( $blocklane_field_ref['pending'] ) ) {
					$display = array();
					foreach ( $blocklane_field_ref['pending'] as $pending_file ) {
						$display[] = $pending_file['name'] . ' (' . size_format( $pending_file['size'] ) . ')';
					}
					$blocklane_field_ref['value'] = $display;
				}
				unset( $blocklane_field_ref['pending'] );
			}
			unset( $blocklane_field_ref );
		} elseif ( ! blocklane_pro_forms_persist_files( $fields ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'We could not save your upload. Please try again.', 'blocklane' ),
				),
				500
			);
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
		// source_id (the located definition) can't tell for a synced-pattern
		// form. _bl_source is still never consulted for the schema (the
		// hijack guard above); as PROVENANCE it is safe — worst case a bot
		// misreports its own lead's origin — so it is verified down to a
		// real published post or dropped. The URL and referrer carry the
		// campaign signal (UTMs) a post id can't; client-supplied, so they
		// are sanitized, length-capped display metadata — never logic.
		$origin_id = isset( $params['_bl_source'] ) ? absint( $params['_bl_source'] ) : 0;
		if ( $origin_id > 0 ) {
			$origin_post = get_post( $origin_id );
			// Publicly viewable, not just status 'publish': internal types
			// (wp_block, wp_navigation, templates…) also carry 'publish',
			// and a bot could inject their names into the inbox origin
			// filter. render.php only stamps singular views, so a genuine
			// origin is always a viewable post.
			if ( ! $origin_post || ! is_post_publicly_viewable( $origin_post ) ) {
				$origin_id = 0;
			}
		}

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

		/**
		 * A validated submission. Storage (Phase 3) and email (Phase 3) hook
		 * here — the pipeline seam, Form Block's `form_block_submit_data`
		 * lesson kept as an action.
		 *
		 * @param array $submission {
		 *     @type string $form_id   Form id.
		 *     @type string $form_name Form name attribute.
		 *     @type int    $source_id Located source post id.
		 *     @type int    $origin_id Viewing page id (verified published, 0 when unknown).
		 *     @type array  $origin    Provenance snapshot { url, referrer, title, type }.
		 *     @type array  $fields    array of { name, label, type, value }.
		 *     @type string $reply_to  Validated reply-to email ('' when none).
		 *     @type array  $attrs     The form block's attributes, config-resolved.
		 * }
		 */
		do_action(
			'blocklane_pro_forms_submission',
			array(
				'form_id'   => $form_id,
				'form_name' => isset( $form_attrs['formName'] ) ? sanitize_text_field( (string) $form_attrs['formName'] ) : '',
				'source_id' => $located['source_id'],
				'origin_id' => $origin_id,
				'origin'    => $origin,
				'fields'    => $fields,
				'reply_to'  => $reply_to,
				'attrs'     => $form_attrs,
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

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = blocklane_pro_forms_table();
		$charset_collate = $wpdb->get_charset_collate();

		// search_text is a values-only projection of the fields snapshot, so
		// inbox search matches what visitors typed — not the JSON structural
		// tokens ("label", "value", "email"…) present in every row.
		// origin_id/origin (v2) are lead provenance: the page the visitor was
		// VIEWING, distinct from source_id (the located form definition — the
		// security anchor, which for a synced-pattern form is the same shared
		// wp_block whatever page converted). origin is a self-describing JSON
		// snapshot { url, referrer, title, type } taken at store time, so the
		// row keeps rendering after the origin page is renamed or deleted —
		// the same doctrine as the fields snapshot.
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
	 * One notification pair as pattern markup.
	 *
	 * @return string
	 */
	function blocklane_pro_forms_pattern_notifications() {
		return '<!-- wp:blocklane/form-notification {"type":"success"} -->' . "\n"
			. '<!-- wp:paragraph --><p>' . esc_html__( 'Thanks! Your message has been sent.', 'blocklane' ) . '</p><!-- /wp:paragraph -->' . "\n"
			. '<!-- /wp:blocklane/form-notification -->' . "\n"
			. '<!-- wp:blocklane/form-notification {"type":"error"} -->' . "\n"
			. '<!-- wp:paragraph --><p>' . esc_html__( 'Something went wrong and your message was not sent. Please check the highlighted fields and try again.', 'blocklane' ) . '</p><!-- /wp:paragraph -->' . "\n"
			. '<!-- /wp:blocklane/form-notification -->' . "\n";
	}

	/**
	 * A single block comment with JSON-encoded attributes (safe for
	 * translated strings containing quotes).
	 *
	 * @param string $name  Block name without namespace.
	 * @param array  $attrs Attributes.
	 * @return string
	 */
	function blocklane_pro_forms_pattern_block( $name, $attrs ) {
		return '<!-- wp:blocklane/' . $name . ' ' . wp_json_encode( $attrs ) . ' /-->' . "\n";
	}

	/**
	 * Register the pattern category + the three starter patterns.
	 *
	 * Skipped entirely while any block manifest is missing (see the build
	 * skew notice): a pattern that inserts unregistered blocks turns the
	 * inserter previews into "unsupported block" noise. The carousel runtime
	 * has carried this guard since the registrar landed; forms was the other
	 * door (#509). Ordering holds by add_action order at equal priority: the
	 * blocks register first (their init hook is added above), then this.
	 */
	function blocklane_pro_forms_register_patterns() {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}
		if ( \blocklane_pro\Block_Suite::missing( 'forms' ) ) {
			return;
		}
		register_block_pattern_category(
			'blocklane-forms',
			array( 'label' => __( 'Forms', 'blocklane' ) )
		);

		$common = array(
			'categories' => array( 'blocklane-forms' ),
			'blockTypes' => array( 'blocklane/form' ),
			'viewportWidth' => 800,
		);

		register_block_pattern(
			'blocklane/form-contact',
			array_merge(
				$common,
				array(
					'title'       => __( 'Contact form', 'blocklane' ),
					'description' => __( 'Name, email, and message with a submit button.', 'blocklane' ),
					'content'     => '<!-- wp:blocklane/form ' . wp_json_encode( array( 'formName' => __( 'Contact', 'blocklane' ) ) ) . ' -->' . "\n"
						. blocklane_pro_forms_pattern_block(
							'form-input',
							array(
								'type'         => 'text',
								'label'        => __( 'Name', 'blocklane' ),
								'required'     => true,
								'autocomplete' => 'name',
							)
						)
						. blocklane_pro_forms_pattern_block(
							'form-input',
							array(
								'type'         => 'email',
								'label'        => __( 'Email', 'blocklane' ),
								'required'     => true,
								'isReplyTo'    => true,
								'autocomplete' => 'email',
							)
						)
						. blocklane_pro_forms_pattern_block(
							'form-textarea',
							array(
								'label'    => __( 'Message', 'blocklane' ),
								'required' => true,
							)
						)
						. blocklane_pro_forms_pattern_block( 'form-submit-button', array( 'text' => __( 'Send message', 'blocklane' ) ) )
						. blocklane_pro_forms_pattern_notifications()
						. '<!-- /wp:blocklane/form -->',
				)
			)
		);

		register_block_pattern(
			'blocklane/form-newsletter',
			array_merge(
				$common,
				array(
					'title'       => __( 'Newsletter signup', 'blocklane' ),
					'description' => __( 'Email plus a consent checkbox.', 'blocklane' ),
					'content'     => '<!-- wp:blocklane/form ' . wp_json_encode( array( 'formName' => __( 'Newsletter', 'blocklane' ) ) ) . ' -->' . "\n"
						. blocklane_pro_forms_pattern_block(
							'form-input',
							array(
								'type'         => 'email',
								'label'        => __( 'Email', 'blocklane' ),
								'required'     => true,
								'isReplyTo'    => true,
								'autocomplete' => 'email',
							)
						)
						. blocklane_pro_forms_pattern_block(
							'form-input',
							array(
								'type'     => 'checkbox',
								'label'    => __( 'I agree to receive email updates.', 'blocklane' ),
								'required' => true,
							)
						)
						. blocklane_pro_forms_pattern_block( 'form-submit-button', array( 'text' => __( 'Subscribe', 'blocklane' ) ) )
						. blocklane_pro_forms_pattern_notifications()
						. '<!-- /wp:blocklane/form -->',
				)
			)
		);

		$rsvp_options = '<!-- wp:blocklane/form-group ' . wp_json_encode(
			array(
				'type'     => 'radio',
				'legend'   => __( 'Will you attend?', 'blocklane' ),
				'required' => true,
			)
		) . ' -->' . "\n"
			. blocklane_pro_forms_pattern_block( 'form-option', array( 'label' => __( 'Yes, I will be there', 'blocklane' ) ) )
			. blocklane_pro_forms_pattern_block( 'form-option', array( 'label' => __( 'Sorry, I can’t make it', 'blocklane' ) ) )
			. '<!-- /wp:blocklane/form-group -->' . "\n";

		register_block_pattern(
			'blocklane/form-rsvp',
			array_merge(
				$common,
				array(
					'title'       => __( 'RSVP', 'blocklane' ),
					'description' => __( 'Attendance, guest count, and a note.', 'blocklane' ),
					'content'     => '<!-- wp:blocklane/form ' . wp_json_encode( array( 'formName' => __( 'RSVP', 'blocklane' ) ) ) . ' -->' . "\n"
						. blocklane_pro_forms_pattern_block(
							'form-input',
							array(
								'type'         => 'text',
								'label'        => __( 'Name', 'blocklane' ),
								'required'     => true,
								'autocomplete' => 'name',
							)
						)
						. blocklane_pro_forms_pattern_block(
							'form-input',
							array(
								'type'         => 'email',
								'label'        => __( 'Email', 'blocklane' ),
								'required'     => true,
								'isReplyTo'    => true,
								'autocomplete' => 'email',
							)
						)
						. $rsvp_options
						. blocklane_pro_forms_pattern_block(
							'form-input',
							array(
								'type'  => 'number',
								'label' => __( 'Number of guests', 'blocklane' ),
								'min'   => '0',
								'max'   => '10',
							)
						)
						. blocklane_pro_forms_pattern_block( 'form-textarea', array( 'label' => __( 'Anything we should know?', 'blocklane' ) ) )
						. blocklane_pro_forms_pattern_block( 'form-submit-button', array( 'text' => __( 'Send RSVP', 'blocklane' ) ) )
						. blocklane_pro_forms_pattern_notifications()
						. '<!-- /wp:blocklane/form -->',
				)
			)
		);
	}
	add_action( 'init', 'blocklane_pro_forms_register_patterns' );

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
