<?php
/**
 * The admin-specific functionality of the plugin.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class SM_Student_Control_Admin {

    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_update_single_student', array($this, 'update_single_student_handler'));
        add_action('wp_ajax_nopriv_update_single_student', array($this, 'restricted_access_handler'));
        add_action('wp_ajax_export_students_excel', array($this, 'export_students_excel_handler'));
    }

    /**
     * Add menu item for the plugin
     */
    public function add_admin_menu() {
        add_menu_page(
            'SM Student Control', 
            'Student Control',
            'manage_options',
            'sm-student-control',
            array($this, 'display_admin_page'),
            'dashicons-groups',
            30
        );
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles($hook) {
        // Verificar se $hook não é nulo antes de usar strpos()
        if (empty($hook)) {
            return;
        }
        
        // Verificar se $hook não é nulo antes de usar
        if (empty($hook) || strpos($hook, 'sm-student-control') === false) {
            return;
        }
        
        // Obter o timestamp do arquivo CSS para versionamento dinâmico
        $css_file_path = SM_STUDENT_CONTROL_DIR . 'assets/css/sm-student-control-admin.css';
        $css_version = file_exists($css_file_path) ? filemtime($css_file_path) : SM_STUDENT_CONTROL_VERSION;
        
        wp_enqueue_style(
            'sm-student-control-admin',
            SM_STUDENT_CONTROL_URL . 'assets/css/sm-student-control-admin.css',
            array(),
            $css_version  // Usar timestamp como versão
        );
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts($hook) {
        // Verificar se $hook não é nulo antes de usar
        if (empty($hook)) {
            return;
        }
        
        // Certificar-se de que $hook não é null
        if (empty($hook) || strpos($hook, 'sm-student-control') === false) {
            return;
        }
        
        // Obter timestamp do arquivo JS para versionamento dinâmico
        $admin_js_path = SM_STUDENT_CONTROL_DIR . 'assets/js/sm-student-control-admin.js';
        $cache_js_path = SM_STUDENT_CONTROL_DIR . 'assets/js/sm-student-control-cache.js';
        
        $admin_js_version = file_exists($admin_js_path) ? filemtime($admin_js_path) : SM_STUDENT_CONTROL_VERSION;
        $cache_js_version = file_exists($cache_js_path) ? filemtime($cache_js_path) : SM_STUDENT_CONTROL_VERSION;
        
        // Script de admin existente
        wp_enqueue_script(
            'sm-student-control-admin',
            SM_STUDENT_CONTROL_URL . 'assets/js/sm-student-control-admin.js',
            array('jquery'),
            $admin_js_version,  // Usar timestamp como versão
            true
        );
        
        // Adicionar script de cache (movido do loader)
        wp_enqueue_script(
            'sm-student-control-cache',
            SM_STUDENT_CONTROL_URL . 'assets/js/sm-student-control-cache.js',
            array('jquery'),
            $cache_js_version,  // Usar timestamp como versão
            true
        );
        
        // Localizar scripts para ambos os arquivos
        $nonce = wp_create_nonce('sm_student_control_nonce');
        
        // Para o script de cache
        wp_localize_script(
            'sm-student-control-cache',
            'sm_student_control',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => $nonce,
                'i18n' => array(
                    'updating' => __('Atualizando dados...', 'sm-student-control'),
                    'success' => __('Dados atualizados com sucesso!', 'sm-student-control'),
                    'error' => __('Erro ao atualizar dados.', 'sm-student-control')
                )
            )
        );
        
        // Para o script de admin (incluindo export)
        wp_localize_script(
            'sm-student-control-admin',
            'sm_student_control_vars',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => $nonce,
                'i18n' => array(
                    'export_success' => __('Arquivo exportado com sucesso!', 'sm-student-control'),
                    'export_error' => __('Erro ao exportar arquivo.', 'sm-student-control')
                )
            )
        );
    }

    /**
     * Display the admin page
     */
    public function display_admin_page() {
        // Inicializar a variável content com valor default
        $content = '';
        
        // Check if we're viewing a specific student
        if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['student_id'])) {
            // Student details page
            if (class_exists('SM_Student_Details')) {
                $student_details = new SM_Student_Details();
                $student_id = intval($_GET['student_id']);
                
                // Apenas obter o conteúdo, não exibir ainda
                $content = $student_details->display_student_details($student_id);
            } else {
                $content = '<div class="notice notice-error"><p>' . 
                    __('Student details handler not available.', 'sm-student-control') . 
                    '</p></div>';
            }
        } else {
            // Students list page
            $content = $this->display_students_list_page();
        }
        
        // IMPORTANTE: Só iniciar o HTML do plugin DEPOIS que o WordPress renderizou suas mensagens
        ?>
        <div class="wrap sm-student-control">
            <?php echo $content; ?>
        </div>
        <?php
    }

    /**
     * Display the students list page
     */
    public function display_students_list_page() {
        // Inicializar variáveis
        $content = '';
        
        // Pegar parâmetros de filtro, ordenação e paginação da URL
        $student_search = isset($_GET['student_search']) ? sanitize_text_field($_GET['student_search']) : '';
        $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : '';
        $last_access_month = isset($_GET['last_access_month']) ? sanitize_text_field($_GET['last_access_month']) : '';
        
        // Parâmetros de ordenação
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'name';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'asc';
        
        // Paginação
        $per_page = 30; // Número fixo de itens por página
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        // Obter cursos para o dropdown do filtro
        $courses_list = SM_Student_Control_Data::get_all_courses();
        
        // Buscar alunos no cache com paginação
        $students_data = SM_Student_Control_Cache::get_all_students_from_cache(
            $student_search, 
            $course_id, 
            $last_access_month,
            $orderby,
            $order,
            $per_page,
            $paged
        );
        
        $students = $students_data['items'];
        $total_students = $students_data['total'];
        $total_pages = ceil($total_students / $per_page);
        
        // Incluir o template com os dados necessários
        ob_start();
        include SM_STUDENT_CONTROL_DIR . 'admin/views/students-list-table.php';
        $content = ob_get_clean();
        
        return $content;
    }

    /**
     * AJAX handler para atualizar um único aluno
     */
    public function update_single_student_handler() {
        // Verificar segurança
        check_ajax_referer('sm_student_control_nonce', 'security');
        
        // Obter ID do usuário
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        if (empty($user_id)) {
            wp_send_json_error(['message' => __('ID de usuário inválido', 'sm-student-control')]);
            return;
        }
        
        // Atualizar o cache sem cálculo de tempo desnecessário
        $success = SM_Student_Control_Cache::update_cache($user_id);
        
        // Preparar resposta
        if ($success) {
            wp_send_json_success(['message' => __('Cache atualizado com sucesso', 'sm-student-control')]);
        } else {
            wp_send_json_error(['message' => __('Falha ao atualizar o cache', 'sm-student-control')]);
        }
    }
    
    /**
     * Restrict access for non-logged in users
     */
    public function restricted_access_handler() {
        wp_send_json_error(['message' => __('Acesso restrito', 'sm-student-control')]);
    }

    /**
     * AJAX handler para exportar dados dos alunos para Excel
     */
    public function export_students_excel_handler() {
        // Debug log
        error_log('SM Student Control: Export handler called');
        
        // Verificar se é requisição AJAX
        if (!wp_doing_ajax()) {
            error_log('SM Student Control: Not an AJAX request');
            wp_send_json_error(['message' => 'Requisição inválida']);
            return;
        }
        
        // Verificar segurança
        if (!check_ajax_referer('sm_student_control_nonce', 'security', false)) {
            error_log('SM Student Control: Security check failed');
            wp_send_json_error(['message' => 'Falha na verificação de segurança']);
            return;
        }
        
        // Verificar permissões
        if (!current_user_can('manage_options')) {
            error_log('SM Student Control: Permission denied');
            wp_send_json_error(['message' => 'Permissão insuficiente']);
            return;
        }

        error_log('SM Student Control: Security and permissions OK');

        // Obter parâmetros de filtro
        $filters = array(
            'student_search' => sanitize_text_field($_POST['student_search'] ?? ''),
            'course_id' => intval($_POST['course_id'] ?? 0),
            'last_access_month' => sanitize_text_field($_POST['last_access_month'] ?? '')
        );

        error_log('SM Student Control: Filters - ' . print_r($filters, true));

        try {
            // Gerar arquivo CSV
            $file_url = $this->generate_csv_file($filters);
            
            error_log('SM Student Control: File URL generated - ' . ($file_url ? $file_url : 'false'));
            
            if ($file_url) {
                wp_send_json_success(['file_url' => $file_url]);
            } else {
                error_log('SM Student Control: generate_csv_file returned false');
                wp_send_json_error(['message' => 'Nenhum dado encontrado para exportar ou erro na geração do arquivo']);
            }
            
        } catch (Exception $e) {
            error_log('SM Student Control: Exception - ' . $e->getMessage());
            error_log('SM Student Control: Exception trace - ' . $e->getTraceAsString());
            wp_send_json_error(['message' => 'Erro na exportação: ' . $e->getMessage()]);
        } catch (Error $e) {
            error_log('SM Student Control: Fatal Error - ' . $e->getMessage());
            error_log('SM Student Control: Fatal Error trace - ' . $e->getTraceAsString());
            wp_send_json_error(['message' => 'Erro fatal: ' . $e->getMessage()]);
        }
    }

    /**
     * Gera arquivo CSV com dados dos alunos (compatível com Excel)
     */
    private function generate_csv_file($filters) {
        error_log('SM Student Control: Starting CSV generation with real data');
        
        try {
            // Primeiro, obter os dados reais dos alunos usando o cache
            if (!class_exists('SM_Student_Control_Cache')) {
                require_once plugin_dir_path(__FILE__) . '../includes/class-sm-student-control-cache.php';
            }
            
            // Usar a mesma função que popula a tabela principal
            $students_data = SM_Student_Control_Cache::get_all_students_from_cache(
                $filters['student_search'], 
                $filters['course_id'], 
                $filters['last_access_month'],
                'name', // orderby
                'asc',  // order
                1000,   // limite alto para pegar todos
                1       // página 1
            );
            
            $students = $students_data['items'];
            error_log('SM Student Control: Found ' . count($students) . ' students for export');

            if (empty($students)) {
                error_log('SM Student Control: No students found for export');
                return false;
            }

            // Criar diretório de uploads
            $upload_dir = wp_upload_dir();
            
            if (isset($upload_dir['error']) && $upload_dir['error']) {
                error_log('SM Student Control: WordPress upload dir error: ' . $upload_dir['error']);
                return false;
            }
            
            $plugin_upload_dir = $upload_dir['basedir'] . '/sm-student-control/';
            
            if (!file_exists($plugin_upload_dir)) {
                $created = wp_mkdir_p($plugin_upload_dir);
                if (!$created) {
                    error_log('SM Student Control: Failed to create directory');
                    return false;
                }
            }

            // Nome do arquivo alterado para "tabela_"
            $filename = 'tabela_' . date('Y-m-d_H-i-s') . '.csv';
            $file_path = $plugin_upload_dir . $filename;
            
            error_log('SM Student Control: Creating file - ' . $file_path);

            // Abrir arquivo para escrita
            $file = fopen($file_path, 'w');
            
            if (!$file) {
                error_log('SM Student Control: Could not open file for writing');
                return false;
            }

            // Adicionar BOM para UTF-8 (compatibilidade com Excel)
            fwrite($file, "\xEF\xBB\xBF");

            // Cabeçalhos baseados na tabela real
            $headers = [
                'ID',
                'Nome',
                'Email',
                'Data de registro',
                'Último acesso',
                'Cursos matriculados'
            ];

            fputcsv($file, $headers, ';');

            // Preencher com dados reais dos alunos
            foreach ($students as $student) {
                // Obter dados do usuário
                $user_info = get_userdata($student['user_id']);
                
                $row = [
                    $student['user_id'],
                    $user_info ? $user_info->display_name : 'N/A',
                    $user_info ? $user_info->user_email : 'N/A',
                    $user_info ? $user_info->user_registered : 'N/A',
                    isset($student['last_login_formatted']) ? $student['last_login_formatted'] : '-',
                    isset($student['enrolled_courses_count']) ? $student['enrolled_courses_count'] : 0
                ];
                
                fputcsv($file, $row, ';');
            }

            fclose($file);
            
            error_log('SM Student Control: File written successfully with ' . count($students) . ' records');

            // Retornar URL do arquivo
            $file_url = $upload_dir['baseurl'] . '/sm-student-control/' . $filename;
            error_log('SM Student Control: Returning file URL - ' . $file_url);
            
            return $file_url;

        } catch (Exception $e) {
            error_log('SM Student Control: CSV generation error - ' . $e->getMessage());
            return false;
        }
    }
}