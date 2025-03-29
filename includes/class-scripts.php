<?php
/**
 * Handles script and style enqueuing for TutorPress.
 */

defined('ABSPATH') || exit;

class TutorPress_Scripts {

    /**
     * Nonce action for security verification
     *
     * @since 1.0.0
     * @var string
     */
    const NONCE_ACTION = 'tutor_curriculum_metabox_nonce';

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_common_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_lesson_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_dashboard_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'localize_script_data']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_gutenberg_scripts']);
        
        // Initialize performance features
        self::init_performance_features();
        
        // Add performance mark for curriculum builder start
        add_action('admin_footer', function() {
            if (function_exists('get_current_screen')) {
                $screen = get_current_screen();
                if ($screen && $screen->base === 'post' && in_array($screen->post_type, ['courses', 'lessons'])) {
                    ?>
                    <script>
                    if (window.performance && window.performance.mark) {
                        window.performance.mark('tutorpress_curriculum_builder_start');
                    }
                    </script>
                    <?php
                }
            }
        }, -999);
    }

    /**
     * Enqueue JavaScript that runs on both lesson pages and the Tutor LMS dashboard.
     */
    public static function enqueue_common_assets() {
        $options = get_option('tutorpress_settings', []);
        
        // Conditionally load override-tutorlms.js
        if (!empty($options['enable_sidebar_tabs']) || !empty($options['enable_dashboard_redirects'])) {
            wp_enqueue_script(
                'tutorpress-override-tutorlms',
                TUTORPRESS_URL . 'assets/js/override-tutorlms.js',
                [],
                filemtime(TUTORPRESS_PATH . 'assets/js/override-tutorlms.js'),
                true
            );
        }
    }

    /**
     * Enqueue CSS and JavaScript for lesson sidebar and wpDiscuz integration.
     */
    public static function enqueue_lesson_assets() {
        if (!is_singular('lesson')) {
            return;
        }
        
        $options = get_option('tutorpress_settings', []);
        if (empty($options['enable_sidebar_tabs'])) {
            return;
        }

        wp_enqueue_style(
            'tutorpress-comments-style',
            TUTORPRESS_URL . 'assets/css/tutor-comments.css',
            [],
            filemtime(TUTORPRESS_PATH . 'assets/css/tutor-comments.css'),
            'all'
        );

        wp_enqueue_script(
            'tutorpress-sidebar-tabs',
            TUTORPRESS_URL . 'assets/js/sidebar-tabs.js',
            [],
            filemtime(TUTORPRESS_PATH . 'assets/js/sidebar-tabs.js'),
            true
        );
    }

    /**
     * Enqueue JavaScript for the Tutor LMS frontend dashboard.
     */
    public static function enqueue_dashboard_assets() {
        if (!is_page('dashboard')) { // Ensure we are on the Tutor LMS dashboard
            return;
        }

        $options = get_option('tutorpress_settings', []);
        if (!empty($options['enable_dashboard_redirects'])) {
            wp_enqueue_script(
                'tutorpress-override-tutorlms',
                TUTORPRESS_URL . 'assets/js/override-tutorlms.js',
                [],
                filemtime(TUTORPRESS_PATH . 'assets/js/override-tutorlms.js'),
                true
            );
        }
    }

    /**
     * Localize script data to pass settings to JavaScript.
     */
    public static function localize_script_data() {
        $options = get_option('tutorpress_settings', []);
        
        wp_localize_script('tutorpress-override-tutorlms', 'TutorPressData', [
            'enableSidebarTabs' => !empty($options['enable_sidebar_tabs']),
            'enableDashboardRedirects' => !empty($options['enable_dashboard_redirects']),
            'adminUrl' => admin_url(),
        ]);
    }

    /**
     * Enqueue Gutenberg scripts
     */
    public static function enqueue_gutenberg_scripts() {
        global $post;
        $screen = get_current_screen();
        
        // Only load on post edit screens
        if (!$screen || $screen->base !== 'post') {
            return;
        }

        $post_id = isset($post) ? $post->ID : 0;
        $post_type = isset($post) ? $post->post_type : '';
        
        if (empty($post_type) && isset($_GET['post_type'])) {
            $post_type = $_GET['post_type'];
        }

        // Only load for courses post type
        if ($post_type !== 'courses') {
            return;
        }

        // Register dependencies first 
        wp_enqueue_script('wp-element');
        wp_enqueue_script('wp-dom-ready');
        wp_enqueue_script('wp-components');
        wp_enqueue_script('wp-plugins');
        wp_enqueue_script('wp-edit-post');
        wp_enqueue_script('wp-i18n');
        wp_enqueue_script('wp-data');

        // Enqueue our script with all required dependencies
        wp_enqueue_script(
            'tutorpress-curriculum-metabox',
            plugins_url('assets/js/curriculum-metabox.js', dirname(__FILE__)),
            [
                'wp-plugins',
                'wp-edit-post',
                'wp-element',
                'wp-i18n',
                'wp-data',
                'wp-components',
                'wp-dom-ready'
            ],
            filemtime(TUTORPRESS_PATH . 'assets/js/curriculum-metabox.js'),
            true
        );

        // Enqueue certificate metabox script
        wp_enqueue_script(
            'tutorpress-certificate-metabox',
            plugins_url('assets/js/certificate-metabox.js', dirname(__FILE__)),
            [
                'wp-plugins',
                'wp-edit-post',
                'wp-element',
                'wp-i18n',
                'wp-data',
                'wp-components',
                'wp-dom-ready',
                'wp-api-fetch'
            ],
            filemtime(TUTORPRESS_PATH . 'assets/js/certificate-metabox.js'),
            true
        );

        // Add custom CSS for the curriculum metabox
        wp_enqueue_style(
            'tutorpress-curriculum-metabox-style',
            plugins_url('assets/css/metaboxes.css', dirname(__FILE__)),
            ['wp-components'],
            filemtime(TUTORPRESS_PATH . 'assets/css/metaboxes.css')
        );

        // Add custom CSS for the certificate metabox
        wp_enqueue_style(
            'tutorpress-certificate-metabox-style',
            plugins_url('assets/css/certificate-metabox.css', dirname(__FILE__)),
            ['wp-components'],
            filemtime(TUTORPRESS_PATH . 'assets/css/certificate-metabox.css')
        );

        // Add localization data
        wp_localize_script('tutorpress-curriculum-metabox', 'tutorpressData', [
            'restUrl' => rest_url('tutorpress/v1'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'courseId' => $post_id,
            'i18n' => [
                'addTopic' => __('Add Topic', 'tutorpress'),
                'editTopic' => __('Edit Topic', 'tutorpress'),
                'deleteTopic' => __('Delete Topic', 'tutorpress'),
                'duplicateTopic' => __('Duplicate Topic', 'tutorpress'),
                'addLesson' => __('Add Lesson', 'tutorpress'),
                'addQuiz' => __('Add Quiz', 'tutorpress'),
                'addAssignment' => __('Add Assignment', 'tutorpress'),
                'saving' => __('Saving...', 'tutorpress'),
                'saved' => __('Saved', 'tutorpress'),
                'error' => __('Error', 'tutorpress'),
                'confirmDelete' => __('Are you sure you want to delete this?', 'tutorpress'),
                'cancel' => __('Cancel', 'tutorpress'),
                'save' => __('Save', 'tutorpress'),
            ],
            'capabilities' => [
                'canEdit' => current_user_can('edit_post', $post_id),
                'canDelete' => current_user_can('delete_post', $post_id),
            ],
        ]);

        // Add localization data for certificate metabox
        wp_localize_script('tutorpress-certificate-metabox', 'tutorpressCertData', [
            'restUrl' => rest_url('tutorpress/v1'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'courseId' => $post_id,
            'i18n' => [
                'loading' => __('Loading certificate templates...', 'tutorpress'),
                'noTemplates' => __('No templates available.', 'tutorpress'),
                'noCustomTemplates' => __('No custom certificates available.', 'tutorpress'),
                'saving' => __('Saving...', 'tutorpress'),
                'saved' => __('Saved', 'tutorpress'),
                'error' => __('Error', 'tutorpress'),
            ],
        ]);
    }

    /**
     * Register performance monitoring
     */
    private static function register_performance_monitoring() {
        add_action('wp_footer', function() {
            ?>
            <script>
            if (window.performance && window.performance.mark) {
                window.performance.mark('tutorpress_curriculum_builder_end');
                window.performance.measure(
                    'tutorpress_curriculum_builder',
                    'tutorpress_curriculum_builder_start',
                    'tutorpress_curriculum_builder_end'
                );
            }
            </script>
            <?php
        }, 999);

        add_action('admin_footer', function() {
            ?>
            <script>
            if (window.performance && window.performance.getEntriesByName) {
                const measures = window.performance.getEntriesByName('tutorpress_curriculum_builder');
                if (measures.length > 0) {
                    console.log('Curriculum Builder Performance:', measures[0].duration + 'ms');
                }
            }
            </script>
            <?php
        }, 999);
    }

    /**
     * Initialize performance features
     */
    private static function init_performance_features() {
        $options = get_option('tutorpress_settings', []);
        
        if (!empty($options['enable_performance_monitoring'])) {
            self::register_performance_monitoring();
        }

        // Add performance-related headers
        add_action('send_headers', function() {
            if (is_admin() && function_exists('get_current_screen')) {
                $screen = get_current_screen();
                if ($screen && $screen->base === 'post' && in_array($screen->post_type, ['courses', 'lessons'])) {
                    header('Link: ' . rest_url('tutorpress/v1') . '; rel=preconnect');
                }
            }
        });

        // Add performance mark cleanup
        self::cleanup_performance_marks();
    }

    /**
     * Cleanup performance marks
     */
    private static function cleanup_performance_marks() {
        add_action('admin_footer', function() {
            ?>
            <script>
            if (window.performance && window.performance.clearMarks) {
                window.performance.clearMarks('tutorpress_curriculum_builder_start');
            }
            </script>
            <?php
        }, 999);
    }
}

// Initialize the class
TutorPress_Scripts::init();
