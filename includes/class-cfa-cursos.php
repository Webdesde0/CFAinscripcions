<?php
/**
 * Classe de compatibilitat per Cursos
 *
 * Aquesta classe ara delega a CFA_Inscripcions_DB per mantenir compatibilitat
 * amb el codi existent. El CPT ja no s'utilitza.
 *
 * @package CFA_Inscripcions
 * @deprecated Usar CFA_Inscripcions_DB directament
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
        // El CPT ja no es registra - els cursos es gestionen amb taula personalitzada
    }

    /**
     * Obtenir cursos actius
     *
     * @deprecated Usar CFA_Inscripcions_DB::obtenir_cursos(array('actius_nomes' => true))
     */
    public static function obtenir_cursos_actius() {
        $cursos = CFA_Inscripcions_DB::obtenir_cursos(array('actius_nomes' => true));

        // Convertir objectes a arrays per compatibilitat amb codi antic
        $result = array();
        foreach ($cursos as $curs) {
            $result[] = array(
                'id'              => $curs->id,
                'nom'             => $curs->nom,
                'descripcio'      => $curs->descripcio,
                'descripcio_curta'=> $curs->descripcio,
                'calendari_id'    => $curs->calendari_id,
                'icona'           => '',
                'color'           => '#4CB963',
                'imatge'          => '',
            );
        }

        return $result;
    }

    /**
     * Obtenir curs per ID
     *
     * @deprecated Usar CFA_Inscripcions_DB::obtenir_curs($id)
     */
    public static function obtenir_curs($id) {
        $curs = CFA_Inscripcions_DB::obtenir_curs($id);

        if (!$curs) {
            return null;
        }

        // Convertir objecte a array per compatibilitat amb codi antic
        return array(
            'id'              => $curs->id,
            'nom'             => $curs->nom,
            'descripcio'      => $curs->descripcio,
            'descripcio_curta'=> $curs->descripcio,
            'calendari_id'    => $curs->calendari_id,
            'icona'           => '',
            'color'           => '#4CB963',
            'imatge'          => '',
        );
    }
}
