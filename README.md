# Bunny Figure Metadata

Plugin de WordPress, independiente y autónomo, para administrar la metadata estructurada de figuras y las entidades derivadas de esa metadata. Parte de la familia de plugins BunnyChase.

## Estado

Versión 1.9.0. Configuración global de metadatos + alta de nuevos metadatos (tipos text, number, currency, month y **textarea** para historias o descripciones extensas) + configuración individual de **búsqueda de WordPress (`searchable`)** + integración no invasiva de búsquedas mediante subconsultas `EXISTS` correlacionadas + catálogos dinámicos + editor de metadata basado en chips de entidad y textarea amplio para campos multilínea + asociación de cada valor de entidad con un TAG de WordPress + información complementaria (URL y logo, con origen de imagen configurable por metadato: personalizado o desde un post relacionado) por entidad + resaltado visual de campos vacíos (TAG/URL/Logo) en las páginas de entidad + **identidad compuesta para Personaje** (distingue personajes homónimos por Serie/Ilustrador, solo para la resolución de imágenes y el conteo/agrupación de ese metadato) + listado de posts asociados a cada valor de entidad + mantenimiento administrativo de posts con filtros, carga por AJAX, vista previa de solo lectura (miniatura + excerpt) por tarjeta, edición directa de todos los metadatos BFM y **modal accesible para edición de campos Textarea** + sincronización de TAGs únicamente al publicar/programar + bloque dinámico de Gutenberg para mostrar las características de la figura con configuración global para todos los metadatos (presentación **Clásica** en lista o **Tarjetas** en cuadrícula, elegible en el propio bloque) + bloque dinámico de Gutenberg con caché para mostrar un ranking compacto de entidades destacadas (pensado para el footer; diseño **Lista** o **Tarjetas** en cuadrícula, elegible en el propio bloque) + **nuevo bloque Gutenberg dinámico genérico "Metadata Field"** para presentar individualmente cualquier metadato (con personalización de etiquetas, elementos HTML independientes para título y valor, y condición de visibilidad para integración limpia en patrones). No hay Bunny Score, merge de entidades ni panel de salud en esta fase.

## ¿Qué hace?

### 1. Configuración global de metadatos

En **Figure Metadata > Settings** se administra la lista global de metadatos disponibles en el editor de posts. Cada fila permite definir:

- La **meta key** y el **nombre** visible del campo.
- El **tipo** de metadato: `text`, `number`, `currency`, `month` o `textarea` (para historias o descripciones largas multilínea).
- La casilla **Búsqueda** («Incluir este metadato en las búsquedas de WordPress»): activa/desactiva (`searchable`, boolean, falso por defecto) la participación del metadato en las búsquedas globales de frontend del sitio.
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

Los campos de tipo `textarea` (como una historia o descripción) se muestran como áreas de texto amplias a ancho completo de la metabox (`.bfm-figure-meta-field--textarea`), con altura generosa y redimensionamiento vertical activo, permitiendo redactar y revisar cómodamente historias y descripciones con saltos de línea y caracteres Unicode sin alterar el flujo del editor.

Los campos que no están marcados como catálogo ni como textarea siguen siendo inputs de texto simples, como en el snippet original.

Los campos marcados como catálogo se editan como **chips individuales** en lugar de un campo de texto libre. Inicialmente son Fabricante, Ilustrador, Serie y Personaje:

- Cada valor aparece como un chip removible: `[ Good Smile Company × ] [ Flare × ]`.
- La detección al confirmar la entrada compara con los **valores de entidad ya administrados** (los mismos que alimentan las secciones de administración), no con los TAGs de WordPress; no usa autocomplete.
- Si el texto escrito coincide (sin distinguir mayúsculas/minúsculas) con un valor existente, se reutiliza exactamente ese texto tal cual está guardado.
- Si no coincide con ninguno, se puede añadir igualmente como un valor nuevo.
- Los chips verdes son entidades existentes; los naranjas son posibles coincidencias y permiten aceptar (`✓`) o rechazar (`↶`) la sugerencia; los rojos son valores nuevos.

- Internamente, el valor se sigue guardando como el mismo texto plano separado por `&` de siempre (`"Good Smile Company & Flare"`) en un input oculto — el formato de almacenamiento no cambió, solo la forma de editarlo.

