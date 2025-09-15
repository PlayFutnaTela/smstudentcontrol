<?php
/**
 * Handles the student details view
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class SM_Student_Details {

    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('admin_init', array($this, 'handle_student_view'));
    }

    /**
     * Handle the student view request
     */
    public function handle_student_view() {
        // Only process on our admin page
        if (!isset($_GET['page']) || $_GET['page'] !== 'sm-student-control') {
            return;
        }
        
        // Check if we're viewing a student
        if (!isset($_GET['action']) || $_GET['action'] !== 'view' || !isset($_GET['student_id'])) {
            return;
        }
        
        // Add the content filter to replace the main admin content
        add_filter('sm_student_control_admin_content', array($this, 'display_student_details'));
    }

    /**
     * Display student details
     * 
     * @param int|null $student_id Optional student ID parameter
     * @return string HTML content for the details page
     */
    public function display_student_details($student_id = null) {
        // Use parameter if provided, otherwise fall back to GET
        if ($student_id === null) {
            $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
        }
        
        if (empty($student_id)) {
            return '<div class="notice notice-error"><p>' . __('Invalid student ID.', 'sm-student-control') . '</p></div>';
        }
        
        // Get student data from cache
        $student = SM_Student_Control_Cache::get_student_from_cache($student_id);
        
        if (!$student) {
            return '<div class="notice notice-error"><p>' . 
                __('Could not retrieve student data from cache. Please try again.', 'sm-student-control') .
                '</p></div>';
        }
        
        // Capture output
        ob_start();
        include SM_STUDENT_CONTROL_DIR . 'admin/views/student-details.php';
        return ob_get_clean();
    }
}