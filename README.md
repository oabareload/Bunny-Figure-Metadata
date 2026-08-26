# Bunny Figure Metadata

Plugin de WordPress, independiente y autónomo, para administrar la metadata estructurada de figuras y las entidades derivadas de esa metadata. Parte de la familia de plugins BunnyChase.

## Estado

Versión 1.0.0. Configuración global de metadatos + defaults globales + alta de nuevos metadatos + catálogos dinámicos + editor de metadata basado en chips de entidad + asociación de cada valor de entidad con un TAG de WordPress + información complementaria (URL y logo) por entidad + listado de posts asociados a cada valor de entidad + sincronización de TAGs únicamente al publicar/programar + bloque dinámico de Gutenberg para mostrar las características de la figura en el frontend con configuración global + enlace a TAG o enlace externo configurable por campo + bloque dinámico de Gutenberg con caché para mostrar un ranking compacto de entidades destacadas (pensado para el footer). No hay Bunny Score, cards, merge de entidades ni panel de salud en esta fase.

## ¿Qué hace?

### 1. Configuración global de metadatos

En **Figure Metadata > Settings** se administra la lista global de metadatos disponibles en el editor de posts. Cada fila permite definir:

- La **meta key** y el **nombre** visible del campo.
- El **valor por defecto** global, usado cuando el post todavía no tiene un valor guardado.
- Si el metadata funciona como **catálogo/entidad**.

La configuración inicial conserva los 12 campos actuales y marca como catálogos Manufacturer, Illustrator, Series y Character. Se pueden añadir nuevos metadatos desde Settings sin modificar código. Cualquier metadata marcada como catálogo utiliza la misma infraestructura existente de entidades: valores independientes, asociación con TAG, URL oficial, logo, conteo de posts y pantalla administrativa propia. Si no está marcada como catálogo, se muestra y guarda como metadata normal.

La migración inicial se ejecuta automáticamente al cargar el plugin, crea la configuración solo si todavía no existe y no modifica la metadata almacenada de los posts ni las asociaciones existentes.

### 2. Editor de metadata de figuras (post editor)

En el editor clásico de posts, añade una metabox **"🐰 Datos de la figura"** con los metadatos definidos globalmente en Settings. La configuración inicial incluye estos 12 campos:

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

Los campos que no están marcados como catálogo siguen siendo inputs de texto simples, como en el snippet original. Su valor por defecto procede exclusivamente de la configuración global.

Los campos marcados como catálogo se editan como **chips individuales** en lugar de un campo de texto libre. Inicialmente son Fabricante, Ilustrador, Serie y Personaje:

- Cada valor aparece como un chip removible: `[ Good Smile Company × ] [ Flare × ]`.
- La detección al confirmar la entrada compara con los **valores de entidad ya administrados** (los mismos que alimentan las secciones de administración), no con los TAGs de WordPress; no usa autocomplete.
- Si el texto escrito coincide (sin distinguir mayúsculas/minúsculas) con un valor existente, se reutiliza exactamente ese texto tal cual está guardado.
- Si no coincide con ninguno, se puede añadir igualmente como un valor nuevo.
- Los chips verdes son entidades existentes; los naranjas son posibles coincidencias y permiten aceptar (`✓`) o rechazar (`↶`) la sugerencia; los rojos son valores nuevos.
- Internamente, el valor se sigue guardando como el mismo texto plano separado por `&` de siempre (`"Good Smile Company & Flare"`) en un input oculto — el formato de almacenamiento no cambió, solo la forma de editarlo.

### 3. Sincronización metadata → TAGs (solo al publicar o programar)

Los metadatos marcados como catálogo se sincronizan con los tags (`post_tag`) del post. Inicialmente son Fabricante, Ilustrador, Serie y Personaje:

- Los valores múltiples dentro de un mismo campo se separan con `&` (no con coma), y cada uno se convierte en un tag independiente.
- El valor `No dice` es metadata válida pero **nunca** se convierte en tag.
- El plugin recuerda qué tags fueron creados automáticamente por esta sincronización (`_bunny_figure_managed_tag_ids`). Al editar la metadata, solo se añaden o quitan esos tags gestionados; **los tags que el usuario haya asignado manualmente nunca se tocan ni se eliminan**.
- La sincronización solo se ejecuta cuando el post pasa a **`publish`** (publicado) o **`future`** (programado); guardar como borrador no crea ni asocia ningún TAG. Es segura e idempotente: cuando un post programado pasa automáticamente de `future` a `publish`, volver a ejecutarla no duplica ni cambia el resultado.
- Para cada valor, la resolución del TAG sigue este orden:
  1. Si existe una asociación Metadata → TAG ya configurada (ver punto 4) y el tag todavía existe, se usa directamente.
  2. Si no, se busca un TAG existente con ese mismo nombre.
  3. Si existe, se usa **y la asociación se guarda** en `BFM_Entity_Tag_Map` para la próxima vez.
  4. Si tampoco existe, se crea un TAG nuevo, **también se guarda la asociación**, y se usa.

