<?php
/**
 * SVG image "editor" for the WordPress image pipeline (Safe SVG Uploads).
 *
 * Registered ahead of GD/Imagick via the wp_image_editors filter, claiming only
 * SVGs (test()), so flows that insist on editing an image — the Site Icon
 * cropper, custom header crops — succeed for SVGs instead of dying with
 * "There has been an error cropping your image." Vectors scale losslessly, so
 * crop/resize/rotate/flip are no-ops and save() copies the (already sanitized
 * on upload) file as-is.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Svg_Image_Editor extends \WP_Image_Editor {

	/**
	 * Claim SVGs only; every other type falls through to GD/Imagick.
	 *
	 * Core's mime map has no svg entry, so for .svg paths $args['mime_type']
	 * arrives unset — match on the path extension in that case.
	 *
	 * @param array $args Editor selection args (path, mime_type, methods).
	 * @return bool
	 */
	public static function test( $args = array() ) {
		if ( isset( $args['mime_type'] ) ) {
			return 'image/svg+xml' === $args['mime_type'];
		}

		return isset( $args['path'] ) && (bool) preg_match( '/\.svg$/i', (string) $args['path'] );
	}

	public static function supports_mime_type( $mime_type ) {
		return 'image/svg+xml' === $mime_type;
	}

	/*
	 * These errors are worded as OUR strings, in our own text domain, even
	 * though this class stands in for core's WP_Image_Editor and core has
	 * near-identical ones. Borrowing core's wording means calling __() with
	 * core's 'default' domain, and wordpress.org refuses that both ways: a
	 * bare __() reads as a forgotten domain, and naming 'default' explicitly
	 * reads as a domain mismatch. Free translations are not worth shipping a
	 * plugin that argues with the directory's checker.
	 */
	public function load() {
		if ( ! is_readable( $this->file ) ) {
			return new \WP_Error( 'error_loading_image', __( 'That file no longer exists.', 'blocklane' ), $this->file );
		}

		$contents = file_get_contents( $this->file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file, not remote.
		if ( false === $contents || false === stripos( $contents, '<svg' ) ) {
			return new \WP_Error( 'invalid_image', __( 'That file is not an SVG image.', 'blocklane' ), $this->file );
		}

		$this->mime_type = 'image/svg+xml';

		$size = Advanced::svg_intrinsic_dimensions( $contents );
		$this->update_size( $size['width'], $size['height'] );

		return true;
	}

	/**
	 * "Save" by copying the file: the source was sanitized on upload and no
	 * edit operation changes a vector, so the original bytes are the output.
	 */
	public function save( $destfilename = null, $mime_type = null ) {
		unset( $mime_type ); // Output is always image/svg+xml.

		$filename = $destfilename ? $destfilename : $this->generate_filename( null, null, 'svg' );

		wp_mkdir_p( dirname( $filename ) );

		if ( $filename !== $this->file && ! copy( $this->file, $filename ) ) {
			return new \WP_Error( 'image_save_error', __( 'The image could not be saved.', 'blocklane' ), $filename );
		}

		$size = $this->get_size();

		return array(
			'path'      => $filename,
			/** This filter is documented in wp-includes/class-wp-image-editor-gd.php */
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own filter, applied here so the hooks other plugins attach to core's editors run for SVGs too.
			'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
			'width'     => $size['width'],
			'height'    => $size['height'],
			'mime-type' => 'image/svg+xml',
			'filesize'  => wp_filesize( $filename ),
		);
	}

	// Vector operations are lossless no-ops — the file is served as uploaded.
	public function resize( $max_w, $max_h, $crop = false ) {
		return true;
	}

	public function multi_resize( $sizes ) {
		return array();
	}

	public function crop( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false ) {
		return true;
	}

	public function rotate( $angle ) {
		return true;
	}

	public function flip( $horz, $vert ) {
		return true;
	}

	public function stream( $mime_type = null ) {
		unset( $mime_type );

		header( 'Content-Type: image/svg+xml' );

		readfile( $this->file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a local file is the method contract.

		return true;
	}
}
