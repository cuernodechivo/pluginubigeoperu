<?php
/**
 * Registro del método de envío basado en el ubigeo elegido.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UEP_Shipping {

	public static function init() {
		add_action( 'woocommerce_shipping_init', array( __CLASS__, 'cargar_metodo' ) );
		add_filter( 'woocommerce_shipping_methods', array( __CLASS__, 'registrar_metodo' ) );

		// Incluye el ubigeo elegido dentro del paquete de envío: así el hash del
		// paquete cambia al cambiar de distrito y WooCommerce recalcula la tarifa
		// en vez de servir la que tenía en caché.
		add_filter( 'woocommerce_cart_shipping_packages', array( __CLASS__, 'inyectar_ubigeo_en_paquetes' ) );

		// Refuerzo: al refrescar el checkout, invalida el caché de tarifas.
		add_action( 'woocommerce_checkout_update_order_review', array( __CLASS__, 'refrescar_envio' ) );

		// Si la "ciudad" del cliente está vacía (la ocultamos para Perú), se usa el
		// nombre del distrito elegido. Con esto la opción de WooCommerce "ocultar
		// costos de envío hasta que se introduzca una dirección" no bloquea el envío.
		add_filter( 'woocommerce_customer_get_billing_city', array( __CLASS__, 'ciudad_desde_ubigeo' ), 10, 2 );
		add_filter( 'woocommerce_customer_get_shipping_city', array( __CLASS__, 'ciudad_desde_ubigeo' ), 10, 2 );

		// Métodos de pago restringidos a las zonas de cobertura (ej. contra
		// entrega solo en Lima Metropolitana y Callao).
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'filtrar_pagos_por_zona' ) );
	}

	/**
	 * Oculta los métodos de pago marcados como "solo zona de cobertura" cuando
	 * el cliente eligió un ubigeo fuera de ella. Se aplica tanto en el refresco
	 * del checkout como en el envío final del pedido.
	 *
	 * Si envía a una dirección diferente, se revisan las dos direcciones: con
	 * que una quede fuera de la cobertura, esos métodos se ocultan.
	 *
	 * @param array $gateways Pasarelas disponibles.
	 * @return array
	 */
	public static function filtrar_pagos_por_zona( $gateways ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $gateways;
		}

		$elegidos = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods', array() ) : array();
		$gestor   = uep_es_gestor();

		list( $id_depa, $id_prov, ) = self::ubigeo_actual();
		$gateways = self::pagos_permitidos( $gateways, $id_depa, $id_prov, $elegidos, $gestor );

		// Envío a otra dirección: la facturación también debe estar en la
		// cobertura, y mientras no se elija cuenta como fuera de ella.
		$datos = self::datos_checkout();

		if ( self::envia_a_otra_direccion( $datos ) ) {
			list( $id_depa, $id_prov, ) = self::ubigeo_de( $datos, 'billing' );
			$gateways = self::pagos_permitidos( $gateways, $id_depa, $id_prov, $elegidos, $gestor, true );
		}

		return $gateways;
	}

	/**
	 * Reglas de métodos de pago, sin depender de la sesión: se usan tanto en
	 * el checkout como en el simulador del panel.
	 *
	 * @param array $gateways  Pasarelas (id => objeto).
	 * @param int   $id_depa   Departamento del cliente (0 = aún no elegido).
	 * @param int   $id_prov   Provincia del cliente.
	 * @param array $elegidos  IDs de las tarifas de envío elegidas.
	 * @param bool  $es_gestor ¿Compra un administrador o gestor de la tienda?
	 * @param bool  $exigir    Sin departamento elegido, ¿ocultar igual los
	 *                         métodos restringidos?
	 * @return array
	 */
	public static function pagos_permitidos( $gateways, $id_depa, $id_prov, $elegidos, $es_gestor, $exigir = false ) {
		$settings = uep_get_settings();
		$elegidos = (array) $elegidos;

		// 0) Métodos de pago internos (ej. link de pago): solo los ven los
		//    administradores y gestores de la tienda, en cualquier situación.
		$solo_admin = (array) $settings['pago_solo_admin'];

		if ( $solo_admin && ! $es_gestor ) {
			foreach ( $solo_admin as $gateway_id ) {
				unset( $gateways[ $gateway_id ] );
			}
		}

		// 1) Recojo en Tienda elegido: si hay métodos marcados para el recojo,
		//    solo esos se aceptan.
		$pago_recojo = (array) $settings['pago_recojo'];

		if ( $pago_recojo && in_array( 'uep_envio_peru_pickup', $elegidos, true ) ) {
			foreach ( array_keys( $gateways ) as $gateway_id ) {
				if ( ! in_array( $gateway_id, $pago_recojo, true ) ) {
					unset( $gateways[ $gateway_id ] );
				}
			}

			return $gateways;
		}

		// 2) Métodos restringidos a las zonas de cobertura (ej. contra entrega).
		$restringidos = (array) $settings['pago_solo_cobertura'];

		if ( 'yes' !== $settings['cobertura_enabled'] || ! $restringidos ) {
			return $gateways;
		}

		// Sin ubigeo elegido aún no se puede decidir: no ocultar nada, salvo
		// que se exija.
		if ( ! $id_depa && ! $exigir ) {
			return $gateways;
		}

		if ( $id_depa && uep_zona_coincide( $settings['cobertura_zonas'], $id_depa, $id_prov ) ) {
			return $gateways;
		}

		foreach ( $restringidos as $gateway_id ) {
			unset( $gateways[ $gateway_id ] );
		}

		return $gateways;
	}

	/**
	 * Devuelve el nombre del distrito elegido cuando la ciudad está vacía.
	 *
	 * @param string      $value    Valor actual de la ciudad.
	 * @param WC_Customer $customer Cliente.
	 * @return string
	 */
	public static function ciudad_desde_ubigeo( $value, $customer ) {
		static $cache = array();

		if ( '' !== (string) $value ) {
			return $value;
		}

		$ubigeo  = self::ubigeo_actual();
		$id_dist = $ubigeo[2];

		if ( ! $id_dist ) {
			return $value;
		}

		if ( ! isset( $cache[ $id_dist ] ) ) {
			$nombres            = uep_get_nombres_ubigeo( 0, 0, $id_dist );
			$cache[ $id_dist ] = $nombres['distrito'];
		}

		return '' !== $cache[ $id_dist ] ? $cache[ $id_dist ] : $value;
	}

	public static function cargar_metodo() {
		require_once UEP_PLUGIN_DIR . 'includes/class-uep-metodo-envio.php';
	}

	public static function registrar_metodo( $methods ) {
		$methods['uep_envio_peru'] = 'UEP_Metodo_Envio';
		return $methods;
	}

	public static function inyectar_ubigeo_en_paquetes( $packages ) {
		$ubigeo = self::ubigeo_actual();
		$gestor = uep_es_gestor() ? 1 : 0;

		foreach ( $packages as $key => $package ) {
			$packages[ $key ]['uep_ubigeo'] = $ubigeo;
			// Diferencia el hash del paquete: las tarifas cacheadas de un gestor
			// (con métodos internos) nunca se reutilizan para un cliente.
			$packages[ $key ]['uep_gestor'] = $gestor;
		}

		return $packages;
	}

	/**
	 * Ubigeo elegido por el cliente: del refresco del checkout (post_data),
	 * del envío final del pedido ($_POST directo) o de la sesión de WooCommerce.
	 * Cuando lo encuentra en la petición, lo persiste en la sesión.
	 *
	 * @return array [idDepa, idProv, idDist]
	 */
	public static function ubigeo_actual() {
		$datos = self::datos_checkout();

		if ( $datos ) {
			$ubigeo = self::ubigeo_de( $datos, self::envia_a_otra_direccion( $datos ) ? 'shipping' : 'billing' );

			if ( $ubigeo[0] && WC()->session ) {
				WC()->session->set( 'uep_ubigeo', $ubigeo );
			}

			return $ubigeo;
		}

		// Página del carrito u otros contextos: usa lo último guardado.
		if ( WC()->session ) {
			$guardado = WC()->session->get( 'uep_ubigeo' );
			if ( is_array( $guardado ) && count( $guardado ) === 3 ) {
				return array_map( 'absint', $guardado );
			}
		}

		return array( 0, 0, 0 );
	}

	/**
	 * Campos del formulario de checkout en la petición actual: del refresco
	 * AJAX (post_data) o del envío final del pedido. Vacío fuera del checkout.
	 *
	 * @return array
	 */
	private static function datos_checkout() {
		$datos = array();

		// Refresco AJAX del checkout.
		if ( isset( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			parse_str( wp_unslash( $_POST['post_data'] ), $datos ); // phpcs:ignore
		} elseif ( isset( $_POST['billing_departamento'] ) || isset( $_POST['shipping_departamento'] ) ) {
			// Envío final del pedido (place order).
			$datos = wp_unslash( $_POST ); // phpcs:ignore
		}

		return (array) $datos;
	}

	/**
	 * ¿Marcó "Enviar a una dirección diferente" y eligió el departamento de envío?
	 *
	 * @param array $datos Campos del checkout.
	 * @return bool
	 */
	private static function envia_a_otra_direccion( $datos ) {
		return ! empty( $datos['ship_to_different_address'] ) && ! empty( $datos['shipping_departamento'] );
	}

	/**
	 * Ubigeo de la dirección de facturación o de envío.
	 *
	 * @param array  $datos   Campos del checkout.
	 * @param string $prefijo 'billing' o 'shipping'.
	 * @return array [idDepa, idProv, idDist]
	 */
	private static function ubigeo_de( $datos, $prefijo ) {
		return array(
			absint( $datos[ $prefijo . '_departamento' ] ?? 0 ),
			absint( $datos[ $prefijo . '_provincia' ] ?? 0 ),
			absint( $datos[ $prefijo . '_distrito' ] ?? 0 ),
		);
	}

	/**
	 * Invalida el caché de tarifas cuando cambia algo en el checkout,
	 * para que el costo se recalcule con el nuevo distrito.
	 */
	public static function refrescar_envio( $post_data ) {
		if ( ! WC()->cart ) {
			return;
		}

		$packages = WC()->cart->get_shipping_packages();

		foreach ( array_keys( $packages ) as $key ) {
			WC()->session->set( 'shipping_for_package_' . $key, false );
		}
	}
}
