<?php
/**
 * SEO settings store + module boot.
 *
 * One serialized option (blocklane_pro_seo) holds every site-level SEO
 * setting: the core-sitemap toggle, the Organization schema fields, and
 * the search-engine verification codes.
 * The front-end runtime (inc/seo/runtime.php) reads the option directly —
 * never this class — so emission works without the module loaded; this class
 * is the management side: normalized read/write for the REST controller,
 * plus the SEO abilities (Abilities API) when the AI opt-in is on.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo implements Bootable {

	const OPTION = 'blocklane_pro_seo';

	/** Verification services offered, in display order. */
	const VERIFICATION_SERVICES = array( 'google', 'bing', 'pinterest', 'yandex', 'facebook' );

	/** Most profiles anyone reasonably lists as Organization sameAs links. */
	const MAX_PROFILES = 10;

	/** Opening-hours day keys, in display order. */
	const WEEK_DAYS = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );

	/** Description cap (Organization schema) — mirrors the runtime's emission cap. */
	const DESCRIPTION_MAX = 300;

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// The importer's WP-CLI command, registered from the module's boot
		// class rather than at the importer's file scope: a class file must
		// declare and do nothing else (the classmap rule), and this is the
		// same instant it registered before — Modules::boot() loads the file
		// and constructs this class back to back. register_cli() self-guards
		// on WP_CLI.
		Seo_Import::register_cli();

		// SEO abilities ride the same opt-in as the rest of the Abilities
		// surface: registration is deliberately skipped unless the AI MCP
		// toggle is on (it grants AI clients access to site settings).
		if ( class_exists( __NAMESPACE__ . '\Advanced', false ) && Advanced::is_on( 'ai-mcp' ) ) {
			add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ), 30 );
		}
	}

	/**
	 * Shipped defaults. Sitemap defaults ON to match core (wp-sitemap.xml is
	 * on out of the box); everything else starts empty.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'sitemap'                    => true,
			// Exclusion lists (not inclusion) so newly registered types and
			// taxonomies default INTO the sitemap, matching core.
			'sitemap_exclude_types'      => array(),
			'sitemap_exclude_taxonomies' => array(),
			'sitemap_authors'            => true,
			'html_sitemap'               => false,
			'html_sitemap_page'          => 0,
			'llms_txt'                   => false,
			'archive_canonicals'         => true,
			'noindex_author_archives'    => false,
			'noindex_date_archives'      => false,
			'default_og_image_id'        => 0,
			'default_og_image_url'       => '',
			'indexnow'                   => false,
			'indexnow_key'               => '',
			'title_format'       => '',
			'title_separator'    => '',
			'org_name'           => '',
			'org_description'    => '',
			'org_logo_id'        => 0,
			'org_profiles'       => array(),
			'local_business'     => false,
			'local_address'      => '',
			'local_city'         => '',
			'local_region'       => '',
			'local_postal_code'  => '',
			'local_country'      => '',
			'local_phone'        => '',
			'local_latitude'     => '',
			'local_longitude'    => '',
			'local_hours'        => array(),
			'verification'       => array_fill_keys( self::VERIFICATION_SERVICES, '' ),
		);
	}

	/**
	 * Normalized settings.
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		return self::normalize( is_array( $stored ) ? $stored : array(), self::defaults() );
	}

	/**
	 * The share-image URL for an attachment — the plugin-side door to the one
	 * derivation rule, so a future change (a cropped OG size, say) lands in
	 * one place instead of the call sites this consolidated.
	 *
	 * Defers to the emission runtime's copy when that file is loaded; the
	 * runtime is self-contained, a contract from the bake era when it also ran
	 * as a generated copy outside the plugin (docs/archive/bake-contract.md), so
	 * it cannot call back into this class and the rule has exactly two homes. Keep the fallback below identical to
	 * blocklane_pro_seo_og_image_url().
	 *
	 * @param int $att_id Attachment ID.
	 * @return string Absolute URL, or '' when it cannot be resolved.
	 */
	public static function og_image_url( $att_id ) {
		if ( function_exists( 'blocklane_pro_seo_og_image_url' ) ) {
			return blocklane_pro_seo_og_image_url( $att_id );
		}

		$att_id = (int) $att_id;
		return $att_id ? (string) wp_get_attachment_image_url( $att_id, 'full' ) : '';
	}

	/**
	 * Persist from a request payload. Accepts partial maps: omitted keys keep
	 * their currently stored value. Unknown keys in the PAYLOAD are ignored;
	 * unknown keys in the STORED row are preserved (Helper::with_foreign_keys —
	 * the other edition, or a newer version of this one, may own them).
	 *
	 * @param array $settings Incoming payload.
	 * @return array|\WP_Error The normalized, saved settings, or the helper's typed refusal (nothing written).
	 */
	public static function save( array $settings ): array|\WP_Error {
		$next = self::normalize( $settings, self::get() );

		// The IndexNow key is minted on first enable — here, never on read,
		// so an unsaved get() can't hand out a key that was never stored.
		if ( $next['indexnow'] && '' === $next['indexnow_key'] ) {
			$next['indexnow_key'] = strtolower( wp_generate_password( 32, false ) );
		}

		// The fallback share-image URL is resolved once, here at save, so the
		// front end never pays a per-page attachment lookup for it — the
		// emission runtime reads it straight off the stored option (and
		// refreshes it if the attachment is edited or deleted).
		$next['default_og_image_url'] = self::og_image_url( $next['default_og_image_id'] );

		$merged = Helper::with_foreign_keys( self::OPTION, $next, self::defaults() );
		if ( is_wp_error( $merged ) ) {
			return $merged;
		}
		update_option( self::OPTION, $merged );
		return self::get();
	}

	/**
	 * Normalize a payload against fallbacks (defaults on read, current values
	 * on save — so partial saves never reset unmentioned keys).
	 *
	 * @param array $in       Raw values.
	 * @param array $fallback Value to keep for any omitted key.
	 * @return array
	 */
	private static function normalize( array $in, array $fallback ) {
		$out = array();

		// The toggles and attachment-ID keys share one shape each — keyed
		// loops so adding a setting is one array entry, not a pasted block
		// whose fallback key can be forgotten.
		foreach ( array(
			'sitemap',
			'sitemap_authors',
			'html_sitemap',
			'llms_txt',
			'archive_canonicals',
			'noindex_author_archives',
			'noindex_date_archives',
			'indexnow',
			'local_business',
		) as $flag ) {
			$out[ $flag ] = isset( $in[ $flag ] ) ? (bool) $in[ $flag ] : $fallback[ $flag ];
		}

		foreach ( array( 'html_sitemap_page', 'default_og_image_id', 'org_logo_id' ) as $id_key ) {
			$out[ $id_key ] = isset( $in[ $id_key ] ) ? absint( $in[ $id_key ] ) : $fallback[ $id_key ];
		}

		foreach ( array( 'sitemap_exclude_types', 'sitemap_exclude_taxonomies' ) as $list ) {
			if ( isset( $in[ $list ] ) && is_array( $in[ $list ] ) ) {
				$out[ $list ] = array_values(
					array_unique( array_filter( array_map( 'sanitize_key', $in[ $list ] ) ) )
				);
			} else {
				$out[ $list ] = $fallback[ $list ];
			}
		}

		// The key is protocol-constrained (8–128 chars of a-z A-Z 0-9 and
		// dash, per the IndexNow spec); anything else falls back to empty
		// and save() mints a fresh one.
		if ( isset( $in['indexnow_key'] ) ) {
			$key                 = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) $in['indexnow_key'] );
			$length              = strlen( $key );
			$out['indexnow_key'] = ( $length >= 8 && $length <= 128 ) ? $key : '';
		} else {
			$out['indexnow_key'] = (string) $fallback['indexnow_key'];
		}

		// Free text with %title% / %site% / %tagline% / %sep% tokens; the
		// runtime resolves them per post. Kept short — it's one <title>.
		$out['title_format'] = isset( $in['title_format'] )
			? self::cap_length( sanitize_text_field( (string) $in['title_format'] ), 120 )
			: $fallback['title_format'];

		$out['title_separator'] = isset( $in['title_separator'] )
			? self::cap_length( trim( sanitize_text_field( (string) $in['title_separator'] ) ), 3 )
			: $fallback['title_separator'];

		$out['org_name'] = isset( $in['org_name'] )
			? sanitize_text_field( (string) $in['org_name'] )
			: $fallback['org_name'];

		$out['org_description'] = isset( $in['org_description'] )
			? self::sanitize_description( $in['org_description'] )
			: $fallback['org_description'];

		if ( isset( $in['org_profiles'] ) && is_array( $in['org_profiles'] ) ) {
			$profiles = array();
			foreach ( $in['org_profiles'] as $url ) {
				$url = esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
				// A profile link needs a real host — "facebook" alone would
				// otherwise be kept as http://facebook and emitted as sameAs.
				$host = $url ? wp_parse_url( $url, PHP_URL_HOST ) : '';
				if ( $host && false !== strpos( $host, '.' ) ) {
					$profiles[] = $url;
				}
			}
			$out['org_profiles'] = array_slice( array_values( array_unique( $profiles ) ), 0, self::MAX_PROFILES );
		} else {
			$out['org_profiles'] = $fallback['org_profiles'];
		}

		foreach ( array( 'local_address', 'local_city', 'local_region', 'local_postal_code', 'local_country', 'local_phone' ) as $field ) {
			$out[ $field ] = isset( $in[ $field ] )
				? self::cap_length( sanitize_text_field( (string) $in[ $field ] ), 200 )
				: $fallback[ $field ];
		}

		// Coordinates stay strings (people paste them) but must parse as
		// numbers inside the valid range; anything else saves as empty.
		foreach ( array(
			'local_latitude'  => 90,
			'local_longitude' => 180,
		) as $field => $range ) {
			if ( isset( $in[ $field ] ) ) {
				$value          = trim( (string) $in[ $field ] );
				$out[ $field ] = ( is_numeric( $value ) && abs( (float) $value ) <= $range ) ? $value : '';
			} else {
				$out[ $field ] = $fallback[ $field ];
			}
		}

		if ( isset( $in['local_hours'] ) && is_array( $in['local_hours'] ) ) {
			$hours = array();
			foreach ( self::WEEK_DAYS as $day ) {
				$row   = ( isset( $in['local_hours'][ $day ] ) && is_array( $in['local_hours'][ $day ] ) )
					? $in['local_hours'][ $day ]
					: array();
				$open  = self::sanitize_time( $row['open'] ?? '' );
				$close = self::sanitize_time( $row['close'] ?? '' );
				// A day needs both ends to count as open; blank = closed.
				if ( '' !== $open && '' !== $close ) {
					$hours[ $day ] = array(
						'open'  => $open,
						'close' => $close,
					);
				}
			}
			$out['local_hours'] = $hours;
		} else {
			$out['local_hours'] = $fallback['local_hours'];
		}

		$out['verification'] = array();
		$in_codes            = ( isset( $in['verification'] ) && is_array( $in['verification'] ) ) ? $in['verification'] : null;
		foreach ( self::VERIFICATION_SERVICES as $service ) {
			if ( null !== $in_codes && array_key_exists( $service, $in_codes ) ) {
				$out['verification'][ $service ] = self::sanitize_verification_code( $in_codes[ $service ] );
			} else {
				$out['verification'][ $service ] = isset( $fallback['verification'][ $service ] )
					? (string) $fallback['verification'][ $service ]
					: '';
			}
		}

		// Derived, never client-supplied: the stored share-image URL rides
		// through normalization untouched (save() re-derives it from the ID).
		//
		// $in is consulted first like every field above. get() passes
		// defaults() as the fallback and its default is '', so reading only
		// the fallback discarded whatever the option actually held — making
		// get(), save()'s return value and the seo-get-settings Ability all
		// report this field empty no matter what was stored.
		if ( isset( $in['default_og_image_url'] ) ) {
			$out['default_og_image_url'] = (string) $in['default_og_image_url'];
		} else {
			$out['default_og_image_url'] = isset( $fallback['default_og_image_url'] )
				? (string) $fallback['default_og_image_url']
				: '';
		}

		return $out;
	}

	/**
	 * Plain-text description, capped.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function sanitize_description( $value ) {
		return self::cap_length( trim( wp_strip_all_tags( (string) $value ) ), self::DESCRIPTION_MAX );
	}

	/**
	 * A 24-hour HH:MM time, or '' for anything else.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function sanitize_time( $value ) {
		$value = trim( (string) $value );

		return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '';
	}

	/**
	 * Multibyte-safe length cap (mbstring isn't guaranteed on every host).
	 *
	 * @param string $value  Value.
	 * @param int    $length Max characters.
	 * @return string
	 */
	private static function cap_length( $value, $length ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length );
		}
		return substr( $value, 0, $length );
	}

	/**
	 * A verification code, as pasted. People paste the whole meta tag the
	 * service hands them, so accept that and extract its content attribute;
	 * then keep only token-safe characters.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_verification_code( $value ) {
		$value = trim( (string) $value );

		if ( false !== stripos( $value, '<meta' )
			&& preg_match( '/content=["\']?([^"\'>\s]+)/i', $value, $m ) ) {
			$value = $m[1];
		}

		return preg_replace( '/[^A-Za-z0-9_\-+=\/.:]/', '', $value );
	}

	/* ---- abilities (Abilities API) ------------------------------------------ */

	/**
	 * Register the SEO abilities. Read/manage pair over the site-level
	 * settings; per-post fields are already reachable to agents through the
	 * REST post meta this module registers, so no duplicate ability is added
	 * for those.
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'blocklane-pro/seo-get-settings',
			array(
				'label'               => __( 'Get SEO settings', 'blocklane' ),
				'description'         => __( 'Read Blocklane SEO site settings: sitemap toggle, Organization schema fields, and which verification services are configured.', 'blocklane' ),
				'category'            => 'blocklane-design',
				'execute_callback'    => static function () {
					$settings = Seo::get();
					// Verification codes are site secrets of a sort — report
					// configured/not rather than the raw tokens.
					$settings['verification'] = array_map(
						static function ( $code ) {
							return '' !== $code;
						},
						$settings['verification']
					);
					return $settings;
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		wp_register_ability(
			'blocklane-pro/seo-update-settings',
			array(
				'label'               => __( 'Update SEO settings', 'blocklane' ),
				'description'         => __( 'Update Blocklane SEO site settings. Accepts a partial map of: sitemap (bool), html_sitemap (bool), html_sitemap_page (page ID), llms_txt (bool; serves an llms.txt content manifest for AI crawlers), archive_canonicals (bool), noindex_author_archives (bool), noindex_date_archives (bool), default_og_image_id (attachment ID for the fallback share image), indexnow (bool; the key is generated automatically and not writable here), title_format (a token string; the tokens are title, site, tagline and sep, each wrapped in percent signs), title_separator, org_name, org_description, org_logo_id, org_profiles (URL list), and the local-business fields: local_business (bool switches the schema to LocalBusiness), local_address, local_city, local_region, local_postal_code, local_country, local_phone, local_latitude, local_longitude, local_hours (day key monday–sunday to {open, close} in 24h HH:MM).', 'blocklane' ),
				'category'            => 'blocklane-design',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'sitemap'                    => array( 'type' => 'boolean' ),
						'sitemap_exclude_types'      => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'sitemap_exclude_taxonomies' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'sitemap_authors'            => array( 'type' => 'boolean' ),
						'html_sitemap'               => array( 'type' => 'boolean' ),
						'html_sitemap_page'          => array( 'type' => 'integer' ),
						'llms_txt'                   => array( 'type' => 'boolean' ),
						'archive_canonicals'         => array( 'type' => 'boolean' ),
						'noindex_author_archives'    => array( 'type' => 'boolean' ),
						'noindex_date_archives'      => array( 'type' => 'boolean' ),
						'default_og_image_id'        => array( 'type' => 'integer' ),
						'indexnow'                   => array( 'type' => 'boolean' ),
						'title_format'       => array( 'type' => 'string' ),
						'title_separator'    => array( 'type' => 'string' ),
						'org_name'           => array( 'type' => 'string' ),
						'org_description'    => array( 'type' => 'string' ),
						'org_logo_id'        => array( 'type' => 'integer' ),
						'org_profiles'       => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'local_business'     => array( 'type' => 'boolean' ),
						'local_address'      => array( 'type' => 'string' ),
						'local_city'         => array( 'type' => 'string' ),
						'local_region'       => array( 'type' => 'string' ),
						'local_postal_code'  => array( 'type' => 'string' ),
						'local_country'      => array( 'type' => 'string' ),
						'local_phone'        => array( 'type' => 'string' ),
						'local_latitude'     => array( 'type' => 'string' ),
						'local_longitude'    => array( 'type' => 'string' ),
						'local_hours'        => array(
							'type'                 => 'object',
							'additionalProperties' => array(
								'type'       => 'object',
								'properties' => array(
									'open'  => array( 'type' => 'string' ),
									'close' => array( 'type' => 'string' ),
								),
							),
						),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					// Verification codes stay UI-only — an agent has no
					// business writing site-ownership tokens.
					$input = is_array( $input ) ? $input : array();
					unset( $input['verification'] );
					$saved = Seo::save( $input );
					if ( is_wp_error( $saved ) ) {
						return $saved;
					}
					unset( $saved['verification'] );
					return $saved;
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}
}