### 3. Mantenimiento administrativo de posts

En **Figure Metadata > Posts** se dispone de una interfaz de mantenimiento para consultar y editar todos los metadatos BFM de los posts desde una sola cuadrícula de tarjetas. La lista carga los primeros 20 posts y sigue cargando más por AJAX/infinite scroll, con filtros combinables por título, metadato, estado del valor y valor concreto. Cada tarjeta muestra el ID y el título en la cabecera; a continuación, una **vista previa de solo lectura** con la miniatura destacada del post (tamaño `thumbnail`, o un placeholder discreto si no tiene) y su excerpt manual (o "Sin excerpt." si no existe, sin generarlo automáticamente); después, un control editable por campo; y el guardado se hace por AJAX con comprobación de nonce y permisos del usuario sobre cada post. La miniatura y el excerpt viajan en la misma respuesta AJAX que ya arma cada tarjeta y nunca forman parte del payload de guardado.

Para los campos de tipo **`textarea`** en las tarjetas de edición masiva, se implementa una presentación compacta con una vista previa truncada del texto y un botón **Editar**. Al hacer clic, se abre un modal accesible (`#bfm-post-textarea-modal`) con un área de texto amplia que muestra el contenido completo. Al confirmar en el modal, se actualiza el valor del campo oculto de la tarjeta y su vista previa; al cancelar o pulsar la tecla Escape, el contenido permanece intacto. El guardado final del post mantiene exactamente el mismo flujo centralizado vía AJAX de la tarjeta sin duplicar peticiones.

### 4. Sincronización metadata → TAGs (solo al publicar o programar)
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

**Ayuda visual de campos vacíos:** en las columnas **TAG**, **URL** y **Logo** (puntos 5 y 6), el control correspondiente se resalta con un borde rojo suave cuando ese valor todavía no tiene ningún dato — el botón "Seleccionar TAG...", el input de URL vacío, o el botón "Subir imagen"/"Elegir imagen" cuando no hay logo. Es únicamente una guía visual (clase `bfm-field-missing`): no bloquea el guardado, no vuelve obligatorio ningún campo y no valida formato. El resaltado desaparece de inmediato al asociar un valor y reaparece si se vuelve a quitar.

#### Identidad compuesta para Personaje (nuevo en 1.2.0)

Un mismo nombre de personaje puede corresponder a personajes distintos (p. ej. `Eve` de *Stellar Blade* frente a un `Eve` original ilustrado por `rurudo`). Esto se resuelve **solo para `_bunny_figure_character`**, sin tocar Fabricante, Ilustrador ni Serie, sin tabla nueva, sin ID nuevo y sin migrar la metadata existente:

- En **Figure Metadata > Settings**, la fila de **Personaje** tiene dos checkboxes adicionales ("Distinguir por Serie" / "Distinguir por Ilustrador", ambos activados por defecto) y un campo de texto para el valor que representa "personaje original" (por defecto `PERSONAJE ORIGINAL`; la comparación no distingue mayúsculas/minúsculas ni espacios).
- Regla fija: la identidad interna de cada aparición de un personaje es `personaje + serie` de ese mismo post; si además esa serie coincide con el valor de "personaje original", también se añade `+ ilustrador`. Así, `Eve + Stellar Blade` y `Eve + PERSONAJE ORIGINAL + rurudo` son identidades distintas, aunque ambas se sigan mostrando simplemente como `Eve`.
- Esta identidad interna (p. ej. `Eve|Stellar Blade`) la construye una única función (`BFM_Character_Identity::build_identity_key()`) y es lo que se usa para **contar y agrupar** valores de Personaje, para el **listado de posts relacionados** (punto 7) y para la **selección de imagen** (punto 6): dos personajes homónimos pueden tener conteos, posts relacionados e imágenes completamente independientes. El **TAG** y la **URL** asociados a Personaje siguen funcionando exactamente igual que antes, por el nombre plano — no se ven afectados por esta identidad.
- Si se desactivan ambos checkboxes, el comportamiento de Personaje vuelve a ser idéntico al de cualquier otra entidad (agrupado solo por el nombre plano).

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

