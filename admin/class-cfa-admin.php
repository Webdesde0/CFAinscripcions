<?php
/**
 * Panell d'administració del plugin
 *
 * @package CFA_Inscripcions
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFA_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_admin_actions'));

        // AJAX per admin
        add_action('wp_ajax_cfa_confirmar_inscripcio', array($this, 'ajax_confirmar_inscripcio'));
        add_action('wp_ajax_cfa_cancel_lar_inscripcio', array($this, 'ajax_cancel_lar_inscripcio'));
        add_action('wp_ajax_cfa_eliminar_inscripcio', array($this, 'ajax_eliminar_inscripcio'));
        add_action('wp_ajax_cfa_editar_inscripcio', array($this, 'ajax_editar_inscripcio'));
        add_action('wp_ajax_cfa_guardar_calendari', array($this, 'ajax_guardar_calendari'));
        add_action('wp_ajax_cfa_eliminar_calendari', array($this, 'ajax_eliminar_calendari'));
        add_action('wp_ajax_cfa_guardar_horaris', array($this, 'ajax_guardar_horaris'));
        add_action('wp_ajax_cfa_afegir_excepcio', array($this, 'ajax_afegir_excepcio'));
        add_action('wp_ajax_cfa_eliminar_excepcio', array($this, 'ajax_eliminar_excepcio'));
        add_action('wp_ajax_cfa_guardar_curs', array($this, 'ajax_guardar_curs'));
        add_action('wp_ajax_cfa_eliminar_curs', array($this, 'ajax_eliminar_curs'));
    }

    /**
     * Afegir menú d'administració
     */
    public function add_admin_menu() {
        // Menú principal - visible per professors i admins
        add_menu_page(
            __('CFA Inscripcions', 'cfa-inscripcions'),
            __('Inscripcions', 'cfa-inscripcions'),
            'cfa_veure_inscripcions',
            'cfa-inscripcions',
            array($this, 'render_page_inscripcions'),
            'dashicons-clipboard',
            30
        );

        // Submenú inscripcions - professors i admins
        add_submenu_page(
            'cfa-inscripcions',
            __('Inscripcions', 'cfa-inscripcions'),
            __('Inscripcions', 'cfa-inscripcions'),
            'cfa_veure_inscripcions',
            'cfa-inscripcions',
            array($this, 'render_page_inscripcions')
        );

        // Submenú cursos - només admins
        add_submenu_page(
            'cfa-inscripcions',
            __('Cursos', 'cfa-inscripcions'),
            __('Cursos', 'cfa-inscripcions'),
            'cfa_gestionar_calendaris',
            'cfa-cursos',
            array($this, 'render_page_cursos')
        );

        // Submenú calendaris - només admins
        add_submenu_page(
            'cfa-inscripcions',
            __('Calendaris', 'cfa-inscripcions'),
            __('Calendaris', 'cfa-inscripcions'),
            'cfa_gestionar_calendaris',
            'cfa-calendaris',
            array($this, 'render_page_calendaris')
        );

        // Submenú configuració - només admins
        add_submenu_page(
            'cfa-inscripcions',
            __('Configuració', 'cfa-inscripcions'),
            __('Configuració', 'cfa-inscripcions'),
            'cfa_gestionar_configuracio',
            'cfa-configuracio',
            array($this, 'render_page_configuracio')
        );
    }

    /**
     * Gestionar accions d'admin
     */
    public function handle_admin_actions() {
        // Guardar configuració
        if (isset($_POST['cfa_guardar_configuracio']) && check_admin_referer('cfa_configuracio_nonce')) {
            $this->guardar_configuracio();
        }
    }

    // =========================================================================
    // PÀGINA D'INSCRIPCIONS
    // =========================================================================

    /**
     * Renderitzar pàgina d'inscripcions
     */
    public function render_page_inscripcions() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'llistat';

        switch ($action) {
            case 'veure':
                $this->render_inscripcio_detall();
                break;
            case 'editar':
                $this->render_inscripcio_editar();
                break;
            default:
                $this->render_inscripcions_llistat();
                break;
        }
    }

    /**
     * Renderitzar llistat d'inscripcions
     */
    private function render_inscripcions_llistat() {
        // Filtres
        $estat = isset($_GET['estat']) ? sanitize_text_field($_GET['estat']) : '';
        $curs_id = isset($_GET['curs_id']) ? absint($_GET['curs_id']) : 0;
        $cerca = isset($_GET['cerca']) ? sanitize_text_field($_GET['cerca']) : '';
        $pag = isset($_GET['pag']) ? max(1, absint($_GET['pag'])) : 1;
        $per_pagina = 20;

        // Comprovar si és professor i filtrar per cursos assignats
        $curs_ids_professor = array();
        $es_professor = current_user_can('cfa_professor') && !current_user_can('manage_options');

        if ($es_professor) {
            $current_user_id = get_current_user_id();
            $curs_ids_professor = CFA_Inscripcions_DB::obtenir_ids_cursos_professor($current_user_id);

            // Si no té cursos assignats, mostrar missatge
            if (empty($curs_ids_professor)) {
                echo '<div class="wrap"><div class="notice notice-warning"><p>' .
                     __('No tens cap curs assignat. Contacta amb l\'administrador.', 'cfa-inscripcions') .
                     '</p></div></div>';
                return;
            }
        }

        $args = array(
            'estat' => $estat,
            'curs_id' => $curs_id,
            'curs_ids' => $curs_ids_professor, // Filtrar per cursos del professor
            'cerca' => $cerca,
            'limit' => $per_pagina,
            'offset' => ($pag - 1) * $per_pagina,
        );

        $inscripcions = CFA_Inscripcions_DB::obtenir_inscripcions($args);
        $total = CFA_Inscripcions_DB::comptar_inscripcions($args);
        $total_pagines = ceil($total / $per_pagina);

        // Comptadors per estat (amb filtre de professor si cal)
        $args_comptador = array('curs_ids' => $curs_ids_professor);
        $total_pendents = CFA_Inscripcions_DB::comptar_inscripcions(array_merge($args_comptador, array('estat' => 'pendent')));
        $total_confirmades = CFA_Inscripcions_DB::comptar_inscripcions(array_merge($args_comptador, array('estat' => 'confirmada')));
        $total_cancel_lades = CFA_Inscripcions_DB::comptar_inscripcions(array_merge($args_comptador, array('estat' => 'cancel_lada')));

        // Obtenir cursos per filtre (només els del professor si cal)
        if ($es_professor) {
            $cursos = CFA_Inscripcions_DB::obtenir_cursos(array('professor_id' => get_current_user_id(), 'actius_nomes' => true));
        } else {
            $cursos = CFA_Inscripcions_DB::obtenir_cursos(array('actius_nomes' => true));
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Inscripcions', 'cfa-inscripcions'); ?></h1>

            <!-- Filtres per estat -->
            <ul class="subsubsub">
                <li>
                    <a href="<?php echo admin_url('admin.php?page=cfa-inscripcions'); ?>"
                       class="<?php echo empty($estat) ? 'current' : ''; ?>">
                        <?php _e('Totes', 'cfa-inscripcions'); ?>
                        <span class="count">(<?php echo $total_pendents + $total_confirmades + $total_cancel_lades; ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo admin_url('admin.php?page=cfa-inscripcions&estat=pendent'); ?>"
                       class="<?php echo $estat === 'pendent' ? 'current' : ''; ?>">
                        <?php _e('Pendents', 'cfa-inscripcions'); ?>
                        <span class="count">(<?php echo $total_pendents; ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo admin_url('admin.php?page=cfa-inscripcions&estat=confirmada'); ?>"
                       class="<?php echo $estat === 'confirmada' ? 'current' : ''; ?>">
                        <?php _e('Confirmades', 'cfa-inscripcions'); ?>
                        <span class="count">(<?php echo $total_confirmades; ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo admin_url('admin.php?page=cfa-inscripcions&estat=cancel_lada'); ?>"
                       class="<?php echo $estat === 'cancel_lada' ? 'current' : ''; ?>">
                        <?php _e('Cancel·lades', 'cfa-inscripcions'); ?>
                        <span class="count">(<?php echo $total_cancel_lades; ?>)</span>
                    </a>
                </li>
            </ul>

            <!-- Formulari de cerca i filtres -->
            <form method="get" class="cfa-filtres-form">
                <input type="hidden" name="page" value="cfa-inscripcions">
                <?php if ($estat) : ?>
                    <input type="hidden" name="estat" value="<?php echo esc_attr($estat); ?>">
                <?php endif; ?>

                <p class="search-box">
                    <select name="curs_id">
                        <option value=""><?php _e('Tots els cursos', 'cfa-inscripcions'); ?></option>
                        <?php foreach ($cursos as $curs) : ?>
                            <option value="<?php echo esc_attr($curs->id); ?>" <?php selected($curs_id, $curs->id); ?>>
                                <?php echo esc_html($curs->nom); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="search" name="cerca" value="<?php echo esc_attr($cerca); ?>"
                           placeholder="<?php _e('Cercar per nom, email o DNI...', 'cfa-inscripcions'); ?>">

                    <input type="submit" class="button" value="<?php _e('Filtrar', 'cfa-inscripcions'); ?>">
                </p>
            </form>

            <!-- Taula d'inscripcions -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="column-nom"><?php _e('Nom', 'cfa-inscripcions'); ?></th>
                        <th scope="col" class="column-curs"><?php _e('Curs', 'cfa-inscripcions'); ?></th>
                        <th scope="col" class="column-data"><?php _e('Data cita', 'cfa-inscripcions'); ?></th>
                        <th scope="col" class="column-hora"><?php _e('Hora', 'cfa-inscripcions'); ?></th>
                        <th scope="col" class="column-contacte"><?php _e('Contacte', 'cfa-inscripcions'); ?></th>
                        <th scope="col" class="column-estat"><?php _e('Estat', 'cfa-inscripcions'); ?></th>
                        <th scope="col" class="column-accions"><?php _e('Accions', 'cfa-inscripcions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inscripcions)) : ?>
                        <tr>
                            <td colspan="7"><?php _e('No s\'han trobat inscripcions.', 'cfa-inscripcions'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($inscripcions as $inscripcio) : ?>
                            <?php
                            $curs = CFA_Inscripcions_DB::obtenir_curs($inscripcio->curs_id);
                            $nom_curs = $curs ? $curs->nom : __('Curs eliminat', 'cfa-inscripcions');
                            ?>
                            <tr data-id="<?php echo esc_attr($inscripcio->id); ?>">
                                <td class="column-nom">
                                    <strong>
                                        <a href="<?php echo admin_url('admin.php?page=cfa-inscripcions&action=veure&id=' . $inscripcio->id); ?>">
                                            <?php echo esc_html($inscripcio->nom . ' ' . $inscripcio->cognoms); ?>
                                        </a>
                                    </strong>
                                    <br>
                                    <small><?php echo esc_html($inscripcio->dni); ?></small>
                                </td>
                                <td class="column-curs"><?php echo esc_html($nom_curs); ?></td>
                                <td class="column-data">
                                    <?php echo esc_html(date_i18n('d/m/Y', strtotime($inscripcio->data_cita))); ?>
                                </td>
                                <td class="column-hora">
                                    <?php echo esc_html(date('H:i', strtotime($inscripcio->hora_cita))); ?>h
                                </td>
                                <td class="column-contacte">
                                    <a href="mailto:<?php echo esc_attr($inscripcio->email); ?>">
                                        <?php echo esc_html($inscripcio->email); ?>
                                    </a>
                                    <br>
                                    <a href="tel:<?php echo esc_attr($inscripcio->telefon); ?>">
                                        <?php echo esc_html($inscripcio->telefon); ?>
                                    </a>
                                </td>
                                <?php $estat_actual = !empty($inscripcio->estat) ? $inscripcio->estat : 'pendent'; ?>
                                <td class="column-estat">
                                    <?php $this->render_estat_badge($estat_actual); ?>
                                </td>
                                <td class="column-accions">
                                    <a href="<?php echo admin_url('admin.php?page=cfa-inscripcions&action=veure&id=' . $inscripcio->id); ?>"
                                       class="button button-small">
                                        <?php _e('Veure', 'cfa-inscripcions'); ?>
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=cfa-inscripcions&action=editar&id=' . $inscripcio->id); ?>"
                                       class="button button-small">
                                        <?php _e('Editar', 'cfa-inscripcions'); ?>
                                    </a>
                                    <?php if ($estat_actual === 'pendent') : ?>
                                        <button type="button" class="button button-small button-primary cfa-btn-confirmar"
                                                data-id="<?php echo esc_attr($inscripcio->id); ?>">
                                            <?php _e('Confirmar', 'cfa-inscripcions'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($estat_actual !== 'cancel_lada') : ?>
                                        <button type="button" class="button button-small cfa-btn-cancel-lar"
                                                data-id="<?php echo esc_attr($inscripcio->id); ?>">
                                            <?php _e('Cancel·lar', 'cfa-inscripcions'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Paginació -->
            <?php if ($total_pagines > 1) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(_n('%s element', '%s elements', $total, 'cfa-inscripcions'), number_format_i18n($total)); ?>
                        </span>
                        <span class="pagination-links">
                            <?php
                            $base_url = admin_url('admin.php?page=cfa-inscripcions');
                            if ($estat) $base_url .= '&estat=' . $estat;
                            if ($curs_id) $base_url .= '&curs_id=' . $curs_id;
                            if ($cerca) $base_url .= '&cerca=' . urlencode($cerca);

                            if ($pag > 1) {
                                echo '<a class="prev-page button" href="' . esc_url($base_url . '&pag=' . ($pag - 1)) . '">‹</a>';
                            }

                            echo '<span class="paging-input">' . $pag . ' / ' . $total_pagines . '</span>';

                            if ($pag < $total_pagines) {
                                echo '<a class="next-page button" href="' . esc_url($base_url . '&pag=' . ($pag + 1)) . '">›</a>';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Renderitzar detall d'una inscripció
     */
    private function render_inscripcio_detall() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $inscripcio = CFA_Inscripcions_DB::obtenir_inscripcio($id);

        if (!$inscripcio) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' .
                 __('Inscripció no trobada.', 'cfa-inscripcions') .
                 '</p></div></div>';
            return;
        }

        $curs = CFA_Inscripcions_DB::obtenir_curs($inscripcio->curs_id);
        $nom_curs = $curs ? $curs->nom : __('Curs eliminat', 'cfa-inscripcions');
        ?>
        <div class="wrap">
            <h1>
                <a href="<?php echo admin_url('admin.php?page=cfa-inscripcions'); ?>" class="page-title-action">
                    ← <?php _e('Tornar al llistat', 'cfa-inscripcions'); ?>
                </a>
                <?php _e('Detall de la inscripció', 'cfa-inscripcions'); ?>
            </h1>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <!-- Columna principal -->
                    <div id="post-body-content">
                        <div class="postbox">
                            <h2 class="hndle"><?php _e('Dades de l\'alumne', 'cfa-inscripcions'); ?></h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th><?php _e('Nom complet', 'cfa-inscripcions'); ?></th>
                                        <td><strong><?php echo esc_html($inscripcio->nom . ' ' . $inscripcio->cognoms); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th><?php _e('DNI/NIE', 'cfa-inscripcions'); ?></th>
                                        <td><?php echo esc_html($inscripcio->dni); ?></td>
                                    </tr>
                                    <?php if (!empty($inscripcio->data_naixement)) : ?>
                                    <tr>
                                        <th><?php _e('Data de naixement', 'cfa-inscripcions'); ?></th>
                                        <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($inscripcio->data_naixement))); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th><?php _e('Telèfon', 'cfa-inscripcions'); ?></th>
                                        <td>
                                            <a href="tel:<?php echo esc_attr($inscripcio->telefon); ?>">
                                                <?php echo esc_html($inscripcio->telefon); ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><?php _e('Email', 'cfa-inscripcions'); ?></th>
                                        <td>
                                            <a href="mailto:<?php echo esc_attr($inscripcio->email); ?>">
                                                <?php echo esc_html($inscripcio->email); ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php if (!empty($inscripcio->adreca)) : ?>
                                    <tr>
                                        <th><?php _e('Adreça', 'cfa-inscripcions'); ?></th>
                                        <td>
                                            <?php echo esc_html($inscripcio->adreca); ?>
                                            <?php if (!empty($inscripcio->poblacio)) : ?>
                                                <br><?php echo esc_html($inscripcio->codi_postal . ' ' . $inscripcio->poblacio); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($inscripcio->nivell_estudis)) : ?>
                                    <tr>
                                        <th><?php _e('Nivell d\'estudis', 'cfa-inscripcions'); ?></th>
                                        <td><?php echo esc_html($inscripcio->nivell_estudis); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($inscripcio->observacions)) : ?>
                                    <tr>
                                        <th><?php _e('Observacions', 'cfa-inscripcions'); ?></th>
                                        <td><?php echo nl2br(esc_html($inscripcio->observacions)); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Barra lateral -->
                    <?php $estat_actual = !empty($inscripcio->estat) ? $inscripcio->estat : 'pendent'; ?>
                    <div id="postbox-container-1" class="postbox-container">
                        <div class="postbox">
                            <h2 class="hndle"><?php _e('Informació de la cita', 'cfa-inscripcions'); ?></h2>
                            <div class="inside">
                                <p><strong><?php _e('Estat:', 'cfa-inscripcions'); ?></strong><br>
                                    <?php $this->render_estat_badge($estat_actual); ?>
                                </p>
                                <p><strong><?php _e('Curs:', 'cfa-inscripcions'); ?></strong><br>
                                    <?php echo esc_html($nom_curs); ?>
                                </p>
                                <p><strong><?php _e('Data:', 'cfa-inscripcions'); ?></strong><br>
                                    <?php echo esc_html(date_i18n('l, d F Y', strtotime($inscripcio->data_cita))); ?>
                                </p>
                                <p><strong><?php _e('Hora:', 'cfa-inscripcions'); ?></strong><br>
                                    <?php echo esc_html(date('H:i', strtotime($inscripcio->hora_cita))); ?>h
                                </p>
                                <hr>
                                <p><small>
                                    <?php _e('Creat:', 'cfa-inscripcions'); ?>
                                    <?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($inscripcio->data_creacio))); ?>
                                </small></p>
                            </div>
                        </div>

                        <div class="postbox">
                            <h2 class="hndle"><?php _e('Accions', 'cfa-inscripcions'); ?></h2>
                            <div class="inside">
                                <?php if ($estat_actual === 'pendent') : ?>
                                    <p>
                                        <button type="button" class="button button-primary button-large cfa-btn-confirmar"
                                                data-id="<?php echo esc_attr($inscripcio->id); ?>" style="width:100%;">
                                            <?php _e('Confirmar cita', 'cfa-inscripcions'); ?>
                                        </button>
                                    </p>
                                <?php endif; ?>
                                <p>
                                    <a href="<?php echo admin_url('admin.php?page=cfa-inscripcions&action=editar&id=' . $inscripcio->id); ?>"
                                       class="button button-large" style="width:100%; text-align:center;">
                                        <?php _e('Editar inscripció', 'cfa-inscripcions'); ?>
                                    </a>
                                </p>
                                <?php if ($estat_actual !== 'cancel_lada') : ?>
                                    <p>
                                        <button type="button" class="button cfa-btn-cancel-lar"
                                                data-id="<?php echo esc_attr($inscripcio->id); ?>" style="width:100%;">
                                            <?php _e('Cancel·lar inscripció', 'cfa-inscripcions'); ?>
                                        </button>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renderitzar formulari d'edició d'inscripció
     */
    private function render_inscripcio_editar() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $inscripcio = CFA_Inscripcions_DB::obtenir_inscripcio($id);

        if (!$inscripcio) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' .
                 __('Inscripció no trobada.', 'cfa-inscripcions') .
                 '</p></div></div>';
            return;
        }

        // Obtenir cursos per al selector
        $cursos = CFA_Inscripcions_DB::obtenir_cursos(array('actius_nomes' => true));
        $curs_actual = CFA_Inscripcions_DB::obtenir_curs($inscripcio->curs_id);
        $nom_curs = $curs_actual ? $curs_actual->nom : __('Curs eliminat', 'cfa-inscripcions');
        $estat_actual = !empty($inscripcio->estat) ? $inscripcio->estat : 'pendent';
        ?>
        <div class="wrap">
            <h1>
                <a href="<?php echo admin_url('admin.php?page=cfa-inscripcions&action=veure&id=' . $id); ?>" class="page-title-action">
                    ← <?php _e('Tornar al detall', 'cfa-inscripcions'); ?>
                </a>
                <?php _e('Editar inscripció', 'cfa-inscripcions'); ?>
            </h1>

            <form id="cfa-editar-inscripcio-form" method="post">
                <input type="hidden" name="inscripcio_id" value="<?php echo esc_attr($id); ?>">
                <?php wp_nonce_field('cfa_editar_inscripcio_nonce', 'nonce'); ?>

                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        <!-- Columna principal -->
                        <div id="post-body-content">
                            <div class="postbox">
                                <h2 class="hndle"><?php _e('Dades de l\'alumne', 'cfa-inscripcions'); ?></h2>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th><label for="nom"><?php _e('Nom', 'cfa-inscripcions'); ?></label></th>
                                            <td><input type="text" name="nom" id="nom" class="regular-text"
                                                       value="<?php echo esc_attr($inscripcio->nom); ?>" required></td>
                                        </tr>
                                        <tr>
                                            <th><label for="cognoms"><?php _e('Cognoms', 'cfa-inscripcions'); ?></label></th>
                                            <td><input type="text" name="cognoms" id="cognoms" class="regular-text"
                                                       value="<?php echo esc_attr($inscripcio->cognoms); ?>" required></td>
                                        </tr>
                                        <tr>
                                            <th><label for="dni"><?php _e('DNI/NIE', 'cfa-inscripcions'); ?></label></th>
                                            <td><input type="text" name="dni" id="dni" class="regular-text"
                                                       value="<?php echo esc_attr($inscripcio->dni); ?>" required></td>
                                        </tr>
                                        <tr>
                                            <th><label for="data_naixement"><?php _e('Data de naixement', 'cfa-inscripcions'); ?></label></th>
                                            <td><input type="date" name="data_naixement" id="data_naixement"
                                                       value="<?php echo esc_attr($inscripcio->data_naixement); ?>"></td>
                                        </tr>
                                        <tr>
                                            <th><label for="telefon"><?php _e('Telèfon', 'cfa-inscripcions'); ?></label></th>
                                            <td><input type="tel" name="telefon" id="telefon" class="regular-text"
                                                       value="<?php echo esc_attr($inscripcio->telefon); ?>" required></td>
                                        </tr>
                                        <tr>
                                            <th><label for="email"><?php _e('Email', 'cfa-inscripcions'); ?></label></th>
                                            <td><input type="email" name="email" id="email" class="regular-text"
                                                       value="<?php echo esc_attr($inscripcio->email); ?>" required></td>
                                        </tr>
                                        <tr>
                                            <th><label for="adreca"><?php _e('Adreça', 'cfa-inscripcions'); ?></label></th>
                                            <td><input type="text" name="adreca" id="adreca" class="large-text"
                                                       value="<?php echo esc_attr($inscripcio->adreca); ?>"></td>
                                        </tr>
                                        <tr>
                                            <th><label for="poblacio"><?php _e('Població', 'cfa-inscripcions'); ?></label></th>
                                            <td><input type="text" name="poblacio" id="poblacio" class="regular-text"
                                                       value="<?php echo esc_attr($inscripcio->poblacio); ?>"></td>
                                        </tr>
                                        <tr>
                                            <th><label for="codi_postal"><?php _e('Codi postal', 'cfa-inscripcions'); ?></label></th>
                                            <td><input type="text" name="codi_postal" id="codi_postal" class="small-text"
                                                       value="<?php echo esc_attr($inscripcio->codi_postal); ?>"></td>
                                        </tr>
                                        <tr>
                                            <th><label for="nivell_estudis"><?php _e('Nivell d\'estudis', 'cfa-inscripcions'); ?></label></th>
                                            <td><input type="text" name="nivell_estudis" id="nivell_estudis" class="regular-text"
                                                       value="<?php echo esc_attr($inscripcio->nivell_estudis); ?>"></td>
                                        </tr>
                                        <tr>
                                            <th><label for="observacions"><?php _e('Observacions', 'cfa-inscripcions'); ?></label></th>
                                            <td><textarea name="observacions" id="observacions" rows="4" class="large-text"><?php
                                                echo esc_textarea($inscripcio->observacions); ?></textarea></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Barra lateral -->
                        <div id="postbox-container-1" class="postbox-container">
                            <div class="postbox">
                                <h2 class="hndle"><?php _e('Informació de la cita', 'cfa-inscripcions'); ?></h2>
                                <div class="inside">
                                    <p>
                                        <label for="curs_id"><strong><?php _e('Curs:', 'cfa-inscripcions'); ?></strong></label><br>
                                        <select name="curs_id" id="curs_id" style="width:100%;">
                                            <?php foreach ($cursos as $curs) : ?>
                                                <option value="<?php echo esc_attr($curs->id); ?>"
                                                    <?php selected($inscripcio->curs_id, $curs->id); ?>>
                                                    <?php echo esc_html($curs->nom); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </p>
                                    <p>
                                        <label for="data_cita"><strong><?php _e('Data:', 'cfa-inscripcions'); ?></strong></label><br>
                                        <input type="date" name="data_cita" id="data_cita"
                                               value="<?php echo esc_attr($inscripcio->data_cita); ?>" style="width:100%;">
                                    </p>
                                    <p>
                                        <label for="hora_cita"><strong><?php _e('Hora:', 'cfa-inscripcions'); ?></strong></label><br>
                                        <input type="time" name="hora_cita" id="hora_cita"
                                               value="<?php echo esc_attr(date('H:i', strtotime($inscripcio->hora_cita))); ?>" style="width:100%;">
                                    </p>
                                    <p>
                                        <label for="estat"><strong><?php _e('Estat:', 'cfa-inscripcions'); ?></strong></label><br>
                                        <select name="estat" id="estat" style="width:100%;">
                                            <option value="pendent" <?php selected($estat_actual, 'pendent'); ?>>
                                                <?php _e('Pendent', 'cfa-inscripcions'); ?>
                                            </option>
                                            <option value="confirmada" <?php selected($estat_actual, 'confirmada'); ?>>
                                                <?php _e('Confirmada', 'cfa-inscripcions'); ?>
                                            </option>
                                            <option value="cancel_lada" <?php selected($estat_actual, 'cancel_lada'); ?>>
                                                <?php _e('Cancel·lada', 'cfa-inscripcions'); ?>
                                            </option>
                                        </select>
                                    </p>
                                </div>
                            </div>

                            <div class="postbox">
                                <h2 class="hndle"><?php _e('Guardar', 'cfa-inscripcions'); ?></h2>
                                <div class="inside">
                                    <p>
                                        <button type="submit" class="button button-primary button-large" style="width:100%;">
                                            <?php _e('Guardar canvis', 'cfa-inscripcions'); ?>
                                        </button>
                                    </p>
                                    <p>
                                        <a href="<?php echo admin_url('admin.php?page=cfa-inscripcions&action=veure&id=' . $id); ?>"
                                           class="button" style="width:100%; text-align:center;">
                                            <?php _e('Cancel·lar', 'cfa-inscripcions'); ?>
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Renderitzar badge d'estat
     */
    private function render_estat_badge($estat) {
        $classes = array(
            'pendent' => 'cfa-badge cfa-badge-warning',
            'confirmada' => 'cfa-badge cfa-badge-success',
            'cancel_lada' => 'cfa-badge cfa-badge-error',
        );

        $texts = array(
            'pendent' => __('Pendent', 'cfa-inscripcions'),
            'confirmada' => __('Confirmada', 'cfa-inscripcions'),
            'cancel_lada' => __('Cancel·lada', 'cfa-inscripcions'),
        );

        $class = isset($classes[$estat]) ? $classes[$estat] : 'cfa-badge';
        $text = isset($texts[$estat]) ? $texts[$estat] : $estat;

        echo '<span class="' . esc_attr($class) . '">' . esc_html($text) . '</span>';
    }

    // =========================================================================
    // PÀGINA DE CALENDARIS
    // =========================================================================

    /**
     * Renderitzar pàgina de calendaris
     */
    public function render_page_calendaris() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'llistat';

        switch ($action) {
            case 'editar':
                $this->render_calendari_form();
                break;
            case 'nou':
                $this->render_calendari_form();
                break;
            case 'horaris':
                $this->render_calendari_horaris();
                break;
            default:
                $this->render_calendaris_llistat();
                break;
        }
    }

    /**
     * Renderitzar llistat de calendaris
     */
    private function render_calendaris_llistat() {
        $calendaris = CFA_Inscripcions_DB::obtenir_calendaris();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Calendaris', 'cfa-inscripcions'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=cfa-calendaris&action=nou'); ?>" class="page-title-action">
                <?php _e('Afegir nou', 'cfa-inscripcions'); ?>
            </a>

            <p class="description">
                <?php _e('Els calendaris defineixen els horaris disponibles per a les inscripcions. Cada curs pot tenir assignat un calendari.', 'cfa-inscripcions'); ?>
            </p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php _e('Nom', 'cfa-inscripcions'); ?></th>
                        <th scope="col"><?php _e('Places/franja', 'cfa-inscripcions'); ?></th>
                        <th scope="col"><?php _e('Plaç màxim', 'cfa-inscripcions'); ?></th>
                        <th scope="col"><?php _e('Estat', 'cfa-inscripcions'); ?></th>
                        <th scope="col"><?php _e('Accions', 'cfa-inscripcions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($calendaris)) : ?>
                        <tr>
                            <td colspan="5"><?php _e('No hi ha calendaris. Crea\'n un per començar.', 'cfa-inscripcions'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($calendaris as $cal) : ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo admin_url('admin.php?page=cfa-calendaris&action=editar&id=' . $cal->id); ?>">
                                            <?php echo esc_html($cal->nom); ?>
                                        </a>
                                    </strong>
                                    <?php if (!empty($cal->descripcio)) : ?>
                                        <br><small><?php echo esc_html($cal->descripcio); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($cal->places_per_franja); ?></td>
                                <td><?php printf(__('%d dies', 'cfa-inscripcions'), $cal->plac_maxim_dies); ?></td>
                                <td>
                                    <?php if ($cal->actiu) : ?>
                                        <span class="cfa-badge cfa-badge-success"><?php _e('Actiu', 'cfa-inscripcions'); ?></span>
                                    <?php else : ?>
                                        <span class="cfa-badge cfa-badge-secondary"><?php _e('Inactiu', 'cfa-inscripcions'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=cfa-calendaris&action=horaris&id=' . $cal->id); ?>"
                                       class="button button-small">
                                        <?php _e('Horaris', 'cfa-inscripcions'); ?>
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=cfa-calendaris&action=editar&id=' . $cal->id); ?>"
                                       class="button button-small">
                                        <?php _e('Editar', 'cfa-inscripcions'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Renderitzar formulari de calendari
     */
    private function render_calendari_form() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $calendari = $id ? CFA_Inscripcions_DB::obtenir_calendari($id) : null;

        $nom = $calendari ? $calendari->nom : '';
        $descripcio = $calendari ? $calendari->descripcio : '';
        $places = $calendari ? $calendari->places_per_franja : 1;
        $plac = $calendari ? $calendari->plac_maxim_dies : 90;
        $actiu = $calendari ? $calendari->actiu : 1;
        ?>
        <div class="wrap">
            <h1>
                <a href="<?php echo admin_url('admin.php?page=cfa-calendaris'); ?>" class="page-title-action">
                    ← <?php _e('Tornar', 'cfa-inscripcions'); ?>
                </a>
                <?php echo $id ? __('Editar calendari', 'cfa-inscripcions') : __('Nou calendari', 'cfa-inscripcions'); ?>
            </h1>

            <form id="cfa-calendari-form" class="cfa-admin-form">
                <input type="hidden" name="id" value="<?php echo esc_attr($id); ?>">
                <?php wp_nonce_field('cfa_calendari_nonce', 'nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="nom"><?php _e('Nom del calendari', 'cfa-inscripcions'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="nom" id="nom" value="<?php echo esc_attr($nom); ?>"
                                   class="regular-text" required>
                            <p class="description"><?php _e('Ex: "Calendari general", "Horaris de català"', 'cfa-inscripcions'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="descripcio"><?php _e('Descripció', 'cfa-inscripcions'); ?></label>
                        </th>
                        <td>
                            <textarea name="descripcio" id="descripcio" rows="3" class="large-text"><?php echo esc_textarea($descripcio); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="places_per_franja"><?php _e('Places per franja horària', 'cfa-inscripcions'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="places_per_franja" id="places_per_franja"
                                   value="<?php echo esc_attr($places); ?>" min="1" max="100" class="small-text" required>
                            <p class="description"><?php _e('Quantes persones poden reservar la mateixa franja horària.', 'cfa-inscripcions'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="plac_maxim_dies"><?php _e('Plaç màxim de reserva', 'cfa-inscripcions'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="plac_maxim_dies" id="plac_maxim_dies"
                                   value="<?php echo esc_attr($plac); ?>" min="1" max="365" class="small-text" required>
                            <?php _e('dies', 'cfa-inscripcions'); ?>
                            <p class="description"><?php _e('Fins a quants dies en el futur es pot fer una reserva.', 'cfa-inscripcions'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Estat', 'cfa-inscripcions'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="actiu" value="1" <?php checked($actiu, 1); ?>>
                                <?php _e('Calendari actiu', 'cfa-inscripcions'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $id ? __('Actualitzar calendari', 'cfa-inscripcions') : __('Crear calendari', 'cfa-inscripcions'); ?>
                    </button>
                    <?php if ($id) : ?>
                        <button type="button" class="button button-link-delete cfa-btn-eliminar-calendari" data-id="<?php echo esc_attr($id); ?>">
                            <?php _e('Eliminar calendari', 'cfa-inscripcions'); ?>
                        </button>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Renderitzar gestió d'horaris d'un calendari
     */
    private function render_calendari_horaris() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $calendari = CFA_Inscripcions_DB::obtenir_calendari($id);

        if (!$calendari) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' .
                 __('Calendari no trobat.', 'cfa-inscripcions') .
                 '</p></div></div>';
            return;
        }

        $horaris = CFA_Inscripcions_DB::obtenir_horaris($id);
        $excepcions = CFA_Inscripcions_DB::obtenir_excepcions($id, date('Y-m-d'), date('Y-m-d', strtotime('+6 months')));

        $dies_setmana = array(
            1 => __('Dilluns', 'cfa-inscripcions'),
            2 => __('Dimarts', 'cfa-inscripcions'),
            3 => __('Dimecres', 'cfa-inscripcions'),
            4 => __('Dijous', 'cfa-inscripcions'),
            5 => __('Divendres', 'cfa-inscripcions'),
            6 => __('Dissabte', 'cfa-inscripcions'),
            7 => __('Diumenge', 'cfa-inscripcions'),
        );

        // Agrupar horaris per dia
        $horaris_per_dia = array();
        foreach ($horaris as $h) {
            $horaris_per_dia[$h->dia_setmana][] = $h;
        }
        ?>
        <div class="wrap">
            <h1>
                <a href="<?php echo admin_url('admin.php?page=cfa-calendaris'); ?>" class="page-title-action">
                    ← <?php _e('Tornar', 'cfa-inscripcions'); ?>
                </a>
                <?php printf(__('Horaris: %s', 'cfa-inscripcions'), esc_html($calendari->nom)); ?>
            </h1>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <!-- Columna principal: Horaris recurrents -->
                    <div id="post-body-content">
                        <div class="postbox">
                            <h2 class="hndle"><?php _e('Horaris setmanals recurrents', 'cfa-inscripcions'); ?></h2>
                            <div class="inside">
                                <p class="description">
                                    <?php _e('Defineix els horaris que es repeteixen cada setmana.', 'cfa-inscripcions'); ?>
                                </p>

                                <form id="cfa-horaris-form">
                                    <input type="hidden" name="calendari_id" value="<?php echo esc_attr($id); ?>">
                                    <?php wp_nonce_field('cfa_horaris_nonce', 'nonce'); ?>

                                    <table class="widefat">
                                        <thead>
                                            <tr>
                                                <th><?php _e('Dia', 'cfa-inscripcions'); ?></th>
                                                <th><?php _e('Hora inici', 'cfa-inscripcions'); ?></th>
                                                <th><?php _e('Hora fi', 'cfa-inscripcions'); ?></th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody id="cfa-horaris-tbody">
                                            <?php foreach ($dies_setmana as $num => $nom) : ?>
                                                <?php if (isset($horaris_per_dia[$num])) : ?>
                                                    <?php foreach ($horaris_per_dia[$num] as $h) : ?>
                                                        <tr data-horari-id="<?php echo esc_attr($h->id); ?>">
                                                            <td><?php echo esc_html($nom); ?></td>
                                                            <td><?php echo esc_html(substr($h->hora_inici, 0, 5)); ?></td>
                                                            <td><?php echo esc_html(substr($h->hora_fi, 0, 5)); ?></td>
                                                            <td>
                                                                <button type="button" class="button button-small button-link-delete cfa-eliminar-horari"
                                                                        data-id="<?php echo esc_attr($h->id); ?>">
                                                                    <?php _e('Eliminar', 'cfa-inscripcions'); ?>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="cfa-afegir-horari-row">
                                                <td>
                                                    <select name="nou_dia">
                                                        <?php foreach ($dies_setmana as $num => $nom) : ?>
                                                            <option value="<?php echo esc_attr($num); ?>"><?php echo esc_html($nom); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="time" name="nou_hora_inici" value="09:00">
                                                </td>
                                                <td>
                                                    <input type="time" name="nou_hora_fi" value="10:00">
                                                </td>
                                                <td>
                                                    <button type="button" class="button button-primary" id="cfa-afegir-horari">
                                                        <?php _e('Afegir', 'cfa-inscripcions'); ?>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </form>
                            </div>
                        </div>

                        <!-- Excepcions -->
                        <div class="postbox">
                            <h2 class="hndle"><?php _e('Excepcions (modificacions puntuals)', 'cfa-inscripcions'); ?></h2>
                            <div class="inside">
                                <p class="description">
                                    <?php _e('Cancel·la o afegeix horaris per dates específiques (baixes, festius, etc.)', 'cfa-inscripcions'); ?>
                                </p>

                                <form id="cfa-excepcio-form">
                                    <input type="hidden" name="calendari_id" value="<?php echo esc_attr($id); ?>">
                                    <?php wp_nonce_field('cfa_excepcio_nonce', 'nonce'); ?>

                                    <table class="form-table">
                                        <tr>
                                            <th><?php _e('Tipus', 'cfa-inscripcions'); ?></th>
                                            <td>
                                                <select name="tipus" id="cfa-excepcio-tipus">
                                                    <option value="cancel_lar"><?php _e('Cancel·lar dia/hora', 'cfa-inscripcions'); ?></option>
                                                    <option value="afegir"><?php _e('Afegir horari extra', 'cfa-inscripcions'); ?></option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php _e('Data', 'cfa-inscripcions'); ?></th>
                                            <td>
                                                <input type="date" name="data" required min="<?php echo date('Y-m-d'); ?>">
                                            </td>
                                        </tr>
                                        <tr class="cfa-excepcio-hores" style="display: none;">
                                            <th><?php _e('Hora', 'cfa-inscripcions'); ?></th>
                                            <td>
                                                <input type="time" name="hora_inici" value="09:00">
                                                <span> - </span>
                                                <input type="time" name="hora_fi" value="10:00">
                                                <p class="description"><?php _e('Deixa buit per cancel·lar tot el dia.', 'cfa-inscripcions'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php _e('Motiu', 'cfa-inscripcions'); ?></th>
                                            <td>
                                                <input type="text" name="motiu" class="regular-text" placeholder="<?php _e('Opcional', 'cfa-inscripcions'); ?>">
                                            </td>
                                        </tr>
                                    </table>

                                    <p>
                                        <button type="submit" class="button button-primary">
                                            <?php _e('Afegir excepció', 'cfa-inscripcions'); ?>
                                        </button>
                                    </p>
                                </form>

                                <?php if (!empty($excepcions)) : ?>
                                    <h4><?php _e('Excepcions programades:', 'cfa-inscripcions'); ?></h4>
                                    <table class="widefat">
                                        <thead>
                                            <tr>
                                                <th><?php _e('Data', 'cfa-inscripcions'); ?></th>
                                                <th><?php _e('Tipus', 'cfa-inscripcions'); ?></th>
                                                <th><?php _e('Hora', 'cfa-inscripcions'); ?></th>
                                                <th><?php _e('Motiu', 'cfa-inscripcions'); ?></th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($excepcions as $exc) : ?>
                                                <tr>
                                                    <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($exc->data))); ?></td>
                                                    <td>
                                                        <?php
                                                        $tipus_text = array(
                                                            'cancel_lar' => __('Cancel·lat', 'cfa-inscripcions'),
                                                            'afegir' => __('Afegit', 'cfa-inscripcions'),
                                                        );
                                                        echo esc_html($tipus_text[$exc->tipus] ?? $exc->tipus);
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if (!empty($exc->hora_inici)) {
                                                            echo esc_html(substr($exc->hora_inici, 0, 5) . ' - ' . substr($exc->hora_fi, 0, 5));
                                                        } else {
                                                            _e('Tot el dia', 'cfa-inscripcions');
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?php echo esc_html($exc->motiu); ?></td>
                                                    <td>
                                                        <button type="button" class="button button-small button-link-delete cfa-eliminar-excepcio"
                                                                data-id="<?php echo esc_attr($exc->id); ?>">
                                                            <?php _e('Eliminar', 'cfa-inscripcions'); ?>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Barra lateral -->
                    <div id="postbox-container-1" class="postbox-container">
                        <div class="postbox">
                            <h2 class="hndle"><?php _e('Informació', 'cfa-inscripcions'); ?></h2>
                            <div class="inside">
                                <p><strong><?php _e('Calendari:', 'cfa-inscripcions'); ?></strong><br>
                                    <?php echo esc_html($calendari->nom); ?>
                                </p>
                                <p><strong><?php _e('Places per franja:', 'cfa-inscripcions'); ?></strong><br>
                                    <?php echo esc_html($calendari->places_per_franja); ?>
                                </p>
                                <p><strong><?php _e('Plaç màxim:', 'cfa-inscripcions'); ?></strong><br>
                                    <?php printf(__('%d dies', 'cfa-inscripcions'), $calendari->plac_maxim_dies); ?>
                                </p>
                                <hr>
                                <p>
                                    <a href="<?php echo admin_url('admin.php?page=cfa-calendaris&action=editar&id=' . $id); ?>" class="button">
                                        <?php _e('Editar calendari', 'cfa-inscripcions'); ?>
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // PÀGINA DE CURSOS
    // =========================================================================

    /**
     * Renderitzar pàgina de cursos
     */
    public function render_page_cursos() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'llistat';

        switch ($action) {
            case 'editar':
                $this->render_curs_form();
                break;
            case 'nou':
                $this->render_curs_form();
                break;
            default:
                $this->render_cursos_llistat();
                break;
        }
    }

    /**
     * Renderitzar llistat de cursos
     */
    private function render_cursos_llistat() {
        $cursos = CFA_Inscripcions_DB::obtenir_cursos();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Cursos', 'cfa-inscripcions'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=cfa-cursos&action=nou'); ?>" class="page-title-action">
                <?php _e('Afegir nou', 'cfa-inscripcions'); ?>
            </a>

            <p class="description">
                <?php _e('Gestiona els cursos disponibles per a inscripcions. Cada curs pot tenir assignat un professor i un calendari.', 'cfa-inscripcions'); ?>
            </p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php _e('Nom', 'cfa-inscripcions'); ?></th>
                        <th scope="col"><?php _e('Calendari', 'cfa-inscripcions'); ?></th>
                        <th scope="col"><?php _e('Professor', 'cfa-inscripcions'); ?></th>
                        <th scope="col"><?php _e('Ordre', 'cfa-inscripcions'); ?></th>
                        <th scope="col"><?php _e('Estat', 'cfa-inscripcions'); ?></th>
                        <th scope="col"><?php _e('Accions', 'cfa-inscripcions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cursos)) : ?>
                        <tr>
                            <td colspan="6"><?php _e('No hi ha cursos. Crea\'n un per començar.', 'cfa-inscripcions'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($cursos as $curs) : ?>
                            <?php
                            $calendari = $curs->calendari_id ? CFA_Inscripcions_DB::obtenir_calendari($curs->calendari_id) : null;
                            $professor = $curs->professor_id ? get_userdata($curs->professor_id) : null;
                            ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo admin_url('admin.php?page=cfa-cursos&action=editar&id=' . $curs->id); ?>">
                                            <?php echo esc_html($curs->nom); ?>
                                        </a>
                                    </strong>
                                    <?php if (!empty($curs->descripcio)) : ?>
                                        <br><small><?php echo esc_html(wp_trim_words($curs->descripcio, 10)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($calendari) : ?>
                                        <a href="<?php echo admin_url('admin.php?page=cfa-calendaris&action=editar&id=' . $calendari->id); ?>">
                                            <?php echo esc_html($calendari->nom); ?>
                                        </a>
                                    <?php else : ?>
                                        <span class="cfa-badge cfa-badge-warning"><?php _e('Sense assignar', 'cfa-inscripcions'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($professor) : ?>
                                        <?php echo esc_html($professor->display_name); ?>
                                    <?php else : ?>
                                        <span style="color:#888;"><?php _e('Sense assignar', 'cfa-inscripcions'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($curs->ordre); ?></td>
                                <td>
                                    <?php if ($curs->actiu) : ?>
                                        <span class="cfa-badge cfa-badge-success"><?php _e('Actiu', 'cfa-inscripcions'); ?></span>
                                    <?php else : ?>
                                        <span class="cfa-badge cfa-badge-secondary"><?php _e('Inactiu', 'cfa-inscripcions'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=cfa-cursos&action=editar&id=' . $curs->id); ?>"
                                       class="button button-small">
                                        <?php _e('Editar', 'cfa-inscripcions'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Renderitzar formulari de curs
     */
    private function render_curs_form() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $curs = $id ? CFA_Inscripcions_DB::obtenir_curs($id) : null;

        $nom = $curs ? $curs->nom : '';
        $descripcio = $curs ? $curs->descripcio : '';
        $calendari_id = $curs ? $curs->calendari_id : 0;
        $professor_id = $curs ? $curs->professor_id : 0;
        $ordre = $curs ? $curs->ordre : 0;
        $actiu = $curs ? $curs->actiu : 1;

        // Obtenir calendaris disponibles
        $calendaris = CFA_Inscripcions_DB::obtenir_calendaris();

        // Obtenir professors disponibles (usuaris amb rol cfa_professor o administradors)
        $professors = get_users(array(
            'role__in' => array('cfa_professor', 'administrator'),
            'orderby' => 'display_name',
            'order' => 'ASC',
        ));
        ?>
        <div class="wrap">
            <h1>
                <a href="<?php echo admin_url('admin.php?page=cfa-cursos'); ?>" class="page-title-action">
                    ← <?php _e('Tornar', 'cfa-inscripcions'); ?>
                </a>
                <?php echo $id ? __('Editar curs', 'cfa-inscripcions') : __('Nou curs', 'cfa-inscripcions'); ?>
            </h1>

            <form id="cfa-curs-form" class="cfa-admin-form">
                <input type="hidden" name="id" value="<?php echo esc_attr($id); ?>">
                <?php wp_nonce_field('cfa_curs_nonce', 'nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="nom"><?php _e('Nom del curs', 'cfa-inscripcions'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="nom" id="nom" value="<?php echo esc_attr($nom); ?>"
                                   class="regular-text" required>
                            <p class="description"><?php _e('Ex: "Llengua catalana", "Graduat ESO"', 'cfa-inscripcions'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="descripcio"><?php _e('Descripció', 'cfa-inscripcions'); ?></label>
                        </th>
                        <td>
                            <textarea name="descripcio" id="descripcio" rows="3" class="large-text"><?php echo esc_textarea($descripcio); ?></textarea>
                            <p class="description"><?php _e('Descripció breu que es mostrarà al formulari d\'inscripció.', 'cfa-inscripcions'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="calendari_id"><?php _e('Calendari assignat', 'cfa-inscripcions'); ?></label>
                        </th>
                        <td>
                            <select name="calendari_id" id="calendari_id" class="regular-text">
                                <option value=""><?php _e('-- Selecciona un calendari --', 'cfa-inscripcions'); ?></option>
                                <?php foreach ($calendaris as $cal) : ?>
                                    <option value="<?php echo esc_attr($cal->id); ?>" <?php selected($calendari_id, $cal->id); ?>>
                                        <?php echo esc_html($cal->nom); ?>
                                        <?php if (!$cal->actiu) echo ' (' . __('inactiu', 'cfa-inscripcions') . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('Selecciona el calendari que controla la disponibilitat d\'aquest curs.', 'cfa-inscripcions'); ?>
                                <a href="<?php echo admin_url('admin.php?page=cfa-calendaris'); ?>"><?php _e('Gestionar calendaris', 'cfa-inscripcions'); ?></a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="professor_id"><?php _e('Professor assignat', 'cfa-inscripcions'); ?></label>
                        </th>
                        <td>
                            <select name="professor_id" id="professor_id" class="regular-text">
                                <option value=""><?php _e('-- Sense assignar --', 'cfa-inscripcions'); ?></option>
                                <?php foreach ($professors as $prof) : ?>
                                    <option value="<?php echo esc_attr($prof->ID); ?>" <?php selected($professor_id, $prof->ID); ?>>
                                        <?php echo esc_html($prof->display_name); ?>
                                        <?php if (in_array('administrator', $prof->roles)) echo ' (Admin)'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('El professor assignat podrà veure i gestionar les inscripcions d\'aquest curs.', 'cfa-inscripcions'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ordre"><?php _e('Ordre', 'cfa-inscripcions'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="ordre" id="ordre"
                                   value="<?php echo esc_attr($ordre); ?>" min="0" class="small-text">
                            <p class="description"><?php _e('Ordre de visualització al formulari (menor = primer).', 'cfa-inscripcions'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Estat', 'cfa-inscripcions'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="actiu" value="1" <?php checked($actiu, 1); ?>>
                                <?php _e('Curs actiu', 'cfa-inscripcions'); ?>
                            </label>
                            <p class="description"><?php _e('Desmarca per ocultar aquest curs del formulari d\'inscripció.', 'cfa-inscripcions'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $id ? __('Actualitzar curs', 'cfa-inscripcions') : __('Crear curs', 'cfa-inscripcions'); ?>
                    </button>
                    <?php if ($id) : ?>
                        <button type="button" class="button button-link-delete cfa-btn-eliminar-curs" data-id="<?php echo esc_attr($id); ?>">
                            <?php _e('Eliminar curs', 'cfa-inscripcions'); ?>
                        </button>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        <?php
    }

    // =========================================================================
    // PÀGINA DE CONFIGURACIÓ
    // =========================================================================

    /**
     * Renderitzar pàgina de configuració
     */
    public function render_page_configuracio() {
        ?>
        <div class="wrap">
            <h1><?php _e('Configuració', 'cfa-inscripcions'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('cfa_configuracio_nonce'); ?>

                <h2><?php _e('General', 'cfa-inscripcions'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cfa_nom_centre"><?php _e('Nom del centre', 'cfa-inscripcions'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="cfa_nom_centre" id="cfa_nom_centre"
                                   value="<?php echo esc_attr(get_option('cfa_inscripcions_nom_centre', '')); ?>"
                                   class="regular-text">
                            <p class="description"><?php _e('Nom que apareixerà als emails. Si està buit, s\'usarà el nom del lloc.', 'cfa-inscripcions'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cfa_admin_email"><?php _e('Email de notificacions', 'cfa-inscripcions'); ?></label>
                        </th>
                        <td>
                            <input type="email" name="cfa_admin_email" id="cfa_admin_email"
                                   value="<?php echo esc_attr(get_option('cfa_inscripcions_admin_email', '')); ?>"
                                   class="regular-text">
                            <p class="description"><?php _e('Email on s\'enviaran les notificacions de noves inscripcions. Si està buit, s\'usarà l\'email de l\'administrador.', 'cfa-inscripcions'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cfa_logo_url"><?php _e('URL del logo', 'cfa-inscripcions'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="cfa_logo_url" id="cfa_logo_url"
                                   value="<?php echo esc_attr(get_option('cfa_inscripcions_logo_url', '')); ?>"
                                   class="regular-text">
                            <p class="description"><?php _e('Logo que apareixerà als emails (opcional).', 'cfa-inscripcions'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php _e('Shortcode', 'cfa-inscripcions'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Com utilitzar', 'cfa-inscripcions'); ?></th>
                        <td>
                            <p><?php _e('Per mostrar el formulari d\'inscripció, afegeix el següent shortcode a qualsevol pàgina:', 'cfa-inscripcions'); ?></p>
                            <code>[cfa_inscripcio]</code>
                            <p class="description" style="margin-top: 10px;">
                                <?php _e('Pots crear una pàgina nova amb la URL /inscripcions i afegir-hi aquest shortcode.', 'cfa-inscripcions'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="cfa_guardar_configuracio" class="button button-primary"
                           value="<?php _e('Guardar canvis', 'cfa-inscripcions'); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Guardar configuració
     */
    private function guardar_configuracio() {
        update_option('cfa_inscripcions_nom_centre', sanitize_text_field($_POST['cfa_nom_centre'] ?? ''));
        update_option('cfa_inscripcions_admin_email', sanitize_email($_POST['cfa_admin_email'] ?? ''));
        update_option('cfa_inscripcions_logo_url', esc_url_raw($_POST['cfa_logo_url'] ?? ''));

        add_settings_error('cfa_configuracio', 'settings_updated', __('Configuració guardada.', 'cfa-inscripcions'), 'success');
        settings_errors('cfa_configuracio');
    }

    // =========================================================================
    // AJAX HANDLERS
    // =========================================================================

    /**
     * AJAX: Confirmar inscripció
     */
    public function ajax_confirmar_inscripcio() {
        check_ajax_referer('cfa_inscripcions_nonce', 'nonce');

        if (!current_user_can('cfa_gestionar_inscripcions')) {
            wp_send_json_error(array('message' => __('No tens permisos.', 'cfa-inscripcions')));
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $inscripcio = CFA_Inscripcions_DB::obtenir_inscripcio($id);

        if (!$inscripcio) {
            wp_send_json_error(array('message' => __('Inscripció no trobada.', 'cfa-inscripcions')));
        }

        $result = CFA_Inscripcions_DB::actualitzar_estat_inscripcio($id, 'confirmada');

        if ($result !== false) {
            // Enviar email de confirmació
            $curs = CFA_Inscripcions_DB::obtenir_curs($inscripcio->curs_id);
            $inscripcio->estat = 'confirmada';
            CFA_Emails::enviar_email_confirmacio_cita($inscripcio, $curs);

            wp_send_json_success(array('message' => __('Inscripció confirmada correctament.', 'cfa-inscripcions')));
        } else {
            wp_send_json_error(array('message' => __('Error en confirmar la inscripció.', 'cfa-inscripcions')));
        }
    }

    /**
     * AJAX: Cancel·lar inscripció
     */
    public function ajax_cancel_lar_inscripcio() {
        check_ajax_referer('cfa_inscripcions_nonce', 'nonce');

        if (!current_user_can('cfa_gestionar_inscripcions')) {
            wp_send_json_error(array('message' => __('No tens permisos.', 'cfa-inscripcions')));
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $motiu = isset($_POST['motiu']) ? sanitize_text_field($_POST['motiu']) : '';

        $inscripcio = CFA_Inscripcions_DB::obtenir_inscripcio($id);

        if (!$inscripcio) {
            wp_send_json_error(array('message' => __('Inscripció no trobada.', 'cfa-inscripcions')));
        }

        $result = CFA_Inscripcions_DB::actualitzar_estat_inscripcio($id, 'cancel_lada');

        if ($result !== false) {
            // Alliberar reserva
            CFA_Inscripcions_DB::eliminar_reserva_per_inscripcio($id);

            // Enviar email
            $curs = CFA_Inscripcions_DB::obtenir_curs($inscripcio->curs_id);
            CFA_Emails::enviar_email_cancel_lacio($inscripcio, $curs, $motiu);

            wp_send_json_success(array('message' => __('Inscripció cancel·lada.', 'cfa-inscripcions')));
        } else {
            wp_send_json_error(array('message' => __('Error en cancel·lar la inscripció.', 'cfa-inscripcions')));
        }
    }

    /**
     * AJAX: Eliminar inscripció
     */
    public function ajax_eliminar_inscripcio() {
        check_ajax_referer('cfa_inscripcions_nonce', 'nonce');

        if (!current_user_can('cfa_gestionar_inscripcions')) {
            wp_send_json_error(array('message' => __('No tens permisos.', 'cfa-inscripcions')));
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

        $result = CFA_Inscripcions_DB::eliminar_inscripcio($id);

        if ($result) {
            wp_send_json_success(array('message' => __('Inscripció eliminada.', 'cfa-inscripcions')));
        } else {
            wp_send_json_error(array('message' => __('Error en eliminar la inscripció.', 'cfa-inscripcions')));
        }
    }

    /**
     * AJAX: Editar inscripció
     */
    public function ajax_editar_inscripcio() {
        check_ajax_referer('cfa_editar_inscripcio_nonce', 'nonce');

        if (!current_user_can('cfa_gestionar_inscripcions')) {
            wp_send_json_error(array('message' => __('No tens permisos.', 'cfa-inscripcions')));
        }

        $id = isset($_POST['inscripcio_id']) ? absint($_POST['inscripcio_id']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => __('ID d\'inscripció invàlid.', 'cfa-inscripcions')));
        }

        // Obtenir curs per actualitzar calendari_id si cal
        $curs_id = isset($_POST['curs_id']) ? absint($_POST['curs_id']) : 0;
        $curs = CFA_Inscripcions_DB::obtenir_curs($curs_id);
        $calendari_id = $curs ? $curs->calendari_id : 0;

        $dades = array(
            'curs_id' => $curs_id,
            'calendari_id' => $calendari_id,
            'data_cita' => sanitize_text_field($_POST['data_cita'] ?? ''),
            'hora_cita' => sanitize_text_field($_POST['hora_cita'] ?? ''),
            'nom' => sanitize_text_field($_POST['nom'] ?? ''),
            'cognoms' => sanitize_text_field($_POST['cognoms'] ?? ''),
            'dni' => strtoupper(sanitize_text_field($_POST['dni'] ?? '')),
            'data_naixement' => sanitize_text_field($_POST['data_naixement'] ?? ''),
            'telefon' => sanitize_text_field($_POST['telefon'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'adreca' => sanitize_text_field($_POST['adreca'] ?? ''),
            'poblacio' => sanitize_text_field($_POST['poblacio'] ?? ''),
            'codi_postal' => sanitize_text_field($_POST['codi_postal'] ?? ''),
            'nivell_estudis' => sanitize_text_field($_POST['nivell_estudis'] ?? ''),
            'observacions' => sanitize_textarea_field($_POST['observacions'] ?? ''),
            'estat' => sanitize_text_field($_POST['estat'] ?? 'pendent'),
        );

        $result = CFA_Inscripcions_DB::actualitzar_inscripcio($id, $dades);

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Inscripció actualitzada correctament.', 'cfa-inscripcions'),
                'redirect' => admin_url('admin.php?page=cfa-inscripcions&action=veure&id=' . $id),
            ));
        } else {
            wp_send_json_error(array('message' => __('Error en actualitzar la inscripció.', 'cfa-inscripcions')));
        }
    }

    /**
     * AJAX: Guardar calendari
     */
    public function ajax_guardar_calendari() {
        check_ajax_referer('cfa_calendari_nonce', 'nonce');

        if (!current_user_can('cfa_gestionar_calendaris')) {
            wp_send_json_error(array('message' => __('No tens permisos.', 'cfa-inscripcions')));
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

        $dades = array(
            'nom' => sanitize_text_field($_POST['nom'] ?? ''),
            'descripcio' => sanitize_textarea_field($_POST['descripcio'] ?? ''),
            'places_per_franja' => absint($_POST['places_per_franja'] ?? 1),
            'plac_maxim_dies' => absint($_POST['plac_maxim_dies'] ?? 90),
            'actiu' => isset($_POST['actiu']) ? 1 : 0,
        );

        if (empty($dades['nom'])) {
            wp_send_json_error(array('message' => __('El nom és obligatori.', 'cfa-inscripcions')));
        }

        if ($id) {
            $result = CFA_Inscripcions_DB::actualitzar_calendari($id, $dades);
            $message = __('Calendari actualitzat.', 'cfa-inscripcions');
        } else {
            $result = CFA_Inscripcions_DB::crear_calendari($dades);
            $id = $result;
            $message = __('Calendari creat.', 'cfa-inscripcions');
        }

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => $message,
                'id' => $id,
                'redirect' => admin_url('admin.php?page=cfa-calendaris'),
            ));
        } else {
            wp_send_json_error(array('message' => __('Error en guardar el calendari.', 'cfa-inscripcions')));
        }
    }

    /**
     * AJAX: Eliminar calendari
     */
    public function ajax_eliminar_calendari() {
        check_ajax_referer('cfa_calendari_nonce', 'nonce');

        if (!current_user_can('cfa_gestionar_calendaris')) {
            wp_send_json_error(array('message' => __('No tens permisos.', 'cfa-inscripcions')));
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

        $result = CFA_Inscripcions_DB::eliminar_calendari($id);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Calendari eliminat.', 'cfa-inscripcions'),
                'redirect' => admin_url('admin.php?page=cfa-calendaris'),
            ));
        } else {
            wp_send_json_error(array('message' => __('Error en eliminar el calendari.', 'cfa-inscripcions')));
        }
    }

    /**
     * AJAX: Guardar horaris
     */
    public function ajax_guardar_horaris() {
        check_ajax_referer('cfa_horaris_nonce', 'nonce');

        if (!current_user_can('cfa_gestionar_calendaris')) {
            wp_send_json_error(array('message' => __('No tens permisos.', 'cfa-inscripcions')));
        }

        $calendari_id = isset($_POST['calendari_id']) ? absint($_POST['calendari_id']) : 0;
        $action = isset($_POST['horari_action']) ? sanitize_text_field($_POST['horari_action']) : '';

        if ($action === 'afegir') {
            $dia = isset($_POST['dia']) ? absint($_POST['dia']) : 0;
            $hora_inici = isset($_POST['hora_inici']) ? sanitize_text_field($_POST['hora_inici']) : '';
            $hora_fi = isset($_POST['hora_fi']) ? sanitize_text_field($_POST['hora_fi']) : '';

            if (!$dia || !$hora_inici || !$hora_fi) {
                wp_send_json_error(array('message' => __('Tots els camps són obligatoris.', 'cfa-inscripcions')));
            }

            $id = CFA_Inscripcions_DB::afegir_horari($calendari_id, $dia, $hora_inici, $hora_fi);

            if ($id) {
                wp_send_json_success(array(
                    'message' => __('Horari afegit.', 'cfa-inscripcions'),
                    'id' => $id,
                ));
            } else {
                wp_send_json_error(array('message' => __('Error en afegir l\'horari.', 'cfa-inscripcions')));
            }
        } elseif ($action === 'eliminar') {
            $horari_id = isset($_POST['horari_id']) ? absint($_POST['horari_id']) : 0;

            $result = CFA_Inscripcions_DB::eliminar_horari($horari_id);

            if ($result) {
                wp_send_json_success(array('message' => __('Horari eliminat.', 'cfa-inscripcions')));
            } else {
                wp_send_json_error(array('message' => __('Error en eliminar l\'horari.', 'cfa-inscripcions')));
            }
        }
    }

    /**
     * AJAX: Afegir excepció
     */
    public function ajax_afegir_excepcio() {
        check_ajax_referer('cfa_excepcio_nonce', 'nonce');

        if (!current_user_can('cfa_gestionar_calendaris')) {
            wp_send_json_error(array('message' => __('No tens permisos.', 'cfa-inscripcions')));
        }

        $dades = array(
            'calendari_id' => isset($_POST['calendari_id']) ? absint($_POST['calendari_id']) : 0,
            'data' => isset($_POST['data']) ? sanitize_text_field($_POST['data']) : '',
            'tipus' => isset($_POST['tipus']) ? sanitize_text_field($_POST['tipus']) : '',
            'hora_inici' => !empty($_POST['hora_inici']) ? sanitize_text_field($_POST['hora_inici']) : null,
            'hora_fi' => !empty($_POST['hora_fi']) ? sanitize_text_field($_POST['hora_fi']) : null,
            'motiu' => isset($_POST['motiu']) ? sanitize_text_field($_POST['motiu']) : '',
        );

        if (!$dades['calendari_id'] || !$dades['data'] || !$dades['tipus']) {
            wp_send_json_error(array('message' => __('Falten camps obligatoris.', 'cfa-inscripcions')));
        }

        $id = CFA_Inscripcions_DB::afegir_excepcio($dades);

        if ($id) {
            wp_send_json_success(array('message' => __('Excepció afegida.', 'cfa-inscripcions')));
        } else {
            wp_send_json_error(array('message' => __('Error en afegir l\'excepció.', 'cfa-inscripcions')));
        }
    }

    /**
     * AJAX: Eliminar excepció
     */
    public function ajax_eliminar_excepcio() {
        check_ajax_referer('cfa_excepcio_nonce', 'nonce');

        if (!current_user_can('cfa_gestionar_calendaris')) {
            wp_send_json_error(array('message' => __('No tens permisos.', 'cfa-inscripcions')));
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

        $result = CFA_Inscripcions_DB::eliminar_excepcio($id);

        if ($result) {
            wp_send_json_success(array('message' => __('Excepció eliminada.', 'cfa-inscripcions')));
        } else {
            wp_send_json_error(array('message' => __('Error en eliminar l\'excepció.', 'cfa-inscripcions')));
        }
    }

    /**
     * AJAX: Guardar curs
     */
    public function ajax_guardar_curs() {
        check_ajax_referer('cfa_curs_nonce', 'nonce');

        if (!current_user_can('cfa_gestionar_calendaris')) {
            wp_send_json_error(array('message' => __('No tens permisos.', 'cfa-inscripcions')));
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

        $dades = array(
            'nom' => sanitize_text_field($_POST['nom'] ?? ''),
            'descripcio' => sanitize_textarea_field($_POST['descripcio'] ?? ''),
            'calendari_id' => !empty($_POST['calendari_id']) ? absint($_POST['calendari_id']) : null,
            'professor_id' => !empty($_POST['professor_id']) ? absint($_POST['professor_id']) : null,
            'ordre' => absint($_POST['ordre'] ?? 0),
            'actiu' => isset($_POST['actiu']) ? 1 : 0,
        );

        if (empty($dades['nom'])) {
            wp_send_json_error(array('message' => __('El nom és obligatori.', 'cfa-inscripcions')));
        }

        if ($id) {
            $result = CFA_Inscripcions_DB::actualitzar_curs($id, $dades);
            $message = __('Curs actualitzat.', 'cfa-inscripcions');
        } else {
            $result = CFA_Inscripcions_DB::crear_curs($dades);
            $id = $result;
            $message = __('Curs creat.', 'cfa-inscripcions');
        }

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => $message,
                'id' => $id,
                'redirect' => admin_url('admin.php?page=cfa-cursos'),
            ));
        } else {
            wp_send_json_error(array('message' => __('Error en guardar el curs.', 'cfa-inscripcions')));
        }
    }

    /**
     * AJAX: Eliminar curs
     */
    public function ajax_eliminar_curs() {
        check_ajax_referer('cfa_curs_nonce', 'nonce');

        if (!current_user_can('cfa_gestionar_calendaris')) {
            wp_send_json_error(array('message' => __('No tens permisos.', 'cfa-inscripcions')));
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

        // Comprovar si hi ha inscripcions associades
        $inscripcions = CFA_Inscripcions_DB::comptar_inscripcions(array('curs_id' => $id));
        if ($inscripcions > 0) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('No es pot eliminar el curs perquè té %d inscripcions associades.', 'cfa-inscripcions'),
                    $inscripcions
                )
            ));
        }

        $result = CFA_Inscripcions_DB::eliminar_curs($id);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Curs eliminat.', 'cfa-inscripcions'),
                'redirect' => admin_url('admin.php?page=cfa-cursos'),
            ));
        } else {
            wp_send_json_error(array('message' => __('Error en eliminar el curs.', 'cfa-inscripcions')));
        }
    }
}
