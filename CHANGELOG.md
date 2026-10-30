# Changelog

Todos los cambios notables de este proyecto se documentan en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/).

## [1.9.0] - 2026-10-30

### Añadido

- **Nuevo tipo de metadato Textarea**: Soporte para campos de texto extenso y multilínea (p. ej. historias, descripciones). Totalmente compatible con Unicode y preservación estricta de saltos de línea mediante sanitización con `sanitize_textarea_field()` y escape seguro con `esc_textarea()`.
- **Edición individual en post editor**: Los campos `textarea` se renderizan como áreas de texto amplias con redimensionamiento vertical habilitado y disposición a ancho completo en la cuadrícula de metabox (`.bfm-figure-meta-field--textarea`).
- **Edición masiva con modal accesible**: En la pantalla de mantenimiento de posts (**Figure Metadata > Posts**), los campos `textarea` se presentan de forma compacta con una vista previa truncada y un botón de edición. El modal dedicado (`#bfm-post-textarea-modal`) permite editar el contenido completo con confirmación y cancelación accesible mediante teclado (Escape) y retorno de foco, sin recargar ni alterar el flujo de guardado de la tarjeta.
- **Configuración individual de búsqueda (`searchable`)**: Propiedad booleana por metadato (desactivada por defecto) administrable desde **Figure Metadata > Settings** con el checkbox «Incluir este metadato en las búsquedas de WordPress».
- **Integración de búsquedas en el sitio (`BFM_Search`)**: Los metadatos marcados como `searchable` se incorporan a las búsquedas normales del sitio mediante consultas SQL preparadas con subconsultas correlacionadas `EXISTS` en `wp_postmeta`. Previene resultados duplicados, evita JOINs innecesarios en la consulta principal de posts, no altera búsquedas administrativas y descarta valores vacíos o nulos.
- **Nuevo bloque Gutenberg dinámico "Metadata Field" (`bunny-figure-metadata/metadata-field`)**: Componente genérico para renderizar en frontend y editor cualquier metadato registrado del post actual. Ofrece selección dinámica de metadatos, personalización u ocultamiento de etiqueta, etiquetas HTML configurables (`h1`–`h6`, `p`, `div`, `span`) de manera independiente para título y valor, clases CSS adicionales, formato seguro con conservación de saltos de línea (`nl2br` + `esc_html`) y opción de visibilidad «Mostrar únicamente si el metadato tiene contenido» que garantiza 0 bytes de HTML para posts sin contenido o patrones.
- **Suite de pruebas automatizadas (`wp_figuremetadata/tests/test-suite.php`)**: Verificación automatizada de 25 puntos que cubren metadatos, edición individual y masiva, búsquedas, bloque Gutenberg y rendimiento.

## [1.8.0] - 2026-10-23

### Añadido

- Nuevo atributo `layout` (`list` por defecto | `cards`) en el bloque **Entidades destacadas**, seleccionable en el selector **Diseño** del panel *Visualización*. No es un bloque nuevo: ambos diseños usan la misma lista ya resuelta y cacheada. Los bloques ya guardados no tienen el atributo y siguen mostrándose como lista, igual que cualquier valor no válido; cambiar el diseño no modifica ninguna otra opción.
- Diseño **Tarjetas**: una tarjeta por entidad en una cuadrícula CSS Grid (`repeat(auto-fill, minmax(min(100%, 220px), 1fr))`), sin columnas, filas ni alturas fijas y sin JavaScript. Cada tarjeta lleva logo (mismo tamaño 1:1 configurado), nombre completo sin truncar y conteo, según las opciones del bloque. Con TAG asociado y "Enlazar al TAG" activo, toda la tarjeta es un único `<a>` con `:hover` y `:focus-visible`; sin TAG es un contenedor no interactivo. Estilos acotados a `.bfm-featured-entities-block--cards` (variables `--bfm-fe-card-*`).

### Cambiado

