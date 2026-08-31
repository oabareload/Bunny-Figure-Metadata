<?php
/**
 * BFM_Admin
 *
 * Registers the plugin admin menu (one top-level page plus one submenu
 * per administrable entity) and enqueues admin assets only on this
 * plugin's own screens.
 *
 * Page slugs: bfm-manufacturers, bfm-illustrators, bfm-series, bfm-characters.
 * The first entity's page also serves as the top-level menu page, the
 * same pattern used by Bunny Magazine's dashboard/submenu structure.
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BFM_Admin {

	/**
	 * Prefix used to build each entity's admin page slug.
	 */
	const PAGE_PREFIX = 'bfm-';

	/**
	 * Page slug of the top-level menu entry (first entity: manufacturers).
	 */
	const MENU_SLUG = self::PAGE_PREFIX . 'manufacturers';
	const SETTINGS_SLUG = self::PAGE_PREFIX . 'settings';
	const FEATURES_SETTINGS_SLUG = self::PAGE_PREFIX . 'features-settings';
	const POSTS_MAINTENANCE_SLUG = self::PAGE_PREFIX . 'posts';

	/**
	 * Build the admin page slug for a given entity slug.
	 *
	 * @param string $entity_slug Entity slug (manufacturers, illustrators, series, characters).
	 * @return string
	 */
	public static function page_slug( string $entity_slug ): string {
		return self::PAGE_PREFIX . $entity_slug;
	}

	/**
	 * Register the top-level menu page and one submenu page per entity.
	 *
	 * @return void
	 */
	public function register_menu(): void {

		$entities = BFM_Meta_Fields::entities();

		if ( empty( $entities ) ) {
			add_menu_page(
				__( 'Bunny Figure Metadata', 'bunny-figure-metadata' ),
				__( 'Figure Metadata', 'bunny-figure-metadata' ),
				'manage_options',
				self::SETTINGS_SLUG,
				array( $this, 'render_settings_page' ),
				'dashicons-tag',
				57
			);
			add_submenu_page(
				self::SETTINGS_SLUG,
				__( 'Features', 'bunny-figure-metadata' ),
				__( 'Features', 'bunny-figure-metadata' ),
				'manage_options',
				self::FEATURES_SETTINGS_SLUG,
				array( $this, 'render_features_settings_page' )
			);
			return;
		}

		$first_slug = array_key_first( $entities );

		/*
		 * Build exactly one render callback per entity slug and reuse it for
		 * both add_menu_page() and add_submenu_page() when the same slug is
		 * used for both (the first entity acts as the top-level page).
		 *
		 * WordPress derives its internal admin hook name from the page slug,
		 * so the top-level page and its same-slug first submenu resolve to
		 * the identical hook. If each call is given a freshly created
		 * closure, WordPress cannot recognize them as the same callback
		 * (each closure has a distinct object identity) and hooks both,
		 * causing the page to render twice. Reusing one closure instance
		 * per slug lets WordPress's own callback-deduplication collapse the
		 * two registrations into a single hook entry, exactly as it already
		 * does when the same callable is passed to both calls.
		 */
		$callbacks = array();

		foreach ( $entities as $slug => $entity ) {
			$callbacks[ $slug ] = $this->render_callback_for( $slug );
		}

		add_menu_page(
			__( 'Bunny Figure Metadata', 'bunny-figure-metadata' ),
			__( 'Figure Metadata', 'bunny-figure-metadata' ),
			'manage_options',
			self::page_slug( $first_slug ),
			$callbacks[ $first_slug ],
			'dashicons-tag',
			57
		);

		foreach ( $entities as $slug => $entity ) {
			add_submenu_page(
				self::page_slug( $first_slug ),
				$entity['label'],
				$entity['label'],
				'manage_options',
				self::page_slug( $slug ),
				$callbacks[ $slug ]
			);
		}

		add_submenu_page(
			self::page_slug( $first_slug ),
			__( 'Posts', 'bunny-figure-metadata' ),
			__( 'Posts', 'bunny-figure-metadata' ),
			'manage_options',
			self::POSTS_MAINTENANCE_SLUG,
			array( $this, 'render_posts_maintenance_page' )
		);

		add_submenu_page(
			self::page_slug( $first_slug ),
			__( 'Settings', 'bunny-figure-metadata' ),
			__( 'Settings', 'bunny-figure-metadata' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			self::page_slug( $first_slug ),
			__( 'Features', 'bunny-figure-metadata' ),
			__( 'Features', 'bunny-figure-metadata' ),
			'manage_options',
			self::FEATURES_SETTINGS_SLUG,
			array( $this, 'render_features_settings_page' )
		);
	}

	/** Register the global metadata option. */
	public function register_settings(): void {
		register_setting(
			'bfm_metadata_settings',
			BFM_Meta_Fields::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_metadata_settings' ),
				'default'           => array(),
			)
		);

		register_setting(
			'bfm_metadata_settings',
			BFM_Character_Identity::OPTION_NAME,
			array(
				'sanitize_callback' => array( 'BFM_Character_Identity', 'sanitize_settings' ),
				'default'           => BFM_Character_Identity::default_settings(),
			)
		);

		register_setting(
			'bfm_features_settings',
			BFM_Features_Block::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_features_settings' ),
				'default'           => BFM_Features_Block::default_global_settings(),
			)
		);
	}

	/**
	 * Sanitize metadata rows submitted by the settings screen.
	 *
	 * @param mixed $raw_settings Submitted option value.
	 * @return array<string, array{label:string,entity:bool,type:string}>
	 */
	public function sanitize_metadata_settings( $raw_settings ): array {
		$rows  = is_array( $raw_settings ) ? $raw_settings : array();
		$clean = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$meta_key = sanitize_key( $row['meta_key'] ?? '' );
			$label    = sanitize_text_field( $row['label'] ?? '' );

			if ( '' === $meta_key || '' === $label || isset( $clean[ $meta_key ] ) ) {
				if ( '' !== $meta_key && isset( $clean[ $meta_key ] ) ) {
					add_settings_error( BFM_Meta_Fields::OPTION_NAME, 'duplicate_metadata', __( 'El meta key ya está utilizado; se omitió el duplicado.', 'bunny-figure-metadata' ) );
				}
				continue;
			}

			$clean[ $meta_key ] = array(
				'label'        => $label,
				'entity'       => ! empty( $row['entity'] ),
				'type'         => in_array( $row['type'] ?? '', array( 'text', 'number', 'currency', 'month' ), true ) ? $row['type'] : 'text',
				'tag'          => ! empty( $row['entity'] ) && ! empty( $row['tag'] ),
				'url'          => ! empty( $row['entity'] ) && ! empty( $row['url'] ),
				'logo'         => ! empty( $row['entity'] ) && ! empty( $row['logo'] ),
				'image_source' => ! empty( $row['entity'] ) && 'related' === ( $row['image_source'] ?? 'custom' ) ? 'related' : 'custom',
			);
		}

		return $clean;
	}

	/**
	 * Sanitize the global Features block configuration.
	 *
	 * @param mixed $raw_settings Submitted option value.
	 * @return array<string, array{show:bool,prefix:string,default:string,prefixTag:string,prefixClass:string,valueTag:string,valueClass:string,link_target:string,order:int}>
	 */
	public function sanitize_features_settings( $raw_settings ): array {
		$defaults = BFM_Features_Block::default_global_settings();
		$rows     = is_array( $raw_settings ) ? $raw_settings : array();
		$clean    = array();

		foreach ( BFM_Features_Block::fixed_field_keys() as $meta_key ) {
			$raw = is_array( $rows[ $meta_key ] ?? null ) ? $rows[ $meta_key ] : array();
			$base = $defaults[ $meta_key ];
			$clean[ $meta_key ] = array(
				'show'        => ! empty( $raw['show'] ),
				'prefix'      => sanitize_text_field( $raw['prefix'] ?? $base['prefix'] ),
				'default'     => sanitize_text_field( $raw['default'] ?? $base['default'] ),
				'prefixTag'   => BFM_Features_Block::sanitize_html_tag( $raw['prefixTag'] ?? $base['prefixTag'] ),
				'prefixClass' => isset( $raw['prefixClass'] ) ? sanitize_html_class( (string) $raw['prefixClass'] ) : $base['prefixClass'],
				'valueTag'    => BFM_Features_Block::sanitize_html_tag( $raw['valueTag'] ?? $base['valueTag'] ),
				'valueClass'  => isset( $raw['valueClass'] ) ? sanitize_html_class( (string) $raw['valueClass'] ) : $base['valueClass'],
				'link_target' => in_array( $raw['link_target'] ?? $base['link_target'], array( 'tag', 'external' ), true ) ? $raw['link_target'] : 'tag',
				'order'       => max( 1, absint( $raw['order'] ?? $base['order'] ) ),
			);
		}

		return $clean;
	}

	/** Render the post maintenance screen. */
	public function render_posts_maintenance_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'bunny-figure-metadata' ) );
		}

		$fields = BFM_Meta_Fields::all();
		$args = array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page' => 20,
			'paged'          => 1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		);
		$query = new WP_Query( $args );
		$rows = '';
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$rows .= BFM_Admin_Ajax::build_maintenance_row_for_post( get_post() );
			}
		}
		wp_reset_postdata();
		?>
		<div class="wrap bunny-wrap bfm-admin-wrap bfm-posts-maintenance-wrap">
			<?php BFM_Admin_Header::render( self::POSTS_MAINTENANCE_SLUG, __( 'Posts', 'bunny-figure-metadata' ) ); ?>
			<div class="bunny-page-content bfm-page-content-full">
				<div class="bfm-box bfm-maintenance-box">
					<h2 class="bfm-box-title"><?php esc_html_e( 'Mantenimiento de posts', 'bunny-figure-metadata' ); ?></h2>
					<form id="bfm-post-maintenance-filter-form" class="bfm-post-maintenance-filters">
						<div class="bfm-post-maintenance-filter-grid">
							<label>
								<span><?php esc_html_e( 'Título', 'bunny-figure-metadata' ); ?></span>
								<input type="text" id="bfm-post-maintenance-title" name="title" class="regular-text">
							</label>
							<label>
								<span><?php esc_html_e( 'Metadato', 'bunny-figure-metadata' ); ?></span>
								<select id="bfm-post-maintenance-meta-key" name="meta_key">
									<option value=""><?php esc_html_e( 'Todos', 'bunny-figure-metadata' ); ?></option>
									<?php foreach ( $fields as $meta_key => $field ) : ?>
										<option value="<?php echo esc_attr( $meta_key ); ?>"><?php echo esc_html( $field['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label>
								<span><?php esc_html_e( 'Estado del valor', 'bunny-figure-metadata' ); ?></span>
								<select id="bfm-post-maintenance-value-status" name="value_status">
									<option value="all"><?php esc_html_e( 'Todos', 'bunny-figure-metadata' ); ?></option>
									<option value="with"><?php esc_html_e( 'Con valor', 'bunny-figure-metadata' ); ?></option>
									<option value="without"><?php esc_html_e( 'Sin valor', 'bunny-figure-metadata' ); ?></option>
								</select>
							</label>
							<label>
								<span><?php esc_html_e( 'Valor', 'bunny-figure-metadata' ); ?></span>
								<input type="text" id="bfm-post-maintenance-value" name="value" class="regular-text">
							</label>
						</div>
						<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Buscar', 'bunny-figure-metadata' ); ?></button></p>
					</form>
					<div class="bfm-post-cards-grid" id="bfm-post-maintenance-body">
						<?php echo $rows; ?>
					</div>
					<div class="bfm-post-maintenance-footer">
						<div class="bfm-post-maintenance-loading" id="bfm-post-maintenance-loading" style="display:none;" aria-live="polite">
							<span class="spinner is-active"></span>
							<span class="bfm-loading-text"><?php esc_html_e( 'Cargando posts...', 'bunny-figure-metadata' ); ?></span>
						</div>
						<p class="bfm-post-maintenance-status" id="bfm-post-maintenance-status" role="status" aria-live="polite"></p>
						<button type="button" class="button" id="bfm-post-maintenance-load-more" style="display:none;">
							<?php esc_html_e( 'Cargar más', 'bunny-figure-metadata' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/** Render the global metadata settings screen. */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'bunny-figure-metadata' ) );
		}

		$fields = BFM_Meta_Fields::all();
		$rows   = array_values( $fields );
		foreach ( array_keys( $fields ) as $index => $meta_key ) {
			$rows[ $index ]['meta_key'] = $meta_key;
		}
		?>
		<div class="wrap bunny-wrap bfm-admin-wrap">
			<?php BFM_Admin_Header::render( self::SETTINGS_SLUG, __( 'Settings', 'bunny-figure-metadata' ) ); ?>
			<div class="bunny-page-content">
			<div class="bfm-box">
			<h2 class="bfm-box-title"><?php esc_html_e( 'Metadata Settings', 'bunny-figure-metadata' ); ?></h2>
			<p><?php esc_html_e( 'Define los campos disponibles en el editor y cuáles funcionan como catálogos de entidades.', 'bunny-figure-metadata' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'bfm_metadata_settings' ); ?>
				<?php settings_errors( BFM_Meta_Fields::OPTION_NAME ); ?>
				<div id="bfm-metadata-rows">
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Meta key', 'bunny-figure-metadata' ); ?></th>
						<th><?php esc_html_e( 'Nombre', 'bunny-figure-metadata' ); ?></th>
						<th><?php esc_html_e( 'Tipo', 'bunny-figure-metadata' ); ?></th>
						<th><?php esc_html_e( 'Catálogo / entidad', 'bunny-figure-metadata' ); ?></th>
						<th><?php esc_html_e( 'Integraciones del catálogo', 'bunny-figure-metadata' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $index => $field ) : ?>
						<tr>
							<td><code><?php echo esc_html( $field['meta_key'] ); ?></code><input type="hidden" name="<?php echo esc_attr( self::settings_input_name( $index, 'meta_key' ) ); ?>" value="<?php echo esc_attr( $field['meta_key'] ); ?>"></td>
							<td><input type="text" class="regular-text" name="<?php echo esc_attr( self::settings_input_name( $index, 'label' ) ); ?>" value="<?php echo esc_attr( $field['label'] ); ?>"></td>
							<td><select name="<?php echo esc_attr( self::settings_input_name( $index, 'type' ) ); ?>">
								<?php foreach ( array( 'text', 'number', 'currency', 'month' ) as $type ) : ?>
									<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $field['type'], $type ); ?>><?php echo esc_html( $type ); ?></option>
								<?php endforeach; ?>
							</select></td>
							<td><label><input type="checkbox" class="bfm-catalog-toggle" name="<?php echo esc_attr( self::settings_input_name( $index, 'entity' ) ); ?>" value="1" <?php checked( $field['entity'] ); ?>> <?php esc_html_e( 'Usar entidad', 'bunny-figure-metadata' ); ?></label></td>
							<td><div class="bfm-catalog-integrations" <?php echo $field['entity'] ? '' : 'hidden'; ?>>
								<div class="bfm-catalog-checks">
									<label class="bfm-inline-check"><input type="checkbox" name="<?php echo esc_attr( self::settings_input_name( $index, 'tag' ) ); ?>" value="1" <?php checked( $field['tag'] ); ?>> <?php esc_html_e( 'Asociar a TAG', 'bunny-figure-metadata' ); ?></label>
									<label class="bfm-inline-check"><input type="checkbox" name="<?php echo esc_attr( self::settings_input_name( $index, 'url' ) ); ?>" value="1" <?php checked( $field['url'] ); ?>> <?php esc_html_e( 'Permitir URL externa', 'bunny-figure-metadata' ); ?></label>
									<label class="bfm-inline-check"><input type="checkbox" class="bfm-logo-toggle" name="<?php echo esc_attr( self::settings_input_name( $index, 'logo' ) ); ?>" value="1" <?php checked( $field['logo'] ); ?>> <?php esc_html_e( 'Usar logo', 'bunny-figure-metadata' ); ?></label>
								</div>
								<div class="bfm-image-source-row" <?php echo $field['logo'] ? '' : 'hidden'; ?>>
									<label class="bfm-inline-select">
										<span><?php esc_html_e( 'Origen de imagen', 'bunny-figure-metadata' ); ?></span>
										<select class="bfm-image-source-select" name="<?php echo esc_attr( self::settings_input_name( $index, 'image_source' ) ); ?>">
											<option value="custom" <?php selected( $field['image_source'] ?? 'custom', 'custom' ); ?>><?php esc_html_e( 'Imagen personalizada', 'bunny-figure-metadata' ); ?></option>
											<option value="related" <?php selected( $field['image_source'] ?? 'custom', 'related' ); ?>><?php esc_html_e( 'Post relacionado', 'bunny-figure-metadata' ); ?></option>
										</select>
									</label>
								</div>
								<?php if ( BFM_Character_Identity::CHARACTER_META_KEY === $field['meta_key'] ) : $identity = BFM_Character_Identity::settings(); ?>
									<div class="bfm-character-identity-row">
										<div class="bfm-catalog-checks">
											<label class="bfm-inline-check"><input type="checkbox" name="<?php echo esc_attr( BFM_Character_Identity::OPTION_NAME . '[identity_fields][]' ); ?>" value="series" <?php checked( in_array( 'series', $identity['identity_fields'], true ) ); ?>> <?php esc_html_e( 'Distinguir por Serie', 'bunny-figure-metadata' ); ?></label>
											<label class="bfm-inline-check"><input type="checkbox" name="<?php echo esc_attr( BFM_Character_Identity::OPTION_NAME . '[identity_fields][]' ); ?>" value="illustrator" <?php checked( in_array( 'illustrator', $identity['identity_fields'], true ) ); ?>> <?php esc_html_e( 'Distinguir por Ilustrador (si Serie = personaje original)', 'bunny-figure-metadata' ); ?></label>
										</div>
										<label class="bfm-inline-select">
											<span><?php esc_html_e( 'Valor de "personaje original"', 'bunny-figure-metadata' ); ?></span>
											<input type="text" class="regular-text" name="<?php echo esc_attr( BFM_Character_Identity::OPTION_NAME . '[original_value]' ); ?>" value="<?php echo esc_attr( $identity['original_value'] ); ?>">
										</label>
									</div>
								<?php endif; ?>
							</div></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>
				<p><button type="button" class="button" id="bfm-add-metadata"><?php esc_html_e( '+ Agregar metadato', 'bunny-figure-metadata' ); ?></button></p>
				<?php submit_button(); ?>
			</form>
		</div>
			</div>
			<div id="bfm-metadata-modal" class="bfm-modal" hidden>
				<div class="bfm-modal-content" role="dialog" aria-modal="true" aria-labelledby="bfm-metadata-modal-title">
					<h2 id="bfm-metadata-modal-title"><?php esc_html_e( 'Agregar metadato', 'bunny-figure-metadata' ); ?></h2>
					<label for="bfm-new-metadata-label"><?php esc_html_e( 'Nombre visible', 'bunny-figure-metadata' ); ?></label>
					<input type="text" id="bfm-new-metadata-label" class="regular-text" autocomplete="off">
					<p class="description"><?php esc_html_e( 'La meta key se generará automáticamente con el prefijo _bunny_figure_.', 'bunny-figure-metadata' ); ?></p>
					<p class="bfm-modal-error" id="bfm-new-metadata-error" role="alert"></p>
					<p class="submit"><button type="button" class="button button-primary" id="bfm-confirm-metadata"><?php esc_html_e( 'Agregar', 'bunny-figure-metadata' ); ?></button> <button type="button" class="button" id="bfm-cancel-metadata"><?php esc_html_e( 'Cancelar', 'bunny-figure-metadata' ); ?></button></p>
				</div>
			</div>
		<?php
	}

	/** Render the global Features block settings screen. */
	public function render_features_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'bunny-figure-metadata' ) );
		}

		$settings = BFM_Features_Block::global_field_settings();
		?>
		<div class="wrap bunny-wrap bfm-admin-wrap">
			<?php BFM_Admin_Header::render( self::FEATURES_SETTINGS_SLUG, __( 'Features', 'bunny-figure-metadata' ) ); ?>
			<div class="bunny-page-content">
				<div class="bfm-box">
					<h2 class="bfm-box-title"><?php esc_html_e( 'Features block settings', 'bunny-figure-metadata' ); ?></h2>
					<p><?php esc_html_e( 'Configura qué campos forman parte del bloque y cómo se muestran globalmente en todas las instancias.', 'bunny-figure-metadata' ); ?></p>
					<form method="post" action="options.php">
						<?php settings_fields( 'bfm_features_settings' ); ?>
						<?php settings_errors( BFM_Features_Block::OPTION_NAME ); ?>
						<div id="bfm-metadata-rows-block">
						<table class="widefat striped">
							<thead>
							<tr>
								<th><?php esc_html_e( 'Campo', 'bunny-figure-metadata' ); ?></th>
								<th><?php esc_html_e( 'Mostrar', 'bunny-figure-metadata' ); ?></th>
								<th><?php esc_html_e( 'Orden', 'bunny-figure-metadata' ); ?></th>
								<th><?php esc_html_e( 'Prefijo', 'bunny-figure-metadata' ); ?></th>
								<th><?php esc_html_e( 'Valor por defecto', 'bunny-figure-metadata' ); ?></th>
								<th><?php esc_html_e( 'Prefijo HTML', 'bunny-figure-metadata' ); ?></th>
								<th><?php esc_html_e( 'Clase prefijo', 'bunny-figure-metadata' ); ?></th>
								<th><?php esc_html_e( 'Valor HTML', 'bunny-figure-metadata' ); ?></th>
								<th><?php esc_html_e( 'Clase valor', 'bunny-figure-metadata' ); ?></th>
								<th><?php esc_html_e( 'Destino del enlace', 'bunny-figure-metadata' ); ?></th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ( BFM_Meta_Fields::all() as $meta_key => $field ) : $setting = $settings[ $meta_key ]; ?>
								<tr>
									<td><strong><?php echo esc_html( $field['label'] ); ?></strong><br><code><?php echo esc_html( $meta_key ); ?></code></td>
									<td><label><input type="checkbox" name="<?php echo esc_attr( BFM_Features_Block::OPTION_NAME . '[' . $meta_key . '][show]' ); ?>" value="1" <?php checked( $setting['show'] ); ?>></label></td>
									<td><input type="number" min="1" step="1" name="<?php echo esc_attr( BFM_Features_Block::OPTION_NAME . '[' . $meta_key . '][order]' ); ?>" value="<?php echo esc_attr( (string) $setting['order'] ); ?>" class="small-text"></td>
									<td><input type="text" name="<?php echo esc_attr( BFM_Features_Block::OPTION_NAME . '[' . $meta_key . '][prefix]' ); ?>" value="<?php echo esc_attr( $setting['prefix'] ); ?>" class="regular-text"></td>
									<td><input type="text" name="<?php echo esc_attr( BFM_Features_Block::OPTION_NAME . '[' . $meta_key . '][default]' ); ?>" value="<?php echo esc_attr( $setting['default'] ); ?>" class="regular-text"></td>
									<td><select name="<?php echo esc_attr( BFM_Features_Block::OPTION_NAME . '[' . $meta_key . '][prefixTag]' ); ?>">
										<?php foreach ( BFM_Features_Block::ALLOWED_TAGS as $allowed_tag ) : ?>
											<option value="<?php echo esc_attr( $allowed_tag ); ?>" <?php selected( $setting['prefixTag'], $allowed_tag ); ?>><?php echo esc_html( $allowed_tag ); ?></option>
										<?php endforeach; ?>
									</select></td>
									<td><input type="text" name="<?php echo esc_attr( BFM_Features_Block::OPTION_NAME . '[' . $meta_key . '][prefixClass]' ); ?>" value="<?php echo esc_attr( $setting['prefixClass'] ); ?>" class="regular-text"></td>
									<td><select name="<?php echo esc_attr( BFM_Features_Block::OPTION_NAME . '[' . $meta_key . '][valueTag]' ); ?>">
										<?php foreach ( BFM_Features_Block::ALLOWED_TAGS as $allowed_tag ) : ?>
											<option value="<?php echo esc_attr( $allowed_tag ); ?>" <?php selected( $setting['valueTag'], $allowed_tag ); ?>><?php echo esc_html( $allowed_tag ); ?></option>
										<?php endforeach; ?>
									</select></td>
									<td><input type="text" name="<?php echo esc_attr( BFM_Features_Block::OPTION_NAME . '[' . $meta_key . '][valueClass]' ); ?>" value="<?php echo esc_attr( $setting['valueClass'] ); ?>" class="regular-text"></td>
									<td><select name="<?php echo esc_attr( BFM_Features_Block::OPTION_NAME . '[' . $meta_key . '][link_target]' ); ?>">
										<option value="tag" <?php selected( $setting['link_target'], 'tag' ); ?>><?php esc_html_e( 'Tag', 'bunny-figure-metadata' ); ?></option>
										<?php if ( BFM_Meta_Fields::has_capability( $meta_key, 'url' ) ) : ?>
											<option value="external" <?php selected( $setting['link_target'], 'external' ); ?>><?php esc_html_e( 'Enlace externo', 'bunny-figure-metadata' ); ?></option>
										<?php endif; ?>
									</select></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						</div>
						<?php submit_button(); ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	private static function settings_input_name( int $index, string $key ): string {
		return BFM_Meta_Fields::OPTION_NAME . '[' . $index . '][' . $key . ']';
	}

	/**
	 * Build a render callback bound to a specific entity slug.
	 *
	 * @param string $slug Entity slug.
	 * @return callable
	 */
	private function render_callback_for( string $slug ): callable {
		return function () use ( $slug ) {
			$this->render_entity_page( $slug );
		};
	}

	/**
	 * Render an entity admin page: capability check, then either the
	 * value list (default) or, when a `value` query var is present, the
	 * drill-down list of posts containing that exact value.
	 *
	 * @param string $slug Entity slug.
	 * @return void
	 */
	private function render_entity_page( string $slug ): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'bunny-figure-metadata' ) );
		}

		$entities = BFM_Meta_Fields::entities();

		if ( ! isset( $entities[ $slug ] ) ) {
			wp_die( esc_html__( 'Unknown entity.', 'bunny-figure-metadata' ) );
		}

		$entity = $entities[ $slug ];

		$value = isset( $_GET['value'] ) ? sanitize_text_field( wp_unslash( $_GET['value'] ) ) : '';

		if ( '' !== $value ) {
			$this->render_entity_posts_page( $slug, $entity, $value );
			return;
		}

		$this->render_entity_list_page( $slug, $entity );
	}

	/**
	 * Render the entity value list (the default screen): fetch values via
	 * the repository (single aggregated query), sort them per the
	 * requested column, resolve their associated tags and complementary
	 * info in a single batch each, and include the view.
	 *
	 * @param string              $slug   Entity slug.
	 * @param array<string,string> $entity Entity definition.
	 * @return void
	 */
	private function render_entity_list_page( string $slug, array $entity ): void {

		list( $orderby, $order ) = $this->current_sort();

		$values = BFM_Entity_Repository::get_values_with_counts( $entity['meta_key'] );
		$values = BFM_Entity_Repository::sort_values( $values, $orderby, $order );

		list( $tag_map, $tag_names ) = ! empty( $entity['tag'] )
			? $this->resolve_tag_associations( $entity['meta_key'] )
			: array( array(), array() );
		list( $info_map, $logo_previews, $effective_image_ids ) = ( ! empty( $entity['url'] ) || ! empty( $entity['logo'] ) )
			? $this->resolve_entity_info( $entity )
			: array( array(), array(), array() );

		$view_file = BFM_PATH . 'admin/views/' . $slug . '.php';

		if ( ! file_exists( $view_file ) ) {
			$view_file = BFM_PATH . 'admin/views/partial-entity-list.php';
		}

		require $view_file;
	}

	/**
	 * Render the drill-down list of posts that contain an exact entity
	 * value, paginated. Generic across all four entities: it only needs
	 * the entity definition (label + meta_key) and the value to filter by.
	 *
	 * @param string              $slug   Entity slug.
	 * @param array<string,string> $entity Entity definition.
	 * @param string              $value  Exact entity value to filter by.
	 * @return void
	 */
	private function render_entity_posts_page( string $slug, array $entity, string $value ): void {

		$paged    = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page = 20;

		$result = BFM_Entity_Repository::get_posts_for_value( $entity['meta_key'], $value, $paged, $per_page );

		$rows = array_map( array( $this, 'build_post_row' ), $result['posts'] );

		$total       = $result['total'];
		$total_pages = $result['total_pages'];
		$paged       = max( 1, min( $paged, max( 1, $total_pages ) ) );

		// $value stays the raw identity key (character) for matching/pagination
		// links; $display_value is only ever used to label the heading.
		$display_value = ( BFM_Character_Identity::CHARACTER_META_KEY === $entity['meta_key'] )
			? BFM_Character_Identity::display_name( $value )
			: $value;

		$view_file = BFM_PATH . 'admin/views/entity-posts.php';

		if ( ! file_exists( $view_file ) ) {
			wp_die( esc_html__( 'View not found.', 'bunny-figure-metadata' ) );
		}

		require $view_file;
	}

	/**
	 * Build the display-ready data for a single row of the drill-down
	 * posts table. Category/tag lookups hit WP_Query's own term cache
	 * (primed for the whole result set by get_posts_for_value()), and the
	 * thumbnail lookup hits its primed meta cache — neither issues a new
	 * query per row.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	public function build_post_row( WP_Post $post ): array {

		$categories = get_the_category( $post->ID );
		$tags       = get_the_tags( $post->ID );

		$edit_url = current_user_can( 'edit_post', $post->ID ) ? get_edit_post_link( $post->ID, 'raw' ) : '';

		return array(
			'id'         => $post->ID,
			'title'      => get_the_title( $post ),
			'thumbnail'  => get_the_post_thumbnail_url( $post, 'thumbnail' ) ?: '',
			'categories' => $categories && ! is_wp_error( $categories ) ? wp_list_pluck( $categories, 'name' ) : array(),
			'tags'       => $tags && ! is_wp_error( $tags ) ? wp_list_pluck( $tags, 'name' ) : array(),
			'date'       => get_the_date( '', $post ),
			'modified'   => get_the_modified_date( '', $post ),
			'edit_url'   => $edit_url ?: '',
			'view_url'   => get_permalink( $post ),
		);
	}

	/**
	 * Read and sanitize the requested sort column/direction from the
	 * (non-destructive, read-only) query string. Defaults preserve the
	 * table's original order: most posts first, name ascending.
	 *
	 * @return array{0: string, 1: string} [ orderby, order ]
	 */
	private function current_sort(): array {

		$allowed_orderby = array( 'name', 'posts' );
		$allowed_order   = array( 'asc', 'desc' );

		$orderby = 'posts';
		if ( isset( $_GET['orderby'] ) ) {
			$requested = sanitize_key( wp_unslash( $_GET['orderby'] ) );
			if ( in_array( $requested, $allowed_orderby, true ) ) {
				$orderby = $requested;
			}
		}

		$order = 'desc';
		if ( isset( $_GET['order'] ) ) {
			$requested = strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) );
			if ( in_array( $requested, $allowed_order, true ) ) {
				$order = $requested;
			}
		}

		return array( $orderby, $order );
	}

	/**
	 * Resolve every stored tag association for a meta key in a single
	 * batched term query, instead of one get_term() call per row.
	 *
	 * @param string $meta_key Source meta key.
	 * @return array{0: array<string,int>, 1: array<int,string>} [ value => term_id, term_id => term_name ]
	 */
	private function resolve_tag_associations( string $meta_key ): array {

		$tag_map  = BFM_Entity_Tag_Map::get_for_meta_key( $meta_key );
		$term_ids = array_values( array_unique( array_filter( array_map( 'absint', $tag_map ) ) ) );

		$tag_names = array();

		if ( ! empty( $term_ids ) ) {

			$terms = get_terms(
				array(
					'taxonomy'   => 'post_tag',
					'include'    => $term_ids,
					'hide_empty' => false,
				)
			);

			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$tag_names[ $term->term_id ] = $term->name;
				}
			}
		}

		return array( $tag_map, $tag_names );
	}

	/**
	 * Resolve complementary info (URL + logo) for every value under a
	 * meta key. The batch logo-thumbnail resolution itself lives in
	 * BFM_Entity_Info_Map::get_logo_urls() (shared with the "Entidades
	 * destacadas" block), so it is not duplicated here.
	 *
	 * When the entity's image source is configured as "related post"
	 * (BFM_Meta_Fields), also resolves the automatic pick for every value
	 * (BFM_Entity_Repository::get_related_image_attachment_ids()) and
	 * folds it in: a manually stored image_id always wins over the
	 * automatic one, exactly like the entity value's own logo does today.
	 *
	 * @param array<string,mixed> $entity Entity definition (meta_key, image_source, ...).
	 * @return array{0: array<string,array{url:string,image_id:int}>, 1: array<int,string>, 2: array<string,int>} [ value => info, image_id => resolved_url, value => effective image_id ]
	 */
	private function resolve_entity_info( array $entity ): array {

		$meta_key = $entity['meta_key'];
		$info_map = BFM_Entity_Info_Map::get_for_meta_key( $meta_key );

		$effective_image_ids = ! empty( $entity['logo'] )
			? BFM_Entity_Info_Map::get_effective_image_ids( $meta_key, $entity['image_source'] ?? 'custom' )
			: array();

		$image_ids_to_resolve = array_values( array_filter( $effective_image_ids ) );

		$logo_previews = BFM_Entity_Info_Map::get_logo_urls( $image_ids_to_resolve );

		return array( $info_map, $logo_previews, $effective_image_ids );
	}

	/**
	 * Enqueue admin CSS/JS only on this plugin's own screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {

		if ( false === strpos( $hook, self::PAGE_PREFIX ) ) {
			return;
		}

		if ( false !== strpos( $hook, self::SETTINGS_SLUG ) ) {
			wp_enqueue_script(
				'bfm-metadata-settings',
				BFM_URL . 'assets/js/metadata-settings.js',
				array( 'jquery' ),
				BFM_VERSION,
				true
			);
		}

		if ( false !== strpos( $hook, self::POSTS_MAINTENANCE_SLUG ) ) {
			wp_enqueue_script(
				'bfm-post-editor-chips',
				BFM_URL . 'assets/js/post-editor-chips.js',
				array( 'jquery' ),
				BFM_VERSION,
				true
			);
			wp_localize_script(
				'bfm-post-editor-chips',
				'BFM_POST_EDITOR_CHIPS',
				array(
					'suggestions' => BFM_Post_Editor::get_entity_suggestions(),
					'i18n'        => array(
						'remove' => __( 'Quitar', 'bunny-figure-metadata' ),
					),
				)
			);
			wp_enqueue_script(
				'bfm-post-metadata-maintenance',
				BFM_URL . 'assets/js/post-metadata-maintenance.js',
				array( 'jquery' ),
				BFM_VERSION,
				true
			);
			wp_localize_script(
				'bfm-post-metadata-maintenance',
				'BFM_POST_MAINTENANCE',
				array(
					'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
					'nonce'       => wp_create_nonce( 'bfm_post_metadata' ),
					'loadAction'  => 'bfm_load_post_metadata_rows',
					'saveAction'  => 'bfm_save_post_metadata',
					'i18n'        => array(
						'saved'      => __( 'Guardado', 'bunny-figure-metadata' ),
						'saving'     => __( 'Guardando...', 'bunny-figure-metadata' ),
						'loading'    => __( 'Cargando posts...', 'bunny-figure-metadata' ),
						'error'      => __( 'No se pudo guardar.', 'bunny-figure-metadata' ),
						'load_error' => __( 'No se pudieron cargar los posts.', 'bunny-figure-metadata' ),
						'no_posts'   => __( 'No se encontraron posts.', 'bunny-figure-metadata' ),
					),
				)
			);
		}

		wp_enqueue_style(
			'bfm-bunny-admin',
			BFM_URL . 'assets/css/bunny-admin.css',
			array(),
			BFM_VERSION
		);

		wp_enqueue_style(
			'bfm-admin',
			BFM_URL . 'assets/css/admin.css',
			array( 'bfm-bunny-admin' ),
			BFM_VERSION
		);

		wp_enqueue_script(
			'bfm-entity-tag-select',
			BFM_URL . 'assets/js/entity-tag-select.js',
			array( 'jquery', 'jquery-ui-autocomplete' ),
			BFM_VERSION,
			true
		);

		wp_localize_script(
			'bfm-entity-tag-select',
			'BFM_ENTITY_TAG',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( BFM_Admin_Ajax::NONCE_ACTION ),
				'searchAction' => 'bfm_search_tags',
				'setAction'    => 'bfm_set_entity_tag',
				'removeAction' => 'bfm_remove_entity_tag',
				'i18n'         => array(
					'select'   => __( 'Seleccionar TAG...', 'bunny-figure-metadata' ),
					'remove'   => __( 'Quitar TAG', 'bunny-figure-metadata' ),
					'saving'   => __( 'Guardando...', 'bunny-figure-metadata' ),
					'removing' => __( 'Quitando...', 'bunny-figure-metadata' ),
					'error'    => __( 'Ocurrió un error. Inténtalo de nuevo.', 'bunny-figure-metadata' ),
				),
			)
		);

		// Media Library integration (Featured-Image-style logo picker).
		wp_enqueue_media();

		wp_enqueue_script(
			'bfm-entity-info',
			BFM_URL . 'assets/js/entity-info.js',
			array( 'jquery' ),
			BFM_VERSION,
			true
		);

		wp_localize_script(
			'bfm-entity-info',
			'BFM_ENTITY_INFO',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( BFM_Admin_Ajax::NONCE_ACTION ),
				'setUrlAction'      => 'bfm_set_entity_url',
				'setImageAction'    => 'bfm_set_entity_image',
				'removeImageAction' => 'bfm_remove_entity_image',
				'i18n'              => array(
					'selectImage'  => __( 'Seleccionar logo', 'bunny-figure-metadata' ),
					'useImage'     => __( 'Usar esta imagen', 'bunny-figure-metadata' ),
					'upload'       => __( 'Subir imagen', 'bunny-figure-metadata' ),
					'replace'      => __( 'Reemplazar', 'bunny-figure-metadata' ),
					'remove'       => __( 'Eliminar', 'bunny-figure-metadata' ),
					'saving'       => __( 'Guardando...', 'bunny-figure-metadata' ),
					'error'        => __( 'Ocurrió un error. Inténtalo de nuevo.', 'bunny-figure-metadata' ),
					'useAutomatic' => __( 'Usar automática', 'bunny-figure-metadata' ),
					'automatic'    => __( 'Automática', 'bunny-figure-metadata' ),
				),
			)
		);
	}
}
