# Bunny Figure Metadata

Plugin de WordPress, independiente y autónomo, para administrar la metadata estructurada de figuras y las entidades derivadas de esa metadata. Parte de la familia de plugins BunnyChase.

## Estado

Versión 0.5.0. Base de administración de entidades + editor de metadata basado en chips de entidad + asociación de cada valor de entidad con un TAG de WordPress + información complementaria (URL y logo) por entidad + listado de posts asociados a cada valor de entidad + sincronización de TAGs únicamente al publicar/programar. No hay Bunny Score, páginas públicas, SEO, cron ni tablas propias en esta fase.

## ¿Qué hace?

### 1. Editor de metadata de figuras (post editor)

En el editor clásico de posts, añade una metabox **"🐰 Datos de la figura"** con 12 campos de metadata estructurada:

- `_bunny_figure_scale` — Escala
- `_bunny_figure_height` — Altura
- `_bunny_figure_price` — Precio
- `_bunny_figure_release_date` — Lanzamiento
- `_bunny_figure_material` — Material
- `_bunny_figure_manufacturer` — Fabricante
- `_bunny_figure_sculptor` — Escultor
- `_bunny_figure_paintwork` — Pintura
- `_bunny_figure_illustrator` — Ilustrador
- `_bunny_figure_series` — Serie
- `_bunny_figure_character` — Personaje
- `_bunny_figure_version` — Versión

Los 8 campos de datos sueltos (escala, altura, precio, etc.) siguen siendo un input de texto simple, como en el snippet original.

**(Desde 0.5.0)** Los 4 campos de entidad (Fabricante, Ilustrador, Serie, Personaje) se editan como **chips individuales** en lugar de un campo de texto libre:

- Cada valor aparece como un chip removible: `[ Good Smile Company × ] [ Flare × ]`.
- El autocompletado al escribir busca entre los **valores de entidad ya administrados** (los mismos que alimentan las cuatro secciones de administración), no entre los TAGs de WordPress.
- Si el texto escrito coincide (sin distinguir mayúsculas/minúsculas) con un valor existente, se reutiliza exactamente ese texto tal cual está guardado.
- Si no coincide con ninguno, se puede añadir igualmente como un valor nuevo (Enter, o si no hay ninguna sugerencia resaltada).
- Internamente, el valor se sigue guardando como el mismo texto plano separado por `&` de siempre (`"Good Smile Company & Flare"`) en un input oculto — el formato de almacenamiento no cambió, solo la forma de editarlo.

### 2. Sincronización metadata → TAGs (solo al publicar o programar)

Cuatro de los doce campos (Fabricante, Ilustrador, Serie, Personaje) se sincronizan con los tags (`post_tag`) del post:

- Los valores múltiples dentro de un mismo campo se separan con `&` (no con coma), y cada uno se convierte en un tag independiente.
- El valor `No dice` es metadata válida pero **nunca** se convierte en tag.
- El plugin recuerda qué tags fueron creados automáticamente por esta sincronización (`_bunny_figure_managed_tag_ids`). Al editar la metadata, solo se añaden o quitan esos tags gestionados; **los tags que el usuario haya asignado manualmente nunca se tocan ni se eliminan**.
- **(Desde 0.5.0)** La sincronización **ya no ocurre en cada guardado**. Solo se ejecuta cuando el post pasa a **`publish`** (publicado) o **`future`** (programado); guardar como borrador, o mientras se edita un post ya publicado antes de guardar, no crea ni asocia ningún TAG hasta que el post vuelva a guardarse en uno de esos dos estados. Es segura e idempotente: cuando un post programado pasa automáticamente de `future` a `publish`, volver a ejecutarla no duplica ni cambia el resultado.
- Para cada valor, la resolución del TAG sigue este orden:
  1. Si existe una asociación Metadata → TAG ya configurada (ver punto 4) y el tag todavía existe, se usa directamente.
  2. Si no, se busca un TAG existente con ese mismo nombre.
  3. Si existe, se usa **y la asociación se guarda** en `BFM_Entity_Tag_Map` para la próxima vez.
  4. Si tampoco existe, se crea un TAG nuevo, **también se guarda la asociación**, y se usa.

La metadata estructurada de los posts sigue siendo la fuente de verdad; los tags son una relación derivada, no al revés.

### 3. Administración de entidades

Un menú de administración ("Figure Metadata") con cuatro secciones:

- Fabricantes
- Ilustradores
- Series
- Personajes