- `BFM_Featured_Entities_Block`: el marcado del logo y del conteo se extrajo de `render_row()` a `render_logo_html()` / `render_count_html()` para compartirlo con las tarjetas. La salida de la lista es idéntica a la anterior. El diseño no forma parte de la clave de caché ni añade consultas.

## [1.7.0] - 2026-10-19

### Añadido

- Nueva **presentación "Tarjetas"** en el bloque **Características**, seleccionable en el panel *Presentación* del propio bloque (junto a la **Clásica**, que no cambia). No es un bloque nuevo ni duplica la obtención de metadata: ambas presentaciones usan la misma resolución de valores y solo difieren en el marcado.
- Atributos nuevos del bloque: `layout` (`classic` por defecto | `cards`) y `cardMinWidth` (ancho de referencia en px: 140 por defecto, rango 100–250). Los bloques ya guardados no tienen esos atributos y siguen renderizando la presentación clásica sin reconfigurarse. Ambos valores se validan en servidor; un ancho fuera de rango se acota y uno no numérico vuelve al valor por defecto.
- Cada tarjeta muestra el valor arriba y el nombre del campo abajo (el mismo prefijo configurado, sin los dos puntos finales), centrado, en una cuadrícula CSS Grid que se ajusta al espacio disponible sin desbordes ni altura fija. El valor se escapa y no se altera ni se trunca.
- Las entidades de un campo con logo activado muestran su imagen junto al nombre visible de la entidad (la imagen es decorativa, `alt` vacío, porque el texto adyacente ya la nombra), resuelta con `BFM_Entity_Info_Map::get_effective_image_ids()` y `get_logo_urls()` (una sola llamada por lote), incluida la clave de identidad compuesta de Personaje. Solo se resuelven en la presentación Tarjetas.
- **Valores múltiples (`&`) en Tarjetas:** si un campo tiene varios valores y al menos uno tiene imagen o un enlace válido, cada valor se muestra en su propia tarjeta, con solo su imagen y solo su enlace (asociados por el nombre del valor, nunca por posición) y con el nombre del campo abajo. Si ninguno tiene imagen ni enlace, siguen agrupados en una sola tarjeta con el formato `A & B` del listado clásico. Un campo con un único valor siempre es una sola tarjeta. La presentación Clásica no cambia.
- Cuando un valor tiene enlace, toda su tarjeta es un único `<a>` (con los mismos destino TAG/externo, firma opcional de WPAM, `target` y `rel` que el bloque clásico), con estados `:hover` y `:focus-visible`. Nunca hay enlaces anidados. Las tarjetas sin enlace no tienen efectos interactivos.
- Dentro de una misma petición de WordPress, la resolución de imágenes de Tarjetas se reutiliza (IDs efectivos por metadato y URLs por adjunto), de modo que varios bloques en una misma página (p. ej. un listado de posts) no repiten esas consultas. Es una memoria estática solo de la petición; no se persiste nada.
- Los campos sin valor, con `No dice` o sin valor por defecto no generan tarjeta (mismo criterio que el listado clásico).

### Cambiado

- `BFM_Features_Block`: la resolución de valores y enlaces se extrajo de `render()` / `render_entity_value()` a `resolve_field_parts()` / `resolve_entity_parts()` para compartirla entre presentaciones. La salida HTML de la presentación Clásica es idéntica a la anterior.

## [1.6.0] - 2026-09-28

### Añadido

- Integración opcional con WPAM en el bloque **Características**: los enlaces externos de entidad (cuando el destino del enlace del campo es "Enlace externo") ahora pasan por el interstitial/redirect `/goext/` de WPAM mediante `wpam_get_external_link_url()`.
- Solo se firman URLs realmente externas (`wp_is_internal_link()`). Se conserva la URL original si es interna, si WPAM no está activo o si WPAM devuelve una cadena vacía. No cambia ningún dato almacenado, el HTML del enlace (`target`, `rel`, clases, icono) ni los enlaces a TAG.

## [1.5.0] - 2026-09-18

### Añadido

