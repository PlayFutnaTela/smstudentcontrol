<?php
/**
 * Template para a tabela de listagem de alunos
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Função auxiliar para criar links de ordenação nas colunas
 * 
 * @param string $column Coluna para ordenação
 * @param string $label Texto da coluna
 * @return string HTML do link com ícone de ordenação
 */
function sm_get_sortable_column($column, $label) {
    $current_orderby = isset($_GET['orderby']) ? $_GET['orderby'] : '';
    $current_order = isset($_GET['order']) ? $_GET['order'] : 'asc';
    
    $is_current = ($current_orderby === $column);
    $order_direction = ($is_current && $current_order === 'asc') ? 'desc' : 'asc';
    
    // Mantém os filtros atuais na URL
    $params = $_GET;
    $params['orderby'] = $column;
    $params['order'] = $order_direction;
    unset($params['paged']); // Reset de paginação ao ordenar
    
    $url = add_query_arg($params, admin_url('admin.php'));
    
    // Define a classe da seta dependendo da ordenação atual
    $arrow_class = '';
    if ($is_current) {
        $arrow_class = ($current_order === 'asc') ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2';
    }
    
    $output = '<a href="' . esc_url($url) . '" class="sortable-column">';
    $output .= '<span class="sort-label">' . $label . '</span>';
    
    // Adiciona o ícone de seta se esta for a coluna de ordenação atual
    if ($is_current) {
        $output .= ' <span class="dashicons ' . $arrow_class . '"></span>';
    }
    
    $output .= '</a>';
    return $output;
}
?>

<!-- Título principal -->
<h1><?php _e('Alunos', 'sm-student-control'); ?></h1> <!-- Students → Alunos -->

<!-- As notificações do WordPress serão injetadas automaticamente após o h1 -->

<div class="sm-student-filter-section">
    <!-- Seção de filtros -->
    <h3><?php _e('Filtrar Alunos', 'sm-student-control'); ?></h3> <!-- Filter Students → Filtrar Alunos -->
    
    <form method="get" class="student-filter-form">
        <input type="hidden" name="page" value="sm-student-control">
        
        <div class="sm-filter-row">      <!-- Prefixo "sm-" -->
            <div class="sm-filter-column"> <!-- Prefixo "sm-" -->
                <!-- Labels de filtro -->
                <label for="student_search"><?php _e('Buscar por nome ou email:', 'sm-student-control'); ?></label>
                <input type="search" id="student_search" name="student_search" 
                       value="<?php echo isset($_GET['student_search']) ? esc_attr($_GET['student_search']) : ''; ?>" 
                       placeholder="<?php _e('Nome ou endereço de email', 'sm-student-control'); ?>">
            </div>
            
            <div class="sm-filter-column">
                <label for="course_filter"><?php _e('Curso:', 'sm-student-control'); ?></label>
                <select id="course_filter" name="course_id">
                    <!-- Opção de todos os cursos -->
                    <option value=""><?php _e('Todos os cursos', 'sm-student-control'); ?></option> <!-- All courses → Todos os cursos -->
                    <?php if (isset($courses_list) && !empty($courses_list)): ?>
                        <?php foreach ($courses_list as $id => $title): ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected(isset($_GET['course_id']) && $_GET['course_id'] == $id); ?>>
                                <?php echo esc_html($title); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="sm-filter-column">
                <label for="last_access_month"><?php _e('Último acesso (mês):', 'sm-student-control'); ?></label>
                <input type="month" id="last_access_month" name="last_access_month" 
                       value="<?php echo isset($_GET['last_access_month']) ? esc_attr($_GET['last_access_month']) : ''; ?>">
            </div>
            
            <div class="sm-filter-column sm-filter-actions">
                <!-- Botões de filtro -->
                <input type="submit" class="button button-primary" value="<?php _e('Filtrar', 'sm-student-control'); ?>">
                <a href="?page=sm-student-control" class="button"><?php _e('Limpar Filtros', 'sm-student-control'); ?></a> <!-- Clear Filters → Limpar Filtros -->
                <button type="button" id="export-excel-btn" class="button button-secondary" style="margin-left: 10px;">
                    <span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>
                    <?php _e('Exportar Excel', 'sm-student-control'); ?>
                </button>
            </div>
        </div>
    </form>
</div>