La metadata estructurada de los posts sigue siendo la fuente de verdad; los tags son una relación derivada, no al revés.

### 4. Administración de entidades

Un menú de administración ("Figure Metadata") con una sección por cada metadata marcada como catálogo. La configuración inicial contiene cuatro secciones:

- Fabricantes
- Ilustradores
- Series
- Personajes

Cada sección recorre la metadata existente de los posts publicados y muestra los valores únicos encontrados junto con el número de posts en los que aparece cada uno (por ejemplo, "Good Smile Company — 42 posts"). Los valores se obtienen directamente de la metadata estructurada, no de la lista de tags de WordPress. Cuando un campo tiene varios valores separados por `&` (p. ej. "Good Smile Company & Max Factory"), cada uno se trata como una fila y una entidad completamente independiente: cuenta, TAG, URL, logo y posts asociados por separado.

Las columnas **Nombre** y **Posts** son ordenables (ascendente/descendente) mediante los encabezados de la tabla; el orden se refleja en la URL (`orderby`, `order`) y se mantiene al recargar.

### 5. Asociación de entidad → TAG

Cada valor de entidad (por ejemplo, "Good Smile Company" en Fabricantes) puede asociarse con **un único TAG** de WordPress mediante un selector con autocompletado:

- Al escribir, se buscan TAGs (`post_tag`) existentes por nombre.
- Al seleccionar uno, la asociación se guarda automáticamente por AJAX — no hay botón "Guardar".
- El chip resultante (`[ Nombre del TAG × ]`) permite quitar la asociación con el botón `×`, también vía AJAX inmediata.
- La asociación se guarda por **`term_id`**, no por el nombre del tag: si el tag se renombra después, la asociación sigue siendo válida.
- La asociación es independiente de los posts: se guarda por *valor de entidad*, no por post.
- Esta misma asociación también se crea/actualiza automáticamente desde la sincronización al publicar (ver punto 2), no solo manualmente desde esta pantalla.

Esta asociación es un dato complementario administrado por el plugin; no modifica ni sustituye la sincronización metadata → tags por post.

### 6. Información complementaria por entidad: URL y Logo

Cada valor de entidad puede tener también:

- **URL oficial/externa**: un campo de texto que se guarda automáticamente por AJAX al perder el foco (si cambió), sin botón "Guardar".
- **Logo/imagen**: usando la Media Library de WordPress. El comportamiento replica la Imagen Destacada nativa: al seleccionar una imagen se ve su preview de inmediato, y sobre ella aparecen los botones **Reemplazar** y **Eliminar**.

Ambos datos pertenecen a la *entidad* (al valor de metadata), no a ningún post en particular, y se administran igual que la asociación de TAG: sin tabla nueva, guardado inmediato, independientes entre sí para cada valor `&`-separado.

**Nota de diseño:** tanto la asociación de TAG como la de URL/logo se identifican por el nombre textual del valor de entidad (p. ej. "Good Smile Company"). Esto es coherente con que la metadata de texto sigue siendo la fuente de verdad: no existe todavía un identificador de entidad independiente del texto.

### 7. Posts asociados a un valor de entidad

Al hacer clic en el número de la columna **Posts** de cualquier fila, se abre — dentro del propio plugin, reutilizando el header/tabs de BunnyChase — un listado paginado de los posts que contienen ese valor exacto en su metadata:

- El encabezado indica claramente qué entidad y qué valor se está filtrando (p. ej. "Fabricante: Good Smile Company").
- El filtro parte **directamente de la metadata estructurada**, no de TAGs: un post con `_bunny_figure_manufacturer = "Good Smile Company & Max Factory"` aparece tanto al consultar "Good Smile Company" como al consultar "Max Factory", de forma completamente independiente.
- La tabla muestra: miniatura (o un placeholder discreto si el post no tiene imagen destacada), título, categoría, etiquetas actuales del post (todas, no solo las gestionadas por el plugin), fecha de publicación, fecha de última modificación, y botones **Editar** / **Ver** que enlazan a las URLs nativas de WordPress.
- Esta pantalla es de **solo lectura**: no modifica metadata ni TAGs.
- Paginada (20 posts por página), con navegación Anterior/Siguiente.