- En las páginas individuales de cada entidad/característica (Fabricantes, Ilustradores, Series, Personajes y cualquier otro campo marcado como entidad), las columnas TAG, URL y Logo ahora resaltan con un borde rojo suave cuando no tienen ningún dato, para detectar de un vistazo qué información falta.
- El resaltado (`bfm-field-missing`) es únicamente una ayuda visual: no bloquea el guardado, no vuelve obligatorio ningún campo y no valida formato. Se actualiza al instante al escribir/asociar un valor y reaparece si se vuelve a borrar.

## [1.4.0] - 2026-09-17

### Añadido

- Cada tarjeta de la pantalla **Posts** ahora muestra, antes de los campos editables, una vista previa de solo lectura con la miniatura destacada del post (tamaño `thumbnail`) y su excerpt manual. Ninguno de los dos es editable ni forma parte del payload de guardado.
- Cuando el post no tiene imagen destacada se muestra un placeholder discreto; cuando no tiene excerpt manual se indica explícitamente "Sin excerpt." sin generarlo automáticamente a partir del contenido.
- Ambos datos viajan dentro de la misma respuesta AJAX que ya devuelve cada fila (`build_maintenance_row_for_post()`), sin consultas adicionales por tarjeta ni cambios en la paginación/infinite scroll existente.

## [1.3.0] - 2026-08-30

### Añadido

- Nueva pantalla **Posts** dentro de Figure Metadata para consultar y editar los metadatos BFM de los posts desde una sola interfaz.
- Búsqueda por título, metadato, estado del valor y valor concreto con carga inicial limitada a 20 posts y carga progresiva por AJAX/infinite scroll.
- Guardado AJAX por fila con validación de `post_id`, `current_user_can( 'edit_post', $post_id )`, nonce y reutilización de la sanitización de `BFM_Post_Editor` para todos los metadatos configurados.
- Los campos de catálogo reutilizan la experiencia de chips del editor para mantener el formato multi-valor `A & B` y actualizar las filas sin recargar la página.

### Corregido

- La consulta de valor vacío distingue `NOT EXISTS` de `valor vacío` usando `WP_Meta_Query`, sin SQL manual ni cambios de almacenamiento.

## [1.2.0] - 2026-08-30

### Añadido

- **Identidad compuesta para `character`**: dos figuras pueden compartir el mismo nombre de personaje (p. ej. `Eve`) y referirse a personajes distintos (`Eve` de *Stellar Blade* vs. un `Eve` original ilustrado por `rurudo`). El plugin ahora puede distinguirlos internamente sin cambiar el nombre mostrado, que sigue siendo simplemente `Eve`.
- Nueva sección en Settings, solo en la fila de **Personaje**: dos checkboxes ("Distinguir por Serie" / "Distinguir por Ilustrador") y un campo de texto para el valor que representa "personaje original" (por defecto `PERSONAJE ORIGINAL`, comparado sin distinguir mayúsculas ni espacios).
- Regla de identidad (fija, aplicada solo a `character`): `personaje + serie`; y cuando la serie coincide con el valor de "personaje original", también `+ ilustrador`. Ejemplo: `Eve + Stellar Blade` es una identidad distinta de `Eve + PERSONAJE ORIGINAL + rurudo`.
- `BFM_Character_Identity` (nueva clase, exclusiva de `character`): único lugar que construye (`build_identity_key()`) y desarma (`display_name()`) esa identidad interna (p. ej. `Eve|Stellar Blade`). No crea tablas, IDs ni migra metadata existente; la clave se calcula al vuelo a partir de la metadata ya guardada.
- La identidad compuesta ahora se usa consistentemente en `character`: conteo y agrupación de valores, listado de posts relacionados (drill-down), y **selección de imagen** (automática y manual) — una imagen asociada a `Eve + Stellar Blade` nunca se confunde con `Eve + PERSONAJE ORIGINAL + rurudo`. TAG y URL asociados siguen funcionando exactamente igual que antes (por el nombre plano del personaje), sin cambios.
- El bloque **Entidades destacadas** y el editor de metadata (chips) se actualizaron para seguir mostrando siempre el nombre plano del personaje, nunca la clave interna.

