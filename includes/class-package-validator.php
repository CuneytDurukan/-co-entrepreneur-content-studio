<?php
/**
 * Publication-package parsing and validation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CE_Content_Studio_Package_Validator {
	const MAX_PACKAGE_BYTES = 1048576;

	/**
	 * Parses and validates a JSON package.
	 *
	 * @param string $raw_json Raw JSON.
	 * @param array  $settings Plugin settings.
	 * @return array Validation result.
	 */
	public function validate( $raw_json, array $settings = array() ) {
		$errors   = array();
		$warnings = array();
		$context  = array(
			'category_ids'    => array(),
			'tag_ids'         => array(),
			'cluster_ids'     => array(),
			'missing_tags'    => array(),
			'internal_links'  => array(),
			'external_links'  => array(),
			'author'          => null,
			'existing_post'   => null,
		);

		$raw_json = trim( (string) $raw_json );

		if ( '' === $raw_json ) {
			$errors[] = __( 'Paste JSON or upload a JSON file.', 'co-entrepreneur-content-studio' );
			return $this->result( array(), $errors, $warnings, $context );
		}

		if ( strlen( $raw_json ) > self::MAX_PACKAGE_BYTES ) {
			$errors[] = __( 'The package is larger than the 1 MB limit.', 'co-entrepreneur-content-studio' );
			return $this->result( array(), $errors, $warnings, $context );
		}

		$decoded = json_decode( $raw_json, true, 32 );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			$errors[] = sprintf(
				/* translators: %s: JSON parser error. */
				__( 'Invalid JSON: %s', 'co-entrepreneur-content-studio' ),
				json_last_error_msg()
			);
			return $this->result( array(), $errors, $warnings, $context );
		}

		$package = $this->normalize_package( $decoded );

		if ( ! empty( $decoded['schema_version'] ) && ( ! is_scalar( $decoded['schema_version'] ) || '1.0' !== (string) $decoded['schema_version'] ) ) {
			$errors[] = __( 'Only schema_version 1.0 is supported.', 'co-entrepreneur-content-studio' );
		}

		foreach ( array( 'content_id', 'title', 'slug', 'body_html', 'language' ) as $required_key ) {
			if ( empty( $package[ $required_key ] ) ) {
				$errors[] = sprintf(
					/* translators: %s: package field name. */
					__( 'Missing required field: %s.', 'co-entrepreneur-content-studio' ),
					$required_key
				);
			}
		}

		if ( ! empty( $package['content_id'] ) && ! preg_match( '/^[a-z0-9][a-z0-9._-]{2,190}$/', $package['content_id'] ) ) {
			$errors[] = __( 'content_id must contain only lowercase letters, numbers, periods, underscores and hyphens.', 'co-entrepreneur-content-studio' );
		}

		if ( ! empty( $package['slug'] ) && sanitize_title( $package['slug'] ) !== $package['slug'] ) {
			$errors[] = __( 'slug must already be a valid lowercase WordPress slug.', 'co-entrepreneur-content-studio' );
		}

		if ( ! empty( $package['language'] ) && 'tr' !== $package['language'] ) {
			$errors[] = __( 'V1 supports Turkish packages with language set to tr.', 'co-entrepreneur-content-studio' );
		}

		if ( empty( $package['category_slugs'] ) ) {
			$errors[] = __( 'At least one category slug is required.', 'co-entrepreneur-content-studio' );
		}

		if ( empty( $package['content_cluster_slugs'] ) ) {
			$errors[] = __( 'At least one content_cluster slug is required.', 'co-entrepreneur-content-studio' );
		}

		$this->validate_body( $package, $errors, $warnings );
		$this->resolve_author( $decoded, $settings, $context, $errors, $warnings );
		$this->resolve_taxonomies( $package, $context, $errors, $warnings );
		$this->validate_featured_image( $package, $warnings, $errors );
		$this->validate_seo_lengths( $package, $warnings );

		$context['internal_links'] = $this->extract_links( $package['body_html'], true );
		$context['external_links'] = $this->extract_links( $package['body_html'], false );

		$this->validate_required_links( $package, $context['internal_links'], $errors );
		$this->validate_language_paths( $context['internal_links'], $warnings );
		$this->validate_preferred_domains( $context['external_links'], $settings, $warnings );

		if ( empty( $package['reciprocal_link_suggestions'] ) ) {
			$warnings[] = __( 'No reciprocal-link suggestions were supplied.', 'co-entrepreneur-content-studio' );
		}

		if ( empty( $package['excerpt'] ) ) {
			$warnings[] = __( 'The excerpt is empty.', 'co-entrepreneur-content-studio' );
		}

		if ( empty( $package['seo_title'] ) || empty( $package['meta_description'] ) ) {
			$warnings[] = __( 'SEO title or meta description is empty; Rank Math defaults will apply.', 'co-entrepreneur-content-studio' );
		}

		$package['body_html'] = self::sanitize_body_html( $package['body_html'] );
		$package['author_id'] = $context['author'] instanceof WP_User ? (int) $context['author']->ID : 0;

		return $this->result( $package, array_values( array_unique( $errors ) ), array_values( array_unique( $warnings ) ), $context );
	}

	/**
	 * Returns the allowed article HTML after sanitization.
	 *
	 * @param string $html Article HTML.
	 * @return string
	 */
	public static function sanitize_body_html( $html ) {
		$global = array(
			'class' => true,
			'id'    => true,
			'title' => true,
		);
		$allowed = array(
			'p'          => $global,
			'br'         => array(),
			'h2'         => $global,
			'h3'         => $global,
			'h4'         => $global,
			'h5'         => $global,
			'h6'         => $global,
			'strong'     => $global,
			'b'          => $global,
			'em'         => $global,
			'i'          => $global,
			'u'          => $global,
			'ul'         => $global,
			'ol'         => array_merge( $global, array( 'start' => true, 'reversed' => true ) ),
			'li'         => array_merge( $global, array( 'value' => true ) ),
			'a'          => array_merge( $global, array( 'href' => true, 'target' => true, 'rel' => true, 'aria-label' => true ) ),
			'blockquote' => array_merge( $global, array( 'cite' => true ) ),
			'cite'       => $global,
			'code'       => $global,
			'pre'        => $global,
			'hr'         => $global,
			'table'      => $global,
			'thead'      => $global,
			'tbody'      => $global,
			'tfoot'      => $global,
			'tr'         => $global,
			'th'         => array_merge( $global, array( 'colspan' => true, 'rowspan' => true, 'scope' => true ) ),
			'td'         => array_merge( $global, array( 'colspan' => true, 'rowspan' => true ) ),
			'figure'     => $global,
			'figcaption' => $global,
			'img'        => array_merge(
				$global,
				array(
					'src'     => true,
					'alt'     => true,
					'width'   => true,
					'height'  => true,
					'loading' => true,
					'srcset'  => true,
					'sizes'   => true,
				)
			),
			'span'       => $global,
			'div'        => $global,
			'sup'        => $global,
			'sub'        => $global,
		);

		return wp_kses( (string) $html, $allowed, array( 'http', 'https', 'mailto', 'tel' ) );
	}

	/**
	 * Normalizes package values without performing writes.
	 *
	 * @param array $decoded Decoded JSON.
	 * @return array
	 */
	private function normalize_package( array $decoded ) {
		$package = array(
			'schema_version'             => isset( $decoded['schema_version'] ) ? sanitize_text_field( $this->scalar_string( $decoded['schema_version'] ) ) : '1.0',
			'content_id'                 => sanitize_text_field( $this->value_string( $decoded, 'content_id' ) ),
			'language'                   => sanitize_key( $this->value_string( $decoded, 'language' ) ),
			'title'                      => sanitize_text_field( $this->value_string( $decoded, 'title' ) ),
			'seo_title'                  => sanitize_text_field( $this->value_string( $decoded, 'seo_title' ) ),
			'meta_description'           => sanitize_textarea_field( $this->value_string( $decoded, 'meta_description' ) ),
			'focus_keyword'              => sanitize_text_field( $this->value_string( $decoded, 'focus_keyword' ) ),
			'slug'                       => $this->value_string( $decoded, 'slug' ),
			'excerpt'                    => sanitize_textarea_field( $this->value_string( $decoded, 'excerpt' ) ),
			'body_html'                  => $this->value_string( $decoded, 'body_html' ),
			'category_slugs'             => $this->normalize_slugs( isset( $decoded['category_slugs'] ) ? $decoded['category_slugs'] : array() ),
			'tag_slugs'                  => $this->normalize_slugs( isset( $decoded['tag_slugs'] ) ? $decoded['tag_slugs'] : array() ),
			'content_cluster_slugs'      => $this->normalize_slugs( isset( $decoded['content_cluster_slugs'] ) ? $decoded['content_cluster_slugs'] : array() ),
			'required_internal_urls'     => $this->normalize_urls( isset( $decoded['required_internal_urls'] ) ? $decoded['required_internal_urls'] : array() ),
			'reciprocal_link_suggestions' => $this->normalize_reciprocal_suggestions( isset( $decoded['reciprocal_link_suggestions'] ) ? $decoded['reciprocal_link_suggestions'] : array() ),
			'featured_image'             => isset( $decoded['featured_image'] ) && is_array( $decoded['featured_image'] ) ? array(
				'media_id' => isset( $decoded['featured_image']['media_id'] ) ? absint( $decoded['featured_image']['media_id'] ) : 0,
				'alt_text' => isset( $decoded['featured_image']['alt_text'] ) ? sanitize_text_field( $this->scalar_string( $decoded['featured_image']['alt_text'] ) ) : '',
			) : null,
		);

		return $package;
	}

	/**
	 * Validates HTML and heading structure.
	 *
	 * @param array    $package Package.
	 * @param string[] $errors Errors.
	 * @param string[] $warnings Warnings.
	 * @return void
	 */
	private function validate_body( array $package, array &$errors, array &$warnings ) {
		$html = $package['body_html'];

		if ( preg_match( '/<h1\b/i', $html ) ) {
			$errors[] = __( 'body_html must not contain an h1 heading.', 'co-entrepreneur-content-studio' );
		}

		$placeholder_patterns = array( 'INTERNAL:', '[SOYADI]', 'REPLACE_DURING_SETUP' );
		foreach ( $placeholder_patterns as $placeholder ) {
			if ( false !== stripos( $html, $placeholder ) ) {
				$errors[] = sprintf(
					/* translators: %s: placeholder text. */
					__( 'Remove placeholder before import: %s.', 'co-entrepreneur-content-studio' ),
					$placeholder
				);
			}
		}
		if ( preg_match( '/href\s*=\s*(["\'])#\1/i', $html ) ) {
			$errors[] = __( 'Remove placeholder before import: href="#".', 'co-entrepreneur-content-studio' );
		}

		if ( preg_match( '/<(script|iframe|object|embed|form)\b/i', $html ) || preg_match( '/\son[a-z]+\s*=/i', $html ) || preg_match( '/(?:href|src)\s*=\s*["\']?\s*(?:javascript|data)\s*:/i', $html ) ) {
			$errors[] = __( 'body_html contains active or executable content.', 'co-entrepreneur-content-studio' );
		}

		if ( preg_match_all( '/<h([2-6])\b/i', $html, $matches ) && ! empty( $matches[1] ) ) {
			$previous = (int) reset( $matches[1] );
			foreach ( array_slice( $matches[1], 1 ) as $level ) {
				$level = (int) $level;
				if ( $level > $previous + 1 ) {
					$warnings[] = __( 'The heading order skips a level.', 'co-entrepreneur-content-studio' );
					break;
				}
				$previous = $level;
			}
		}

		if ( self::sanitize_body_html( $html ) !== $html ) {
			$warnings[] = __( 'Unsupported HTML or attributes will be removed when the draft is created.', 'co-entrepreneur-content-studio' );
		}
	}

	/**
	 * Resolves the package/default author.
	 *
	 * @param array    $decoded Raw package.
	 * @param array    $settings Settings.
	 * @param array    $context Context.
	 * @param string[] $errors Errors.
	 * @param string[] $warnings Warnings.
	 * @return void
	 */
	private function resolve_author( array $decoded, array $settings, array &$context, array &$errors, array &$warnings ) {
		$author = null;

		if ( isset( $decoded['author'] ) && is_numeric( $decoded['author'] ) ) {
			$author = get_user_by( 'id', absint( $decoded['author'] ) );
		} elseif ( ! empty( $decoded['author'] ) && is_string( $decoded['author'] ) ) {
			$author = get_user_by( 'login', sanitize_user( $decoded['author'] ) );
		} elseif ( ! empty( $settings['default_author_id'] ) ) {
			$author = get_user_by( 'id', absint( $settings['default_author_id'] ) );
		} else {
			$author     = wp_get_current_user();
			$warnings[] = __( 'No default author is configured; the current administrator will be used.', 'co-entrepreneur-content-studio' );
		}

		if ( ! $author instanceof WP_User || ! $author->exists() ) {
			$errors[] = __( 'The requested or configured author does not exist.', 'co-entrepreneur-content-studio' );
			return;
		}

		$context['author'] = $author;
	}

	/**
	 * Resolves existing terms and identifies missing tags.
	 *
	 * @param array    $package Package.
	 * @param array    $context Context.
	 * @param string[] $errors Errors.
	 * @param string[] $warnings Warnings.
	 * @return void
	 */
	private function resolve_taxonomies( array $package, array &$context, array &$errors, array &$warnings ) {
		foreach ( $package['category_slugs'] as $slug ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( ! $term instanceof WP_Term ) {
				$errors[] = sprintf( __( 'Category does not exist: %s.', 'co-entrepreneur-content-studio' ), $slug );
				continue;
			}
			$context['category_ids'][] = (int) $term->term_id;
		}

		if ( ! taxonomy_exists( 'content_cluster' ) || ! is_object_in_taxonomy( 'post', 'content_cluster' ) ) {
			$errors[] = __( 'The content_cluster taxonomy is not registered for posts.', 'co-entrepreneur-content-studio' );
		} else {
			foreach ( $package['content_cluster_slugs'] as $slug ) {
				$term = get_term_by( 'slug', $slug, 'content_cluster' );
				if ( ! $term instanceof WP_Term ) {
					$errors[] = sprintf( __( 'Content cluster does not exist: %s.', 'co-entrepreneur-content-studio' ), $slug );
					continue;
				}
				$context['cluster_ids'][] = (int) $term->term_id;
			}
		}

		foreach ( $package['tag_slugs'] as $slug ) {
			$term = get_term_by( 'slug', $slug, 'post_tag' );
			if ( ! $term instanceof WP_Term ) {
				$context['missing_tags'][] = $slug;
				$warnings[]                = sprintf( __( 'Tag does not exist: %s.', 'co-entrepreneur-content-studio' ), $slug );
				continue;
			}
			$context['tag_ids'][] = (int) $term->term_id;
		}
	}

	/**
	 * Validates supplied media.
	 *
	 * @param array    $package Package.
	 * @param string[] $warnings Warnings.
	 * @param string[] $errors Errors.
	 * @return void
	 */
	private function validate_featured_image( array $package, array &$warnings, array &$errors ) {
		if ( empty( $package['featured_image'] ) ) {
			$warnings[] = __( 'No featured image was supplied.', 'co-entrepreneur-content-studio' );
			return;
		}

		$media_id = $package['featured_image']['media_id'];
		if ( ! $media_id || 'attachment' !== get_post_type( $media_id ) || ! wp_attachment_is_image( $media_id ) ) {
			$errors[] = __( 'featured_image.media_id must reference an existing image attachment.', 'co-entrepreneur-content-studio' );
		}

		if ( empty( $package['featured_image']['alt_text'] ) ) {
			$warnings[] = __( 'The featured image has no supplied alt text.', 'co-entrepreneur-content-studio' );
		}
	}

	/**
	 * Adds advisory SEO-length warnings.
	 *
	 * @param array    $package Package.
	 * @param string[] $warnings Warnings.
	 * @return void
	 */
	private function validate_seo_lengths( array $package, array &$warnings ) {
		$title_length       = $this->text_length( $package['seo_title'] );
		$description_length = $this->text_length( $package['meta_description'] );

		if ( $title_length && ( $title_length < 30 || $title_length > 60 ) ) {
			$warnings[] = __( 'The SEO title is outside the usual 30–60 character preview range.', 'co-entrepreneur-content-studio' );
		}

		if ( $description_length && ( $description_length < 100 || $description_length > 160 ) ) {
			$warnings[] = __( 'The meta description is outside the usual 100–160 character preview range.', 'co-entrepreneur-content-studio' );
		}
	}

	/**
	 * Confirms required URLs exist among article links.
	 *
	 * @param array    $package Package.
	 * @param array    $internal_links Internal links.
	 * @param string[] $errors Errors.
	 * @return void
	 */
	private function validate_required_links( array $package, array $internal_links, array &$errors ) {
		$found = array_map( array( $this, 'normalize_comparable_url' ), wp_list_pluck( $internal_links, 'url' ) );

		foreach ( $package['required_internal_urls'] as $required_url ) {
			if ( ! in_array( $this->normalize_comparable_url( $required_url ), $found, true ) ) {
				$errors[] = sprintf( __( 'Required internal URL is missing from body_html: %s', 'co-entrepreneur-content-studio' ), $required_url );
			}
		}
	}

	/**
	 * Warns about internal links outside /tr/.
	 *
	 * @param array    $internal_links Internal links.
	 * @param string[] $warnings Warnings.
	 * @return void
	 */
	private function validate_language_paths( array $internal_links, array &$warnings ) {
		foreach ( $internal_links as $link ) {
			$path = wp_parse_url( $link['url'], PHP_URL_PATH );
			if ( $path && 0 !== strpos( trailingslashit( $path ), '/tr/' ) ) {
				$warnings[] = sprintf( __( 'Internal URL may use the wrong language path: %s', 'co-entrepreneur-content-studio' ), $link['url'] );
			}
		}
	}

	/**
	 * Warns about external domains outside the preferred list.
	 *
	 * @param array    $external_links External links.
	 * @param array    $settings Settings.
	 * @param string[] $warnings Warnings.
	 * @return void
	 */
	private function validate_preferred_domains( array $external_links, array $settings, array &$warnings ) {
		$preferred = isset( $settings['preferred_domains'] ) && is_array( $settings['preferred_domains'] ) ? $settings['preferred_domains'] : array();
		if ( empty( $preferred ) ) {
			return;
		}

		foreach ( $external_links as $link ) {
			$host     = strtolower( (string) wp_parse_url( $link['url'], PHP_URL_HOST ) );
			$approved = false;
			foreach ( $preferred as $domain ) {
				$domain = strtolower( $domain );
				if ( $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain ) {
					$approved = true;
					break;
				}
			}
			if ( ! $approved ) {
				$warnings[] = sprintf( __( 'External domain is not on the preferred-source list: %s', 'co-entrepreneur-content-studio' ), $host );
			}
		}
	}

	/**
	 * Extracts internal or external HTTP links from HTML.
	 *
	 * @param string $html HTML.
	 * @param bool   $internal Whether to return internal links.
	 * @return array
	 */
	private function extract_links( $html, $internal ) {
		$links     = array();
		$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

		if ( ! preg_match_all( '/<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER ) ) {
			return $links;
		}

		foreach ( $matches as $match ) {
			$url = html_entity_decode( trim( $match[2] ), ENT_QUOTES, 'UTF-8' );
			if ( 0 === strpos( $url, '/' ) ) {
				$url = home_url( $url );
			}

			$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
			$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || ! $host ) {
				continue;
			}

			$is_internal = $host === $site_host;
			if ( $is_internal !== (bool) $internal ) {
				continue;
			}

			$links[] = array(
				'url'    => esc_url_raw( $url ),
				'anchor' => sanitize_text_field( wp_strip_all_tags( $match[3] ) ),
			);
		}

		return $links;
	}

	/**
	 * Normalizes a slug array.
	 *
	 * @param mixed $values Values.
	 * @return string[]
	 */
	private function normalize_slugs( $values ) {
		if ( ! is_array( $values ) ) {
			return array();
		}
		$values = array_filter( $values, 'is_scalar' );
		return array_values( array_unique( array_filter( array_map( 'sanitize_title', $values ) ) ) );
	}

	/**
	 * Normalizes a URL array.
	 *
	 * @param mixed $values Values.
	 * @return string[]
	 */
	private function normalize_urls( $values ) {
		if ( ! is_array( $values ) ) {
			return array();
		}
		$values = array_filter( $values, 'is_scalar' );
		return array_values( array_unique( array_filter( array_map( 'esc_url_raw', $values ) ) ) );
	}

	/**
	 * Normalizes reciprocal suggestions.
	 *
	 * @param mixed $suggestions Suggestions.
	 * @return array
	 */
	private function normalize_reciprocal_suggestions( $suggestions ) {
		$normalized = array();
		if ( ! is_array( $suggestions ) ) {
			return $normalized;
		}

		foreach ( $suggestions as $suggestion ) {
			if ( ! is_array( $suggestion ) || empty( $suggestion['source_post_id'] ) ) {
				continue;
			}
			$normalized[] = array(
				'source_post_id' => is_scalar( $suggestion['source_post_id'] ) ? absint( $suggestion['source_post_id'] ) : 0,
				'anchor'         => isset( $suggestion['anchor'] ) ? sanitize_text_field( $this->scalar_string( $suggestion['anchor'] ) ) : '',
			);
		}

		return $normalized;
	}

	/**
	 * Produces a stable URL comparison value.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function normalize_comparable_url( $url ) {
		return untrailingslashit( strtolower( esc_url_raw( $url ) ) );
	}

	/**
	 * Multibyte-safe length where available.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private function text_length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	}

	/**
	 * Returns one decoded field only when it is scalar.
	 *
	 * @param array  $values Values.
	 * @param string $key Key.
	 * @return string
	 */
	private function value_string( array $values, $key ) {
		return isset( $values[ $key ] ) ? $this->scalar_string( $values[ $key ] ) : '';
	}

	/**
	 * Safely converts a JSON scalar to text.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function scalar_string( $value ) {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Packages validation output.
	 *
	 * @param array    $package Package.
	 * @param string[] $errors Errors.
	 * @param string[] $warnings Warnings.
	 * @param array    $context Resolved context.
	 * @return array
	 */
	private function result( array $package, array $errors, array $warnings, array $context ) {
		return array(
			'package'  => $package,
			'errors'   => $errors,
			'warnings' => $warnings,
			'context'  => $context,
			'valid'    => empty( $errors ),
		);
	}
}
