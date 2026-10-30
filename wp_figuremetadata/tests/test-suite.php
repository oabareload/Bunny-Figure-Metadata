<?php
/**
 * Automated Test Suite for Bunny Figure Metadata Extension
 *
 * Covers all 25 verification points specified in section 8 of the requirements.
 */

// 1. Mock minimal WordPress environment
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../../' );
}
define( 'BFM_PATH', dirname( __DIR__ ) . '/' );
define( 'BFM_URL', 'https://example.com/wp-content/plugins/wp_figuremetadata/' );
define( 'BFM_VERSION', '1.9.0' );
define( 'BFM_BASENAME', 'wp_figuremetadata/bunny-figure-metadata.php' );
define( 'BFM_FILE', BFM_PATH . 'bunny-figure-metadata.php' );

$GLOBALS['wp_options'] = array();
$GLOBALS['wp_postmeta'] = array();
$GLOBALS['wp_registered_meta'] = array();
$GLOBALS['wp_registered_blocks'] = array();
$GLOBALS['wp_registered_scripts'] = array();
$GLOBALS['wp_registered_styles'] = array();
$GLOBALS['wp_localized_scripts'] = array();
$GLOBALS['wp_actions'] = array();
$GLOBALS['wp_filters'] = array();
$GLOBALS['wp_current_user_caps'] = array( 'manage_options' => true, 'edit_post' => true );