### Alcance

- Ningún otro metadato (`manufacturer`, `illustrator`, `series`, etc.) se ve afectado; el comportamiento de `character` sin cambios de configuración adicionales (ambos checkboxes activados por defecto) reproduce exactamente la regla descrita arriba.

## [1.1.1] - 2026-08-29

### Corregido

- El selector **Origen de imagen** en Settings ahora solo se muestra cuando **Usar logo** está activo, y se oculta al desactivarlo; sin cambios en los metadatos que ya usaban logos personalizados.
- El bloque **Entidades destacadas** solo resolvía el `image_id` guardado manualmente, por lo que un metadato configurado con origen "Imagen de un post relacionado" nunca mostraba su imagen automática (solo funcionaba si antes se fijaba una imagen manual). Ahora resuelve la imagen efectiva — manual si existe, automática si no — con el mismo servicio que ya usa la pantalla de la entidad.
- **Cambiar** en la imagen de un post relacionado ahora abre el selector de medios con la búsqueda pre-rellenada con el nombre exacto del valor del metadato (p. ej. `Hatsune Miku` para `character`), usando la búsqueda nativa de la Media Library; no se creó ningún sistema nuevo de indexación o relación.

### Añadido

- `BFM_Entity_Info_Map::get_effective_image_ids()`: único servicio compartido que resuelve la imagen que debe mostrarse por cada valor de un metadato (manual si existe, automática desde el post relacionado si no), usado ahora tanto por la pantalla de la entidad como por el bloque de Entidades destacadas — sin lógica duplicada.
- `BFM_Meta_Fields::image_source_for_meta_key()`: helper único para resolver el origen de imagen configurado a partir del meta_key.

### Cambiado

- Los controles de "Integraciones del catálogo" en Settings (Asociar a TAG / Permitir URL externa / Usar logo / Origen de imagen) se rediseñaron para ser compactos: checkboxes en una fila con ajuste de línea en lugar de una etiqueta por línea, y el selector de origen de imagen con altura y ancho reducidos. Sin cambios de estilo fuera de esta pantalla.

## [1.1.0] - 2026-08-28

### Añadido

- Nuevo origen de imagen configurable por metadato de catálogo (Settings > Origen de imagen): **Imagen personalizada (logo)** (comportamiento existente) o **Imagen de un post relacionado**. Disponible para cualquier metadato de catálogo; por ahora solo se usa con Personaje.
- Cuando el origen es "Imagen de un post relacionado", la imagen se selecciona automáticamente (imagen destacada del post relacionado más antiguo o, si no tiene, su primera imagen adjunta) y puede sobreescribirse manualmente desde la pantalla de la entidad, igual que el logo; la selección manual queda fija y ya no cambia automáticamente. Solo se guarda el ID del adjunto, nunca la URL.
- `BFM_Entity_Repository::get_related_image_attachment_ids()`: resuelve la imagen automática de cada valor de entidad reutilizando la relación valor → posts ya existente, con un máximo de tres consultas en total sin importar cuántos valores o posts existan.
- `BFM_Entity_Info_Map::get_display_image_html()`: genera el `<img>` de la imagen de una entidad con `wp_get_attachment_image()`, solicitando siempre el tamaño `thumbnail` o, si no existe para ese adjunto, el tamaño más pequeño realmente disponible (nunca `full`/`large`).

### Cambiado

- `BFM_Entity_Info_Map::get_logo_urls()` aplica ahora esa misma resolución de tamaño con reserva al tamaño más pequeño disponible cuando `thumbnail` no existe para un adjunto, en lugar de omitir la imagen; sin cambios para los adjuntos que ya tienen `thumbnail` (el caso habitual).

## [1.0.2] - 2026-08-26

### Añadido

- Valor por defecto configurable por campo en la página global de Features para representar metadatos vacíos.

## [1.0.1] - 2026-08-26

### Cambiado

