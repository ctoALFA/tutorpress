<?php
/**
 * Certificate Metabox for Gutenberg Editor
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
 * Certificate Metabox Class
 * 
 * Handles the certificate selection metabox in the Gutenberg editor
 * using vanilla JavaScript and Tutor LMS's data structure.
 */
class Certificate_Metabox {

    /**
     * REST API namespace
     */
    const REST_NAMESPACE = 'tutorpress/v1';
    
    /**
     * Certificate template meta key
     */
    const TEMPLATE_META_KEY = 'tutor_course_certificate_template';

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
     * Register post meta for certificate data
     * Uses Tutor LMS's meta structure for compatibility
     */
    public static function register_meta() {
        register_post_meta(
            'courses',
            self::TEMPLATE_META_KEY,
            array(
                'show_in_rest'  => true,
                'single'        => true,
                'type'          => 'string',
                'auth_callback' => function( $allowed, $meta_key, $post_id ) {
                    return current_user_can( 'edit_post', $post_id );
                }
            )
        );
    }

    /**
     * Add certificate metabox to Gutenberg editor
     */
    public static function add_meta_box() {
        add_meta_box(
            'tutorpress-certificate',
            __( 'Certificate', 'tutorpress' ),
            array( __CLASS__, 'render_meta_box' ),
            'courses',
            'normal',
            'high'
        );
    }

    /**
     * Render the certificate metabox
     * 
     * @param \WP_Post $post The post object
     */
    public static function render_meta_box( $post ) {
        // Add nonce for security
        wp_nonce_field( 'tutorpress_certificate_nonce', 'certificate_nonce' );

        // Get current certificate template
        $current_template = get_post_meta( $post->ID, self::TEMPLATE_META_KEY, true );
        $current_template = empty( $current_template ) ? 'default' : $current_template;
        
        ?>
        <div id="tutorpress-certificate-builder" 
             class="tutorpress-certificate-builder" 
             data-course-id="<?php echo esc_attr( $post->ID ); ?>"
             data-current-template="<?php echo esc_attr( $current_template ); ?>">
            
            <!-- Tabs -->
            <div class="tutorpress-certificate-tabs">
                <button type="button" class="tutorpress-certificate-tab active" data-tab="templates">
                    <?php esc_html_e( 'Templates', 'tutorpress' ); ?>
                </button>
                <button type="button" class="tutorpress-certificate-tab" data-tab="custom-certificates">
                    <?php esc_html_e( 'Custom Certificates', 'tutorpress' ); ?>
                </button>
            </div>
            
            <!-- Container for templates -->
            <div class="tutorpress-certificate-tab-content active" data-tab-content="templates">
                <div class="tutorpress-certificate-grid">
                    <!-- Templates will be loaded via JS -->
                    <div class="tutorpress-certificate-loading">
                        <?php esc_html_e( 'Loading certificate templates...', 'tutorpress' ); ?>
                    </div>
                </div>
            </div>
            
            <!-- Container for custom certificates -->
            <div class="tutorpress-certificate-tab-content" data-tab-content="custom-certificates">
                <div class="tutorpress-certificate-grid">
                    <!-- Custom certificates will be loaded via JS -->
                    <div class="tutorpress-certificate-loading">
                        <?php esc_html_e( 'Loading custom certificates...', 'tutorpress' ); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Register REST API routes
     */
    public static function register_rest_routes() {
        // Get certificate templates
        register_rest_route(
            self::REST_NAMESPACE,
            '/certificate-templates',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_certificate_templates' ),
                'permission_callback' => array( __CLASS__, 'check_read_permission' ),
            )
        );
        
        // Save certificate template for course
        register_rest_route(
            self::REST_NAMESPACE,
            '/certificate-template',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'save_certificate_template' ),
                'permission_callback' => array( __CLASS__, 'check_edit_permission' ),
                'args'                => array(
                    'course_id'  => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                    'template_key' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );
    }
    
    /**
     * Check if user has permission to read
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public static function check_read_permission($request) {
        return current_user_can('edit_posts');
    }
    
    /**
     * Check if user has permission to edit certificate
     * 
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public static function check_edit_permission($request) {
        $course_id = isset($request['course_id']) ? intval($request['course_id']) : 0;
        
        if (!$course_id) {
            return new WP_Error(
                'tutorpress_invalid_course_id',
                __('Invalid course ID.', 'tutorpress'),
                array('status' => 400)
            );
        }
        
        if (!current_user_can('edit_post', $course_id)) {
            return new WP_Error(
                'tutorpress_permission_denied',
                __('You do not have permission to edit this course.', 'tutorpress'),
                array('status' => 403)
            );
        }
        
        return true;
    }
    
    /**
     * Get certificate templates
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function get_certificate_templates($request) {
        // Check if Tutor Pro is active
        if (!function_exists('tutor_pro') || !class_exists('\TUTOR_CERT\Certificate')) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => __('Tutor LMS Pro with Certificates add-on is required.', 'tutorpress'),
                'templates' => array(),
            ));
        }
        
        // Get templates using Tutor LMS's function
        $certificate = new \TUTOR_CERT\Certificate();
        $templates = $certificate->get_templates(true, true);
        
        // Format for our response
        $formatted_templates = array();
        $certificate_img_url_base = 'https://preview.tutorlms.com/certificate-templates/';
        
        foreach ($templates as $key => $template) {
            if ($key === 'none') {
                $preview_src = '';
            } else {
                $preview_src = isset($template['preview_src']) ? $template['preview_src'] : $certificate_img_url_base . $key . '.png';
            }
            
            $formatted_templates[] = array(
                'key' => $key,
                'name' => isset($template['name']) ? $template['name'] : ucfirst(str_replace('_', ' ', $key)),
                'preview_src' => $preview_src,
                'is_default' => isset($template['is_default']) ? $template['is_default'] : false,
                'is_custom' => strpos($key, 'tutor_cb_') === 0,
            );
        }
        
        return rest_ensure_response($formatted_templates);
    }
    
    /**
     * Save certificate template for course
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function save_certificate_template($request) {
        $course_id = $request['course_id'];
        $template_key = $request['template_key'];
        
        // Update the course's certificate template meta
        update_post_meta($course_id, self::TEMPLATE_META_KEY, $template_key);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Certificate template saved successfully.', 'tutorpress'),
            'template_key' => $template_key,
        ));
    }
}

// Initialize the metabox
Certificate_Metabox::init(); 