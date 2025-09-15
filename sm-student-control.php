<?php
/**
 * Plugin Name: SM Student Control
 * Plugin URI: https://epiccentro.com.br
 * Description: Um plugin para controle e monitoramento de alunos em cursos do MasterStudy LMS
 * Version: 1.0.0
 * Author: Epic Centro
 * Author URI: https://epiccentro.com.br
 * Text Domain: sm-student-control
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'SM_STUDENT_CONTROL_VERSION', '1.0.0' );
define( 'SM_STUDENT_CONTROL_DIR', plugin_dir_path( __FILE__ ) );
define( 'SM_STUDENT_CONTROL_URL', plugin_dir_url( __FILE__ ) );

// Carregar classe de cache diretamente para evitar erro de inicialização
require_once SM_STUDENT_CONTROL_DIR . 'includes/class-sm-student-control-cache.php';

// Require other dependencies
require_once SM_STUDENT_CONTROL_DIR . 'includes/class-sm-student-control-loader.php';

// ADICIONAR: Inicializar o cache
add_action('plugins_loaded', 'sm_student_control_init_cache');

/**
 * Inicializa a tabela de cache após todos os plugins serem carregados
 */
function sm_student_control_init_cache() {
    SM_Student_Control_Cache::init();
}

// Register activation hook
register_activation_hook(__FILE__, 'sm_student_control_activate');

/**
 * Actions to perform on plugin activation
 */
function sm_student_control_activate() {
    // Criar tabela de cache
    require_once SM_STUDENT_CONTROL_DIR . 'includes/class-sm-student-control-cache.php';
    SM_Student_Control_Cache::create_cache_table();
    
    // Programar o cron initial
    if ( ! wp_next_scheduled( 'sm_student_control_daily_cache_update' ) ) {
        $midnight = strtotime( 'today midnight' ) + 86400;
        wp_schedule_event( $midnight, 'daily', 'sm_student_control_daily_cache_update' );
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Register deactivation hook
register_deactivation_hook(__FILE__, 'sm_student_control_deactivate');

/**
 * Actions to perform on plugin deactivation
 */
function sm_student_control_deactivate() {
    $ts = wp_next_scheduled('sm_student_control_daily_cache_update');
    if ($ts) {
        wp_unschedule_event($ts, 'sm_student_control_daily_cache_update');
    }
    flush_rewrite_rules();
}

// Initialize plugin
$sm_student_control = new SM_Student_Control_Loader();

/**
 * Adiciona botão de atualização forçada na lista de alunos
 */
function sm_student_control_add_refresh_button() {
    // Verificar permissões
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Gerar nonce para segurança
    $nonce = wp_create_nonce('sm_student_control_nonce');
    
    ?>
    <div class="sm-student-control-refresh-wrapper" style="margin-top:20px;">
        <h3><?php _e('Atualização de Dados', 'sm-student-control'); ?></h3>
        <p><?php _e('Atualize o cache de dados dos alunos para exibir as informações mais recentes.', 'sm-student-control'); ?></p>
        
        <button id="refresh-student-cache" 
                class="button button-primary" 
                data-nonce="<?php echo esc_attr($nonce); ?>">
            <span class="dashicons dashicons-update"></span>
            <?php _e('Atualizar Dados dos Alunos', 'sm-student-control'); ?>
        </button>
        <span id="refresh-status"></span>
    </div>
    <?php
}

// Registrar a função para o hook
add_action('sm_student_control_after_students_list', 'sm_student_control_add_refresh_button');