**Nota de diseño:** tanto la asociación de TAG como la de URL/logo se identifican por el nombre textual del valor de entidad (p. ej. "Good Smile Company"). Esto es coherente con que la metadata de texto sigue siendo la fuente de verdad: no existe todavía un identificador de entidad independiente del texto. La única excepción es la **imagen** de Personaje, que internamente usa una identidad compuesta (personaje + serie + ilustrador) para distinguir personajes homónimos — ver "Identidad compuesta para Personaje" en el punto 4; TAG y URL de Personaje siguen identificándose por el nombre plano, sin cambios.

#### Origen de imagen configurable por metadato (1.1.0, ajustado en 1.1.1)

En **Figure Metadata > Settings**, cada metadato de catálogo tiene, cuando **Usar logo** está activo, un selector **Origen de imagen** con dos opciones (el selector permanece oculto mientras "Usar logo" no esté marcado):

- **Imagen personalizada** (por defecto): el comportamiento de siempre, descrito arriba — se sube o elige manualmente una imagen para cada valor.
- **Post relacionado**: la imagen de cada valor se resuelve automáticamente a partir de sus posts relacionados (la misma relación valor → posts que ya usa el punto 7), sin necesidad de subir nada:
  1. Se toma el post relacionado más antiguo de ese valor.
  2. Se usa su imagen destacada si tiene una.
  3. Si no tiene imagen destacada, se usa la primera imagen adjunta de ese post.
  4. Si no hay ninguna imagen disponible, no se muestra ninguna.

  En la columna **Logo** de la pantalla de la entidad, esa imagen automática se etiqueta como "Automática" y puede reemplazarse manualmente con el botón **Cambiar**: abre el selector de medios de WordPress con la búsqueda ya rellenada con el nombre exacto del valor (p. ej. `Hatsune Miku`), usando la búsqueda nativa de la Media Library — no existe ni se creó ningún sistema de indexación propio para relacionar medios con valores de metadata. Solo se guarda el **attachment ID** elegido, nunca la URL. Una vez fijada manualmente, esa elección **ya no cambia automáticamente** aunque aparezcan posts nuevos — el botón **Usar automática** la descarta y vuelve a calcularla a partir de los posts relacionados.

  Esta opción está disponible para cualquier metadato de catálogo, pero por ahora solo se usa con **Personaje**.

  La imagen de la entidad siempre se muestra en tamaño `thumbnail` (o el tamaño registrado más pequeño disponible para ese adjunto si no tiene `thumbnail`), nunca `full`/`large`, generada con las APIs nativas de WordPress (`wp_get_attachment_image()` / `wp_get_attachment_image_src()`).

  La imagen que realmente se muestra para cada valor (manual si existe, automática si no) se resuelve en un único servicio compartido (`BFM_Entity_Info_Map::get_effective_image_ids()`), usado tanto por esta pantalla como por el bloque "Entidades destacadas" (punto 9) — sin lógica duplicada entre ambos.

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

Muestra los metadatos configurados del post actual, por ejemplo:

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
- **Presentación** (desde 1.7.0): **Clásica** (lista, valor por defecto) o **Tarjetas**. Con Tarjetas aparece **Ancho de tarjeta (px)**: ancho de referencia, 140 px por defecto, de 100 a 250 px. El encabezado (mostrar/ocultar, elemento HTML, clase) funciona igual en ambas presentaciones y no se duplica.
- Por cada metadato configurado: mostrar/ocultar, prefijo, elemento HTML del prefijo, clase CSS del prefijo, elemento HTML del valor, clase CSS del valor.
- Valor por defecto para metadatos vacíos, configurable por campo. Si se deja vacío, ese metadato no genera una fila; por defecto se muestra `No dice`.

**Entidades (Fabricante, Ilustrador, Serie, Personaje):**

