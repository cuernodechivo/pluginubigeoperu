<?php
/**
 * Recojo en Tienda: fecha y hora de recojo en el checkout.
 *
 * La opción aparece como método de envío gratuito (ver UEP_Metodo_Envio).
 * Cuando el cliente la elige, se muestra un calendario (fecha mínima:
 * N días después de la compra) y un selector de hora dentro del horario
 * de atención configurado.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UEP_Pickup {

	const RATE_ID = 'uep_envio_peru_pickup';

	public static function init() {
		$settings = uep_get_settings();

		if ( 'yes' !== $settings['pickup_enabled'] ) {
			return;
		}

		// Sección de fecha/hora dentro del resumen del pedido (se refresca con el checkout).
		add_action( 'woocommerce_review_order_after_shipping', array( __CLASS__, 'seccion_recojo' ) );

		// Validación y guardado al finalizar la compra.
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validar' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'guardar_en_pedido' ), 10, 2 );

		// Mostrar la fecha elegida: gracias por tu compra / mi cuenta, emails y admin.
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'mostrar_en_pedido' ) );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( __CLASS__, 'mostrar_en_admin' ) );

		// Aviso "listo para recoger" desde la pantalla del pedido.
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'guardar_admin' ), 60 );
	}

	/**
	 * ¿El cliente tiene elegido el recojo en tienda?
	 */
	public static function elegido() {
		if ( ! WC()->session ) {
			return false;
		}

		$elegidos = (array) WC()->session->get( 'chosen_shipping_methods', array() );

		return in_array( self::RATE_ID, $elegidos, true );
	}

	/**
	 * ¿El usuario actual puede elegir cualquier fecha y hora de recojo?
	 * Los administradores y gestores de la tienda generan pedidos por teléfono
	 * o WhatsApp y necesitan coordinar fechas fuera de las reglas normales
	 * (mismo día, domingos, feriados). El cliente final sigue la configuración.
	 */
	public static function sin_restricciones() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Fecha mínima de recojo (zona horaria del sitio).
	 *
	 * @return string Y-m-d
	 */
	public static function fecha_minima() {
		$settings = uep_get_settings();
		$dias     = max( 0, absint( $settings['pickup_min_days'] ) );

		$fecha = new DateTime( 'now', wp_timezone() );
		$fecha->modify( '+' . $dias . ' days' );

		return $fecha->format( 'Y-m-d' );
	}

	/**
	 * Días de la semana sin atención (0 = domingo … 6 = sábado).
	 *
	 * @return int[]
	 */
	public static function dias_cerrados() {
		$dias = uep_get_settings()['pickup_closed_days'];

		return array_map( 'absint', is_array( $dias ) ? $dias : array() );
	}

	/**
	 * Feriados (fechas exactas sin atención).
	 *
	 * @return string[] Fechas Y-m-d.
	 */
	public static function feriados() {
		$crudo = (string) uep_get_settings()['pickup_holidays'];

		$fechas = preg_split( '/[\s,;]+/', $crudo, -1, PREG_SPLIT_NO_EMPTY );
		$fechas = array_filter( (array) $fechas, function ( $f ) {
			return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $f );
		} );

		return array_values( $fechas );
	}

	/**
	 * ¿La fecha está disponible para recojo? (día de atención y no feriado).
	 *
	 * @param string $fecha Fecha Y-m-d.
	 * @return bool
	 */
	public static function fecha_disponible( $fecha ) {
		$dia_semana = (int) gmdate( 'w', strtotime( $fecha . ' 12:00:00' ) );

		if ( in_array( $dia_semana, self::dias_cerrados(), true ) ) {
			return false;
		}

		return ! in_array( $fecha, self::feriados(), true );
	}

	/**
	 * Horarios disponibles cada 30 minutos dentro del horario de atención.
	 *
	 * @return string[] Horas en formato H:i.
	 */
	public static function horarios() {
		$settings = uep_get_settings();

		$inicio = strtotime( '1970-01-01 ' . $settings['pickup_start'] );
		$fin    = strtotime( '1970-01-01 ' . $settings['pickup_end'] );

		if ( false === $inicio || false === $fin || $inicio >= $fin ) {
			$inicio = strtotime( '1970-01-01 10:00' );
			$fin    = strtotime( '1970-01-01 18:00' );
		}

		$slots = array();
		for ( $t = $inicio; $t <= $fin; $t += 30 * MINUTE_IN_SECONDS ) {
			$slots[] = gmdate( 'H:i', $t );
		}

		return $slots;
	}

	/**
	 * Valores de fecha/hora enviados en el refresco del checkout, para no
	 * perderlos cuando WooCommerce re-renderiza el resumen del pedido.
	 */
	private static function valores_actuales() {
		$fecha = '';
		$hora  = '';

		if ( isset( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			parse_str( wp_unslash( $_POST['post_data'] ), $datos ); // phpcs:ignore
			$fecha = isset( $datos['uep_pickup_fecha'] ) ? sanitize_text_field( $datos['uep_pickup_fecha'] ) : '';
			$hora  = isset( $datos['uep_pickup_hora'] ) ? sanitize_text_field( $datos['uep_pickup_hora'] ) : '';
		}

		return array( $fecha, $hora );
	}

	/**
	 * Fila con el calendario y la hora, visible solo si el recojo está elegido.
	 */
	public static function seccion_recojo() {
		if ( ! self::elegido() ) {
			return;
		}

		$settings = uep_get_settings();
		$minima   = self::fecha_minima();
		$libre    = self::sin_restricciones();
		list( $fecha, $hora ) = self::valores_actuales();
		?>
		<tr class="uep-pickup-fila">
			<td colspan="2">
				<div class="uep-pickup-caja">
					<strong><?php esc_html_e( '¿Cuándo recogerás tu pedido?', 'ubigeo-envio-peru' ); ?></strong>
					<p class="uep-pickup-aviso">
						<?php
						if ( $libre ) {
							esc_html_e( 'Como gestor de la tienda puedes elegir cualquier fecha y hora.', 'ubigeo-envio-peru' );
						} else {
							printf(
								/* translators: %s: fecha mínima */
								esc_html__( 'Disponible a partir del %s.', 'ubigeo-envio-peru' ),
								esc_html( date_i18n( 'j \d\e F', strtotime( $minima . ' 12:00:00' ) ) )
							);
						}
						?>
					</p>

					<p class="form-row">
						<label for="uep_pickup_fecha"><?php esc_html_e( 'Fecha de recojo', 'ubigeo-envio-peru' ); ?>&nbsp;<abbr class="required" title="obligatorio">*</abbr></label>
						<input type="date" id="uep_pickup_fecha" name="uep_pickup_fecha"
							   <?php echo $libre ? '' : 'min="' . esc_attr( $minima ) . '"'; ?>
							   value="<?php echo esc_attr( $fecha ); ?>">
					</p>

					<p class="form-row">
						<label for="uep_pickup_hora"><?php esc_html_e( 'Hora de recojo', 'ubigeo-envio-peru' ); ?>&nbsp;<abbr class="required" title="obligatorio">*</abbr></label>
						<?php if ( $libre ) : ?>
							<input type="time" id="uep_pickup_hora" name="uep_pickup_hora" value="<?php echo esc_attr( $hora ); ?>">
						<?php else : ?>
							<select id="uep_pickup_hora" name="uep_pickup_hora">
								<option value=""><?php esc_html_e( 'Elige una hora…', 'ubigeo-envio-peru' ); ?></option>
								<?php foreach ( self::horarios() as $slot ) : ?>
									<option value="<?php echo esc_attr( $slot ); ?>" <?php selected( $hora, $slot ); ?>><?php echo esc_html( $slot ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
					</p>

					<?php if ( '' !== trim( $settings['pickup_note'] ) ) : ?>
						<p class="uep-pickup-nota"><?php echo esc_html( $settings['pickup_note'] ); ?></p>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Valida fecha y hora al finalizar la compra.
	 *
	 * @param array    $data   Datos del checkout.
	 * @param WP_Error $errors Errores.
	 */
	public static function validar( $data, $errors ) {
		if ( ! self::elegido() ) {
			return;
		}

		$fecha = isset( $_POST['uep_pickup_fecha'] ) ? sanitize_text_field( wp_unslash( $_POST['uep_pickup_fecha'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$hora  = isset( $_POST['uep_pickup_hora'] ) ? sanitize_text_field( wp_unslash( $_POST['uep_pickup_hora'] ) ) : '';  // phpcs:ignore WordPress.Security.NonceVerification

		$valida = (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $fecha );

		if ( ! $valida || ! $fecha ) {
			$errors->add( 'uep_pickup', __( 'Elige la fecha de recojo en tienda.', 'ubigeo-envio-peru' ) );
			return;
		}

		// Gestores de la tienda: cualquier fecha y hora válidas (coordinan
		// pedidos por teléfono o WhatsApp). El cliente final sigue las reglas.
		if ( self::sin_restricciones() ) {
			if ( ! preg_match( '/^\d{2}:\d{2}$/', $hora ) ) {
				$errors->add( 'uep_pickup', __( 'Indica la hora de recojo en tienda.', 'ubigeo-envio-peru' ) );
			}
			return;
		}

		if ( $fecha < self::fecha_minima() ) {
			$errors->add(
				'uep_pickup',
				sprintf(
					/* translators: %d: días mínimos */
					__( 'El recojo en tienda está disponible desde %d día(s) después de la compra. Elige una fecha válida.', 'ubigeo-envio-peru' ),
					absint( uep_get_settings()['pickup_min_days'] )
				)
			);
		}

		if ( ! self::fecha_disponible( $fecha ) ) {
			$errors->add(
				'uep_pickup',
				__( 'La tienda no atiende en la fecha elegida para el recojo. Elige otro día.', 'ubigeo-envio-peru' )
			);
		}

		if ( ! in_array( $hora, self::horarios(), true ) ) {
			$errors->add( 'uep_pickup', __( 'Elige la hora de recojo en tienda.', 'ubigeo-envio-peru' ) );
		}
	}

	/**
	 * Guarda la fecha y hora en el pedido.
	 *
	 * @param WC_Order $order Pedido.
	 * @param array    $data  Datos del checkout.
	 */
	public static function guardar_en_pedido( $order, $data ) {
		if ( ! self::elegido() ) {
			return;
		}

		$fecha = isset( $_POST['uep_pickup_fecha'] ) ? sanitize_text_field( wp_unslash( $_POST['uep_pickup_fecha'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$hora  = isset( $_POST['uep_pickup_hora'] ) ? sanitize_text_field( wp_unslash( $_POST['uep_pickup_hora'] ) ) : '';  // phpcs:ignore WordPress.Security.NonceVerification

		if ( $fecha ) {
			$order->update_meta_data( '_uep_pickup_fecha', $fecha );
		}
		if ( $hora ) {
			$order->update_meta_data( '_uep_pickup_hora', $hora );
		}
	}

	/**
	 * Texto legible de la fecha de recojo de un pedido, o '' si no aplica.
	 *
	 * @param WC_Order $order Pedido.
	 * @return string
	 */
	public static function texto_recojo( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return '';
		}

		$fecha = $order->get_meta( '_uep_pickup_fecha' );
		$hora  = $order->get_meta( '_uep_pickup_hora' );

		if ( ! $fecha ) {
			return '';
		}

		$texto = date_i18n( 'l j \d\e F \d\e Y', strtotime( $fecha . ' 12:00:00' ) );

		if ( $hora ) {
			$texto .= ' — ' . $hora;
		}

		return $texto;
	}

	/**
	 * Bloque en "gracias por tu compra", "mi cuenta" y emails.
	 *
	 * @param WC_Order $order Pedido.
	 */
	public static function mostrar_en_pedido( $order ) {
		$texto = self::texto_recojo( $order );

		if ( ! $texto ) {
			return;
		}

		$settings = uep_get_settings();

		echo '<section class="uep-pickup-detalle">';
		echo '<h2 style="font-size:1.2em;margin:16px 0 8px;">' . esc_html__( 'Recojo en Tienda', 'ubigeo-envio-peru' ) . '</h2>';
		echo '<p><strong>' . esc_html__( 'Fecha y hora de recojo:', 'ubigeo-envio-peru' ) . '</strong> ' . esc_html( $texto ) . '</p>';

		if ( '' !== trim( $settings['pickup_note'] ) ) {
			echo '<p>' . esc_html( $settings['pickup_note'] ) . '</p>';
		}

		echo '</section>';
	}

	/**
	 * Bloque en la pantalla de pedido del administrador, con aviso de
	 * "listo para recoger" por email y WhatsApp.
	 *
	 * @param WC_Order $order Pedido.
	 */
	public static function mostrar_en_admin( $order ) {
		$texto = self::texto_recojo( $order );

		if ( ! $texto ) {
			return;
		}

		$avisado = $order->get_meta( '_uep_pickup_listo_avisado' );

		echo '<div class="uep-pickup-admin" style="clear:both;padding-top:10px;">';
		echo '<h3>' . esc_html__( 'Recojo en Tienda', 'ubigeo-envio-peru' ) . '</h3>';
		echo '<p>' . esc_html( $texto ) . '</p>';

		echo '<p><label>';
		echo '<input type="checkbox" name="uep_pickup_avisar_listo" value="yes"> ';
		echo esc_html__( 'Avisar por email que el pedido está listo para recoger (al actualizar)', 'ubigeo-envio-peru' );
		echo '</label></p>';

		if ( $avisado ) {
			echo '<p class="description">' . esc_html( sprintf( /* translators: %s: fecha */ __( 'Último aviso enviado: %s', 'ubigeo-envio-peru' ), $avisado ) ) . '</p>';
		}

		$settings   = uep_get_settings();
		$mensaje_wa = sprintf(
			/* translators: 1: nombre 2: pedido 3: fecha 4: nota */
			__( 'Hola %1$s, tu pedido #%2$s ya está listo para recoger en tienda. Recojo elegido: %3$s.%4$s ¡Te esperamos!', 'ubigeo-envio-peru' ),
			$order->get_billing_first_name(),
			$order->get_order_number(),
			$texto,
			'' !== trim( $settings['pickup_note'] ) ? ' ' . $settings['pickup_note'] : ''
		);

		$url_wa = uep_whatsapp_url( $order, $mensaje_wa );

		if ( $url_wa ) {
			echo '<p><a href="' . esc_url( $url_wa ) . '" target="_blank" class="button">';
			echo esc_html__( 'Avisar por WhatsApp', 'ubigeo-envio-peru' );
			echo '</a></p>';
		}

		echo '</div>';
	}

	/**
	 * Envía el aviso "listo para recoger" cuando se marcó la casilla al
	 * actualizar el pedido.
	 *
	 * @param int $order_id ID del pedido.
	 */
	public static function guardar_admin( $order_id ) {
		if ( ! isset( $_POST['uep_pickup_avisar_listo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order->get_meta( '_uep_pickup_fecha' ) ) {
			return;
		}

		if ( self::enviar_email_listo( $order ) ) {
			$order->update_meta_data( '_uep_pickup_listo_avisado', date_i18n( 'Y-m-d H:i' ) );
			$order->add_order_note( __( 'Se avisó al cliente que su pedido está listo para recoger en tienda.', 'ubigeo-envio-peru' ) );
			$order->save();
		}
	}

	/**
	 * Email al cliente: su pedido está listo para recoger.
	 *
	 * @param WC_Order $order Pedido.
	 * @return bool
	 */
	private static function enviar_email_listo( $order ) {
		$settings = uep_get_settings();
		$mailer   = WC()->mailer();

		$asunto = sprintf(
			/* translators: %s: número de pedido */
			__( 'Tu pedido #%s está listo para recoger', 'ubigeo-envio-peru' ),
			$order->get_order_number()
		);

		$titulo = __( '¡Tu pedido está listo!', 'ubigeo-envio-peru' );

		$cuerpo  = '<p>' . sprintf(
			/* translators: %s: nombre */
			esc_html__( 'Hola %s,', 'ubigeo-envio-peru' ),
			esc_html( $order->get_billing_first_name() )
		) . '</p>';

		$cuerpo .= '<p>' . sprintf(
			/* translators: %s: número de pedido */
			esc_html__( 'Tu pedido #%s ya está listo para que lo recojas en tienda.', 'ubigeo-envio-peru' ),
			esc_html( $order->get_order_number() )
		) . '</p>';

		$cuerpo .= '<p><strong>' . esc_html__( 'Fecha y hora de recojo elegida:', 'ubigeo-envio-peru' ) . '</strong> ' . esc_html( self::texto_recojo( $order ) ) . '</p>';

		if ( '' !== trim( $settings['pickup_note'] ) ) {
			$cuerpo .= '<p>' . esc_html( $settings['pickup_note'] ) . '</p>';
		}

		$mensaje = $mailer->wrap_message( $titulo, $cuerpo );

		return (bool) $mailer->send( $order->get_billing_email(), $asunto, $mensaje );
	}
}