### 8. Bloque dinámico "Características" (configuración global desde 1.0.0)

Un bloque de Gutenberg **dinámico/server-side** (`bunny-figure-metadata/features`) pensado para insertarse en patrones, que reemplaza la dependencia de un plugin externo usado anteriormente para mostrar las características de la figura. No guarda HTML en el post — solo la configuración del encabezado del bloque; el marcado se genera en cada render a partir de la metadata actual y de la configuración global del plugin.

En **Figure Metadata > Features** se administra la configuración global del bloque para todos los usos del bloque en el sitio:

- Qué campos aparecen en la lista.
- El orden de visualización.
- Prefijo, etiqueta HTML del prefijo, clase CSS del prefijo.
- Etiqueta HTML del valor y clase CSS del valor.
- Destino del enlace del valor: **TAG** o **Enlace externo** cuando ese campo tenga URL externa configurada.

La configuración del bloque no ofrece más overrides por campo ni compatibilidad con configuraciones antiguas; la lista global se usa en todas las instancias del bloque y se aplica igual en editor y frontend.

Muestra los 12 campos de metadata del post actual, por ejemplo:

```
📦 Características
📐 Escala: 1/3
📏 Altura: 650 mm
💴 Precio: 188,000 JPY
📅 Lanzamiento: Abril 2027
🧱 Material: Platinum Silicone & PVC & Resin & Fabric & Alloy
🏭 Fabricante: Infinity Studio
🗿 Escultor: No dice
🎨 Pintura: No dice
✏️ Ilustrador: No dice
🎮 Serie: Street Fighter 6
👤 Personaje: Cammy
🏷️ Versión: No dice
```

**Configuración disponible (panel del bloque):**

- Encabezado general: mostrar/ocultar, elemento HTML (H1–H6, p, div, span, strong, em), clase CSS adicional.
- Por cada uno de los 12 campos: mostrar/ocultar, prefijo, elemento HTML del prefijo, clase CSS del prefijo, elemento HTML del valor, clase CSS del valor.
- **Valor por defecto** para metadatos vacíos: configurable libremente (por ejemplo `No dice`, `N/A`, `Desconocido`, o vacío para no mostrar nada). Es puramente una representación visual: nunca se guarda en la metadata, nunca se convierte en una entidad, y nunca genera ni asocia un TAG. El literal `No dice` ya no está hardcodeado en el renderizado — solo sigue apareciendo cuando esa es la metadata real ya guardada (igual que antes).

**Entidades (Fabricante, Ilustrador, Serie, Personaje):**

- Reutiliza la estructura de entidades existente: cada valor `&`-separado se resuelve de forma independiente, exactamente igual que en el resto del plugin.
- Si el valor tiene una URL configurada (`BFM_Entity_Info_Map`), se muestra como enlace (`<a href="...">Valor</a>`); si no, como texto plano.
- El valor especial `No dice` nunca se trata como entidad (no se busca URL para él), igual que nunca genera un TAG.
- **No se muestran logos todavía** — queda para un sprint posterior.
- El bloque es de **solo lectura**: no crea entidades, TAGs, URLs ni ninguna otra información al renderizarse.

### 9. Bloque dinámico "Entidades destacadas" (nuevo en 0.7.0)

Un segundo bloque de Gutenberg **dinámico/server-side** (`bunny-figure-metadata/featured-entities`), pensado para colocarse **una sola vez en el área de widgets del footer** (editor de bloques de widgets) y aparecer así en todas las páginas del sitio. Muestra un ranking compacto de los valores más destacados de una entidad, en formato de **lista** (no cards ni collage):

```
Fabricantes destacados
[logo] Good Smile Company — 87
[logo] Max Factory — 54
[logo] FREEing — 42
[logo] ALTER — 31
[logo] Kotobukiya — 28
```

El logo se muestra en proporción 1:1 y alineado con la altura del texto (`width`/`height` en `em`), pensado para convivir bien dentro de una columna de footer.

**Configuración disponible (panel del bloque):**

