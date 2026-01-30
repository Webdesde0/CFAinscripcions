<?php
/**
 * Gestió d'emails del plugin
 *
 * @package CFA_Inscripcions
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFA_Emails {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
    }

    /**
     * Establir tipus de contingut HTML per emails
     */
    public function set_html_content_type() {
        return 'text/html';
    }

    /**
     * Obtenir email de l'administrador
     */
    private static function get_admin_email() {
        $email = get_option('cfa_inscripcions_admin_email', '');
        if (empty($email)) {
            $email = get_option('admin_email');
        }
        return $email;
    }

    /**
     * Obtenir nom del centre
     */
    private static function get_nom_centre() {
        $nom = get_option('cfa_inscripcions_nom_centre', '');
        if (empty($nom)) {
            $nom = get_bloginfo('name');
        }
        return $nom;
    }

    /**
     * Plantilla base d'email
     */
    private static function get_email_template($contingut, $titol = '') {
        $nom_centre = self::get_nom_centre();
        $logo_url = get_option('cfa_inscripcions_logo_url', '');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($titol); ?></title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 0;
                    background-color: #f5f5f5;
                }
                .email-wrapper {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .email-container {
                    background-color: #ffffff;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    overflow: hidden;
                }
                .email-header {
                    background-color: #0073aa;
                    color: #ffffff;
                    padding: 30px;
                    text-align: center;
                }
                .email-header h1 {
                    margin: 0;
                    font-size: 24px;
                    font-weight: 600;
                }
                .email-header img {
                    max-width: 150px;
                    margin-bottom: 15px;
                }
                .email-body {
                    padding: 30px;
                }
                .email-body h2 {
                    color: #0073aa;
                    margin-top: 0;
                }
                .info-box {
                    background-color: #f8f9fa;
                    border-left: 4px solid #0073aa;
                    padding: 15px 20px;
                    margin: 20px 0;
                    border-radius: 0 4px 4px 0;
                }
                .info-box p {
                    margin: 5px 0;
                }
                .info-box strong {
                    color: #333;
                }
                .button {
                    display: inline-block;
                    background-color: #0073aa;
                    color: #ffffff !important;
                    padding: 12px 24px;
                    text-decoration: none;
                    border-radius: 4px;
                    margin-top: 15px;
                }
                .email-footer {
                    background-color: #f8f9fa;
                    padding: 20px 30px;
                    text-align: center;
                    font-size: 14px;
                    color: #666;
                }
                .email-footer p {
                    margin: 5px 0;
                }
                .status-pendent {
                    color: #856404;
                    background-color: #fff3cd;
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-weight: 500;
                }
                .status-confirmada {
                    color: #155724;
                    background-color: #d4edda;
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-weight: 500;
                }
            </style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="email-container">
                    <div class="email-header">
                        <?php if (!empty($logo_url)) : ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($nom_centre); ?>">
                        <?php endif; ?>
                        <h1><?php echo esc_html($nom_centre); ?></h1>
                    </div>
                    <div class="email-body">
                        <?php echo $contingut; ?>
                    </div>
                    <div class="email-footer">
                        <p><?php echo esc_html($nom_centre); ?></p>
                        <p><?php echo esc_html(get_bloginfo('url')); ?></p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Formatar data en català
     */
    private static function formatar_data($data) {
        $timestamp = strtotime($data);
        return date_i18n('l, j F Y', $timestamp);
    }

    /**
     * Formatar hora
     */
    private static function formatar_hora($hora) {
        return date('H:i', strtotime($hora)) . 'h';
    }

    /**
     * Enviar email de nova inscripció a l'administrador
     */
    public static function enviar_email_nova_inscripcio($inscripcio, $curs) {
        $admin_email = self::get_admin_email();
        $nom_centre = self::get_nom_centre();

        $assumpte = sprintf(
            __('[%s] Nova sol·licitud d\'inscripció - %s', 'cfa-inscripcions'),
            $nom_centre,
            $inscripcio->nom . ' ' . $inscripcio->cognoms
        );

        ob_start();
        ?>
        <h2><?php _e('Nova sol·licitud d\'inscripció', 'cfa-inscripcions'); ?></h2>
        <p><?php _e('S\'ha rebut una nova sol·licitud d\'inscripció amb les següents dades:', 'cfa-inscripcions'); ?></p>

        <div class="info-box">
            <p><strong><?php _e('Curs:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html($curs->nom); ?></p>
            <p><strong><?php _e('Data cita:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html(self::formatar_data($inscripcio->data_cita)); ?></p>
            <p><strong><?php _e('Hora:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html(self::formatar_hora($inscripcio->hora_cita)); ?></p>
            <p><strong><?php _e('Estat:', 'cfa-inscripcions'); ?></strong> <span class="status-pendent"><?php _e('Pendent de confirmació', 'cfa-inscripcions'); ?></span></p>
        </div>

        <h3><?php _e('Dades de l\'alumne', 'cfa-inscripcions'); ?></h3>
        <div class="info-box">
            <p><strong><?php _e('Nom:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html($inscripcio->nom . ' ' . $inscripcio->cognoms); ?></p>
            <p><strong><?php _e('DNI/NIE:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html($inscripcio->dni); ?></p>
            <?php if (!empty($inscripcio->data_naixement)) : ?>
                <p><strong><?php _e('Data naixement:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html(self::formatar_data($inscripcio->data_naixement)); ?></p>
            <?php endif; ?>
            <p><strong><?php _e('Telèfon:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html($inscripcio->telefon); ?></p>
            <p><strong><?php _e('Email:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html($inscripcio->email); ?></p>
            <?php if (!empty($inscripcio->adreca)) : ?>
                <p><strong><?php _e('Adreça:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html($inscripcio->adreca); ?></p>
            <?php endif; ?>
            <?php if (!empty($inscripcio->poblacio)) : ?>
                <p><strong><?php _e('Població:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html($inscripcio->poblacio); ?> <?php echo esc_html($inscripcio->codi_postal); ?></p>
            <?php endif; ?>
            <?php if (!empty($inscripcio->nivell_estudis)) : ?>
                <p><strong><?php _e('Nivell estudis:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html($inscripcio->nivell_estudis); ?></p>
            <?php endif; ?>
            <?php if (!empty($inscripcio->observacions)) : ?>
                <p><strong><?php _e('Observacions:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html($inscripcio->observacions); ?></p>
            <?php endif; ?>
        </div>

        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=cfa-inscripcions&action=veure&id=' . $inscripcio->id)); ?>" class="button">
                <?php _e('Veure inscripció al panell', 'cfa-inscripcions'); ?>
            </a>
        </p>
        <?php
        $contingut = ob_get_clean();

        $html = self::get_email_template($contingut, $assumpte);

        return wp_mail($admin_email, $assumpte, $html);
    }

    /**
     * Enviar email de confirmació de rebut a l'usuari
     */
    public static function enviar_email_confirmacio_rebut($inscripcio, $curs) {
        $nom_centre = self::get_nom_centre();

        $assumpte = sprintf(
            __('Sol·licitud d\'inscripció rebuda - %s', 'cfa-inscripcions'),
            $nom_centre
        );

        ob_start();
        ?>
        <h2><?php _e('Hem rebut la teva sol·licitud!', 'cfa-inscripcions'); ?></h2>
        <p><?php printf(__('Hola %s,', 'cfa-inscripcions'), esc_html($inscripcio->nom)); ?></p>
        <p><?php _e('Hem rebut correctament la teva sol·licitud d\'inscripció. A continuació et mostrem un resum:', 'cfa-inscripcions'); ?></p>

        <div class="info-box">
            <p><strong><?php _e('Curs:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html($curs->nom); ?></p>
            <p><strong><?php _e('Data cita:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html(self::formatar_data($inscripcio->data_cita)); ?></p>
            <p><strong><?php _e('Hora:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html(self::formatar_hora($inscripcio->hora_cita)); ?></p>
            <p><strong><?php _e('Estat:', 'cfa-inscripcions'); ?></strong> <span class="status-pendent"><?php _e('Pendent de confirmació', 'cfa-inscripcions'); ?></span></p>
        </div>

        <h3><?php _e('Què passa ara?', 'cfa-inscripcions'); ?></h3>
        <p><?php _e('El centre revisarà la teva sol·licitud i es posarà en contacte amb tu per confirmar la cita. Rebràs un altre correu quan la inscripció sigui confirmada.', 'cfa-inscripcions'); ?></p>

        <p><?php _e('Si tens qualsevol dubte, no dubtis en contactar amb nosaltres.', 'cfa-inscripcions'); ?></p>

        <p><?php _e('Gràcies per confiar en nosaltres!', 'cfa-inscripcions'); ?></p>
        <?php
        $contingut = ob_get_clean();

        $html = self::get_email_template($contingut, $assumpte);

        return wp_mail($inscripcio->email, $assumpte, $html);
    }

    /**
     * Enviar email de confirmació de cita a l'usuari
     */
    public static function enviar_email_confirmacio_cita($inscripcio, $curs) {
        $nom_centre = self::get_nom_centre();

        $assumpte = sprintf(
            __('Cita confirmada - %s', 'cfa-inscripcions'),
            $nom_centre
        );

        ob_start();
        ?>
        <h2><?php _e('La teva cita ha estat confirmada!', 'cfa-inscripcions'); ?></h2>
        <p><?php printf(__('Hola %s,', 'cfa-inscripcions'), esc_html($inscripcio->nom)); ?></p>
        <p><?php _e('Ens complau comunicar-te que la teva cita d\'inscripció ha estat confirmada.', 'cfa-inscripcions'); ?></p>

        <div class="info-box">
            <p><strong><?php _e('Curs:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html($curs->nom); ?></p>
            <p><strong><?php _e('Data cita:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html(self::formatar_data($inscripcio->data_cita)); ?></p>
            <p><strong><?php _e('Hora:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html(self::formatar_hora($inscripcio->hora_cita)); ?></p>
            <p><strong><?php _e('Estat:', 'cfa-inscripcions'); ?></strong> <span class="status-confirmada"><?php _e('Confirmada', 'cfa-inscripcions'); ?></span></p>
        </div>

        <h3><?php _e('Recordatori', 'cfa-inscripcions'); ?></h3>
        <p><?php _e('Recorda portar:', 'cfa-inscripcions'); ?></p>
        <ul>
            <li><?php _e('Document d\'identitat (DNI/NIE)', 'cfa-inscripcions'); ?></li>
            <li><?php _e('Qualsevol documentació rellevant', 'cfa-inscripcions'); ?></li>
        </ul>

        <p><?php _e('Si no pots assistir a la cita, si us plau, comunica-ho el més aviat possible perquè puguem alliberar la plaça.', 'cfa-inscripcions'); ?></p>

        <p><?php _e('T\'esperem!', 'cfa-inscripcions'); ?></p>
        <?php
        $contingut = ob_get_clean();

        $html = self::get_email_template($contingut, $assumpte);

        return wp_mail($inscripcio->email, $assumpte, $html);
    }

    /**
     * Enviar email de cancel·lació a l'usuari
     */
    public static function enviar_email_cancel_lacio($inscripcio, $curs, $motiu = '') {
        $nom_centre = self::get_nom_centre();

        $assumpte = sprintf(
            __('Inscripció cancel·lada - %s', 'cfa-inscripcions'),
            $nom_centre
        );

        ob_start();
        ?>
        <h2><?php _e('Inscripció cancel·lada', 'cfa-inscripcions'); ?></h2>
        <p><?php printf(__('Hola %s,', 'cfa-inscripcions'), esc_html($inscripcio->nom)); ?></p>
        <p><?php _e('Et comuniquem que la teva inscripció ha estat cancel·lada.', 'cfa-inscripcions'); ?></p>

        <div class="info-box">
            <p><strong><?php _e('Curs:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html($curs->nom); ?></p>
            <p><strong><?php _e('Data cita:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html(self::formatar_data($inscripcio->data_cita)); ?></p>
            <p><strong><?php _e('Hora:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html(self::formatar_hora($inscripcio->hora_cita)); ?></p>
        </div>

        <?php if (!empty($motiu)) : ?>
            <p><strong><?php _e('Motiu:', 'cfa-inscripcions'); ?></strong> <?php echo esc_html($motiu); ?></p>
        <?php endif; ?>

        <p><?php _e('Si vols fer una nova inscripció, pots fer-ho a través de la nostra web.', 'cfa-inscripcions'); ?></p>

        <p><?php _e('Disculpa les molèsties.', 'cfa-inscripcions'); ?></p>
        <?php
        $contingut = ob_get_clean();

        $html = self::get_email_template($contingut, $assumpte);

        return wp_mail($inscripcio->email, $assumpte, $html);
    }
}
