<?php
/**
 * Curriculum Metabox for Gutenberg Editor
 *
 * @package TutorPress
 * @since 1.0.0
 */

namespace TutorPress\Gutenberg\Metaboxes;

use WP_REST_Server;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Curriculum Metabox Class
 * 
 * Handles the curriculum builder metabox in the Gutenberg editor
 * using vanilla JavaScript and Tutor LMS's data structure.
 */
class Curriculum_Metabox {

    /**
     * REST API namespace
     */
    const REST_NAMESPACE = 'tutorpress/v1';

    /**
     * Initialize the metabox
     */
    public static function init() {
        // Register meta for course post type
        add_action( 'init', array( __CLASS__, 'register_meta' ) );

        // Add metabox to Gutenberg editor
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );

        // Register REST API endpoints
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
    }

    /**
     * Register post meta for curriculum data
     * Uses Tutor LMS's meta structure for compatibility
     */
    public static function register_meta() {
        register_post_meta(
            'courses',
            '_tutor_course_price_type',
            array(
                'show_in_rest'  => true,
                'single'        => true,
                'type'         => 'string',
                'auth_callback' => function( $allowed, $meta_key, $post_id ) {
                    return current_user_can( 'edit_post', $post_id );
                }
            )
        );

        // Register other course meta used by Tutor LMS
        $meta_keys = array(
            '_tutor_course_product_id',
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
    }

    /**
     * Add curriculum metabox to Gutenberg editor
     */
    public static function add_meta_box() {
        add_meta_box(
            'tutorpress-curriculum',
            __( 'Course Curriculum', 'tutorpress' ),
            array( __CLASS__, 'render_meta_box' ),
            'courses',
            'normal',
            'high'
        );
    }

    /**
     * Render the curriculum metabox
     * 
     * @param \WP_Post $post The post object
     */
    public static function render_meta_box( $post ) {
        // Add nonce for security
        wp_nonce_field( 'tutorpress_curriculum_nonce', 'curriculum_nonce' );

        // Get course topics
        $topics = get_posts(array(
            'post_type'      => 'topics',
            'post_parent'    => $post->ID,
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC'
        ));

        ?>
        <div id="tutorpress-curriculum-builder" 
             class="tutorpress-curriculum-builder" 
             data-course-id="<?php echo esc_attr( $post->ID ); ?>">
            
            <!-- Header with Expand All -->
            <div class="tutorpress-curriculum-header">
                <button type="button" class="tutorpress-expand-all">
                    <?php esc_html_e( 'Expand All', 'tutorpress' ); ?>
                </button>
            </div>

            <!-- Topics Container -->
            <div class="tutorpress-topics-container">
                <?php
                if ( ! empty( $topics ) ) {
                    foreach ( $topics as $topic ) {
                        // Get topic contents (lessons, quizzes, etc.)
                        $contents = get_posts(array(
                            'post_type'      => array( 'lesson', 'tutor_quiz', 'tutor_assignments' ),
                            'post_parent'    => $topic->ID,
                            'posts_per_page' => -1,
                            'orderby'        => 'menu_order',
                            'order'          => 'ASC'
                        ));

                        ?>
                        <div class="tutorpress-topic" data-topic-id="<?php echo esc_attr( $topic->ID ); ?>">
                            <!-- Topic Header -->
                            <div class="tutorpress-topic-header">
                                <div class="tutorpress-topic-header-left">
                                    <span class="tutorpress-drag-handle tutor-icon-drag"></span>
                                    <button type="button" class="tutorpress-topic-toggle" aria-expanded="false">
                                        <span class="tutor-icon-angle-down"></span>
                                    </button>
                                    <h3 class="tutorpress-topic-title"><?php echo esc_html( $topic->post_title ); ?></h3>
                                </div>
                                <div class="tutorpress-topic-header-right">
                                    <button type="button" class="tutorpress-topic-edit" title="<?php esc_attr_e( 'Edit Topic', 'tutorpress' ); ?>">
                                        <span class="tutor-icon-pencil"></span>
                                    </button>
                                    <button type="button" class="tutorpress-topic-duplicate" title="<?php esc_attr_e( 'Duplicate Topic', 'tutorpress' ); ?>">
                                        <span class="tutor-icon-file-import"></span>
                                    </button>
                                    <button type="button" class="tutorpress-topic-delete" title="<?php esc_attr_e( 'Delete Topic', 'tutorpress' ); ?>">
                                        <span class="tutor-icon-trash-can"></span>
                                    </button>
                                </div>
                            </div>

                            <!-- Topic Content -->
                            <div class="tutorpress-topic-content">
                                <!-- Content Items -->
                                <div class="tutorpress-content-items">
                                    <?php
                                    if ( ! empty( $contents ) ) {
                                        foreach ( $contents as $content ) {
                                            $content_type = $content->post_type;
                                            $type_icon = 'lesson' === $content_type ? 'tutor-icon-book-open' : 
                                                       ('tutor_quiz' === $content_type ? 'tutor-icon-quiz-o' : 
                                                       ('tutor_assignments' === $content_type ? 'tutor-icon-assignment' : 'tutor-icon-interactive'));
                                            ?>
                                            <div class="tutorpress-content-item" 
                                                 data-id="<?php echo esc_attr( $content->ID ); ?>"
                                                 data-type="<?php echo esc_attr( $content_type ); ?>">
                                                <div class="tutorpress-content-item-left">
                                                    <span class="tutorpress-content-icon <?php echo esc_attr( $type_icon ); ?>"></span>
                                                    <span class="tutorpress-drag-handle tutor-icon-drag"></span>
                                                    <span class="tutorpress-content-title">
                                                        <?php echo esc_html( $content->post_title ); ?>
                                                    </span>
                                                </div>
                                                <div class="tutorpress-content-item-right">
                                                    <button type="button" class="tutorpress-content-edit" title="<?php esc_attr_e( 'Edit', 'tutorpress' ); ?>">
                                                        <span class="tutor-icon-pencil"></span>
                                                    </button>
                                                    <button type="button" class="tutorpress-content-duplicate" title="<?php esc_attr_e( 'Duplicate', 'tutorpress' ); ?>">
                                                        <span class="tutor-icon-file-import"></span>
                                                    </button>
                                                    <button type="button" class="tutorpress-content-delete" title="<?php esc_attr_e( 'Delete', 'tutorpress' ); ?>">
                                                        <span class="tutor-icon-trash-can"></span>
                                                    </button>
                                                </div>
                                            </div>
                                            <?php
                                        }
                                    }
                                    ?>
                                </div>

                                <!-- Add Content Buttons -->
                                <div class="tutorpress-content-actions">
                                    <button type="button" class="tutorpress-add-lesson">
                                        <span class="tutor-icon-book-open"></span>
                                        <?php esc_html_e( 'Lesson', 'tutorpress' ); ?>
                                    </button>
                                    <button type="button" class="tutorpress-add-quiz">
                                        <span class="tutor-icon-quiz-o"></span>
                                        <?php esc_html_e( 'Quiz', 'tutorpress' ); ?>
                                    </button>
                                    <button type="button" class="tutorpress-add-assignment">
                                        <span class="tutor-icon-assignment"></span>
                                        <?php esc_html_e( 'Assignment', 'tutorpress' ); ?>
                                    </button>
                                    <button type="button" class="tutorpress-add-interactive-quiz">
                                        <span class="tutor-icon-interactive"></span>
                                        <?php esc_html_e( 'Interactive Quiz', 'tutorpress' ); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>

            <!-- Add Topic Button -->
            <div class="tutorpress-add-topic-wrapper">
                <button type="button" class="tutorpress-add-topic">
                    <span class="dashicons dashicons-plus"></span>
                    <?php esc_html_e( 'Add Topic', 'tutorpress' ); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Register REST API routes
     */
    public static function register_rest_routes() {
        // Get course topics
        register_rest_route(
            self::REST_NAMESPACE,
            '/course/(?P<course_id>\d+)/topics',
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'get_course_topics'],
                'permission_callback' => [__CLASS__, 'check_read_permission'],
                'args' => [
                    'course_id' => [
                        'required' => true,
                        'validate_callback' => 'rest_validate_request_arg',
                    ],
                ],
            ]
        );

        // Register REST routes for curriculum management
        register_rest_route(
            self::REST_NAMESPACE,
            '/topic',
            [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'handle_topic_save'],
                'permission_callback' => [__CLASS__, 'check_topic_permissions'],
                'args' => [
                    'course_id' => ['required' => true],
                    'title' => ['required' => true],
                    'topic_id' => ['required' => false],
                    'summary' => ['required' => false],
                    'order' => ['required' => false, 'type' => 'integer'],
                    'content_drip_settings' => ['required' => false]
                ]
            ]
        );
    }

    /**
     * Check if user can read course content
     */
    public static function check_read_permission($request) {
        $course_id = $request->get_param('course_id');
        return current_user_can('read_course', $course_id);
    }

    /**
     * Check permissions for topic operations
     */
    public static function check_topic_permissions($request) {
        $course_id = $request->get_param('course_id');
        $topic_id = $request->get_param('topic_id');
        
        // For creation, check course edit permissions
        if ($course_id) {
            return current_user_can('edit_post', $course_id);
        }
        
        // For deletion/update, check topic edit permissions
        if ($topic_id) {
            return current_user_can('edit_post', $topic_id);
        }
        
        return false;
    }

    /**
     * Get course topics
     */
    public static function get_course_topics($request) {
        $course_id = $request->get_param('course_id');
        
        $topics = get_posts([
            'post_type' => 'topics',
            'post_parent' => $course_id,
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ]);

        return rest_ensure_response($topics);
    }

    /**
     * Handle topic save request
     */
    public static function handle_topic_save($request) {
        $course_id = $request->get_param('course_id');
        $title = $request->get_param('title');
        $summary = $request->get_param('summary');
        $topic_id = $request->get_param('topic_id');
        $order = $request->get_param('order');

        // If no order provided and it's a new topic, get next order
        if (!$order && !$topic_id) {
            $order = tutor_utils()->get_next_topic_order_id($course_id);
        } else if ($topic_id) {
            // If editing an existing topic and no order provided, keep the current order
            $current_order = get_post_field('menu_order', $topic_id);
            $order = $order ?: $current_order;
        }

        $topic_data = [
            'post_type'    => 'topics',
            'post_title'   => $title,
            'post_content' => $summary,
            'post_status'  => 'publish',
            'post_parent'  => $course_id,
            'menu_order'   => $order,
        ];

        if ($topic_id) {
            $topic_data['ID'] = $topic_id;
            $topic_id = wp_update_post($topic_data);
        } else {
            $topic_id = wp_insert_post($topic_data);
        }

        if (is_wp_error($topic_id)) {
            return new WP_Error(
                'topic_save_failed',
                $topic_id->get_error_message(),
                ['status' => 500]
            );
        }

        // Update topic meta
        update_post_meta($topic_id, '_tutor_course_id_for_topic', $course_id);

        // Get the updated topic data
        $topic = get_post($topic_id);
        
        return rest_ensure_response([
            'id' => $topic_id,
            'title' => $topic->post_title,
            'summary' => $topic->post_content,
            'order' => $topic->menu_order,
        ]);
    }

    /**
     * Handle topic deletion
     */
    public static function handle_topic_delete($request) {
        $topic_id = $request->get_param('topic_id');
        
        // Check if topic exists and is of correct type
        $topic = get_post($topic_id);
        if (!$topic || $topic->post_type !== 'topics') {
            return new WP_Error(
                'topic_not_found',
                __('Topic not found', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Delete the topic
        $result = wp_delete_post($topic_id, true);
        
        if (!$result) {
            return new WP_Error(
                'topic_delete_failed',
                __('Failed to delete topic', 'tutorpress'),
                ['status' => 500]
            );
        }

        return rest_ensure_response([
            'deleted' => true,
            'topic_id' => $topic_id
        ]);
    }
}

// Initialize the metabox
Curriculum_Metabox::init();