- Reutiliza la estructura de entidades existente: cada valor `&`-separado se resuelve de forma independiente, exactamente igual que en el resto del plugin.
- Si el valor tiene una URL configurada (`BFM_Entity_Info_Map`), se muestra como enlace (`<a href="...">Valor</a>`); si no, como texto plano.
- **Integración opcional con WPAM (desde 1.6.0):** cuando el destino del enlace del campo es "Enlace externo", la URL externa pasa por `wpam_get_external_link_url( $url, $post_id )` para usar el interstitial/redirect `/goext/` de WPAM. Solo se aplica a URLs realmente externas (`wp_is_internal_link()`); se conserva la URL original si es interna, si WPAM no está activo o si WPAM devuelve una cadena vacía. Los enlaces a TAG y el resto del HTML del enlace no cambian, y el bloque no depende de WPAM para funcionar.
- El valor especial `No dice` nunca se trata como entidad (no se busca URL para él), igual que nunca genera un TAG.
- **No se muestran logos en la presentación Clásica.** Sí se muestran en Tarjetas (ver más abajo).
- El bloque es de **solo lectura**: no crea entidades, TAGs, URLs ni ninguna otra información al renderizarse.

#### Presentación "Tarjetas" (nuevo en 1.7.0)

La opción se guarda como atributos del bloque (`layout`, `cardMinWidth`); un bloque sin ellos sigue siendo Clásico. No hay un bloque aparte: Clásica y Tarjetas comparten la misma resolución de valores (qué campos se muestran, valor por defecto, `No dice`, enlaces a TAG/externos y firma opcional de WPAM) y solo cambia el marcado.

- Cuadrícula CSS Grid (`repeat(auto-fill, minmax(min(ancho, 100%), 1fr))`): el ancho configurado es una referencia, no un tamaño rígido; no hay altura fija ni desplazamiento horizontal. El ancho se valida en servidor (entero, 100–250, 140 por defecto) y es lo único que se escribe en el `style` del bloque, como variable `--bfm-card-min`.
- Cada tarjeta: valor arriba (texto sin transformar ni truncar, o imagen) y nombre del campo abajo (el prefijo configurado sin los dos puntos finales).
- **Imagen**: si el campo es una entidad con logo activado y el valor tiene imagen, se muestra la imagen (`object-fit: contain`) junto al nombre visible del valor; la imagen es decorativa (`alt` vacío) porque el texto adyacente ya la nombra. La imagen se resuelve con `BFM_Entity_Info_Map::get_effective_image_ids()` y `get_logo_urls()`, respetando el origen de imagen del metadato y la identidad compuesta de Personaje.
- **Enlace**: un valor enlazado convierte toda su tarjeta en un único `<a>`, con `:hover` y `:focus-visible`. Nunca hay enlaces anidados. Las tarjetas sin enlace no tienen efectos interactivos.
- **Valores múltiples (`&`)**: si un campo tiene varios valores y al menos uno tiene imagen o un enlace válido, cada valor va en su propia tarjeta, con solo su imagen y solo su enlace (se asocian por el nombre del valor, no por posición) y con el nombre del campo abajo. Si ninguno tiene imagen ni enlace, siguen agrupados en una sola tarjeta con el formato `A & B` del listado clásico. Un campo con un único valor es siempre una sola tarjeta. Esta regla no afecta a la presentación Clásica.
- Los estilos son variables CSS (`--bfm-card-*`) derivadas del color del texto del tema; el CSS es el mismo `blocks/features/style.css` y solo se carga donde el bloque se usa.

### 9. Bloque dinámico "Entidades destacadas" (nuevo en 0.7.0)

Un segundo bloque de Gutenberg **dinámico/server-side** (`bunny-figure-metadata/featured-entities`), pensado para colocarse **una sola vez en el área de widgets del footer** (editor de bloques de widgets) y aparecer así en todas las páginas del sitio. Muestra un ranking compacto de los valores más destacados de una entidad, en formato de **lista** por defecto o, desde 1.8.0, como **tarjetas** en una cuadrícula (ver más abajo):

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