Cada sección recorre la metadata existente de los posts publicados y muestra los valores únicos encontrados junto con el número de posts en los que aparece cada uno (por ejemplo, "Good Smile Company — 42 posts"). Los valores se obtienen directamente de la metadata estructurada, no de la lista de tags de WordPress. Cuando un campo tiene varios valores separados por `&` (p. ej. "Good Smile Company & Max Factory"), cada uno se trata como una fila y una entidad completamente independiente: cuenta, TAG, URL, logo y posts asociados por separado.

Las columnas **Nombre** y **Posts** son ordenables (ascendente/descendente) mediante los encabezados de la tabla; el orden se refleja en la URL (`orderby`, `order`) y se mantiene al recargar.

### 4. Asociación de entidad → TAG

Cada valor de entidad (por ejemplo, "Good Smile Company" en Fabricantes) puede asociarse con **un único TAG** de WordPress mediante un selector con autocompletado:

- Al escribir, se buscan TAGs (`post_tag`) existentes por nombre.
- Al seleccionar uno, la asociación se guarda automáticamente por AJAX — no hay botón "Guardar".
- El chip resultante (`[ Nombre del TAG × ]`) permite quitar la asociación con el botón `×`, también vía AJAX inmediata.
- La asociación se guarda por **`term_id`**, no por el nombre del tag: si el tag se renombra después, la asociación sigue siendo válida.
- La asociación es independiente de los posts: se guarda por *valor de entidad*, no por post.
- **(Desde 0.5.0)** Esta misma asociación también se crea/actualiza automáticamente desde la sincronización al publicar (ver punto 2), no solo manualmente desde esta pantalla.

Esta asociación es un dato complementario administrado por el plugin; no modifica ni sustituye la sincronización metadata → tags por post.

### 5. Información complementaria por entidad: URL y Logo

Cada valor de entidad puede tener también:

- **URL oficial/externa**: un campo de texto que se guarda automáticamente por AJAX al perder el foco (si cambió), sin botón "Guardar".
- **Logo/imagen**: usando la Media Library de WordPress. El comportamiento replica la Imagen Destacada nativa: al seleccionar una imagen se ve su preview de inmediato, y sobre ella aparecen los botones **Reemplazar** y **Eliminar**.

Ambos datos pertenecen a la *entidad* (al valor de metadata), no a ningún post en particular, y se administran igual que la asociación de TAG: sin tabla nueva, guardado inmediato, independientes entre sí para cada valor `&`-separado. No se ven afectados por los cambios de este Sprint.

**Nota de diseño:** tanto la asociación de TAG como la de URL/logo se identifican por el nombre textual del valor de entidad (p. ej. "Good Smile Company"). Esto es coherente con que la metadata de texto sigue siendo la fuente de verdad: no existe todavía un identificador de entidad independiente del texto.

### 6. Posts asociados a un valor de entidad

Al hacer clic en el número de la columna **Posts** de cualquier fila, se abre — dentro del propio plugin, reutilizando el header/tabs de BunnyChase — un listado paginado de los posts que contienen ese valor exacto en su metadata:

- El encabezado indica claramente qué entidad y qué valor se está filtrando (p. ej. "Fabricante: Good Smile Company").
- El filtro parte **directamente de la metadata estructurada**, no de TAGs: un post con `_bunny_figure_manufacturer = "Good Smile Company & Max Factory"` aparece tanto al consultar "Good Smile Company" como al consultar "Max Factory", de forma completamente independiente.
- La tabla muestra: miniatura (o un placeholder discreto si el post no tiene imagen destacada), título, categoría, etiquetas actuales del post (todas, no solo las gestionadas por el plugin), fecha de publicación, fecha de última modificación, y botones **Editar** / **Ver** que enlazan a las URLs nativas de WordPress.
- Esta pantalla es de **solo lectura**: no modifica metadata ni TAGs.
- Paginada (20 posts por página), con navegación Anterior/Siguiente.

## Rendimiento

