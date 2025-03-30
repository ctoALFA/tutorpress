# Tutor LMS Certificate System - Developer Documentation

This document provides a comprehensive technical reference of Tutor LMS's certificate system, designed for building plugins that interact with it.

## Core Files and Structure

### Main Entry Points

```
/tutor-pro/addons/tutor-certificate/tutor-certificate.php
/tutor-pro/addons/tutor-certificate/classes/init.php
/tutor-pro/addons/tutor-certificate/classes/Certificate.php
```

### Key Constants

```php
define('TUTOR_CERT_VERSION', '1.0.0');
define('TUTOR_CERT_FILE', __FILE__); // Points to tutor-certificate.php
```

### Helper Function

```php
// Gets certificate addon info
function TUTOR_CERT() {
    $info = array(
        'path'         => plugin_dir_path(TUTOR_CERT_FILE),
        'url'          => plugin_dir_url(TUTOR_CERT_FILE),
        'basename'     => plugin_basename(TUTOR_CERT_FILE),
        'version'      => TUTOR_CERT_VERSION,
        'nonce_action' => 'tutor_nonce_action',
        'nonce'        => '_wpnonce',
    );
    return (object) $info;
}
```

## Certificate Class Properties

```php
namespace TUTOR_CERT;

class Certificate {
    private $template; // Current template data
    public $certificates_dir_name = 'tutor-certificates'; // Directory name where certificates are stored
    public $certificate_stored_key = 'tutor_certificate_has_image'; // Meta key for certificate image storage
    public static $template_meta_key = 'tutor_course_certificate_template'; // Meta key for course certificate template
    public static $certificate_img_url_base = 'https://preview.tutorlms.com/certificate-templates/'; // Base URL for template images
}
```

## Certificate Template System

### Template Storage

Templates are stored in:
```
/tutor-pro/addons/tutor-certificate/templates/{template_key}/
```

Each template directory contains:
- `certificate.php` - HTML structure
- `pdf.css` - Styling
- `background.png` - Background image
- `font.css` (optional) - Font definitions

### Template Data Structure

```php
// Example template data structure
$template = [
    'name' => 'Template Name',
    'orientation' => 'landscape', // or 'portrait'
    'is_default' => true/false,
    'path' => '/path/to/template/',
    'url' => 'https://url/to/template/',
    'preview_src' => 'https://url/to/preview.png',
    'background_src' => 'https://url/to/background.png',
    'key' => 'template_key'
];
```

### Template Registration

```php
// Get all available templates
$templates = $certificate->get_templates($add_none, $include_admins, $template_in);

// Add custom template via filter
add_filter('tutor_certificate_templates', function($templates, $include_admins, $template_in) {
    $templates['my_template'] = [
        'name' => 'My Custom Template',
        'orientation' => 'landscape',
        'path' => plugin_dir_path(__FILE__) . 'templates/my_template/',
        'url' => plugin_dir_url(__FILE__) . 'templates/my_template/',
        // Other properties
    ];
    return $templates;
}, 10, 3);
```

## Certificate Generation Process

### Core Method

```php
/**
 * Generate Certificate HTML
 *
 * @param int $course_id
 * @param bool $completed Completion data
 * @return string HTML content
 */
public function generate_certificate($course_id, $completed = false) {
    // Implementation loads template and renders certificate
}
```

### AJAX Endpoint for Generation

```php
add_action('wp_ajax_tutor_generate_course_certificate', array($this, 'send_certificate_html'));
add_action('wp_ajax_nopriv_tutor_generate_course_certificate', array($this, 'send_certificate_html'));

/**
 * Send Certificate HTML via AJAX
 */
public function send_certificate_html() {
    // Processes AJAX request and returns HTML or builder URL
}
```

### Certificate Builder Integration

```php
// Check if certificate should use external builder
if (strpos($this->template['key'], 'tutor_cb_') === 0) {
    $template_id = preg_replace('/\D/', '', $this->template['key']);
    wp_send_json_success(
        array(
            'certificate_builder_url' => apply_filters(
                'tutor_certificate_builder_url',
                $template_id,
                array(
                    'cert_hash'   => $cert_hash,
                    'course_id'   => $course_id,
                    'orientation' => $this->template['orientation'],
                    'format'      => Input::post('format', 'jpg'),
                )
            ),
        )
    );
    exit;
}
```

### Storage of Generated Certificates

