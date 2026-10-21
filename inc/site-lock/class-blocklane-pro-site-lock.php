<?php
/**
 * Site Lock store: the whole-site "Coming Soon" / "Maintenance" gate setting.
 *
 * One option holds the toggle, the mode, and the shared password. The password
 * is stored *encrypted at rest* (libsodium secretbox, key derived from the site
 * salts) rather than hashed, so a manage_options admin can reveal it in the
 * dashboard — appropriate here because this is a shared, hand-it-to-visitors
 * site password, not a login credential. It is never sent to the front end or
 * to non-admins. Real WordPress user passwords are unaffected.
 *
 * Legacy installs whose password was stored hashed (password_hash) still verify
 * and keep the gate working; the value can't be revealed until it's re-saved
 * (which migrates it to encrypted storage). Regenerating the site salts makes a
 * stored password unrecoverable (re-enter it), same as it invalidates cookies.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Lock {

	const OPTION = 'blocklane_pro_site_lock';

	const MODE_COMING_SOON = 'coming-soon';
	const MODE_MAINTENANCE = 'maintenance';

	/**
	 * The normalized setting: { enabled, mode, password_enc, password_hash }.
	 * password_enc is the current (reversible) storage; password_hash is the
	 * legacy one-way hash kept only so pre-upgrade passwords still verify.
	 *
	 * Deliberately NOT memoized: save() re-reads through here after writing and
	 * compares before/after snapshots to decide the page-cache purge — a static
	 * request-cache would hand save() the pre-write snapshot and silently
	 * disable every purge-on-transition. Any future memo must be invalidated
	 * (or bypassed) in save(). The option is autoloaded (seeded in
	 * blocklane-pro.php's version migration), so the bare read costs no query.
	 *
	 * @return array{enabled:bool,mode:string,password_enc:string,password_hash:string}
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );

		return self::normalize( is_array( $stored ) ? $stored : array() );
	}

	private static function normalize( array $raw ) {
		return array(
			'enabled'       => ! empty( $raw['enabled'] ),
			'mode'          => self::MODE_MAINTENANCE === ( $raw['mode'] ?? '' ) ? self::MODE_MAINTENANCE : self::MODE_COMING_SOON,
			'password_enc'  => isset( $raw['password_enc'] ) && is_string( $raw['password_enc'] ) ? $raw['password_enc'] : '',
			'password_hash' => isset( $raw['password_hash'] ) && is_string( $raw['password_hash'] ) ? $raw['password_hash'] : '',
			'preview_key'   => isset( $raw['preview_key'] ) && is_string( $raw['preview_key'] ) ? $raw['preview_key'] : '',
		);
	}

	/**
	 * Persist from a request payload. A new non-empty password is encrypted (and
	 * supersedes any legacy hash); clear_password wipes both; otherwise the stored
	 * password is preserved so toggling mode/enabled doesn't drop it.
	 *
	 * Unknown keys in the STORED row are preserved (Helper::with_foreign_keys —
	 * the other edition, or a newer version of this one, may own them); the
	 * purge decision below reads normalized snapshots and never sees them.
	 *
	 * @param array $input { enabled, mode, password?, clear_password? }.
	 * @return array|\WP_Error The normalized setting as stored, or the helper's typed refusal (nothing written).
	 */
	public static function save( array $input ): array|\WP_Error {
		$current = self::get();

		$next = array(
			'enabled'       => ! empty( $input['enabled'] ),
			'mode'          => self::MODE_MAINTENANCE === ( $input['mode'] ?? '' ) ? self::MODE_MAINTENANCE : self::MODE_COMING_SOON,
			'password_enc'  => $current['password_enc'],
			'password_hash' => $current['password_hash'],
			// A shareable preview key persists across saves; regenerate to revoke.
			'preview_key'   => '' !== $current['preview_key'] ? $current['preview_key'] : self::new_preview_key(),
		);

		if ( ! empty( $input['regenerate_preview'] ) ) {
			$next['preview_key'] = self::new_preview_key();
		}

		if ( ! empty( $input['clear_password'] ) ) {
			$next['password_enc']  = '';
			$next['password_hash'] = '';
		} elseif ( isset( $input['password'] ) && is_string( $input['password'] ) && '' !== $input['password'] ) {
			$enc = self::encrypt( $input['password'] );
			if ( '' !== $enc ) {
				$next['password_enc']  = $enc;
				$next['password_hash'] = ''; // migrate off the legacy hash.
			} else {
				// libsodium unavailable: fall back to a one-way hash so the gate
				// still works — the password just can't be revealed.
				$next['password_enc']  = '';
				$next['password_hash'] = wp_hash_password( $input['password'] );
			}
		}

		// Invariant: Coming Soon is only a real gate with a password. If none remains
		// after this save, it can't be "on" — force it off so the site returns to
		// public rather than sitting in an inert half-locked state. Removing the
		// password is therefore a valid reset to public. (Maintenance needs none.)
		if ( self::MODE_COMING_SOON === $next['mode']
			&& '' === $next['password_enc'] && '' === $next['password_hash'] ) {
			$next['enabled'] = false;
		}

		$merged = Helper::with_foreign_keys( self::OPTION, $next, self::normalize( array() ) );
		if ( is_wp_error( $merged ) ) {
			return $merged;
		}
		update_option( self::OPTION, $merged );

		$saved = self::get();

		// The site's cacheability contract changed if the lock started or
		// stopped GATING — not merely if `enabled` flipped: legacy or
		// out-of-band data can hold enabled=true with a passwordless Coming
		// Soon, which isn't gating (responses were cacheable), and saving a
		// password into that state must purge even though `enabled` never
		// moved. A mode swap while gating also purges (it retires a splash).
		// While gating, nothing new gets stored — the gate marks every
		// response no-store (see Site_Lock_Cache) — so transitions are the
		// only purge moments.
		$gated_before = self::gates( $current );
		$gated_after  = self::gates( $saved );
		if ( $gated_before !== $gated_after
			|| ( $gated_after && $current['mode'] !== $saved['mode'] ) ) {
			Site_Lock_Cache::purge_page_caches();
		}

		return $saved;
	}

	public static function is_enabled() {
		return self::get()['enabled'];
	}

	/**
	 * Whether the lock is actually gating anyone: enabled and not the inert
	 * passwordless Coming Soon (which maybe_gate() leaves open). Deliberately
	 * visitor-agnostic — it decides response cacheability, and that must be
	 * identical for locked and unlocked visitors because a shared page cache
	 * keys them to the same entry.
	 *
	 * @return bool
	 */
	public static function is_gating() {
		return self::gates( self::get() );
	}

	/**
	 * Whether a normalized setting array is a real gate — enabled and not the
	 * inert passwordless Coming Soon. Array-shaped rather than option-reading
	 * so save() can evaluate the BEFORE state as well as the after; the stored
	 * option can only ever answer "after".
	 *
	 * @param array<string,mixed> $s Normalized setting (see normalize()).
	 * @return bool
	 */
	private static function gates( array $s ) {
		return ! empty( $s['enabled'] )
			&& ( self::MODE_MAINTENANCE === ( $s['mode'] ?? '' )
				|| '' !== ( $s['password_enc'] ?? '' )
				|| '' !== ( $s['password_hash'] ?? '' ) );
	}

	public static function mode() {
		return self::get()['mode'];
	}

	public static function has_password() {
		$s = self::get();
		return '' !== $s['password_enc'] || '' !== $s['password_hash'];
	}

	/**
	 * The shareable preview key — a random secret that unlocks the gate via a URL
	 * param, so a preview can be shared without handing out the password. Distinct
	 * from the password, so it can be revoked (regenerated) independently. Read-only
	 * here; created/rotated through save().
	 *
	 * @return string
	 */
	public static function preview_key() {
		return self::get()['preview_key'];
	}

	/** A fresh URL-safe preview secret. */
	private static function new_preview_key() {
		return wp_generate_password( 24, false );
	}

	/**
	 * Constant-time check of a submitted preview key against the stored one.
	 *
	 * @param string $key
	 * @return bool
	 */
	public static function check_preview_key( $key ) {
		$stored = self::preview_key();
		return '' !== $stored && is_string( $key ) && hash_equals( $stored, $key );
	}

	/**
	 * The current password in clear text, for the admin reveal only. Empty when
	 * none is set or when only a legacy (unrecoverable) hash exists.
	 *
	 * @return string
	 */
	public static function reveal() {
		return self::decrypt( self::get()['password_enc'] );
	}

	/**
	 * A stable per-password token used as the unlock-cookie HMAC basis: constant
	 * for a given password, changes when the password changes (invalidating old
	 * cookies), and never the plaintext itself.
	 *
	 * @return string
	 */
	public static function password_token() {
		$s = self::get();
		if ( '' !== $s['password_enc'] ) {
			$plain = self::decrypt( $s['password_enc'] );
			return '' === $plain ? '' : hash( 'sha256', 'enc:' . $plain );
		}
		// Legacy: the hash is already a stable per-password value.
		return '' !== $s['password_hash'] ? hash( 'sha256', 'leg:' . $s['password_hash'] ) : '';
	}

	/**
	 * Verify a submitted password — against the encrypted value (constant-time)
	 * or, for pre-upgrade installs, the legacy hash.
	 *
	 * @param string $plain Submitted password.
	 * @return bool
	 */
	public static function check_password( $plain ) {
		$s     = self::get();
		$plain = (string) $plain;

		if ( '' !== $s['password_enc'] ) {
			$stored = self::decrypt( $s['password_enc'] );
			return '' !== $stored && hash_equals( $stored, $plain );
		}

		return '' !== $s['password_hash'] && wp_check_password( $plain, $s['password_hash'] );
	}

	/* ---- reversible storage (libsodium secretbox) -------------------------- */

	/**
	 * 32-byte key derived from the site salts. Constant per install; rotating the
	 * salts makes existing values unrecoverable (by design).
	 */
	private static function crypto_key() {
		$material = wp_salt( 'secure_auth' ) . '|blocklane-pro-site-lock';
		return sodium_crypto_generichash( $material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	private static function encrypt( $plain ) {
		if ( '' === (string) $plain || ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return '';
		}
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( (string) $plain, $nonce, self::crypto_key() );

		return base64_encode( $nonce . $cipher );
	}

	private static function decrypt( $stored ) {
		if ( '' === (string) $stored || ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return '';
		}
		// The stored value is our own sodium secretbox ciphertext (nonce + box),
		// base64-encoded by encrypt() so it survives the options table; strict
		// decoding rejects anything that is not that encoding. Not obfuscation.
		$decoded = base64_decode( (string) $stored, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64 -- decoding our own ciphertext, strict mode.
		if ( false === $decoded || strlen( $decoded ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$nonce  = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, self::crypto_key() );

		return false === $plain ? '' : $plain;
	}
}