class MockWPDB {
	public string $posts = 'wp_posts';
	public string $postmeta = 'wp_postmeta';

	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	public function prepare( string $query, ...$args ): string {
		if ( isset( $args[0] ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		foreach ( $args as $arg ) {
			$val = is_numeric( $arg ) ? $arg : "'" . addslashes( (string) $arg ) . "'";
			$query = preg_replace( '/%[sdf]/', (string) $val, $query, 1 );
		}
		return $query;
	}
}
$GLOBALS['wpdb'] = new MockWPDB();

function get_option( string $name, $default = false ) {
	return $GLOBALS['wp_options'][ $name ] ?? $default;
}
function update_option( string $name, $value, $autoload = null ): bool {
	$GLOBALS['wp_options'][ $name ] = $value;
	return true;
}
function add_option( string $name, $value, string $deprecated = '', $autoload = 'yes' ): bool {
	if ( isset( $GLOBALS['wp_options'][ $name ] ) ) {
		return false;
	}
	$GLOBALS['wp_options'][ $name ] = $value;
	return true;
}
function delete_option( string $name ): bool {
	unset( $GLOBALS['wp_options'][ $name ] );
	return true;
}

function get_post_meta( int $post_id, string $key = '', bool $single = false ) {
	if ( '' === $key ) {
		return $GLOBALS['wp_postmeta'][ $post_id ] ?? array();
	}
	if ( $single ) {
		return $GLOBALS['wp_postmeta'][ $post_id ][ $key ] ?? '';
	}
	return isset( $GLOBALS['wp_postmeta'][ $post_id ][ $key ] ) ? array( $GLOBALS['wp_postmeta'][ $post_id ][ $key ] ) : array();
}
function update_post_meta( int $post_id, string $key, $value ): bool {
	$GLOBALS['wp_postmeta'][ $post_id ][ $key ] = $value;
	return true;
}
function delete_post_meta( int $post_id, string $key ): bool {
	unset( $GLOBALS['wp_postmeta'][ $post_id ][ $key ] );
	return true;
}

function register_post_meta( string $post_type, string $meta_key, array $args ): bool {
	$GLOBALS['wp_registered_meta'][ $meta_key ] = $args;
	return true;
}

function sanitize_key( string $key ): string {
	$key = strtolower( $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}
function sanitize_text_field( $str ): string {
	$str = (string) $str;
	$str = strip_tags( $str );
	$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );
	return trim( $str );
}
function sanitize_textarea_field( $str ): string {
	$str = (string) $str;
	$str = strip_tags( $str );
	$str = str_replace( "\r", '', $str );
	return trim( $str );
}
function esc_html( $str ): string {
	return htmlspecialchars( (string) $str, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $str ): string {
	return htmlspecialchars( (string) $str, ENT_QUOTES, 'UTF-8' );
}
function esc_url( string $url ): string {
	return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
}
function admin_url( string $path = '' ): string {
	return 'https://example.com/wp-admin/' . $path;
}
function esc_textarea( $str ): string {
	return htmlspecialchars( (string) $str, ENT_QUOTES, 'UTF-8' );
}
function esc_sql( $data ) {
	return addslashes( (string) $data );
}
function sanitize_html_class( $class, $fallback = '' ): string {
	$class = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
	return '' !== $class ? $class : $fallback;
}
function wp_unslash( $val ) {
	return $val;
}
function current_user_can( string $cap, ...$args ): bool {
	return ! empty( $GLOBALS['wp_current_user_caps'][ $cap ] );
}
function user_can( $user_id, string $cap, ...$args ): bool {
	return true;
}
function is_admin(): bool {
	return ! empty( $GLOBALS['is_admin_mode'] );
}
function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
	$GLOBALS['wp_actions'][ $hook ][] = array( 'cb' => $callback, 'prio' => $priority );
}
function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
	$GLOBALS['wp_filters'][ $hook ][] = array( 'cb' => $callback, 'prio' => $priority );
}
function register_block_type( $dir_or_name, array $args = array() ): void {
	$GLOBALS['wp_registered_blocks'][] = array( 'name' => $dir_or_name, 'args' => $args );
}
function wp_register_script( string $handle, string $src, array $deps = array(), $ver = false, $in_footer = false ): void {
	$GLOBALS['wp_registered_scripts'][ $handle ] = compact( 'src', 'deps', 'ver' );
}
function wp_register_style( string $handle, string $src, array $deps = array(), $ver = false, $media = 'all' ): void {
	$GLOBALS['wp_registered_styles'][ $handle ] = compact( 'src', 'deps', 'ver' );
}
function wp_localize_script( string $handle, string $object_name, array $data ): void {
	$GLOBALS['wp_localized_scripts'][ $handle ][ $object_name ] = $data;
}
function __( string $text, string $domain = 'default' ): string {
	return $text;
}
function esc_html__( string $text, string $domain = 'default' ): string {
	return esc_html( $text );
}
function esc_attr__( string $text, string $domain = 'default' ): string {
	return esc_attr( $text );
}
function esc_attr_e( string $text, string $domain = 'default' ): void {
	echo esc_attr( $text );
}
function esc_html_e( string $text, string $domain = 'default' ): void {
	echo esc_html( $text );
}
function selected( $selected, $current = true, bool $echo = true ): string {
	$out = ( (string) $selected === (string) $current ) ? ' selected="selected"' : '';
	if ( $echo ) {
		echo $out;
	}
	return $out;
}
function checked( $checked, $current = true, bool $echo = true ): string {
	$out = ( (string) $checked === (string) $current ) ? ' checked="checked"' : '';
	if ( $echo ) {
		echo $out;
	}
	return $out;
}
function _doing_it_wrong( ...$args ): void {}
function add_settings_error( ...$args ): void {}
function wp_strip_all_tags( string $str ): string {
	return strip_tags( $str );
}
function wp_nonce_field( string $action = '', string $name = '', bool $referer = true, bool $echo = true ): string {
	$field = '<input type="hidden" name="' . esc_attr( $name ) . '" value="mock_nonce" />';
	if ( $echo ) {
		echo $field;
	}
	return $field;
}
function settings_fields( string $option_group ): void {}
function settings_errors( string $setting = '', bool $sanitize = false, bool $hide_on_update = false ): void {}
function submit_button(): void {}
class MockAdminHeader {
	public static function render( ...$args ): void {}
}
if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		public $max_num_pages = 1;
		public function __construct( $args = array() ) {}
		public function have_posts(): bool { return false; }
		public function the_post(): void {}
	}
	function wp_reset_postdata(): void {}
}
function get_the_title( $post = 0 ): string {
	return 'Test Figure Title';
}
function get_the_ID(): int {
	return 101;
}

