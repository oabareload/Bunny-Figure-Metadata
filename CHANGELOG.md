# Changelog

Todos los cambios notables de este proyecto se documentan en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/).

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
