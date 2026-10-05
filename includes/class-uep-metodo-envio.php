<?php
/**
 * Método de envío "Envío Perú (ubigeo)".
 *
 * Este archivo se carga en woocommerce_shipping_init, cuando WC_Shipping_Method ya existe.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UEP_Metodo_Envio extends WC_Shipping_Method {

	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'uep_envio_peru';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Envío Perú (ubigeo)', 'ubigeo-envio-peru' );
		$this->method_description = __( 'Calcula el costo de envío según el departamento, provincia y distrito elegidos. Se configura en WooCommerce → Envío Perú.', 'ubigeo-envio-peru' );
		$this->enabled            = 'yes';
		$this->title              = uep_get_settings()['method_title'];
	}

	public function is_available( $package ) {
		return isset( $package['destination']['country'] ) && 'PE' === $package['destination']['country'];
	}

	public function calculate_shipping( $package = array() ) {
		$settings = uep_get_settings();

		// El ubigeo viaja dentro del paquete (ver UEP_Shipping::inyectar_ubigeo_en_paquetes);
		// si no está, se resuelve desde la petición o la sesión.
		if ( ! empty( $package['uep_ubigeo'] ) && is_array( $package['uep_ubigeo'] ) ) {
			list( $id_depa, $id_prov, $id_dist ) = array_map( 'absint', $package['uep_ubigeo'] );
		} else {
			list( $id_depa, $id_prov, $id_dist ) = UEP_Shipping::ubigeo_actual();
		}

		// Zonas de cobertura del envío a domicilio (ej. Lima/Lima + Callao):
		// fuera de ellas solo se ofrece el envío por agencia.
		$en_cobertura = true;

		if ( 'yes' === $settings['cobertura_enabled'] && $id_depa ) {
			$en_cobertura = uep_zona_coincide( $settings['cobertura_zonas'], $id_depa, $id_prov );
		}

		if ( ! $en_cobertura ) {
			// Fuera de cobertura la única opción es el envío por agencia
			// (sin recojo en tienda).
			$this->add_rate(
				array(
					'id'      => $this->id . '_agencia',
					'label'   => $settings['agencia_title'],
					'cost'    => 0,
					'package' => $package,
				)
			);

			$this->agregar_metodos_admin( $package );

			return;
		}

		// Resuelve el costo: regla de distrito > provincia > departamento > costo por defecto.
		$costo  = (float) $settings['default_cost'];
		$tarifa = uep_resolver_tarifa( $id_depa, $id_prov, $id_dist );

		if ( $tarifa ) {
			$costo = (float) $tarifa['costo'];
		}

		// Las tarifas están definidas en soles: convertir a la moneda base
		// (ej. USD) para que WooCommerce y el plugin de multimoneda operen bien.
		$costo = uep_tarifa_a_base( $costo );

		$label = $settings['method_title'];

		// Envío gratis por monto mínimo (solo aplica al envío normal). El umbral
		// (en soles) se convierte a moneda base y luego a la moneda que el
		// cliente está viendo, para compararlo contra el total del carrito.
		$free_over = uep_convertir_monto_a_moneda_actual( uep_tarifa_a_base( (float) $settings['free_over'] ) );
		if ( $free_over > 0 && WC()->cart && $this->total_carrito() >= $free_over ) {
			$costo = 0;
			$label = __( 'Envío gratis', 'ubigeo-envio-peru' );
		}

		// Cupones de envío gratis de WooCommerce (solo el envío normal).
		if ( $costo > 0 && WC()->cart ) {
			foreach ( WC()->cart->get_applied_coupons() as $codigo ) {
				$cupon = new WC_Coupon( $codigo );
				if ( $cupon->get_free_shipping() ) {
					$costo = 0;
					$label = __( 'Envío gratis', 'ubigeo-envio-peru' );
					break;
				}
			}
		}

		$this->add_rate(
			array(
				'id'      => $this->id,
				'label'   => $label,
				'cost'    => $costo,
				'package' => $package,
			)
		);

		// Envío rápido (Flash): segunda opción con costo mayor. Usa el costo Flash
		// de la zona si está definido; si no, el costo Flash por defecto.
		// Con hora límite configurada, pasada esa hora el Flash sigue visible
		// pero con el aviso de que el mismo día aplica a compras antes de la hora.
		if ( 'yes' === $settings['express_enabled'] ) {
			$costo_express = null;

			if ( $tarifa && isset( $tarifa['costo_express'] ) && '' !== (string) $tarifa['costo_express'] && null !== $tarifa['costo_express'] ) {
				$costo_express = (float) $tarifa['costo_express'];
			} elseif ( '' !== (string) $settings['express_default_cost'] ) {
				$costo_express = (float) $settings['express_default_cost'];
			}

			if ( null !== $costo_express ) {
				$label_express = $settings['express_title'];
				$limite        = (string) $settings['express_cutoff'];

				// Pasada la hora límite, el aviso aclara la condición del mismo día.
				if ( preg_match( '/^\d{2}:\d{2}$/', $limite ) && ! $this->flash_disponible( $settings ) ) {
					$label_express .= ' — ' . sprintf(
						/* translators: %s: hora límite */
						__( 'se envía el mismo día solo en compras antes de las %s', 'ubigeo-envio-peru' ),
						date_i18n( 'g:i a', strtotime( '1970-01-01 ' . $limite ) )
					);
				}

				$this->add_rate(
					array(
						'id'      => $this->id . '_express',
						'label'   => $label_express,
						'cost'    => uep_tarifa_a_base( $costo_express ),
						'package' => $package,
					)
				);
			}
		}

		// Recojo en Tienda: gratis, el cliente elige fecha y hora (ver UEP_Pickup).
		// Puede limitarse a su propia zona (ej. solo provincia de Lima), aparte
		// de la cobertura del envío a domicilio.
		if ( 'yes' === $settings['pickup_enabled'] && $this->recojo_disponible( $id_depa, $id_prov, $settings ) ) {
			$this->add_rate(
				array(
					'id'      => $this->id . '_pickup',
					'label'   => $settings['pickup_title'],
					'cost'    => 0,
					'package' => $package,
				)
			);
		}

		$this->agregar_metodos_admin( $package );
	}

	/**
	 * Métodos internos (ej. Flex y Urbano de Mercado Libre) que solo ven los
	 * administradores y gestores de la tienda al registrar pedidos a mano.
	 * Se ofrecen en cualquier zona: un pedido de Mercado Libre puede ir a
	 * Lima o a provincia.
	 *
	 * @param array $package Paquete de envío.
	 */
	private function agregar_metodos_admin( $package ) {
		if ( ! uep_es_gestor() ) {
			return;
		}

		foreach ( uep_metodos_admin() as $indice => $metodo ) {
			$this->add_rate(
				array(
					'id'      => $this->id . '_admin_' . $indice,
					'label'   => $metodo['nombre'],
					'cost'    => uep_tarifa_a_base( $metodo['costo'] ),
					'package' => $package,
				)
			);
		}
	}

	/**
	 * ¿El Flash sigue disponible a esta hora? (hora límite configurable,
	 * en la zona horaria del sitio).
	 *
	 * @param array $settings Ajustes del plugin.
	 * @return bool
	 */
	private function flash_disponible( $settings ) {
		$limite = (string) $settings['express_cutoff'];

		if ( '' === $limite || ! preg_match( '/^\d{2}:\d{2}$/', $limite ) ) {
			return true;
		}

		$ahora = new DateTime( 'now', wp_timezone() );

		return $ahora->format( 'H:i' ) <= $limite;
	}

	/**
	 * ¿El recojo en tienda está disponible para el ubigeo elegido?
	 *
	 * @param int   $id_depa  Departamento elegido.
	 * @param int   $id_prov  Provincia elegida.
	 * @param array $settings Ajustes del plugin.
	 * @return bool
	 */
	private function recojo_disponible( $id_depa, $id_prov, $settings ) {
		if ( 'yes' !== $settings['pickup_zone_enabled'] ) {
			return true;
		}

		if ( ! $id_depa ) {
			return false;
		}

		return uep_zona_coincide( $settings['pickup_zonas'], $id_depa, $id_prov );
	}

	/**
	 * Total del carrito (productos, con impuestos si aplican).
	 */
	private function total_carrito() {
		$cart = WC()->cart;

		if ( $cart->display_prices_including_tax() ) {
			return (float) $cart->get_cart_contents_total() + (float) $cart->get_cart_contents_tax();
		}

		return (float) $cart->get_cart_contents_total();
	}

}