- Se eliminó de Settings el control de valor por defecto de cada metadato.
- La configuración global de Features incluye ahora todos los metadatos definidos en Settings, incluidos los añadidos posteriormente.

## [1.0.0] - 2026-08-26

### Añadido

- Nueva configuración global del bloque de características, disponible desde la navegación del plugin, para decidir qué campos se muestran, en qué orden y cómo se renderizan cada prefijo/valor.
- Soporte para elegir destino del enlace de cada campo: TAG o enlace externo cuando ese campo dispone de URL configurada; el valor externo muestra un indicador visual con flecha integrada.

### Cambiado

- El bloque de características usa exclusivamente la configuración global del plugin; se eliminó la configuración individual por campo en el editor de Gutenberg.
- La resolución de enlaces del bloque prioriza la URL externa cuando está configurada como destino y añade un icono de enlace externo sin duplicar lógica ni afectar el resto del plugin.

## [0.9.0] - 2026-08-25

### Cambiado

- Los chips de los metadatos configurados como catálogos muestran visualmente si el valor ya existe (verde), es una posible coincidencia (naranja) o es nuevo (rojo).
- Las posibles coincidencias pueden aceptarse para reutilizar el nombre canónico existente o rechazarse para conservar el valor original; la detección usa normalización, palabras y distancia de edición sin cambiar el formato de almacenamiento.

## [0.8.0] - 2026-08-24

### Añadido

- Configuración global de metadatos en Settings: meta key, nombre visible, valor por defecto y modo catálogo/entidad.
- Alta de nuevos metadatos desde Settings, disponibles automáticamente en el editor de posts sin modificar código.
- Catálogos dinámicos que reutilizan la infraestructura existente de entidades, incluyendo sincronización con TAGs y administración de URL, logo, valores y posts asociados.
- Migración automática e idempotente de los 12 campos actuales, conservando Manufacturer, Illustrator, Series y Character como catálogos y sin modificar la metadata existente de los posts.

### Cambiado

- Los defaults de metadata se obtienen globalmente desde Settings; ya no se configuran por publicación.
- Los valores múltiples de cualquier metadata configurada como catálogo mantienen el formato existente separado por `&`.

## [0.7.0] - 2026-08-09

### Añadido

- Bloque dinámico de Gutenberg `bunny-figure-metadata/featured-entities` ("Bunny Figure: Entidades destacadas"), pensado para colocarse una sola vez en el área de widgets del footer (bloques) y aparecer en todas las páginas del sitio. Presentación en lista compacta (no cards ni collage): logo opcional en proporción 1:1 alineado con la altura del texto, nombre, y cantidad de posts.
- Panel de configuración completo: metadata a mostrar (Fabricante/Ilustrador/Serie/Personaje), cantidad de entidades, orden (Posts ↓, Posts ↑, Nombre A-Z, Nombre Z-A, Aleatorio), mostrar/ocultar logo, mostrar/ocultar cantidad de posts, enlazar o no al TAG asociado, título personalizado, elemento HTML del título (H2–H6) y clase CSS del título. Si no se configura un título personalizado, se usa uno por defecto derivado de la entidad (p. ej. "Fabricantes destacados").
- Reutiliza íntegramente la infraestructura de entidades ya existente: `BFM_Entity_Repository` para el ranking de valores y conteos, `BFM_Entity_Tag_Map` para el enlace al TAG asociado (reutilizando el `term_id` existente, sin crear páginas nuevas), y `BFM_Entity_Info_Map` para el logo. El bloque es de solo lectura: no crea entidades, TAGs, URLs ni logos.
- `includes/class-bfm-featured-entities-block.php` (`BFM_Featured_Entities_Block`): registro del bloque (mismo patrón sin build step que el bloque "Características") y `render_callback` con la lógica de ranking, resolución batch de TAGs/logos, y caché.
- `blocks/featured-entities/` (`block.json`, `index.js`, `style.css`): editor en JS plano contra los globals `wp.*`, con vista previa en vivo vía `<ServerSideRender>`.
- `BFM_Entity_Repository::sort_values_for_order()`: resuelve los cinco órdenes soportados (dos de posts, dos de nombre, y aleatorio), delegando los cuatro órdenes deterministas en el `sort_values()` ya existente — sin duplicar lógica de comparación — y añadiendo únicamente el manejo del orden aleatorio.
- `BFM_Entity_Info_Map::get_logo_urls()`: helper estático reutilizable que resuelve en batch las URLs de miniatura de una lista de attachment IDs (un solo `get_posts()` de priming + `wp_get_attachment_image_src()` por ID, sin N+1). Extraído de la lógica que ya existía dentro de `BFM_Admin::resolve_entity_info()`, que ahora reutiliza este mismo helper en vez de duplicarla — sin cambios de comportamiento en las pantallas de administración.