- **Diseño** (desde 1.8.0): **Lista** (valor por defecto, la presentación de siempre) o **Tarjetas (grid)**. Un bloque guardado antes de 1.8.0 no tiene el atributo `layout` y sigue mostrándose como lista; cualquier valor no válido también cae a lista. Cambiarlo no modifica ninguna otra opción.
- **Metadata**: Fabricante, Ilustrador, Serie o Personaje.
- **Cantidad** de entidades a mostrar (1–50).
- **Orden**: Posts ↓ (más posts primero), Posts ↑ (menos posts primero), Nombre A-Z, Nombre Z-A, o Aleatorio.
- **Mostrar logo**: Sí/No.
- **Tamaño del logo** (px): control numérico en el Inspector, 12–96 px, por defecto **24 px**. El logo siempre se renderiza en proporción 1:1 (`width`/`height` iguales, con `object-fit: contain` para no deformarlo) y queda alineado verticalmente con el texto de la fila sin importar el tamaño elegido.
- **Mostrar cantidad de posts**: Sí/No.
- **Enlazar al TAG asociado**: Sí/No — cuando está activo, el nombre enlaza directamente al archivo/tag nativo de WordPress, reutilizando el `term_id` ya asociado (ver punto 4); no se crean páginas nuevas para las entidades.
- **Título personalizado**, con **elemento HTML del título** (H2–H6) y **clase CSS** configurables. Si el título se deja vacío, se usa uno por defecto derivado del nombre de la entidad (p. ej. "Fabricantes destacados").

Reutiliza la infraestructura de entidades ya existente (`BFM_Entity_Repository` para el ranking, `BFM_Entity_Tag_Map` para el enlace al TAG, `BFM_Entity_Info_Map` para el logo): no se crea ningún sistema de entidades paralelo. El bloque es de **solo lectura**.

El logo respeta el **origen de imagen** configurado en Settings para ese metadato (punto 6): si es "Imagen personalizada", usa el logo subido manualmente, exactamente como siempre; si es "Post relacionado", muestra la imagen efectiva de cada valor — manual si se fijó una, automática desde su post relacionado si no — resuelta por el mismo servicio compartido que usa la pantalla de la entidad (`BFM_Entity_Info_Map::get_effective_image_ids()`).

#### Diseño "Tarjetas" (nuevo en 1.8.0)

Cada entidad del ranking se muestra como una tarjeta independiente en una cuadrícula CSS Grid (`repeat(auto-fill, minmax(min(100%, 220px), 1fr))`): el número de columnas depende solo del ancho disponible, no hay filas ni columnas fijas, ni alturas fijas, ni JavaScript. Cada tarjeta contiene, igual que la lista, el logo (con el mismo tamaño 1:1 configurado), el nombre completo (sin truncar, con saltos de línea) y, si está activado, el conteo de posts. Cuando la entidad tiene TAG asociado y "Enlazar al TAG" está activo, toda la tarjeta es un único `<a>` (con `:hover` y `:focus-visible`; nunca enlaces anidados); sin TAG es un contenedor no interactivo. Los estilos de tarjeta están acotados a `.bfm-featured-entities-block--cards` (variables `--bfm-fe-card-*`) y no afectan a la lista.

El diseño solo cambia el marcado de la lista ya resuelta: **no forma parte de la clave de caché** ni añade consultas, de modo que Lista y Tarjetas comparten el mismo transient.

**Caché (obligatoria desde esta primera versión):**

- El ranking ya resuelto (nombres, conteos, URL de TAG, URL de logo) para cada configuración de bloque se guarda en un **transient de WordPress**, para no recalcularlo en cada visita del frontend.
- Órdenes deterministas (Posts ↓/↑, Nombre A-Z/Z-A): caché de **12 horas**. Orden **Aleatorio**: caché de **5 minutos**, para que efectivamente pueda rotar.
- La clave de caché depende de la metadata, la cantidad, el orden, y de si logos/enlace a TAG están activos (las opciones que determinan qué hay que calcular).
- **Invalidación perezosa (lazy) por versión**: al publicar o programar un post, se incrementa un contador de versión por cada uno de los cuatro campos de entidad (una única lectura/escritura en `wp_options`). Ninguna caché se recalcula en ese momento: las cachés bajo la versión anterior simplemente dejan de usarse y expiran solas según su propio TTL. El siguiente visitante regenera el resultado y lo vuelve a cachear.

### 10. Bloque dinámico "Metadata Field" (nuevo en 1.9.0)

Un bloque de Gutenberg **dinámico/server-side** (`bunny-figure-metadata/metadata-field`) pensado para presentar individualmente cualquier metadato registrado en Bunny Figure Metadata, como historias narrativas, notas de diseño o descripciones detalladas.

