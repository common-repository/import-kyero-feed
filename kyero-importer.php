<?php
/*
 * @wordpress-plugin
 * Plugin Name:       Import Kyero Feed
 * Plugin URI:        https://wordpress.org/plugins/import-kyero-feed/
 * Description:       Import Easy Real Estate properties and images from a Kyero feed.
 * Author:            grimaceofdespair
 * Author URI:        https://www.bithive.be/
 * Version:           0.1
 * Requires at least: 5.2
 * Requires PHP:      5.6
 * Text Domain:       import-kyero-feed
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'WP_LOAD_IMPORTERS' ) && ! defined( 'DOING_CRON' ) ) {
	return;
}

/** Display verbose errors */
if ( ! defined( 'IKYF_DEBUG' ) ) {
	define( 'IKYF_DEBUG', WP_DEBUG );
}

/** WordPress Import Administration API */
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( ! class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) ) {
		require $class_wp_importer;
	}
}

/** Kyero_Parser class */
require_once dirname( __FILE__ ) . '/class-kyero-parser.php';

/** Kyero_Import class */
require_once dirname( __FILE__ ) . '/class-kyero-import.php';

function ikyf_init() {
	load_plugin_textdomain( 'import-kyero-feed' );

	/**
	 * Import Kyero Feed object for registering the import callback
	 * @global Kyero_Import $kyero_import
	 */
	$GLOBALS['import_kyero_feed'] = new Kyero_Import();
	// phpcs:ignore WordPress.WP.CapitalPDangit
	register_importer( 'kyero', 'Kyero', __( 'Import Easy Real Estate <strong>properties and images</strong> from a Kyero feed.', 'import-kyero-feed' ), array( $GLOBALS['import_kyero_feed'], 'dispatch' ) );
}

add_action( 'admin_init', 'ikyf_init' );

function ikyf_import_url( $url, $login ) {
	$import = new Kyero_Import;
	$import->run( $url, $login );
}

add_action( 'import_kyero_url', 'ikyf_import_url', 10, 2 );

function ikyf_post_meta( $postmeta, $post_id, $post ) {

	$post_id = ikyf_get_post_id( $post );

	// Skip setting kyero properties on reimport
	if ( $post_id ) {
		return array();
	}

	return $postmeta;
}

add_filter( 'wp_import_post_meta', 'ikyf_post_meta', 10, 3 );

function ikyf_property_exists( $post_exists, $post ) {
	return ikyf_get_post_id( $post );
}

add_filter( 'wp_import_existing_post', 'ikyf_property_exists', 10, 2 );

function ikyf_get_post_id( $post ) {
	$post_id = ikyf_get_post_by_metadata( $post, 'property', 'REAL_HOMES_property_id' );

	if ( ! $post_id ) {
		$post_id = ikyf_get_post_by_metadata( $post, 'attachment', 'kyero_import_url' );
	}

	return $post_id;
}

function ikyf_get_post_by_metadata( $post, $post_type, $key ) {

	$meta_value = ikyf_metadata_value( $post, $key );

	if ( $meta_value ) {

		$posts = get_posts(
			array(
				'numberposts' => 1,
				'post_type'   => $post_type,
				'post_status' => 'any',
				'meta_key'    => $key,
				'meta_value'  => $meta_value,
			)
		);

		if ( count( $posts ) > 0 ) {

			$post['postmeta'] = array();

			return $posts[0]->ID;
		}
	}

	return 0;
}

function ikyf_metadata_value( $post, $key ) {

	if ( isset( $post['postmeta'] ) ) {

		$postmeta = $post['postmeta'];

		$index = array_search( $key, array_column( $postmeta, 'key' ), true );

		if ( false !== $index ) {
			return $postmeta[ $index ]['value'];
		}
	}

	return null;
}