### Caché (nuevo en el plugin, solo para este bloque)

- El resultado ya ranqueado y listo para renderizar de cada configuración de bloque se guarda en un transient de WordPress (`bfm_fe_{hash}`), evitando recalcular el ranking en cada visita del frontend. La clave de caché depende de la metadata, la cantidad, el orden, y de si logos/enlace a TAG necesitan resolverse (las únicas opciones que afectan qué datos hay que calcular).
- Órdenes deterministas (Posts ↓/↑, Nombre A-Z/Z-A): TTL de 12 horas. Orden Aleatorio: TTL de 5 minutos, para que efectivamente pueda rotar.
- Invalidación perezosa (lazy) basada en versión, no en enumeración de cachés activas: una única opción pequeña (`bfm_featured_entities_cache_version`) guarda un contador de versión por cada uno de los cuatro meta keys de entidad, incluido en cada clave de caché. Al publicar o programar un post, se incrementan los cuatro contadores en una sola lectura/escritura (`get_option()`/`update_option()`), sin recalcular nada de inmediato. Las cachés previas bajo la versión anterior dejan de consultarse y expiran solas por su propio TTL, en vez de buscarse y borrarse una por una.
- La invalidación solo se dispara cuando el post resulta `publish` o `future` (mismo criterio que `BFM_Tag_Sync`, pero implementado en un hook propio de este bloque para no acoplar ambas responsabilidades); nunca en borradores, autosave o revisiones.
- No se usa cron ni ninguna tabla nueva: el ranking se sirve desde transients (WordPress los persiste en `wp_options` si no hay un object cache persistente instalado, o en el object cache si lo hay).

### No incluido en esta versión

Bunny Score, páginas públicas por entidad, SEO/schema markup, integraciones externas, sincronización con X, crawling de URLs, importadores, cron, tablas de base de datos nuevas, REST API pública, funcionalidad de merge de entidades, un identificador de entidad independiente del texto de metadata, presentación en cards/collage para "Entidades destacadas" (solo lista compacta en esta versión), y panel de salud/diagnóstico.

## [0.7.1] - 2026-08-10

### Corregido

- **Bloque "Entidades destacadas" no se podía seleccionar en el editor de Widgets.** Causa: el `edit()` del bloque envolvía el `<ServerSideRender>` en un `<div>` plano (`{ className: props.className }`) en vez de usar `blockEditor.useBlockProps()`. Con `apiVersion: 3`, el editor de bloques necesita que los props devueltos por `useBlockProps()` (que incluyen los manejadores de click/selección, `tabIndex`, `data-block`, etc.) se apliquen sobre el elemento raíz para poder vincular ese nodo del DOM con el bloque; sin ellos, el bloque se ve pero no se registra correctamente como seleccionable, algo más notorio en el editor de Widgets que en el editor de posts. Corregido en `blocks/featured-entities/index.js` reemplazando el wrapper por `el( 'div', useBlockProps(), ... )`. No se tocó el `render_callback` de PHP, el frontend ni la lógica de caché.

### Añadido