- **Metadata**: Fabricante, Ilustrador, Serie o Personaje.
- **Cantidad** de entidades a mostrar (1–50).
- **Orden**: Posts ↓ (más posts primero), Posts ↑ (menos posts primero), Nombre A-Z, Nombre Z-A, o Aleatorio.
- **Mostrar logo**: Sí/No.
- **Tamaño del logo** (px): control numérico en el Inspector, 12–96 px, por defecto **24 px**. El logo siempre se renderiza en proporción 1:1 (`width`/`height` iguales, con `object-fit: contain` para no deformarlo) y queda alineado verticalmente con el texto de la fila sin importar el tamaño elegido.
- **Mostrar cantidad de posts**: Sí/No.
- **Enlazar al TAG asociado**: Sí/No — cuando está activo, el nombre enlaza directamente al archivo/tag nativo de WordPress, reutilizando el `term_id` ya asociado (ver punto 4); no se crean páginas nuevas para las entidades.
- **Título personalizado**, con **elemento HTML del título** (H2–H6) y **clase CSS** configurables. Si el título se deja vacío, se usa uno por defecto derivado del nombre de la entidad (p. ej. "Fabricantes destacados").

Reutiliza la infraestructura de entidades ya existente (`BFM_Entity_Repository` para el ranking, `BFM_Entity_Tag_Map` para el enlace al TAG, `BFM_Entity_Info_Map` para el logo): no se crea ningún sistema de entidades paralelo. El bloque es de **solo lectura**.

**Caché (obligatoria desde esta primera versión):**

- El ranking ya resuelto (nombres, conteos, URL de TAG, URL de logo) para cada configuración de bloque se guarda en un **transient de WordPress**, para no recalcularlo en cada visita del frontend.
- Órdenes deterministas (Posts ↓/↑, Nombre A-Z/Z-A): caché de **12 horas**. Orden **Aleatorio**: caché de **5 minutos**, para que efectivamente pueda rotar.
- La clave de caché depende de la metadata, la cantidad, el orden, y de si logos/enlace a TAG están activos (las opciones que determinan qué hay que calcular).
- **Invalidación perezosa (lazy) por versión**: al publicar o programar un post, se incrementa un contador de versión por cada uno de los cuatro campos de entidad (una única lectura/escritura en `wp_options`). Ninguna caché se recalcula en ese momento: las cachés bajo la versión anterior simplemente dejan de usarse y expiran solas según su propio TTL. El siguiente visitante regenera el resultado y lo vuelve a cachear.

## Rendimiento

- Cada sección de entidad resuelve sus valores con **una sola consulta agregada** a `wp_postmeta` (vía `$wpdb`), sin recorrer todos los posts con `WP_Query` y sin patrones N+1.
- El ordenamiento por nombre/posts se aplica en memoria sobre el resultado ya obtenido (`BFM_Entity_Repository::sort_values()`), sin consultas adicionales.
- Las asociaciones TAG de una sección se resuelven con **una sola consulta batch** (`get_terms` con `include`), nunca una consulta por fila.
- Las miniaturas de logo se resuelven precargando el caché de adjuntos con **una sola consulta** (`get_posts` con `post__in`); las llamadas posteriores a `wp_get_attachment_image_src()` usan ese caché.
- El listado de posts por valor de entidad resuelve todos los IDs coincidentes con la misma consulta agregada ya usada para contar valores, y luego usa un único `WP_Query` con `post__in` + `posts_per_page` para traer solo la página solicitada, evitando la consulta `SQL_CALC_FOUND_ROWS` adicional (`no_found_rows => true`).
- Las sugerencias de autocompletado del editor (chips) se precargan una sola vez por carga de pantalla de edición de post — 4 consultas agregadas (una por campo de entidad), reutilizando exactamente la misma consulta que ya usan las pantallas de administración de entidades. No hay peticiones AJAX por cada tecla escrita.
- La sincronización de TAGs ya no se ejecuta en cada guardado, solo cuando el post resulta publicado o programado.
- **(Desde 0.6.0)** El bloque "Características" es server-side puro: no hay JavaScript ni peticiones AJAX en el frontend. Por cada render, se hacen 12 lecturas de post meta (una por campo, todas del mismo post ya cargado en memoria por WordPress) más como máximo 4 lecturas de `get_option()` (una por campo de entidad presente, para resolver URLs — cacheadas por WordPress tras la primera lectura), sin importar cuántos valores `&`-separados tenga cada campo. El CSS del bloque (`blocks/features/style.css`) solo se carga en las páginas donde el bloque realmente se usa, gracias al registro vía `block.json`.
- **(Desde 0.7.0)** El bloque "Entidades destacadas" resuelve su ranking (una consulta agregada vía `BFM_Entity_Repository`, más como máximo una consulta batch `get_terms()` y un `get_posts()` de priming de attachments cuando el TAG o el logo están activados) **una sola vez por combinación de configuración**, gracias a la caché en transients: mientras la caché esté vigente, servir el bloque no ejecuta ninguna consulta a la base de datos, solo la lectura del transient. Su CSS (`blocks/featured-entities/style.css`) también se carga únicamente en las páginas donde el bloque se usa (incluyendo el área de widgets, ya que WordPress encola el estilo registrado de un bloque en cualquier contexto donde ese bloque se renderiza realmente, no solo en el contenido del post).
- No se usa cron, ni tablas de base de datos propias. Tanto la asociación entidad → TAG como la información complementaria (URL + logo) se guardan cada una en una única fila de `wp_options` (autoload desactivado). El ranking de "Entidades destacadas" se sirve desde transients (persistidos por WordPress en `wp_options` si no hay un object cache persistente instalado, o en el object cache si lo hay); su invalidación es un simple contador de versión (otra fila pequeña de `wp_options`), sin cron ni recalculo inmediato.

