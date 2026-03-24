# CFA Inscripcions - Context del projecte

## Descripció

Plugin de WordPress (v1.2.1) per gestionar inscripcions a Centres de Formació d'Adults (CFA). Permet crear cursos, configurar calendaris amb franges horàries, i gestionar inscripcions d'alumnes amb un formulari públic multi-pas. Desenvolupat en català.

## Estructura de fitxers

```
cfa-inscripcions.php          # Fitxer principal del plugin (singleton). Constants, hooks, enqueue assets, rol "Professor CFA"
includes/
  class-cfa-inscripcions-db.php  # (~1287 línies) Capa de base de dades. Totes les operacions CRUD (inscripcions, cursos, calendaris, horaris, excepcions, reserves)
  class-cfa-formulari.php        # Formulari públic frontend (shortcode [cfa_inscripcio]). 3 passos: selecció curs → data/hora → dades personals. AJAX handlers.
  class-cfa-emails.php           # Sistema d'emails HTML: nova inscripció (admin), confirmació rebut, confirmació cita, cancel·lació
  class-cfa-cursos.php           # Classe legacy/compatibilitat (deprecated). Delega a CFA_Inscripcions_DB
admin/
  class-cfa-admin.php            # (~1952 línies) Panell d'administració WP. Menús, pàgines render, AJAX handlers admin
assets/
  js/public.js                   # JS frontend: navegació passos, calendari interactiu, franges horàries, submit AJAX
  js/admin.js                    # JS admin: confirmar/cancel·lar/eliminar inscripcions, gestió calendaris/horaris/excepcions
  css/public.css                 # Estils formulari públic
  css/admin.css                  # Estils panell admin
dist/
  cfa-inscripcions.zip           # Build distribuïble del plugin
preview.html                     # Preview estàtic del formulari
```

## Arquitectura

- **Patró Singleton** en totes les classes principals
- **Taules personalitzades** a la BD de WordPress (no CPTs):
  - `wp_cfa_inscripcions` - Inscripcions d'alumnes (nom, cognoms, DNI, email, telèfon, data_cita, hora_cita, estat). Constraint UNIQUE(curs_id, dni)
  - `wp_cfa_cursos` - Cursos (nom, descripció, calendari_id, ordre, actiu)
  - `wp_cfa_calendaris` - Configuració calendaris (places_per_franja, plac_maxim_dies)
  - `wp_cfa_horaris` - Horaris recurrents (calendari_id, dia_setmana 1-7, hora_inici, hora_fi, professor_id)
  - `wp_cfa_excepcions` - Excepcions puntuals (cancel·lar, afegir, modificar dies/hores)
  - `wp_cfa_reserves` - Places ocupades per data/hora. Constraint UNIQUE(calendari_id, inscripcio_id)
  - `wp_cfa_cursos_professors` - Relació many-to-many cursos ↔ professors
- **AJAX** per totes les interaccions (frontend i admin)
- **Nonce verification** a tots els endpoints AJAX
- **Rols**: `cfa_professor` (veure i gestionar inscripcions), `administrator` (tot)

## Funcionalitats clau

### Formulari públic (shortcode `[cfa_inscripcio]`)
- 3 passos: Selecció curs → Calendari/hora → Dades personals
- Calendari interactiu que mostra dies disponibles via AJAX
- Franges horàries dinàmiques segons calendari del curs
- Validació DNI/NIE espanyol (amb lletra de control)
- Honeypot anti-spam + rate limiting (10/hora per IP)
- Comprovació de duplicats per DNI/curs
- Emails automàtics a admin i alumne

### Panell admin
- **Inscripcions**: Llista filtrable (per curs, estat, cerca), detall, editar, canvi d'estat (pendent→confirmada→cancel·lada/no_presentat)
- **Cursos**: CRUD, assignació de calendari i professors (many-to-many)
- **Calendaris**: CRUD, configuració horaris recurrents per dia de setmana, excepcions puntuals
- **Configuració**: Email admin, nom centre, logo URL

### Estats d'inscripció
- `pendent` → `confirmada` (envia email confirmació cita)
- `pendent`/`confirmada` → `cancel_lada` (envia email cancel·lació amb motiu opcional)
- `confirmada` → `no_presentat`

## Dependències

- WordPress ≥ 5.0
- PHP ≥ 7.4
- jQuery (inclòs amb WP)
- No hi ha dependències npm ni composer

## Idioma

Tot el codi (variables, comentaris, UI) és en **català**. Utilitza el text domain `cfa-inscripcions` amb funcions i18n de WP (`__()`, `_e()`, `printf()`).

## Opcions de WordPress utilitzades

- `cfa_inscripcions_db_version` - Versió de l'esquema de BD
- `cfa_inscripcions_admin_email` - Email de l'administrador
- `cfa_inscripcions_nom_centre` - Nom del centre
- `cfa_inscripcions_logo_url` - URL del logo per emails

## Notes de desenvolupament

- No hi ha sistema de build (ni webpack ni res). Els fitxers JS/CSS es serveixen directament.
- No hi ha tests automatitzats.
- El fitxer `class-cfa-cursos.php` és deprecated i només existeix per compatibilitat. Tot passa per `CFA_Inscripcions_DB`.
- La pàgina admin renderitza HTML directament dins de mètodes PHP (no templates separats).
- El formulari de cursos admin usa POST directe (sense AJAX), la resta d'accions admin usen AJAX.
- El `preview.html` és un fitxer HTML estàtic de preview del formulari (no es fa servir en producció).
- El `dist/cfa-inscripcions.zip` és un build distribuïble.
