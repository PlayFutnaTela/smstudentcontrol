<?php
/**
 * Template para o formulário de filtro de alunos
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Garantir que as variáveis existam ou definir valores padrão
$student_search = isset($student_search) ? $student_search : '';
$course_id = isset($course_id) ? $course_id : '';
$last_access_month = isset($last_access_month) ? $last_access_month : '';
?>

<div class="wrap sm-student-control-filter">
    <div class="filter-container">
        <h3><?php _e('Filtrar Alunos', 'sm-student-control'); ?></h3>
        
        <form method="get" action="">
            <input type="hidden" name="page" value="sm-student-control" />
            
            <div class="filter-row">
                <div class="filter-field">
                    <label for="student-search"><?php _e('Buscar por nome ou email:', 'sm-student-control'); ?></label>
                    <input type="text" id="student-search" name="student_search" value="<?php echo esc_attr($student_search); ?>" placeholder="<?php _e('Nome ou email', 'sm-student-control'); ?>" />
                </div>
                
                <div class="filter-field">
                    <label for="course-filter"><?php _e('Curso:', 'sm-student-control'); ?></label>
                    <select id="course-filter" name="course_id">
                        <option value=""><?php _e('Todos os cursos', 'sm-student-control'); ?></option>
                    </select>
                </div>
                
                <div class="filter-field">
                    <label for="last-access-filter"><?php _e('Último acesso (mês):', 'sm-student-control'); ?></label>
                    <input type="month" id="last-access-filter" name="last_access_month" value="<?php echo esc_attr($last_access_month); ?>" />
                </div>
                
                <div class="filter-submit">
                    <button type="submit" class="button button-primary"><?php _e('Filtrar', 'sm-student-control'); ?></button>
                    <a href="?page=sm-student-control" class="button"><?php _e('Limpar Filtros', 'sm-student-control'); ?></a>
                </div>
            </div>
        </form>
    </div>
</div>