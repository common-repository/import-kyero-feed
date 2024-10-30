<?php
/**
 * WordPress Kyero XML file parser implementations
 *
 * @package Kyero
 * @subpackage Importer
 */

/**
 * Kyero Parser that makes use of the SimpleXML PHP extension.
 */
class Kyero_Parser {
	function parse( $file ) {
		$authors    = array();
		$posts      = array();
		$categories = array();
		$tags       = array();
		$terms      = array();

		$internal_errors = libxml_use_internal_errors( true );

		$dom       = new DOMDocument;
		$old_value = null;
		if ( function_exists( 'libxml_disable_entity_loader' ) && PHP_VERSION_ID < 80000 ) {
			$old_value = libxml_disable_entity_loader( true );
		}
		$success = $dom->loadXML( file_get_contents( $file ) );
		if ( ! is_null( $old_value ) ) {
			libxml_disable_entity_loader( $old_value );
		}

		if ( ! $success || isset( $dom->doctype ) ) {
			return new WP_Error( 'SimpleXML_parse_error', __( 'There was an error when reading this Kyero file', 'import-kyero-feed' ), libxml_get_errors() );
		}

		$xml = simplexml_import_dom( $dom );
		unset( $dom );

		// halt if loading produces an error
		if ( ! $xml ) {
			return new WP_Error( 'SimpleXML_parse_error', __( 'There was an error when reading this Kyero file', 'import-kyero-feed' ), libxml_get_errors() );
		}

		$kyero_version = $xml->xpath( '/root/kyero/feed_version' );
		if ( ! $kyero_version ) {
			return new WP_Error( 'Kyero_parse_error', __( 'This does not appear to be a Kyero file, missing/invalid Kyero version number', 'import-kyero-feed' ) );
		}

		$kyero_version = (string) trim( $kyero_version[0] );
		// confirm that we are dealing with the correct file format
		if ( ! preg_match( '/^\d+(\.\d+)?$/', $kyero_version ) ) {
			return new WP_Error( 'Kyero_parse_error', __( 'This does not appear to be a Kyero file, missing/invalid Kyero version number', 'import-kyero-feed' ) );
		}

		$base_url = $xml->xpath( '/rss/channel/wp:base_site_url' );
		$base_url = (string) trim( isset( $base_url[0] ) ? $base_url[0] : '' );

		$base_blog_url = $xml->xpath( '/rss/channel/wp:base_blog_url' );
		if ( $base_blog_url ) {
			$base_blog_url = (string) trim( $base_blog_url[0] );
		} else {
			$base_blog_url = $base_url;
		}

		$namespaces = $xml->getDocNamespaces();
		if ( ! isset( $namespaces['wp'] ) ) {
			$namespaces['wp'] = 'http://wordpress.org/export/1.1/';
		}
		if ( ! isset( $namespaces['excerpt'] ) ) {
			$namespaces['excerpt'] = 'http://wordpress.org/export/1.1/excerpt/';
		}

		$post_id  = 1;
		$image_id = 1;

		// grab posts
		foreach ( $xml->property as $property ) {

			$property_type = (string) $property->type;

			if ( empty( $property->town ) ) {
				$title = $property_type;
			} else {
				$title = "$property_type in $property->town";
			}

			$decription   = '';
			$descriptions = $property->desc;
			if ( ! empty( $descriptions ) ) {
				$decription = (string) $descriptions->en;
			}
			$content = $decription;

			$location = '';
			if ( ! empty( $property->location ) ) {
				$property_location = $property->location;
				$location          = "$property_location->latitude,$property_location->longitude";
			}

			$property_size = '';
			$lot_size      = '';
			if ( ! empty( $property->surface_area ) ) {
				$property_size = (int) $property->surface_area->built;
				$lot_size      = (int) $property->surface_area->plot;
			}

			$address = array();
			if ( ! empty( $property->location_detail ) ) {
				$address[] = (string) $property->location_detail;
			}
			if ( ! empty( $property->town ) ) {
				$address[] = (string) $property->town;
			}
			if ( ! empty( $property->province ) ) {
				$address[] = (string) $property->province;
			}
			if ( ! empty( $property->country ) ) {
				$address[] = (string) $property->country;
			}
			$address_text = join( ', ', $address );

			$postmeta = array(
				array(
					'key'   => 'REAL_HOMES_property_id',
					'value' => (string) $property->ref,
				),
				array(
					'key'   => 'REAL_HOMES_property_price',
					'value' => (string) $property->price,
				),
				array(
					'key'   => 'REAL_HOMES_property_location',
					'value' => $location,
				),
				array(
					'key'   => 'REAL_HOMES_property_bedrooms',
					'value' => (string) $property->beds,
				),
				array(
					'key'   => 'REAL_HOMES_property_bathrooms',
					'value' => (string) $property->baths,
				),
				array(
					'key'   => 'REAL_HOMES_property_size',
					'value' => $property_size,
				),
				array(
					'key'   => 'REAL_HOMES_property_lot_size',
					'value' => $lot_size,
				),
				array(
					'key'   => 'REAL_HOMES_property_address',
					'value' => $address_text,
				),
			);

			$property_city = (string) $property->country;

			$terms = array(
				array(
					'name'          => $property_type,
					'slug'          => sanitize_title( $property_type ),
					'domain'        => 'property-type',
					'term_name'     => $property_type,
					'term_taxonomy' => 'property-type',
				),
				array(
					'name'          => $property_city,
					'slug'          => sanitize_title( $property_city ),
					'domain'        => 'property-city',
					'term_name'     => $property_city,
					'term_taxonomy' => 'property-city',
				),
			);

			if ( $property->features ) {
				foreach ( $property->features->feature as $feature ) {
					$feature_string = (string) $feature;

					$terms[] = array(
						'name'          => $feature_string,
						'slug'          => sanitize_title( $feature_string ),
						'domain'        => 'property-feature',
						'term_name'     => $feature_string,
						'term_taxonomy' => 'property-feature',
					);
				}
			}

			if ( $property->images ) {
				$image_index = 1;
				foreach ( $property->images->image as $image ) {

					$image_id++;

					if ( 1 === $image_index ) {
						$postmeta[] = array(
							'key'   => '_thumbnail_id',
							'value' => $image_id,
						);
					}

					$image_title = "Image $image_index for $title ($property->ref)";

					$posts[] = array(
						'post_id'        => $image_id,
						'post_title'     => $image_title,
						'post_name'      => sanitize_title( $image_title ),
						'post_type'      => 'attachment',
						'post_date'      => (string) $property->date,
						'post_date_gmt'  => get_gmt_from_date( $property->date ),
						'post_author'    => 'kyero',
						'post_content'   => '',
						'post_excerpt'   => '',
						'guid'           => '',
						'comment_status' => 'closed',
						'ping_status'    => 'open',
						'status'         => 'draft',
						'post_parent'    => $post_id,
						'menu_order'     => 0,
						'post_password'  => '',
						'is_sticky'      => false,
						'attachment_url' => (string) $image->url,
						'postmeta'       => array(
							array(
								'key'   => 'kyero_import_url',
								'value' => (string) $image->url,
							),
						),
					);

					$image_index++;
				}
			}

			$posts[] = array(
				'post_id'        => $post_id,
				'post_title'     => $title,
				'post_name'      => sanitize_title( $title ),
				'post_type'      => 'property',
				'post_date'      => (string) $property->date,
				'post_date_gmt'  => get_gmt_from_date( $property->date ),
				'post_author'    => 'kyero',
				'post_content'   => $content,
				'post_excerpt'   => wp_trim_excerpt( $decription ),
				'guid'           => '',
				'comment_status' => 'closed',
				'ping_status'    => 'open',
				'status'         => 'draft',
				'post_parent'    => null,
				'menu_order'     => 0,
				'post_password'  => '',
				'is_sticky'      => false,
				'postmeta'       => $postmeta,
				'terms'          => $terms,
			);

			$post_id = ++$image_id;

		}
		return array(
			'authors'       => $authors,
			'posts'         => $posts,
			'categories'    => $categories,
			'tags'          => $tags,
			'terms'         => $terms,
			'base_url'      => $base_url,
			'base_blog_url' => $base_blog_url,
			'version'       => $kyero_version,
		);
	}
}
