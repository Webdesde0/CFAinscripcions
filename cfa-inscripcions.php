<?php
/**
 * Plugin Name: CFA Inscripcions
 * Plugin URI: https://github.com/Webdesde0/CFAinscripcions
 * Description: Plugin de gestió d'inscripcions per a Centres de Formació d'Adults (CFA). Permet crear cursos i gestionar inscripcions amb formulari públic.
 * Version: 1.0.0
 * Author: CFA
 * Author URI: https://github.com/Webdesde0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cfa-inscripcions
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Evitar accés directe
if (!defined('ABSPATH')) {
    exit;
}

// Definir constants del plugin
define('CFA_INSCRIPCIONS_VERSION', '1.0.0');
define('CFA_INSCRIPCIONS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CFA_INSCRIPCIONS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CFA_INSCRIPCIONS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Classe principal del plugin
 */
class CFA_Inscripcions {

    /**
     * Instància única del plugin
     */
    private static $instance = null;

    /**
     * Obtenir instància única (Singleton)
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Carregar dependències
     */
    private function load_dependencies() {
        // Classes del plugin
        require_once CFA_INSCRIPCIONS_PLUGIN_DIR . 'includes/class-cfa-cursos.php';
        require_once CFA_INSCRIPCIONS_PLUGIN_DIR . 'includes/class-cfa-inscripcions-db.php';
        require_once CFA_INSCRIPCIONS_PLUGIN_DIR . 'includes/class-cfa-formulari.php';
        require_once CFA_INSCRIPCIONS_PLUGIN_DIR . 'includes/class-cfa-emails.php';

        // Admin
        if (is_admin()) {
            require_once CFA_INSCRIPCIONS_PLUGIN_DIR . 'admin/class-cfa-admin.php';
        }
    }

    /**
     * Inicialitzar hooks
     */
    private function init_hooks() {
        // Activació i desactivació
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Inicialització
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Enqueue scripts i styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Activació del plugin
     */
    public function activate() {
        // Crear taula d'inscripcions
        CFA_Inscripcions_DB::create_tables();

        // Registrar CPT per flush rewrite rules
        CFA_Cursos::register_post_type();
        flush_rewrite_rules();
    }

    /**
     * Desactivació del plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Inicialització
     */
    public function init() {
        // Inicialitzar classes
        CFA_Cursos::get_instance();
        CFA_Inscripcions_DB::get_instance();
        CFA_Formulari::get_instance();
        CFA_Emails::get_instance();

        if (is_admin()) {
            CFA_Admin::get_instance();
        }
    }

    /**
     * Carregar traduccions
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'cfa-inscripcions',
            false,
            dirname(CFA_INSCRIPCIONS_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Enqueue assets públics
     */
    public function enqueue_public_assets() {
        wp_enqueue_style(
            'cfa-inscripcions-public',
            CFA_INSCRIPCIONS_PLUGIN_URL . 'assets/css/public.css',
            array(),
            CFA_INSCRIPCIONS_VERSION
        );

        wp_enqueue_script(
            'cfa-inscripcions-public',
            CFA_INSCRIPCIONS_PLUGIN_URL . 'assets/js/public.js',
            array('jquery'),
            CFA_INSCRIPCIONS_VERSION,
            true
        );

        wp_localize_script('cfa-inscripcions-public', 'cfaInscripcions', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cfa_inscripcions_nonce'),
            'messages' => array(
                'enviando' => __('Enviant...', 'cfa-inscripcions'),
                'error' => __('Hi ha hagut un error. Torna-ho a provar.', 'cfa-inscripcions'),
                'exito' => __('Inscripció realitzada correctament!', 'cfa-inscripcions'),
            ),
        ));
    }

    /**
     * Enqueue assets admin
     */
    public function enqueue_admin_assets($hook) {
        // Només a les pàgines del plugin
        if (strpos($hook, 'cfa-inscripcions') === false && get_post_type() !== 'cfa_curs') {
            return;
        }

        wp_enqueue_style(
            'cfa-inscripcions-admin',
            CFA_INSCRIPCIONS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CFA_INSCRIPCIONS_VERSION
        );

        wp_enqueue_script(
            'cfa-inscripcions-admin',
            CFA_INSCRIPCIONS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            CFA_INSCRIPCIONS_VERSION,
            true
        );
    }
}

// Iniciar el plugin
function cfa_inscripcions() {
    return CFA_Inscripcions::get_instance();
}

// Arrencar!
cfa_inscripcions();