**Características y opciones disponibles en el Inspector:**

- **Selección dinámica del metadato**: Desplegable alimentado directamente desde `BFM_Meta_Fields::all()`, mostrando el identificador técnico y la etiqueta administrativa. Si un metadato deja de existir o cambia su identificador, el bloque gestiona la situación limpiamente devolviendo una cadena vacía sin provocar errores.
- **Configuración de etiqueta**:
  - Mostrar u ocultar la etiqueta (`showLabel`).
  - Personalizar el texto visible de la etiqueta (`customLabel`) sin alterar la definición original del metadato.
  - Seleccionar el elemento HTML de la etiqueta: `h1`, `h2`, `h3`, `h4`, `h5`, `h6`, `p`, `div`, `span` (por defecto `h2`).
- **Configuración del valor**:
  - Seleccionar de forma independiente el elemento HTML del valor: `h1`, `h2`, `h3`, `h4`, `h5`, `h6`, `p`, `div`, `span` (por defecto `div`).
  - Clases CSS adicionales para el valor (`valueClassName`).
  - Formato adaptado al tipo: los campos de tipo `textarea` conservan los saltos de línea (`nl2br`) y escapan con seguridad cualquier contenido HTML arbitrario (`esc_html`).
- **Condición de visibilidad («Mostrar únicamente si el metadato tiene contenido»)**:
  - Activa por defecto (`hideIfEmpty`). Cuando está habilitada, si el valor es `null`, cadena vacía, solo espacios o el valor excluido `No dice`, el bloque no genera absolutamente ningún marcado HTML (0 bytes: sin contenedor envolvente, sin títulos vacíos ni separaciones residuales). Esto permite incluir el bloque de forma fija dentro de **patrones de publicación (Block Patterns)**.

### 11. Integración con las búsquedas de WordPress (nuevo en 1.9.0)

Los metadatos que tengan activa la propiedad `searchable` participan en las búsquedas estándar de WordPress en el frontend del sitio:

- Combina de manera no destructiva la búsqueda nativa en `post_title`, `post_content` y `post_excerpt` con los metadatos configurados mediante una subconsulta `EXISTS` correlacionada sobre `wp_postmeta`.
- **Cero duplicados**: al usar `EXISTS (SELECT 1 FROM wp_postmeta ...)` no se producen duplicados de posts y no se requiere `DISTINCT` costoso.
- **Cero JOINs adicionales**: no altera los JOINs de la consulta principal de posts.
- **Consultas preparadas**: utiliza escape estricto y cláusula `IN (...)` para evaluar todos los metadatos participantes en una sola condición.
- No altera las búsquedas en la administración de WordPress (`is_admin()`).
- Descarta correctamente valores nulos y vacíos.

## Rendimiento

