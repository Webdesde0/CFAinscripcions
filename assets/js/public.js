/**
 * CFA Inscripcions - JavaScript públic
 */
(function($) {
    'use strict';

    // Variables globals
    let currentStep = 1;
    let selectedCursId = null;
    let selectedCalendariId = null;
    let selectedDate = null;
    let selectedTime = null;
    let currentMonth = new Date().getMonth();
    let currentYear = new Date().getFullYear();
    let availableDays = {};

    // Noms dels mesos en català
    const monthNames = [
        'Gener', 'Febrer', 'Març', 'Abril', 'Maig', 'Juny',
        'Juliol', 'Agost', 'Setembre', 'Octubre', 'Novembre', 'Desembre'
    ];

    /**
     * Inicialització
     */
    function init() {
        // Navegació entre passos
        $(document).on('click', '.cfa-boto-seguent', function() {
            const nextStep = $(this).data('seguent');
            if (validateStep(currentStep)) {
                goToStep(nextStep);
            }
        });

        $(document).on('click', '.cfa-boto-anterior', function() {
            const prevStep = $(this).data('anterior');
            goToStep(prevStep);
        });

        // Selecció de curs
        $(document).on('change', 'input[name="curs_id"]', function() {
            selectedCursId = $(this).val();
            selectedCalendariId = $(this).data('calendari-id');
            $('#cfa-calendari-id').val(selectedCalendariId);
        });

        // Navegació del calendari
        $(document).on('click', '.cfa-calendari-prev', function() {
            navigateMonth(-1);
        });

        $(document).on('click', '.cfa-calendari-next', function() {
            navigateMonth(1);
        });

        // Clic en un dia del calendari
        $(document).on('click', '.cfa-dia-disponible', function() {
            selectDay($(this));
        });

        // Clic en una franja horària
        $(document).on('click', '.cfa-franja', function() {
            selectTimeSlot($(this));
        });

        // Enviament del formulari
        $('#cfa-inscripcio-form').on('submit', function(e) {
            e.preventDefault();
            submitForm();
        });

        // Comprovar si hi ha curs preseleccionat
        const preselected = $('input[name="curs_id"]:checked');
        if (preselected.length) {
            selectedCursId = preselected.val();
            selectedCalendariId = preselected.data('calendari-id');
            $('#cfa-calendari-id').val(selectedCalendariId);
        }
    }

    /**
     * Validar pas actual
     */
    function validateStep(step) {
        switch (step) {
            case 1:
                if (!selectedCursId) {
                    alert(cfaInscripcions.messages.error || 'Has de seleccionar un curs');
                    return false;
                }
                if (!selectedCalendariId) {
                    alert('Aquest curs no té calendari assignat');
                    return false;
                }
                return true;

            case 2:
                if (!selectedDate || !selectedTime) {
                    alert('Has de seleccionar una data i hora');
                    return false;
                }
                return true;

            case 3:
                // La validació del formulari es fa al submit
                return true;

            default:
                return true;
        }
    }

    /**
     * Anar a un pas
     */
    function goToStep(step) {
        // Ocultar pas actual
        $('#cfa-pas-' + currentStep).removeClass('cfa-pas-contingut-actiu');

        // Actualitzar indicadors
        $('.cfa-pas[data-pas="' + currentStep + '"]').removeClass('cfa-pas-actiu');
        if (step > currentStep) {
            $('.cfa-pas[data-pas="' + currentStep + '"]').addClass('cfa-pas-completat');
        }

        // Mostrar nou pas
        currentStep = step;
        $('#cfa-pas-' + step).addClass('cfa-pas-contingut-actiu');
        $('.cfa-pas[data-pas="' + step + '"]').addClass('cfa-pas-actiu').removeClass('cfa-pas-completat');

        // Accions específiques per pas
        if (step === 2) {
            loadCalendar();
        } else if (step === 3) {
            updateSummary();
        }

        // Scroll al top del formulari
        $('html, body').animate({
            scrollTop: $('.cfa-inscripcio-wrapper').offset().top - 50
        }, 300);
    }

    /**
     * Carregar calendari
     */
    function loadCalendar() {
        renderCalendar();
        loadAvailability();
    }

    /**
     * Renderitzar calendari
     */
    function renderCalendar() {
        const container = $('#cfa-calendari-dies');
        container.empty();

        // Actualitzar capçalera
        $('.cfa-calendari-mes-any').text(monthNames[currentMonth] + ' ' + currentYear);

        // Calcular primer dia del mes
        const firstDay = new Date(currentYear, currentMonth, 1);
        let startDay = firstDay.getDay();
        startDay = startDay === 0 ? 7 : startDay; // Convertir diumenge de 0 a 7

        // Dies del mes
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();

        // Dies buits abans del primer dia
        for (let i = 1; i < startDay; i++) {
            container.append('<div class="cfa-dia cfa-dia-buit"></div>');
        }

        // Dies del mes
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(currentYear, currentMonth, day);
            const dateStr = formatDate(date);

            let classes = ['cfa-dia'];

            if (date < today) {
                classes.push('cfa-dia-passat');
            } else if (date.toDateString() === today.toDateString()) {
                classes.push('cfa-dia-avui');
            }

            const $day = $('<div>')
                .addClass(classes.join(' '))
                .text(day)
                .attr('data-date', dateStr);

            container.append($day);
        }

        // Habilitar/deshabilitar navegació
        const now = new Date();
        const isPastMonth = currentYear < now.getFullYear() ||
                           (currentYear === now.getFullYear() && currentMonth < now.getMonth());
        $('.cfa-calendari-prev').prop('disabled', isPastMonth);
    }

    /**
     * Carregar disponibilitat
     */
    function loadAvailability() {
        const container = $('#cfa-calendari-dies');
        container.addClass('cfa-loading');

        $.ajax({
            url: cfaInscripcions.ajaxurl,
            type: 'POST',
            data: {
                action: 'cfa_obtenir_disponibilitat',
                nonce: cfaInscripcions.nonce,
                calendari_id: selectedCalendariId,
                mes: currentMonth + 1,
                any: currentYear
            },
            success: function(response) {
                container.removeClass('cfa-loading');

                if (response.success) {
                    availableDays = response.data.dies;
                    markAvailableDays();
                }
            },
            error: function() {
                container.removeClass('cfa-loading');
            }
        });
    }

    /**
     * Marcar dies disponibles
     */
    function markAvailableDays() {
        $('.cfa-dia').each(function() {
            const $day = $(this);
            const dateStr = $day.data('date');

            if (dateStr && availableDays[dateStr]) {
                $day.addClass('cfa-dia-disponible');
            } else if (!$day.hasClass('cfa-dia-passat') && !$day.hasClass('cfa-dia-buit')) {
                $day.addClass('cfa-dia-no-disponible');
            }
        });

        // Restaurar selecció si existeix
        if (selectedDate) {
            $(`.cfa-dia[data-date="${selectedDate}"]`).addClass('cfa-dia-seleccionat');
        }
    }

    /**
     * Navegar entre mesos
     */
    function navigateMonth(direction) {
        currentMonth += direction;

        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        } else if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }

        // Ocultar franges
        $('#cfa-franges-wrapper').hide();
        selectedDate = null;
        selectedTime = null;
        updateNextButton();

        loadCalendar();
    }

    /**
     * Seleccionar dia
     */
    function selectDay($day) {
        // Desseleccionar anterior
        $('.cfa-dia-seleccionat').removeClass('cfa-dia-seleccionat');

        // Seleccionar nou
        $day.addClass('cfa-dia-seleccionat');
        selectedDate = $day.data('date');
        $('#cfa-data-cita').val(selectedDate);

        // Reiniciar hora seleccionada
        selectedTime = null;
        $('#cfa-hora-cita').val('');
        updateNextButton();

        // Carregar franges horàries
        loadTimeSlots();
    }

    /**
     * Carregar franges horàries
     */
    function loadTimeSlots() {
        const container = $('#cfa-franges-llista');
        const wrapper = $('#cfa-franges-wrapper');

        container.empty();
        wrapper.show();
        container.addClass('cfa-loading');

        $.ajax({
            url: cfaInscripcions.ajaxurl,
            type: 'POST',
            data: {
                action: 'cfa_obtenir_franges',
                nonce: cfaInscripcions.nonce,
                calendari_id: selectedCalendariId,
                data: selectedDate
            },
            success: function(response) {
                container.removeClass('cfa-loading');

                if (response.success && response.data.franges.length > 0) {
                    response.data.franges.forEach(function(franja) {
                        const horaInici = franja.hora_inici.substring(0, 5);

                        const $slot = $('<div>')
                            .addClass('cfa-franja')
                            .attr('data-hora', franja.hora_inici)
                            .html(`
                                <div class="cfa-franja-hora">${horaInici}</div>
                            `);

                        container.append($slot);
                    });
                } else {
                    container.html('<p>No hi ha hores disponibles per aquest dia.</p>');
                }
            },
            error: function() {
                container.removeClass('cfa-loading');
                container.html('<p>Error carregant horaris.</p>');
            }
        });
    }

    /**
     * Seleccionar franja horària
     */
    function selectTimeSlot($slot) {
        $('.cfa-franja-seleccionada').removeClass('cfa-franja-seleccionada');
        $slot.addClass('cfa-franja-seleccionada');
        selectedTime = $slot.data('hora');
        $('#cfa-hora-cita').val(selectedTime);
        updateNextButton();
    }

    /**
     * Actualitzar botó següent
     */
    function updateNextButton() {
        const btn = $('#cfa-pas-2 .cfa-boto-seguent');
        btn.prop('disabled', !selectedDate || !selectedTime);
    }

    /**
     * Actualitzar resum
     */
    function updateSummary() {
        const cursNom = $('input[name="curs_id"]:checked').closest('.cfa-curs-card').find('.cfa-curs-nom').text();
        const dateFormatted = formatDateDisplay(selectedDate);
        const timeFormatted = selectedTime.substring(0, 5) + 'h';

        $('#cfa-resum-seleccio').html(`
            <p><strong>Curs:</strong> ${cursNom}</p>
            <p><strong>Data:</strong> ${dateFormatted}</p>
            <p><strong>Hora:</strong> ${timeFormatted}</p>
        `);
    }

    /**
     * Enviar formulari
     */
    function submitForm() {
        const $form = $('#cfa-inscripcio-form');
        const $btn = $form.find('.cfa-boto-enviar');

        // Validar camps obligatoris
        const required = $form.find('[required]');
        let valid = true;

        required.each(function() {
            if (!$(this).val() || ($(this).attr('type') === 'checkbox' && !$(this).is(':checked'))) {
                $(this).addClass('error');
                valid = false;
            } else {
                $(this).removeClass('error');
            }
        });

        if (!valid) {
            alert('Si us plau, omple tots els camps obligatoris.');
            return;
        }

        // Deshabilitar botó
        $btn.prop('disabled', true).text(cfaInscripcions.messages.enviando || 'Enviant...');

        // Enviar
        $.ajax({
            url: cfaInscripcions.ajaxurl,
            type: 'POST',
            data: $form.serialize() + '&action=cfa_enviar_inscripcio&nonce=' + cfaInscripcions.nonce,
            success: function(response) {
                if (response.success) {
                    // Mostrar pàgina d'èxit
                    $('.cfa-pas-contingut').removeClass('cfa-pas-contingut-actiu');
                    $('#cfa-pas-exit').show().addClass('cfa-pas-contingut-actiu');

                    // Mostrar detalls
                    const detalls = response.data.detalls;
                    $('#cfa-exit-detalls').html(`
                        <p><strong>Curs:</strong> ${detalls.curs}</p>
                        <p><strong>Data:</strong> ${detalls.data}</p>
                        <p><strong>Hora:</strong> ${detalls.hora}h</p>
                        <p><strong>Nom:</strong> ${detalls.nom}</p>
                    `);

                    // Actualitzar indicadors
                    $('.cfa-pas').addClass('cfa-pas-completat').removeClass('cfa-pas-actiu');

                    // Scroll
                    $('html, body').animate({
                        scrollTop: $('.cfa-inscripcio-wrapper').offset().top - 50
                    }, 300);
                } else {
                    alert(response.data.message || cfaInscripcions.messages.error);
                    $btn.prop('disabled', false).text('Enviar inscripció');
                }
            },
            error: function() {
                alert(cfaInscripcions.messages.error || 'Error de connexió');
                $btn.prop('disabled', false).text('Enviar inscripció');
            }
        });
    }

    /**
     * Formatar data (YYYY-MM-DD)
     */
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    /**
     * Formatar data per mostrar
     */
    function formatDateDisplay(dateStr) {
        const parts = dateStr.split('-');
        const date = new Date(parts[0], parts[1] - 1, parts[2]);

        const diasSetmana = ['Diumenge', 'Dilluns', 'Dimarts', 'Dimecres', 'Dijous', 'Divendres', 'Dissabte'];
        const dia = diasSetmana[date.getDay()];

        return `${dia}, ${parts[2]} de ${monthNames[parseInt(parts[1]) - 1]} de ${parts[0]}`;
    }

    // Inicialitzar quan el DOM estigui llest
    $(document).ready(init);

})(jQuery);
