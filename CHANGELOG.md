# Changelog

Todos los cambios notables de este proyecto se documentan en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/).

## [0.5.0] - 2026-08-04

### Añadido

- Editor de metadata basado en chips para los cuatro campos de entidad (Fabricante, Ilustrador, Serie, Personaje): cada valor se edita como un chip removible individualmente, en lugar de un campo de texto libre.
- Autocompletado del editor basado en los valores de entidad ya administrados (`BFM_Entity_Repository`), no en los TAGs de WordPress; si el texto escrito coincide con un valor existente (sin distinguir mayúsculas), se reutiliza exactamente ese texto.
- `assets/js/post-editor-chips.js`: UI de chips + autocompletado local (sin AJAX por tecla), reemplazando el script inline anterior basado en `ajax-tag-search`.
- `BFM_Post_Editor::get_entity_suggestions()`: precarga las sugerencias de los 4 campos reutilizando la misma consulta agregada que ya usan las pantallas de administración de entidades.

### Cambiado

- **La sincronización metadata → TAG ya no se ejecuta en cada guardado.** Ahora solo corre cuando el post resulta en estado `publish` o `future` (programado); guardar como borrador nunca crea ni asocia tags. El cambio se implementó como una comprobación de estado al inicio de `BFM_Tag_Sync::sync_tags()` (sigue enganchado a `save_post_post`, sin mover el hook a `transition_post_status`, para evitar leer metadata desactualizada — ver docblock de la clase para el detalle). Es idempotente ante la auto-publicación de posts programados (`future` → `publish`), que WordPress gestiona re-disparando `save_post_post` a través de `wp_publish_post()`.
- La resolución de TAGs durante la sincronización ahora **persiste automáticamente** la asociación Metadata → TAG en `BFM_Entity_Tag_Map` cuando se resuelve por fallback (tag encontrado por nombre, o creado); antes solo se usaba una asociación ya existente sin generar una nueva.
- Metabox del editor: se reemplazó el marcado de los 4 campos de entidad (input de texto + autocompletado sobre tags) por el nuevo campo de chips (`bfm-figure-chip-field`). El formato de almacenamiento interno (texto separado por `&` en el mismo campo POST) no cambió, por lo que `save_metadata()` no requirió modificaciones.

### No incluido en esta versión

Bunny Score, páginas públicas por entidad, SEO/schema markup, integraciones externas, sincronización con X, crawling de URLs, importadores, cron, tablas de base de datos nuevas, REST API pública, funcionalidad de merge de entidades, y un identificador de entidad independiente del texto de metadata.

## [0.4.0] - 2026-08-03

### Añadido

- Listado de posts asociados a un valor de entidad: al hacer clic en el número de la columna "Posts" en cualquiera de las cuatro secciones, se abre una pantalla dentro del plugin con los posts que contienen ese valor exacto en su metadata.
- El encabezado de la pantalla indica la entidad y el valor exacto que se está filtrando (p. ej. "Fabricante: Good Smile Company").
- La tabla muestra miniatura (o placeholder si no hay imagen destacada), título, categoría, etiquetas actuales del post (todas, no solo las gestionadas por el plugin), fecha de publicación, fecha de última modificación, y botones Editar/Ver hacia las URLs nativas de WordPress.
- Paginación (20 posts por página) con navegación Anterior/Siguiente.
- Nueva vista genérica `admin/views/entity-posts.php`, compartida por las cuatro entidades (recibe entidad/meta_key/valor, sin lógica ni queries propias).
- `BFM_Entity_Repository::get_matching_post_ids()` y `get_posts_for_value()`: resuelven qué posts contienen un valor exacto, tratando cada valor separado por `&` de forma independiente (un post con "A & B" aparece al consultar "A" y al consultar "B").
- `BFM_Admin::build_post_row()`: construye los datos de cada fila (título, miniatura, categorías, etiquetas, fechas, enlaces) a partir de un `WP_Post`.

### Cambiado

- `BFM_Entity_Repository`: la consulta compartida que alimenta el conteo de valores se extrajo a un método privado único (`raw_meta_rows()`), reutilizado ahora también por la resolución de posts por valor. Sin cambios de comportamiento en `get_values_with_counts()`.
- La columna "Posts" en las cuatro tablas de entidades ahora es un enlace hacia el nuevo listado, en vez de texto plano.

