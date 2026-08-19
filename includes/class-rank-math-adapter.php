<?php
/**
 * Rank Math integration.
 *
 * The metadata keys are deliberately isolated here. They are present in Rank
 * Math's public source, but must still be smoke-tested against the exact version
 * installed on the target site before release.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CE_Content_Studio_Rank_Math_Adapter {
	const TITLE_META_KEY         = 'rank_math_title';
	const DESCRIPTION_META_KEY   = 'rank_math_description';
	const FOCUS_KEYWORD_META_KEY = 'rank_math_focus_keyword';

	/**
	 * Whether Rank Math appears to be active.
	 *
	 * @return bool
	 */
	public function is_available() {
		return defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' );
	}

	/**
	 * Saves the three V1 SEO fields.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $package Normalized package.
	 * @return string[] Non-blocking warnings.
	 */
	public function save( $post_id, array $package ) {
		if ( ! $this->is_available() ) {
			return array( __( 'Rank Math is not active. Add the SEO fields manually before publishing.', 'co-entrepreneur-content-studio' ) );
		}

		if ( ! empty( $package['seo_title'] ) ) {
			update_post_meta( $post_id, self::TITLE_META_KEY, sanitize_text_field( $package['seo_title'] ) );
		}

		if ( ! empty( $package['meta_description'] ) ) {
			update_post_meta( $post_id, self::DESCRIPTION_META_KEY, sanitize_textarea_field( $package['meta_description'] ) );
		}

		if ( ! empty( $package['focus_keyword'] ) ) {
			update_post_meta( $post_id, self::FOCUS_KEYWORD_META_KEY, sanitize_text_field( $package['focus_keyword'] ) );
		}

		return array();
	}
}
