/**
 * Admin JavaScript for SM Student Control
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Initialize DataTables if available
        if ($.fn.DataTable && $('.students-list').length) {
            $('.students-list').DataTable({
                "paging": true,
                "searching": false, // We already have our own search
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "responsive": true
            });
        }
        
        // Corrigir o código jQuery que manipula o clique no botão:
        $(document).on('click', '.update-cache-button', function() {
            var userId = $(this).data('user-id');
            console.log('Iniciando atualização para user ID:', userId);
            
            // Mostrar mensagem de carregamento
            var $button = $(this);
            var originalText = $button.text();
            $button.text('Atualizando...').prop('disabled', true);
            
            // Fazer requisição AJAX para update_cache_action
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_single_student',
                    security: sm_student_control_vars.nonce,
                    user_id: userId  // Este é o ID do WordPress, não do cache
                },
                success: function(response) {
                    if (response.success) {
                        // Atualizar UI ou mostrar mensagem de sucesso
                        if ($('.update-success').length) {
                            $('.update-success').show().delay(3000).fadeOut();
                        } else {
                            $('<div class="update-success notice notice-success is-dismissible"><p>Atualização concluída com sucesso.</p></div>')
                                .insertBefore($button.closest('.student-actions'))
                                .delay(3000)
                                .fadeOut();
                        }
                        console.log('Atualização concluída:', response.data);
                        
                        // Atualizar a página para mostrar dados atualizados
                        location.reload();
                    } else {
                        // Mostrar erro
                        alert('Erro ao atualizar: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro AJAX:', error);
                    alert('Erro ao conectar ao servidor.');
                },
                complete: function() {
                    // Restaurar botão
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });

        // Handler para o botão de exportar Excel
        $(document).on('click', '#export-excel-btn', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // Mostrar loading
            $button.text('Gerando arquivo...').prop('disabled', true);
            
            // Coletar dados do formulário de filtro
            var formData = {
                action: 'export_students_excel',
                security: sm_student_control_vars.nonce,
                student_search: $('#student_search').val() || '',
                course_id: $('#course_filter').val() || '',
                last_access_month: $('#last_access_month').val() || ''
            };
            
            // Fazer requisição AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Criar link temporário e fazer download
                        var link = document.createElement('a');
                        link.href = response.data.file_url;
                        link.download = '';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        
                        // Mostrar mensagem de sucesso
                        var successMsg = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p>Arquivo Excel gerado com sucesso!</p></div>');
                        $('.filter-container').after(successMsg);
                        setTimeout(function() {
                            successMsg.fadeOut();
                        }, 3000);
                    } else {
                        alert('Erro ao gerar arquivo: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro AJAX:', error);
                    alert('Erro ao conectar ao servidor.');
                },
                complete: function() {
                    // Restaurar botão
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });
    });
    
})(jQuery);