- **Tamaño del logo configurable** (`logoSize`, en px) en el bloque "Entidades destacadas": nuevo `RangeControl` en el panel "Visualización" del Inspector (12–96 px, por defecto **24 px**), sanitizado en `BFM_Featured_Entities_Block::sanitize_logo_size()` (entero, con `absint()`, cae al valor por defecto si es 0/no numérico, y se acota entre 12 y 96). El logo se sigue renderizando siempre en proporción 1:1 (mismo valor para `width`/`height`, tanto como atributos HTML como en un `style` inline), con `object-fit: contain` (`blocks/featured-entities/style.css`) para no deformar la imagen. La fila mantiene el logo alineado verticalmente con el texto vía `align-items: center` en el contenedor flex, sin cambios según el tamaño. El tamaño se aplica únicamente en el momento del render (dentro de `render_row()`), sobre los datos de entidad ya resueltos por la caché: **no forma parte de la clave de caché** (`build_cache_key()` no cambió de firma), por lo que cambiar el tamaño del logo nunca invalida ni duplica cachés existentes.

## [0.6.0] - 2026-08-05

### Añadido

- Bloque dinámico de Gutenberg `bunny-figure-metadata/features` ("Bunny Figure: Características"), pensado para insertarse en patrones, que reemplaza la dependencia de un plugin externo usado anteriormente para mostrar las características de la figura. Server-side puro: no guarda HTML en `post_content`, solo la configuración del bloque; el marcado se genera en cada render a partir de la metadata actual del post.
- Panel de configuración completo: mostrar/ocultar encabezado general, elemento HTML del encabezado (H1–H6, p, div, span, strong, em) y clase CSS adicional; por cada uno de los 12 campos de metadata, mostrar/ocultar, prefijo, elemento HTML y clase CSS del prefijo, y elemento HTML y clase CSS del valor.
- Valor por defecto configurable para metadatos vacíos (por defecto `No dice`, editable a cualquier texto o vacío). Es puramente una representación visual: nunca se guarda en la metadata, nunca se convierte en entidad, y nunca genera ni asocia un TAG. Ya no hay ningún `No dice` hardcodeado en el renderizado.
- Para Fabricante/Ilustrador/Serie/Personaje, reutiliza la estructura de entidades existente: cada valor separado por `&` se resuelve de forma independiente; si tiene una URL configurada (`BFM_Entity_Info_Map`) se muestra como enlace, si no, como texto plano. El valor `No dice` nunca se trata como entidad. No se muestran logos todavía.
- `includes/class-bfm-features-block.php` (`BFM_Features_Block`): registra el bloque (script/estilo del editor registrados manualmente, sin build step) y contiene el `render_callback`, reutilizando `BFM_Meta_Fields` y `BFM_Entity_Info_Map` — sin duplicar lógica ni crear un sistema de metadata paralelo.
- `blocks/features/` (`block.json`, `index.js`, `style.css`): editor en JS plano contra los globals `wp.*` (sin JSX ni build step, consistente con el resto de los scripts del plugin), con vista previa en vivo vía `<ServerSideRender>` que llama al mismo `render_callback` del frontend.
- Lista blanca de etiquetas HTML permitidas (`h1`–`h6`, `p`, `div`, `span`, `strong`, `em`) para los elementos configurables del bloque, evitando que un atributo pueda inyectar una etiqueta arbitraria.

### Rendimiento

- El bloque no ejecuta JavaScript ni peticiones AJAX en el frontend. Por render: 12 lecturas de post meta (el post ya está cargado en memoria) y como máximo 4 lecturas de `get_option()` (una por campo de entidad presente, cacheadas por WordPress tras la primera lectura), sin importar cuántos valores `&`-separados tenga cada campo. El CSS del bloque solo se carga en las páginas donde el bloque realmente se usa (registro vía `block.json`).

### No incluido en esta versión

Bunny Score, páginas públicas por entidad, SEO/schema markup, integraciones externas, sincronización con X, crawling de URLs, importadores, cron, tablas de base de datos nuevas, REST API pública, funcionalidad de merge de entidades, un identificador de entidad independiente del texto de metadata, logos en el bloque "Características" (frontend), cards, y panel de salud/diagnóstico.

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