```php
add_action('wp_ajax_tutor_store_certificate_image', array($this, 'store_certificate_image'));
add_action('wp_ajax_nopriv_tutor_store_certificate_image', array($this, 'store_certificate_image'));

/**
 * Store certificate image
 */
public function store_certificate_image() {
    // Processes AJAX request to store generated certificate as image
}
```

## Certificate Viewing and Verification

### Certificate URL Generation

```php
/**
 * Get certificate public URL
 *
 * @param string $cert_hash
 * @return string
 */
public function tutor_certificate_public_url($cert_hash) {
    $url = '#';
    $page_id = (int) tutor_utils()->get_option('tutor_certificate_page');

    if (!in_array($page_id, array(0, -1))) {
        $page = get_post($page_id);
        $url = home_url() . DIRECTORY_SEPARATOR . $page->post_name . '?cert_hash=' . $cert_hash;
    }

    return $url;
}
```

### Certificate Verification

```php
/**
 * Get completed course data by certificate hash
 *
 * @param string $cert_hash Certificate hash
 * @param mixed $data Default return if not found
 * @return object|mixed
 */
public function completed_course($cert_hash, $data = false) {
    global $wpdb;
    $is_completed = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT comment_ID as certificate_id,
                comment_post_ID as course_id,
                comment_author as completed_user_id,
                comment_date as completion_date,
                comment_content as completed_hash
            FROM	$wpdb->comments
            WHERE 	comment_agent = %s
                    AND comment_type = %s
                    AND comment_content = %s",
            'TutorLMSPlugin',
            'course_completed',
            $cert_hash
        )
    );

    return !empty($is_completed) ? $is_completed : $data;
}
```

### Certificate Template Override

```php
add_filter('template_include', array($this, 'view_certificate'));

/**
 * View Certificate
 *
 * @param string $template
 * @return string
 */
public function view_certificate($template) {
    $cert_hash = Input::get('cert_hash');
    
    // Process certificate viewing and return template path
}
```

## Certificate Settings and Options

### Global Settings Registration

```php
add_filter('tutor/options/extend/attr', array($this, 'add_options'), 10);

/**
 * Add certificate options to Tutor settings
 * 
 * @param array $attr Settings attributes
 * @return array
 */
public function add_options($attr) {
    // Register certificate settings
}
```

### Settings Structure

```php
// Certificate settings fields
$attr['tutor_certificate'] = array(
    'label'    => __('Certificate', 'tutor-pro'),
    'slug'     => 'tutor_certificate',
    'desc'     => __('All Certificate Settings', 'tutor-pro'),
    'template' => 'tab',
    'icon'     => 'tutor-icon-certificate-landscape',
    'blocks'   => array(
        // Certificate settings blocks
    )
);
```

### Per-Course Certificate Template

```php
add_action('tutor_save_course', array($this, 'save_certificate_template_meta'));

/**
 * Save certificate template meta
 *
 * @param int $post_id Course ID
 */
public function save_certificate_template_meta($post_id) {
    if (Input::has(self::$template_meta_key)) {
        update_post_meta($post_id, self::$template_meta_key, Input::post(self::$template_meta_key));
    }
}
```

## Instructor Signature System

```php
namespace TUTOR_CERT;

class Instructor_Signature {
    private $file_name_string = 'tutor_pro_custom_signature_file';
    private $file_id_string = 'tutor_pro_custom_signature_id';
    private $image_meta = 'tutor_pro_custom_signature_image_id';
    private $image_post_identifier = 'tutor_pro_custom_signature_image';

    public function __construct($register_hooks = true) {
        if ($register_hooks) {
            add_action('tutor_profile_edit_input_after', array($this, 'custom_signature_field'));
            add_action('tutor_profile_update_before', array($this, 'save_custom_signature'));
        }
    }

    // Other methods for signature handling
}
```

## Frontend Certificate Integration

### Download Button

```php
add_action('tutor_course/single/actions_btn_group/before', array($this, 'certificate_download_btn'));

/**
 * Display certificate download button on course page
 */
public function certificate_download_btn() {
    // Display download button for certificate
}
```

### Course Archive Download Button

```php
add_filter('tutor_course/loop/start/button', array($this, 'download_btn_in_archive'), 99, 2);

/**
 * Add download button in course archive
 *
 * @param string $html
 * @param int $course_id
 * @return string
 */
public function download_btn_in_archive($html, $course_id) {
    // Add download button in course archive listings
}
```

### Certificate Showcase

```php
add_action('tutor_course/single/after/topics', array($this, 'add_certificate_showcase'));

/**
 * Add certificate showcase to course page
 *
 * @param int $course_id
 */
public function add_certificate_showcase($course_id) {
    // Display certificate showcase on course page
}
```

