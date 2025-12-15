<?php
/**
 * Quick Edit Helper Functions
 *
 * Global helper functions for registering quick edit fields.
 *
 * @package     ArrayPress\WP\RegisterQuickEdit
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

use ArrayPress\RegisterQuickEdit\QuickEdit;

if ( ! function_exists( 'register_quick_edit_fields' ) ):
	/**
	 * Register quick edit fields for posts or custom post types.
	 *
	 * @param string|array $post_types Post type(s) to register fields for.
	 * @param array        $fields     Array of field configurations.
	 *
	 * @return void
	 */
	function register_quick_edit_fields( $post_types, array $fields ): void {
		$post_types = (array) $post_types;

		foreach ( $post_types as $post_type ) {
			new QuickEdit( $fields, $post_type );
		}
	}
endif;
