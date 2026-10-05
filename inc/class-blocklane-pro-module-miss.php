<?php
/**
 * Module_Miss — the typed answer to "why did this module not load?".
 *
 * Modules::boot_module() and Modules::route_module() return one of these
 * (or null for a whole module) instead of a bool plus a class name in a
 * shared slot. It carries the module, the PHASE the miss belongs to, the
 * KIND of miss, its subject and, when the kind has one, the mapped file.
 *
 * Each phase owns its own state slot and its own sentence, because each has a
 * different consequence: boot (nothing of the module loaded), routes (it
 * booted whole and only lost its REST controller — the two used to share one
 * slot and one "did not load" sentence, #542), content (the render path for
 * content already built), and lifecycle (once-per-version housekeeping that
 * silently did not happen). describe() is the
 * untranslated log line; the admin notice's wording lives in
 * Modules::module_notices().
 *
 * A value: no hooks, no I/O, nothing at file scope beyond the declaration.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Module_Miss {

	public const PHASE_BOOT   = 'boot';
	public const PHASE_ROUTES = 'routes';
	/**
	 * A CONTENT runtime — the render path required before the theme gate, for
	 * content the user has already built. Its own phase because its
	 * consequence is its own: the module's management side is unaffected and
	 * may be running, while saved content of that kind stops rendering.
	 * These six requires sat outside every guarantee until this phase existed
	 * (DEFERRED-WORK #508).
	 */
	public const PHASE_CONTENT = 'content';
	/**
	 * A LIFECYCLE file — a unit's once-per-version housekeeping, required at
	 * plugins_loaded before Version_Migration runs. Its own phase because a
	 * miss here is silent by nature: nothing renders differently, a migration
	 * simply never happened.
	 */
	public const PHASE_LIFECYCLE = 'lifecycle';
	/**
	 * An extension's ADMIN half — the editor controls, settings row and usage
	 * tracking a present extension carries beside its content runtime
	 * (Extensions_Handler::ADMIN_RUNTIMES). Its own phase because its
	 * consequence is its own: content already built with the extension keeps
	 * rendering while the controls to edit it are gone (#875).
	 */
	public const PHASE_ADMIN = 'admin';

	/** A `runtimes` entry is not a file. Subject: the path relative to inc/. */
	public const RUNTIME_MISSING = 'runtime-missing';
	/** A class in `classes ∪ {boot}` (or the controller) has no classmap entry. Subject: the class. */
	public const CLASS_UNMAPPED = 'class-unmapped';
	/** The class is mapped to a file that does not exist. Subject: the class; path: the file. */
	public const CLASS_FILE_MISSING = 'class-file-missing';
	/** The mapped file exists and was included, but the class is still undeclared — a stale map. */
	public const CLASS_UNDECLARED = 'class-undeclared';

	/**
	 * @param string      $slug    Module slug (the manifest key).
	 * @param string      $phase   One of the four PHASE_* constants.
	 * @param string      $kind    One of the four kind constants.
	 * @param string      $subject The class (FQCN) or the runtime path relative to inc/.
	 * @param string|null $path    The mapped file, relative to the plugin root, when the kind has one.
	 */
	public function __construct(
		public readonly string $slug,
		public readonly string $phase,
		public readonly string $kind,
		public readonly string $subject,
		public readonly ?string $path = null
	) {}

	/**
	 * The English, untranslated log line — what blocklane_pro_log_failure()
	 * writes to the PHP error log, WP_DEBUG or not.
	 */
	public function describe(): string {
		$what = match ( $this->phase ) {
			self::PHASE_ROUTES    => sprintf( 'module "%s" booted but its REST routes did not register', $this->slug ),
			self::PHASE_CONTENT   => sprintf( 'the "%s" content runtime did not load, so saved content of that kind will not render', $this->slug ),
			self::PHASE_LIFECYCLE => sprintf( 'the "%s" lifecycle file did not load, so its once-per-version housekeeping was skipped', $this->slug ),
			default               => sprintf( 'module "%s" skipped at boot', $this->slug ),
		};
		switch ( $this->kind ) {
			case self::RUNTIME_MISSING:
				$why = sprintf( 'file inc/%s is missing', $this->subject );
				break;
			case self::CLASS_UNMAPPED:
				$why = sprintf( 'class %s is not in inc/classmap.php', $this->subject );
				break;
			case self::CLASS_FILE_MISSING:
				$why = sprintf( 'class %s is mapped to a missing file %s', $this->subject, (string) $this->path );
				break;
			default:
				$why = sprintf( 'file %s no longer declares class %s (inc/classmap.php is stale)', (string) $this->path, $this->subject );
		}
		// The classmap advice belongs only to the classmap kinds. A missing
		// runtime file is not a stale map, and telling an admin to regenerate
		// one sends them somewhere the fix does not live.
		$fix = in_array( $this->kind, array( self::CLASS_UNMAPPED, self::CLASS_UNDECLARED ), true )
			? '. Reinstall the plugin; in development run `php bin/generate-classmap.php`.'
			: '. Reinstall the plugin.';
		return $what . ' - ' . $why . $fix;
	}
}
