<?php
/**
 * Custom Post Type per als Cursos
 *
 * @package CFA_Inscripcions
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFA_Cursos {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array(__CLASS__, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_cfa_curs', array($this, 'save_meta_boxes'), 10, 2);

        // Columnes personalitzades a l'admin
        add_filter('manage_cfa_curs_posts_columns', array($this, 'add_admin_columns'));
        add_action('manage_cfa_curs_posts_custom_column', array($this, 'render_admin_columns'), 10, 2);
    }

    /**
     * Registrar el Custom Post Type
     */
    public static function register_post_type() {
        $labels = array(
            'name'                  => __('Cursos', 'cfa-inscripcions'),
            'singular_name'         => __('Curs', 'cfa-inscripcions'),
            'menu_name'             => __('Cursos', 'cfa-inscripcions'),
            'add_new'               => __('Afegir nou', 'cfa-inscripcions'),
            'add_new_item'          => __('Afegir nou curs', 'cfa-inscripcions'),
            'edit_item'             => __('Editar curs', 'cfa-inscripcions'),
            'new_item'              => __('Nou curs', 'cfa-inscripcions'),
            'view_item'             => __('Veure curs', 'cfa-inscripcions'),
            'search_items'          => __('Cercar cursos', 'cfa-inscripcions'),
            'not_found'             => __('No s\'han trobat cursos', 'cfa-inscripcions'),
            'not_found_in_trash'    => __('No s\'han trobat cursos a la paperera', 'cfa-inscripcions'),
            'all_items'             => __('Tots els cursos', 'cfa-inscripcions'),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => 'cfa-inscripcions',
            'query_var'           => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => null,
            'supports'            => array('title', 'editor', 'thumbnail'),
            'show_in_rest'        => true,
        );

        register_post_type('cfa_curs', $args);
    }

    /**
     * Afegir meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'cfa_curs_calendari',
            __('Configuració del curs', 'cfa-inscripcions'),
            array($this, 'render_meta_box_calendari'),
            'cfa_curs',
            'side',
            'high'
        );

        add_meta_box(
            'cfa_curs_opcions',
            __('Opcions addicionals', 'cfa-inscripcions'),
            array($this, 'render_meta_box_opcions'),
            'cfa_curs',
            'normal',
            'default'
        );
    }

    /**
     * Renderitzar meta box del calendari
     */
    public function render_meta_box_calendari($post) {
        wp_nonce_field('cfa_curs_meta_box', 'cfa_curs_meta_box_nonce');

        $calendari_id = get_post_meta($post->ID, '_cfa_calendari_id', true);
        $actiu = get_post_meta($post->ID, '_cfa_curs_actiu', true);
        $ordre = get_post_meta($post->ID, '_cfa_curs_ordre', true);

        // Obtenir calendaris disponibles
        $calendaris = CFA_Inscripcions_DB::obtenir_calendaris();

        if ($actiu === '') {
            $actiu = '1';
        }
        if ($ordre === '') {
            $ordre = '0';
        }
        ?>
        <p>
            <label for="cfa_calendari_id">
                <strong><?php _e('Calendari assignat:', 'cfa-inscripcions'); ?></strong>
            </label>
        </p>
        <p>
            <select name="cfa_calendari_id" id="cfa_calendari_id" class="widefat">
                <option value=""><?php _e('-- Selecciona un calendari --', 'cfa-inscripcions'); ?></option>
                <?php foreach ($calendaris as $cal) : ?>
                    <option value="<?php echo esc_attr($cal->id); ?>" <?php selected($calendari_id, $cal->id); ?>>
                        <?php echo esc_html($cal->nom); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description">
            <?php _e('Selecciona el calendari que controla la disponibilitat d\'aquest curs.', 'cfa-inscripcions'); ?>
            <br>
            <a href="<?php echo admin_url('admin.php?page=cfa-calendaris'); ?>">
                <?php _e('Gestionar calendaris', 'cfa-inscripcions'); ?>
            </a>
        </p>

        <hr>

        <p>
            <label for="cfa_curs_actiu">
                <input type="checkbox" name="cfa_curs_actiu" id="cfa_curs_actiu" value="1" <?php checked($actiu, '1'); ?>>
                <strong><?php _e('Curs actiu', 'cfa-inscripcions'); ?></strong>
            </label>
        </p>
        <p class="description">
            <?php _e('Desmarca per ocultar aquest curs del formulari d\'inscripció.', 'cfa-inscripcions'); ?>
        </p>

        <hr>

        <p>
            <label for="cfa_curs_ordre">
                <strong><?php _e('Ordre:', 'cfa-inscripcions'); ?></strong>
            </label>
        </p>
        <p>
            <input type="number" name="cfa_curs_ordre" id="cfa_curs_ordre" value="<?php echo esc_attr($ordre); ?>" class="small-text" min="0">
        </p>
        <p class="description">
            <?php _e('Ordre de visualització al formulari (menor = primer).', 'cfa-inscripcions'); ?>
        </p>
        <?php
    }

    /**
     * Renderitzar meta box d'opcions
     */
    public function render_meta_box_opcions($post) {
        $icona = get_post_meta($post->ID, '_cfa_curs_icona', true);
        $color = get_post_meta($post->ID, '_cfa_curs_color', true);
        $descripcio_curta = get_post_meta($post->ID, '_cfa_curs_descripcio_curta', true);

        if (empty($color)) {
            $color = '#0073aa';
        }
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="cfa_curs_descripcio_curta"><?php _e('Descripció curta', 'cfa-inscripcions'); ?></label>
                </th>
                <td>
                    <input type="text" name="cfa_curs_descripcio_curta" id="cfa_curs_descripcio_curta"
                           value="<?php echo esc_attr($descripcio_curta); ?>" class="large-text"
                           placeholder="<?php _e('Breu descripció que es mostrarà al formulari', 'cfa-inscripcions'); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cfa_curs_icona"><?php _e('Icona (classe CSS)', 'cfa-inscripcions'); ?></label>
                </th>
                <td>
                    <input type="text" name="cfa_curs_icona" id="cfa_curs_icona"
                           value="<?php echo esc_attr($icona); ?>" class="regular-text"
                           placeholder="dashicons-welcome-learn-more">
                    <p class="description">
                        <?php _e('Classe CSS de la icona (ex: dashicons-welcome-learn-more). Deixa buit per no mostrar icona.', 'cfa-inscripcions'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cfa_curs_color"><?php _e('Color accent', 'cfa-inscripcions'); ?></label>
                </th>
                <td>
                    <input type="color" name="cfa_curs_color" id="cfa_curs_color"
                           value="<?php echo esc_attr($color); ?>">
                    <p class="description">
                        <?php _e('Color d\'accent per aquest curs al formulari.', 'cfa-inscripcions'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Guardar meta boxes
     */
    public function save_meta_boxes($post_id, $post) {
        // Verificar nonce
        if (!isset($_POST['cfa_curs_meta_box_nonce']) ||
            !wp_verify_nonce($_POST['cfa_curs_meta_box_nonce'], 'cfa_curs_meta_box')) {
            return;
        }

        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Verificar autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Guardar calendari
        if (isset($_POST['cfa_calendari_id'])) {
            update_post_meta($post_id, '_cfa_calendari_id', sanitize_text_field($_POST['cfa_calendari_id']));
        }

        // Guardar estat actiu
        $actiu = isset($_POST['cfa_curs_actiu']) ? '1' : '0';
        update_post_meta($post_id, '_cfa_curs_actiu', $actiu);

        // Guardar ordre
        if (isset($_POST['cfa_curs_ordre'])) {
            update_post_meta($post_id, '_cfa_curs_ordre', absint($_POST['cfa_curs_ordre']));
        }

        // Guardar opcions addicionals
        if (isset($_POST['cfa_curs_descripcio_curta'])) {
            update_post_meta($post_id, '_cfa_curs_descripcio_curta', sanitize_text_field($_POST['cfa_curs_descripcio_curta']));
        }

        if (isset($_POST['cfa_curs_icona'])) {
            update_post_meta($post_id, '_cfa_curs_icona', sanitize_text_field($_POST['cfa_curs_icona']));
        }

        if (isset($_POST['cfa_curs_color'])) {
            update_post_meta($post_id, '_cfa_curs_color', sanitize_hex_color($_POST['cfa_curs_color']));
        }
    }

    /**
     * Afegir columnes personalitzades
     */
    public function add_admin_columns($columns) {
        $new_columns = array();

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            if ($key === 'title') {
                $new_columns['calendari'] = __('Calendari', 'cfa-inscripcions');
                $new_columns['actiu'] = __('Actiu', 'cfa-inscripcions');
                $new_columns['ordre'] = __('Ordre', 'cfa-inscripcions');
            }
        }

        return $new_columns;
    }

    /**
     * Renderitzar columnes personalitzades
     */
    public function render_admin_columns($column, $post_id) {
        switch ($column) {
            case 'calendari':
                $calendari_id = get_post_meta($post_id, '_cfa_calendari_id', true);
                if ($calendari_id) {
                    $calendari = CFA_Inscripcions_DB::obtenir_calendari($calendari_id);
                    if ($calendari) {
                        echo esc_html($calendari->nom);
                    } else {
                        echo '<span style="color:#999;">' . __('Calendari no trobat', 'cfa-inscripcions') . '</span>';
                    }
                } else {
                    echo '<span style="color:#999;">' . __('Cap', 'cfa-inscripcions') . '</span>';
                }
                break;

            case 'actiu':
                $actiu = get_post_meta($post_id, '_cfa_curs_actiu', true);
                if ($actiu === '1' || $actiu === '') {
                    echo '<span style="color:green;">✓</span>';
                } else {
                    echo '<span style="color:#999;">✗</span>';
                }
                break;

            case 'ordre':
                $ordre = get_post_meta($post_id, '_cfa_curs_ordre', true);
                echo esc_html($ordre ?: '0');
                break;
        }
    }

    /**
     * Obtenir cursos actius
     */
    public static function obtenir_cursos_actius() {
        $args = array(
            'post_type'      => 'cfa_curs',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_cfa_curs_actiu',
                    'value'   => '1',
                    'compare' => '=',
                ),
                array(
                    'key'     => '_cfa_curs_actiu',
                    'compare' => 'NOT EXISTS',
                ),
            ),
            'meta_key'       => '_cfa_curs_ordre',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
        );

        $query = new WP_Query($args);

        $cursos = array();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $cursos[] = array(
                    'id'              => $post_id,
                    'nom'             => get_the_title(),
                    'descripcio'      => get_the_content(),
                    'descripcio_curta'=> get_post_meta($post_id, '_cfa_curs_descripcio_curta', true),
                    'calendari_id'    => get_post_meta($post_id, '_cfa_calendari_id', true),
                    'icona'           => get_post_meta($post_id, '_cfa_curs_icona', true),
                    'color'           => get_post_meta($post_id, '_cfa_curs_color', true) ?: '#0073aa',
                    'imatge'          => get_the_post_thumbnail_url($post_id, 'thumbnail'),
                );
            }
            wp_reset_postdata();
        }

        return $cursos;
    }

    /**
     * Obtenir curs per ID
     */
    public static function obtenir_curs($id) {
        $post = get_post($id);

        if (!$post || $post->post_type !== 'cfa_curs') {
            return null;
        }

        return array(
            'id'              => $post->ID,
            'nom'             => $post->post_title,
            'descripcio'      => $post->post_content,
            'descripcio_curta'=> get_post_meta($post->ID, '_cfa_curs_descripcio_curta', true),
            'calendari_id'    => get_post_meta($post->ID, '_cfa_calendari_id', true),
            'icona'           => get_post_meta($post->ID, '_cfa_curs_icona', true),
            'color'           => get_post_meta($post->ID, '_cfa_curs_color', true) ?: '#0073aa',
            'imatge'          => get_the_post_thumbnail_url($post->ID, 'thumbnail'),
        );
    }
}