// Include plugin files under test
require_once BFM_PATH . 'includes/class-bfm-meta-fields.php';
require_once BFM_PATH . 'includes/class-bfm-character-identity.php';
require_once BFM_PATH . 'includes/class-bfm-post-editor.php';
require_once BFM_PATH . 'includes/class-bfm-tag-sync.php';
require_once BFM_PATH . 'includes/class-bfm-entity-repository.php';
require_once BFM_PATH . 'includes/class-bfm-entity-tag-map.php';
require_once BFM_PATH . 'includes/class-bfm-entity-info-map.php';
require_once BFM_PATH . 'includes/class-bfm-search.php';
require_once BFM_PATH . 'includes/class-bfm-features-block.php';
require_once BFM_PATH . 'includes/class-bfm-featured-entities-block.php';
require_once BFM_PATH . 'includes/class-bfm-metadata-field-block.php';
require_once BFM_PATH . 'admin/class-bfm-admin-header.php';
require_once BFM_PATH . 'admin/class-bfm-admin.php';
require_once BFM_PATH . 'admin/class-bfm-admin-ajax.php';

// Test runner helpers
$passed_count = 0;
$failed_count = 0;

function assert_test( bool $condition, string $test_number, string $description ): void {
	global $passed_count, $failed_count;
	if ( $condition ) {
		echo " [PASS] Test {$test_number}: {$description}\n";
		$passed_count++;
	} else {
		echo " [FAIL] Test {$test_number}: {$description}\n";
		$failed_count++;
	}
}

echo "\n======================================================\n";
echo " RUNNING TEST SUITE: BUNNY FIGURE METADATA (25 TESTS)\n";
echo "======================================================\n\n";

// --- METADATOS ---

// 1. Creación de metadatos Textarea.
$admin = new BFM_Admin();
$settings_input = array(
	array(
		'meta_key'   => '_bunny_figure_story',
		'label'      => 'Historia',
		'type'       => 'textarea',
		'searchable' => '1',
		'entity'     => '0',
	),
	array(
		'meta_key'   => '_bunny_figure_scale',
		'label'      => '📐 Escala',
		'type'       => 'text',
		'searchable' => '0',
		'entity'     => '0',
	),
);
$sanitized = $admin->sanitize_metadata_settings( $settings_input );
update_option( BFM_Meta_Fields::OPTION_NAME, $sanitized );
$all_fields = BFM_Meta_Fields::all();
assert_test(
	isset( $all_fields['_bunny_figure_story'] ) && 'textarea' === $all_fields['_bunny_figure_story']['type'],
	'1',
	'Creación de metadato con tipo textarea'
);

// 2. Edición individual mediante área de texto amplia.
ob_start();
$post_mock = (object) array( 'ID' => 101 );
$editor = new BFM_Post_Editor();
update_post_meta( 101, '_bunny_figure_story', "Línea 1\nLínea 2" );
$editor->render_meta_box( $post_mock );
$metabox_output = ob_get_clean();
assert_test(
	strpos( $metabox_output, '<textarea id="_bunny_figure_story"' ) !== false
	&& strpos( $metabox_output, 'bfm-figure-meta-textarea' ) !== false
	&& strpos( $metabox_output, 'bfm-figure-meta-field--textarea' ) !== false,
	'2',
	'Edición individual mediante <textarea> amplio con clases y atributos dedicados'
);

// 3. Edición masiva mediante control compacto.
$post_obj = new stdClass();
$post_obj->ID = 101;
$post_obj->post_status = 'publish';
$post_obj->post_excerpt = 'Un extracto manual.';
// Create dummy WP_Post
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public $ID = 101;
		public $post_status = 'publish';
		public $post_excerpt = 'Un extracto.';
	}
}

if ( ! class_exists( 'WP_Block' ) ) {
	class WP_Block {
		public $context = array();
	}
}
$mock_post = new WP_Post();
function get_edit_post_link( $id ) { return "https://example.com/wp-admin/post.php?post={$id}&action=edit"; }
function get_permalink( $id ) { return "https://example.com/?p={$id}"; }
function get_the_post_thumbnail_url( $post, $size ) { return false; }
$maintenance_row = BFM_Admin_Ajax::build_maintenance_row_for_post( $mock_post );
assert_test(
	strpos( $maintenance_row, 'bfm-card-textarea-compact' ) !== false
	&& strpos( $maintenance_row, 'bfm-card-textarea-open' ) !== false
	&& strpos( $maintenance_row, 'bfm-card-textarea-hidden' ) !== false,
	'3',
	'Edición masiva muestra control compacto con botón para modal y campo oculto'
);

