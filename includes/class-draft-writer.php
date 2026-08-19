<?php
/**
 * WordPress draft creation and update service.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CE_Content_Studio_Draft_Writer {
	const CONTENT_ID_META_KEY   = '_ce_content_id';
	const PACKAGE_HASH_META_KEY = '_ce_package_hash';

	/** @var CE_Content_Studio_Rank_Math_Adapter */
	private $rank_math;

	/** @var CE_Content_Studio_Polylang_Adapter */
	private $polylang;

	/**
	 * Constructor.
	 *
	 * @param CE_Content_Studio_Rank_Math_Adapter $rank_math Rank Math adapter.
	 * @param CE_Content_Studio_Polylang_Adapter  $polylang Polylang adapter.
	 */
	public function __construct( CE_Content_Studio_Rank_Math_Adapter $rank_math, CE_Content_Studio_Polylang_Adapter $polylang ) {
		$this->rank_math = $rank_math;
		$this->polylang  = $polylang;
	}

	/**
	 * Finds a post linked to a content ID.
	 *
	 * @param string $content_id Content ID.
	 * @return WP_Post|WP_Error|null
	 */
	public function find_existing( $content_id ) {
		$post_ids = get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'any',
				'posts_per_page'   => 2,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'meta_key'         => self::CONTENT_ID_META_KEY,
				'meta_value'       => sanitize_text_field( $content_id ),
				'suppress_filters' => true,
			)
		);

		if ( count( $post_ids ) > 1 ) {
			return new WP_Error( 'duplicate_content_id', __( 'More than one post already uses this content_id. Resolve the duplicate manually before importing.', 'co-entrepreneur-content-studio' ) );
		}

		return empty( $post_ids ) ? null : get_post( (int) $post_ids[0] );
	}

	/**
	 * Returns integration warnings that should be visible before writing.
	 *
	 * @return string[]
	 */
	public function get_preflight_warnings() {
		$warnings = array();
		if ( ! $this->rank_math->is_available() ) {
			$warnings[] = __( 'Rank Math is not active. SEO fields will need manual review.', 'co-entrepreneur-content-studio' );
		}
		if ( ! $this->polylang->is_available() ) {
			$warnings[] = __( 'Polylang is not active. Language and permalink will need manual review.', 'co-entrepreneur-content-studio' );
		}
		return $warnings;
	}

	/**
	 * Creates or updates one draft.
	 *
	 * @param array $validation Validation result.
	 * @param bool  $confirm_update Whether updating an existing draft is confirmed.
	 * @param array $create_tag_slugs Missing tags explicitly approved for creation.
	 * @return array|WP_Error
	 */
	public function save( array $validation, $confirm_update, array $create_tag_slugs = array() ) {
		if ( empty( $validation['valid'] ) || empty( $validation['package'] ) ) {
			return new WP_Error( 'invalid_package', __( 'The package must pass validation before a draft can be written.', 'co-entrepreneur-content-studio' ) );
		}

		$package  = $validation['package'];
		$context  = $validation['context'];
		$existing = $this->find_existing( $package['content_id'] );

		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		if ( $existing instanceof WP_Post && 'draft' !== $existing->post_status ) {
			return new WP_Error(
				'published_content_id',
				__( 'This content_id belongs to a non-draft post. The plugin will not modify it.', 'co-entrepreneur-content-studio' ),
				array( 'post_id' => $existing->ID )
			);
		}

		if ( $existing instanceof WP_Post && ! $confirm_update ) {
			return new WP_Error( 'update_not_confirmed', __( 'Confirm the preview before updating the existing draft.', 'co-entrepreneur-content-studio' ) );
		}

		$tag_ids = isset( $context['tag_ids'] ) ? array_map( 'absint', $context['tag_ids'] ) : array();
		$missing = isset( $context['missing_tags'] ) ? $context['missing_tags'] : array();

		foreach ( array_intersect( $missing, array_map( 'sanitize_title', $create_tag_slugs ) ) as $slug ) {
			$created = wp_insert_term( $slug, 'post_tag', array( 'slug' => $slug ) );
			if ( is_wp_error( $created ) ) {
				if ( 'term_exists' === $created->get_error_code() ) {
					$tag_ids[] = (int) $created->get_error_data();
					continue;
				}
				return $created;
			}
			$tag_ids[] = (int) $created['term_id'];
		}

		$post_data = array(
			'post_type'     => 'post',
			'post_status'   => 'draft',
			'post_title'    => $package['title'],
			'post_name'     => $package['slug'],
			'post_content'  => $package['body_html'],
			'post_excerpt'  => $package['excerpt'],
			'post_author'   => (int) $package['author_id'],
			'post_category' => array_map( 'absint', $context['category_ids'] ),
		);

		if ( $existing instanceof WP_Post ) {
			$post_data['ID'] = $existing->ID;
		}

		$post_id = wp_insert_post( wp_slash( $post_data ), true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$tag_result = wp_set_post_terms( $post_id, array_values( array_unique( $tag_ids ) ), 'post_tag', false );
		if ( is_wp_error( $tag_result ) ) {
			return $tag_result;
		}

		$cluster_result = wp_set_post_terms( $post_id, array_map( 'absint', $context['cluster_ids'] ), 'content_cluster', false );
		if ( is_wp_error( $cluster_result ) ) {
			return $cluster_result;
		}

		update_post_meta( $post_id, self::CONTENT_ID_META_KEY, $package['content_id'] );
		update_post_meta( $post_id, self::PACKAGE_HASH_META_KEY, hash( 'sha256', wp_json_encode( $package ) ) );

		$warnings = $this->rank_math->save( $post_id, $package );
		$warnings = array_merge( $warnings, $this->polylang->assign_turkish( $post_id ) );

		if ( ! empty( $package['featured_image']['media_id'] ) ) {
			set_post_thumbnail( $post_id, (int) $package['featured_image']['media_id'] );
			if ( '' !== $package['featured_image']['alt_text'] ) {
				update_post_meta( (int) $package['featured_image']['media_id'], '_wp_attachment_image_alt', $package['featured_image']['alt_text'] );
			}
		}

		return array(
			'post_id'  => (int) $post_id,
			'updated'  => $existing instanceof WP_Post,
			'warnings' => $warnings,
		);
	}
}