- Cada sección de entidad resuelve sus valores con **una sola consulta agregada** a `wp_postmeta` (vía `$wpdb`), sin recorrer todos los posts con `WP_Query` y sin patrones N+1.
- El ordenamiento por nombre/posts se aplica en memoria sobre el resultado ya obtenido (`BFM_Entity_Repository::sort_values()`), sin consultas adicionales.
- Las asociaciones TAG de una sección se resuelven con **una sola consulta batch** (`get_terms` con `include`), nunca una consulta por fila.
- Las miniaturas de logo se resuelven precargando el caché de adjuntos con **una sola consulta** (`get_posts` con `post__in`); las llamadas posteriores a `wp_get_attachment_image_src()` usan ese caché.
- El listado de posts por valor de entidad resuelve todos los IDs coincidentes con la misma consulta agregada ya usada para contar valores, y luego usa un único `WP_Query` con `post__in` + `posts_per_page` para traer solo la página solicitada, evitando la consulta `SQL_CALC_FOUND_ROWS` adicional (`no_found_rows => true`).
- **(Desde 0.5.0)** Las sugerencias de autocompletado del editor (chips) se precargan **una sola vez por carga de pantalla** de edición de post — 4 consultas agregadas (una por campo de entidad), reutilizando exactamente la misma consulta que ya usan las pantallas de administración de entidades. No hay peticiones AJAX por cada tecla escrita: el filtrado del autocompletado ocurre en el navegador sobre esa lista ya cargada.
- **(Desde 0.5.0)** La sincronización de TAGs ya no se ejecuta en cada guardado, solo cuando el post resulta publicado o programado — menos trabajo en los guardados de borrador, que ahora no tocan tags ni la base de datos de términos en absoluto.
- No se usa cron, ni tablas de base de datos propias. Tanto la asociación entidad → TAG como la información complementaria (URL + logo) se guardan cada una en una única fila de `wp_options` (autoload desactivado).

## Seguridad

- Guardado de metadata protegido con nonce, verificación de capability (`edit_post`) y sanitización (`sanitize_text_field`) de toda entrada, incluyendo el valor reconstruido por los chips (sigue pasando por el mismo `save_metadata()` sin cambios).
- Pantallas de administración protegidas con `current_user_can( 'manage_options' )`.
- Los seis endpoints AJAX (búsqueda de TAG, asociar/quitar TAG, guardar URL, asociar/quitar logo) verifican capability y nonce antes de procesar cualquier solicitud.
- El endpoint de asociación de TAG valida que la entidad exista, que el `term_id` recibido sea un entero válido y que corresponda a un término real de `post_tag`.
- La sincronización al publicar valida el `post_type`, el estado resultante del post, `DOING_AUTOSAVE`, si es una revisión, y `current_user_can( 'edit_post', $post_id )`, exactamente igual que antes.
- Todo el output se escapa (`esc_html`, `esc_attr`, `esc_url`) en las vistas y en el metabox.

## Identidad visual

La administración usa el mismo lenguaje visual (`bunny-admin.css`, header, tabs) que el resto de la familia BunnyChase (Bunny Reactions, Bunny Magazine). El CSS es una copia propia del plugin, sin dependencia funcional hacia esos plugins. Los chips del editor de posts usan un estilo propio y autocontenido (no depende de `bunny-admin.css`, que no se carga en `post.php`/`post-new.php`), visualmente consistente con los chips de TAG ya usados en las pantallas de administración de entidades.

## Estructura del plugin

```
wp_figuremetadata/
├── bunny-figure-metadata.php
├── uninstall.php
├── includes/
│   ├── class-bfm-core.php
│   ├── class-bfm-meta-fields.php
│   ├── class-bfm-post-editor.php
│   ├── class-bfm-tag-sync.php
│   ├── class-bfm-entity-repository.php
│   ├── class-bfm-entity-tag-map.php
│   └── class-bfm-entity-info-map.php
├── admin/
│   ├── class-bfm-admin.php
│   ├── class-bfm-admin-header.php
│   ├── class-bfm-admin-ajax.php
│   └── views/
│       ├── manufacturers.php
│       ├── illustrators.php
│       ├── series.php
│       ├── characters.php
│       ├── partial-entity-list.php
│       └── entity-posts.php
└── assets/
    ├── css/
    │   ├── bunny-admin.css
    │   └── admin.css
    └── js/
        ├── entity-tag-select.js
        ├── entity-info.js
        └── post-editor-chips.js
```

## Archivos de referencia (raíz del proyecto)

- `bunny-figure-metadata-editor.php` — snippet original de producción, del que se migró la lógica del editor y de sincronización de tags. Se conserva sin modificar como referencia histórica.
- `bunny-metadata-scan.php` — herramienta CLI histórica de migración/export/import de metadata. No forma parte del plugin; se conserva sin modificar.

## Requisitos

- WordPress 6.0+
- PHP 8.2+

## Fuera de alcance (esta fase)

No implementado todavía, intencionalmente: Bunny Score, páginas públicas por entidad, SEO/schema markup, integraciones externas, sincronización con X, crawling de URLs, importadores, cron, REST API pública, tablas de base de datos nuevas, funcionalidad de merge de entidades, y un identificador de entidad independiente del texto de metadata.