### Rendimiento

- El listado de posts por valor usa como máximo 2 consultas totales, sin importar cuántos posts existan: la consulta agregada ya existente (compartida con el conteo de valores) para resolver los IDs coincidentes, y un único `WP_Query` con `post__in` + `posts_per_page` para traer solo la página solicitada. Se evita la consulta `SQL_CALC_FOUND_ROWS` de `WP_Query` (`no_found_rows => true`) calculando el total en PHP a partir de los IDs ya resueltos. Categorías y etiquetas se leen del caché de términos que `WP_Query` precarga para todo el resultado.

### No incluido en esta versión

Bunny Score, páginas públicas por entidad, SEO/schema markup, integraciones externas, sincronización con X, crawling de URLs, importadores, cron, tablas de base de datos nuevas, REST API pública, funcionalidad de merge de entidades, y un identificador de entidad independiente del texto de metadata.

## [0.3.0] - 2026-08-03

### Añadido

- Información complementaria por valor de entidad (Fabricante/Ilustrador/Serie/Personaje):
  - **URL oficial/externa**: campo de texto con guardado automático por AJAX al perder el foco (sin botón "Guardar"), validada con `FILTER_VALIDATE_URL`.
  - **Logo/imagen**: integración con la Media Library de WordPress (`wp.media`), replicando el comportamiento de la Imagen Destacada nativa — preview inmediato tras seleccionar, con Reemplazar y Eliminar sobre la imagen.
- `includes/class-bfm-entity-info-map.php`: almacenamiento de URL + logo por valor de entidad en una única fila de `wp_options` (`bfm_entity_info_map`, autoload desactivado), con el mismo esquema de clave (`meta_key => valor => datos`) que `BFM_Entity_Tag_Map`, y poda automática de entradas vacías.
- Endpoints AJAX nuevos (`bfm_set_entity_url`, `bfm_set_entity_image`, `bfm_remove_entity_image`), reutilizando el `guard()` (capability + nonce) y los validadores de entidad/valor ya existentes en `BFM_Admin_Ajax`.
- `BFM_Tag_Sync` ahora consulta primero la asociación Metadata → TAG existente (`BFM_Entity_Tag_Map`) para cada valor a sincronizar; si existe y el tag sigue existiendo, la usa. Si no existe (o el tag fue borrado), cae exactamente al comportamiento anterior (reutilizar por nombre o crear). Sin heurísticas nuevas.
- Resolución en batch de miniaturas de logo (`get_posts` + `wp_get_attachment_image_src`, una sola consulta de priming por pantalla), sin N+1.
- `assets/js/entity-info.js` y estilos nuevos en `assets/css/admin.css` (campo de URL, preview de logo, acciones Reemplazar/Eliminar), cargados únicamente en las pantallas del plugin junto con `wp_enqueue_media()`.

### Verificado (sin cambios de diseño)

- Se confirmó que `BFM_Entity_Info_Map` usa el mismo esquema de identificación por valor de entidad que `BFM_Entity_Tag_Map` (coherente, sin duplicar lógica), y que URL/logo se administran de forma independiente para cada valor separado por `&`, nunca sobre la cadena combinada.

### No incluido en esta versión

Bunny Score, páginas públicas por entidad, SEO/schema markup, integraciones externas, sincronización con X, crawling de URLs, importadores, cron, tablas de base de datos nuevas, REST API pública, y un identificador de entidad independiente del texto de metadata (renombrar/fusionar entidades sin perder sus asociaciones).

## [0.2.0] - 2026-08-02

### Añadido

