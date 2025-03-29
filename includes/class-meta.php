<?php
/**
 * Meta Registration for TutorPress
 *
 * Handles registration of meta fields for the curriculum builder,
 * ensuring compatibility with Tutor LMS's data structure.
 *
 * @package TutorPress
 * @since 1.0.0
 */

namespace TutorPress;

defined( 'ABSPATH' ) || exit;

/**
 * Meta Registration Class
 */
class Meta {
    /**
     * Initialize the meta registration
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_post_meta' ) );
    }

    /**
     * Register post meta for curriculum builder
     */
    public static function register_post_meta() {
        // Register course meta fields that match Tutor LMS structure
        $meta_keys = array(
            '_tutor_course_price_type',
            '_tutor_course_product_id',
            '_tutor_course_duration',
            '_tutor_course_level',
            '_tutor_course_benefits',
            '_tutor_course_requirements',
            '_tutor_course_target_audience',
            '_tutor_course_material_includes'
        );

        foreach ( $meta_keys as $meta_key ) {
            register_post_meta(
                'courses',
                $meta_key,
                array(
                    'show_in_rest'  => true,
                    'single'        => true,
                    'type'         => 'string',
                    'auth_callback' => function( $allowed, $meta_key, $post_id ) {
                        return current_user_can( 'edit_post', $post_id );
                    }
                )
            );
        }

        // Register curriculum meta with basic schema
        register_post_meta(
            'courses',
            '_tutor_course_curriculum',
            array(
                'show_in_rest' => array(
                    'schema' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'topics' => array(
                                'type'  => 'array',
                                'items' => array(
                                    'type'       => 'object',
                                    'properties' => array(
                                        'id'       => array( 'type' => 'integer' ),
                                        'title'    => array( 'type' => 'string' ),
                                        'contents' => array(
                                            'type'  => 'array',
                                            'items' => array(
                                                'type'       => 'object',
                                                'properties' => array(
                                                    'id'    => array( 'type' => 'integer' ),
                                                    'title' => array( 'type' => 'string' ),
                                                    'type'  => array( 'type' => 'string' )
                                                )
                                            )
                                        )
                                    )
                                )
                            )
                        )
                    )
                ),
                'single'        => true,
                'type'         => 'object',
                'auth_callback' => function( $allowed, $meta_key, $post_id ) {
                    return current_user_can( 'edit_post', $post_id );
                }
            )
        );
    }
}

// Initialize the meta registration
Meta::init();