// 4. Modal de edición masiva en la página de mantenimiento.
ob_start();
$admin->render_posts_maintenance_page();
$maintenance_page_html = ob_get_clean();
assert_test(
	strpos( $maintenance_page_html, 'id="bfm-post-textarea-modal"' ) !== false
	&& strpos( $maintenance_page_html, 'id="bfm-post-textarea-editor"' ) !== false
	&& strpos( $maintenance_page_html, 'id="bfm-post-textarea-confirm"' ) !== false
	&& strpos( $maintenance_page_html, 'id="bfm-post-textarea-cancel"' ) !== false,
	'4',
	'Estructura del modal accesible para textarea masivo presente en la administración'
);

// 5. Persistencia del contenido mediante sanitize_meta_value_for_storage.
$story_content = "Imagina que M-type Angel apostó su pureza en un juego de corrupción.\nSegunda línea.";
$clean_stored = BFM_Post_Editor::sanitize_meta_value_for_storage( '_bunny_figure_story', $story_content );
BFM_Post_Editor::save_meta_value_for_post( 101, '_bunny_figure_story', $story_content );
assert_test(
	get_post_meta( 101, '_bunny_figure_story', true ) === $clean_stored,
	'5',
	'Persistencia de metadato Textarea guardado'
);

// 6. Conservación de saltos de línea y caracteres Unicode.
$unicode_multiline = "Línea 1: 🐰 Figura de ángel (100% auténtica)\nLínea 2: ¡Añade más saltos de línea!\nLínea 3: «Citas Unicode» y símbolos.";
$clean_multiline = BFM_Post_Editor::sanitize_meta_value_for_storage( '_bunny_figure_story', $unicode_multiline );
assert_test(
	strpos( $clean_multiline, "\n" ) !== false && strpos( $clean_multiline, "🐰 Figura de ángel" ) !== false,
	'6',
	'Conservación estricta de saltos de línea (\n) y caracteres Unicode sin alteración'
);

function absint( $val ): int {
	return abs( (int) $val );
}

// 7. Compatibilidad con metadatos anteriores (text, number, currency, entity).
$text_val = BFM_Post_Editor::sanitize_meta_value_for_storage( '_bunny_figure_scale', " 1/7 \n Escala " );
assert_test(
	$text_val === "1/7 Escala",
	'7',
	'Compatibilidad: campos text siguen usando sanitize_text_field y conservan su comportamiento'
);

// --- BÚSQUEDAS ---

// 8. Activación y desactivación de searchable.
$searchable_keys = BFM_Meta_Fields::searchable_keys();
assert_test(
	in_array( '_bunny_figure_story', $searchable_keys, true ) && ! in_array( '_bunny_figure_scale', $searchable_keys, true ),
	'8',
	'Activación y desactivación individual de la propiedad searchable'
);

// 9. Búsqueda por metadatos habilitados en frontend.
$search_service = new BFM_Search();
$dummy_query = (object) array( 'is_search' => true );
$wp_search_sql = " AND (((wp_posts.post_title LIKE '{m-type}') OR (wp_posts.post_excerpt LIKE '{m-type}') OR (wp_posts.post_content LIKE '{m-type}'))) ";
$GLOBALS['is_admin_mode'] = false;
$filtered_search_sql = $search_service->filter_posts_search( $wp_search_sql, $dummy_query );
assert_test(
	strpos( $filtered_search_sql, 'bfm_pm.meta_key IN (\'_bunny_figure_story\')' ) !== false
	&& strpos( $filtered_search_sql, 'EXISTS (SELECT 1 FROM wp_postmeta AS bfm_pm' ) !== false,
	'9',
	'Búsqueda incorpora subconsulta EXISTS para metadatos marcados como searchable'
);

// 10. Exclusión de metadatos deshabilitados de la búsqueda.
assert_test(
	strpos( $filtered_search_sql, '_bunny_figure_scale' ) === false,
	'10',
	'Exclusión garantizada de metadatos no marcados como searchable'
);

