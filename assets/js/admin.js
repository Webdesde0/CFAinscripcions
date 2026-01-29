/**
 * CFA Inscripcions - JavaScript admin
 */
(function($) {
    'use strict';

    /**
     * Inicialització
     */
    function init() {
        // Confirmar inscripció
        $(document).on('click', '.cfa-btn-confirmar', function() {
            const id = $(this).data('id');
            if (confirm('Vols confirmar aquesta inscripció? S\'enviarà un email de confirmació a l\'alumne.')) {
                ajaxAction('cfa_confirmar_inscripcio', { id: id }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            }
        });

        // Cancel·lar inscripció
        $(document).on('click', '.cfa-btn-cancel-lar', function() {
            const id = $(this).data('id');
            const motiu = prompt('Motiu de la cancel·lació (opcional):');
            if (motiu !== null) {
                ajaxAction('cfa_cancel_lar_inscripcio', { id: id, motiu: motiu }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            }
        });

        // Eliminar inscripció
        $(document).on('click', '.cfa-btn-eliminar', function() {
            const id = $(this).data('id');
            if (confirm('Estàs segur que vols eliminar aquesta inscripció? Aquesta acció no es pot desfer.')) {
                ajaxAction('cfa_eliminar_inscripcio', { id: id }, function(response) {
                    if (response.success) {
                        window.location.href = 'admin.php?page=cfa-inscripcions';
                    }
                });
            }
        });

        // Formulari calendari
        $('#cfa-calendari-form').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            ajaxAction('cfa_guardar_calendari', formData, function(response) {
                if (response.success && response.data.redirect) {
                    window.location.href = response.data.redirect;
                }
            });
        });

        // Eliminar calendari
        $(document).on('click', '.cfa-btn-eliminar-calendari', function() {
            const id = $(this).data('id');
            if (confirm('Estàs segur que vols eliminar aquest calendari? S\'eliminaran també tots els horaris i excepcions associats.')) {
                ajaxAction('cfa_eliminar_calendari', { id: id, nonce: $('#nonce').val() }, function(response) {
                    if (response.success && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    }
                });
            }
        });

        // Afegir horari
        $('#cfa-afegir-horari').on('click', function() {
            const calendariId = $('input[name="calendari_id"]').val();
            const dia = $('select[name="nou_dia"]').val();
            const horaInici = $('input[name="nou_hora_inici"]').val();
            const horaFi = $('input[name="nou_hora_fi"]').val();

            if (!horaInici || !horaFi) {
                alert('Has d\'indicar l\'hora d\'inici i fi.');
                return;
            }

            ajaxAction('cfa_guardar_horaris', {
                calendari_id: calendariId,
                horari_action: 'afegir',
                dia: dia,
                hora_inici: horaInici,
                hora_fi: horaFi,
                nonce: $('#nonce').val()
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        });

        // Eliminar horari
        $(document).on('click', '.cfa-eliminar-horari', function() {
            const id = $(this).data('id');
            const calendariId = $('input[name="calendari_id"]').val();

            if (confirm('Vols eliminar aquest horari?')) {
                ajaxAction('cfa_guardar_horaris', {
                    calendari_id: calendariId,
                    horari_action: 'eliminar',
                    horari_id: id,
                    nonce: $('#nonce').val()
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            }
        });

        // Mostrar/ocultar camps d'hora en excepcions
        $('#cfa-excepcio-tipus').on('change', function() {
            const tipus = $(this).val();
            if (tipus === 'afegir') {
                $('.cfa-excepcio-hores').show();
            } else {
                $('.cfa-excepcio-hores').hide();
            }
        });

        // Formulari excepció
        $('#cfa-excepcio-form').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            ajaxAction('cfa_afegir_excepcio', formData, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        });

        // Eliminar excepció
        $(document).on('click', '.cfa-eliminar-excepcio', function() {
            const id = $(this).data('id');
            const calendariId = $('input[name="calendari_id"]').val();

            if (confirm('Vols eliminar aquesta excepció?')) {
                ajaxAction('cfa_eliminar_excepcio', {
                    id: id,
                    calendari_id: calendariId,
                    nonce: $('input[name="nonce"]').val()
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            }
        });

        // Formulari editar inscripció
        $('#cfa-editar-inscripcio-form').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            ajaxAction('cfa_editar_inscripcio', formData, function(response) {
                if (response.success && response.data.redirect) {
                    window.location.href = response.data.redirect;
                }
            });
        });
    }

    /**
     * Executar acció AJAX
     */
    function ajaxAction(action, data, successCallback) {
        // Afegir action si no és un string serialitzat
        if (typeof data === 'object') {
            data.action = action;
            if (!data.nonce) {
                data.nonce = cfaAdmin.nonce;
            }
        } else {
            data += '&action=' + action;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    if (response.data.message) {
                        showNotice(response.data.message, 'success');
                    }
                    if (successCallback) {
                        successCallback(response);
                    }
                } else {
                    showNotice(response.data.message || 'Error', 'error');
                }
            },
            error: function() {
                showNotice('Error de connexió', 'error');
            }
        });
    }

    /**
     * Mostrar notificació
     */
    function showNotice(message, type) {
        const notice = $('<div>')
            .addClass('notice notice-' + type + ' is-dismissible')
            .html('<p>' + message + '</p>');

        $('.wrap h1').first().after(notice);

        // Auto ocultar
        setTimeout(function() {
            notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Inicialitzar
    $(document).ready(init);

    // Variable global per nonce
    window.cfaAdmin = window.cfaAdmin || { nonce: '' };

})(jQuery);
