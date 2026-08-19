<?php
/**
 * Plugin Name: Co-Entrepreneur Content Studio
 * Description: Imports a structured article package into a reviewable WordPress draft.
 * Version: 0.1.0
 * Author: Co-Entrepreneur
 * Text Domain: co-entrepreneur-content-studio
 * Requires at least: 6.5
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CE_CONTENT_STUDIO_VERSION', '0.1.0' );
define( 'CE_CONTENT_STUDIO_FILE', __FILE__ );
define( 'CE_CONTENT_STUDIO_DIR', plugin_dir_path( __FILE__ ) );

require_once CE_CONTENT_STUDIO_DIR . 'includes/class-package-validator.php';
require_once CE_CONTENT_STUDIO_DIR . 'includes/class-rank-math-adapter.php';
require_once CE_CONTENT_STUDIO_DIR . 'includes/class-polylang-adapter.php';
require_once CE_CONTENT_STUDIO_DIR . 'includes/class-draft-writer.php';
require_once CE_CONTENT_STUDIO_DIR . 'includes/class-import-screen.php';

/**
 * Boots the admin-only plugin services.
 *
 * @return void
 */
function ce_content_studio_boot() {
	$rank_math = new CE_Content_Studio_Rank_Math_Adapter();
	$polylang  = new CE_Content_Studio_Polylang_Adapter();
	$validator = new CE_Content_Studio_Package_Validator();
	$writer    = new CE_Content_Studio_Draft_Writer( $rank_math, $polylang );

	new CE_Content_Studio_Import_Screen( $validator, $writer );
}

add_action( 'plugins_loaded', 'ce_content_studio_boot' );