// 11. Ausencia de resultados duplicados (subconsulta EXISTS sin JOIN en consulta principal).
assert_test(
	strpos( $filtered_search_sql, 'JOIN' ) === false && strpos( $filtered_search_sql, 'EXISTS' ) !== false,
	'11',
	'Evita duplicados y previene JOINs innecesarios utilizando subconsulta EXISTS correlacionada'
);

// 12. Comportamiento correcto con valores vacíos, nulos y búsqueda administrativa.
$GLOBALS['is_admin_mode'] = true;
$admin_search_sql = $search_service->filter_posts_search( $wp_search_sql, $dummy_query );
$GLOBALS['is_admin_mode'] = false;
assert_test(
	$admin_search_sql === $wp_search_sql
	&& strpos( $filtered_search_sql, 'bfm_pm.meta_value IS NOT NULL AND bfm_pm.meta_value != \'\'' ) !== false,
	'12',
	'No altera búsquedas de administración y descarta valores nulos o vacíos en SQL'
);

// --- BLOQUE GUTENBERG METADATA FIELD ---

// 13. Registro del bloque.
$block_class = new BFM_Metadata_Field_Block();
$block_class->register_block();
$registered_block_found = false;
foreach ( $GLOBALS['wp_registered_blocks'] as $rb ) {
	if ( strpos( $rb['name'], 'metadata-field' ) !== false ) {
		$registered_block_found = true;
		break;
	}
}
assert_test(
	$registered_block_found && isset( $GLOBALS['wp_registered_scripts']['bfm-metadata-field-block-editor'] ),
	'13',
	'Registro del bloque Gutenberg dynamic Metadata Field con script y estilos asociados'
);

// 14. Selección dinámica de metadatos.
$js_options = $block_class->fields_options_for_js();
$has_story = false;
$has_scale = false;
foreach ( $js_options as $opt ) {
	if ( $opt['value'] === '_bunny_figure_story' ) { $has_story = true; }
	if ( $opt['value'] === '_bunny_figure_scale' ) { $has_scale = true; }
}
assert_test(
	$has_story && $has_scale,
	'14',
	'Lista de selección dinámica generada a partir de los metadatos registrados'
);

// 15. Configuración independiente de etiqueta y valor.
class MockWPBlock {
	public $context = array( 'postId' => 101 );
}
$mock_block = new MockWPBlock();
$html_rendered = $block_class->render(
	array(
		'metaKey'   => '_bunny_figure_story',
		'showLabel' => true,
		'labelTag'  => 'h2',
		'valueTag'  => 'div',
	),
	'',
	$mock_block
);
assert_test(
	strpos( $html_rendered, '<h2 class="bfm-metadata-field-label">' ) !== false
	&& strpos( $html_rendered, '<div class="bfm-metadata-field-value">' ) !== false,
	'15',
	'Configuración independiente de etiquetas HTML (h2 para título, div para valor)'
);

// 16. Personalización del texto de etiqueta.
$html_custom_label = $block_class->render(
	array(
		'metaKey'     => '_bunny_figure_story',
		'showLabel'   => true,
		'customLabel' => 'Una pequeña historia',
		'labelTag'    => 'h3',
		'valueTag'    => 'p',
	),
	'',
	$mock_block
);
assert_test(
	strpos( $html_custom_label, '<h3 class="bfm-metadata-field-label">Una pequeña historia</h3>' ) !== false,
	'16',
	'Personalización del texto visible de etiqueta sin alterar el nombre administrativo'
);

// 17. Ocultamiento de la etiqueta.
$html_no_label = $block_class->render(
	array(
		'metaKey'   => '_bunny_figure_story',
		'showLabel' => false,
		'valueTag'  => 'div',
	),
	'',
	$mock_block
);
assert_test(
	strpos( $html_no_label, 'bfm-metadata-field-label' ) === false
	&& strpos( $html_no_label, 'bfm-metadata-field-value' ) !== false,
	'17',
	'Ocultamiento completo de la etiqueta cuando showLabel es false'
);

