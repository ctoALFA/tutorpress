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
        
        // Debug information
        error_log('TutorPress: enqueue_gutenberg_scripts called with post: ' . print_r($post, true));
        error_log('TutorPress: screen info: ' . print_r($screen, true));
        
        // Always load on post edit screens to ensure it's loaded
        if (!$screen || $screen->base !== 'post') {
            error_log('TutorPress: Not a post edit screen');
            return;
        }

        error_log('TutorPress: Loading curriculum metabox script for post edit screen');

        $post_id = isset($post) ? $post->ID : 0;
        $post_type = isset($post) ? $post->post_type : '';
        
        if (empty($post_type) && isset($_GET['post_type'])) {
            $post_type = $_GET['post_type'];
        }
        
        error_log('TutorPress: Post ID: ' . $post_id . ', Post Type: ' . $post_type);

        // Register dependencies first 
        wp_enqueue_script('wp-element');
        wp_enqueue_script('wp-dom-ready');
        wp_enqueue_script('wp-components');
        wp_enqueue_script('wp-plugins');
        wp_enqueue_script('wp-edit-post');
        wp_enqueue_script('wp-i18n');
        wp_enqueue_script('wp-data');

        // Now register our script with all required dependencies
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

        // Add custom CSS for the curriculum metabox
        wp_enqueue_style(
            'tutorpress-curriculum-metabox-style',
            plugins_url('assets/css/metaboxes.css', dirname(__FILE__)),
            ['wp-components'],
            filemtime(TUTORPRESS_PATH . 'assets/css/metaboxes.css')
        );

        // Add localization data
        wp_localize_script('tutorpress-curriculum-metabox', 'TutorPressCurriculum', [
            'restUrl' => rest_url('tutorpress/v1'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'courseId' => $post_id,
            'debug' => [
                'postId' => $post_id,
                'postType' => $post_type,
                'screenId' => $screen->id,
                'screenBase' => $screen->base,
                'screenPostType' => $screen->post_type,
                'isGutenberg' => true,
                'inEditor' => is_admin() && $screen->base === 'post',
                'isCourse' => $post_type === 'courses' || $screen->post_type === 'courses'
            ],
            'i18n' => [
                'loading' => __('Loading...', 'tutorpress'),
                'addTopic' => __('Add Topic', 'tutorpress'),
                'addContent' => __('Add Content', 'tutorpress'),
                'confirmDeleteTopic' => __('Are you sure you want to delete this topic?', 'tutorpress'),
                'confirmDeleteContent' => __('Are you sure you want to delete this content?', 'tutorpress'),
                'lesson' => __('Lesson', 'tutorpress'),
                'quiz' => __('Quiz', 'tutorpress'),
                'assignment' => __('Assignment', 'tutorpress')
            ]
        ]);

        wp_set_script_translations(
            'tutorpress-curriculum-metabox',
            'tutorpress'
        );
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
