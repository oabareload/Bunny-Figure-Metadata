# Bunny Figure Metadata

Plugin de WordPress, independiente y autónomo, para administrar la metadata estructurada de figuras y las entidades derivadas de esa metadata. Parte de la familia de plugins BunnyChase.

## Estado

Fase 1: base de administración de entidades + migración del editor de metadata existente. No hay Bunny Score, páginas públicas, SEO, cron, tablas propias ni caché en esta fase.

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

Esta funcionalidad es una migración directa del Code Snippet `bunny-figure-metadata-editor.php` que estaba en producción: mismos campos, mismo comportamiento de guardado (nonce, capability check, sanitización), mismo autocompletado de tags en los campos que lo usan.

### 2. Sincronización metadata → TAGs

Cuatro de los doce campos (Fabricante, Ilustrador, Serie, Personaje) se sincronizan automáticamente con los tags (`post_tag`) del post al guardar:

- Los valores múltiples dentro de un mismo campo se separan con `&` (no con coma), y cada uno se convierte en un tag independiente.
- El valor `No dice` es metadata válida pero **nunca** se convierte en tag.
- El plugin recuerda qué tags fueron creados automáticamente por esta sincronización (`_bunny_figure_managed_tag_ids`). Al editar la metadata, solo se añaden o quitan esos tags gestionados; **los tags que el usuario haya asignado manualmente nunca se tocan ni se eliminan**.

La metadata estructurada de los posts sigue siendo la fuente de verdad; los tags son una relación derivada, no al revés.

### 3. Administración de entidades

Un menú de administración ("Figure Metadata") con cuatro secciones:

- Fabricantes
- Ilustradores
- Series
- Personajes

Cada sección recorre la metadata existente de los posts publicados y muestra los valores únicos encontrados junto con el número de posts en los que aparece cada uno (por ejemplo, "Good Smile Company — 42 posts"). Los valores se obtienen directamente de la metadata estructurada, no de la lista de tags de WordPress.

En esta fase, la administración es de solo lectura (listado + conteo). No incluye todavía edición de información complementaria por entidad (TAG relacionado, URLs, logo), páginas públicas, ni ningún tipo de puntuación o score.

## Rendimiento

- Cada sección de entidad resuelve sus valores con **una sola consulta agregada** a `wp_postmeta` (vía `$wpdb`), sin recorrer todos los posts con `WP_Query` y sin patrones N+1.
- Los assets CSS de administración solo se cargan en las pantallas propias del plugin (`admin_enqueue_scripts` con comprobación de hook), nunca de forma global en `wp-admin`.
- No se usa cron, caché, ni tablas de base de datos propias: no son necesarias para el volumen actual de posts.

## Seguridad

- Guardado de metadata protegido con nonce, verificación de capability (`edit_post`) y sanitización (`sanitize_text_field`) de toda entrada.
- Pantallas de administración protegidas con `current_user_can( 'manage_options' )`.
- Todo el output se escapa (`esc_html`, `esc_attr`, `esc_url`) en las vistas.

## Identidad visual

La administración usa el mismo lenguaje visual (`bunny-admin.css`, header, tabs) que el resto de la familia BunnyChase (Bunny Reactions, Bunny Magazine). El CSS es una copia propia del plugin, sin dependencia funcional hacia esos plugins.

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
│   └── class-bfm-entity-repository.php
├── admin/
│   ├── class-bfm-admin.php
│   ├── class-bfm-admin-header.php
│   └── views/
│       ├── manufacturers.php
│       ├── illustrators.php
│       ├── series.php
│       ├── characters.php
│       └── partial-entity-list.php
└── assets/
    ├── css/
    │   ├── bunny-admin.css
    │   └── admin.css
    └── js/
```

## Archivos de referencia (raíz del proyecto)

- `bunny-figure-metadata-editor.php` — snippet original de producción, del que se migró la lógica del editor y de sincronización de tags. Se conserva sin modificar como referencia histórica.
- `bunny-metadata-scan.php` — herramienta CLI histórica de migración/export/import de metadata. No forma parte del plugin; se conserva sin modificar.

## Requisitos

- WordPress 6.0+
- PHP 8.2+

## Fuera de alcance (esta fase)

No implementado todavía, intencionalmente: Bunny Score, páginas públicas por entidad, SEO/schema markup, integraciones externas, sincronización con X, crawling de URLs, importadores, cron, REST API pública, tablas de base de datos nuevas, y edición de información complementaria por entidad (TAG relacionado, URLs, logo).
