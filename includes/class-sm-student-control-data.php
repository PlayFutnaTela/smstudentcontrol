<?php
/**
 * Data handling class for SM Student Control.
 * 
 * This class retrieves and processes data from Masterstudy LMS.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class SM_Student_Control_Data {
    
    /**
     * Get all students (users enrolled in at least one course or with subscriber role)
     */
    public static function get_all_students($search = '', $course_id = '', $last_access_month = '') {
        global $wpdb;
        
        // First approach - Get all user IDs that are either:
        // 1. Enrolled in a course OR 
        // 2. Have subscriber role
        $query = "
            SELECT DISTINCT u.ID AS user_id
            FROM {$wpdb->users} u
            
            -- Join for first and last name (used in search)
            LEFT JOIN {$wpdb->usermeta} um_fn ON um_fn.user_id = u.ID AND um_fn.meta_key = 'first_name'
            LEFT JOIN {$wpdb->usermeta} um_ln ON um_ln.user_id = u.ID AND um_ln.meta_key = 'last_name'
            
            -- Left join for capabilities
            LEFT JOIN {$wpdb->usermeta} um_cap ON um_cap.user_id = u.ID 
                                             AND um_cap.meta_key = '{$wpdb->prefix}capabilities'
            
            -- Optional left join to user_courses for course enrollment
            LEFT JOIN {$wpdb->prefix}stm_lms_user_courses uc ON uc.user_id = u.ID
            
            WHERE 1=1
        ";
        
        // Add filters
        $where_clauses = [];
        $query_args = [];
        
        // Core condition: either enrolled in ANY course OR has subscriber role
        // Use approach that works with serialized PHP data in capabilities field
        $where_clauses[] = "(
            uc.user_id IS NOT NULL 
            OR um_cap.meta_value LIKE %s 
            OR um_cap.meta_value LIKE %s
        )";
        $query_args[] = '%' . $wpdb->esc_like('s:10:"subscriber"') . '%';
        $query_args[] = '%' . $wpdb->esc_like('s:9:"assinante"') . '%'; 
        
        // Exclude admins and editors
        $where_clauses[] = "(
            um_cap.meta_value NOT LIKE %s
            AND um_cap.meta_value NOT LIKE %s
        )";
        $query_args[] = '%' . $wpdb->esc_like('s:13:"administrator"') . '%';
        $query_args[] = '%' . $wpdb->esc_like('s:6:"editor"') . '%';
        
        // Filter by name or email
        if (!empty($search)) {
            $where_clauses[] = "(
                u.display_name LIKE %s 
                OR u.user_email LIKE %s 
                OR CONCAT(um_fn.meta_value, ' ', um_ln.meta_value) LIKE %s
            )";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $query_args[] = $search_term;
            $query_args[] = $search_term;
            $query_args[] = $search_term;
        }
        
        // Filter by course (only applies to users enrolled in that specific course)
        if (!empty($course_id)) {
            $where_clauses[] = "uc.course_id = %d";
            $query_args[] = $course_id;
        }
        
        // Filter by last access month
        if (!empty($last_access_month)) {
            $year_month = explode('-', $last_access_month);
            if (count($year_month) == 2) {
                $year = $year_month[0];
                $month = $year_month[1];
                
                $start_date = mktime(0, 0, 0, $month, 1, $year);
                $end_date = mktime(23, 59, 59, $month + 1, 0, $year);
                
                // Formatos de data para comparação com strings
                $start_date_mysql = date('Y-m-d H:i:s', $start_date);
                $end_date_mysql = date('Y-m-d H:i:s', $end_date);
                
                // Primeiro, forme subqueries separadas para cada tipo de atividade
                $lessons_subquery = $wpdb->prepare(
                    "SELECT 1 FROM {$wpdb->prefix}stm_lms_user_lessons WHERE user_id = u.ID AND end_time BETWEEN %d AND %d",
                    $start_date, $end_date
                );
                
                $quizzes_subquery = $wpdb->prepare(
                    "SELECT 1 FROM {$wpdb->prefix}stm_lms_user_quizzes WHERE user_id = u.ID AND `timestamp` BETWEEN %d AND %d", 
                    $start_date, $end_date
                );
                
                $last_login_subquery = $wpdb->prepare(
                    "SELECT 1 FROM {$wpdb->usermeta} 
                     WHERE user_id = u.ID AND meta_key = 'last_login' AND (
                         (meta_value REGEXP '^[0-9]+$' AND meta_value BETWEEN %d AND %d) OR
                         (meta_value BETWEEN %s AND %s)
                     )",
                    $start_date, $end_date, $start_date_mysql, $end_date_mysql
                );
                
                // Agora combine as subqueries com EXISTS
                $where_clauses[] = "(" . 
                    "EXISTS (" . $lessons_subquery . ") OR " .
                    "EXISTS (" . $quizzes_subquery . ") OR " .
                    "(uc.time_updated BETWEEN " . $start_date . " AND " . $end_date . ") OR " .
                    "EXISTS (" . $last_login_subquery . ")" .
                ")";
            }
        }
        
        // Add WHERE clauses if any
        if (!empty($where_clauses)) {
            $query .= " AND " . implode(' AND ', $where_clauses);
        }
        
        // Add order by to ensure consistent results
        $query .= " ORDER BY u.ID ASC";
        
        // Get distinct user IDs with prepared statement
        $prepared_query = $wpdb->prepare($query, $query_args);
        $user_ids = $wpdb->get_col($prepared_query);
        
        // Get full user data
        $students = [];
        if (!empty($user_ids)) {
            foreach ($user_ids as $user_id) {
                $student_data = self::get_student_data($user_id);
                if ($student_data) {
                    $students[] = $student_data;
                }
            }
        }
        
        return $students;
    }

    /**
     * Get detailed student data
     */
    public static function get_student_data($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return null;
        }
        
        $first_name = get_user_meta($user_id, 'first_name', true);
        $last_name = get_user_meta($user_id, 'last_name', true);
        
        // Limpar possíveis caracteres invisíveis ou problemas de encoding
        $first_name = trim(preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $first_name));
        $last_name = trim(preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $last_name));
        
        // Modificação: Verificar se o nome e sobrenome existem antes de concatenar
        if (!empty($first_name) && !empty($last_name)) {
            // Concatenar manualmente, garantindo um espaço entre os nomes
            $full_name = $first_name;
            
            // Se o sobrenome já começa com hífen, não adicionar espaço adicional
            if (substr($last_name, 0, 1) === '-') {
                $full_name .= ' ' . $last_name; // Já tem um espaço antes do hífen
            } else {
                $full_name .= ' ' . $last_name;
            }
        } else if (!empty($first_name)) {
            $full_name = $first_name;
        } else if (!empty($last_name)) {
            $full_name = $last_name;
        } else {
            $full_name = $user->display_name;
        }
        
        // Backup: Se ocorrer algum problema e o full_name ainda estiver incorreto
        if ($last_name === "- O Sábio" && $full_name === $last_name) {
            $full_name = "Edegus - O Sábio"; // Correção manual para este caso específico
        }
        
        // ANTES DO RETURN FINAL (linha ~198)
        $last_access = self::get_student_last_access($user_id);
        
        // Após formatar a data (se aplicável)
        $formatted_last_access = self::format_date_safely($last_access);
        
        return [
            'id' => $user_id,
            'email' => $user->user_email,
            'username' => $user->user_login,
            'full_name' => $full_name,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'registration_date' => $user->user_registered,
            'last_access' => $last_access,
            'last_access_formatted' => $formatted_last_access,
        ];
    }
    
    /**
     * Get student's last access timestamp from multiple sources
     * 
     * @param int $user_id Student ID
     * @return int|null Timestamp of last access or null if not found
     */
    public static function get_student_last_access($user_id) {
        global $wpdb;
        
        // Inicializar como null para comparações corretas
        $last_access = null;
        $timestamps = [];
        
        try {
            // 1. Verificar em quizzes
            $last_quiz = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(end_time) FROM {$wpdb->prefix}stm_lms_user_quizzes WHERE user_id = %d",
                $user_id
            ));
            
            if (!empty($last_quiz)) {
                $timestamps['quiz'] = intval($last_quiz);
            }
            
            // 2. Verificar em lições
            $last_lesson = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(end_time) FROM {$wpdb->prefix}stm_lms_user_lessons WHERE user_id = %d",
                $user_id
            ));
            
            if (!empty($last_lesson)) {
                $timestamps['lesson'] = intval($last_lesson);
            }
            
            // 3. Metadados do usuário
            $last_login = get_user_meta($user_id, 'last_login', true);
            if (!empty($last_login)) {
                $timestamps['login'] = intval($last_login);
            }
            
            // Encontrar o timestamp mais recente
            if (!empty($timestamps)) {
                // A função max() encontrará o maior valor no array
                $last_access = max($timestamps);
            }
        } catch (Exception $e) {
            error_log('Error getting student last access: ' . $e->getMessage());
        }
        
        return $last_access;
    }
    
    /**
     * Get student's enrolled courses
     */
    public static function get_student_courses($user_id) {
        global $wpdb;
        
        $courses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}stm_lms_user_courses WHERE user_id = %d",
                $user_id
            ),
            ARRAY_A
        );
        
        if (empty($courses)) {
            return array();
        }
        
        $result = array();
        
        foreach ($courses as $course) {
            $course_id = $course['course_id'];
            
            // MODIFICAÇÃO CRUCIAL: Processar o timestamp de forma consistente
            $start_time = isset($course['start_time']) ? intval($course['start_time']) : 0;
            
            $formatted_course = array(
                'course_id' => $course_id,
                'course_name' => get_the_title($course_id),
                'progress' => $course['progress_percent'],
                'progress_percent' => $course['progress_percent'],
                'status' => $course['status'],
                'status_label' => self::get_course_status_label($course['status']),
                'url' => get_permalink($course_id),
                'start_time' => $start_time,
                // ADICIONADO: Incluir campo formatado pronto para uso
                'enrollment_date' => self::format_date_safely($start_time)
            );
            
            $result[] = $formatted_course;
        }
        
        return $result;
    }
    
    /**
     * Get student's recent quizzes
     */
    public static function get_student_recent_quizzes($user_id, $limit = 10) {
        global $wpdb;
        
        // Obter dados dos quizzes
        $query = $wpdb->prepare(
            "SELECT 
                uq.quiz_id as id,
                uq.course_id,
                q.post_title as title,
                c.post_title as course_title,
                uq.progress,
                uq.status,
                uq.created_at,
                uq.user_quiz_id
             FROM {$wpdb->prefix}stm_lms_user_quizzes uq
             LEFT JOIN {$wpdb->posts} q ON q.ID = uq.quiz_id
             LEFT JOIN {$wpdb->posts} c ON c.ID = uq.course_id
             WHERE uq.user_id = %d
             ORDER BY uq.user_quiz_id DESC
             LIMIT %d",
            $user_id,
            $limit
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Nenhum quiz encontrado
        if (empty($results)) {
            return array();
        }
        
        // IMPORTANTE: Processar resultados para padronizar timestamps
        foreach ($results as &$quiz) {
            // Converter created_at para timestamp padronizado
            if (isset($quiz['created_at'])) {
                // Verificar se a data já é um timestamp
                $quiz['completion_timestamp'] = self::to_timestamp($quiz['created_at']);
                
                // Pré-formatar a data aqui - ÚNICA VEZ
                $quiz['completion_date'] = date_i18n(
                    get_option('date_format') . ' ' . get_option('time_format'), 
                    $quiz['completion_timestamp'],
                    false // Crucial para UTC-3
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Get student's recent lessons - versão otimizada
     */
    public static function get_student_recent_lessons($user_id, $limit = 10) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'stm_lms_user_lessons';
        
        // Obter dados do banco
        $lessons = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ul.lesson_id, ul.course_id, ul.end_time, 
                        p.post_title as lesson_title, c.post_title as course_title
                FROM {$table} ul
                JOIN {$wpdb->posts} p ON p.ID = ul.lesson_id
                JOIN {$wpdb->posts} c ON c.ID = ul.course_id
                WHERE ul.user_id = %d
                ORDER BY ul.end_time DESC
                LIMIT %d",
                $user_id,
                $limit
            ),
            ARRAY_A
        );
        
        foreach ($lessons as &$lesson) {
            // Adicionar URLs se disponível
            if (class_exists('STM_LMS_Lesson') && method_exists('STM_LMS_Lesson', 'get_lesson_url')) {
                $lesson['lesson_url'] = STM_LMS_Lesson::get_lesson_url($lesson['course_id'], $lesson['lesson_id']);
                $lesson['course_url'] = get_permalink($lesson['course_id']);
            } else {
                $lesson['lesson_url'] = get_permalink($lesson['lesson_id']);
                $lesson['course_url'] = get_permalink($lesson['course_id']);
            }
            
            // Processar a data de conclusão
            if (!empty($lesson['end_time'])) {
                // Garantir que é um inteiro
                $lesson['end_time'] = intval($lesson['end_time']);
                
                // NOVA IMPLEMENTAÇÃO: Usar DateTime para garantir o timezone correto
                try {
                    // Criar DateTime a partir do timestamp UNIX (sempre em UTC)
                    $dt = new DateTime("@{$lesson['end_time']}");
                    
                    // Definir explicitamente o timezone para o do WordPress
                    $dt->setTimezone(new DateTimeZone(wp_timezone_string()));
                    
                    // Formatar usando o padrão do WordPress
                    $date_format = get_option('date_format') . ' ' . get_option('time_format');
                    $lesson['completion_date'] = $dt->format($date_format);
                } catch (Exception $e) {
                    // Fallback em caso de erro
                    $lesson['completion_date'] = date_i18n(
                        get_option('date_format') . ' ' . get_option('time_format'), 
                        $lesson['end_time'],
                        false
                    );
                }
            }
        }
        
        return $lessons;
    }
    
    /**
     * Recupera todas as lições concluídas por um aluno sem limite de registros
     * 
     * @param int $user_id ID do aluno
     * @return array Lista completa de lições
     */
    public static function get_all_student_lessons($user_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'stm_lms_user_lessons';
        
        // Similar ao get_student_recent_lessons, mas SEM LIMIT
        $lessons = $wpdb->get_results(
            $wpdb->prepare(
                // CORRIGIDO: Remover seleção do campo start_time que não é mais usado
                "SELECT ul.lesson_id, ul.course_id, ul.end_time, 
                        p.post_title as lesson_title, c.post_title as course_title
                FROM {$table} ul
                JOIN {$wpdb->posts} p ON p.ID = ul.lesson_id
                JOIN {$wpdb->posts} c ON c.ID = ul.course_id
                WHERE ul.user_id = %d
                ORDER BY ul.end_time DESC",
                $user_id
            ),
            ARRAY_A
        );
        
        if (!$lessons) {
            return array();
        }
        
        // CORRIGIDO: Remover referência a start_time do processamento
        $processed_lessons = array();
        foreach ($lessons as $lesson) {
            $processed_lessons[] = array(
                'lesson_id' => $lesson['lesson_id'],
                'course_id' => $lesson['course_id'],
                'end_time' => $lesson['end_time']
            );
        }
        
        return $processed_lessons;
    }
    
    /**
     * Obtém apenas os IDs de todos os alunos (para uso nos processos de atualização)
     * @return array IDs dos alunos
     */
    public static function get_all_student_ids() {
        global $wpdb;
        
        // Query simplificada apenas para obter IDs
        $query = "
            SELECT DISTINCT u.ID
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um_cap 
              ON um_cap.user_id = u.ID AND um_cap.meta_key = %s
            LEFT JOIN {$wpdb->prefix}stm_lms_user_courses uc 
              ON uc.user_id = u.ID
            WHERE (
              uc.user_id IS NOT NULL
              OR um_cap.meta_value LIKE %s
              OR um_cap.meta_value LIKE %s
            )
            AND (
              um_cap.meta_value NOT LIKE %s
              AND um_cap.meta_value NOT LIKE %s
            )
        ";
$cap_key = $wpdb->prefix . 'capabilities';
$student_like = '%' . $wpdb->esc_like('s:10:"subscriber"') . '%';
$assinante_like = '%' . $wpdb->esc_like('s:9:"assinante"') . '%';
$admin_like = '%' . $wpdb->esc_like('s:13:"administrator"') . '%';
$editor_like = '%' . $wpdb->esc_like('s:6:"editor"') . '%';
return $wpdb->get_col(
    $wpdb->prepare(
      $query,
        $cap_key,
        $student_like,
        $assinante_like,
        $admin_like,
        $editor_like
    )
);
    }
    
    /**
     * Obter todos os cursos disponíveis no sistema
     * @return array Lista de cursos com ID e título
     */
    public static function get_all_courses() {
        global $wpdb;
        
        // Obter todos os cursos publicados (post type 'stm-courses')
        $courses = $wpdb->get_results(
            "SELECT ID, post_title 
             FROM {$wpdb->posts} 
             WHERE post_type = 'stm-courses' 
             AND post_status = 'publish'
             ORDER BY post_title ASC"
        );
        
        if (!$courses) {
            return array();
        }
        
        // Formatar para array simples
        $formatted_courses = array();
        foreach ($courses as $course) {
            $formatted_courses[] = array(
                'id' => $course->ID,
                'title' => $course->post_title
            );
        }
        
        return $formatted_courses;
    }
    
    /**
     * Converte código de status para label legível
     * 
     * @param string $status Código de status do curso
     * @return string Label formatado para exibição
     */
    public static function get_course_status_label($status) {
        switch ($status) {
            case 'completed':
                return __('Completed', 'sm-student-control');
            case 'in_progress':
                return __('In Progress', 'sm-student-control');
            case 'enrolled':
                return __('Enrolled', 'sm-student-control');
            case 'expired':
                return __('Expired', 'sm-student-control');
            default:
                return ucfirst(str_replace('_', ' ', $status));
        }
    }
    
    /**
     * Recupera o histórico completo de matrículas de um aluno, incluindo cursos não mais ativos
     * 
     * @param int $user_id ID do aluno
     * @return array Histórico completo de matrículas
     */
    public static function get_student_course_history($user_id) {
        global $wpdb;
        
        // 1. Obter IDs de cursos em que o aluno está atualmente matriculado
        $active_course_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT course_id FROM {$wpdb->prefix}stm_lms_user_courses 
                 WHERE user_id = %d AND status IN ('enrolled', 'in_progress', 'completed')",
                $user_id
            )
        );
        
        // 2. Obter IDs de todos os cursos em que o aluno já interagiu
        $query = $wpdb->prepare(
            "(SELECT DISTINCT course_id FROM {$wpdb->prefix}stm_lms_user_courses WHERE user_id = %d)
             UNION
             (SELECT DISTINCT course_id FROM {$wpdb->prefix}stm_lms_user_lessons WHERE user_id = %d)
             UNION
             (SELECT DISTINCT course_id FROM {$wpdb->prefix}stm_lms_user_quizzes WHERE user_id = %d)",
            $user_id, $user_id, $user_id
        );
        
        $all_course_ids = $wpdb->get_col($query);
        
        // 3. Filtrar para obter apenas cursos inativos/históricos
        $history_course_ids = array_diff($all_course_ids, $active_course_ids);
        
        if (empty($history_course_ids)) {
            return array();
        }
        
        // 4. Obter informações básicas dos cursos
        $formatted_history = array();
        foreach ($history_course_ids as $course_id) {
            // Dados básicos do curso
            $course_post = get_post($course_id);
            
            $course_name = $course_post ? $course_post->post_title : sprintf(__('Course #%d (Removed)', 'sm-student-control'), $course_id);
            $course_status = $course_post ? $course_post->post_status : 'deleted';
            
            // Obter dados de matrícula para este curso
            $enrollment = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT progress_percent, status, start_time 
                     FROM {$wpdb->prefix}stm_lms_user_courses 
                     WHERE user_id = %d AND course_id = %d",
                    $user_id, $course_id
                ),
                ARRAY_A
            );
            
            // Formatação do status
            $status_label = '';
            switch ($course_status) {
                case 'publish':
                    $status_label = __('Available (Unenrolled)', 'sm-student-control');
                    break;
                case 'trash':
                    $status_label = __('In Trash', 'sm-student-control');
                    break;
                case 'draft':
                    $status_label = __('Draft', 'sm-student-control');
                    break;
                case 'deleted':
                    $status_label = __('Removed', 'sm-student-control');
                    break;
                default:
                    $status_label = ucfirst($course_status);
            }
            
            // REMOVIDO: Cálculo do tempo total gasto nas lições
            
            // Formatar dados para retorno
            $formatted_course = array(
                'course_id' => $course_id,
                'course_name' => $course_name,
                'progress' => isset($enrollment['progress_percent']) ? $enrollment['progress_percent'] : 0,
                'progress_percent' => isset($enrollment['progress_percent']) ? $enrollment['progress_percent'] : 0,
                'status' => isset($enrollment['status']) ? $enrollment['status'] : 'unknown',
                'status_label' => $status_label,
                'course_status' => $course_status,
                'start_time' => isset($enrollment['start_time']) ? intval($enrollment['start_time']) : 0
                // Campo 'enrollment_date' removido, agora usamos somente start_time
            );
            
            $formatted_history[] = $formatted_course;
        }
        
        return $formatted_history;
    }

    /**
     * Recupera todos os quizzes completados por um aluno sem limite de registros
     * 
     * @param int $user_id ID do aluno
     * @return array Lista completa de quizzes
     */
    public static function get_all_student_quizzes($user_id) {
        global $wpdb;
        
        // Primeiro, verificar quais colunas existem na tabela
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}stm_lms_user_quizzes'");
    
        if (!$table_exists) {
            return array(); // Tabela não existe
        }
        
        // Obter todas as colunas da tabela
        $cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}stm_lms_user_quizzes");
        $has_timestamp = false;
        $timestamp_column = '';
        
        // Verificar se existe alguma coluna de timestamp
        foreach ($cols as $col) {
            if (in_array($col->Field, ['timestamp', 'time', 'complete_time', 'date', 'completed_at'])) {
                $has_timestamp = true;
                $timestamp_column = $col->Field;
                break;
            }
        }
        
        // Construir a consulta com base nas colunas disponíveis
        if ($has_timestamp) {
            $query = $wpdb->prepare(
                "SELECT 
                    uq.quiz_id,
                    uq.course_id,
                    uq.{$timestamp_column} as quiz_timestamp
                 FROM {$wpdb->prefix}stm_lms_user_quizzes uq
                 WHERE uq.user_id = %d
                 ORDER BY uq.user_quiz_id DESC",
                $user_id
            );
        } else {
            // Se não houver coluna de timestamp, selecionar apenas IDs
            $query = $wpdb->prepare(
                "SELECT 
                    uq.quiz_id,
                    uq.course_id
                 FROM {$wpdb->prefix}stm_lms_user_quizzes uq
                 WHERE uq.user_id = %d
                 ORDER BY uq.user_quiz_id DESC",
                $user_id
            );
        }
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if (empty($results)) {
            return array();
        }
        
        // Processamento mínimo para economizar recursos
        $quizzes = array();
        foreach ($results as $result) {
            $quiz = array(
                'quiz_id' => $result['quiz_id'],
                'course_id' => $result['course_id']
            );
            
            // Adicionar timestamp se disponível
            if ($has_timestamp && isset($result['quiz_timestamp'])) {
                $quiz['timestamp'] = $result['quiz_timestamp'];
            } else {
                $quiz['timestamp'] = 0; // Valor padrão
            }
            
            $quizzes[] = $quiz;
        }
        
        return $quizzes;
    }
    
    /**
     * Processa e formata datas com segurança, garantindo o fuso horário correto
     * 
     * @param mixed $date Data em qualquer formato (string, timestamp, null)
     * @param string $format Formato de saída (opcional)
     * @return string Data formatada ou valor padrão
     */
    public static function format_date_safely($date, $format = '') {
        // Se formato não for especificado, usar formato de data e hora do WordPress
        if (empty($format)) {
            $format = get_option('date_format') . ' ' . get_option('time_format');
        }

        // Se data vazia, retornar N/A
        if (empty($date)) {
            return __('N/A', 'sm-student-control');
        }

        // Processar diferentes tipos de entrada
        $timestamp = is_numeric($date) ? intval($date) : strtotime($date);

        if ($timestamp === false || $timestamp <= 0) {
            return __('Invalid date', 'sm-student-control');
        }

        // IMPORTANTE: Usar DateTime para converter corretamente o timezone UTC-0 para UTC-3
        try {
            // Criar objeto DateTime a partir do timestamp (UTC)
            $dt = new DateTime("@{$timestamp}");
            
            // Definir timezone do WordPress (UTC-3)
            $dt->setTimezone(new DateTimeZone(wp_timezone_string()));
            
            // Formatar usando padrão do WordPress
            return $dt->format($format);
        } catch (Exception $e) {
            // Fallback para o método tradicional (menos preciso com timezone)
            return date_i18n($format, $timestamp, false);
        }
    }
    
    /**
     * Garante que datas estejam em formato timestamp UNIX
     * Função universal para converter qualquer formato de data para timestamp
     * 
     * @param mixed $date_value Qualquer formato de data (string, timestamp, etc)
     * @return int Timestamp UNIX
     */
    public static function to_timestamp($date_value) {
        // Se já for timestamp ou número
        if (is_numeric($date_value)) {
            return intval($date_value);
        }
        
        // Se for string no formato MySQL (como o created_at dos quizzes)
        if (is_string($date_value) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date_value)) {
            // Ajuste crítico: ao converter strings MySQL para timestamp, considerar o fuso horário do WP
            $timestamp = strtotime($date_value);
            return $timestamp;
        }
        
        // Outros formatos de string
        if (is_string($date_value) && !empty($date_value)) {
            $timestamp = strtotime($date_value);
            if ($timestamp !== false) {
                return $timestamp;
            }
        }
        
        // Valor inválido
        return 0;
    }
}