## Client-Side Certificate Processing

### HTML to Image Conversion

```javascript
// From html-to-image.js
var dispatchConversionMethods = function(action, document, callback) {
    var body = document.getElementsByTagName('body')[0];
    var watermark = document.getElementById('watermark');
    var width = watermark.offsetWidth;
    var height = watermark.offsetHeight;
    
    // Set body styling for proper rendering
    body.style.display = 'inline-block';
    body.style.overflow = 'hidden';
    body.style.width = width + 'px';
    body.style.height = height + 'px';
    body.style.padding = '0px';
    body.style.margin = '0px';
    
    var targetElement = document.getElementsByTagName('body')[0];
    
    // HTML2Canvas options
    var options = {
        scale: 3,
        letterRendering: true,
        logging: true,
        foreignObjectRendering: isChrome,
        allowTaint: true,
        useCORS: true,
        x: 0,
        y: 0,
        width: width,
        height: height,
        windowWidth: width,
        windowHeight: height
    };
    
    // Process images before conversion
    prepareImages(document, function() {
        html2canvas(targetElement, options).then(function(canvas) {
            var imageData = canvas.toDataURL('image/jpeg', 1);
            
            // Store the certificate image
            storeCertificate(imageData, function(success, message) {
                // Handle callback after storage
            });
        });
    });
};
```

### Font Loading

```javascript
var loadFonts = function(courseId, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open('get', '?tutor_action=get_fonts&course_id=' + courseId);
    xhr.responseType = 'text';
    xhr.send();
    xhr.onloadend = function() {
        var fontCss = xhr.response;
        var urls = fontCss.match(/https?:\/\/[^ \)]+/g);
        var completed = 0;
        
        // Load each font file
        urls.forEach(function(url) {
            var fontRequest = new XMLHttpRequest();
            fontRequest.open('get', url);
            fontRequest.responseType = 'blob';
            fontRequest.onloadend = function() {
                var reader = new FileReader();
                reader.onloadend = function() {
                    fontCss = fontCss.replace(new RegExp(url), reader.result);
                    completed++;
                    if (completed == urls.length) {
                        $('#tutor_svg_font_id').prepend('<style>' + fontCss + '</style>');
                        callback();
                    }
                };
                reader.readAsDataURL(fontRequest.response);
            };
            fontRequest.send();
        });
    };
};
```

## Database Schema

### Certificate Record

Certificates are tracked in the WordPress comments table with specific format:

| Field            | Value                         | Description                                 |
|------------------|-------------------------------|---------------------------------------------|
| comment_ID       | (integer)                     | Certificate ID                              |
| comment_post_ID  | (integer)                     | Course ID                                   |
| comment_author   | (integer)                     | User ID of student                          |
| comment_date     | (datetime)                    | Completion Date                             |
| comment_content  | (string)                      | Certificate Hash - unique identifier        |
| comment_agent    | 'TutorLMSPlugin'              | Fixed value to identify Tutor LMS records   |
| comment_type     | 'course_completed'            | Record type                                 |

### Certificate Image Storage

Certificate images are stored in:
- **Path**: wp-upload_dir/tutor-certificates/{random_string}-{cert_hash}.jpg
- **Meta**: The random string is stored in comment meta with key 'tutor_certificate_has_image'

## Template Initialization

```php
/**
 * Prepare template data
 *
 * @param int $course_id
 * @param bool $check_if_none Check if template is explicitly set to none
 * @return mixed
 */
private function prepare_template_data($course_id, $check_if_none = false) {
    if (!$this->template) {
        // Get template from settings or use default
        $template = tutor_utils()->get_option('certificate_template');
        !$template ? $template = 'default' : 0;
        
        $global_template = $template;
        
        // Get course-specific template if set
        $course_template = get_post_meta($course_id, self::$template_meta_key, true);
        
        // Prepare template arguments
        $template_arg = array();
        $template_arg[] = $template;
        $course_template ? $template_arg[] = $course_template : 0;
        $templates = $this->get_templates(false, false, $template_arg);
        
        // Use course template if available
        ($course_template && isset($templates[$course_template])) ? $template = $course_template : 0;
        
        // Check if explicitly set to none
        if ($check_if_none && in_array($course_template, array('none', 'off'))) {
            return false;
        }
        
        // Check for certificate builder templates
        if (strpos($template, 'tutor_cb_') === 0 && !tutor_utils()->is_plugin_active('tutor-lms-certificate-builder/tutor-lms-certificate-builder.php')) {
            // Fall back to default if builder is not active
            $template = $global_template;
        }
        
        $this->template = tutor_utils()->avalue_dot($template, $templates);
    }
}
```