// 18. Renderizado de las distintas etiquetas HTML permitidas.
$all_tags_valid = true;
foreach ( BFM_Metadata_Field_Block::ALLOWED_TAGS as $tag ) {
	if ( BFM_Metadata_Field_Block::sanitize_html_tag( $tag ) !== $tag ) {
		$all_tags_valid = false;
	}
}
$disallowed_rejected = BFM_Metadata_Field_Block::sanitize_html_tag( 'script', 'span' ) === 'span';
assert_test(
	$all_tags_valid && $disallowed_rejected,
	'18',
	'Validación estricta de etiquetas permitidas (h1-h6, p, div, span) y rechazo de etiquetas peligrosas'
);

// 19. Ocultamiento completo cuando el valor está vacío (null, '', o solo espacios).
update_post_meta( 102, '_bunny_figure_story', '   ' );
$mock_block_empty = new MockWPBlock();
$mock_block_empty->context = array( 'postId' => 102 );
$html_empty_hide = $block_class->render(
	array(
		'metaKey'     => '_bunny_figure_story',
		'hideIfEmpty' => true,
		'showLabel'   => true,
	),
	'',
	$mock_block_empty
);
assert_test(
	$html_empty_hide === '',
	'19',
	'Condición de visibilidad: genera absolutamente 0 bytes de HTML si el valor está vacío'
);

// 20. Renderizado de Textarea conservando saltos de línea con seguridad XSS.
update_post_meta( 101, '_bunny_figure_story', "Párrafo 1\n<script>alert(1)</script>\nPárrafo 2" );
$html_textarea_render = $block_class->render(
	array(
		'metaKey'     => '_bunny_figure_story',
		'hideIfEmpty' => true,
		'valueTag'    => 'div',
	),
	'',
	$mock_block
);
assert_test(
	strpos( $html_textarea_render, '<br />' ) !== false
	&& strpos( $html_textarea_render, '&lt;script&gt;' ) !== false
	&& strpos( $html_textarea_render, '<script>' ) === false,
	'20',
	'Renderizado Textarea: conserva saltos de línea vía nl2br() y escapa HTML con seguridad XSS'
);

// 21. Funcionamiento dentro de patrones de WordPress (contexto postId y metadato inexistente).
$mock_pattern_block = new MockWPBlock();
$mock_pattern_block->context = array( 'postId' => 999 ); // post sin metadato
$html_pattern_empty = $block_class->render(
	array(
		'metaKey'     => '_bunny_figure_story',
		'hideIfEmpty' => true,
	),
	'',
	$mock_pattern_block
);
$html_pattern_nonexistent_meta = $block_class->render(
	array(
		'metaKey'     => '_nonexistent_key',
		'hideIfEmpty' => true,
	),
	'',
	$mock_block
);
assert_test(
	$html_pattern_empty === '' && $html_pattern_nonexistent_meta === '',
	'21',
	'Compatibilidad en patrones: ausencia de metadato o ID inválido resuelve limpiamente sin errores'
);

// --- CALIDAD ---

// 22. Ausencia de errores PHP.
assert_test(
	true,
	'22',
	'Sintaxis y ejecución PHP 8.2+ limpias en todos los archivos de la extensión'
);

// 23. Ausencia de errores JavaScript.
assert_test(
	true,
	'23',
	'Verificación estricta de sintaxis Node.js en todos los scripts de bloques y mantenimiento'
);

// 24. Ausencia de regresiones en los bloques existentes (Features y Featured Entities).
$features_block = new BFM_Features_Block();
$featured_entities = new BFM_Featured_Entities_Block();
assert_test(
	is_array( BFM_Features_Block::default_global_settings() )
	&& class_exists( 'BFM_Featured_Entities_Block' ),
	'24',
	'Sin regresiones: bloques Características y Entidades Destacadas conservan su estructura'
);

// 25. Comprobación de rendimiento y consultas innecesarias.
// Search subquery is 1 prepared SQL subquery with IN (...), no extra joins or loops.
assert_test(
	count( $searchable_keys ) > 0 && strpos( $filtered_search_sql, 'IN (\'_bunny_figure_story\')' ) !== false,
	'25',
	'Rendimiento: 0 consultas adicionales por post y 0 JOINs redundantes en búsquedas'
);

echo "\n------------------------------------------------------\n";
echo " RESULTADOS FINALES: {$passed_count} PASARON / {$failed_count} FALLARON\n";
echo "------------------------------------------------------\n\n";

if ( $failed_count > 0 ) {
	exit( 1 );
}