- Cada sección de entidad resuelve sus valores con **una sola consulta agregada** a `wp_postmeta` (vía `$wpdb`), sin recorrer todos los posts con `WP_Query` y sin patrones N+1.
- El ordenamiento por nombre/posts se aplica en memoria sobre el resultado ya obtenido (`BFM_Entity_Repository::sort_values()`), sin consultas adicionales.
- Las asociaciones TAG de una sección se resuelven con **una sola consulta batch** (`get_terms` con `include`), nunca una consulta por fila.
- Las miniaturas de logo se resuelven precargando el caché de adjuntos con **una sola consulta** (`get_posts` con `post__in`); las llamadas posteriores a `wp_get_attachment_image_src()` usan ese caché.
- **(Desde 1.1.0)** Cuando un metadato de catálogo usa el origen de imagen "Imagen de un post relacionado", la resolución automática por valor (`BFM_Entity_Repository::get_related_image_attachment_ids()`) usa como máximo **tres consultas en total** para toda la pantalla — una para encontrar el post relacionado más antiguo de cada valor, una para precargar el caché de esos posts, y una última, solo si hace falta, para resolver en lote la primera imagen adjunta de los posts sin imagen destacada — sin importar cuántos valores o posts existan.
- **(Desde 1.2.0)** La identidad compuesta de Personaje agrega como máximo **dos consultas adicionales** por carga de pantalla (los valores de Serie e Ilustrador de todos los posts, reutilizando la misma consulta agregada que ya usa el resto del repositorio), calculadas una sola vez y cacheadas en memoria durante la petición (`BFM_Entity_Repository::character_identity_context()`), sin importar cuántas veces se invoque internamente durante esa misma carga de página. No se ejecutan en absoluto para ningún otro metadato.
- El listado de posts por valor de entidad resuelve todos los IDs coincidentes con la misma consulta agregada ya usada para contar valores, y luego usa un único `WP_Query` con `post__in` + `posts_per_page` para traer solo la página solicitada, evitando la consulta `SQL_CALC_FOUND_ROWS` adicional (`no_found_rows => true`).
- Las sugerencias de autocompletado del editor (chips) se precargan una sola vez por carga de pantalla de edición de post — 4 consultas agregadas (una por campo de entidad), reutilizando exactamente la misma consulta que ya usan las pantallas de administración de entidades. No hay peticiones AJAX por cada tecla escrita.
- La sincronización de TAGs ya no se ejecuta en cada guardado, solo cuando el post resulta publicado o programado.
- **(Desde 0.6.0)** El bloque "Características" es server-side puro: no hay JavaScript ni peticiones AJAX en el frontend. Por cada render, se hacen 12 lecturas de post meta (una por campo, todas del mismo post ya cargado en memoria por WordPress) más como máximo 4 lecturas de `get_option()` (una por campo de entidad presente, para resolver URLs — cacheadas por WordPress tras la primera lectura), sin importar cuántos valores `&`-separados tenga cada campo. El CSS del bloque (`blocks/features/style.css`) solo se carga en las páginas donde el bloque realmente se usa, gracias al registro vía `block.json`.
- **(Desde 1.7.0)** La presentación Tarjetas es HTML + CSS puro (sin JavaScript ni consultas por tarjeta) y reutiliza los mismos datos que la Clásica. Solo en Tarjetas, y solo para campos de entidad con logo activado, se resuelven imágenes: una llamada a `get_effective_image_ids()` por campo y una única llamada por lote a `get_logo_urls()`. Ambos resultados se reutilizan durante la misma petición (memoria estática, sin persistencia), así que varios bloques en una página no los repiten. Con origen de imagen "Post relacionado" esa resolución usa las consultas descritas arriba una vez por petición y metadato. La presentación Clásica no resuelve imágenes.
- **(Desde 0.7.0)** El bloque "Entidades destacadas" resuelve su ranking (una consulta agregada vía `BFM_Entity_Repository`, más como máximo una consulta batch `get_terms()` y un `get_posts()` de priming de attachments cuando el TAG o el logo están activados) **una sola vez por combinación de configuración**, gracias a la caché en transients: mientras la caché esté vigente, servir el bloque no ejecuta ninguna consulta a la base de datos, solo la lectura del transient. Su CSS (`blocks/featured-entities/style.css`) también se carga únicamente en las páginas donde el bloque se usa (incluyendo el área de widgets, ya que WordPress encola el estilo registrado de un bloque en cualquier contexto donde ese bloque se renderiza realmente, no solo en el contenido del post).
- **(Desde 1.8.0)** El diseño Tarjetas de "Entidades destacadas" no añade consultas: usa la misma lista ya cacheada que la Lista (el diseño no entra en la clave del transient) y es HTML + CSS puro.
- No se usa cron, ni tablas de base de datos propias. Tanto la asociación entidad → TAG como la información complementaria (URL + logo) se guardan cada una en una única fila de `wp_options` (autoload desactivado). El ranking de "Entidades destacadas" se sirve desde transients (persistidos por WordPress en `wp_options` si no hay un object cache persistente instalado, o en el object cache si lo hay); su invalidación es un simple contador de versión (otra fila pequeña de `wp_options`), sin cron ni recalculo inmediato.

## Seguridad

