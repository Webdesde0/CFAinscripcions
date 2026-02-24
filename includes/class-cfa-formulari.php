<?php
/**
 * Formulari d'inscripció frontend
 *
 * @package CFA_Inscripcions
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFA_Formulari {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Shortcode
        add_shortcode('cfa_inscripcio', array($this, 'render_formulari'));

        // AJAX handlers
        add_action('wp_ajax_cfa_obtenir_disponibilitat', array($this, 'ajax_obtenir_disponibilitat'));
        add_action('wp_ajax_nopriv_cfa_obtenir_disponibilitat', array($this, 'ajax_obtenir_disponibilitat'));

        add_action('wp_ajax_cfa_obtenir_franges', array($this, 'ajax_obtenir_franges'));
        add_action('wp_ajax_nopriv_cfa_obtenir_franges', array($this, 'ajax_obtenir_franges'));

        add_action('wp_ajax_cfa_enviar_inscripcio', array($this, 'ajax_enviar_inscripcio'));
        add_action('wp_ajax_nopriv_cfa_enviar_inscripcio', array($this, 'ajax_enviar_inscripcio'));
    }

    /**
     * Renderitzar el formulari d'inscripció
     */
    public function render_formulari($atts) {
        $atts = shortcode_atts(array(
            'curs' => '', // Preseleccionar un curs
        ), $atts);

        // Obtenir cursos actius
        $cursos = CFA_Inscripcions_DB::obtenir_cursos(array('actius_nomes' => true));

        if (empty($cursos)) {
            return '<div class="cfa-inscripcio-avís">' .
                   __('No hi ha cursos disponibles en aquest moment.', 'cfa-inscripcions') .
                   '</div>';
        }

        ob_start();
        ?>
        <div class="cfa-inscripcio-wrapper" id="cfa-inscripcio">
            <!-- Indicador de passos -->
            <div class="cfa-passos">
                <div class="cfa-pas cfa-pas-actiu" data-pas="1">
                    <span class="cfa-pas-numero">1</span>
                    <span class="cfa-pas-text"><?php _e('Curs', 'cfa-inscripcions'); ?></span>
                </div>
                <div class="cfa-pas-separador"></div>
                <div class="cfa-pas" data-pas="2">
                    <span class="cfa-pas-numero">2</span>
                    <span class="cfa-pas-text"><?php _e('Data i hora', 'cfa-inscripcions'); ?></span>
                </div>
                <div class="cfa-pas-separador"></div>
                <div class="cfa-pas" data-pas="3">
                    <span class="cfa-pas-numero">3</span>
                    <span class="cfa-pas-text"><?php _e('Dades', 'cfa-inscripcions'); ?></span>
                </div>
            </div>

            <form id="cfa-inscripcio-form" method="post">
                <?php wp_nonce_field('cfa_inscripcio_nonce', 'cfa_nonce'); ?>

                <!-- Honeypot anti-spam (camp ocult que els bots omplen) -->
                <div style="position: absolute; left: -9999px;" aria-hidden="true">
                    <input type="text" name="website_url" tabindex="-1" autocomplete="off" value="">
                </div>

                <!-- PAS 1: Selecció de curs -->
                <div class="cfa-pas-contingut cfa-pas-contingut-actiu" id="cfa-pas-1">
                    <h2><?php _e('Selecciona el curs', 'cfa-inscripcions'); ?></h2>
                    <p class="cfa-pas-descripcio"><?php _e('Tria el curs en el qual vols inscriure\'t', 'cfa-inscripcions'); ?></p>

                    <div class="cfa-cursos-llista">
                        <?php foreach ($cursos as $curs) : ?>
                            <?php
                            $preseleccionat = (!empty($atts['curs']) && $atts['curs'] == $curs->id) ? 'checked' : '';
                            ?>
                            <label class="cfa-curs-card" data-curs-id="<?php echo esc_attr($curs->id); ?>"
                                   data-calendari-id="<?php echo esc_attr($curs->calendari_id); ?>">
                                <input type="radio" name="curs_id" value="<?php echo esc_attr($curs->id); ?>"
                                       data-calendari-id="<?php echo esc_attr($curs->calendari_id); ?>"
                                       required <?php echo $preseleccionat; ?>>
                                <div class="cfa-curs-card-contingut">
                                    <div class="cfa-curs-info">
                                        <span class="cfa-curs-nom"><?php echo esc_html($curs->nom); ?></span>
                                        <?php if (!empty($curs->descripcio)) : ?>
                                            <span class="cfa-curs-descripcio"><?php echo esc_html($curs->descripcio); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="cfa-curs-check">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    </span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="cfa-pas-botons">
                        <button type="button" class="cfa-boto cfa-boto-primari cfa-boto-seguent" data-seguent="2">
                            <?php _e('Següent', 'cfa-inscripcions'); ?>
                            <span class="cfa-boto-icona">→</span>
                        </button>
                    </div>
                </div>

                <!-- PAS 2: Selecció de data i hora -->
                <div class="cfa-pas-contingut" id="cfa-pas-2">
                    <h2><?php _e('Selecciona data i hora', 'cfa-inscripcions'); ?></h2>
                    <p class="cfa-pas-descripcio"><?php _e('Tria una data i hora disponible per a la teva cita', 'cfa-inscripcions'); ?></p>

                    <div class="cfa-calendari-wrapper">
                        <div class="cfa-calendari-header">
                            <button type="button" class="cfa-calendari-nav cfa-calendari-prev">
                                <span>←</span>
                            </button>
                            <span class="cfa-calendari-mes-any"></span>
                            <button type="button" class="cfa-calendari-nav cfa-calendari-next">
                                <span>→</span>
                            </button>
                        </div>
                        <div class="cfa-calendari-dies-setmana">
                            <span><?php _e('Dl', 'cfa-inscripcions'); ?></span>
                            <span><?php _e('Dt', 'cfa-inscripcions'); ?></span>
                            <span><?php _e('Dc', 'cfa-inscripcions'); ?></span>
                            <span><?php _e('Dj', 'cfa-inscripcions'); ?></span>
                            <span><?php _e('Dv', 'cfa-inscripcions'); ?></span>
                            <span><?php _e('Ds', 'cfa-inscripcions'); ?></span>
                            <span><?php _e('Dg', 'cfa-inscripcions'); ?></span>
                        </div>
                        <div class="cfa-calendari-dies" id="cfa-calendari-dies">
                            <!-- Dies generats per JavaScript -->
                        </div>
                    </div>

                    <div class="cfa-franges-wrapper" id="cfa-franges-wrapper" style="display: none;">
                        <h3><?php _e('Hores disponibles', 'cfa-inscripcions'); ?></h3>
                        <div class="cfa-franges-llista" id="cfa-franges-llista">
                            <!-- Franges generades per JavaScript -->
                        </div>
                    </div>

                    <input type="hidden" name="data_cita" id="cfa-data-cita" value="">
                    <input type="hidden" name="hora_cita" id="cfa-hora-cita" value="">
                    <input type="hidden" name="calendari_id" id="cfa-calendari-id" value="">

                    <div class="cfa-pas-botons">
                        <button type="button" class="cfa-boto cfa-boto-secundari cfa-boto-anterior" data-anterior="1">
                            <span class="cfa-boto-icona">←</span>
                            <?php _e('Anterior', 'cfa-inscripcions'); ?>
                        </button>
                        <button type="button" class="cfa-boto cfa-boto-primari cfa-boto-seguent" data-seguent="3" disabled>
                            <?php _e('Següent', 'cfa-inscripcions'); ?>
                            <span class="cfa-boto-icona">→</span>
                        </button>
                    </div>
                </div>

                <!-- PAS 3: Dades personals -->
                <div class="cfa-pas-contingut" id="cfa-pas-3">
                    <h2><?php _e('Les teves dades', 'cfa-inscripcions'); ?></h2>
                    <p class="cfa-pas-descripcio"><?php _e('Omple les teves dades per completar la inscripció', 'cfa-inscripcions'); ?></p>

                    <div class="cfa-resum-seleccio" id="cfa-resum-seleccio">
                        <!-- Resum generat per JavaScript -->
                    </div>

                    <div class="cfa-formulari-camps">
                        <div class="cfa-camp-fila">
                            <div class="cfa-camp">
                                <label for="cfa-nom"><?php _e('Nom', 'cfa-inscripcions'); ?> <span class="required">*</span></label>
                                <input type="text" name="nom" id="cfa-nom" required>
                            </div>
                            <div class="cfa-camp">
                                <label for="cfa-cognoms"><?php _e('Cognoms', 'cfa-inscripcions'); ?> <span class="required">*</span></label>
                                <input type="text" name="cognoms" id="cfa-cognoms" required>
                            </div>
                        </div>

                        <div class="cfa-camp-fila">
                            <div class="cfa-camp">
                                <label for="cfa-dni"><?php _e('DNI/NIE', 'cfa-inscripcions'); ?> <span class="required">*</span></label>
                                <input type="text" name="dni" id="cfa-dni" required pattern="[0-9A-Za-z]{8,9}[A-Za-z]?"
                                       placeholder="12345678A">
                            </div>
                            <div class="cfa-camp">
                                <label for="cfa-telefon"><?php _e('Telèfon', 'cfa-inscripcions'); ?> <span class="required">*</span></label>
                                <input type="tel" name="telefon" id="cfa-telefon" required>
                            </div>
                        </div>

                        <div class="cfa-camp">
                            <label for="cfa-email"><?php _e('Correu electrònic', 'cfa-inscripcions'); ?> <span class="required">*</span></label>
                            <input type="email" name="email" id="cfa-email" required>
                        </div>

                        <div class="cfa-camp cfa-camp-checkbox">
                            <label>
                                <input type="checkbox" name="accepta_privacitat" id="cfa-accepta-privacitat" required>
                                <?php
                                printf(
                                    __('He llegit i accepto la %spolítica de privacitat%s', 'cfa-inscripcions'),
                                    '<a href="' . esc_url(get_privacy_policy_url()) . '" target="_blank">',
                                    '</a>'
                                );
                                ?>
                                <span class="required">*</span>
                            </label>
                        </div>
                    </div>

                    <div class="cfa-pas-botons">
                        <button type="button" class="cfa-boto cfa-boto-secundari cfa-boto-anterior" data-anterior="2">
                            <span class="cfa-boto-icona">←</span>
                            <?php _e('Anterior', 'cfa-inscripcions'); ?>
                        </button>
                        <button type="submit" class="cfa-boto cfa-boto-primari cfa-boto-enviar">
                            <?php _e('Enviar inscripció', 'cfa-inscripcions'); ?>
                        </button>
                    </div>
                </div>

                <!-- Missatge d'èxit -->
                <div class="cfa-pas-contingut cfa-pas-exit" id="cfa-pas-exit" style="display: none;">
                    <div class="cfa-exit-icona">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="16 8 10 14 8 12"></polyline>
                        </svg>
                    </div>
                    <h2><?php _e('Inscripció enviada!', 'cfa-inscripcions'); ?></h2>
                    <p><?php _e('Hem rebut la teva sol·licitud d\'inscripció correctament.', 'cfa-inscripcions'); ?></p>
                    <p><?php _e('Rebràs un correu electrònic amb la confirmació. El centre es posarà en contacte amb tu per confirmar la cita.', 'cfa-inscripcions'); ?></p>
                    <div class="cfa-exit-detalls" id="cfa-exit-detalls">
                        <!-- Detalls generats per JavaScript -->
                    </div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Obtenir disponibilitat del calendari (dies amb horaris)
     */
    public function ajax_obtenir_disponibilitat() {
        check_ajax_referer('cfa_inscripcions_nonce', 'nonce');

        $calendari_id = isset($_POST['calendari_id']) ? absint($_POST['calendari_id']) : 0;
        $mes = isset($_POST['mes']) ? absint($_POST['mes']) : date('n');
        $any = isset($_POST['any']) ? absint($_POST['any']) : date('Y');

        if (!$calendari_id) {
            wp_send_json_error(array('message' => __('Calendari no vàlid', 'cfa-inscripcions')));
        }

        $calendari = CFA_Inscripcions_DB::obtenir_calendari($calendari_id);
        if (!$calendari) {
            wp_send_json_error(array('message' => __('Calendari no trobat', 'cfa-inscripcions')));
        }

        // Calcular rang de dates
        $data_inici = sprintf('%04d-%02d-01', $any, $mes);
        $data_fi_mes = date('Y-m-t', strtotime($data_inici));

        // Plaç màxim
        $plac_maxim = date('Y-m-d', strtotime('+' . $calendari->plac_maxim_dies . ' days'));
        $data_fi = min($data_fi_mes, $plac_maxim);

        // No mostrar dies passats
        $avui = date('Y-m-d');
        if ($data_inici < $avui) {
            $data_inici = $avui;
        }

        // Obtenir dies disponibles
        $dies_disponibles = CFA_Inscripcions_DB::obtenir_dies_disponibles($calendari_id, $data_inici, $data_fi);

        wp_send_json_success(array(
            'dies' => $dies_disponibles,
            'plac_maxim' => $plac_maxim,
        ));
    }

    /**
     * AJAX: Obtenir franges horàries d'un dia
     */
    public function ajax_obtenir_franges() {
        check_ajax_referer('cfa_inscripcions_nonce', 'nonce');

        $calendari_id = isset($_POST['calendari_id']) ? absint($_POST['calendari_id']) : 0;
        $data = isset($_POST['data']) ? sanitize_text_field($_POST['data']) : '';

        if (!$calendari_id || !$data) {
            wp_send_json_error(array('message' => __('Paràmetres invàlids', 'cfa-inscripcions')));
        }

        // Validar data
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            wp_send_json_error(array('message' => __('Format de data invàlid', 'cfa-inscripcions')));
        }

        $franges = CFA_Inscripcions_DB::obtenir_franges_disponibles($calendari_id, $data);

        wp_send_json_success(array(
            'franges' => $franges,
        ));
    }

    /**
     * AJAX: Enviar inscripció
     */
    public function ajax_enviar_inscripcio() {
        check_ajax_referer('cfa_inscripcions_nonce', 'nonce');

        // 1. Verificació honeypot anti-spam (si té valor, és un bot)
        if (!empty($_POST['website_url'])) {
            // Silenciosament rebutjar però simular èxit per confondre bots
            wp_send_json_success(array(
                'message' => __('Inscripció realitzada correctament!', 'cfa-inscripcions'),
                'inscripcio_id' => 0,
            ));
            return;
        }

        // 2. Rate limiting per IP (màxim 10 inscripcions per hora per IP)
        $ip = $this->get_client_ip_for_rate_limit();
        $transient_key = 'cfa_rate_' . md5($ip);
        $submissions = get_transient($transient_key);

        if ($submissions === false) {
            $submissions = 0;
        }

        if ($submissions >= 10) {
            wp_send_json_error(array(
                'message' => __('Has superat el límit d\'inscripcions. Torna-ho a provar més tard.', 'cfa-inscripcions')
            ));
            return;
        }

        // Validar camps obligatoris
        $camps_obligatoris = array('curs_id', 'calendari_id', 'data_cita', 'hora_cita', 'nom', 'cognoms', 'dni', 'telefon', 'email');

        foreach ($camps_obligatoris as $camp) {
            if (empty($_POST[$camp])) {
                wp_send_json_error(array(
                    'message' => sprintf(__('El camp %s és obligatori', 'cfa-inscripcions'), $camp)
                ));
            }
        }

        $curs_id = absint($_POST['curs_id']);
        $calendari_id = absint($_POST['calendari_id']);
        $data_cita = sanitize_text_field($_POST['data_cita']);
        $hora_cita = sanitize_text_field($_POST['hora_cita']);

        // 3. Validar format de data (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_cita)) {
            wp_send_json_error(array('message' => __('Format de data invàlid', 'cfa-inscripcions')));
        }

        // 4. Validar format d'hora (HH:MM:SS o HH:MM)
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora_cita)) {
            wp_send_json_error(array('message' => __('Format d\'hora invàlid', 'cfa-inscripcions')));
        }

        // 5. Validar DNI/NIE espanyol
        $dni = strtoupper(sanitize_text_field($_POST['dni']));
        if (!$this->validar_dni_nie($dni)) {
            wp_send_json_error(array('message' => __('El DNI/NIE introduït no és vàlid', 'cfa-inscripcions')));
        }

        // 6. Validar email
        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('El correu electrònic no és vàlid', 'cfa-inscripcions')));
        }

        // 7. Validar telèfon (mínim 9 dígits)
        $telefon = preg_replace('/[^0-9+]/', '', $_POST['telefon']);
        if (strlen($telefon) < 9) {
            wp_send_json_error(array('message' => __('El telèfon ha de tenir almenys 9 dígits', 'cfa-inscripcions')));
        }

        // Validar que el curs existeix
        $curs = CFA_Inscripcions_DB::obtenir_curs($curs_id);
        if (!$curs) {
            wp_send_json_error(array('message' => __('Curs no vàlid', 'cfa-inscripcions')));
        }

        // Validar que hi ha places disponibles
        if (!CFA_Inscripcions_DB::te_places_disponibles($calendari_id, $data_cita, $hora_cita)) {
            wp_send_json_error(array('message' => __('Aquesta franja horària ja no està disponible. Si us plau, selecciona una altra.', 'cfa-inscripcions')));
        }

        // Preparar dades
        $dades = array(
            'curs_id'         => $curs_id,
            'calendari_id'    => $calendari_id,
            'data_cita'       => $data_cita,
            'hora_cita'       => $hora_cita,
            'nom'             => sanitize_text_field($_POST['nom']),
            'cognoms'         => sanitize_text_field($_POST['cognoms']),
            'dni'             => $dni,
            'telefon'         => $telefon,
            'email'           => $email,
            'estat'           => 'pendent',
        );

        // Crear inscripció
        $inscripcio_id = CFA_Inscripcions_DB::crear_inscripcio($dades);

        if (!$inscripcio_id) {
            wp_send_json_error(array('message' => __('Error en crear la inscripció. Torna-ho a provar.', 'cfa-inscripcions')));
        }

        // Crear reserva
        CFA_Inscripcions_DB::crear_reserva($calendari_id, $inscripcio_id, $data_cita, $hora_cita);

        // Incrementar comptador rate limit
        set_transient($transient_key, $submissions + 1, HOUR_IN_SECONDS);

        // Enviar emails
        $inscripcio = CFA_Inscripcions_DB::obtenir_inscripcio($inscripcio_id);
        CFA_Emails::enviar_email_nova_inscripcio($inscripcio, $curs);
        CFA_Emails::enviar_email_confirmacio_rebut($inscripcio, $curs);

        // Formatar data i hora per la resposta
        $data_formatada = date_i18n('l, j F Y', strtotime($data_cita));
        $hora_formatada = date('H:i', strtotime($hora_cita));

        wp_send_json_success(array(
            'message' => __('Inscripció realitzada correctament!', 'cfa-inscripcions'),
            'inscripcio_id' => $inscripcio_id,
            'detalls' => array(
                'curs' => $curs->nom,
                'data' => $data_formatada,
                'hora' => $hora_formatada,
                'nom' => $dades['nom'] . ' ' . $dades['cognoms'],
            ),
        ));
    }

    /**
     * Validar DNI/NIE espanyol
     *
     * @param string $document DNI o NIE a validar
     * @return bool True si és vàlid
     */
    private function validar_dni_nie($document) {
        $document = strtoupper(trim($document));

        // Longitud: 8-9 caràcters (NIE té lletra inicial)
        if (strlen($document) < 8 || strlen($document) > 9) {
            return false;
        }

        // NIE: Comença amb X, Y, Z
        $nie_prefixes = array('X' => 0, 'Y' => 1, 'Z' => 2);

        if (isset($nie_prefixes[$document[0]])) {
            // És NIE: substituïm la lletra inicial pel número corresponent
            $document = $nie_prefixes[$document[0]] . substr($document, 1);
        }

        // Format: 8 dígits + 1 lletra
        if (!preg_match('/^[0-9]{8}[A-Z]$/', $document)) {
            return false;
        }

        // Calcular lletra de control
        $numero = substr($document, 0, 8);
        $lletra = substr($document, -1);
        $lletres = 'TRWAGMYFPDXBNJZSQVHLCKE';
        $lletra_calculada = $lletres[$numero % 23];

        return $lletra === $lletra_calculada;
    }

    /**
     * Obtenir IP del client per rate limiting
     *
     * @return string IP del client
     */
    private function get_client_ip_for_rate_limit() {
        $ip = '';

        // Prioritzar headers de proxy si existeixen
        if (!empty($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Pot contenir múltiples IPs, agafem la primera
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $ip = '';
            }
        }

        // Fallback a REMOTE_ADDR
        if (empty($ip) && !empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field($ip);
    }
}
