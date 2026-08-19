<?php
/**
 * Single WordPress-native import screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CE_Content_Studio_Import_Screen {
	const MENU_SLUG      = 'ce-content-studio';
	const SETTINGS_KEY   = 'ce_content_studio_settings';
	const NONCE_ACTION   = 'ce_content_studio_import';
	const MAX_FILE_BYTES = 1048576;

	/** @var CE_Content_Studio_Package_Validator */
	private $validator;

	/** @var CE_Content_Studio_Draft_Writer */
	private $writer;

	/** @var string */
	private $raw_json = '';

	/** @var array|null */
	private $validation;

	/** @var string[] */
	private $screen_errors = array();

	/** @var string[] */
	private $screen_notices = array();

	/**
	 * Constructor.
	 *
	 * @param CE_Content_Studio_Package_Validator $validator Validator.
	 * @param CE_Content_Studio_Draft_Writer      $writer Writer.
	 */
	public function __construct( CE_Content_Studio_Package_Validator $validator, CE_Content_Studio_Draft_Writer $writer ) {
		$this->validator = $validator;
		$this->writer    = $writer;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_request' ) );
		add_action( 'admin_notices', array( $this, 'render_saved_post_notice' ) );
	}

	/**
	 * Registers the single top-level admin page.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Content Studio', 'co-entrepreneur-content-studio' ),
			__( 'Content Studio', 'co-entrepreneur-content-studio' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-media-document',
			25
		);
	}

	/**
	 * Handles settings, preview and create actions.
	 *
	 * @return void
	 */
	public function handle_request() {
		if ( ! is_admin() || ! isset( $_GET['page'] ) || self::MENU_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to use Content Studio.', 'co-entrepreneur-content-studio' ) );
		}

		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			return;
		}

		check_admin_referer( self::NONCE_ACTION, 'ce_nonce' );

		$action = isset( $_POST['ce_action'] ) ? sanitize_key( wp_unslash( $_POST['ce_action'] ) ) : '';
		if ( 'save_settings' === $action ) {
			$this->save_settings();
			return;
		}

		$this->raw_json = $this->read_package_input();
		if ( '' === $this->raw_json ) {
			return;
		}

		$this->validation = $this->validator->validate( $this->raw_json, $this->get_settings() );
		$this->validation['warnings'] = array_values(
			array_unique( array_merge( $this->validation['warnings'], $this->writer->get_preflight_warnings() ) )
		);
		$this->attach_existing_post();

		if ( 'create' !== $action || empty( $this->validation['valid'] ) || ! empty( $this->screen_errors ) ) {
			return;
		}

		$create_tags = isset( $_POST['create_tag_slugs'] ) && is_array( $_POST['create_tag_slugs'] )
			? array_map( 'sanitize_title', wp_unslash( $_POST['create_tag_slugs'] ) )
			: array();
		$confirmed   = ! empty( $_POST['confirm_update'] );
		$result      = $this->writer->save( $this->validation, $confirmed, $create_tags );

		if ( is_wp_error( $result ) ) {
			$this->screen_errors[] = $result->get_error_message();
			$data                  = $result->get_error_data();
			if ( is_array( $data ) && ! empty( $data['post_id'] ) ) {
				$this->validation['context']['existing_post'] = get_post( (int) $data['post_id'] );
			}
			return;
		}

		$edit_url = get_edit_post_link( $result['post_id'], 'raw' );
		if ( $edit_url ) {
			wp_safe_redirect( add_query_arg( 'ce_content_studio_saved', '1', $edit_url ) );
			exit;
		}

		$this->screen_notices[] = __( 'Draft saved, but WordPress did not return an edit link.', 'co-entrepreneur-content-studio' );
	}

	/**
	 * Shows edit/preview links after redirecting to the native editor.
	 *
	 * @return void
	 */
	public function render_saved_post_notice() {
		if ( empty( $_GET['ce_content_studio_saved'] ) || empty( $_GET['post'] ) ) {
			return;
		}

		$post_id = absint( $_GET['post'] );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$preview_url = get_preview_post_link( $post_id );
		$import_url  = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php esc_html_e( 'Content Studio saved this post as a draft.', 'co-entrepreneur-content-studio' ); ?>
				<?php if ( $preview_url ) : ?>
					<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview draft', 'co-entrepreneur-content-studio' ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( $import_url ); ?>"><?php esc_html_e( 'Import another package', 'co-entrepreneur-content-studio' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders the complete page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Content Studio — Import Draft', 'co-entrepreneur-content-studio' ); ?></h1>
			<p><?php esc_html_e( 'Paste or upload one publication package, review the preview, then create a WordPress draft.', 'co-entrepreneur-content-studio' ); ?></p>

			<?php $this->render_notices(); ?>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( self::NONCE_ACTION, 'ce_nonce' ); ?>
				<input type="hidden" name="ce_action" value="preview">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ce_package_json"><?php esc_html_e( 'Publication package JSON', 'co-entrepreneur-content-studio' ); ?></label></th>
						<td>
							<textarea id="ce_package_json" name="ce_package_json" rows="16" class="large-text code"><?php echo esc_textarea( $this->raw_json ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Use either pasted JSON or a file, not both.', 'co-entrepreneur-content-studio' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ce_package_file"><?php esc_html_e( 'JSON file', 'co-entrepreneur-content-studio' ); ?></label></th>
						<td><input id="ce_package_file" name="ce_package_file" type="file" accept="application/json,.json"></td>
					</tr>
				</table>

				<?php submit_button( __( 'Preview Package', 'co-entrepreneur-content-studio' ) ); ?>
			</form>

			<?php
			if ( is_array( $this->validation ) ) {
				$this->render_preview();
			}
			?>

			<hr>
			<h2><?php esc_html_e( 'Settings', 'co-entrepreneur-content-studio' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( self::NONCE_ACTION, 'ce_nonce' ); ?>
				<input type="hidden" name="ce_action" value="save_settings">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ce_default_author_id"><?php esc_html_e( 'Default author', 'co-entrepreneur-content-studio' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_users(
								array(
									'name'             => 'default_author_id',
									'id'               => 'ce_default_author_id',
									'selected'         => $settings['default_author_id'],
									'show_option_none' => __( 'Use the importing administrator', 'co-entrepreneur-content-studio' ),
								)
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ce_preferred_domains"><?php esc_html_e( 'Preferred external domains', 'co-entrepreneur-content-studio' ); ?></label></th>
						<td>
							<textarea id="ce_preferred_domains" name="preferred_domains" rows="5" class="large-text code"><?php echo esc_textarea( implode( "\n", $settings['preferred_domains'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One hostname per line. Other external domains produce a warning only.', 'co-entrepreneur-content-studio' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Settings', 'co-entrepreneur-content-studio' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders validation notices.
	 *
	 * @return void
	 */
	private function render_notices() {
		$errors   = $this->screen_errors;
		$warnings = array();

		if ( is_array( $this->validation ) ) {
			$errors   = array_merge( $errors, $this->validation['errors'] );
			$warnings = $this->validation['warnings'];
		}

		if ( ! empty( $errors ) ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Draft creation is blocked:', 'co-entrepreneur-content-studio' ) . '</strong></p><ul>';
			foreach ( array_unique( $errors ) as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			echo '</ul></div>';
		}

		if ( ! empty( $warnings ) ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Warnings:', 'co-entrepreneur-content-studio' ) . '</strong></p><ul>';
			foreach ( array_unique( $warnings ) as $warning ) {
				echo '<li>' . esc_html( $warning ) . '</li>';
			}
			echo '</ul></div>';
		}

		foreach ( $this->screen_notices as $notice ) {
			echo '<div class="notice notice-success"><p>' . esc_html( $notice ) . '</p></div>';
		}
	}

	/**
	 * Renders parsed fields and the guarded create action.
	 *
	 * @return void
	 */
	private function render_preview() {
		$package  = $this->validation['package'];
		$context  = $this->validation['context'];
		$existing = isset( $context['existing_post'] ) ? $context['existing_post'] : null;

		if ( empty( $package ) ) {
			return;
		}
		?>
		<hr>
		<h2><?php esc_html_e( 'Package Preview', 'co-entrepreneur-content-studio' ); ?></h2>
		<table class="widefat striped" style="max-width:1000px">
			<tbody>
				<?php $this->render_preview_row( __( 'Content ID', 'co-entrepreneur-content-studio' ), $package['content_id'] ); ?>
				<?php $this->render_preview_row( __( 'Title', 'co-entrepreneur-content-studio' ), $package['title'] ); ?>
				<?php $this->render_preview_row( __( 'Slug', 'co-entrepreneur-content-studio' ), $package['slug'] ); ?>
				<?php $this->render_preview_row( __( 'Excerpt', 'co-entrepreneur-content-studio' ), $package['excerpt'] ); ?>
				<?php $this->render_preview_row( __( 'SEO title', 'co-entrepreneur-content-studio' ), $package['seo_title'] ); ?>
				<?php $this->render_preview_row( __( 'Meta description', 'co-entrepreneur-content-studio' ), $package['meta_description'] ); ?>
				<?php $this->render_preview_row( __( 'Focus keyword', 'co-entrepreneur-content-studio' ), $package['focus_keyword'] ); ?>
				<?php $this->render_preview_row( __( 'Language', 'co-entrepreneur-content-studio' ), $package['language'] ); ?>
				<?php $this->render_preview_row( __( 'Author', 'co-entrepreneur-content-studio' ), $context['author'] instanceof WP_User ? $context['author']->display_name : '' ); ?>
				<?php $this->render_preview_row( __( 'Categories', 'co-entrepreneur-content-studio' ), implode( ', ', $package['category_slugs'] ) ); ?>
				<?php $this->render_preview_row( __( 'Tags', 'co-entrepreneur-content-studio' ), implode( ', ', $package['tag_slugs'] ) ); ?>
				<?php $this->render_preview_row( __( 'Content cluster', 'co-entrepreneur-content-studio' ), implode( ', ', $package['content_cluster_slugs'] ) ); ?>
				<?php $this->render_preview_row( __( 'Featured image', 'co-entrepreneur-content-studio' ), $this->featured_image_summary( $package ) ); ?>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Sanitized article preview', 'co-entrepreneur-content-studio' ); ?></h3>
		<div style="max-width:960px;padding:16px;background:#fff;border:1px solid #c3c4c7">
			<?php echo CE_Content_Studio_Package_Validator::sanitize_body_html( $package['body_html'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Explicit allowlist. ?>
		</div>

		<?php $this->render_link_summary( $context, $package ); ?>

		<?php if ( $existing instanceof WP_Post ) : ?>
			<p>
				<strong><?php esc_html_e( 'Existing post:', 'co-entrepreneur-content-studio' ); ?></strong>
				<a href="<?php echo esc_url( get_edit_post_link( $existing->ID, 'raw' ) ); ?>"><?php echo esc_html( get_the_title( $existing ) ); ?></a>
				(<?php echo esc_html( $existing->post_status ); ?>)
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $this->validation['valid'] ) && empty( $this->screen_errors ) && ( ! ( $existing instanceof WP_Post ) || 'draft' === $existing->post_status ) ) : ?>
			<form method="post">
				<?php wp_nonce_field( self::NONCE_ACTION, 'ce_nonce' ); ?>
				<input type="hidden" name="ce_action" value="create">
				<textarea name="ce_package_json" hidden><?php echo esc_textarea( $this->raw_json ); ?></textarea>

				<?php if ( ! empty( $context['missing_tags'] ) ) : ?>
					<fieldset>
						<legend><strong><?php esc_html_e( 'Missing tags', 'co-entrepreneur-content-studio' ); ?></strong></legend>
						<p><?php esc_html_e( 'Select only the tags that should be created. Their slug will also be used as the initial display name.', 'co-entrepreneur-content-studio' ); ?></p>
						<?php foreach ( $context['missing_tags'] as $slug ) : ?>
							<label style="display:block"><input type="checkbox" name="create_tag_slugs[]" value="<?php echo esc_attr( $slug ); ?>"> <?php echo esc_html( $slug ); ?></label>
						<?php endforeach; ?>
					</fieldset>
				<?php endif; ?>

				<?php if ( $existing instanceof WP_Post ) : ?>
					<p><label><input type="checkbox" name="confirm_update" value="1" required> <?php esc_html_e( 'I reviewed this package and confirm updating the existing draft.', 'co-entrepreneur-content-studio' ); ?></label></p>
				<?php endif; ?>

				<?php
				submit_button(
					$existing instanceof WP_Post
						? __( 'Update Existing Draft', 'co-entrepreneur-content-studio' )
						: __( 'Create Draft', 'co-entrepreneur-content-studio' ),
					'primary',
					'submit',
					false
				);
				?>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders internal and reciprocal link summaries.
	 *
	 * @param array $context Validation context.
	 * @param array $package Package.
	 * @return void
	 */
	private function render_link_summary( array $context, array $package ) {
		?>
		<h3><?php esc_html_e( 'Internal links found', 'co-entrepreneur-content-studio' ); ?></h3>
		<?php if ( empty( $context['internal_links'] ) ) : ?>
			<p><?php esc_html_e( 'None.', 'co-entrepreneur-content-studio' ); ?></p>
		<?php else : ?>
			<ul>
				<?php foreach ( $context['internal_links'] as $link ) : ?>
					<li><a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $link['anchor'] ? $link['anchor'] : $link['url'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Reciprocal-link suggestions', 'co-entrepreneur-content-studio' ); ?></h3>
		<?php if ( empty( $package['reciprocal_link_suggestions'] ) ) : ?>
			<p><?php esc_html_e( 'None.', 'co-entrepreneur-content-studio' ); ?></p>
		<?php else : ?>
			<ul>
				<?php foreach ( $package['reciprocal_link_suggestions'] as $suggestion ) : ?>
					<?php $source = get_post( $suggestion['source_post_id'] ); ?>
					<li>
						<?php echo esc_html( $source instanceof WP_Post ? get_the_title( $source ) : sprintf( __( 'Post #%d', 'co-entrepreneur-content-studio' ), $suggestion['source_post_id'] ) ); ?>
						— <?php echo esc_html( $suggestion['anchor'] ); ?>
						<?php if ( $source instanceof WP_Post && get_edit_post_link( $source->ID, 'raw' ) ) : ?>
							<a href="<?php echo esc_url( get_edit_post_link( $source->ID, 'raw' ) ); ?>"><?php esc_html_e( 'Open post to edit', 'co-entrepreneur-content-studio' ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders one preview table row.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	private function render_preview_row( $label, $value ) {
		?>
		<tr><th scope="row" style="width:180px"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $value ); ?></td></tr>
		<?php
	}

	/**
	 * Returns a human-readable image summary.
	 *
	 * @param array $package Package.
	 * @return string
	 */
	private function featured_image_summary( array $package ) {
		if ( empty( $package['featured_image'] ) ) {
			return __( 'None', 'co-entrepreneur-content-studio' );
		}
		return sprintf(
			/* translators: 1: attachment ID, 2: alt text. */
			__( 'Media ID %1$d; alt: %2$s', 'co-entrepreneur-content-studio' ),
			$package['featured_image']['media_id'],
			$package['featured_image']['alt_text'] ? $package['featured_image']['alt_text'] : __( '(empty)', 'co-entrepreneur-content-studio' )
		);
	}

	/**
	 * Adds existing-post state to the validation context.
	 *
	 * @return void
	 */
	private function attach_existing_post() {
		if ( empty( $this->validation['package']['content_id'] ) ) {
			return;
		}

		$existing = $this->writer->find_existing( $this->validation['package']['content_id'] );
		if ( is_wp_error( $existing ) ) {
			$this->screen_errors[] = $existing->get_error_message();
			return;
		}

		$this->validation['context']['existing_post'] = $existing;

		if ( ! empty( $this->validation['package']['slug'] ) ) {
			$slug_post = get_page_by_path( $this->validation['package']['slug'], OBJECT, 'post' );
			if ( $slug_post instanceof WP_Post && ( ! ( $existing instanceof WP_Post ) || $slug_post->ID !== $existing->ID ) ) {
				$this->screen_errors[] = __( 'Another post already uses the requested slug.', 'co-entrepreneur-content-studio' );
			}
		}

		if ( $existing instanceof WP_Post && 'draft' !== $existing->post_status ) {
			$this->screen_errors[] = __( 'This content_id belongs to a non-draft post. Open it manually; Content Studio will not modify it.', 'co-entrepreneur-content-studio' );
		}
	}

	/**
	 * Reads paste/upload input without storing the uploaded file.
	 *
	 * @return string
	 */
	private function read_package_input() {
		$pasted   = isset( $_POST['ce_package_json'] ) ? trim( (string) wp_unslash( $_POST['ce_package_json'] ) ) : '';
		$has_file = isset( $_FILES['ce_package_file'] ) && is_array( $_FILES['ce_package_file'] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES['ce_package_file']['error'];

		if ( '' !== $pasted && $has_file ) {
			$this->screen_errors[] = __( 'Use pasted JSON or an uploaded file, not both.', 'co-entrepreneur-content-studio' );
			return '';
		}

		if ( '' !== $pasted ) {
			return $pasted;
		}

		if ( ! $has_file ) {
			$this->screen_errors[] = __( 'Paste JSON or choose a JSON file.', 'co-entrepreneur-content-studio' );
			return '';
		}

		$file = $_FILES['ce_package_file'];
		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			$this->screen_errors[] = __( 'The JSON upload failed.', 'co-entrepreneur-content-studio' );
			return '';
		}

		$name = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$tmp  = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';

		if ( 'json' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			$this->screen_errors[] = __( 'The uploaded file must use the .json extension.', 'co-entrepreneur-content-studio' );
			return '';
		}

		if ( $size < 1 || $size > self::MAX_FILE_BYTES || ! is_uploaded_file( $tmp ) ) {
			$this->screen_errors[] = __( 'The uploaded file is empty, invalid or larger than 1 MB.', 'co-entrepreneur-content-studio' );
			return '';
		}

		$contents = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local uploaded temporary file.
		if ( false === $contents ) {
			$this->screen_errors[] = __( 'WordPress could not read the uploaded JSON file.', 'co-entrepreneur-content-studio' );
			return '';
		}

		return (string) $contents;
	}

	/**
	 * Saves the two simple settings.
	 *
	 * @return void
	 */
	private function save_settings() {
		$author_id = isset( $_POST['default_author_id'] ) ? absint( $_POST['default_author_id'] ) : 0;
		if ( $author_id && ! get_user_by( 'id', $author_id ) ) {
			$this->screen_errors[] = __( 'The selected default author no longer exists.', 'co-entrepreneur-content-studio' );
			return;
		}

		$raw_domains = isset( $_POST['preferred_domains'] ) ? (string) wp_unslash( $_POST['preferred_domains'] ) : '';
		$domains     = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw_domains ) as $domain ) {
			$domain = strtolower( trim( $domain ) );
			$domain = preg_replace( '#^https?://#', '', $domain );
			$domain = trim( $domain, "/ \t" );
			if ( $domain && preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain ) ) {
				$domains[] = $domain;
			}
		}

		update_option(
			self::SETTINGS_KEY,
			array(
				'default_author_id' => $author_id,
				'preferred_domains' => array_values( array_unique( $domains ) ),
			),
			false
		);

		$this->screen_notices[] = __( 'Settings saved.', 'co-entrepreneur-content-studio' );
	}

	/**
	 * Returns normalized settings.
	 *
	 * @return array
	 */
	private function get_settings() {
		$settings = get_option( self::SETTINGS_KEY, array() );
		return array(
			'default_author_id' => isset( $settings['default_author_id'] ) ? absint( $settings['default_author_id'] ) : 0,
			'preferred_domains' => isset( $settings['preferred_domains'] ) && is_array( $settings['preferred_domains'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $settings['preferred_domains'] ) ) ) : array(),
		);
	}
}
