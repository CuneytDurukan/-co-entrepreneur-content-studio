<?php
/**
 * Content Studio intentionally preserves all content and settings on uninstall.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Intentionally no destructive cleanup.
