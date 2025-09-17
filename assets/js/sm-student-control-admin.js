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
            
            console.log('Export button clicked');
            
            var $button = $(this);
            var originalText = $button.text();
            
            // Mostrar loading
            $button.text('Gerando arquivo...').prop('disabled', true);
            
            // Verificar se as variáveis globais existem
            if (typeof ajaxurl === 'undefined') {
                console.error('ajaxurl is not defined');
                alert('Erro: ajaxurl não definido');
                $button.text(originalText).prop('disabled', false);
                return;
            }
            
            if (typeof sm_student_control_vars === 'undefined' || !sm_student_control_vars.nonce) {
                console.error('sm_student_control_vars or nonce is not defined');
                alert('Erro: nonce não definido');
                $button.text(originalText).prop('disabled', false);
                return;
            }
            
            // Coletar dados do formulário de filtro
            var formData = {
                action: 'export_students_excel',
                security: sm_student_control_vars.nonce,
                student_search: $('#student_search').val() || '',
                course_id: $('#course_filter').val() || '',
                last_access_month: $('#last_access_month').val() || ''
            };
            
            console.log('Form data:', formData);
            
            // Fazer requisição AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                timeout: 30000, // 30 seconds timeout
                success: function(response) {
                    console.log('AJAX response:', response);
                    console.log('Response type:', typeof response);
                    console.log('Response success:', response.success);
                    console.log('Response data:', response.data);
                    
                    if (response.success) {
                        console.log('Success response:', response.data);
                        
                        if (response.data.file_url) {
                            console.log('File URL:', response.data.file_url);
                            
                            // Criar link temporário e fazer download
                            var link = document.createElement('a');
                            link.href = response.data.file_url;
                            link.download = '';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            
                            // Mostrar mensagem de sucesso
                            var successMsg = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p>Arquivo Excel gerado com sucesso!</p></div>');
                            $('.filter-container, .sm-student-filter-section').first().after(successMsg);
                            setTimeout(function() {
                                successMsg.fadeOut();
                            }, 3000);
                        } else {
                            // Resposta de debug
                            console.log('Debug response:', response.data);
                            alert('Debug info: ' + JSON.stringify(response.data, null, 2));
                        }
                    } else {
                        console.error('Export failed:', response);
                        console.error('Error message:', response.data ? response.data.message : 'No error message');
                        
                        var errorMsg = 'Erro desconhecido';
                        if (response.data && response.data.message) {
                            errorMsg = response.data.message;
                        } else if (typeof response.data === 'string') {
                            errorMsg = response.data;
                        }
                        alert('Erro ao gerar arquivo: ' + errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', {xhr: xhr, status: status, error: error});
                    console.error('Response text:', xhr.responseText);
                    
                    if (status === 'timeout') {
                        alert('Erro: Timeout - a requisição demorou muito para responder.');
                    } else {
                        alert('Erro ao conectar ao servidor: ' + error);
                    }
                },
                complete: function() {
                    // Restaurar botão
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });
    });
    
})(jQuery);