- Asociación de cada valor de entidad (Fabricante/Ilustrador/Serie/Personaje) con un único TAG de WordPress, mediante un selector con autocompletado y guardado automático por AJAX (sin botón "Guardar"). La asociación se persiste por `term_id` (no por nombre), por lo que renombrar el TAG después no la invalida.
- Endpoints AJAX (`bfm_search_tags`, `bfm_set_entity_tag`, `bfm_remove_entity_tag`), protegidos con nonce y `current_user_can( 'manage_options' )`, con validación server-side de entidad, valor y `term_id`.
- Almacenamiento de las asociaciones entidad → TAG en una única fila de `wp_options` (`bfm_entity_tag_map`, autoload desactivado), sin tablas SQL nuevas.
- Ordenamiento de las tablas de entidades por Nombre o Posts (ascendente/descendente) mediante los encabezados de columna, con el estado reflejado en la URL (`orderby`, `order`).
- Resolución de las asociaciones TAG de una sección completa en una sola consulta batch (`get_terms` con `include`), sin N+1.
- Estilos nuevos (selector, chip de TAG, botón de quitar, estados de guardado/error, encabezados ordenables) en `assets/css/admin.css`, y `assets/js/entity-tag-select.js` cargado únicamente en las pantallas del plugin.

### Corregido

- (Incluido previamente en 0.1.1) Bug de doble renderizado en la sección Fabricantes: `register_menu()` generaba dos closures distintas para la misma página (top-level + primer submenú con el mismo slug), que WordPress enganchaba por separado al mismo hook interno. Se corrigió reutilizando una única instancia de closure por slug.
- Bug en `BFM_Entity_Repository::sort_values()`: al ordenar por posts en sentido descendente, el desempate por nombre se invertía incorrectamente (quedaba Z→A en vez de A→Z). El desempate por nombre ahora es siempre ascendente, independientemente de la dirección del ordenamiento primario.

### No incluido en esta versión

Bunny Score, páginas públicas por entidad, SEO/schema markup, integraciones externas, sincronización con X, crawling de URLs, importadores, cron, tablas de base de datos nuevas, REST API pública, y otros campos complementarios por entidad (URL de X, URL de website, imagen/logo).

## [0.1.1] - 2026-08-02

### Corregido

- Bug de doble renderizado en la sección Fabricantes de la administración ("Header / Tabla / Header / Tabla"), causado por dos registros distintos de callback (`add_menu_page()` y `add_submenu_page()`) resolviendo al mismo hook interno de WordPress con dos closures diferentes. Corregido en `admin/class-bfm-admin.php` reutilizando una única instancia de closure por slug de entidad. Ilustradores, Series y Personajes no estaban afectados y no se modificaron.

## [0.1.0] - 2026-08-02

### Añadido

- Plugin `Bunny Figure Metadata` creado como plugin de WordPress independiente y autónomo (`wp_figuremetadata/`).
- Metabox de edición de metadata estructurada de figuras en el editor clásico de posts, con los 12 campos existentes (`_bunny_figure_scale`, `_bunny_figure_height`, `_bunny_figure_price`, `_bunny_figure_release_date`, `_bunny_figure_material`, `_bunny_figure_manufacturer`, `_bunny_figure_sculptor`, `_bunny_figure_paintwork`, `_bunny_figure_illustrator`, `_bunny_figure_series`, `_bunny_figure_character`, `_bunny_figure_version`). Migrado sin cambios de comportamiento desde el Code Snippet de producción `bunny-figure-metadata-editor.php`.
- Sincronización automática de metadata → tags (`post_tag`) para los campos Fabricante, Ilustrador, Serie y Personaje, con separador `&` para valores múltiples, exclusión del valor `No dice`, y distinción entre tags gestionados automáticamente y tags asignados manualmente (los manuales nunca se eliminan al sincronizar).
- Pantalla de administración "Figure Metadata" con cuatro secciones (Fabricantes, Ilustradores, Series, Personajes), cada una mostrando los valores únicos encontrados en la metadata de los posts publicados junto con su conteo de posts.
- Consulta agregada única por entidad (vía `$wpdb`) para obtener valores únicos y conteos, sin recorrer todos los posts ni generar consultas N+1.
- Carga condicional de CSS de administración únicamente en las pantallas propias del plugin.
- Identidad visual compartida con la familia de plugins BunnyChase (header, tabs, variables `--bunny-*`), implementada de forma autónoma sin dependencia funcional hacia Bunny Reactions ni Bunny Magazine.

### No incluido en esta versión

Bunny Score, páginas públicas por entidad, SEO/schema markup, integraciones externas, sincronización con X, crawling de URLs, importadores, cron, caché, tablas de base de datos nuevas, REST API pública, y edición de información complementaria por entidad (TAG relacionado, URLs, logo).