## Certificate REST API (Version 3.0.0+)

### Certificate List Endpoint

```php
/**
 * Get course certificate list
 *
 * @since 3.0.0
 * @return void
 */
public function ajax_course_certificate_list() {
    if (!tutor_utils()->is_nonce_verified()) {
        $this->json_response(tutor_utils()->error_message('nonce'), null, HttpHelper::STATUS_BAD_REQUEST);
    }

    $has_access_role = User::has_any_role(array(User::ADMIN, User::INSTRUCTOR));

    if (!$has_access_role) {
        $this->json_response(
            tutor_utils()->error_message(HttpHelper::STATUS_UNAUTHORIZED),
            null,
            HttpHelper::STATUS_UNAUTHORIZED
        );
    }

    $templates = $this->get_templates();
    $data = array_values($templates);

    $this->json_response(
        __('Certificate list fetched successfully!', 'tutor-pro'),
        $data
    );
}
```

### Course Details Response Extension

```php
/**
 * Extend course details response
 *
 * @since 3.0.0
 * @param array $data response data
 * @return array
 */
public function extend_course_details_response(array $data) {
    $course_id = $data['ID'];
    $template_key = get_post_meta($course_id, self::$template_meta_key, true);
    $template_key = $template_key ? $template_key : 'default';

    $templates = $this->get_templates(false, true);

    $hide_default_certificates_for_instructors = (bool) tutor_utils()->get_option('hide_default_certificates_for_instructors', false);
    if (User::is_only_instructor() && $hide_default_certificates_for_instructors) {
        $templates = array_filter(
            $templates,
            function($template) use ($template_key) {
                return ($template['key'] === $template_key) || !isset($template['is_default']);
            }
        );
    }

    $templates = array_values($templates);

    $data['course_certificate_template'] = $template_key;
    $data['course_certificates_templates'] = $templates;

    return $data;
}
```

## Integration Hooks

### Actions

```php
// Certificate generation and viewing
do_action('tutor_certificate/before_content');
do_action('tutor_certificate_access', $completed); // Control access to certificates

// Certificate template registration
do_action('tutor_certificate_templates', $templates, $include_admins, $template_in);

// Certificate builder integration
do_action('tutor_certificate_builder_url', $template_id, $args);

// Course template handling
do_action('tutor_course_certificate_template', $course_id);
```

### Filters

```php
// Certificate URL and data
apply_filters('tutor_certificate_public_url', $cert_hash);
apply_filters('tutor_pro_certificate_access', true, $completed);
apply_filters('tutor_certificate_instructor_signature', $instructor_id, $use_default);
apply_filters('tutor_cert_authorised_name', $authorized);

// Template registration
apply_filters('tutor_certificate_templates', $templates, $include_admins, $template_in);

// Certificate course data
apply_filters('tutor_course/single/benefits', $array, $course_id);
apply_filters('tutor_course/single/requirements', $array, $course_id);
apply_filters('tutor_course/single/target_audience', $array, $course_id);
apply_filters('tutor_course/single/material_includes', $array, $course_id);

// CSS customization
apply_filters('tutor_cer_css', $css, $this);

// Certificate response data
apply_filters('tutor_course_details_response', $data);
```



### Available Template Variables

```php
$course            // WP_Post object of the course
$user              // WP_User object of the student
$completed         // Object with completion data
$completed_date    // Formatted completion date
$signature_image_url // URL to instructor/default signature
$duration_text     // Formatted course duration
```

## Options and Meta Keys Reference

```php
// Certificate meta keys
'tutor_course_certificate_template'   // Course-specific template
'tutor_certificate_has_image'         // Certificate image reference
'tutor_pro_custom_signature_image_id' // Instructor signature image ID

// Certificate options
'certificate_template'                 // Default template
'tutor_cert_authorised_name'           // Authorised name
'tutor_cert_authorised_company_name'   // Company name
'tutor_certificate_page'               // Certificate page ID
'show_instructor_name_on_certificate'  // Show instructor name
'send_certificate_link_to_course_completion_email' // Include in email
'tutor_cert_signature_image_id'        // Default signature image
'enable_certificate_showcase'          // Enable showcase
'certificate_showcase_title'           // Showcase title
'certificate_showcase_desc'            // Showcase description
'hide_default_certificates_for_instructors' // Hide default templates for instructors
```

