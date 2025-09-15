/**
 * Gerencia as funcionalidades de atualização do cache de alunos
 * 
 * @package    SM_Student_Control
 * @since      1.0.0
 */

jQuery(document).ready(function($) {
    // Inicializa apenas o sistema de atualização em lote
    initBatchUpdate();
    
    /**
     * Inicializa a funcionalidade de atualização em lote
     */
    function initBatchUpdate() {
        var $button = $('#refresh-student-cache');
        var $status = $('#refresh-status');
        var offset = 0;
        var isRunning = false;
        
        // Evento de clique no botão de atualização em lote
        $button.on('click', function() {
            if (isRunning) return;
            
            isRunning = true;
            offset = 0;
            $(this).addClass('updating');
            $status.html('Iniciando atualização...');
            
            processNextBatch();
        });
        
        /**
         * Processa um lote de alunos
         */
        function processNextBatch() {
            // CORREÇÃO: Garantir que estamos usando sm_student_control e não sm_cache
            if (typeof sm_student_control === 'undefined') {
                $status.html('Erro de configuração do plugin.');
                $button.removeClass('updating');
                isRunning = false;
                return;
            }
            
            $.ajax({
                url: sm_student_control.ajax_url,
                type: 'POST',
                data: {
                    action: 'refresh_cache',
                    offset: offset,
                    student_search: getUrlParameter('student_search') || $('#student-search').val() || '',
                    course_id: getUrlParameter('course_id') || $('#course-filter').val() || '',
                    last_access_month: getUrlParameter('last_access_month') || $('#last-access-filter').val() || '',
                    nonce: sm_student_control.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html(response.data.message);
                        
                        if (!response.data.done) {
                            offset = response.data.offset;
                            processNextBatch();
                        } else {
                            $button.removeClass('updating');
                            isRunning = false;
                            
                            // Recarregar a página após 2 segundos para mostrar dados atualizados
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    $status.html('Erro ao atualizar dados: ' + error);
                    $button.removeClass('updating');
                    isRunning = false;
                }
            });
        }
        
        // Função helper para obter parâmetros da URL
        function getUrlParameter(name) {
            name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
            var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
            var results = regex.exec(location.search);
            return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
        }
    }
    
    $('.update-cache-button').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var userId = button.data('user-id');
        
        // Mostrar status de carregamento
        button.prop('disabled', true);
        button.text('Atualizando...');
        
        // Adicionar mensagem de status
        var statusMessage = $('<div class="update-status"></div>');
        button.after(statusMessage);
        statusMessage.text('Iniciando atualização. Isso pode levar alguns instantes...');
        
        // Configurar timeout mais longo
        $.ajax({
            url: sm_student_control.ajax_url,
            type: 'POST',
            data: {
                action: 'update_single_student',
                user_id: userId,
                nonce: sm_student_control.nonce
            },
            timeout: 120000, // 2 minutos
            success: function(response) {
                console.log('AJAX response:', response);
                
                if (response.success) {
                    statusMessage.html('<span style="color:green">✓ Atualizado com sucesso!</span>');
                    button.text('Atualizado');
                    
                    // Recarregar a página após 2 segundos
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    statusMessage.html('<span style="color:red">✗ Erro: ' + response.data.message + '</span>');
                    button.text('Tentar novamente');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', status, error);
                
                // Formatar a mensagem de erro de forma mais legível
                let errorMessage = 'Erro desconhecido';
                
                if (status === 'timeout') {
                    errorMessage = 'Tempo limite excedido';
                } else if (xhr.status === 503) {
                    errorMessage = 'Servidor sobrecarregado (503)';
                } else if (typeof error === 'string' && error.length > 0) {
                    errorMessage = error;
                } else if (xhr.statusText) {
                    errorMessage = xhr.statusText;
                } else if (status) {
                    errorMessage = status;
                }
                
                // Exibir mensagem de erro formatada
                statusMessage.html('<span style="color:red">✗ Erro: ' + errorMessage + '</span>');
                button.text('Tentar novamente');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
});