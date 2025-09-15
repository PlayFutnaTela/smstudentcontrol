<?php
/**
 * Template para dados do GamiPress do aluno
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Usar diretamente os dados já decodificados - NÃO fazer json_decode novamente
$gamipress_data = isset($student['gamipress_data']) ? $student['gamipress_data'] : null;

if (empty($gamipress_data)) {
    return;
}
?>

<!-- Pontos -->
<h3><?php _e('Pontos e Recompensas', 'sm-student-control'); ?></h3>

<?php if (!empty($gamipress_data['points'])): ?>
    <div class="gamipress-points-container">
        <?php foreach ($gamipress_data['points'] as $points): ?>
            <div class="gamipress-points-item">
                <?php if (!empty($points['image_url'])): ?>
                    <img src="<?php echo esc_url($points['image_url']); ?>" alt="<?php echo esc_attr($points['name']); ?>" class="points-icon">
                <?php endif; ?>
                <div class="points-details">
                    <span class="points-name"><?php echo esc_html($points['name']); ?>:</span>
                    <span class="points-amount"><?php echo esc_html(number_format_i18n($points['amount'])); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p><?php _e('Nenhum ponto ganho ainda.', 'sm-student-control'); ?></p>
<?php endif; ?>

<!-- Conquistas -->
<h3><?php _e('Conquistas', 'sm-student-control'); ?></h3>

<?php if (!empty($gamipress_data['achievements'])): ?>
    <div class="gamipress-achievements-container">
        <?php foreach ($gamipress_data['achievements'] as $achievement): ?>
            <div class="gamipress-achievement-item">
                <?php if (!empty($achievement['image_url'])): ?>
                    <img src="<?php echo esc_url($achievement['image_url']); ?>" alt="<?php echo esc_attr($achievement['title']); ?>" class="achievement-icon">
                <?php endif; ?>
                <div class="achievement-details">
                    <h4><?php echo esc_html($achievement['title']); ?></h4>
                    <p><?php echo esc_html($achievement['description']); ?></p>
                    <span class="achievement-date"><?php echo SM_Student_Control_Data::format_date_safely($achievement['date']); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p><?php _e('Nenhuma conquista desbloqueada ainda.', 'sm-student-control'); ?></p>
<?php endif; ?>

<!-- Badges -->
<h3><?php _e('Emblemas', 'sm-student-control'); ?></h3>

<?php if (!empty($gamipress_data['badges'])): ?>
    <div class="gamipress-badges-container">
        <?php foreach ($gamipress_data['badges'] as $badge): ?>
            <div class="gamipress-badge-item">
                <?php if (!empty($badge['image_url'])): ?>
                    <img src="<?php echo esc_url($badge['image_url']); ?>" alt="<?php echo esc_attr($badge['title']); ?>" class="badge-icon">
                <?php endif; ?>
                <div class="badge-details">
                    <h4><?php echo esc_html($badge['title']); ?></h4>
                    <p><?php echo esc_html($badge['description']); ?></p>
                    <span class="badge-date"><?php echo SM_Student_Control_Data::format_date_safely($badge['date']); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p><?php _e('Nenhum emblema conquistado ainda.', 'sm-student-control'); ?></p>
<?php endif; ?>