<div class="sm-student-list-section">
    <!-- Cabeçalhos da tabela de alunos -->
    <h2><?php _e('Alunos', 'sm-student-control'); ?></h2>

    <?php if (empty($students)): ?>
        <div class="notice notice-warning inline">
            <p><?php _e('Nenhum aluno encontrado com os critérios informados.', 'sm-student-control'); ?></p>
        </div>
    <?php else: ?>
        <p class="students-count">
            <?php 
            printf(_n('%s aluno encontrado', '%s alunos encontrados', count($students), 'sm-student-control'), 
                number_format_i18n(count($students))
            ); 
            ?>
        </p>
        
        <table class="wp-list-table widefat fixed striped students-table">
            <thead>
                <tr>
                    <th scope="col"><?php _e('ID', 'sm-student-control'); ?></th>
                    <th scope="col" class="sortable-header <?php echo (isset($_GET['orderby']) && $_GET['orderby'] === 'name') ? 'sorted' : ''; ?>">
                        <?php echo sm_get_sortable_column('name', __('Nome', 'sm-student-control')); ?>
                    </th>
                    <th scope="col"><?php _e('Email', 'sm-student-control'); ?></th>
                    <th scope="col" class="sortable-header <?php echo (isset($_GET['orderby']) && $_GET['orderby'] === 'registration_date') ? 'sorted' : ''; ?>">
                        <?php echo sm_get_sortable_column('registration_date', __('Data de registro', 'sm-student-control')); ?>
                    </th>
                    <th scope="col" class="sortable-header <?php echo (isset($_GET['orderby']) && $_GET['orderby'] === 'last_access') ? 'sorted' : ''; ?>">
                        <?php echo sm_get_sortable_column('last_access', __('Último acesso', 'sm-student-control')); ?>
                    </th>
                    <th scope="col"><?php _e('Cursos matriculados', 'sm-student-control'); ?></th>
                    <th scope="col"><?php _e('Ações', 'sm-student-control'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo esc_html($student['id']); ?></td>
                        <td><?php echo esc_html($student['full_name']); ?></td>
                        <td><?php echo esc_html($student['email']); ?></td>
                        <td>
                            <?php 
                            if (is_array($student['registration_date'])) {
                                echo esc_html(isset($student['registration_date']['formatted']) ? 
                                    $student['registration_date']['formatted'] : '-');
                            } else {
                                echo esc_html($student['registration_date'] ?: '-');
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if (!empty($student['last_access'])) {
                                if (is_array($student['last_access'])) {
                                    // Se for um array com formatação já definida
                                    echo esc_html($student['last_access']['formatted'] ?? '-');
                                } else if (is_numeric($student['last_access'])) {
                                    // Se for um timestamp UNIX, formata para data legível
                                    echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $student['last_access']));
                                } else {
                                    // Caso seja uma string de data ou outro formato
                                    echo esc_html($student['last_access']);
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            $enrolled_courses = $student['enrolled_courses'];
                            echo is_array($enrolled_courses) ? count($enrolled_courses) : intval($enrolled_courses); 
                            ?>
                        </td>
                        <td>
                            <!-- Botão de detalhes -->
                            <a href="?page=sm-student-control&action=view&student_id=<?php echo esc_attr($student['id']); ?>" class="button button-small">
                                <?php _e('Ver Detalhes', 'sm-student-control'); ?> <!-- View Details → Ver Detalhes -->
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <?php 
    // Adicionar ação para o botão de atualização após a listagem
    do_action('sm_student_control_after_students_list'); 
    ?>
</div>

<!-- Adicione este CSS no arquivo de estilos ou inline para os elementos de ordenação -->
<style>
.sortable-header {
    cursor: pointer;
}

.sortable-column {
    display: block;
    color: #23282d;
    text-decoration: none;
}

.sortable-column:hover {
    color: #0073aa;
}

.sortable-header.sorted {
    background-color: #f8f8f8;
}

.dashicons-arrow-up-alt2,
.dashicons-arrow-down-alt2 {
    font-size: 16px;
    height: 16px;
    width: 16px;
    line-height: 1;
    vertical-align: text-top;
}
</style>

<!-- Após a tabela de alunos, adicionar a navegação de paginação -->
<?php if ($total_pages > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php 
                printf(
                    _n('%s aluno', '%s alunos', $total_students, 'sm-student-control'),
                    number_format_i18n($total_students)
                ); 
                ?>
            </span>
            
            <?php
            // Remova este código existente com problemas:
            // $paginate_args = array(
            //     'base' => add_query_arg('paged', '%#%'),
            //     ...
            // );

            // Substitua pelo código abaixo que lida corretamente com paginação:
            $page_url = menu_page_url('sm-student-control', false); // URL base segura
            $args_string = '';

            // Construir parâmetros de URL manualmente para evitar problemas com add_query_arg
            if (!empty($student_search)) {
                $args_string .= '&student_search=' . urlencode($student_search);
            }
            if (!empty($course_id) && is_scalar($course_id)) {
                $args_string .= '&course_id=' . intval($course_id);
            }
            if (!empty($last_access_month) && is_string($last_access_month)) {
                $args_string .= '&last_access_month=' . urlencode($last_access_month);
            }
            if (!empty($orderby) && is_string($orderby)) {
                $args_string .= '&orderby=' . urlencode($orderby);
            }
            if (!empty($order) && is_string($order)) {
                $args_string .= '&order=' . urlencode($order);
            }

            // Configurar argumentos para o paginador
            $paginate_args = array(
                'base' => $page_url . '&paged=%#%' . $args_string,
                'format' => '',
                'prev_text' => __('&laquo; Anterior', 'sm-student-control'),
                'next_text' => __('Próxima &raquo;', 'sm-student-control'),
                'total' => $total_pages,
                'current' => $paged
            );

            // Gerar links de paginação - não é necessário add_args aqui
            echo paginate_links($paginate_args);
            ?>
        </div>
    </div>
<?php endif; ?>