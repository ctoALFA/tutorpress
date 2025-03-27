<?php
/**
 * Curriculum Metabox for Gutenberg Editor
 *
 * @package TutorPress
 * @since 1.0.0
 */

namespace TutorPress\Gutenberg\Metaboxes;

defined( 'ABSPATH' ) || exit;

/**
 * Curriculum Metabox Class
 * 
 * Handles the curriculum builder metabox in the Gutenberg editor
 * using vanilla JavaScript and Tutor LMS's data structure.
 */
class Curriculum_Metabox {

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
     * Register REST API endpoints
     * These endpoints will proxy to Tutor LMS's AJAX endpoints
     */
    public static function register_rest_routes() {
        register_rest_route(
            'tutorpress/v1',
            '/curriculum/(?P<id>\d+)',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'get_curriculum' ),
                    'permission_callback' => function( $request ) {
                        return current_user_can( 'edit_post', $request['id'] );
                    }
                ),
                array(
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => array( __CLASS__, 'update_curriculum' ),
                    'permission_callback' => function( $request ) {
                        return current_user_can( 'edit_post', $request['id'] );
                    }
                )
            )
        );
    }

    /**
     * Get curriculum data
     * 
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public static function get_curriculum( $request ) {
        $course_id = $request['id'];

        // Get topics
        $topics = get_posts(array(
            'post_type'      => 'topics',
            'post_parent'    => $course_id,
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC'
        ));

        $curriculum = array();
        foreach ( $topics as $topic ) {
            // Get topic contents
            $contents = get_posts(array(
                'post_type'      => array( 'lesson', 'tutor_quiz', 'tutor_assignments' ),
                'post_parent'    => $topic->ID,
                'posts_per_page' => -1,
                'orderby'        => 'menu_order',
                'order'          => 'ASC'
            ));

            $curriculum[] = array(
                'ID'         => $topic->ID,
                'title'      => $topic->post_title,
                'contents'   => array_map( function( $content ) {
                    return array(
                        'ID'        => $content->ID,
                        'title'     => $content->post_title,
                        'type'      => $content->post_type,
                        'order'     => $content->menu_order
                    );
                }, $contents )
            );
        }

        return rest_ensure_response( array(
            'success' => true,
            'data'    => $curriculum
        ) );
    }

    /**
     * Update curriculum data
     * This will proxy to Tutor LMS's update endpoints
     * 
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public static function update_curriculum( $request ) {
        $course_id = $request['id'];
        $action = $request->get_param( 'action' );
        $data = $request->get_json_params();

        switch ( $action ) {
            case 'add_topic':
                // Proxy to Tutor LMS's add topic endpoint
                $result = wp_remote_post( admin_url( 'admin-ajax.php' ), array(
                    'body' => array(
                        'action'    => 'tutor_save_topic',
                        'course_id' => $course_id,
                        'title'     => $data['title'],
                        'summary'   => $data['summary'] ?? '',
                        'nonce'     => wp_create_nonce( 'tutor_nonce' )
                    )
                ));
                break;

            case 'delete_topic':
                // Proxy to Tutor LMS's delete topic endpoint
                $result = wp_remote_post( admin_url( 'admin-ajax.php' ), array(
                    'body' => array(
                        'action'   => 'tutor_delete_topic',
                        'topic_id' => $data['topic_id'],
                        'nonce'    => wp_create_nonce( 'tutor_nonce' )
                    )
                ));
                break;

            default:
                return new \WP_Error(
                    'invalid_action',
                    __( 'Invalid action', 'tutorpress' ),
                    array( 'status' => 400 )
                );
        }

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $response = json_decode( wp_remote_retrieve_body( $result ), true );

        return rest_ensure_response( $response );
    }
}

// Initialize the metabox
Curriculum_Metabox::init();
