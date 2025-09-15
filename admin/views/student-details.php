<?php
/**
 * Template para os detalhes do aluno
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Importante: NÃO inserir HTML antes desta div de wrap
// Isso permite que notificações do WordPress apareçam corretamente
?>
<div class="wrap sm-student-control-details">
    <h2><?php echo esc_html($student['full_name']); ?> (ID: <?php echo esc_html($student['id']); ?>)</h2>

    <!-- NOVO: Link de navegação para retornar à lista de alunos -->
    <div class="navigation-links">
        <a href="<?php echo esc_url(admin_url('admin.php?page=sm-student-control')); ?>" class="button">
            <span class="dashicons dashicons-arrow-left-alt"></span> 
            <?php _e('Voltar para Lista de Alunos', 'sm-student-control'); ?>
        </a>
    </div>
    
    <div class="student-profile">
        <div class="student-info">
            <div class="info-item">
                <strong><?php _e('Email:', 'sm-student-control'); ?></strong>
                <?php echo esc_html($student['email']); ?>
            </div>
            
            <div class="info-item">
                <strong><?php _e('Nome de usuário:', 'sm-student-control'); ?></strong> <!-- Username → Nome de usuário -->
                <?php echo esc_html($student['username']); ?>
            </div>
            
            <div class="info-item">
                <strong><?php _e('Data de registro:', 'sm-student-control'); ?></strong> <!-- Registration Date → Data de registro -->
                <?php 
                echo SM_Student_Control_Data::format_date_safely($student['registration_date']);
                ?>
            </div>
            
            <!-- No bloco de detalhes de último acesso -->
            <div class="info-item">
                <strong><?php _e('Último acesso:', 'sm-student-control'); ?></strong>
                <?php 
                // Exibição normal
                if (!empty($student['last_access'])) {
                    echo SM_Student_Control_Data::format_date_safely($student['last_access']);
                } else {
                    _e('Nunca', 'sm-student-control');
                }
                ?>
            </div>
        </div>
        
        <!-- Seção de ações do aluno -->
        <div class="student-actions">
            <button class="button update-cache-button" data-user-id="<?php echo esc_attr($student['id']); ?>">
                <?php _e('Atualizar Cache', 'sm-student-control'); ?> <!-- Update Cache → Atualizar Cache -->
            </button>
            
            <!-- Adicione esta linha para o link de edição de usuário -->
            <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $student['id'])); ?>" target="_blank" class="button">
                <span class="dashicons dashicons-admin-users"></span>
                <?php _e('Editar Usuário no WordPress', 'sm-student-control'); ?> <!-- Edit User in WordPress → Editar Usuário no WordPress -->
            </a>
        </div>
    </div>
    
    <!-- Na seção de cursos matriculados -->
    <div class="student-courses section">
        <h3><?php _e('Cursos Matriculados', 'sm-student-control'); ?></h3>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('ID do Curso', 'sm-student-control'); ?></th>
                    <th><?php _e('Nome do Curso', 'sm-student-control'); ?></th>
                    <th><?php _e('Progresso', 'sm-student-control'); ?></th>
                    <th><?php _e('Status', 'sm-student-control'); ?></th>
                    <th><?php _e('Data de matrícula', 'sm-student-control'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($student['courses'])) : ?>
                    <tr>
                        <td colspan="6"><?php _e('Nenhum curso encontrado.', 'sm-student-control'); ?></td> <!-- No courses found → Nenhum curso encontrado -->
                    </tr>
                <?php else : ?>
                    <?php foreach ($student['courses'] as $course) : ?>
                        <tr>
                            <td><?php echo esc_html($course['course_id']); ?></td>
                            <td>
                                <!-- Correção em Course URL -->
                                <?php if (!empty($course['url']) && !empty($course['course_name'])): ?>
                                    <a href="<?php echo esc_url($course['url']); ?>" target="_blank">
                                        <?php echo esc_html($course['course_name']); ?>
                                    </a>
                                <?php elseif (!empty($course['course_name'])): ?>
                                    <?php echo esc_html($course['course_name']); ?>
                                <?php else: ?>
                                    <?php _e('Unknown course', 'sm-student-control'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress" style="width: <?php echo esc_attr(isset($course['progress_percent']) ? $course['progress_percent'] : 0); ?>%;"></div>
                                </div>
                                <?php echo esc_html(isset($course['progress_percent']) ? $course['progress_percent'] : 0); ?>%
                            </td>
                            <td><?php echo esc_html(isset($course['status_label']) ? $course['status_label'] : ''); ?></td>
                            <td>
                                <!-- Para os Cursos (Enrollment Date) -->
                                <?php 
                                if (!empty($course['enrollment_date'])) {
                                    // Usar o campo já formatado do cache
                                    echo $course['enrollment_date'];
                                } else {
                                    // Não tentar formatar start_time diretamente
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Seção de quizzes do aluno -->
    <div class="student-quizzes section">
        <h3><?php _e('Questionários Recentes', 'sm-student-control'); ?></h3>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Questionário', 'sm-student-control'); ?></th>
                    <th><?php _e('Curso', 'sm-student-control'); ?></th>
                    <th><?php _e('Nota', 'sm-student-control'); ?></th>
                    <th><?php _e('Status', 'sm-student-control'); ?></th>
                    <th><?php _e('Data de conclusão', 'sm-student-control'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($student['quizzes'])) : ?>
                    <tr>
                        <td colspan="5"><?php _e('Nenhum questionário encontrado.', 'sm-student-control'); ?></td> <!-- No quizzes found → Nenhum questionário encontrado -->
                    </tr>
                <?php else : ?>
                    <?php foreach ($student['quizzes'] as $quiz) : ?>
                        <tr>
                            <td><?php echo esc_html($quiz['title']); ?></td>
                            <td>
                                <!-- Correção em Quiz Course -->
                                <?php if (!empty($quiz['course_id']) && !empty($quiz['course_title'])): ?>
                                    <a href="<?php echo esc_url(get_permalink($quiz['course_id'])); ?>" target="_blank">
                                        <?php echo esc_html($quiz['course_title']); ?>
                                    </a>
                                <?php elseif (!empty($quiz['course_title'])): ?>
                                    <?php echo esc_html($quiz['course_title']); ?>
                                <?php else: ?>
                                    <?php _e('Unknown course', 'sm-student-control'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress" style="width: <?php echo esc_attr($quiz['progress']); ?>%;"></div>
                                </div>
                                <?php echo esc_html($quiz['progress']); ?>%
                            </td>
                            <td>
                                <span class="status <?php echo esc_attr($quiz['status']); ?>">
                                    <?php echo $quiz['status'] === 'passed' ? __('Passed', 'sm-student-control') : __('Failed', 'sm-student-control'); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if (!empty($quiz['completion_date'])) {
                                    // Usar campo já formatado
                                    echo $quiz['completion_date'];
                                } elseif (!empty($quiz['completion_timestamp'])) {
                                    // Nunca formatar diretamente - usar um campo específico do cache
                                    echo $quiz['completion_date'] ?? '-';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Na seção de lições recentes -->
    <div class="student-lessons section">
        <h3><?php _e('Lições Recentes', 'sm-student-control'); ?></h3>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Lição', 'sm-student-control'); ?></th>
                    <th><?php _e('Curso', 'sm-student-control'); ?></th>
                    <th><?php _e('Data de conclusão', 'sm-student-control'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($student['lessons'])) : ?>
                    <tr>
                        <td colspan="6"><?php _e('Nenhuma lição encontrada.', 'sm-student-control'); ?></td> <!-- No lessons found → Nenhuma lição encontrada -->
                    </tr>
                <?php else : ?>
                    <?php foreach ($student['lessons'] as $lesson) : ?>
                        <tr>
                            <td>
                                <?php if (!empty($lesson['lesson_url'])) : ?>
                                    <a href="<?php echo esc_url($lesson['lesson_url']); ?>" target="_blank">
                                        <?php echo esc_html($lesson['lesson_title']); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html($lesson['lesson_title']); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($lesson['course_url'])) : ?>
                                    <a href="<?php echo esc_url($lesson['course_url']); ?>" target="_blank">
                                        <?php echo esc_html($lesson['course_title']); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html($lesson['course_title']); ?>
                                <?php endif; ?>
                            </td>
                            <!-- Para as Lições (Completion Date) -->
                            <td>
                                <?php 
                                if (!empty($lesson['completion_date'])) {
                                    echo $lesson['completion_date'];
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>