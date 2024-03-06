<?php

namespace GFExcel\Shorttag;

use GFExcel\Addon\GravityExportAddon;
use GFExcel\GFExcel;

/**
 * A short tag handler for [gravityexport_download_url].
 * Example usage: [gravityexport_download_url id=1 type=csv]
 * Id is required, type is optional.
 * @since 1.6.1
 */
class DownloadUrl {
	/** @var string */
	public const SHORTTAG = 'gravityexport_download_url';

	/**
	 * The length of the secret used to protect the embed tag.
	 * @since $ver$
	 */
	private const SECRET_LENGTH = 6;

	public function __construct() {
		add_shortcode( 'gfexcel_download_url', [ $this, 'handle' ] ); // Backward compatible
		add_shortcode( self::SHORTTAG, [ $this, 'handle' ] );
		add_filter( 'gform_replace_merge_tags', [ $this, 'handleNotification' ], 10, 2 );
	}

	/**
	 * Handles the [gfexcel_download_url] shorttag.
	 * @since 1.6.1
	 *
	 * @param array|string $arguments
	 *
	 * @return string returns the replacing content, either a url or a message.
	 */
	public function handle( $arguments ) {
		if ( ! is_array( $arguments ) ) {
			$arguments = [];
		}

		if ( ! array_key_exists( 'id', $arguments ) ) {
			return $this->error( sprintf( 'Please add an `%s` argument to \'%s\' shorttag.', 'id', self::SHORTTAG ) );
		}

		$feed = GravityExportAddon::get_instance()->get_feed_by_form_id( $arguments['id'] );
		$hash = rgars( $feed, 'meta/hash' );

		if ( ! $feed || ! $hash ) {
			return $this->error( 'GravityExport: This form has no public download URL.' );
		}

		$is_protected = self::is_embed_protected( $feed );

		if ( ! $is_protected ) {
			return $this->getUrl( $arguments['id'], $arguments['type'] ?? null );
		}

		$secret = rgar( $arguments, 'secret', '' );

		if ( ! $this->validate_secret( $hash, $secret ) ) {
			return $this->error( sprintf( 'Please add a valid `%s` argument to the \'%s\' shorttag.', 'secret', self::SHORTTAG ) );
		}

		return $this->getUrl( $arguments['id'], $arguments['type'] ?? null );
	}

	/**
	 * Handles the short-tag for gravity forms.
	 * @since 1.6.1
	 *
	 * @param string $text the text of the notification
	 * @param array|false $form The form object.
	 *
	 * @return string The url or an error message
	 */
	public function handleNotification( $text, $form ) {
		if ( ! is_array( $form ) || ! isset( $form['id'] ) ) {
			return $text;
		}

		foreach ( [ self::SHORTTAG, 'gfexcel_download_url' ] as $short_tag ) {
			$custom_merge_tag = '{' . $short_tag . '}';

			if ( strpos( $text, $custom_merge_tag ) === false ) {
				continue;
			}

			$text = str_replace( $custom_merge_tag, $this->getUrl( $form['id'] ), $text );
		}

		return $text;
	}

	/**
	 * Get the actual url by providing a array with an id, and a type.
	 * @since 1.6.1
	 *
	 * @param int $id
	 * @param string|null $type either 'csv' or 'xlsx'.
	 *
	 * @return string
	 */
	private function getUrl( $id, $type = null ): string {
		$url = GFExcel::url( $id );

		if ( $type && in_array( strtolower( $type ), GFExcel::getPluginFileExtensions(), true ) ) {
			$url .= '.' . strtolower( $type );
		}

		return $url;
	}

	/**
	 * Returns the error message. Can be overwritten by filter hook.
	 * @since 1.6.1
	 *
	 * @param string $message The error message.
	 *
	 * @return string The filtered error message.
	 */
	private function error( string $message ): string {
		return (string) gf_apply_filters( [
			'gfexcel_shorttag_error',
		], $message );
	}

	/**
	 * Validates if the secret matches the hash.
	 * @since $ver$
	 *
	 * @param string $hash The hash.
	 * @param string $secret The secret.
	 *
	 * @return bool
	 */
	private function validate_secret( string $hash, string $secret ): bool {
		$test = self::get_secret( $hash );
		if ( strlen( $test ) !== self::SECRET_LENGTH || strlen( $secret ) !== self::SECRET_LENGTH ) {
			return false;
		}

		return $test === $secret;
	}

	/**
	 * Returns whether shortcode for this feed is protected.
	 * @since $ver$
	 *
	 * @param array $feed The feed object.
	 *
	 * @return bool Whether the embed is protected.
	 * @filter gk/gravityexport/embed/is-protected Enabled embed protection for all short tags.
	 */
	private static function is_embed_protected( array $feed ): bool {
		$is_global_embed_protected = (bool) apply_filters( 'gk/gravityexport/embed/is-protected', false );
		if ( $is_global_embed_protected ) {
			return true;
		}

		return (bool) rgars( $feed, 'meta/has_embed_secret', false );
	}

	/**
	 * Generates the secret from the hash.
	 * @since $ver$
	 *
	 * @param string $hash The hash.
	 *
	 * @return string The secret.
	 */
	public static function get_secret( string $hash ): string {
		return strrev( substr( $hash, self::SECRET_LENGTH, self::SECRET_LENGTH ) );
	}

	/**
	 * Returns the hash for a form.
	 * @since $ver$
	 *
	 * @param int $form_id The form id.
	 *
	 * @return string|null
	 */
	public static function get_form_hash( $form_id ): ?string {
		$feed = GravityExportAddon::get_instance()->get_feed_by_form_id( $form_id );

		return rgars( $feed ?? [], 'meta/hash' );
	}

	/**
	 * Generates the embed code for a form.
	 * @since $ver$
	 *
	 * @param int $form_id The form id.
	 * @param string|null $type The type of the download.
	 *
	 * @return string|null The embed code.
	 */
	public static function generate_embed_short_code( int $form_id, ?string $type = null ): ?string {
		$feed = GravityExportAddon::get_instance()->get_feed_by_form_id( $form_id );

		$hash = self::get_form_hash( $form_id );
		if ( ! $hash ) {
			return null;
		}

		$attributes = [ 'id' => $form_id ];
		if ( $type ) {
			$attributes['type'] = $type;
		}

		if ( self::is_embed_protected( $feed ) ) {
			$attributes['secret'] = self::get_secret( $hash );
		}

		foreach ( $attributes as $key => $value ) {
			$attributes[ $key ] = sprintf( '%s="%s"', $key, esc_attr( $value ) );
		}

		return sprintf( '[%s %s]', self::SHORTTAG, implode( ' ', $attributes ) );
	}
}
