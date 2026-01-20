<?php
/**
 * Gestió de la base de dades del plugin
 *
 * @package CFA_Inscripcions
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFA_Inscripcions_DB {

    private static $instance = null;

    // Noms de les taules
    public static $table_inscripcions;
    public static $table_calendaris;
    public static $table_horaris;
    public static $table_excepcions;
    public static $table_reserves;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;

        self::$table_inscripcions = $wpdb->prefix . 'cfa_inscripcions';
        self::$table_calendaris = $wpdb->prefix . 'cfa_calendaris';
        self::$table_horaris = $wpdb->prefix . 'cfa_horaris';
        self::$table_excepcions = $wpdb->prefix . 'cfa_excepcions';
        self::$table_reserves = $wpdb->prefix . 'cfa_reserves';
    }

    /**
     * Crear totes les taules necessàries
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Taula d'inscripcions
        $table_inscripcions = $wpdb->prefix . 'cfa_inscripcions';
        $sql_inscripcions = "CREATE TABLE $table_inscripcions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            curs_id bigint(20) UNSIGNED NOT NULL,
            calendari_id bigint(20) UNSIGNED NOT NULL,
            data_cita date NOT NULL,
            hora_cita time NOT NULL,
            nom varchar(100) NOT NULL,
            cognoms varchar(200) NOT NULL,
            dni varchar(20) NOT NULL,
            data_naixement date DEFAULT NULL,
            telefon varchar(20) NOT NULL,
            email varchar(100) NOT NULL,
            adreca varchar(255) DEFAULT NULL,
            poblacio varchar(100) DEFAULT NULL,
            codi_postal varchar(10) DEFAULT NULL,
            nivell_estudis varchar(100) DEFAULT NULL,
            observacions text DEFAULT NULL,
            estat enum('pendent','confirmada','cancel_lada') DEFAULT 'pendent',
            data_creacio datetime DEFAULT CURRENT_TIMESTAMP,
            data_modificacio datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ip_usuari varchar(45) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY curs_id (curs_id),
            KEY calendari_id (calendari_id),
            KEY estat (estat),
            KEY data_cita (data_cita)
        ) $charset_collate;";

        dbDelta($sql_inscripcions);

        // Taula de calendaris (configuració per cada calendari/curs)
        $table_calendaris = $wpdb->prefix . 'cfa_calendaris';
        $sql_calendaris = "CREATE TABLE $table_calendaris (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            nom varchar(200) NOT NULL,
            descripcio text DEFAULT NULL,
            places_per_franja int(11) DEFAULT 1,
            plac_maxim_dies int(11) DEFAULT 90,
            actiu tinyint(1) DEFAULT 1,
            data_creacio datetime DEFAULT CURRENT_TIMESTAMP,
            data_modificacio datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        dbDelta($sql_calendaris);

        // Taula d'horaris recurrents (dies de la setmana amb hores)
        $table_horaris = $wpdb->prefix . 'cfa_horaris';
        $sql_horaris = "CREATE TABLE $table_horaris (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            calendari_id bigint(20) UNSIGNED NOT NULL,
            dia_setmana tinyint(1) NOT NULL COMMENT '1=Dilluns, 7=Diumenge',
            hora_inici time NOT NULL,
            hora_fi time NOT NULL,
            actiu tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY calendari_id (calendari_id),
            KEY dia_setmana (dia_setmana)
        ) $charset_collate;";

        dbDelta($sql_horaris);

        // Taula d'excepcions (modificacions puntuals a horaris)
        $table_excepcions = $wpdb->prefix . 'cfa_excepcions';
        $sql_excepcions = "CREATE TABLE $table_excepcions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            calendari_id bigint(20) UNSIGNED NOT NULL,
            data date NOT NULL,
            tipus enum('cancel_lar','afegir','modificar') NOT NULL,
            hora_inici time DEFAULT NULL,
            hora_fi time DEFAULT NULL,
            places_especials int(11) DEFAULT NULL,
            motiu varchar(255) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY calendari_id (calendari_id),
            KEY data (data)
        ) $charset_collate;";

        dbDelta($sql_excepcions);

        // Taula de reserves (places ocupades per data/hora)
        $table_reserves = $wpdb->prefix . 'cfa_reserves';
        $sql_reserves = "CREATE TABLE $table_reserves (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            calendari_id bigint(20) UNSIGNED NOT NULL,
            inscripcio_id bigint(20) UNSIGNED NOT NULL,
            data date NOT NULL,
            hora time NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY reserva_unica (calendari_id, inscripcio_id),
            KEY calendari_data_hora (calendari_id, data, hora)
        ) $charset_collate;";

        dbDelta($sql_reserves);

        // Guardar versió de la BD
        update_option('cfa_inscripcions_db_version', '1.0.0');
    }

    /**
     * Eliminar totes les taules (per desinstal·lació)
     */
    public static function drop_tables() {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'cfa_reserves',
            $wpdb->prefix . 'cfa_excepcions',
            $wpdb->prefix . 'cfa_horaris',
            $wpdb->prefix . 'cfa_calendaris',
            $wpdb->prefix . 'cfa_inscripcions',
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        delete_option('cfa_inscripcions_db_version');
    }

    // =========================================================================
    // MÈTODES PER INSCRIPCIONS
    // =========================================================================

    /**
     * Crear nova inscripció
     */
    public static function crear_inscripcio($dades) {
        global $wpdb;

        $defaults = array(
            'estat' => 'pendent',
            'ip_usuari' => self::get_client_ip(),
        );

        $dades = wp_parse_args($dades, $defaults);

        $result = $wpdb->insert(
            self::$table_inscripcions,
            $dades,
            array(
                '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
            )
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Obtenir inscripció per ID
     */
    public static function obtenir_inscripcio($id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::$table_inscripcions . " WHERE id = %d",
                $id
            )
        );
    }

    /**
     * Obtenir inscripcions amb filtres
     */
    public static function obtenir_inscripcions($args = array()) {
        global $wpdb;

        $defaults = array(
            'estat' => '',
            'curs_id' => 0,
            'calendari_id' => 0,
            'data_desde' => '',
            'data_fins' => '',
            'cerca' => '',
            'orderby' => 'data_creacio',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if (!empty($args['estat'])) {
            $where[] = 'estat = %s';
            $values[] = $args['estat'];
        }

        if (!empty($args['curs_id'])) {
            $where[] = 'curs_id = %d';
            $values[] = $args['curs_id'];
        }

        if (!empty($args['calendari_id'])) {
            $where[] = 'calendari_id = %d';
            $values[] = $args['calendari_id'];
        }

        if (!empty($args['data_desde'])) {
            $where[] = 'data_cita >= %s';
            $values[] = $args['data_desde'];
        }

        if (!empty($args['data_fins'])) {
            $where[] = 'data_cita <= %s';
            $values[] = $args['data_fins'];
        }

        if (!empty($args['cerca'])) {
            $where[] = '(nom LIKE %s OR cognoms LIKE %s OR email LIKE %s OR dni LIKE %s)';
            $cerca = '%' . $wpdb->esc_like($args['cerca']) . '%';
            $values[] = $cerca;
            $values[] = $cerca;
            $values[] = $cerca;
            $values[] = $cerca;
        }

        $allowed_orderby = array('id', 'data_creacio', 'data_cita', 'nom', 'estat');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'data_creacio';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM " . self::$table_inscripcions . " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY $orderby $order";
        $sql .= " LIMIT %d OFFSET %d";

        $values[] = $args['limit'];
        $values[] = $args['offset'];

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Comptar inscripcions amb filtres
     */
    public static function comptar_inscripcions($args = array()) {
        global $wpdb;

        $defaults = array(
            'estat' => '',
            'curs_id' => 0,
            'calendari_id' => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if (!empty($args['estat'])) {
            $where[] = 'estat = %s';
            $values[] = $args['estat'];
        }

        if (!empty($args['curs_id'])) {
            $where[] = 'curs_id = %d';
            $values[] = $args['curs_id'];
        }

        if (!empty($args['calendari_id'])) {
            $where[] = 'calendari_id = %d';
            $values[] = $args['calendari_id'];
        }

        $sql = "SELECT COUNT(*) FROM " . self::$table_inscripcions . " WHERE " . implode(' AND ', $where);

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Actualitzar estat d'una inscripció
     */
    public static function actualitzar_estat_inscripcio($id, $nou_estat) {
        global $wpdb;

        $estats_valids = array('pendent', 'confirmada', 'cancel_lada');
        if (!in_array($nou_estat, $estats_valids)) {
            return false;
        }

        return $wpdb->update(
            self::$table_inscripcions,
            array('estat' => $nou_estat),
            array('id' => $id),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Eliminar inscripció
     */
    public static function eliminar_inscripcio($id) {
        global $wpdb;

        // Primer eliminem la reserva associada
        $wpdb->delete(
            self::$table_reserves,
            array('inscripcio_id' => $id),
            array('%d')
        );

        // Després eliminem la inscripció
        return $wpdb->delete(
            self::$table_inscripcions,
            array('id' => $id),
            array('%d')
        );
    }

    // =========================================================================
    // MÈTODES PER CALENDARIS
    // =========================================================================

    /**
     * Crear calendari
     */
    public static function crear_calendari($dades) {
        global $wpdb;

        $defaults = array(
            'places_per_franja' => 1,
            'plac_maxim_dies' => 90,
            'actiu' => 1,
        );

        $dades = wp_parse_args($dades, $defaults);

        $result = $wpdb->insert(
            self::$table_calendaris,
            $dades,
            array('%s', '%s', '%d', '%d', '%d')
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Obtenir calendari per ID
     */
    public static function obtenir_calendari($id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::$table_calendaris . " WHERE id = %d",
                $id
            )
        );
    }

    /**
     * Obtenir tots els calendaris
     */
    public static function obtenir_calendaris($actius_nomes = false) {
        global $wpdb;

        $sql = "SELECT * FROM " . self::$table_calendaris;

        if ($actius_nomes) {
            $sql .= " WHERE actiu = 1";
        }

        $sql .= " ORDER BY nom ASC";

        return $wpdb->get_results($sql);
    }

    /**
     * Actualitzar calendari
     */
    public static function actualitzar_calendari($id, $dades) {
        global $wpdb;

        return $wpdb->update(
            self::$table_calendaris,
            $dades,
            array('id' => $id),
            array('%s', '%s', '%d', '%d', '%d'),
            array('%d')
        );
    }

    /**
     * Eliminar calendari
     */
    public static function eliminar_calendari($id) {
        global $wpdb;

        // Eliminar horaris associats
        $wpdb->delete(self::$table_horaris, array('calendari_id' => $id), array('%d'));

        // Eliminar excepcions associades
        $wpdb->delete(self::$table_excepcions, array('calendari_id' => $id), array('%d'));

        // Eliminar reserves associades
        $wpdb->delete(self::$table_reserves, array('calendari_id' => $id), array('%d'));

        // Eliminar calendari
        return $wpdb->delete(self::$table_calendaris, array('id' => $id), array('%d'));
    }

    // =========================================================================
    // MÈTODES PER HORARIS RECURRENTS
    // =========================================================================

    /**
     * Afegir horari recurrent
     */
    public static function afegir_horari($calendari_id, $dia_setmana, $hora_inici, $hora_fi) {
        global $wpdb;

        $result = $wpdb->insert(
            self::$table_horaris,
            array(
                'calendari_id' => $calendari_id,
                'dia_setmana' => $dia_setmana,
                'hora_inici' => $hora_inici,
                'hora_fi' => $hora_fi,
                'actiu' => 1,
            ),
            array('%d', '%d', '%s', '%s', '%d')
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Obtenir horaris d'un calendari
     */
    public static function obtenir_horaris($calendari_id) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::$table_horaris . "
                WHERE calendari_id = %d AND actiu = 1
                ORDER BY dia_setmana, hora_inici",
                $calendari_id
            )
        );
    }

    /**
     * Eliminar horari
     */
    public static function eliminar_horari($id) {
        global $wpdb;

        return $wpdb->delete(
            self::$table_horaris,
            array('id' => $id),
            array('%d')
        );
    }

    /**
     * Eliminar tots els horaris d'un calendari
     */
    public static function eliminar_horaris_calendari($calendari_id) {
        global $wpdb;

        return $wpdb->delete(
            self::$table_horaris,
            array('calendari_id' => $calendari_id),
            array('%d')
        );
    }

    // =========================================================================
    // MÈTODES PER EXCEPCIONS
    // =========================================================================

    /**
     * Afegir excepció
     */
    public static function afegir_excepcio($dades) {
        global $wpdb;

        $result = $wpdb->insert(
            self::$table_excepcions,
            $dades,
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Obtenir excepcions d'un calendari per rang de dates
     */
    public static function obtenir_excepcions($calendari_id, $data_inici = null, $data_fi = null) {
        global $wpdb;

        $sql = "SELECT * FROM " . self::$table_excepcions . " WHERE calendari_id = %d";
        $values = array($calendari_id);

        if ($data_inici) {
            $sql .= " AND data >= %s";
            $values[] = $data_inici;
        }

        if ($data_fi) {
            $sql .= " AND data <= %s";
            $values[] = $data_fi;
        }

        $sql .= " ORDER BY data";

        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }

    /**
     * Eliminar excepció
     */
    public static function eliminar_excepcio($id) {
        global $wpdb;

        return $wpdb->delete(
            self::$table_excepcions,
            array('id' => $id),
            array('%d')
        );
    }

    // =========================================================================
    // MÈTODES PER RESERVES
    // =========================================================================

    /**
     * Crear reserva
     */
    public static function crear_reserva($calendari_id, $inscripcio_id, $data, $hora) {
        global $wpdb;

        $result = $wpdb->insert(
            self::$table_reserves,
            array(
                'calendari_id' => $calendari_id,
                'inscripcio_id' => $inscripcio_id,
                'data' => $data,
                'hora' => $hora,
            ),
            array('%d', '%d', '%s', '%s')
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Comptar reserves per una franja horària
     */
    public static function comptar_reserves($calendari_id, $data, $hora) {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . self::$table_reserves . "
                WHERE calendari_id = %d AND data = %s AND hora = %s",
                $calendari_id, $data, $hora
            )
        );
    }

    /**
     * Obtenir reserves per data
     */
    public static function obtenir_reserves_per_data($calendari_id, $data) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT hora, COUNT(*) as total FROM " . self::$table_reserves . "
                WHERE calendari_id = %d AND data = %s
                GROUP BY hora",
                $calendari_id, $data
            )
        );
    }

    /**
     * Eliminar reserva per inscripció
     */
    public static function eliminar_reserva_per_inscripcio($inscripcio_id) {
        global $wpdb;

        return $wpdb->delete(
            self::$table_reserves,
            array('inscripcio_id' => $inscripcio_id),
            array('%d')
        );
    }

    // =========================================================================
    // MÈTODES AUXILIARS
    // =========================================================================

    /**
     * Obtenir IP del client
     */
    private static function get_client_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }

        return $ip;
    }

    /**
     * Comprovar si una franja té places disponibles
     */
    public static function te_places_disponibles($calendari_id, $data, $hora) {
        $calendari = self::obtenir_calendari($calendari_id);

        if (!$calendari) {
            return false;
        }

        $reserves = self::comptar_reserves($calendari_id, $data, $hora);

        return $reserves < $calendari->places_per_franja;
    }

    /**
     * Obtenir franges disponibles per un dia
     */
    public static function obtenir_franges_disponibles($calendari_id, $data) {
        global $wpdb;

        $calendari = self::obtenir_calendari($calendari_id);
        if (!$calendari) {
            return array();
        }

        // Obtenir dia de la setmana (1=Dilluns, 7=Diumenge)
        $dia_setmana = date('N', strtotime($data));

        // Obtenir horaris recurrents per aquest dia
        $horaris = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::$table_horaris . "
                WHERE calendari_id = %d AND dia_setmana = %d AND actiu = 1
                ORDER BY hora_inici",
                $calendari_id, $dia_setmana
            )
        );

        // Comprovar excepcions per aquesta data
        $excepcions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::$table_excepcions . "
                WHERE calendari_id = %d AND data = %s",
                $calendari_id, $data
            )
        );

        $franges = array();

        // Processar excepcions
        $dia_cancelat = false;
        $horaris_extres = array();

        foreach ($excepcions as $exc) {
            if ($exc->tipus === 'cancel_lar' && empty($exc->hora_inici)) {
                // Cancel·lació de tot el dia
                $dia_cancelat = true;
            } elseif ($exc->tipus === 'afegir') {
                // Afegir horari extra
                $horaris_extres[] = $exc;
            }
        }

        if ($dia_cancelat) {
            return array();
        }

        // Processar horaris recurrents
        foreach ($horaris as $horari) {
            // Comprovar si aquest horari específic està cancel·lat
            $cancelat = false;
            foreach ($excepcions as $exc) {
                if ($exc->tipus === 'cancel_lar' && $exc->hora_inici === $horari->hora_inici) {
                    $cancelat = true;
                    break;
                }
            }

            if (!$cancelat) {
                $reserves = self::comptar_reserves($calendari_id, $data, $horari->hora_inici);
                $places_disponibles = $calendari->places_per_franja - $reserves;

                if ($places_disponibles > 0) {
                    $franges[] = array(
                        'hora_inici' => $horari->hora_inici,
                        'hora_fi' => $horari->hora_fi,
                        'places_disponibles' => $places_disponibles,
                        'places_totals' => $calendari->places_per_franja,
                    );
                }
            }
        }

        // Afegir horaris extres
        foreach ($horaris_extres as $extra) {
            $places = $extra->places_especials ?: $calendari->places_per_franja;
            $reserves = self::comptar_reserves($calendari_id, $data, $extra->hora_inici);
            $places_disponibles = $places - $reserves;

            if ($places_disponibles > 0) {
                $franges[] = array(
                    'hora_inici' => $extra->hora_inici,
                    'hora_fi' => $extra->hora_fi,
                    'places_disponibles' => $places_disponibles,
                    'places_totals' => $places,
                );
            }
        }

        // Ordenar per hora
        usort($franges, function($a, $b) {
            return strcmp($a['hora_inici'], $b['hora_inici']);
        });

        return $franges;
    }

    /**
     * Obtenir dies amb disponibilitat en un rang de dates
     */
    public static function obtenir_dies_disponibles($calendari_id, $data_inici, $data_fi) {
        $dies_disponibles = array();

        $current = new DateTime($data_inici);
        $end = new DateTime($data_fi);

        while ($current <= $end) {
            $data = $current->format('Y-m-d');
            $franges = self::obtenir_franges_disponibles($calendari_id, $data);

            if (!empty($franges)) {
                $dies_disponibles[$data] = count($franges);
            }

            $current->modify('+1 day');
        }

        return $dies_disponibles;
    }
}
