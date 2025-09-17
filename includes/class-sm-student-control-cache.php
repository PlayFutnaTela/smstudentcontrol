<?php
/**
 * Responsável pelo sistema de cache de dados dos alunos
 *
 * Esta classe gerencia o armazenamento em cache dos dados dos alunos,
 * permitindo consultas mais rápidas e eficientes.
 *
 * @since      1.0.0
 * @package    SM_Student_Control
 * @subpackage SM_Student_Control/includes
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class SM_Student_Control_Cache {

    /**
     * Nome da tabela de cache
     *
     * @var string
     */
    private static $table_name;

    /**
     * Inicializa a propriedade estática de nome da tabela
     */
    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'sm_student_control_cache';
    }

    /**
     * Retorna o nome da tabela de cache
     *
     * @return string Nome da tabela
     */
    public static function get_table_name() {
        // A tabela já deve estar inicializada pelo hook plugins_loaded
        return self::$table_name;
    }

    /**
     * Cria a tabela de cache com todas as colunas necessárias
     */
    public static function create_cache_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_student_control_cache';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            full_name varchar(255) DEFAULT '',
            email varchar(255) DEFAULT '',
            username varchar(255) DEFAULT '',
            registration_date varchar(255) DEFAULT '',
            last_access_timestamp bigint(20) DEFAULT 0,
            courses_data longtext DEFAULT NULL,
            course_history_data longtext DEFAULT NULL,
            quizzes_data longtext DEFAULT NULL,
            lessons_data longtext DEFAULT NULL,
            all_lessons_count int(11) DEFAULT 0,
            all_quizzes_count int(11) DEFAULT 0,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Verifica se a tabela de cache existe
     *
     * @return bool True se a tabela existe
     */
    public static function cache_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_student_control_cache';
        
        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }

    /**
     * Atualiza o cache para um usuário específico
     */
    public static function update_cache($user_id) {
        // ADICIONAR ESTE BLOCO: Verificar se user_id é um array e processar recursivamente se for
        if (is_array($user_id)) {
            $success = true;
            foreach ($user_id as $single_id) {
                $result = self::update_cache($single_id);
                if (!$result) {
                    $success = false;
                }
            }
            return $success;
        }
        
        
        // Verificar se é um ID de WordPress válido antes de prosseguir
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_student_control_cache';
        $user_ids = is_array($user_id) ? $user_id : array($user_id);
        $success = true;
        
        foreach ($user_ids as $id) {
            try {
                // Definir limite de tempo mais alto para operações pesadas
                $original_time_limit = ini_get('max_execution_time');
                set_time_limit(120); // 2 minutos
                
                // Obter dados do usuário
                $user_data = get_userdata($id);
                
                // IMPORTANTE: Mesmo se o usuário não existir mais, tentamos recuperar outros dados
                $full_name = $user_data ? $user_data->display_name : 'Usuário Removido #' . $id;
                $email = $user_data ? $user_data->user_email : '';
                $username = $user_data ? $user_data->user_login : '';
                $registration_date = $user_data ? $user_data->user_registered : '';
                
                // Obter último acesso
                $last_access = SM_Student_Control_Data::get_student_last_access($id);
                $last_access_timestamp = !empty($last_access) ? intval($last_access) : 0;
                
                // Obter dados dos cursos, quizzes e lições
                $courses = SM_Student_Control_Data::get_student_courses($id);
                
                // OTIMIZAÇÃO: Gerar o histórico de cursos através de cursos não ativos 
                // em vez de chamar get_student_course_history() que faz consultas redundantes
                $course_history = array();
                $active_course_ids = array_column($courses, 'course_id');
                
                // Buscar interações com lições e quizzes para cursos não mais ativos
                $all_lessons = SM_Student_Control_Data::get_all_student_lessons($id);
                $all_quizzes = SM_Student_Control_Data::get_all_student_quizzes($id);
                
                // Extrair IDs de cursos de lições e quizzes
                $lesson_course_ids = array_unique(array_column($all_lessons, 'course_id'));
                $quiz_course_ids = array_unique(array_column($all_quizzes, 'course_id'));
                
                // Mesclar todos os IDs de cursos
                $all_course_ids = array_unique(array_merge($lesson_course_ids, $quiz_course_ids));
                
                // Filtrar para obter apenas cursos inativos
                $history_course_ids = array_diff($all_course_ids, $active_course_ids);
                
                // Processar histórico de cursos (somente IDs essenciais)
                foreach ($history_course_ids as $course_id) {
                    $course_post = get_post($course_id);
                    $course_history[] = array(
                        'course_id' => $course_id,
                        'course_name' => $course_post ? $course_post->post_title : 'Course #' . $course_id . ' (Removed)',
                        'course_status' => $course_post ? $course_post->post_status : 'deleted'
                    );
                }
                
                $quizzes = []; // Initialize empty array before use
                $quizzes = SM_Student_Control_Data::get_student_recent_quizzes($id, 10);
                $lessons = SM_Student_Control_Data::get_student_recent_lessons($id, 10);
                
                // Armazenar no cache
                $data = array(
                    'user_id' => $id,
                    'full_name' => $full_name,
                    'email' => $email,
                    'username' => $username,
                    'registration_date' => $registration_date,
                    'last_access_timestamp' => $last_access_timestamp,
                    'courses_data' => json_encode($courses),
                    'course_history_data' => json_encode($course_history),
                    'quizzes_data' => json_encode($quizzes),
                    'lessons_data' => json_encode($lessons),
                    'all_lessons_count' => count($all_lessons),
                    'all_quizzes_count' => count($all_quizzes),
                    'updated_at' => current_time('mysql')
                );
                
                $formats = array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s');
                
                // CORREÇÃO: Verificar se o registro já existe
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
                    $id
                )) > 0;

                // Inserir ou atualizar o registro
                if ($exists) {
                    $wpdb->update($table_name, $data, array('user_id' => $id), $formats, array('%d'));
                } else {
                    $wpdb->insert($table_name, $data, $formats);
                }
                
            } catch (Exception $e) {
                $success = false;
            } finally {
                // Restaurar limite de tempo original
                set_time_limit($original_time_limit);
            }
        }
        
        return $success;
    }

    /**
     * Obtém dados de um aluno específico do cache
     * 
     * @param int $student_id ID do aluno
     * @return array Array com dados do aluno ou array vazio se não encontrado
     */
    public static function get_student_from_cache($student_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_student_control_cache';
        
        // Verificar se a tabela existe
        if (!self::cache_table_exists()) {
            return array();
        }
        
        $student = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d",
            $student_id
        ), ARRAY_A);
        
        if (empty($student)) {
            return array();
        }
        
        // Processar dados de quizzes e lições
        $quizzes = json_decode($student['quizzes_data'] ?? '[]', true) ?: array();
        $lessons = json_decode($student['lessons_data'] ?? '[]', true) ?: array();
        
        // CORREÇÃO: Incluir todos os campos necessários no array de retorno, verificando existência
        $result = array(
            'id' => $student['user_id'],
            'full_name' => $student['full_name'] ?? '',
            'email' => $student['email'] ?? '',
            'username' => $student['username'] ?? '',
            'registration_date' => $student['registration_date'] ?? '',
            'last_access' => $student['last_access_timestamp'] ?? '',
            'courses' => json_decode($student['courses_data'] ?? '[]', true) ?: array(),
            'course_history' => json_decode($student['course_history_data'] ?? '[]', true) ?: array(),
            'quizzes' => $quizzes,
            'lessons' => $lessons,
            'all_lessons_count' => $student['all_lessons_count'] ?? 0,
            'all_quizzes_count' => $student['all_quizzes_count'] ?? 0,
            'updated_at' => $student['updated_at'] ?? ''
        );
        
        return $result;
    }

    /**
     * Obtém todos os alunos do cache com filtros opcionais e paginação
     *
     * @param string $student_search Termo de busca (nome ou email)
     * @param int $course_id ID do curso para filtrar
     * @param string $last_access_month Mês de último acesso (formato: YYYY-MM)
     * @param string $orderby Campo para ordenação
     * @param string $order Direção da ordenação (asc/desc)
     * @param int $per_page Itens por página
     * @param int $paged Número da página atual
     * @return array Array com 'items' (alunos) e 'total' (total de registros)
     */
    public static function get_all_students_from_cache($student_search = '', $course_id = '', $last_access_month = '', $orderby = 'name', $order = 'asc', $per_page = 50, $paged = 1) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_student_control_cache';
        
        // Verificar se a tabela existe
        if (!self::cache_table_exists()) {
            return array('items' => array(), 'total' => 0);
        }
        
        // Construir a consulta base
        $sql_select = "SELECT * FROM {$table_name}";
        $sql_count = "SELECT COUNT(*) FROM {$table_name}";
        $where_conditions = array();
        $where_values = array();
        
        // Filtro por nome ou email
        if (!empty($student_search)) {
            $where_conditions[] = "(full_name LIKE %s OR email LIKE %s)";
            $where_values[] = '%' . $wpdb->esc_like($student_search) . '%';
            $where_values[] = '%' . $wpdb->esc_like($student_search) . '%';
        }
        
        // Filtro por curso
        if (!empty($course_id)) {
            $where_conditions[] = "courses_data LIKE %s";
            $where_values[] = '%"course_id":' . $wpdb->esc_like($course_id) . '%';
        }
        
        // Filtro por mês de último acesso
        if (!empty($last_access_month)) {
            list($year, $month) = explode('-', $last_access_month);
            
            // Criar timestamps UNIX para o início e fim do mês
            $start_timestamp = mktime(0, 0, 0, $month, 1, $year);
            $end_timestamp = mktime(23, 59, 59, $month, date('t', $start_timestamp), $year);
            
            error_log("SM Student Control: Filtering by last access month: {$last_access_month}");
            error_log("SM Student Control: Start timestamp: {$start_timestamp} (" . date('Y-m-d H:i:s', $start_timestamp) . ")");
            error_log("SM Student Control: End timestamp: {$end_timestamp} (" . date('Y-m-d H:i:s', $end_timestamp) . ")");
            
            $where_conditions[] = "(last_access_timestamp BETWEEN %d AND %d)";
            $where_values[] = $start_timestamp;
            $where_values[] = $end_timestamp;
        }
        
        // Adicionar WHERE se houver condições
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = " WHERE " . implode(' AND ', $where_conditions);
        }
        
        // Adicionar ordenação
        $valid_columns = array(
            'name' => 'full_name',
            'registration_date' => 'registration_date',
            'last_access' => 'last_access_timestamp'
        );
        
        $sql_orderby = isset($valid_columns[$orderby]) ? $valid_columns[$orderby] : 'full_name';
        $sql_order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        
        // Adicionar limites para paginação
        $offset = ($paged - 1) * $per_page;
        $limit_clause = " LIMIT {$per_page} OFFSET {$offset}";
        
        // Consultas finais
        $sql_select .= $where_clause . " ORDER BY {$sql_orderby} {$sql_order}" . $limit_clause;
        $sql_count .= $where_clause;
        
        // Preparar e executar a consulta de contagem
        if (!empty($where_values)) {
            $prepared_count = $wpdb->prepare($sql_count, $where_values);
        } else {
            $prepared_count = $sql_count;
        }
        $total = $wpdb->get_var($prepared_count);
        
        // Preparar e executar a consulta de seleção
        if (!empty($where_values)) {
            $prepared_select = $wpdb->prepare($sql_select, $where_values);
        } else {
            $prepared_select = $sql_select;
        }
        
        // Debug: mostrar a query final
        error_log("SM Student Control: Final SQL query: " . $prepared_select);
        
        $results = $wpdb->get_results($prepared_select, ARRAY_A);
        
        error_log("SM Student Control: Found " . count($results) . " results from cache");
        
        // Debug: verificar alguns dados de último acesso
        if (!empty($results)) {
            $sample_size = min(3, count($results));
            for ($i = 0; $i < $sample_size; $i++) {
                $row = $results[$i];
                error_log("SM Student Control: Sample {$i} - User ID: {$row['user_id']}, Last Access: {$row['last_access_timestamp']} (" . 
                    ($row['last_access_timestamp'] ? date('Y-m-d H:i:s', $row['last_access_timestamp']) : 'Never') . ")");
            }
        }
        
        // Processar os resultados para o formato esperado
        $students = array();
        if (!empty($results)) {
            foreach ($results as $row) {
                $courses = json_decode($row['courses_data'], true) ?: array();
                $enrolled_courses = count($courses);
                
                $students[] = array(
                    'id' => $row['user_id'],
                    'full_name' => $row['full_name'],
                    'email' => $row['email'],
                    'registration_date' => $row['registration_date'],
                    'last_access' => $row['last_access_timestamp'],
                    'enrolled_courses' => $enrolled_courses,
                    'courses' => $courses
                );
            }
        }
        
        // Retornar resultados e contagem total
        return array(
            'items' => $students,
            'total' => (int)$total
        );
    }

    /**
     * Handler do cron para atualização automática do cache
     */
    public static function daily_cache_handler() {        
        // Processo em lotes para evitar timeouts em grandes instalações
        $all_students = SM_Student_Control_Data::get_all_student_ids();
        $batch_size = 50;
        $total_students = count($all_students);
        $updated = 0;
        
        for ($offset = 0; $offset < $total_students; $offset += $batch_size) {
            $batch = array_slice($all_students, $offset, $batch_size);
            $updated += self::update_cache($batch);
            
            // Pequena pausa para não sobrecarregar o servidor
            if ($offset + $batch_size < $total_students) {
                sleep(2);
            }
        }
    }

    /**
     * Handler AJAX para atualizar o cache de múltiplos alunos
     */
    public static function refresh_cache_handler() {
        // Verificar permissões
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
        }
        
        // Verificar nonce
        check_ajax_referer('sm_student_control_nonce', 'nonce');
        
        // Obter parâmetros
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = 10;
        $student_search = isset($_POST['student_search']) ? sanitize_text_field($_POST['student_search']) : '';
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $last_access_month = isset($_POST['last_access_month']) ? sanitize_text_field($_POST['last_access_month']) : '';
        
        // Se tivermos filtros, atualizamos apenas os alunos filtrados
        if (!empty($student_search) || !empty($course_id) || !empty($last_access_month)) {
            $students = SM_Student_Control_Data::get_all_students($student_search, $course_id, $last_access_month);  // MODIFICADO AQUI
            $user_ids = array_column($students, 'id');
        } else {
            // Caso contrário, atualizamos todos
            $user_ids = SM_Student_Control_Data::get_all_student_ids();
        }
        
        $total = count($user_ids);
        
        // Obter o lote atual
        $batch = array_slice($user_ids, $offset, $batch_size);
        
        if (empty($batch)) {
            wp_send_json_success([
                'done' => true,
                'message' => 'Atualização completa: ' . $total . ' alunos processados.'
            ]);
        }
        
        // Atualizar este lote
        $updated = self::update_cache($batch);
        
        // Responder com progresso
        wp_send_json_success([
            'done' => false,
            'offset' => $offset + $batch_size,
            'total' => $total,
            'processed' => min($offset + $batch_size, $total),
            'updated' => $updated,
            'message' => 'Processando alunos... ' . min($offset + $batch_size, $total) . ' de ' . $total
        ]);
    }

    /**
     * Handler para atualização de cache de um único aluno via AJAX
     */
    public static function update_single_student_handler() {        
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sm_student_control_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            exit;
        }
        
        // Verificar user_id
        if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
            wp_send_json_error(array('message' => 'Invalid user ID'));
            exit;
        }
        
        $user_id = intval($_POST['user_id']);
        
        // Aumentar limites para esta operação
        ini_set('memory_limit', '256M');
        
        // Enviar header para evitar timeout no navegador
        header('Content-Type: application/json');
        ob_flush();
        flush();
        
        // Limpar cache existente da tabela
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_student_control_cache';
        $wpdb->delete($table_name, ['user_id' => $user_id]);
        
        // Executar atualização com verificação de timeout
        $start_time = time();
        $result = self::update_cache($user_id);
        $execution_time = time() - $start_time;
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Cache updated successfully',
                'execution_time' => $execution_time
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to update cache',
                'execution_time' => $execution_time
            ));
        }
        
        exit;
    }

    /**
     * Limpa o cache e reconstrui com valores corretos com novo fuso horário
     */
    public static function rebuild_cache() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_student_control_cache';
        
        // Limpar a tabela completamente
        $wpdb->query("TRUNCATE TABLE $table_name");
        
        // Obter todos os IDs de alunos
        $student_ids = SM_Student_Control_Data::get_all_student_ids();
        
        // Reconstruir o cache em lotes
        $batch_size = 50;
        $total = count($student_ids);
        
        for ($offset = 0; $offset < $total; $offset += $batch_size) {
            $batch = array_slice($student_ids, $offset, $batch_size);
            self::update_cache($batch);
            
            // Pequena pausa para não sobrecarregar
            if ($offset + $batch_size < $total) {
                sleep(1);
            }
        }
        
        return $total;
    }

    /**
     * Atualiza a estrutura da tabela de cache para incluir novas colunas
     */
    public static function update_cache_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_student_control_cache';
        
        // Verificar se a tabela existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            self::create_cache_table(); // Chamar função para criar a tabela completa
            return true;
        }
        
        // Verificar e adicionar as colunas que podem estar faltando
        $columns_to_check = array(
            'course_history_data' => "ADD COLUMN course_history_data longtext DEFAULT NULL AFTER courses_data",
            'all_lessons_count' => "ADD COLUMN all_lessons_count int(11) DEFAULT 0 AFTER lessons_data",
            'all_quizzes_count' => "ADD COLUMN all_quizzes_count int(11) DEFAULT 0 AFTER all_lessons_count",
            'username' => "ADD COLUMN username varchar(255) DEFAULT '' AFTER email",
            'registration_date' => "ADD COLUMN registration_date varchar(255) DEFAULT '' AFTER username",
            'last_access_timestamp' => "ADD COLUMN last_access_timestamp bigint(20) DEFAULT 0 AFTER registration_date"
        );
        
        foreach ($columns_to_check as $column => $add_statement) {
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE '$column'");
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE $table_name $add_statement");
            }
        }
        
        // Remover campos obsoletos se eles existirem
        $columns_to_remove = array('total_lesson_time', 'average_lesson_time');
        
        foreach ($columns_to_remove as $column) {
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE '$column'");
            if (!empty($column_exists)) {
                $wpdb->query("ALTER TABLE $table_name DROP COLUMN $column");
            }
        }
        
        return true;
    }
}

// Registrar handlers AJAX
add_action('wp_ajax_refresh_student_cache', ['SM_Student_Control_Cache', 'refresh_cache_handler']);
add_action('wp_ajax_update_single_student', ['SM_Student_Control_Cache', 'update_single_student_handler']);

// Registrar handler de cron
add_action('sm_student_control_daily_cache_update', ['SM_Student_Control_Cache', 'daily_cache_handler']);