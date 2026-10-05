<?php
/**
 * Página de administración: WooCommerce → Envío Perú.
 * Dos pestañas: Tarifas y Ajustes. Sin licencias, sin pasos extra.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UEP_Admin {

	const CAPABILITY = 'manage_woocommerce';
	const PAGE_SLUG  = 'uep-envio-peru';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		add_action( 'admin_post_uep_guardar_tarifa', array( __CLASS__, 'guardar_tarifa' ) );
		add_action( 'admin_post_uep_editar_tarifa', array( __CLASS__, 'editar_tarifa' ) );
		add_action( 'admin_post_uep_eliminar_tarifa', array( __CLASS__, 'eliminar_tarifa' ) );
		add_action( 'admin_post_uep_guardar_ajustes', array( __CLASS__, 'guardar_ajustes' ) );
		add_action( 'admin_post_uep_exportar_csv', array( __CLASS__, 'exportar_csv' ) );
		add_action( 'admin_post_uep_importar_csv', array( __CLASS__, 'importar_csv' ) );
		add_action( 'admin_post_uep_exportar_config', array( __CLASS__, 'exportar_config' ) );
		add_action( 'wp_ajax_uep_simular', array( __CLASS__, 'simular' ) );
		add_action( 'admin_post_uep_importar_config', array( __CLASS__, 'importar_config' ) );
		add_action( 'admin_post_uep_convertir_checkout', array( __CLASS__, 'convertir_checkout' ) );
		add_action( 'admin_post_uep_restaurar_checkout', array( __CLASS__, 'restaurar_checkout' ) );

		// Aviso si la página de finalizar compra usa el checkout por bloques
		// (el plugin necesita el checkout clásico).
		add_action( 'admin_notices', array( __CLASS__, 'aviso_checkout_bloques' ) );
	}

	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Envío Perú', 'ubigeo-envio-peru' ),
			__( 'Envío Perú', 'ubigeo-envio-peru' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	public static function assets( $hook ) {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'uep-admin', UEP_PLUGIN_URL . 'assets/css/admin.css', array(), UEP_VERSION );
		wp_enqueue_script( 'uep-admin', UEP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), UEP_VERSION, true );

		wp_localize_script(
			'uep-admin',
			'uep_admin',
			array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'uep_ubigeo' ),
				'sim_nonce' => wp_create_nonce( 'uep_simular' ),
				'i18n'      => array(
					'todas_prov' => __( 'Todo el departamento', 'ubigeo-envio-peru' ),
					'todos_dist' => __( 'Toda la provincia', 'ubigeo-envio-peru' ),
					'elige'      => __( 'Elige…', 'ubigeo-envio-peru' ),
					'calculando' => __( 'Calculando…', 'ubigeo-envio-peru' ),
					'error'      => __( 'No se pudo simular. Recarga la página e inténtalo de nuevo.', 'ubigeo-envio-peru' ),
					/* translators: 1: visibles 2: total */
					'conteo'     => __( '%1$d de %2$d tarifas', 'ubigeo-envio-peru' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Render
	 * ------------------------------------------------------------------- */

	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'ubigeo-envio-peru' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'inicio'; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap uep-wrap">
			<h1><?php esc_html_e( 'Envío Perú', 'ubigeo-envio-peru' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Ubigeo en el checkout + costo de envío por departamento, provincia o distrito.', 'ubigeo-envio-peru' ); ?>
			</p>

			<?php self::avisos(); ?>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( self::url( 'inicio' ) ); ?>" class="nav-tab <?php echo 'inicio' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Inicio', 'ubigeo-envio-peru' ); ?>
				</a>
				<a href="<?php echo esc_url( self::url( 'tarifas' ) ); ?>" class="nav-tab <?php echo 'tarifas' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Tarifas de envío', 'ubigeo-envio-peru' ); ?>
				</a>
				<a href="<?php echo esc_url( self::url( 'ajustes' ) ); ?>" class="nav-tab <?php echo 'ajustes' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Ajustes', 'ubigeo-envio-peru' ); ?>
				</a>
				<a href="<?php echo esc_url( self::url( 'ayuda' ) ); ?>" class="nav-tab <?php echo 'ayuda' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Guía rápida', 'ubigeo-envio-peru' ); ?>
				</a>
			</h2>

			<?php
			if ( 'ajustes' === $tab ) {
				self::render_ajustes();
			} elseif ( 'ayuda' === $tab ) {
				self::render_ayuda();
			} elseif ( 'tarifas' === $tab ) {
				self::render_tarifas();
			} else {
				self::render_inicio();
			}
			?>
		</div>
		<?php
	}

	private static function url( $tab ) {
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	private static function avisos() {
		if ( ! isset( $_GET['uep_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$msg    = sanitize_key( $_GET['uep_msg'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$textos = array(
			'tarifa_ok'           => __( 'Tarifa guardada.', 'ubigeo-envio-peru' ),
			'tarifa_error'        => __( 'No se pudo guardar la tarifa. Elige al menos un departamento y un costo válido.', 'ubigeo-envio-peru' ),
			'eliminada'           => __( 'Tarifa eliminada.', 'ubigeo-envio-peru' ),
			'ajustes_ok'          => __( 'Ajustes guardados.', 'ubigeo-envio-peru' ),
			'import_error'        => __( 'No se pudo leer el archivo CSV.', 'ubigeo-envio-peru' ),
			'config_ok'           => __( 'Configuración importada correctamente (ajustes y tarifas).', 'ubigeo-envio-peru' ),
			'config_error'        => __( 'No se pudo importar: el archivo no es una configuración válida de Envío Perú.', 'ubigeo-envio-peru' ),
			'checkout_convertido' => __( 'La página de finalizar compra ahora usa el checkout clásico. Se guardó una copia del contenido anterior.', 'ubigeo-envio-peru' ),
			'checkout_restaurado' => __( 'Se restauró el contenido anterior de la página de finalizar compra.', 'ubigeo-envio-peru' ),
			'checkout_error'      => __( 'No se encontró la página de finalizar compra de WooCommerce.', 'ubigeo-envio-peru' ),
		);

		if ( 'import' === $msg ) {
			$ok  = isset( $_GET['uep_ok'] ) ? absint( $_GET['uep_ok'] ) : 0;   // phpcs:ignore WordPress.Security.NonceVerification
			$err = isset( $_GET['uep_err'] ) ? absint( $_GET['uep_err'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

			printf(
				'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
				$err > 0 ? 'notice-warning' : 'notice-success',
				esc_html(
					sprintf(
						/* translators: 1: importadas 2: con error */
						__( 'Importación terminada: %1$d tarifa(s) guardada(s), %2$d fila(s) con error (nombre no encontrado o datos incompletos).', 'ubigeo-envio-peru' ),
						$ok,
						$err
					)
				)
			);
			return;
		}

		if ( isset( $textos[ $msg ] ) ) {
			$clase = false === strpos( $msg, 'error' ) ? 'notice-success' : 'notice-error';
			printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $clase ), esc_html( $textos[ $msg ] ) );
		}
	}

	/* ---------------------------------------------------------------------
	 * Pestaña: Inicio (resumen, revisión y simulador)
	 * ------------------------------------------------------------------- */

	private static function render_inicio() {
		$settings = uep_get_settings();
		$checks   = self::revision_configuracion( $settings );
		$alertas  = count(
			array_filter(
				$checks,
				function ( $c ) {
					return 'alerta' === $c['estado'];
				}
			)
		);
		?>
		<div class="uep-card">
			<h2><?php esc_html_e( 'Así funciona hoy tu tienda', 'ubigeo-envio-peru' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Resumen automático de tu configuración: lo que ve cada cliente según dónde vive.', 'ubigeo-envio-peru' ); ?></p>

			<div class="uep-resumen">
				<?php foreach ( self::resumen_tienda( $settings ) as $tarjeta ) : ?>
					<div class="uep-resumen-tarjeta uep-resumen-<?php echo esc_attr( $tarjeta['tipo'] ); ?>">
						<h3><?php echo esc_html( $tarjeta['titulo'] ); ?></h3>
						<?php if ( '' !== $tarjeta['subtitulo'] ) : ?>
							<p class="uep-resumen-sub"><?php echo esc_html( $tarjeta['subtitulo'] ); ?></p>
						<?php endif; ?>
						<ul>
							<?php foreach ( $tarjeta['items'] as $item ) : ?>
								<li><?php echo esc_html( $item ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="uep-card">
			<h2>
				<?php esc_html_e( 'Revisión de la configuración', 'ubigeo-envio-peru' ); ?>
				<?php if ( $alertas ) : ?>
					<span class="uep-badge uep-badge-alerta">
						<?php echo esc_html( sprintf( /* translators: %d: cantidad */ _n( '%d punto por revisar', '%d puntos por revisar', $alertas, 'ubigeo-envio-peru' ), $alertas ) ); ?>
					</span>
				<?php else : ?>
					<span class="uep-badge uep-badge-ok"><?php esc_html_e( 'Todo en orden', 'ubigeo-envio-peru' ); ?></span>
				<?php endif; ?>
			</h2>

			<ul class="uep-revision">
				<?php foreach ( $checks as $c ) : ?>
					<li class="uep-revision-<?php echo esc_attr( $c['estado'] ); ?>">
						<span class="uep-revision-icono" aria-hidden="true"></span>
						<span>
							<?php echo esc_html( $c['texto'] ); ?>
							<?php if ( ! empty( $c['enlace'] ) ) : ?>
								<a href="<?php echo esc_url( $c['enlace'] ); ?>"><?php echo esc_html( $c['enlace_texto'] ); ?></a>
							<?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="uep-card" id="uep-simulador">
			<h2><?php esc_html_e( 'Simulador: ¿qué verá un cliente?', 'ubigeo-envio-peru' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Elige un distrito para ver las opciones de envío, los precios y los métodos de pago que aparecerían en el checkout. No crea pedidos ni cambia nada.', 'ubigeo-envio-peru' ); ?>
			</p>

			<div class="uep-sim-form">
				<label>
					<span><?php esc_html_e( 'Departamento', 'ubigeo-envio-peru' ); ?></span>
					<select id="uep_sim_depa">
						<option value=""><?php esc_html_e( 'Elige…', 'ubigeo-envio-peru' ); ?></option>
						<?php foreach ( uep_get_departamentos() as $dep ) : ?>
							<option value="<?php echo esc_attr( $dep['idDepa'] ); ?>"><?php echo esc_html( $dep['departamento'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Provincia', 'ubigeo-envio-peru' ); ?></span>
					<select id="uep_sim_prov" disabled>
						<option value=""><?php esc_html_e( 'Elige…', 'ubigeo-envio-peru' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Distrito', 'ubigeo-envio-peru' ); ?></span>
					<select id="uep_sim_dist" disabled>
						<option value=""><?php esc_html_e( 'Elige…', 'ubigeo-envio-peru' ); ?></option>
					</select>
				</label>
				<fieldset class="uep-sim-rol">
					<legend><?php esc_html_e( 'Ver como', 'ubigeo-envio-peru' ); ?></legend>
					<label><input type="radio" name="uep_sim_rol" value="cliente" checked> <?php esc_html_e( 'Cliente', 'ubigeo-envio-peru' ); ?></label>
					<label><input type="radio" name="uep_sim_rol" value="gestor"> <?php esc_html_e( 'Administrador / gestor', 'ubigeo-envio-peru' ); ?></label>
				</fieldset>
			</div>

			<div id="uep_sim_resultado" class="uep-sim-resultado" aria-live="polite"></div>
		</div>

		<div class="uep-card uep-atajos">
			<h2><?php esc_html_e( 'Tareas frecuentes', 'ubigeo-envio-peru' ); ?></h2>
			<a class="button" href="<?php echo esc_url( self::url( 'tarifas' ) ); ?>"><?php esc_html_e( 'Cambiar el precio de un distrito', 'ubigeo-envio-peru' ); ?></a>
			<a class="button" href="<?php echo esc_url( self::url( 'ajustes' ) . '#uep-sec-domicilio' ); ?>"><?php esc_html_e( 'Agregar una zona de reparto', 'ubigeo-envio-peru' ); ?></a>
			<a class="button" href="<?php echo esc_url( self::url( 'ajustes' ) . '#uep-sec-flash' ); ?>"><?php esc_html_e( 'Cambiar la hora límite del Flash', 'ubigeo-envio-peru' ); ?></a>
			<a class="button" href="<?php echo esc_url( self::url( 'ajustes' ) . '#uep-sec-pagos' ); ?>"><?php esc_html_e( 'Elegir quién paga con qué', 'ubigeo-envio-peru' ); ?></a>
			<a class="button" href="<?php echo esc_url( self::url( 'ayuda' ) ); ?>"><?php esc_html_e( 'Ver la guía rápida', 'ubigeo-envio-peru' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Precio en la moneda base, como texto plano (sin HTML).
	 *
	 * @param float $monto Monto en la moneda base.
	 * @return string
	 */
	private static function precio_texto( $monto ) {
		$html = wc_price( (float) $monto, array( 'currency' => get_option( 'woocommerce_currency' ) ) );

		return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Títulos de las pasarelas de pago activas cuyos IDs se indican.
	 *
	 * @param array $ids IDs de pasarela.
	 * @return string[]
	 */
	private static function titulos_pasarelas( $ids ) {
		$ids = array_filter( (array) $ids );

		if ( ! $ids || ! function_exists( 'WC' ) ) {
			return array();
		}

		$todas   = WC()->payment_gateways()->payment_gateways();
		$titulos = array();

		foreach ( $ids as $id ) {
			if ( isset( $todas[ $id ] ) && 'yes' === $todas[ $id ]->enabled ) {
				$titulos[] = $todas[ $id ]->get_title();
			}
		}

		return $titulos;
	}

	/**
	 * Días de atención del recojo en lenguaje natural ("de lunes a sábado").
	 *
	 * @param array $cerrados Días cerrados (0 = domingo … 6 = sábado).
	 * @return string
	 */
	private static function dias_atencion_texto( $cerrados ) {
		$nombres = array(
			1 => __( 'lunes', 'ubigeo-envio-peru' ),
			2 => __( 'martes', 'ubigeo-envio-peru' ),
			3 => __( 'miércoles', 'ubigeo-envio-peru' ),
			4 => __( 'jueves', 'ubigeo-envio-peru' ),
			5 => __( 'viernes', 'ubigeo-envio-peru' ),
			6 => __( 'sábado', 'ubigeo-envio-peru' ),
			0 => __( 'domingo', 'ubigeo-envio-peru' ),
		);

		$cerrados = array_map( 'absint', (array) $cerrados );
		$orden    = array( 1, 2, 3, 4, 5, 6, 0 );
		$abiertos = array_values( array_diff( $orden, $cerrados ) );

		if ( 7 === count( $abiertos ) ) {
			return __( 'todos los días', 'ubigeo-envio-peru' );
		}
		if ( ! $abiertos ) {
			return __( 'ningún día (revisa los días sin atención)', 'ubigeo-envio-peru' );
		}

		// ¿Es un tramo seguido de la semana? (ej. lunes a sábado).
		$inicio   = array_search( $abiertos[0], $orden, true );
		$seguidos = array_slice( $orden, $inicio, count( $abiertos ) ) === $abiertos;

		if ( $seguidos && count( $abiertos ) >= 3 ) {
			return sprintf(
				/* translators: 1: primer día 2: último día */
				__( 'de %1$s a %2$s', 'ubigeo-envio-peru' ),
				$nombres[ $abiertos[0] ],
				$nombres[ end( $abiertos ) ]
			);
		}

		return implode(
			', ',
			array_map(
				function ( $d ) use ( $nombres ) {
					return $nombres[ $d ];
				},
				$abiertos
			)
		);
	}

	/**
	 * Tarjetas del resumen "Así funciona hoy tu tienda".
	 *
	 * @param array $settings Ajustes.
	 * @return array[]
	 */
	private static function resumen_tienda( $settings ) {
		$tarjetas    = array();
		$cobertura   = 'yes' === $settings['cobertura_enabled'];
		$zonas_cov   = (array) $settings['cobertura_zonas'];
		$pagos_cov   = self::titulos_pasarelas( $settings['pago_solo_cobertura'] );
		$pagos_rec   = self::titulos_pasarelas( $settings['pago_recojo'] );
		$pagos_admin = self::titulos_pasarelas( $settings['pago_solo_admin'] );

		// --- Clientes dentro de las zonas de cobertura ---
		$items = array(
			sprintf(
				/* translators: %s: nombre del envío */
				__( '%s, con el precio de cada distrito (pestaña Tarifas).', 'ubigeo-envio-peru' ),
				$settings['method_title']
			),
		);

		if ( 'yes' === $settings['express_enabled'] ) {
			$items[] = '' !== (string) $settings['express_cutoff']
				? sprintf(
					/* translators: 1: nombre del Flash 2: hora límite */
					__( '%1$s. Después de las %2$s muestra el aviso de que el mismo día solo aplica a compras anteriores.', 'ubigeo-envio-peru' ),
					$settings['express_title'],
					date_i18n( 'g:i a', strtotime( '1970-01-01 ' . $settings['express_cutoff'] ) )
				)
				: sprintf(
					/* translators: %s: nombre del Flash */
					__( '%s, sin hora límite.', 'ubigeo-envio-peru' ),
					$settings['express_title']
				);
		}

		if ( 'yes' === $settings['pickup_enabled'] ) {
			$items[] = sprintf(
				/* translators: 1: nombre del recojo 2: días mínimos 3: días de atención 4: hora inicio 5: hora fin */
				__( '%1$s: desde %2$d día(s) después de la compra, %3$s de %4$s a %5$s.', 'ubigeo-envio-peru' ),
				$settings['pickup_title'],
				absint( $settings['pickup_min_days'] ),
				self::dias_atencion_texto( $settings['pickup_closed_days'] ),
				$settings['pickup_start'],
				$settings['pickup_end']
			);

			$zonas_rec = (array) $settings['pickup_zonas'];
			sort( $zonas_rec );
			$zonas_ord = $zonas_cov;
			sort( $zonas_ord );

			if ( 'yes' === $settings['pickup_zone_enabled'] && $zonas_rec !== $zonas_ord ) {
				$items[] = sprintf(
					/* translators: %s: zonas */
					__( 'El recojo solo se ofrece en: %s.', 'ubigeo-envio-peru' ),
					implode( ', ', array_map( 'uep_zona_etiqueta', $zonas_rec ) )
				);
			}

			if ( $pagos_rec ) {
				$items[] = sprintf(
					/* translators: %s: métodos de pago */
					__( 'Si eligen recojo, solo pueden pagar con: %s.', 'ubigeo-envio-peru' ),
					implode( ', ', $pagos_rec )
				);
			}
		}

		if ( $cobertura && $pagos_cov ) {
			$items[] = sprintf(
				/* translators: %s: métodos de pago */
				__( 'Disponible solo en estas zonas: %s.', 'ubigeo-envio-peru' ),
				implode( ', ', $pagos_cov )
			);
		}

		$tarjetas[] = array(
			'tipo'      => 'cobertura',
			'titulo'    => $cobertura ? __( 'Clientes en tus zonas de cobertura', 'ubigeo-envio-peru' ) : __( 'Todos los clientes', 'ubigeo-envio-peru' ),
			'subtitulo' => $cobertura
				? implode( ' · ', array_map( 'uep_zona_etiqueta', $zonas_cov ) )
				: __( 'La cobertura está desactivada: el envío a domicilio se ofrece en todo el país.', 'ubigeo-envio-peru' ),
			'items'     => $items,
		);

		// --- Clientes fuera de cobertura ---
		if ( $cobertura ) {
			$items    = array(
				sprintf(
					/* translators: %s: nombre del envío por agencia */
					__( 'Solo ven: %s.', 'ubigeo-envio-peru' ),
					$settings['agencia_title']
				),
				__( 'No ven envío a domicilio, Flash ni recojo en tienda.', 'ubigeo-envio-peru' ),
			);
			$agencias = UEP_Agencia::lista_agencias();

			if ( $agencias ) {
				$items[] = sprintf(
					/* translators: %s: agencias */
					__( 'Agencias con las que trabajas: %s.', 'ubigeo-envio-peru' ),
					implode( ', ', $agencias )
				);
			}
			if ( $pagos_cov ) {
				$items[] = sprintf(
					/* translators: %s: métodos de pago */
					__( 'No pueden pagar con: %s.', 'ubigeo-envio-peru' ),
					implode( ', ', $pagos_cov )
				);
			}

			$tarjetas[] = array(
				'tipo'      => 'agencia',
				'titulo'    => __( 'Clientes del resto del país', 'ubigeo-envio-peru' ),
				'subtitulo' => __( 'Cualquier distrito fuera de tus zonas de cobertura.', 'ubigeo-envio-peru' ),
				'items'     => $items,
			);
		}

		// --- Equipo ---
		$items = array();

		foreach ( uep_metodos_admin() as $metodo ) {
			$items[] = sprintf(
				/* translators: %s: método interno */
				__( 'Envío interno: %s.', 'ubigeo-envio-peru' ),
				$metodo['nombre']
			);
		}
		if ( $pagos_admin ) {
			$items[] = sprintf(
				/* translators: %s: métodos de pago */
				__( 'Métodos de pago que solo ve el equipo: %s.', 'ubigeo-envio-peru' ),
				implode( ', ', $pagos_admin )
			);
		}
		if ( 'yes' === $settings['pickup_enabled'] ) {
			$items[] = __( 'Pueden elegir cualquier fecha y hora en el recojo en tienda.', 'ubigeo-envio-peru' );
		}
		if ( $cobertura ) {
			$items[] = __( 'Ven los campos de agencia y sede en el checkout (opcionales).', 'ubigeo-envio-peru' );
		}

		$tarjetas[] = array(
			'tipo'      => 'equipo',
			'titulo'    => __( 'Solo administradores y gestores', 'ubigeo-envio-peru' ),
			'subtitulo' => __( 'Lo que ve tu equipo al registrar pedidos desde la tienda.', 'ubigeo-envio-peru' ),
			'items'     => $items,
		);

		return $tarjetas;
	}

	/**
	 * Distritos de las zonas de cobertura que no tienen tarifa propia (ni de
	 * su provincia ni de su departamento) y por eso cobran el costo por defecto.
	 *
	 * @param array   $zonas   Zonas "idDepa|idProv".
	 * @param array[] $tarifas Tarifas de uep_get_tarifas().
	 * @return string[] Nombres de distrito.
	 */
	private static function distritos_sin_tarifa( $zonas, $tarifas ) {
		$t_dist = array();
		$t_prov = array();
		$t_depa = array();

		foreach ( $tarifas as $t ) {
			if ( 1 !== (int) $t['estado'] ) {
				continue;
			}
			if ( (int) $t['idDist'] ) {
				$t_dist[ (int) $t['idDist'] ] = true;
			} elseif ( (int) $t['idProv'] ) {
				$t_prov[ (int) $t['idProv'] ] = true;
			} else {
				$t_depa[ (int) $t['idDepa'] ] = true;
			}
		}

		$sin = array();

		foreach ( (array) $zonas as $zona ) {
			if ( ! preg_match( '/^(\d+)\|(\d+)$/', (string) $zona, $m ) ) {
				continue;
			}

			$depa = (int) $m[1];
			$prov = (int) $m[2];

			if ( isset( $t_depa[ $depa ] ) ) {
				continue;
			}

			$provincias = $prov ? array( array( 'idProv' => $prov ) ) : uep_get_provincias( $depa );

			foreach ( $provincias as $pv ) {
				$id_prov = (int) $pv['idProv'];

				if ( isset( $t_prov[ $id_prov ] ) ) {
					continue;
				}

				foreach ( uep_get_distritos( $id_prov ) as $d ) {
					if ( ! isset( $t_dist[ (int) $d['idDist'] ] ) ) {
						$sin[] = trim( $d['distrito'] );
					}
				}
			}
		}

		return array_values( array_unique( $sin ) );
	}

	/**
	 * Revisión automática de la configuración.
	 *
	 * @param array $settings Ajustes.
	 * @return array[] Cada punto: estado (ok|alerta|info), texto, enlace, enlace_texto.
	 */
	private static function revision_configuracion( $settings ) {
		$c         = array();
		$cobertura = 'yes' === $settings['cobertura_enabled'];
		$ajustes   = self::url( 'ajustes' );

		// 1) Checkout clásico.
		if ( self::checkout_usa_bloques() ) {
			$c[] = array(
				'estado'       => 'alerta',
				'texto'        => __( 'Tu página de finalizar compra usa el checkout por bloques: ahí no aparecen los campos de ubigeo ni los costos de envío.', 'ubigeo-envio-peru' ),
				'enlace'       => self::url( 'ayuda' ) . '#uep-faq',
				'enlace_texto' => __( 'Cómo solucionarlo', 'ubigeo-envio-peru' ),
			);
		} else {
			$c[] = array(
				'estado' => 'ok',
				'texto'  => __( 'La página de finalizar compra usa el checkout clásico.', 'ubigeo-envio-peru' ),
			);
		}

		// 2) Plugins antiguos.
		$activos = (array) get_option( 'active_plugins', array() );

		if ( in_array( 'ubigeo-peru/ubigeo-peru.php', $activos, true ) || in_array( 'costo-ubigeo-peru/costo-ubigeo-peru.php', $activos, true ) ) {
			$c[] = array(
				'estado'       => 'alerta',
				'texto'        => __( 'Uno de los plugins de ubigeo anteriores sigue activo: duplicará campos y costos en el checkout.', 'ubigeo-envio-peru' ),
				'enlace'       => admin_url( 'plugins.php' ),
				'enlace_texto' => __( 'Ir a Plugins', 'ubigeo-envio-peru' ),
			);
		}

		// 3) Tarifas.
		$tarifas = uep_get_tarifas();

		if ( ! $tarifas ) {
			$c[] = array(
				'estado'       => 'alerta',
				'texto'        => sprintf(
					/* translators: %s: costo por defecto */
					__( 'No tienes tarifas: todos los envíos a domicilio cobrarán el costo por defecto (%s).', 'ubigeo-envio-peru' ),
					self::precio_texto( uep_tarifa_a_base( $settings['default_cost'] ) )
				),
				'enlace'       => self::url( 'tarifas' ),
				'enlace_texto' => __( 'Agregar tarifas', 'ubigeo-envio-peru' ),
			);
		} else {
			$c[] = array(
				'estado' => 'ok',
				'texto'  => sprintf(
					/* translators: %d: cantidad */
					_n( '%d tarifa registrada.', '%d tarifas registradas.', count( $tarifas ), 'ubigeo-envio-peru' ),
					count( $tarifas )
				),
			);
		}

		// 4) Distritos de cobertura sin tarifa propia.
		if ( $cobertura && $tarifas ) {
			$sin = self::distritos_sin_tarifa( $settings['cobertura_zonas'], $tarifas );

			if ( $sin ) {
				$muestra = implode( ', ', array_slice( $sin, 0, 6 ) ) . ( count( $sin ) > 6 ? '…' : '' );
				$c[]     = array(
					'estado'       => 'alerta',
					'texto'        => sprintf(
						/* translators: 1: cantidad 2: costo por defecto 3: distritos */
						_n(
							'%1$d distrito de tus zonas de cobertura no tiene tarifa y cobrará el costo por defecto (%2$s): %3$s.',
							'%1$d distritos de tus zonas de cobertura no tienen tarifa y cobrarán el costo por defecto (%2$s): %3$s.',
							count( $sin ),
							'ubigeo-envio-peru'
						),
						count( $sin ),
						self::precio_texto( uep_tarifa_a_base( $settings['default_cost'] ) ),
						$muestra
					),
					'enlace'       => self::url( 'tarifas' ),
					'enlace_texto' => __( 'Agregar tarifas', 'ubigeo-envio-peru' ),
				);
			} else {
				$c[] = array(
					'estado' => 'ok',
					'texto'  => __( 'Todos los distritos de tus zonas de cobertura tienen su tarifa.', 'ubigeo-envio-peru' ),
				);
			}
		}

		// 5) Pago contra entrega.
		$pasarelas = WC()->payment_gateways()->payment_gateways();

		if ( $cobertura && isset( $pasarelas['cod'] ) && 'yes' === $pasarelas['cod']->enabled ) {
			$titulo = $pasarelas['cod']->get_title();

			if ( in_array( 'cod', (array) $settings['pago_solo_cobertura'], true ) ) {
				$c[] = array(
					'estado' => 'ok',
					'texto'  => sprintf(
						/* translators: %s: método de pago */
						__( '"%s" solo se ofrece en tus zonas de cobertura.', 'ubigeo-envio-peru' ),
						$titulo
					),
				);
			} else {
				$c[] = array(
					'estado'       => 'alerta',
					'texto'        => sprintf(
						/* translators: %s: método de pago */
						__( '"%s" se ofrece en todo el país, incluidos los envíos por agencia.', 'ubigeo-envio-peru' ),
						$titulo
					),
					'enlace'       => $ajustes . '#uep-sec-pagos',
					'enlace_texto' => __( 'Limitarlo a las zonas de cobertura', 'ubigeo-envio-peru' ),
				);
			}
		}

		// 6) Flash.
		if ( 'yes' === $settings['express_enabled'] ) {
			if ( '' !== (string) $settings['express_cutoff'] ) {
				$c[] = array(
					'estado' => 'ok',
					'texto'  => sprintf(
						/* translators: %s: hora */
						__( 'El Flash avisa que el mismo día solo aplica a compras antes de las %s.', 'ubigeo-envio-peru' ),
						date_i18n( 'g:i a', strtotime( '1970-01-01 ' . $settings['express_cutoff'] ) )
					),
				);
			} else {
				$c[] = array(
					'estado'       => 'info',
					'texto'        => __( 'El Flash no tiene hora límite: siempre se ofrece como envío del mismo día.', 'ubigeo-envio-peru' ),
					'enlace'       => $ajustes . '#uep-sec-flash',
					'enlace_texto' => __( 'Configurar hora límite', 'ubigeo-envio-peru' ),
				);
			}
		}

		// 7) ERP.
		if ( 'yes' === $settings['erp_state'] ) {
			$c[] = array(
				'estado' => 'ok',
				'texto'  => __( 'Cada pedido guarda el código del departamento y el servicio de envío para tu ERP.', 'ubigeo-envio-peru' ),
			);
		} else {
			$c[] = array(
				'estado'       => 'info',
				'texto'        => __( 'La compatibilidad con ERP está desactivada.', 'ubigeo-envio-peru' ),
				'enlace'       => $ajustes . '#uep-sec-erp',
				'enlace_texto' => __( 'Activar', 'ubigeo-envio-peru' ),
			);
		}

		return $c;
	}

	/**
	 * Simulador: opciones de envío, precios y pagos para un distrito, usando
	 * exactamente la misma lógica que el checkout.
	 */
	public static function simular() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'html' => '' ), 403 );
		}
		check_ajax_referer( 'uep_simular', 'nonce' );

		$id_depa = isset( $_POST['idDepa'] ) ? absint( $_POST['idDepa'] ) : 0;
		$id_prov = isset( $_POST['idProv'] ) ? absint( $_POST['idProv'] ) : 0;
		$id_dist = isset( $_POST['idDist'] ) ? absint( $_POST['idDist'] ) : 0;
		$gestor  = isset( $_POST['rol'] ) && 'gestor' === sanitize_key( wp_unslash( $_POST['rol'] ) );

		if ( ! uep_validar_jerarquia( $id_depa, $id_prov, $id_dist ) ) {
			wp_send_json_error( array( 'html' => '<p>' . esc_html__( 'Elige departamento, provincia y distrito.', 'ubigeo-envio-peru' ) . '</p>' ) );
		}

		$settings  = uep_get_settings();
		$nombres   = uep_get_nombres_ubigeo( $id_depa, $id_prov, $id_dist );
		$cobertura = 'yes' === $settings['cobertura_enabled'];
		$dentro    = ! $cobertura || uep_zona_coincide( $settings['cobertura_zonas'], $id_depa, $id_prov );

		// Opciones de envío: el método real del checkout, viendo como el rol elegido.
		WC()->shipping();
		UEP_Shipping::cargar_metodo();

		$usuario = get_current_user_id();

		if ( ! $gestor ) {
			wp_set_current_user( 0 );
		}

		$metodo = new UEP_Metodo_Envio();
		$metodo->calculate_shipping(
			array(
				'contents'      => array(),
				'contents_cost' => 0,
				'destination'   => array(
					'country'   => 'PE',
					'state'     => '',
					'postcode'  => '',
					'city'      => $nombres['distrito'],
					'address'   => '',
					'address_2' => '',
				),
				'uep_ubigeo'    => array( $id_depa, $id_prov, $id_dist ),
			)
		);
		$rates = $metodo->rates;

		wp_set_current_user( $usuario );

		// Métodos de pago activos.
		$pasarelas = array();

		foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gw ) {
			if ( 'yes' === $gw->enabled ) {
				$pasarelas[ $id ] = $gw;
			}
		}

		// ¿De dónde sale el precio?
		$tarifa = uep_resolver_tarifa( $id_depa, $id_prov, $id_dist );

		if ( ! $dentro ) {
			$regla = __( 'Este distrito está fuera de tus zonas de cobertura: solo se ofrece el envío por agencia.', 'ubigeo-envio-peru' );
		} elseif ( ! $tarifa ) {
			$regla = sprintf(
				/* translators: %s: costo por defecto */
				__( 'Este distrito no tiene tarifa propia: se cobra el costo por defecto (%s). Puedes agregarla en la pestaña Tarifas.', 'ubigeo-envio-peru' ),
				self::precio_texto( uep_tarifa_a_base( $settings['default_cost'] ) )
			);
		} elseif ( (int) $tarifa['idDist'] ) {
			$regla = sprintf(
				/* translators: %s: distrito */
				__( 'El precio sale de la tarifa del distrito %s.', 'ubigeo-envio-peru' ),
				trim( $nombres['distrito'] )
			);
		} elseif ( (int) $tarifa['idProv'] ) {
			$regla = sprintf(
				/* translators: %s: provincia */
				__( 'El precio sale de la tarifa de toda la provincia %s.', 'ubigeo-envio-peru' ),
				trim( $nombres['provincia'] )
			);
		} else {
			$regla = sprintf(
				/* translators: %s: departamento */
				__( 'El precio sale de la tarifa de todo el departamento %s.', 'ubigeo-envio-peru' ),
				trim( $nombres['departamento'] )
			);
		}

		$moneda = get_option( 'woocommerce_currency' );

		ob_start();
		?>
		<p class="uep-sim-destino">
			<strong><?php echo esc_html( implode( ' › ', array_map( 'trim', array_filter( $nombres ) ) ) ); ?></strong>
			<span class="uep-etiqueta <?php echo $dentro ? 'uep-etiqueta-ok' : 'uep-etiqueta-agencia'; ?>">
				<?php echo $dentro ? esc_html__( 'Dentro de cobertura', 'ubigeo-envio-peru' ) : esc_html__( 'Fuera de cobertura', 'ubigeo-envio-peru' ); ?>
			</span>
		</p>
		<p class="description"><?php echo esc_html( $regla ); ?></p>

		<?php if ( $rates ) : ?>
			<table class="widefat striped uep-sim-tabla">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Opción de envío', 'ubigeo-envio-peru' ); ?></th>
						<th class="uep-col-costo"><?php echo esc_html( sprintf( /* translators: %s: moneda */ __( 'Costo (%s)', 'ubigeo-envio-peru' ), $moneda ) ); ?></th>
						<th><?php esc_html_e( 'Puede pagar con', 'ubigeo-envio-peru' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rates as $rate_id => $rate ) : ?>
						<?php
						$pagos = UEP_Shipping::pagos_permitidos( $pasarelas, $id_depa, $id_prov, array( $rate_id ), $gestor );
						$pagos = array_map(
							function ( $g ) {
								return $g->get_title();
							},
							array_values( $pagos )
						);
						?>
						<tr>
							<td>
								<?php echo esc_html( $rate->get_label() ); ?>
								<?php if ( false !== strpos( $rate_id, '_admin_' ) ) : ?>
									<span class="uep-etiqueta"><?php esc_html_e( 'Solo equipo', 'ubigeo-envio-peru' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="uep-col-costo"><?php echo wp_kses_post( wc_price( (float) $rate->get_cost(), array( 'currency' => $moneda ) ) ); ?></td>
							<td>
								<?php if ( $pagos ) : ?>
									<?php echo esc_html( implode( ', ', $pagos ) ); ?>
								<?php else : ?>
									<em><?php esc_html_e( 'Ningún método de pago disponible', 'ubigeo-envio-peru' ); ?></em>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><em><?php esc_html_e( 'No hay opciones de envío para este distrito.', 'ubigeo-envio-peru' ); ?></em></p>
		<?php endif; ?>

		<p class="uep-sim-nota">
			<?php echo $gestor ? esc_html__( 'Vista de un administrador o gestor de la tienda.', 'ubigeo-envio-peru' ) : esc_html__( 'Vista de un cliente.', 'ubigeo-envio-peru' ); ?>
			<?php esc_html_e( 'El simulador no incluye el envío gratis por monto ni los cupones, porque dependen del carrito.', 'ubigeo-envio-peru' ); ?>
		</p>
		<?php
		wp_send_json_success( array( 'html' => ob_get_clean() ) );
	}

	/* ---------------------------------------------------------------------
	 * Pestaña: Tarifas
	 * ------------------------------------------------------------------- */

	private static function render_tarifas() {
		$tarifas  = uep_get_tarifas();
		$settings = uep_get_settings();
		$moneda   = 'PEN' === $settings['tarifa_currency'] ? 'S/' : get_woocommerce_currency_symbol();
		?>
		<div class="uep-card">
			<h2><?php esc_html_e( 'Agregar o actualizar una tarifa', 'ubigeo-envio-peru' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Elige solo el departamento para cubrirlo entero, o afina por provincia y distrito. La regla más específica gana: distrito → provincia → departamento → costo por defecto.', 'ubigeo-envio-peru' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="uep-form-tarifa">
				<?php wp_nonce_field( 'uep_guardar_tarifa' ); ?>
				<input type="hidden" name="action" value="uep_guardar_tarifa">

				<div class="uep-grid">
					<label>
						<span><?php esc_html_e( 'Departamento', 'ubigeo-envio-peru' ); ?> *</span>
						<select name="idDepa" id="uep_depa" required>
							<option value=""><?php esc_html_e( 'Elige…', 'ubigeo-envio-peru' ); ?></option>
							<?php foreach ( uep_get_departamentos() as $dep ) : ?>
								<option value="<?php echo esc_attr( $dep['idDepa'] ); ?>"><?php echo esc_html( $dep['departamento'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>

					<label>
						<span><?php esc_html_e( 'Provincia (opcional)', 'ubigeo-envio-peru' ); ?></span>
						<select name="idProv" id="uep_prov">
							<option value="0"><?php esc_html_e( 'Todo el departamento', 'ubigeo-envio-peru' ); ?></option>
						</select>
					</label>

					<label>
						<span><?php esc_html_e( 'Distrito (opcional)', 'ubigeo-envio-peru' ); ?></span>
						<select name="idDist" id="uep_dist">
							<option value="0"><?php esc_html_e( 'Toda la provincia', 'ubigeo-envio-peru' ); ?></option>
						</select>
					</label>

					<label>
						<span><?php echo esc_html( sprintf( __( 'Costo normal (%s)', 'ubigeo-envio-peru' ), $moneda ) ); ?> *</span>
						<input type="number" name="costo" step="0.01" min="0" required placeholder="0.00">
					</label>

					<label>
						<span><?php echo esc_html( sprintf( __( 'Costo Flash (%s, opcional)', 'ubigeo-envio-peru' ), $moneda ) ); ?></span>
						<input type="number" name="costo_express" step="0.01" min="0" placeholder="<?php esc_attr_e( 'Usa el Flash por defecto', 'ubigeo-envio-peru' ); ?>">
					</label>

					<div class="uep-submit">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar tarifa', 'ubigeo-envio-peru' ); ?></button>
					</div>
				</div>
			</form>
		</div>

		<div class="uep-card">
			<h2><?php esc_html_e( 'Tarifas configuradas', 'ubigeo-envio-peru' ); ?></h2>

			<?php if ( ! $tarifas ) : ?>
				<p><?php esc_html_e( 'Aún no hay tarifas. Mientras tanto, todos los envíos usan el costo por defecto:', 'ubigeo-envio-peru' ); ?>
					<strong><?php echo esc_html( $moneda . ' ' . number_format( (float) $settings['default_cost'], 2 ) ); ?></strong>
				</p>
			<?php else : ?>
				<p class="uep-buscador">
					<label class="screen-reader-text" for="uep_buscar_tarifa"><?php esc_html_e( 'Buscar tarifa', 'ubigeo-envio-peru' ); ?></label>
					<input type="search" id="uep_buscar_tarifa" class="regular-text"
						   placeholder="<?php esc_attr_e( 'Buscar distrito, provincia o departamento…', 'ubigeo-envio-peru' ); ?>">
					<span id="uep_buscar_conteo" class="description"></span>
				</p>
				<table class="widefat striped uep-tabla">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Zona', 'ubigeo-envio-peru' ); ?></th>
							<th class="uep-col-costo"><?php esc_html_e( 'Normal', 'ubigeo-envio-peru' ); ?></th>
							<th class="uep-col-costo"><?php esc_html_e( 'Flash', 'ubigeo-envio-peru' ); ?></th>
							<th class="uep-col-acciones"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $tarifas as $tarifa ) : ?>
							<?php
							$tid       = (int) $tarifa['tarifa_id'];
							$form_id   = 'uep-form-tarifa-' . $tid;
							$hay_flash = isset( $tarifa['costo_express'] ) && null !== $tarifa['costo_express'] && '' !== (string) $tarifa['costo_express'];

							$url_eliminar = wp_nonce_url(
								add_query_arg(
									array(
										'action'    => 'uep_eliminar_tarifa',
										'tarifa_id' => $tid,
									),
									admin_url( 'admin-post.php' )
								),
								'uep_eliminar_tarifa_' . $tid
							);
							?>
							<tr>
								<td><?php echo esc_html( uep_tarifa_alcance( $tarifa ) ); ?></td>
								<td class="uep-col-costo">
									<span class="uep-ver"><?php echo esc_html( $moneda . ' ' . number_format( (float) $tarifa['costo'], 2 ) ); ?></span>
									<input class="uep-campo-edicion" type="number" step="0.01" min="0" required
										   name="costo" form="<?php echo esc_attr( $form_id ); ?>"
										   value="<?php echo esc_attr( number_format( (float) $tarifa['costo'], 2, '.', '' ) ); ?>">
								</td>
								<td class="uep-col-costo">
									<span class="uep-ver">
										<?php
										if ( $hay_flash ) {
											echo esc_html( $moneda . ' ' . number_format( (float) $tarifa['costo_express'], 2 ) );
										} else {
											echo '<span class="description">' . esc_html__( 'por defecto', 'ubigeo-envio-peru' ) . '</span>';
										}
										?>
									</span>
									<input class="uep-campo-edicion" type="number" step="0.01" min="0"
										   name="costo_express" form="<?php echo esc_attr( $form_id ); ?>"
										   value="<?php echo $hay_flash ? esc_attr( number_format( (float) $tarifa['costo_express'], 2, '.', '' ) ) : ''; ?>"
										   placeholder="<?php esc_attr_e( 'por defecto', 'ubigeo-envio-peru' ); ?>">
								</td>
								<td class="uep-col-acciones">
									<a href="#" class="uep-editar"><?php esc_html_e( 'Editar', 'ubigeo-envio-peru' ); ?></a>
									<button type="submit" class="button button-primary button-small uep-accion-edicion"
											form="<?php echo esc_attr( $form_id ); ?>">
										<?php esc_html_e( 'Guardar', 'ubigeo-envio-peru' ); ?>
									</button>
									<a href="#" class="uep-cancelar uep-accion-edicion"><?php esc_html_e( 'Cancelar', 'ubigeo-envio-peru' ); ?></a>
									<a href="<?php echo esc_url( $url_eliminar ); ?>"
									   class="uep-eliminar"
									   onclick="return confirm('<?php echo esc_js( __( '¿Eliminar esta tarifa?', 'ubigeo-envio-peru' ) ); ?>');">
										<?php esc_html_e( 'Eliminar', 'ubigeo-envio-peru' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php foreach ( $tarifas as $tarifa ) : ?>
					<form id="uep-form-tarifa-<?php echo (int) $tarifa['tarifa_id']; ?>" method="post"
						  action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="uep_editar_tarifa">
						<input type="hidden" name="tarifa_id" value="<?php echo (int) $tarifa['tarifa_id']; ?>">
						<?php wp_nonce_field( 'uep_editar_tarifa_' . (int) $tarifa['tarifa_id'] ); ?>
					</form>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<div class="uep-card">
			<h2><?php esc_html_e( 'Exportar / Importar tarifas (CSV)', 'ubigeo-envio-peru' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Columnas: departamento, provincia, distrito, costo_normal, costo_flash. Deja provincia/distrito vacíos para reglas de zona amplia, y costo_flash vacío para usar el Flash por defecto. Los nombres deben ser los del catálogo (usa la exportación como plantilla).', 'ubigeo-envio-peru' ); ?>
			</p>

			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'uep_exportar_csv', admin_url( 'admin-post.php' ) ), 'uep_exportar_csv' ) ); ?>">
					<?php esc_html_e( 'Exportar tarifas a CSV', 'ubigeo-envio-peru' ); ?>
				</a>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="margin-top:8px;">
				<?php wp_nonce_field( 'uep_importar_csv' ); ?>
				<input type="hidden" name="action" value="uep_importar_csv">
				<input type="file" name="uep_csv" accept=".csv,text/csv" required>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Importar CSV', 'ubigeo-envio-peru' ); ?></button>
			</form>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Pestaña: Ajustes
	 * ------------------------------------------------------------------- */

	private static function render_ajustes() {
		$settings = uep_get_settings();
		$moneda   = 'PEN' === $settings['tarifa_currency'] ? 'S/' : get_woocommerce_currency_symbol();
		?>
		<div class="uep-card">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'uep_guardar_ajustes' ); ?>
				<input type="hidden" name="action" value="uep_guardar_ajustes">

				<nav class="uep-indice" aria-label="<?php esc_attr_e( 'Secciones de ajustes', 'ubigeo-envio-peru' ); ?>">
					<a href="#uep-sec-general"><?php esc_html_e( 'General', 'ubigeo-envio-peru' ); ?></a>
					<a href="#uep-sec-domicilio"><?php esc_html_e( 'Envío a domicilio y zonas de cobertura', 'ubigeo-envio-peru' ); ?></a>
					<a href="#uep-sec-flash"><?php esc_html_e( 'Envío Flash', 'ubigeo-envio-peru' ); ?></a>
					<a href="#uep-sec-recojo"><?php esc_html_e( 'Recojo en Tienda', 'ubigeo-envio-peru' ); ?></a>
					<a href="#uep-sec-agencia"><?php esc_html_e( 'Envío por Agencia', 'ubigeo-envio-peru' ); ?></a>
					<a href="#uep-sec-pagos"><?php esc_html_e( 'Métodos de pago', 'ubigeo-envio-peru' ); ?></a>
					<a href="#uep-sec-interno"><?php esc_html_e( 'Uso interno del equipo', 'ubigeo-envio-peru' ); ?></a>
					<a href="#uep-sec-erp"><?php esc_html_e( 'Integración con ERP', 'ubigeo-envio-peru' ); ?></a>
					<a href="#uep-sec-respaldo"><?php esc_html_e( 'Copia de seguridad', 'ubigeo-envio-peru' ); ?></a>
				</nav>

				<section class="uep-seccion" id="uep-sec-general">
					<h2 class="uep-seccion-titulo"><?php esc_html_e( 'General', 'ubigeo-envio-peru' ); ?></h2>
					<p class="uep-seccion-intro"><?php esc_html_e( 'Opciones básicas: el ubigeo en el checkout y cómo se interpretan tus precios.', 'ubigeo-envio-peru' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Ubigeo en el checkout', 'ubigeo-envio-peru' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="checkout_enabled" value="yes" <?php checked( $settings['checkout_enabled'], 'yes' ); ?>>
								<?php esc_html_e( 'Mostrar Departamento / Provincia / Distrito en el checkout (solo Perú)', 'ubigeo-envio-peru' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="uep_tarifa_currency"><?php esc_html_e( 'Moneda de las tarifas', 'ubigeo-envio-peru' ); ?></label></th>
						<td>
							<select id="uep_tarifa_currency" name="tarifa_currency">
								<option value="base" <?php selected( $settings['tarifa_currency'], 'base' ); ?>><?php echo esc_html( sprintf( __( 'Moneda base de WooCommerce (%s)', 'ubigeo-envio-peru' ), get_option( 'woocommerce_currency' ) ) ); ?></option>
								<option value="PEN" <?php selected( $settings['tarifa_currency'], 'PEN' ); ?>><?php esc_html_e( 'Soles (S/)', 'ubigeo-envio-peru' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'En qué moneda están escritas tus tarifas. Con "moneda base" no se convierte nada (tu multimoneda las muestra en soles como a cualquier precio). Elige "Soles" solo si escribes las tarifas en S/ y tu base es otra moneda: el plugin las convertirá con el tipo de cambio de CURCY/WOOCS.', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="uep_tarifa_tc"><?php esc_html_e( 'Tipo de cambio manual (respaldo)', 'ubigeo-envio-peru' ); ?></label></th>
						<td>
							<input type="number" step="0.0001" min="0.0001" id="uep_tarifa_tc" name="tarifa_tc"
								   value="<?php echo esc_attr( $settings['tarifa_tc'] ); ?>">
							<p class="description"><?php esc_html_e( 'Soles por 1 unidad de la moneda base (ej. 3.75). Solo se usa si no hay un plugin de multimoneda con el tipo de cambio del sol.', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="uep_default_cost"><?php echo esc_html( sprintf( __( 'Costo por defecto (%s)', 'ubigeo-envio-peru' ), $moneda ) ); ?></label></th>
						<td>
							<input type="number" step="0.01" min="0" id="uep_default_cost" name="default_cost"
								   value="<?php echo esc_attr( $settings['default_cost'] ); ?>">
							<p class="description"><?php esc_html_e( 'Se cobra cuando la zona elegida no tiene una tarifa configurada.', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="uep_free_over"><?php echo esc_html( sprintf( __( 'Envío gratis desde (%s)', 'ubigeo-envio-peru' ), $moneda ) ); ?></label></th>
						<td>
							<input type="number" step="0.01" min="0" id="uep_free_over" name="free_over"
								   value="<?php echo esc_attr( $settings['free_over'] ); ?>">
							<p class="description"><?php esc_html_e( 'Si el carrito llega a este monto, el envío normal es gratis (el Flash mantiene su precio). Deja 0 para desactivarlo.', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
				</table>
				</section>

				<section class="uep-seccion" id="uep-sec-domicilio">
					<h2 class="uep-seccion-titulo"><?php esc_html_e( 'Envío a domicilio y zonas de cobertura', 'ubigeo-envio-peru' ); ?></h2>
					<p class="uep-seccion-intro"><?php esc_html_e( 'Tu reparto propio, con el precio de cada distrito (pestaña Tarifas). Solo se ofrece dentro de las zonas de cobertura; el resto del país va por agencia.', 'ubigeo-envio-peru' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="uep_method_title"><?php esc_html_e( 'Nombre del envío', 'ubigeo-envio-peru' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="uep_method_title" name="method_title"
								   value="<?php echo esc_attr( $settings['method_title'] ); ?>">
							<p class="description"><?php esc_html_e( 'Así verá el cliente el método de envío. Ej.: "Envío a domicilio".', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cobertura del envío a domicilio', 'ubigeo-envio-peru' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="cobertura_enabled" value="yes" <?php checked( $settings['cobertura_enabled'], 'yes' ); ?>>
								<?php esc_html_e( 'Limitar el envío a domicilio (normal y Flash) a una zona. Fuera de ella se ofrece el envío por agencia.', 'ubigeo-envio-peru' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Zonas de cobertura', 'ubigeo-envio-peru' ); ?></th>
						<td>
							<?php self::render_zonas( 'cobertura_zonas', $settings['cobertura_zonas'], 'uep_cov' ); ?>
							<p class="description"><?php esc_html_e( 'Ej.: LIMA › LIMA y CALLAO › CALLAO = Lima Metropolitana y Callao tienen envío a domicilio; el resto va por agencia.', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
				</table>
				</section>

				<section class="uep-seccion" id="uep-sec-flash">
					<h2 class="uep-seccion-titulo"><?php esc_html_e( 'Envío Flash', 'ubigeo-envio-peru' ); ?></h2>
					<p class="uep-seccion-intro"><?php esc_html_e( 'Una segunda opción más rápida y más cara, en las mismas zonas que el envío a domicilio.', 'ubigeo-envio-peru' ); ?></p>
				<table class="form-table" role="presentation" data-uep-toggle="express_enabled">
					<tr>
						<th scope="row"><?php esc_html_e( 'Envío rápido (Flash)', 'ubigeo-envio-peru' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="express_enabled" value="yes" <?php checked( $settings['express_enabled'], 'yes' ); ?>>
								<?php esc_html_e( 'Ofrecer una segunda opción de envío más rápida con precio mayor', 'ubigeo-envio-peru' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="uep_express_title"><?php esc_html_e( 'Nombre del envío Flash', 'ubigeo-envio-peru' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="uep_express_title" name="express_title"
								   value="<?php echo esc_attr( $settings['express_title'] ); ?>">
							<p class="description"><?php esc_html_e( 'Ej.: "Envío Flash (mismo día / 24 horas)". Incluye el tiempo de entrega en el nombre.', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="uep_express_default_cost"><?php echo esc_html( sprintf( __( 'Costo Flash por defecto (%s)', 'ubigeo-envio-peru' ), $moneda ) ); ?></label></th>
						<td>
							<input type="number" step="0.01" min="0" id="uep_express_default_cost" name="express_default_cost"
								   value="<?php echo esc_attr( $settings['express_default_cost'] ); ?>">
							<p class="description"><?php esc_html_e( 'Se usa en las zonas que no tengan un costo Flash propio. Déjalo vacío para ofrecer el Flash solo en las zonas donde definas su costo.', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="uep_express_cutoff"><?php esc_html_e( 'Ofrecer Flash solo hasta las', 'ubigeo-envio-peru' ); ?></label></th>
						<td>
							<input type="time" id="uep_express_cutoff" name="express_cutoff" value="<?php echo esc_attr( $settings['express_cutoff'] ); ?>">
							<p class="description"><?php esc_html_e( 'Hora límite del envío el mismo día. Pasada esta hora el Flash sigue visible, pero con el aviso: "se envía el mismo día solo en compras antes de las [hora]". Déjalo vacío para no mostrar ningún aviso.', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
				</table>
				</section>

				<section class="uep-seccion" id="uep-sec-recojo">
					<h2 class="uep-seccion-titulo"><?php esc_html_e( 'Recojo en Tienda', 'ubigeo-envio-peru' ); ?></h2>
					<p class="uep-seccion-intro"><?php esc_html_e( 'El cliente recoge gratis en tu tienda y elige fecha y hora. Los administradores y gestores pueden elegir cualquier fecha.', 'ubigeo-envio-peru' ); ?></p>
				<table class="form-table" role="presentation" data-uep-toggle="pickup_enabled">
					<tr>
						<th scope="row"><?php esc_html_e( 'Recojo en Tienda', 'ubigeo-envio-peru' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="pickup_enabled" value="yes" <?php checked( $settings['pickup_enabled'], 'yes' ); ?>>
								<?php esc_html_e( 'Ofrecer recojo en tienda gratis, con calendario de fecha y hora en el checkout', 'ubigeo-envio-peru' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="uep_pickup_title"><?php esc_html_e( 'Nombre del recojo', 'ubigeo-envio-peru' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="uep_pickup_title" name="pickup_title"
								   value="<?php echo esc_attr( $settings['pickup_title'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="uep_pickup_note"><?php esc_html_e( 'Nota para el cliente (opcional)', 'ubigeo-envio-peru' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="uep_pickup_note" name="pickup_note"
								   value="<?php echo esc_attr( $settings['pickup_note'] ); ?>"
								   placeholder="<?php esc_attr_e( 'Ej.: Av. Ejemplo 123, Miraflores. Trae tu DNI y el número de pedido.', 'ubigeo-envio-peru' ); ?>">
							<p class="description"><?php esc_html_e( 'Se muestra junto al calendario, en el pedido y en los emails (ideal para la dirección de la tienda).', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Zonas del recojo en tienda', 'ubigeo-envio-peru' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:8px;">
								<input type="checkbox" name="pickup_zone_enabled" value="yes" <?php checked( $settings['pickup_zone_enabled'], 'yes' ); ?>>
								<?php esc_html_e( 'Ofrecer el recojo solo a clientes de estas zonas', 'ubigeo-envio-peru' ); ?>
							</label>
							<?php self::render_zonas( 'pickup_zonas', $settings['pickup_zonas'], 'uep_pk' ); ?>
							<p class="description"><?php esc_html_e( 'Ej.: LIMA › LIMA y CALLAO › CALLAO = solo Lima Metropolitana y Callao ven la opción de recojo.', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="uep_pickup_min_days"><?php esc_html_e( 'Disponible desde (días después de la compra)', 'ubigeo-envio-peru' ); ?></label></th>
						<td>
							<input type="number" min="0" max="30" step="1" id="uep_pickup_min_days" name="pickup_min_days"
								   value="<?php echo esc_attr( $settings['pickup_min_days'] ); ?>">
							<p class="description"><?php esc_html_e( 'El calendario solo permitirá fechas a partir de este número de días. Ej.: 2 = el cliente puede recoger desde 2 días después de comprar.', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="uep_pickup_start"><?php esc_html_e( 'Horario de atención', 'ubigeo-envio-peru' ); ?></label></th>
						<td>
							<input type="time" id="uep_pickup_start" name="pickup_start" value="<?php echo esc_attr( $settings['pickup_start'] ); ?>">
							&nbsp;—&nbsp;
							<input type="time" id="uep_pickup_end" name="pickup_end" value="<?php echo esc_attr( $settings['pickup_end'] ); ?>">
							<p class="description"><?php esc_html_e( 'El cliente elige la hora en intervalos de 30 minutos dentro de este horario.', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Días sin atención (recojo)', 'ubigeo-envio-peru' ); ?></th>
						<td>
							<?php
							$dias_semana  = array(
								1 => __( 'Lunes', 'ubigeo-envio-peru' ),
								2 => __( 'Martes', 'ubigeo-envio-peru' ),
								3 => __( 'Miércoles', 'ubigeo-envio-peru' ),
								4 => __( 'Jueves', 'ubigeo-envio-peru' ),
								5 => __( 'Viernes', 'ubigeo-envio-peru' ),
								6 => __( 'Sábado', 'ubigeo-envio-peru' ),
								0 => __( 'Domingo', 'ubigeo-envio-peru' ),
							);
							$dias_cerrados = array_map( 'absint', (array) $settings['pickup_closed_days'] );
							foreach ( $dias_semana as $num => $nombre ) :
								?>
								<label style="margin-right:12px;display:inline-block;">
									<input type="checkbox" name="pickup_closed_days[]" value="<?php echo esc_attr( $num ); ?>"
										<?php checked( in_array( $num, $dias_cerrados, true ) ); ?>>
									<?php echo esc_html( $nombre ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'El calendario de recojo no aceptará estos días. Ej.: marca Domingo si la tienda no abre los domingos.', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="uep_pickup_holidays"><?php esc_html_e( 'Feriados (recojo)', 'ubigeo-envio-peru' ); ?></label></th>
						<td>
							<textarea id="uep_pickup_holidays" name="pickup_holidays" rows="3" class="large-text"
									  placeholder="2026-07-28, 2026-07-29, 2026-12-25"><?php echo esc_textarea( $settings['pickup_holidays'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Fechas exactas sin atención, en formato AAAA-MM-DD, separadas por comas o líneas.', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
				</table>
				</section>

				<section class="uep-seccion" id="uep-sec-agencia">
					<h2 class="uep-seccion-titulo"><?php esc_html_e( 'Envío por Agencia', 'ubigeo-envio-peru' ); ?></h2>
					<p class="uep-seccion-intro"><?php esc_html_e( 'Para clientes fuera de tus zonas de cobertura: dejas el pedido en la agencia y el cliente paga el envío al recoger.', 'ubigeo-envio-peru' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="uep_agencia_title"><?php esc_html_e( 'Nombre del envío por agencia', 'ubigeo-envio-peru' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="uep_agencia_title" name="agencia_title"
								   value="<?php echo esc_attr( $settings['agencia_title'] ); ?>">
							<p class="description"><?php esc_html_e( 'Así verá la opción el cliente de provincia. El costo en el checkout es S/ 0: el envío lo cobra la agencia al recoger.', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="uep_agencia_list"><?php esc_html_e( 'Agencias disponibles', 'ubigeo-envio-peru' ); ?></label></th>
						<td>
							<textarea id="uep_agencia_list" name="agencia_list" rows="3" class="regular-text"><?php echo esc_textarea( $settings['agencia_list'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Una agencia por línea (ej. Shalom, Olva Courier). El cliente elegirá una y escribirá la sede donde recogerá su pedido.', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="uep_agencia_note"><?php esc_html_e( 'Nota del envío por agencia (opcional)', 'ubigeo-envio-peru' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="uep_agencia_note" name="agencia_note"
								   value="<?php echo esc_attr( $settings['agencia_note'] ); ?>"
								   placeholder="<?php esc_attr_e( 'Ej.: Enviamos por Shalom u Olva Courier. Te avisaremos cuando el paquete esté en la agencia.', 'ubigeo-envio-peru' ); ?>">
							<p class="description"><?php esc_html_e( 'Se muestra en el checkout, el pedido y los emails cuando el cliente elige envío por agencia.', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
				</table>
				</section>

				<section class="uep-seccion" id="uep-sec-pagos">
					<h2 class="uep-seccion-titulo"><?php esc_html_e( 'Métodos de pago', 'ubigeo-envio-peru' ); ?></h2>
					<p class="uep-seccion-intro"><?php esc_html_e( 'Quién puede pagar con qué. Las reglas se aplican en este orden: 1) solo administradores, 2) recojo en tienda, 3) solo en zonas de cobertura.', 'ubigeo-envio-peru' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Pagos solo para administradores', 'ubigeo-envio-peru' ); ?></th>
						<td>
							<?php
							$solo_admin  = (array) $settings['pago_solo_admin'];
							$gws_admin   = class_exists( 'WooCommerce' ) && WC()->payment_gateways ? WC()->payment_gateways->payment_gateways() : array();

							if ( $gws_admin ) :
								foreach ( $gws_admin as $gateway ) :
									// Se listan también las pasarelas desactivadas para que la
									// selección no se pierda si se apagan temporalmente.
									$inactiva = 'yes' !== $gateway->enabled;
									?>
									<label style="display:block;margin-bottom:4px;">
										<input type="checkbox" name="pago_solo_admin[]" value="<?php echo esc_attr( $gateway->id ); ?>"
											<?php checked( in_array( $gateway->id, $solo_admin, true ) ); ?>>
										<?php echo esc_html( $gateway->get_title() ); ?>
										<?php if ( $inactiva ) : ?>
											<span class="description"><?php esc_html_e( '(desactivado en WooCommerce)', 'ubigeo-envio-peru' ); ?></span>
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Los métodos marcados desaparecen del checkout para los clientes: solo los ven los administradores y gestores de la tienda (útil para el link de pago u otros métodos internos). En el panel de administración siguen disponibles al crear pedidos a mano.', 'ubigeo-envio-peru' ); ?></p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'No hay métodos de pago activos.', 'ubigeo-envio-peru' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Pagos permitidos para el recojo', 'ubigeo-envio-peru' ); ?></th>
						<td>
							<?php
							$pago_recojo = (array) $settings['pago_recojo'];
							$gws_recojo  = class_exists( 'WooCommerce' ) && WC()->payment_gateways ? WC()->payment_gateways->payment_gateways() : array();

							if ( $gws_recojo ) :
								foreach ( $gws_recojo as $gateway ) :
									// Se listan también las pasarelas desactivadas para que la
									// selección no se pierda si se apagan temporalmente.
									$inactiva = 'yes' !== $gateway->enabled;
									?>
									<label style="display:block;margin-bottom:4px;">
										<input type="checkbox" name="pago_recojo[]" value="<?php echo esc_attr( $gateway->id ); ?>"
											<?php checked( in_array( $gateway->id, $pago_recojo, true ) ); ?>>
										<?php echo esc_html( $gateway->get_title() ); ?>
										<?php if ( $inactiva ) : ?>
											<span class="description"><?php esc_html_e( '(desactivado en WooCommerce)', 'ubigeo-envio-peru' ); ?></span>
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Si marcas alguno, los clientes que elijan Recojo en Tienda solo podrán pagar con esos métodos. Sin marcar ninguno, se aceptan todos.', 'ubigeo-envio-peru' ); ?></p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'No hay métodos de pago activos.', 'ubigeo-envio-peru' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Pagos solo en cobertura', 'ubigeo-envio-peru' ); ?></th>
						<td>
							<?php
							$restringidos = (array) $settings['pago_solo_cobertura'];
							$gateways     = class_exists( 'WooCommerce' ) && WC()->payment_gateways ? WC()->payment_gateways->payment_gateways() : array();

							if ( $gateways ) :
								foreach ( $gateways as $gateway ) :
									// Se listan también las pasarelas desactivadas para que la
									// selección no se pierda si se apagan temporalmente.
									$inactiva = 'yes' !== $gateway->enabled;
									?>
									<label style="display:block;margin-bottom:4px;">
										<input type="checkbox" name="pago_solo_cobertura[]" value="<?php echo esc_attr( $gateway->id ); ?>"
											<?php checked( in_array( $gateway->id, $restringidos, true ) ); ?>>
										<?php echo esc_html( $gateway->get_title() ); ?>
										<?php if ( $inactiva ) : ?>
											<span class="description"><?php esc_html_e( '(desactivado en WooCommerce)', 'ubigeo-envio-peru' ); ?></span>
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Los métodos marcados solo se mostrarán a clientes dentro de las zonas de cobertura (ej. marca "Contra entrega" para que solo Lima Metropolitana y Callao lo vean).', 'ubigeo-envio-peru' ); ?></p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'No hay métodos de pago activos.', 'ubigeo-envio-peru' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				</section>

				<section class="uep-seccion" id="uep-sec-interno">
					<h2 class="uep-seccion-titulo"><?php esc_html_e( 'Uso interno del equipo', 'ubigeo-envio-peru' ); ?></h2>
					<p class="uep-seccion-intro"><?php esc_html_e( 'Opciones que solo ven los administradores y gestores de la tienda al registrar pedidos (ej. ventas de Mercado Libre).', 'ubigeo-envio-peru' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="uep_admin_metodos"><?php esc_html_e( 'Métodos solo para administradores', 'ubigeo-envio-peru' ); ?></label></th>
						<td>
							<textarea id="uep_admin_metodos" name="admin_metodos" rows="3" class="large-text"><?php echo esc_textarea( $settings['admin_metodos'] ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Un método por línea, con el formato "Nombre | costo" (el costo es opcional; sin él vale 0). Estos métodos solo los ven los administradores y gestores de la tienda al registrar pedidos a mano (ej. ventas de Mercado Libre), en cualquier zona del país. Deja el campo vacío para no ofrecer ninguno.', 'ubigeo-envio-peru' ); ?>
							</p>
						</td>
					</tr>
				</table>
				</section>

				<section class="uep-seccion" id="uep-sec-erp">
					<h2 class="uep-seccion-titulo"><?php esc_html_e( 'Integración con ERP', 'ubigeo-envio-peru' ); ?></h2>
					<p class="uep-seccion-intro"><?php esc_html_e( 'Datos extra que se guardan en cada pedido para tu ERP (ANKA / Odoo) u otras integraciones.', 'ubigeo-envio-peru' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Compatibilidad con ERP', 'ubigeo-envio-peru' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="erp_state" value="yes" <?php checked( $settings['erp_state'], 'yes' ); ?>>
								<?php esc_html_e( 'Guardar el código del departamento (LIM, CAL, CUS…) en el campo estándar "Estado/Región" del pedido', 'ubigeo-envio-peru' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Es el código ISO que usan WooCommerce y los ERP (Odoo y similares) para identificar el departamento, así el contacto se crea completo en tu sistema. No cambia lo que ve el cliente: en la web y en los correos sigue mostrándose el nombre ("LIMA"). Cada pedido guarda además el servicio de envío usado en los campos _uep_servicio y _uep_canal.', 'ubigeo-envio-peru' ); ?></p>
						</td>
					</tr>
				</table>
				</section>

				<div class="uep-guardar-barra">
					<?php submit_button( __( 'Guardar ajustes', 'ubigeo-envio-peru' ), 'primary', 'submit', false ); ?>
					<span class="description"><?php esc_html_e( 'Los cambios se aplican al instante en el checkout.', 'ubigeo-envio-peru' ); ?></span>
				</div>
			</form>
		</div>

		<div class="uep-card" id="uep-sec-respaldo">
			<h2><?php esc_html_e( 'Copia de seguridad / migración', 'ubigeo-envio-peru' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Exporta toda la configuración (ajustes + tarifas) a un archivo, e impórtala en otro sitio o después de una reinstalación. Al importar, los ajustes se reemplazan y, si el archivo incluye tarifas, también reemplazan a las actuales.', 'ubigeo-envio-peru' ); ?>
			</p>

			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'uep_exportar_config', admin_url( 'admin-post.php' ) ), 'uep_exportar_config' ) ); ?>">
					<?php esc_html_e( 'Exportar configuración (JSON)', 'ubigeo-envio-peru' ); ?>
				</a>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="margin-top:8px;">
				<?php wp_nonce_field( 'uep_importar_config' ); ?>
				<input type="hidden" name="action" value="uep_importar_config">
				<input type="file" name="uep_config" accept=".json,application/json" required>
				<button type="submit" class="button"
						onclick="return confirm('<?php echo esc_js( __( 'Se reemplazarán los ajustes actuales (y las tarifas, si el archivo las incluye). ¿Continuar?', 'ubigeo-envio-peru' ) ); ?>');">
					<?php esc_html_e( 'Importar configuración', 'ubigeo-envio-peru' ); ?>
				</button>
			</form>
		</div>
		<?php
	}

	/**
	 * Administrador de lista de zonas (departamento + provincia opcional).
	 *
	 * @param string $name    Nombre del campo (array) que se envía al guardar.
	 * @param array  $zonas   Zonas actuales en formato "idDepa|idProv".
	 * @param string $prefijo Prefijo de IDs para los selects (JS encadenado).
	 */
	private static function render_zonas( $name, $zonas, $prefijo ) {
		$lista_id = $prefijo . '_lista';
		?>
		<ul id="<?php echo esc_attr( $lista_id ); ?>" class="uep-zonas-lista">
			<?php foreach ( (array) $zonas as $zona ) : ?>
				<?php if ( preg_match( '/^\d+\|\d+$/', (string) $zona ) ) : ?>
					<li>
						<input type="hidden" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $zona ); ?>">
						<span><?php echo esc_html( uep_zona_etiqueta( $zona ) ); ?></span>
						<a href="#" class="uep-zona-quitar"><?php esc_html_e( 'Quitar', 'ubigeo-envio-peru' ); ?></a>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>

		<select id="<?php echo esc_attr( $prefijo ); ?>_depa">
			<option value=""><?php esc_html_e( 'Elige…', 'ubigeo-envio-peru' ); ?></option>
			<?php foreach ( uep_get_departamentos() as $dep ) : ?>
				<option value="<?php echo esc_attr( $dep['idDepa'] ); ?>"><?php echo esc_html( $dep['departamento'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<select id="<?php echo esc_attr( $prefijo ); ?>_prov">
			<option value="0"><?php esc_html_e( 'Todo el departamento', 'ubigeo-envio-peru' ); ?></option>
		</select>
		<button type="button" class="button uep-zona-agregar"
				data-lista="<?php echo esc_attr( $lista_id ); ?>"
				data-name="<?php echo esc_attr( $name ); ?>"
				data-depa="<?php echo esc_attr( $prefijo ); ?>_depa"
				data-prov="<?php echo esc_attr( $prefijo ); ?>_prov">
			<?php esc_html_e( 'Agregar zona', 'ubigeo-envio-peru' ); ?>
		</button>
		<?php
	}

	/**
	 * Sanitiza una lista de zonas "idDepa|idProv" enviada por el formulario.
	 *
	 * @param mixed $crudo Valor recibido.
	 * @return string[]
	 */
	private static function sanitizar_zonas( $crudo ) {
		$zonas = array();

		foreach ( (array) $crudo as $zona ) {
			$zona = (string) wp_unslash( $zona );

			if ( preg_match( '/^(\d+)\|(\d+)$/', $zona, $m ) ) {
				$zonas[] = absint( $m[1] ) . '|' . absint( $m[2] );
			}
		}

		return array_values( array_unique( $zonas ) );
	}

	/* ---------------------------------------------------------------------
	 * Pestaña: Ayuda
	 * ------------------------------------------------------------------- */

	/**
	 * URL de la lista de pedidos (HPOS o clásica).
	 */
	private static function url_pedidos() {
		$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		return $hpos ? admin_url( 'admin.php?page=wc-orders' ) : admin_url( 'edit.php?post_type=shop_order' );
	}

	private static function render_ayuda() {
		$ajustes = self::url( 'ajustes' );
		$tareas  = array(
			array(
				__( '¿Cómo cambio el precio de envío de un distrito?', 'ubigeo-envio-peru' ),
				array(
					__( 'Ve a la pestaña "Tarifas de envío".', 'ubigeo-envio-peru' ),
					__( 'Escribe el distrito en el buscador de la tabla "Tarifas configuradas".', 'ubigeo-envio-peru' ),
					__( 'Haz clic en "Editar", cambia el costo normal o Flash y pulsa "Guardar".', 'ubigeo-envio-peru' ),
					__( 'Si el distrito no aparece, agrégalo con el formulario "Agregar o actualizar una tarifa".', 'ubigeo-envio-peru' ),
				),
				self::url( 'tarifas' ),
			),
			array(
				__( '¿Cómo empiezo a repartir en un distrito o provincia nueva?', 'ubigeo-envio-peru' ),
				array(
					__( 'Ve a Ajustes → "Envío a domicilio y zonas de cobertura".', 'ubigeo-envio-peru' ),
					__( 'En "Zonas de cobertura" elige el departamento y la provincia, pulsa "Agregar zona" y guarda.', 'ubigeo-envio-peru' ),
					__( 'En "Tarifas de envío" agrega el precio de sus distritos (o uno para toda la provincia).', 'ubigeo-envio-peru' ),
					__( 'Comprueba el resultado con el Simulador de la pestaña Inicio.', 'ubigeo-envio-peru' ),
				),
				$ajustes . '#uep-sec-domicilio',
			),
			array(
				__( '¿Cómo registro un pedido de Mercado Libre?', 'ubigeo-envio-peru' ),
				array(
					__( 'Inicia sesión en la tienda con tu usuario de administrador o gestor.', 'ubigeo-envio-peru' ),
					__( 'Agrega los productos al carrito y ve a "Finalizar compra" con los datos del comprador.', 'ubigeo-envio-peru' ),
					__( 'Elige "Flex Mercado Libre" o "Urbano (Mercado Libre)" como envío: solo tu equipo ve esas opciones.', 'ubigeo-envio-peru' ),
					__( 'Finaliza el pedido: queda marcado como canal "interno" para tu ERP.', 'ubigeo-envio-peru' ),
				),
				$ajustes . '#uep-sec-interno',
			),
			array(
				__( 'Un cliente de provincia compró: ¿cómo lo despacho por agencia?', 'ubigeo-envio-peru' ),
				array(
					__( 'Abre el pedido en WooCommerce → Pedidos.', 'ubigeo-envio-peru' ),
					__( 'En el panel "Envío por Agencia" completa la agencia y la sede si faltan.', 'ubigeo-envio-peru' ),
					__( 'Deja el paquete en la agencia y escribe el número de guía en "N° de guía / tracking".', 'ubigeo-envio-peru' ),
					__( 'Pulsa "Actualizar": el cliente recibe un email con la agencia, la sede y la guía. También puedes usar el botón "Avisar por WhatsApp".', 'ubigeo-envio-peru' ),
				),
				self::url_pedidos(),
			),
			array(
				__( '¿Cómo aviso que un pedido de recojo ya está listo?', 'ubigeo-envio-peru' ),
				array(
					__( 'Abre el pedido en WooCommerce → Pedidos.', 'ubigeo-envio-peru' ),
					__( 'En el panel "Recojo en Tienda" marca "Avisar por email que el pedido está listo para recoger".', 'ubigeo-envio-peru' ),
					__( 'Pulsa "Actualizar". Si prefieres, usa el botón "Avisar por WhatsApp", que ya trae el mensaje armado.', 'ubigeo-envio-peru' ),
				),
				self::url_pedidos(),
			),
			array(
				__( '¿Cómo cambio la hora límite del Envío Flash?', 'ubigeo-envio-peru' ),
				array(
					__( 'Ve a Ajustes → "Envío Flash".', 'ubigeo-envio-peru' ),
					__( 'Cambia "Ofrecer Flash solo hasta las" y guarda.', 'ubigeo-envio-peru' ),
					__( 'Pasada esa hora el Flash se sigue vendiendo, con el aviso de que el mismo día aplica a compras anteriores.', 'ubigeo-envio-peru' ),
				),
				$ajustes . '#uep-sec-flash',
			),
			array(
				__( '¿Cómo elijo qué métodos de pago ve cada cliente?', 'ubigeo-envio-peru' ),
				array(
					__( 'Ve a Ajustes → "Métodos de pago".', 'ubigeo-envio-peru' ),
					__( '"Pagos solo para administradores": se ocultan a los clientes (ej. link de pago).', 'ubigeo-envio-peru' ),
					__( '"Pagos permitidos para el recojo": los únicos aceptados cuando eligen Recojo en Tienda.', 'ubigeo-envio-peru' ),
					__( '"Pagos solo en cobertura": se ocultan fuera de tus zonas (ej. contra entrega solo en Lima Metropolitana y Callao).', 'ubigeo-envio-peru' ),
				),
				$ajustes . '#uep-sec-pagos',
			),
			array(
				__( 'Un cliente dice que no ve una opción de envío o de pago. ¿Qué reviso?', 'ubigeo-envio-peru' ),
				array(
					__( 'Abre el Simulador en la pestaña Inicio y elige su distrito.', 'ubigeo-envio-peru' ),
					__( 'Verás si está dentro o fuera de cobertura, de dónde sale el precio y qué métodos de pago le corresponden.', 'ubigeo-envio-peru' ),
					__( 'Si el problema es un precio, corrígelo en "Tarifas de envío"; si es una opción, en Ajustes.', 'ubigeo-envio-peru' ),
				),
				self::url( 'inicio' ) . '#uep-simulador',
			),
			array(
				__( '¿Cómo hago una copia de seguridad o paso la configuración a otro sitio?', 'ubigeo-envio-peru' ),
				array(
					__( 'Ve a Ajustes → "Copia de seguridad / migración" y pulsa "Exportar configuración".', 'ubigeo-envio-peru' ),
					__( 'Guarda el archivo: incluye todos los ajustes y las tarifas.', 'ubigeo-envio-peru' ),
					__( 'En el otro sitio, en el mismo lugar, usa "Importar configuración" con ese archivo.', 'ubigeo-envio-peru' ),
				),
				$ajustes . '#uep-sec-respaldo',
			),
		);
		?>
		<div class="uep-card">
			<h2><?php esc_html_e( 'Guía rápida', 'ubigeo-envio-peru' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Las tareas del día a día, paso a paso. Haz clic en cada pregunta para ver cómo hacerla.', 'ubigeo-envio-peru' ); ?></p>

			<div class="uep-guia">
				<?php foreach ( $tareas as $tarea ) : ?>
					<details class="uep-guia-item">
						<summary><?php echo esc_html( $tarea[0] ); ?></summary>
						<ol>
							<?php foreach ( $tarea[1] as $paso ) : ?>
								<li><?php echo esc_html( $paso ); ?></li>
							<?php endforeach; ?>
						</ol>
						<a class="button button-small" href="<?php echo esc_url( $tarea[2] ); ?>"><?php esc_html_e( 'Ir ahora', 'ubigeo-envio-peru' ); ?></a>
					</details>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="uep-card">
			<h2><?php esc_html_e( 'Conceptos clave', 'ubigeo-envio-peru' ); ?></h2>
			<dl class="uep-glosario">
				<dt><?php esc_html_e( 'Zona de cobertura', 'ubigeo-envio-peru' ); ?></dt>
				<dd><?php esc_html_e( 'Las provincias donde haces reparto propio (hoy Lima › Lima y Callao › Callao). Ahí se ofrecen el envío a domicilio, el Flash y el recojo; fuera de ellas, solo el envío por agencia.', 'ubigeo-envio-peru' ); ?></dd>

				<dt><?php esc_html_e( 'Tarifa', 'ubigeo-envio-peru' ); ?></dt>
				<dd><?php esc_html_e( 'El precio de envío de un distrito, de una provincia completa o de un departamento completo. Siempre gana la más específica: distrito → provincia → departamento → costo por defecto.', 'ubigeo-envio-peru' ); ?></dd>

				<dt><?php esc_html_e( 'Costo por defecto', 'ubigeo-envio-peru' ); ?></dt>
				<dd><?php esc_html_e( 'Lo que se cobra en un distrito de tus zonas de cobertura que no tiene tarifa. La revisión de la pestaña Inicio te avisa si hay distritos en esa situación.', 'ubigeo-envio-peru' ); ?></dd>

				<dt><?php esc_html_e( 'Administrador / gestor de la tienda', 'ubigeo-envio-peru' ); ?></dt>
				<dd><?php esc_html_e( 'Usuarios con rol de administrador o "Gestor de la tienda". En el checkout ven opciones internas: envíos de Mercado Libre, métodos de pago del equipo, cualquier fecha de recojo y los campos de agencia.', 'ubigeo-envio-peru' ); ?></dd>

				<dt><?php esc_html_e( 'Moneda de las tarifas', 'ubigeo-envio-peru' ); ?></dt>
				<dd><?php esc_html_e( 'Tus tarifas se escriben en la moneda base de WooCommerce. El cliente que navega en otra moneda las ve convertidas con el tipo de cambio de tu plugin de multimoneda, igual que tus productos.', 'ubigeo-envio-peru' ); ?></dd>
			</dl>
		</div>

		<div class="uep-card" id="uep-faq">
			<h2><?php esc_html_e( 'Preguntas técnicas', 'ubigeo-envio-peru' ); ?></h2>
			<p><strong><?php esc_html_e( '¿Funciona con el checkout por bloques?', 'ubigeo-envio-peru' ); ?></strong><br>
			<?php esc_html_e( 'No. Usa el checkout clásico (shortcode [woocommerce_checkout]). Si tu página usa bloques, verás un aviso en el panel con un botón para convertirla en un clic; se guarda una copia para poder volver atrás.', 'ubigeo-envio-peru' ); ?></p>

			<?php if ( '' !== (string) get_option( 'uep_checkout_backup', '' ) ) : ?>
				<p>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'uep_restaurar_checkout', admin_url( 'admin-post.php' ) ), 'uep_restaurar_checkout' ) ); ?>" class="button">
						<?php esc_html_e( 'Restaurar el contenido anterior de la página de finalizar compra', 'ubigeo-envio-peru' ); ?>
					</a>
				</p>
			<?php endif; ?>

			<p><strong><?php esc_html_e( '¿Qué datos recibe mi ERP?', 'ubigeo-envio-peru' ); ?></strong><br>
			<?php esc_html_e( 'Por la API REST de pedidos: el departamento (código ISO en "state" y nombre en "departamento"), la provincia, el distrito y el servicio de envío en "uep_envio" (servicio, nombre y canal web/interno).', 'ubigeo-envio-peru' ); ?></p>

			<p><strong><?php esc_html_e( '¿Usaba antes otros plugins de ubigeo o costo de envío?', 'ubigeo-envio-peru' ); ?></strong><br>
			<?php esc_html_e( 'Si tu tienda ya tenía las tablas de ubigeo (wp_ubigeo_departamento, provincia y distrito), este plugin las reutiliza y los pedidos antiguos siguen mostrando su ubigeo. Desactiva y borra los plugins anteriores para que no se dupliquen campos ni costos.', 'ubigeo-envio-peru' ); ?></p>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Handlers (admin-post)
	 * ------------------------------------------------------------------- */

	public static function guardar_tarifa() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( -1, 403 );
		}
		check_admin_referer( 'uep_guardar_tarifa' );

		$costo_express = isset( $_POST['costo_express'] ) ? trim( wp_unslash( $_POST['costo_express'] ) ) : '';

		$ok = uep_guardar_tarifa(
			isset( $_POST['idDepa'] ) ? absint( $_POST['idDepa'] ) : 0,
			isset( $_POST['idProv'] ) ? absint( $_POST['idProv'] ) : 0,
			isset( $_POST['idDist'] ) ? absint( $_POST['idDist'] ) : 0,
			isset( $_POST['costo'] ) ? (float) wp_unslash( $_POST['costo'] ) : -1,
			'' === $costo_express ? null : (float) $costo_express
		);

		self::redirect( 'tarifas', $ok ? 'tarifa_ok' : 'tarifa_error' );
	}

	public static function editar_tarifa() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( -1, 403 );
		}

		$tarifa_id = isset( $_POST['tarifa_id'] ) ? absint( $_POST['tarifa_id'] ) : 0;
		check_admin_referer( 'uep_editar_tarifa_' . $tarifa_id );

		$costo_express = isset( $_POST['costo_express'] ) ? trim( wp_unslash( $_POST['costo_express'] ) ) : '';

		$ok = uep_actualizar_tarifa(
			$tarifa_id,
			isset( $_POST['costo'] ) ? (float) wp_unslash( $_POST['costo'] ) : -1,
			'' === $costo_express ? null : (float) $costo_express
		);

		self::redirect( 'tarifas', $ok ? 'tarifa_ok' : 'tarifa_error' );
	}

	public static function eliminar_tarifa() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( -1, 403 );
		}

		$tarifa_id = isset( $_GET['tarifa_id'] ) ? absint( $_GET['tarifa_id'] ) : 0;
		check_admin_referer( 'uep_eliminar_tarifa_' . $tarifa_id );

		uep_eliminar_tarifa( $tarifa_id );

		self::redirect( 'tarifas', 'eliminada' );
	}

	public static function guardar_ajustes() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( -1, 403 );
		}
		check_admin_referer( 'uep_guardar_ajustes' );

		$express_default = isset( $_POST['express_default_cost'] ) ? trim( wp_unslash( $_POST['express_default_cost'] ) ) : '';

		$settings = array(
			'checkout_enabled'     => isset( $_POST['checkout_enabled'] ) ? 'yes' : 'no',
			'method_title'         => isset( $_POST['method_title'] ) ? sanitize_text_field( wp_unslash( $_POST['method_title'] ) ) : '',
			'default_cost'         => isset( $_POST['default_cost'] ) ? (string) max( 0, (float) $_POST['default_cost'] ) : '0',
			'free_over'            => isset( $_POST['free_over'] ) ? (string) max( 0, (float) $_POST['free_over'] ) : '0',
			'tarifa_currency'      => isset( $_POST['tarifa_currency'] ) && 'base' === $_POST['tarifa_currency'] ? 'base' : 'PEN',
			'tarifa_tc'            => isset( $_POST['tarifa_tc'] ) && (float) $_POST['tarifa_tc'] > 0 ? (string) (float) $_POST['tarifa_tc'] : '3.75',
			'express_enabled'      => isset( $_POST['express_enabled'] ) ? 'yes' : 'no',
			'express_title'        => isset( $_POST['express_title'] ) ? sanitize_text_field( wp_unslash( $_POST['express_title'] ) ) : '',
			'express_default_cost' => '' === $express_default ? '' : (string) max( 0, (float) $express_default ),
			'express_cutoff'       => isset( $_POST['express_cutoff'] ) && preg_match( '/^\d{2}:\d{2}$/', $_POST['express_cutoff'] ) ? $_POST['express_cutoff'] : '',
			'pago_solo_cobertura'  => isset( $_POST['pago_solo_cobertura'] ) ? array_values( array_map( 'sanitize_key', (array) $_POST['pago_solo_cobertura'] ) ) : array(),
			'pago_recojo'          => isset( $_POST['pago_recojo'] ) ? array_values( array_map( 'sanitize_key', (array) $_POST['pago_recojo'] ) ) : array(),
			'pago_solo_admin'      => isset( $_POST['pago_solo_admin'] ) ? array_values( array_map( 'sanitize_key', (array) $_POST['pago_solo_admin'] ) ) : array(),
			'pickup_enabled'       => isset( $_POST['pickup_enabled'] ) ? 'yes' : 'no',
			'pickup_title'         => isset( $_POST['pickup_title'] ) ? sanitize_text_field( wp_unslash( $_POST['pickup_title'] ) ) : '',
			'pickup_min_days'      => isset( $_POST['pickup_min_days'] ) ? (string) min( 30, max( 0, absint( $_POST['pickup_min_days'] ) ) ) : '2',
			'pickup_start'         => isset( $_POST['pickup_start'] ) && preg_match( '/^\d{2}:\d{2}$/', $_POST['pickup_start'] ) ? $_POST['pickup_start'] : '10:00',
			'pickup_end'           => isset( $_POST['pickup_end'] ) && preg_match( '/^\d{2}:\d{2}$/', $_POST['pickup_end'] ) ? $_POST['pickup_end'] : '18:00',
			'pickup_note'          => isset( $_POST['pickup_note'] ) ? sanitize_text_field( wp_unslash( $_POST['pickup_note'] ) ) : '',
			'pickup_closed_days'   => isset( $_POST['pickup_closed_days'] ) ? array_values( array_map( 'absint', (array) $_POST['pickup_closed_days'] ) ) : array(),
			'pickup_holidays'      => isset( $_POST['pickup_holidays'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pickup_holidays'] ) ) : '',
			'pickup_zone_enabled'  => isset( $_POST['pickup_zone_enabled'] ) ? 'yes' : 'no',
			'pickup_zonas'         => self::sanitizar_zonas( $_POST['pickup_zonas'] ?? array() ),
			'cobertura_enabled'    => isset( $_POST['cobertura_enabled'] ) ? 'yes' : 'no',
			'cobertura_zonas'      => self::sanitizar_zonas( $_POST['cobertura_zonas'] ?? array() ),
			'agencia_title'        => isset( $_POST['agencia_title'] ) ? sanitize_text_field( wp_unslash( $_POST['agencia_title'] ) ) : '',
			'agencia_note'         => isset( $_POST['agencia_note'] ) ? sanitize_text_field( wp_unslash( $_POST['agencia_note'] ) ) : '',
			'agencia_list'         => isset( $_POST['agencia_list'] ) ? sanitize_textarea_field( wp_unslash( $_POST['agencia_list'] ) ) : '',
			'admin_metodos'        => isset( $_POST['admin_metodos'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_metodos'] ) ) : '',
			'erp_state'            => isset( $_POST['erp_state'] ) ? 'yes' : 'no',
		);

		if ( '' === $settings['method_title'] ) {
			$settings['method_title'] = __( 'Envío a domicilio (1 a 2 días hábiles)', 'ubigeo-envio-peru' );
		}
		if ( '' === $settings['express_title'] ) {
			$settings['express_title'] = __( 'Envío Flash (mismo día / 24 horas)', 'ubigeo-envio-peru' );
		}
		if ( '' === $settings['pickup_title'] ) {
			$settings['pickup_title'] = __( 'Recojo en Tienda (gratis)', 'ubigeo-envio-peru' );
		}
		if ( '' === $settings['agencia_title'] ) {
			$settings['agencia_title'] = __( 'Envío por Agencia — pagas el envío al recoger en la agencia', 'ubigeo-envio-peru' );
		}

		update_option( 'uep_settings', $settings );

		// Activar/desactivar servicios cambia las opciones de envío: purga el caché.
		uep_purgar_cache_tarifas();

		self::redirect( 'ajustes', 'ajustes_ok' );
	}

	private static function redirect( $tab, $msg ) {
		wp_safe_redirect( add_query_arg( 'uep_msg', $msg, self::url( $tab ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * CSV
	 * ------------------------------------------------------------------- */

	public static function exportar_csv() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( -1, 403 );
		}
		check_admin_referer( 'uep_exportar_csv' );

		$tarifas = uep_get_tarifas();

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=tarifas-envio-peru-' . gmdate( 'Y-m-d' ) . '.csv' );

		$salida = fopen( 'php://output', 'w' );

		// BOM para que Excel abra bien las tildes/ñ.
		fwrite( $salida, "\xEF\xBB\xBF" );
		fputcsv( $salida, array( 'departamento', 'provincia', 'distrito', 'costo_normal', 'costo_flash' ) );

		foreach ( $tarifas as $t ) {
			$flash = ( isset( $t['costo_express'] ) && null !== $t['costo_express'] && '' !== (string) $t['costo_express'] )
				? number_format( (float) $t['costo_express'], 2, '.', '' )
				: '';

			fputcsv(
				$salida,
				array(
					trim( (string) $t['departamento'] ),
					trim( (string) $t['provincia'] ),
					trim( (string) $t['distrito'] ),
					number_format( (float) $t['costo'], 2, '.', '' ),
					$flash,
				)
			);
		}

		fclose( $salida ); // phpcs:ignore
		exit;
	}

	public static function importar_csv() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( -1, 403 );
		}
		check_admin_referer( 'uep_importar_csv' );

		if ( empty( $_FILES['uep_csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['uep_csv']['tmp_name'] ) ) {
			self::redirect( 'tarifas', 'import_error' );
		}

		$contenido = file_get_contents( $_FILES['uep_csv']['tmp_name'] ); // phpcs:ignore
		$contenido = str_replace( "\xEF\xBB\xBF", '', (string) $contenido );

		// Detecta el separador (Excel en español suele usar punto y coma).
		$primera_linea = strtok( $contenido, "\n" );
		$separador     = ( substr_count( (string) $primera_linea, ';' ) > substr_count( (string) $primera_linea, ',' ) ) ? ';' : ',';

		$filas   = array_values( array_filter( array_map( 'trim', explode( "\n", $contenido ) ), 'strlen' ) );
		$ok      = 0;
		$errores = 0;

		foreach ( $filas as $i => $linea ) {
			$campos = str_getcsv( $linea, $separador );

			if ( count( $campos ) < 4 ) {
				$errores++;
				continue;
			}

			list( $depa_nombre, $prov_nombre, $dist_nombre, $costo ) = array_map( 'trim', array_slice( array_pad( $campos, 5, '' ), 0, 4 ) );
			$flash = trim( (string) ( $campos[4] ?? '' ) );

			// Salta la cabecera.
			if ( 0 === $i && ! is_numeric( str_replace( ',', '.', $costo ) ) ) {
				continue;
			}

			$id_depa = uep_buscar_depa_por_nombre( $depa_nombre );
			if ( ! $id_depa ) {
				$errores++;
				continue;
			}

			$id_prov = 0;
			$id_dist = 0;

			if ( '' !== $prov_nombre ) {
				$id_prov = uep_buscar_prov_por_nombre( $id_depa, $prov_nombre );
				if ( ! $id_prov ) {
					$errores++;
					continue;
				}
			}

			if ( '' !== $dist_nombre ) {
				if ( ! $id_prov ) {
					$errores++;
					continue;
				}
				$id_dist = uep_buscar_dist_por_nombre( $id_prov, $dist_nombre );
				if ( ! $id_dist ) {
					$errores++;
					continue;
				}
			}

			$costo = (float) str_replace( ',', '.', $costo );
			$flash = '' === $flash ? null : (float) str_replace( ',', '.', $flash );

			if ( uep_guardar_tarifa( $id_depa, $id_prov, $id_dist, $costo, $flash ) ) {
				$ok++;
			} else {
				$errores++;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'uep_msg' => 'import',
					'uep_ok'  => $ok,
					'uep_err' => $errores,
				),
				self::url( 'tarifas' )
			)
		);
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Exportar / importar configuración completa (JSON)
	 * ------------------------------------------------------------------- */

	public static function exportar_config() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( -1, 403 );
		}
		check_admin_referer( 'uep_exportar_config' );

		global $wpdb;

		$tarifas = $wpdb->get_results(
			"SELECT idDepa, idProv, idDist, costo, costo_express FROM {$wpdb->prefix}ubigeo_envio_tarifa WHERE estado = 1",
			ARRAY_A
		);

		$paquete = array(
			'plugin'   => 'ubigeo-envio-peru',
			'version'  => UEP_VERSION,
			'exported' => gmdate( 'c' ),
			'settings' => uep_get_settings(),
			'tarifas'  => is_array( $tarifas ) ? $tarifas : array(),
		);

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=envio-peru-config-' . gmdate( 'Y-m-d' ) . '.json' );

		echo wp_json_encode( $paquete, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public static function importar_config() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( -1, 403 );
		}
		check_admin_referer( 'uep_importar_config' );

		if ( empty( $_FILES['uep_config']['tmp_name'] ) || ! is_uploaded_file( $_FILES['uep_config']['tmp_name'] ) ) {
			self::redirect( 'ajustes', 'config_error' );
		}

		$json    = file_get_contents( $_FILES['uep_config']['tmp_name'] ); // phpcs:ignore
		$paquete = json_decode( (string) $json, true );

		if ( ! is_array( $paquete ) || 'ubigeo-envio-peru' !== ( $paquete['plugin'] ?? '' ) ) {
			self::redirect( 'ajustes', 'config_error' );
		}

		// Ajustes: solo claves conocidas, con la sanitización de cada tipo.
		if ( isset( $paquete['settings'] ) && is_array( $paquete['settings'] ) ) {
			update_option( 'uep_settings', self::sanitizar_settings( $paquete['settings'] ) );
		}

		// Tarifas: si el archivo las incluye, reemplazan a las actuales.
		if ( isset( $paquete['tarifas'] ) && is_array( $paquete['tarifas'] ) && $paquete['tarifas'] ) {
			global $wpdb;
			$wpdb->query( "DELETE FROM {$wpdb->prefix}ubigeo_envio_tarifa" );

			foreach ( $paquete['tarifas'] as $t ) {
				if ( ! is_array( $t ) ) {
					continue;
				}

				$flash = $t['costo_express'] ?? null;

				uep_guardar_tarifa(
					absint( $t['idDepa'] ?? 0 ),
					absint( $t['idProv'] ?? 0 ),
					absint( $t['idDist'] ?? 0 ),
					(float) ( $t['costo'] ?? -1 ),
					( null === $flash || '' === $flash ) ? null : (float) $flash
				);
			}
		}

		uep_purgar_cache_tarifas();

		self::redirect( 'ajustes', 'config_ok' );
	}

	/**
	 * Sanitiza los ajustes importados: solo claves conocidas y cada una con
	 * el tipo correcto. Lo desconocido se descarta.
	 *
	 * @param array $crudo Ajustes del archivo.
	 * @return array
	 */
	private static function sanitizar_settings( $crudo ) {
		$limpio = uep_get_settings();

		$si_no = array( 'checkout_enabled', 'express_enabled', 'pickup_enabled', 'pickup_zone_enabled', 'cobertura_enabled', 'erp_state' );
		foreach ( $si_no as $clave ) {
			if ( isset( $crudo[ $clave ] ) ) {
				$limpio[ $clave ] = 'yes' === $crudo[ $clave ] ? 'yes' : 'no';
			}
		}

		$textos = array( 'method_title', 'express_title', 'pickup_title', 'pickup_note', 'agencia_title', 'agencia_note' );
		foreach ( $textos as $clave ) {
			if ( isset( $crudo[ $clave ] ) ) {
				$limpio[ $clave ] = sanitize_text_field( (string) $crudo[ $clave ] );
			}
		}

		$multilinea = array( 'pickup_holidays', 'agencia_list', 'admin_metodos' );
		foreach ( $multilinea as $clave ) {
			if ( isset( $crudo[ $clave ] ) ) {
				$limpio[ $clave ] = sanitize_textarea_field( (string) $crudo[ $clave ] );
			}
		}

		$montos = array( 'default_cost', 'free_over', 'express_default_cost' );
		foreach ( $montos as $clave ) {
			if ( isset( $crudo[ $clave ] ) ) {
				$limpio[ $clave ] = '' === (string) $crudo[ $clave ] ? '' : (string) max( 0, (float) $crudo[ $clave ] );
			}
		}

		$horas = array( 'pickup_start', 'pickup_end', 'express_cutoff' );
		foreach ( $horas as $clave ) {
			if ( isset( $crudo[ $clave ] ) && preg_match( '/^\d{2}:\d{2}$/', (string) $crudo[ $clave ] ) ) {
				$limpio[ $clave ] = (string) $crudo[ $clave ];
			}
		}

		if ( isset( $crudo['pickup_min_days'] ) ) {
			$limpio['pickup_min_days'] = (string) min( 30, max( 0, absint( $crudo['pickup_min_days'] ) ) );
		}

		if ( isset( $crudo['pickup_closed_days'] ) ) {
			$limpio['pickup_closed_days'] = array_values(
				array_filter(
					array_map( 'absint', (array) $crudo['pickup_closed_days'] ),
					function ( $d ) {
						return $d >= 0 && $d <= 6;
					}
				)
			);
		}

		if ( isset( $crudo['pago_solo_cobertura'] ) ) {
			$limpio['pago_solo_cobertura'] = array_values( array_map( 'sanitize_key', (array) $crudo['pago_solo_cobertura'] ) );
		}

		if ( isset( $crudo['pago_recojo'] ) ) {
			$limpio['pago_recojo'] = array_values( array_map( 'sanitize_key', (array) $crudo['pago_recojo'] ) );
		}

		if ( isset( $crudo['pago_solo_admin'] ) ) {
			$limpio['pago_solo_admin'] = array_values( array_map( 'sanitize_key', (array) $crudo['pago_solo_admin'] ) );
		}

		if ( isset( $crudo['tarifa_currency'] ) ) {
			$limpio['tarifa_currency'] = 'base' === $crudo['tarifa_currency'] ? 'base' : 'PEN';
		}

		if ( isset( $crudo['tarifa_tc'] ) && (float) $crudo['tarifa_tc'] > 0 ) {
			$limpio['tarifa_tc'] = (string) (float) $crudo['tarifa_tc'];
		}

		foreach ( array( 'cobertura_zonas', 'pickup_zonas' ) as $clave ) {
			if ( isset( $crudo[ $clave ] ) ) {
				$limpio[ $clave ] = self::sanitizar_zonas( $crudo[ $clave ] );
			}
		}

		return $limpio;
	}

	/* ---------------------------------------------------------------------
	 * Checkout por bloques: aviso y conversión al checkout clásico
	 * ------------------------------------------------------------------- */

	/**
	 * ¿La página de finalizar compra usa el checkout por bloques?
	 */
	private static function checkout_usa_bloques() {
		$pagina_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'checkout' ) : 0;

		if ( $pagina_id <= 0 ) {
			return false;
		}

		$pagina = get_post( $pagina_id );

		return $pagina && has_block( 'woocommerce/checkout', $pagina );
	}

	public static function aviso_checkout_bloques() {
		if ( ! current_user_can( self::CAPABILITY ) || ! self::checkout_usa_bloques() ) {
			return;
		}

		$url_convertir = wp_nonce_url(
			add_query_arg( 'action', 'uep_convertir_checkout', admin_url( 'admin-post.php' ) ),
			'uep_convertir_checkout'
		);
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Ubigeo y Envío Perú:', 'ubigeo-envio-peru' ); ?></strong>
				<?php esc_html_e( 'Tu página de finalizar compra usa el checkout por bloques, donde los campos de ubigeo y el costo de envío NO funcionan. El plugin necesita el checkout clásico.', 'ubigeo-envio-peru' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $url_convertir ); ?>" class="button button-primary"
				   onclick="return confirm('<?php echo esc_js( __( 'Se reemplazará el contenido de la página de finalizar compra por el checkout clásico. Se guardará una copia para poder restaurarla. ¿Continuar?', 'ubigeo-envio-peru' ) ); ?>');">
					<?php esc_html_e( 'Convertir al checkout clásico', 'ubigeo-envio-peru' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	public static function convertir_checkout() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( -1, 403 );
		}
		check_admin_referer( 'uep_convertir_checkout' );

		$pagina_id = wc_get_page_id( 'checkout' );
		$pagina    = $pagina_id > 0 ? get_post( $pagina_id ) : null;

		if ( ! $pagina ) {
			self::redirect( 'ayuda', 'checkout_error' );
		}

		// Copia de seguridad del contenido actual para poder restaurarlo.
		update_option( 'uep_checkout_backup', $pagina->post_content, false );

		wp_update_post(
			array(
				'ID'           => $pagina_id,
				'post_content' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
			)
		);

		self::redirect( 'ayuda', 'checkout_convertido' );
	}

	public static function restaurar_checkout() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( -1, 403 );
		}
		check_admin_referer( 'uep_restaurar_checkout' );

		$backup    = get_option( 'uep_checkout_backup', '' );
		$pagina_id = wc_get_page_id( 'checkout' );

		if ( '' !== $backup && $pagina_id > 0 ) {
			wp_update_post(
				array(
					'ID'           => $pagina_id,
					'post_content' => $backup,
				)
			);
			delete_option( 'uep_checkout_backup' );
		}

		self::redirect( 'ayuda', 'checkout_restaurado' );
	}
}