- Guardado de metadata protegido con nonce, verificación de capability (`edit_post`) y sanitización (`sanitize_text_field`) de toda entrada, incluyendo el valor reconstruido por los chips.
- Pantallas de administración protegidas con `current_user_can( 'manage_options' )`.
- Los seis endpoints AJAX (búsqueda de TAG, asociar/quitar TAG, guardar URL, asociar/quitar logo) verifican capability y nonce antes de procesar cualquier solicitud.
- El endpoint de asociación de TAG valida que la entidad exista, que el `term_id` recibido sea un entero válido y que corresponda a un término real de `post_tag`.
- La sincronización al publicar valida el `post_type`, el estado resultante del post, `DOING_AUTOSAVE`, si es una revisión, y `current_user_can( 'edit_post', $post_id )`.
- **(Desde 0.6.0)** Los elementos HTML configurables del bloque (encabezado, prefijo, valor) se validan contra una lista blanca fija (`h1`–`h6`, `p`, `div`, `span`, `strong`, `em`); cualquier otro valor cae a `span` de forma segura, evitando que un atributo de bloque pueda inyectar una etiqueta arbitraria. Todo el contenido de metadata se escapa (`esc_html`) antes de mostrarse; las URLs de entidad se escapan con `esc_url()`.
- **(Desde 1.7.0)** `layout` se valida contra `classic`/`cards` (cualquier otro valor cae a `classic`) y `cardMinWidth` se convierte a entero y se acota a 100–250; solo ese entero llega al `style` del bloque. En Tarjetas, texto y atributos se escapan (`esc_html`, `esc_attr`, `esc_url`) y un enlace que `esc_url()` rechaza (p. ej. `javascript:`) no se enlaza.
- **(Desde 0.7.0)** El bloque "Entidades destacadas" valida la metadata configurada contra los cuatro meta keys de entidad reales, el orden contra una lista fija de valores permitidos, la cantidad se acota entre 1 y 50 (`absint()` + `max()`/`min()`), y el elemento HTML del título se valida contra una lista blanca (`h2`–`h6`); cualquier valor fuera de esas listas cae a un valor seguro por defecto. Nombres, URLs de TAG y URLs de logo se escapan (`esc_html`, `esc_url`) antes de renderizarse.
- **(Desde 1.8.0)** En "Entidades destacadas", `layout` se valida contra `list`/`cards` (cualquier otro valor cae a `list`); en Tarjetas, nombre, conteo y URLs se escapan con las mismas funciones que la lista y un enlace que `esc_url()` rechaza no se enlaza.
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
│   ├── class-bfm-character-identity.php
│   ├── class-bfm-post-editor.php
│   ├── class-bfm-tag-sync.php
│   ├── class-bfm-entity-repository.php
│   ├── class-bfm-entity-tag-map.php
│   ├── class-bfm-entity-info-map.php
│   ├── class-bfm-search.php
│   ├── class-bfm-features-block.php
│   ├── class-bfm-featured-entities-block.php
│   └── class-bfm-metadata-field-block.php
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
│   ├── featured-entities/
│   │   ├── block.json
│   │   ├── index.js
│   │   └── style.css
│   └── metadata-field/
│       ├── block.json
│       ├── index.js
│       └── style.css
├── assets/
│   ├── css/
│   │   ├── bunny-admin.css
│   │   └── admin.css
│   └── js/
│       ├── entity-tag-select.js
│       ├── entity-info.js
│       ├── metadata-settings.js
│       ├── post-editor-chips.js
│       └── post-metadata-maintenance.js
└── tests/
    └── test-suite.php
```

## Archivos de referencia (raíz del proyecto)

- `bunny-figure-metadata-editor.php` — snippet original de producción, del que se migró la lógica del editor y de sincronización de tags. Se conserva sin modificar como referencia histórica.
- `bunny-metadata-scan.php` — herramienta CLI histórica de migración/export/import de metadata. No forma parte del plugin; se conserva sin modificar.

## Requisitos

- WordPress 6.0+
- PHP 8.2+

## Fuera de alcance (esta fase)

No implementado todavía, intencionalmente: Bunny Score, páginas públicas por entidad, SEO/schema markup, integraciones externas (salvo la firma opcional de enlaces vía WPAM en el bloque "Características"), sincronización con X, crawling de URLs, importadores, cron, REST API pública, tablas de base de datos nuevas, funcionalidad de merge de entidades, un identificador de entidad independiente del texto de metadata, presentación en collage para "Entidades destacadas" (solo lista compacta o tarjetas en cuadrícula), y panel de salud/diagnóstico.