## Seguridad

- Guardado de metadata protegido con nonce, verificación de capability (`edit_post`) y sanitización (`sanitize_text_field`) de toda entrada, incluyendo el valor reconstruido por los chips.
- Pantallas de administración protegidas con `current_user_can( 'manage_options' )`.
- Los seis endpoints AJAX (búsqueda de TAG, asociar/quitar TAG, guardar URL, asociar/quitar logo) verifican capability y nonce antes de procesar cualquier solicitud.
- El endpoint de asociación de TAG valida que la entidad exista, que el `term_id` recibido sea un entero válido y que corresponda a un término real de `post_tag`.
- La sincronización al publicar valida el `post_type`, el estado resultante del post, `DOING_AUTOSAVE`, si es una revisión, y `current_user_can( 'edit_post', $post_id )`.
- **(Desde 0.6.0)** Los elementos HTML configurables del bloque (encabezado, prefijo, valor) se validan contra una lista blanca fija (`h1`–`h6`, `p`, `div`, `span`, `strong`, `em`); cualquier otro valor cae a `span` de forma segura, evitando que un atributo de bloque pueda inyectar una etiqueta arbitraria. Todo el contenido de metadata se escapa (`esc_html`) antes de mostrarse; las URLs de entidad se escapan con `esc_url()`.
- **(Desde 0.7.0)** El bloque "Entidades destacadas" valida la metadata configurada contra los cuatro meta keys de entidad reales, el orden contra una lista fija de valores permitidos, la cantidad se acota entre 1 y 50 (`absint()` + `max()`/`min()`), y el elemento HTML del título se valida contra una lista blanca (`h2`–`h6`); cualquier valor fuera de esas listas cae a un valor seguro por defecto. Nombres, URLs de TAG y URLs de logo se escapan (`esc_html`, `esc_url`) antes de renderizarse.
- Todo el output se escapa (`esc_html`, `esc_attr`, `esc_url`) en las vistas y en el metabox.

## Identidad visual

La administración usa el mismo lenguaje visual (`bunny-admin.css`, header, tabs) que el resto de la familia BunnyChase (Bunny Reactions, Bunny Magazine). El CSS es una copia propia del plugin, sin dependencia funcional hacia esos plugins. Los chips del editor de posts usan un estilo propio y autocontenido (no depende de `bunny-admin.css`, que no se carga en `post.php`/`post-new.php`). El bloque "Características" tiene su propio CSS mínimo y autocontenido (`blocks/features/style.css`), pensado para integrarse dentro de patrones de tema existentes en el frontend, no para replicar la identidad visual del panel de administración. El bloque "Entidades destacadas" sigue el mismo criterio: CSS propio y mínimo (`blocks/featured-entities/style.css`), pensado para integrarse en el frontend (típicamente el footer del tema), sin ninguna relación con la identidad visual del panel de administración.

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
│   ├── class-bfm-entity-info-map.php
│   ├── class-bfm-features-block.php
│   └── class-bfm-featured-entities-block.php
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
├── blocks/
│   ├── features/
│   │   ├── block.json
│   │   ├── index.js
│   │   └── style.css
│   └── featured-entities/
│       ├── block.json
│       ├── index.js
│       └── style.css
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

No implementado todavía, intencionalmente: Bunny Score, páginas públicas por entidad, SEO/schema markup, integraciones externas, sincronización con X, crawling de URLs, importadores, cron, REST API pública, tablas de base de datos nuevas, funcionalidad de merge de entidades, un identificador de entidad independiente del texto de metadata, presentación en cards/collage para "Entidades destacadas" (solo lista compacta en esta versión), y panel de salud/diagnóstico.
