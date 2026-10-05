<?php
/**
 * Campos de ubigeo en el checkout clásico y visualización en pedidos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UEP_Checkout {

	public static function init() {
		$settings = uep_get_settings();

		if ( 'yes' !== $settings['checkout_enabled'] ) {
			return;
		}

		// Campos y localización para Perú.
		add_filter( 'woocommerce_states', array( __CLASS__, 'quitar_estados_peru' ) );
		add_filter( 'woocommerce_get_country_locale', array( __CLASS__, 'locale_peru' ) );
		add_filter( 'woocommerce_default_address_fields', array( __CLASS__, 'campos_base' ) );
		add_filter( 'woocommerce_country_locale_field_selectors', array( __CLASS__, 'selectores_locale' ) );
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'agregar_campos' ), 99 );

		// Assets del checkout.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );

		// Visualización del ubigeo con nombres (pedidos, emails, cuenta).
		add_filter( 'woocommerce_order_formatted_billing_address', array( __CLASS__, 'direccion_facturacion' ), 10, 2 );
		add_filter( 'woocommerce_order_formatted_shipping_address', array( __CLASS__, 'direccion_envio' ), 10, 2 );

		// Bloque detallado del ubigeo en los correos (cliente y administrador).
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'ubigeo_en_email' ), 5 );

		// API REST de pedidos: agrega los nombres del ubigeo.
		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( __CLASS__, 'rest_pedido' ), 10, 2 );

		// Coherencia del ubigeo en el servidor (no confiar en los IDs posteados).
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validar_jerarquia' ), 10, 2 );

		// Estimador de costo de envío en la página del carrito.
		add_action( 'woocommerce_before_cart_totals', array( __CLASS__, 'estimador_carrito' ) );

		// Datos para integraciones/ERP: servicio y canal del envío + código ISO
		// del departamento en el campo estándar Estado.
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'datos_para_erp' ), 20, 2 );

		// El ubigeo elegido en el carrito (estimador) precarga los campos del
		// checkout, con prioridad sobre el guardado en la cuenta del cliente.
		add_filter( 'woocommerce_checkout_get_value', array( __CLASS__, 'valor_desde_estimador' ), 10, 2 );
	}

	/**
	 * Devuelve el ubigeo de la sesión (elegido en el carrito) como valor por
	 * defecto de los campos del checkout. El valor posteado siempre gana
	 * (WooCommerce lo resuelve antes de aplicar este filtro).
	 *
	 * @param mixed  $value Valor actual (null = sin resolver).
	 * @param string $input Nombre del campo.
	 * @return mixed
	 */
	public static function valor_desde_estimador( $value, $input ) {
		$indices = array(
			'billing_departamento' => 0,
			'billing_provincia'    => 1,
			'billing_distrito'     => 2,
		);

		if ( ! isset( $indices[ $input ] ) || ! WC()->session ) {
			return $value;
		}

		$ubigeo = (array) WC()->session->get( 'uep_ubigeo', array() );
		$id     = absint( $ubigeo[ $indices[ $input ] ] ?? 0 );

		return $id ? (string) $id : $value;
	}

	/**
	 * Servicio de envío elegido, en códigos estables para integraciones.
	 *
	 * WooCommerce guarda en el pedido el method_id del método padre
	 * (uep_envio_peru) para los cinco servicios, así que el detalle se toma
	 * de la tarifa elegida en la sesión.
	 *
	 * @return array|null array( codigo, nombre, canal ) o null.
	 */
	private static function servicio_elegido() {
		if ( ! WC()->session ) {
			return null;
		}

		$settings = uep_get_settings();
		$base     = 'uep_envio_peru';

		foreach ( (array) WC()->session->get( 'chosen_shipping_methods', array() ) as $rate_id ) {
			if ( 0 !== strpos( (string) $rate_id, $base ) ) {
				continue;
			}

			$sufijo = substr( (string) $rate_id, strlen( $base ) );

			if ( '' === $sufijo ) {
				return array( 'domicilio', $settings['method_title'], 'web' );
			}
			if ( '_express' === $sufijo ) {
				return array( 'flash', $settings['express_title'], 'web' );
			}
			if ( '_pickup' === $sufijo ) {
				return array( 'pickup', $settings['pickup_title'], 'web' );
			}
			if ( '_agencia' === $sufijo ) {
				return array( 'agencia', $settings['agencia_title'], 'web' );
			}
			if ( 0 === strpos( $sufijo, '_admin_' ) ) {
				$indice  = absint( substr( $sufijo, strlen( '_admin_' ) ) );
				$metodos = uep_metodos_admin();
				$nombre  = isset( $metodos[ $indice ]['nombre'] ) ? $metodos[ $indice ]['nombre'] : 'interno';

				return array( 'interno-' . sanitize_title( $nombre ), $nombre, 'interno' );
			}
		}

		return null;
	}

	/**
	 * Guarda en el pedido los datos que necesitan las integraciones (ERP,
	 * facturación, reportes):
	 *
	 * - _uep_servicio / _uep_servicio_nombre / _uep_canal: qué servicio de
	 *   envío se usó, con un código estable que no depende del texto visible.
	 * - billing_state / shipping_state: código ISO del departamento (LIM, CAL,
	 *   CUS…), el mismo que usan WooCommerce y Odoo, para que el ERP empareje
	 *   el contacto sin configuración adicional.
	 *
	 * @param WC_Order $order Pedido en construcción.
	 * @param array    $data  Datos posteados del checkout.
	 */
	public static function datos_para_erp( $order, $data ) {
		$servicio = self::servicio_elegido();

		if ( $servicio ) {
			$order->update_meta_data( '_uep_servicio', $servicio[0] );
			$order->update_meta_data( '_uep_servicio_nombre', $servicio[1] );
			$order->update_meta_data( '_uep_canal', $servicio[2] );
		}

		if ( 'yes' !== uep_get_settings()['erp_state'] ) {
			return;
		}

		foreach ( array( 'billing', 'shipping' ) as $tipo ) {
			$id_depa = absint( $order->get_meta( "_{$tipo}_departamento" ) );
			$id_dist = absint( $order->get_meta( "_{$tipo}_distrito" ) );

			if ( ! $id_depa && isset( $data[ "{$tipo}_departamento" ] ) ) {
				$id_depa = absint( $data[ "{$tipo}_departamento" ] );
			}
			if ( ! $id_dist && isset( $data[ "{$tipo}_distrito" ] ) ) {
				$id_dist = absint( $data[ "{$tipo}_distrito" ] );
			}

			if ( ! $id_depa ) {
				continue;
			}

			$iso = uep_departamento_iso( $id_depa );

			if ( '' !== $iso ) {
				if ( 'billing' === $tipo ) {
					$order->set_billing_state( $iso );
				} else {
					$order->set_shipping_state( $iso );
				}
			}

			// La ciudad se llena con el distrito desde el navegador; aquí se
			// asegura también en el servidor (pedidos por API, sin JS, etc.).
			$ciudad = 'billing' === $tipo ? $order->get_billing_city() : $order->get_shipping_city();

			if ( '' === trim( (string) $ciudad ) && $id_dist ) {
				$nombres = uep_get_nombres_ubigeo( 0, 0, $id_dist );

				if ( '' !== $nombres['distrito'] ) {
					if ( 'billing' === $tipo ) {
						$order->set_billing_city( $nombres['distrito'] );
					} else {
						$order->set_shipping_city( $nombres['distrito'] );
					}
				}
			}
		}
	}

	/**
	 * Estimador de envío en el carrito: el cliente elige su ubigeo y el total
	 * muestra el costo antes de llegar al checkout.
	 */
	public static function estimador_carrito() {
		$guardado = WC()->session ? (array) WC()->session->get( 'uep_ubigeo', array() ) : array();
		$id_depa  = absint( $guardado[0] ?? 0 );
		$id_prov  = absint( $guardado[1] ?? 0 );
		$id_dist  = absint( $guardado[2] ?? 0 );
		?>
		<div class="uep-estimador">
			<strong><?php esc_html_e( '¿A dónde enviamos tu pedido?', 'ubigeo-envio-peru' ); ?></strong>

			<select id="uep_est_depa">
				<option value=""><?php esc_html_e( 'Departamento…', 'ubigeo-envio-peru' ); ?></option>
				<?php foreach ( uep_get_departamentos() as $dep ) : ?>
					<option value="<?php echo esc_attr( $dep['idDepa'] ); ?>" <?php selected( $id_depa, (int) $dep['idDepa'] ); ?>>
						<?php echo esc_html( $dep['departamento'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select id="uep_est_prov">
				<option value=""><?php esc_html_e( 'Provincia…', 'ubigeo-envio-peru' ); ?></option>
				<?php if ( $id_depa ) : ?>
					<?php foreach ( uep_get_provincias( $id_depa ) as $prov ) : ?>
						<option value="<?php echo esc_attr( $prov['idProv'] ); ?>" <?php selected( $id_prov, (int) $prov['idProv'] ); ?>>
							<?php echo esc_html( $prov['provincia'] ); ?>
						</option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>

			<select id="uep_est_dist">
				<option value=""><?php esc_html_e( 'Distrito…', 'ubigeo-envio-peru' ); ?></option>
				<?php if ( $id_prov ) : ?>
					<?php foreach ( uep_get_distritos( $id_prov ) as $dist ) : ?>
						<option value="<?php echo esc_attr( $dist['idDist'] ); ?>" <?php selected( $id_dist, (int) $dist['idDist'] ); ?>>
							<?php echo esc_html( $dist['distrito'] ); ?>
						</option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>

			<button type="button" class="button" id="uep_est_calcular"><?php esc_html_e( 'Calcular envío', 'ubigeo-envio-peru' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Rechaza combinaciones incoherentes (ej. un distrito que no pertenece a la
	 * provincia elegida), que podrían forzar una tarifa equivocada.
	 *
	 * @param array    $data   Datos del checkout.
	 * @param WP_Error $errors Errores.
	 */
	public static function validar_jerarquia( $data, $errors ) {
		$conjuntos = array( 'billing' );

		if ( ! empty( $data['ship_to_different_address'] ) ) {
			$conjuntos[] = 'shipping';
		}

		foreach ( $conjuntos as $tipo ) {
			$pais = $data[ $tipo . '_country' ] ?? '';

			if ( 'PE' !== $pais ) {
				continue;
			}

			$id_depa = absint( $_POST[ $tipo . '_departamento' ] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
			$id_prov = absint( $_POST[ $tipo . '_provincia' ] ?? 0 );    // phpcs:ignore WordPress.Security.NonceVerification
			$id_dist = absint( $_POST[ $tipo . '_distrito' ] ?? 0 );     // phpcs:ignore WordPress.Security.NonceVerification

			// Los campos vacíos ya los reporta la validación de "campo obligatorio".
			if ( ! $id_depa || ! $id_prov || ! $id_dist ) {
				continue;
			}

			if ( ! uep_validar_jerarquia( $id_depa, $id_prov, $id_dist ) ) {
				$errors->add(
					'uep_ubigeo_invalido',
					__( 'La combinación de departamento, provincia y distrito no es válida. Vuelve a seleccionar tu ubicación.', 'ubigeo-envio-peru' )
				);
				return;
			}
		}
	}

	/**
	 * Vacía la lista de estados de Perú (se reemplazan por el ubigeo).
	 */
	public static function quitar_estados_peru( $states ) {
		$states['PE'] = array();
		return $states;
	}

	/**
	 * Para Perú: oculta estado/ciudad/código postal y hace obligatorio el ubigeo.
	 * Para el resto de países el ubigeo queda oculto (ver campos_base()).
	 */
	public static function locale_peru( $locale ) {
		$locale['PE']['state']    = array( 'required' => false, 'hidden' => true );
		$locale['PE']['city']     = array( 'required' => false, 'hidden' => true );
		$locale['PE']['postcode'] = array( 'required' => false, 'hidden' => true );

		$locale['PE']['departamento'] = array( 'required' => true, 'hidden' => false );
		$locale['PE']['provincia']    = array( 'required' => true, 'hidden' => false );
		$locale['PE']['distrito']     = array( 'required' => true, 'hidden' => false );

		return $locale;
	}

	/**
	 * Registra los campos de ubigeo como campos de dirección base, ocultos por
	 * defecto. El locale de Perú los muestra y los vuelve obligatorios, y el JS
	 * de WooCommerce los oculta automáticamente al cambiar a otro país.
	 */
	public static function campos_base( $fields ) {
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return $fields;
		}

		foreach ( array( 'departamento', 'provincia', 'distrito' ) as $campo ) {
			$fields[ $campo ] = array(
				'required' => false,
				'hidden'   => true,
			);
		}

		return $fields;
	}

	/**
	 * Selectores CSS que usa el JS de WooCommerce para mostrar/ocultar
	 * los campos de ubigeo al cambiar de país.
	 */
	public static function selectores_locale( $selectors ) {
		return array_merge(
			$selectors,
			array(
				'departamento' => '#billing_departamento_field, #shipping_departamento_field',
				'provincia'    => '#billing_provincia_field, #shipping_provincia_field',
				'distrito'     => '#billing_distrito_field, #shipping_distrito_field',
			)
		);
	}

	/**
	 * Agrega los selects de Departamento / Provincia / Distrito.
	 */
	public static function agregar_campos( $fields ) {
		$checkout = WC()->checkout();

		foreach ( array( 'billing', 'shipping' ) as $seccion ) {
			// Obligatorio solo cuando el país (facturación o envío) es Perú;
			// para otros países el campo está oculto y no debe bloquear el pago.
			$pais     = $checkout->get_value( $seccion . '_country' );
			$required = empty( $pais ) || 'PE' === $pais;

			$id_depa = absint( $checkout->get_value( $seccion . '_departamento' ) );
			$id_prov = absint( $checkout->get_value( $seccion . '_provincia' ) );

			$fields[ $seccion ][ $seccion . '_departamento' ] = array(
				'type'     => 'select',
				'label'    => __( 'Departamento', 'ubigeo-envio-peru' ),
				'required' => $required,
				'class'    => array( 'form-row-wide', 'uep-select' ),
				'options'  => self::opciones_departamentos(),
				'priority' => 65,
			);

			$fields[ $seccion ][ $seccion . '_provincia' ] = array(
				'type'     => 'select',
				'label'    => __( 'Provincia', 'ubigeo-envio-peru' ),
				'required' => $required,
				'class'    => array( 'form-row-wide', 'uep-select' ),
				'options'  => self::opciones_provincias( $id_depa ),
				'priority' => 66,
			);

			$fields[ $seccion ][ $seccion . '_distrito' ] = array(
				'type'     => 'select',
				'label'    => __( 'Distrito', 'ubigeo-envio-peru' ),
				'required' => $required,
				'class'    => array( 'form-row-wide', 'uep-select' ),
				'options'  => self::opciones_distritos( $id_prov ),
				'priority' => 67,
			);
		}

		return $fields;
	}

	private static function opciones_departamentos() {
		$opciones = array( '' => __( 'Elige un departamento…', 'ubigeo-envio-peru' ) );

		foreach ( uep_get_departamentos() as $dep ) {
			$opciones[ $dep['idDepa'] ] = $dep['departamento'];
		}

		return $opciones;
	}

	private static function opciones_provincias( $id_depa ) {
		$opciones = array( '' => __( 'Elige una provincia…', 'ubigeo-envio-peru' ) );

		if ( $id_depa ) {
			foreach ( uep_get_provincias( $id_depa ) as $prov ) {
				$opciones[ $prov['idProv'] ] = $prov['provincia'];
			}
		}

		return $opciones;
	}

	private static function opciones_distritos( $id_prov ) {
		$opciones = array( '' => __( 'Elige un distrito…', 'ubigeo-envio-peru' ) );

		if ( $id_prov ) {
			foreach ( uep_get_distritos( $id_prov ) as $dist ) {
				$opciones[ $dist['idDist'] ] = $dist['distrito'];
			}
		}

		return $opciones;
	}

	/**
	 * JS de selects encadenados, solo en el checkout.
	 */
	public static function assets() {
		if ( ! is_checkout() && ! is_cart() ) {
			return;
		}

		wp_enqueue_style(
			'uep-checkout',
			UEP_PLUGIN_URL . 'assets/css/checkout.css',
			array(),
			UEP_VERSION
		);

		if ( is_cart() ) {
			wp_enqueue_script(
				'uep-cart',
				UEP_PLUGIN_URL . 'assets/js/cart.js',
				array( 'jquery' ),
				UEP_VERSION,
				true
			);

			wp_localize_script(
				'uep-cart',
				'uep_cart',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'uep_ubigeo' ),
					'i18n'     => array(
						'provincia' => __( 'Provincia…', 'ubigeo-envio-peru' ),
						'distrito'  => __( 'Distrito…', 'ubigeo-envio-peru' ),
					),
				)
			);

			return;
		}

		wp_enqueue_script(
			'uep-checkout',
			UEP_PLUGIN_URL . 'assets/js/checkout.js',
			array( 'jquery' ),
			UEP_VERSION,
			true
		);

		$pickup = null;
		// Los gestores de la tienda eligen cualquier fecha: sin bloqueos en el JS.
		if ( class_exists( 'UEP_Pickup' ) && 'yes' === uep_get_settings()['pickup_enabled'] && ! UEP_Pickup::sin_restricciones() ) {
			$pickup = array(
				'cerrados' => array_map( 'strval', UEP_Pickup::dias_cerrados() ),
				'feriados' => UEP_Pickup::feriados(),
				'msg'      => __( 'La tienda no atiende ese día. Elige otra fecha, por favor.', 'ubigeo-envio-peru' ),
			);
		}

		wp_localize_script(
			'uep-checkout',
			'uep_checkout',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'uep_ubigeo' ),
				'pickup'   => $pickup,
				'i18n'     => array(
					'provincia' => __( 'Elige una provincia…', 'ubigeo-envio-peru' ),
					'distrito'  => __( 'Elige un distrito…', 'ubigeo-envio-peru' ),
				),
			)
		);
	}

	/**
	 * Tarjeta unificada "Detalles del envío" en todos los correos de pedido
	 * (cliente y administrador): método, ubigeo detallado y los datos del
	 * servicio elegido (domicilio, recojo en tienda o agencia).
	 *
	 * @param WC_Order $order Pedido.
	 */
	public static function ubigeo_en_email( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$settings = uep_get_settings();
		$filas    = array();
		$nota     = '';

		// Método de envío elegido (con nombre visible).
		$metodo = $order->get_shipping_method();
		if ( $metodo ) {
			$filas[] = array( __( 'Método de envío', 'ubigeo-envio-peru' ), $metodo );
		}

		// Ubigeo detallado (envío si existe; si no, facturación).
		$tipo    = $order->get_meta( '_shipping_departamento' ) ? 'shipping' : 'billing';
		$id_depa = absint( $order->get_meta( "_{$tipo}_departamento" ) );

		if ( $id_depa ) {
			$nombres = uep_get_nombres_ubigeo(
				$id_depa,
				$order->get_meta( "_{$tipo}_provincia" ),
				$order->get_meta( "_{$tipo}_distrito" )
			);

			if ( $nombres['departamento'] ) {
				$filas[] = array( __( 'Departamento', 'ubigeo-envio-peru' ), $nombres['departamento'] );
			}
			if ( $nombres['provincia'] ) {
				$filas[] = array( __( 'Provincia', 'ubigeo-envio-peru' ), $nombres['provincia'] );
			}
			if ( $nombres['distrito'] ) {
				$filas[] = array( __( 'Distrito', 'ubigeo-envio-peru' ), $nombres['distrito'] );
			}
		}

		$es_recojo  = '' !== (string) $order->get_meta( '_uep_pickup_fecha' );
		$es_agencia = class_exists( 'UEP_Agencia' ) && UEP_Agencia::pedido_por_agencia( $order );

		if ( $es_recojo ) {
			$filas[] = array( __( 'Fecha y hora de recojo', 'ubigeo-envio-peru' ), UEP_Pickup::texto_recojo( $order ) );

			if ( '' !== trim( $settings['pickup_note'] ) ) {
				$filas[] = array( __( 'Recoger en', 'ubigeo-envio-peru' ), $settings['pickup_note'] );
			}
		} elseif ( $es_agencia ) {
			$agencia = (string) $order->get_meta( '_uep_agencia_nombre' );
			$sede    = (string) $order->get_meta( '_uep_agencia_sede' );
			$guia    = (string) $order->get_meta( '_uep_agencia_guia' );

			if ( $agencia ) {
				$filas[] = array( __( 'Agencia', 'ubigeo-envio-peru' ), $agencia );
			}
			if ( $sede ) {
				$filas[] = array( __( 'Sede de recojo', 'ubigeo-envio-peru' ), $sede );
			}
			if ( $guia ) {
				$filas[] = array( __( 'N° de guía', 'ubigeo-envio-peru' ), $guia );
			}

			$nota = __( 'El costo del envío lo cobra la agencia cuando recojas tu producto.', 'ubigeo-envio-peru' );
			if ( '' !== trim( $settings['agencia_note'] ) ) {
				$nota .= ' ' . $settings['agencia_note'];
			}
		} else {
			// Envío a domicilio: la dirección exacta del cliente.
			$dir = trim( $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2() );
			if ( '' === $dir ) {
				$dir = trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() );
			}
			if ( '' !== $dir ) {
				$filas[] = array( __( 'Dirección de entrega', 'ubigeo-envio-peru' ), $dir );
			}
			if ( $order->get_billing_phone() ) {
				$filas[] = array( __( 'Teléfono de contacto', 'ubigeo-envio-peru' ), $order->get_billing_phone() );
			}
		}

		if ( ! $filas ) {
			return;
		}

		// Tarjeta con el mismo estilo de las tablas de los correos de WooCommerce.
		$estilo_celda = 'padding:10px 12px;border:1px solid #e5e5e5;font-family:\'Helvetica Neue\',Helvetica,Roboto,Arial,sans-serif;font-size:14px;color:#636363;';

		echo '<h2 style="font-family:\'Helvetica Neue\',Helvetica,Roboto,Arial,sans-serif;font-size:18px;margin:0 0 12px;">'
			. esc_html__( 'Detalles del envío', 'ubigeo-envio-peru' ) . '</h2>';

		echo '<table cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;margin:0 0 24px;">';

		foreach ( $filas as $fila ) {
			echo '<tr>';
			echo '<td style="' . esc_attr( $estilo_celda ) . 'width:40%;"><strong>' . esc_html( $fila[0] ) . ':</strong></td>';
			echo '<td style="' . esc_attr( $estilo_celda ) . '">' . esc_html( $fila[1] ) . '</td>';
			echo '</tr>';
		}

		if ( '' !== $nota ) {
			echo '<tr><td colspan="2" style="' . esc_attr( $estilo_celda ) . 'background:#f8f8f8;">' . esc_html( $nota ) . '</td></tr>';
		}

		echo '</table>';
	}

	/**
	 * Completa la dirección de facturación formateada con los nombres del ubigeo.
	 * Con esto la dirección se ve completa en el admin, emails, "gracias por tu
	 * compra" y "mi cuenta", sin bloques extra.
	 */
	public static function direccion_facturacion( $address, $order ) {
		return self::inyectar_ubigeo( $address, $order, 'billing' );
	}

	public static function direccion_envio( $address, $order ) {
		return self::inyectar_ubigeo( $address, $order, 'shipping' );
	}

	private static function inyectar_ubigeo( $address, $order, $tipo ) {
		if ( ! is_array( $address ) || ! $order instanceof WC_Order ) {
			return $address;
		}

		if ( isset( $address['country'] ) && 'PE' !== $address['country'] ) {
			return $address;
		}

		$id_depa = absint( $order->get_meta( "_{$tipo}_departamento" ) );
		$id_prov = absint( $order->get_meta( "_{$tipo}_provincia" ) );
		$id_dist = absint( $order->get_meta( "_{$tipo}_distrito" ) );

		if ( ! $id_depa ) {
			return $address;
		}

		$nombres = uep_get_nombres_ubigeo( $id_depa, $id_prov, $id_dist );

		$ciudad = implode( ', ', array_filter( array( $nombres['distrito'], $nombres['provincia'] ) ) );

		if ( $ciudad ) {
			$address['city'] = $ciudad;
		}
		if ( $nombres['departamento'] ) {
			$address['state'] = $nombres['departamento'];
		}

		return $address;
	}

	/**
	 * Nombres del ubigeo en la respuesta REST de pedidos.
	 */
	public static function rest_pedido( $response, $order ) {
		if ( empty( $response->data ) || ! $order instanceof WC_Order ) {
			return $response;
		}

		foreach ( array( 'billing', 'shipping' ) as $tipo ) {
			$nombres = uep_get_nombres_ubigeo(
				$order->get_meta( "_{$tipo}_departamento" ),
				$order->get_meta( "_{$tipo}_provincia" ),
				$order->get_meta( "_{$tipo}_distrito" )
			);

			$response->data[ $tipo ]['departamento'] = $nombres['departamento'];
			$response->data[ $tipo ]['provincia']    = $nombres['provincia'];
			$response->data[ $tipo ]['distrito']     = $nombres['distrito'];
		}

		// Servicio de envío en códigos estables, para integraciones y ERP.
		$response->data['uep_envio'] = array(
			'servicio' => (string) $order->get_meta( '_uep_servicio' ),
			'nombre'   => (string) $order->get_meta( '_uep_servicio_nombre' ),
			'canal'    => (string) $order->get_meta( '_uep_canal' ),
		);

		return $response;
	}
}
