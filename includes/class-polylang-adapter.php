<?php
/**
 * Polylang integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CE_Content_Studio_Polylang_Adapter {
	/**
	 * Whether the supported Polylang assignment function is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return function_exists( 'pll_set_post_language' );
	}

	/**
	 * Assigns Turkish and reads it back when possible.
	 *
	 * @param int $post_id Post ID.
	 * @return string[] Non-blocking warnings.
	 */
	public function assign_turkish( $post_id ) {
		if ( ! $this->is_available() ) {
			return array( __( 'Polylang is not active. Confirm the draft language and permalink manually.', 'co-entrepreneur-content-studio' ) );
		}

		pll_set_post_language( $post_id, 'tr' );

		if ( function_exists( 'pll_get_post_language' ) && 'tr' !== pll_get_post_language( $post_id, 'slug' ) ) {
			return array( __( 'Polylang did not confirm Turkish after assignment. Check the draft manually.', 'co-entrepreneur-content-studio' ) );
		}

		return array();
	}
}
