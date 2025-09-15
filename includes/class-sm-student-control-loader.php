<?php
/**
 * SM Student Control Loader
 *
 * This class is responsible for loading necessary classes and functions for the plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class SM_Student_Control_Loader {

    /**
     * The admin instance.
     *
     * @var      SM_Student_Control_Admin
     */
    protected $admin;

    /**
     * The student details handler instance.
     *
     * @var      SM_Student_Details
     */
    protected $student_details;

    /**
     * Constructor.
     */
    public function __construct() {
        // Load dependencies
        $this->load_dependencies();
        
        // Instantiate all main classes immediately
        $this->init_classes();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        // Core plugin classes
        require_once SM_STUDENT_CONTROL_DIR . 'includes/class-sm-student-control-data.php';
        
        // Admin specific classes
        if ( is_admin() ) {
            require_once SM_STUDENT_CONTROL_DIR . 'admin/class-sm-student-control-admin.php';
            require_once SM_STUDENT_CONTROL_DIR . 'admin/class-student-details.php';
        }
    }
    
    /**
     * Initialize all plugin classes.
     */
    private function init_classes() {
        // Only instantiate admin components when:
        // 1. We're in the WordPress admin area
        // 2. The admin class is available
        if (is_admin()) {
            // The SM_Student_Control_Admin should be defined in class-sm-student-control-admin.php
            if (class_exists('SM_Student_Control_Admin')) {
                $this->admin = new SM_Student_Control_Admin();
            }
            
            // Student details handler gets initialized separately (if available)
            if (class_exists('SM_Student_Details')) {
                $this->student_details = new SM_Student_Details();
            }
        }
    }
}