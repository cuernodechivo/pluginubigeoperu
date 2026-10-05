<?php
/**
 * Envío por Agencia (provincias fuera de la zona de cobertura).
 *
 * - El cliente elige la agencia (Shalom, Olva, etc.) y escribe la sede donde
 *   recogerá su pedido; todo queda guardado en el pedido.
 * - El administrador registra el número de guía en el pedido y el cliente
 *   recibe un email automático avisando que su paquete está en camino.
 * - El costo del envío lo cobra la agencia al recoger (S/ 0 en el checkout).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UEP_Agencia {

	const RATE_ID = 'uep_envio_peru_agencia';

	public static function init() {
		$settings = uep_get_settings();

		if ( 'yes' !== $settings['cobertura_enabled'] ) {
			return;
		}

		// Checkout: selector de agencia + sede (opcionales) y guardado.
		add_action( 'woocommerce_review_order_after_shipping', array( __CLASS__, 'aviso_checkout' ) );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'guardar_en_pedido' ), 10, 2 );

		// Visualización para el cliente.
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'mostrar_en_pedido' ) );

		// Panel del administrador: datos + número de guía editable.
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( __CLASS__, 'mostrar_en_admin' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'guardar_admin' ), 50 );
	}

	/**
	 * Agencias disponibles (una por línea en los ajustes).
	 *
	 * @return string[]
	 */
	public static function lista_agencias() {
		$crudo = (string) uep_get_settings()['agencia_list'];

		$lineas = array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', $crudo ) ), 'strlen' );

		return array_values( $lineas );
	}

	/**
	 * ¿El usuario actual puede ver/llenar los campos de agencia y sede en el
	 * checkout? Por ahora solo administradores y gestores de la tienda, que
	 * generan pedidos en nombre de los clientes. El cliente final solo ve la
	 * explicación del envío por agencia.
	 */
	public static function campos_para_admin() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * ¿El cliente tiene elegido el envío por agencia?
	 */
	public static function elegido() {
		if ( ! WC()->session ) {
			return false;
		}

		$elegidos = (array) WC()->session->get( 'chosen_shipping_methods', array() );

		return in_array( self::RATE_ID, $elegidos, true );
	}

	/**
	 * ¿El pedido se envió por agencia?
	 *
	 * Nota: WooCommerce guarda en el pedido el method_id del método padre
	 * (uep_envio_peru), no el ID de la tarifa, así que se usa un marcador
	 * propio guardado al crear el pedido.
	 *
	 * @param WC_Order $order Pedido.
	 * @return bool
	 */
	public static function pedido_por_agencia( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		if ( 'yes' === $order->get_meta( '_uep_envio_agencia' ) ) {
			return true;
		}

		// Compatibilidad con pedidos que guardaron los datos sin el marcador.
		return '' !== (string) $order->get_meta( '_uep_agencia_sede' )
			|| '' !== (string) $order->get_meta( '_uep_agencia_nombre' );
	}

	/**
	 * Valores enviados en el refresco del checkout (para no perderlos al re-renderizar).
	 */
	private static function valores_actuales() {
		$agencia = '';
		$otro    = '';
		$sede    = '';

		if ( isset( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			parse_str( wp_unslash( $_POST['post_data'] ), $datos ); // phpcs:ignore
			$agencia = isset( $datos['uep_agencia_nombre'] ) ? sanitize_text_field( $datos['uep_agencia_nombre'] ) : '';
			$otro    = isset( $datos['uep_agencia_otro'] ) ? sanitize_text_field( $datos['uep_agencia_otro'] ) : '';
			$sede    = isset( $datos['uep_agencia_sede'] ) ? sanitize_text_field( $datos['uep_agencia_sede'] ) : '';
		}

		return array( $agencia, $otro, $sede );
	}

	/**
	 * Nombre final de la agencia enviada en el checkout: la elegida de la
	 * lista, o la escrita a mano si se eligió "Otro".
	 *
	 * @return string
	 */
	private static function agencia_posteada() {
		$agencia = isset( $_POST['uep_agencia_nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['uep_agencia_nombre'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( '__otro' === $agencia ) {
			$agencia = isset( $_POST['uep_agencia_otro'] ) ? sanitize_text_field( wp_unslash( $_POST['uep_agencia_otro'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		}

		return trim( $agencia );
	}

	/**
	 * Caja bajo las opciones de envío: explicación + agencia + sede.
	 */
	public static function aviso_checkout() {
		if ( ! self::elegido() ) {
			return;
		}

		$settings = uep_get_settings();
		$agencias = self::lista_agencias();
		list( $agencia, $otro, $sede ) = self::valores_actuales();

		// Si escribieron una agencia con "Otro", mantener esa selección al re-renderizar.
		$es_otro = '__otro' === $agencia || ( '' !== $agencia && ! in_array( $agencia, $agencias, true ) );
		if ( $es_otro && '__otro' !== $agencia && '' === $otro ) {
			$otro = $agencia;
		}
		?>
		<tr class="uep-agencia-fila">
			<td colspan="2">
				<div class="uep-agencia-caja">
					<strong><?php esc_html_e( 'Envío por Agencia', 'ubigeo-envio-peru' ); ?></strong>
					<p>
						<?php esc_html_e( 'Dejaremos tu pedido en la agencia de transporte. El costo del envío lo define la agencia y lo pagas directamente al recoger tu producto.', 'ubigeo-envio-peru' ); ?>
					</p>

					<?php if ( self::campos_para_admin() ) : ?>

						<?php if ( $agencias ) : ?>
							<p class="form-row">
								<label for="uep_agencia_nombre"><?php esc_html_e( 'Agencia de tu preferencia (opcional)', 'ubigeo-envio-peru' ); ?></label>
								<select id="uep_agencia_nombre" name="uep_agencia_nombre">
									<option value=""><?php esc_html_e( 'Elige una agencia…', 'ubigeo-envio-peru' ); ?></option>
									<?php foreach ( $agencias as $nombre ) : ?>
										<option value="<?php echo esc_attr( $nombre ); ?>" <?php selected( $agencia, $nombre ); ?>><?php echo esc_html( $nombre ); ?></option>
									<?php endforeach; ?>
									<option value="__otro" <?php selected( $es_otro ); ?>><?php esc_html_e( 'Otro (escribir)', 'ubigeo-envio-peru' ); ?></option>
								</select>
							</p>

							<p class="form-row" id="uep_agencia_otro_wrap" <?php echo $es_otro ? '' : 'style="display:none;"'; ?>>
								<label for="uep_agencia_otro"><?php esc_html_e( 'Nombre de la agencia', 'ubigeo-envio-peru' ); ?></label>
								<input type="text" id="uep_agencia_otro" name="uep_agencia_otro"
									   value="<?php echo esc_attr( $otro ); ?>"
									   placeholder="<?php esc_attr_e( 'Ej.: Marvisur, Cruz del Sur Cargo…', 'ubigeo-envio-peru' ); ?>">
							</p>
						<?php endif; ?>

						<p class="form-row">
							<label for="uep_agencia_sede"><?php esc_html_e( 'Sede o dirección de la agencia donde recogerás (opcional)', 'ubigeo-envio-peru' ); ?></label>
							<input type="text" id="uep_agencia_sede" name="uep_agencia_sede"
								   value="<?php echo esc_attr( $sede ); ?>"
								   placeholder="<?php esc_attr_e( 'Ej.: Sede Cusco — Av. La Cultura 750', 'ubigeo-envio-peru' ); ?>">
						</p>

					<?php endif; ?>

					<?php if ( '' !== trim( $settings['agencia_note'] ) ) : ?>
						<p class="uep-agencia-nota"><?php echo esc_html( $settings['agencia_note'] ); ?></p>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Valida agencia y sede al finalizar la compra.
	 *
	 * @param array    $data   Datos del checkout.
	 * @param WP_Error $errors Errores.
	 */
	public static function validar( $data, $errors ) {
		if ( ! self::elegido() ) {
			return;
		}

		// Los campos solo se muestran (y por lo tanto solo se exigen) a los
		// administradores; el cliente final finaliza sin elegir agencia.
		if ( ! self::campos_para_admin() ) {
			return;
		}

		$agencias = self::lista_agencias();
		$agencia  = self::agencia_posteada();
		$sede     = isset( $_POST['uep_agencia_sede'] ) ? sanitize_text_field( wp_unslash( $_POST['uep_agencia_sede'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( $agencias && mb_strlen( $agencia ) < 2 ) {
			$errors->add( 'uep_agencia', __( 'Elige la agencia donde recogerás tu pedido (o escríbela en "Otro").', 'ubigeo-envio-peru' ) );
		}

		if ( strlen( trim( $sede ) ) < 3 ) {
			$errors->add( 'uep_agencia', __( 'Indica la sede o dirección de la agencia donde recogerás tu pedido.', 'ubigeo-envio-peru' ) );
		}
	}

	/**
	 * Guarda agencia y sede en el pedido.
	 *
	 * @param WC_Order $order Pedido.
	 * @param array    $data  Datos del checkout.
	 */
	public static function guardar_en_pedido( $order, $data ) {
		if ( ! self::elegido() ) {
			return;
		}

		$agencia = self::agencia_posteada();
		$sede    = isset( $_POST['uep_agencia_sede'] ) ? sanitize_text_field( wp_unslash( $_POST['uep_agencia_sede'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		$order->update_meta_data( '_uep_envio_agencia', 'yes' );

		if ( $agencia ) {
			$order->update_meta_data( '_uep_agencia_nombre', $agencia );
		}
		if ( $sede ) {
			$order->update_meta_data( '_uep_agencia_sede', $sede );
		}
	}

	/**
	 * Bloque en "gracias por tu compra", "mi cuenta" y emails.
	 *
	 * @param WC_Order $order Pedido.
	 */
	public static function mostrar_en_pedido( $order ) {
		if ( ! self::pedido_por_agencia( $order ) ) {
			return;
		}

		$settings = uep_get_settings();
		$agencia  = $order->get_meta( '_uep_agencia_nombre' );
		$sede     = $order->get_meta( '_uep_agencia_sede' );
		$guia     = $order->get_meta( '_uep_agencia_guia' );

		echo '<section class="uep-agencia-detalle">';
		echo '<h2 style="font-size:1.2em;margin:16px 0 8px;">' . esc_html__( 'Envío por Agencia', 'ubigeo-envio-peru' ) . '</h2>';

		if ( $agencia ) {
			echo '<p><strong>' . esc_html__( 'Agencia:', 'ubigeo-envio-peru' ) . '</strong> ' . esc_html( $agencia ) . '</p>';
		}
		if ( $sede ) {
			echo '<p><strong>' . esc_html__( 'Sede de recojo:', 'ubigeo-envio-peru' ) . '</strong> ' . esc_html( $sede ) . '</p>';
		}
		if ( $guia ) {
			echo '<p><strong>' . esc_html__( 'Número de guía:', 'ubigeo-envio-peru' ) . '</strong> ' . esc_html( $guia ) . '</p>';
		}

		echo '<p>' . esc_html__( 'El costo del envío lo cobra la agencia cuando recojas tu producto.', 'ubigeo-envio-peru' ) . '</p>';

		if ( '' !== trim( $settings['agencia_note'] ) ) {
			echo '<p>' . esc_html( $settings['agencia_note'] ) . '</p>';
		}

		echo '</section>';
	}

	/**
	 * Panel en la pantalla de pedido del administrador: datos elegidos por el
	 * cliente + número de guía editable con aviso automático.
	 *
	 * @param WC_Order $order Pedido.
	 */
	public static function mostrar_en_admin( $order ) {
		if ( ! self::pedido_por_agencia( $order ) ) {
			return;
		}

		$agencia    = (string) $order->get_meta( '_uep_agencia_nombre' );
		$sede       = (string) $order->get_meta( '_uep_agencia_sede' );
		$guia       = $order->get_meta( '_uep_agencia_guia' );
		$notificado = $order->get_meta( '_uep_agencia_guia_notificada' );
		$agencias   = self::lista_agencias();

		// La agencia guardada siempre debe aparecer aunque ya no esté en la lista.
		if ( '' !== $agencia && ! in_array( $agencia, $agencias, true ) ) {
			array_unshift( $agencias, $agencia );
		}

		echo '<div class="uep-agencia-admin" style="clear:both;padding-top:10px;">';
		echo '<h3>' . esc_html__( 'Envío por Agencia', 'ubigeo-envio-peru' ) . '</h3>';

		echo '<p style="margin-bottom:4px;"><label for="uep_agencia_nombre_admin"><strong>' . esc_html__( 'Agencia:', 'ubigeo-envio-peru' ) . '</strong></label><br>';
		echo '<select id="uep_agencia_nombre_admin" name="uep_agencia_nombre_admin" style="width:100%;max-width:260px;">';
		echo '<option value="">' . esc_html__( '— Sin definir —', 'ubigeo-envio-peru' ) . '</option>';
		foreach ( $agencias as $nombre ) {
			printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $nombre ), selected( $agencia, $nombre, false ) );
		}
		echo '<option value="__otro">' . esc_html__( 'Otro (escribir)', 'ubigeo-envio-peru' ) . '</option>';
		echo '</select></p>';

		echo '<p id="uep_agencia_otro_admin_wrap" style="margin-bottom:4px;display:none;"><label for="uep_agencia_otro_admin"><strong>' . esc_html__( 'Nombre de la agencia:', 'ubigeo-envio-peru' ) . '</strong></label><br>';
		echo '<input type="text" id="uep_agencia_otro_admin" name="uep_agencia_otro_admin" value="" style="width:100%;max-width:260px;" placeholder="' . esc_attr__( 'Ej.: Marvisur, Cruz del Sur Cargo…', 'ubigeo-envio-peru' ) . '"></p>';

		echo '<script>jQuery(function($){$(document).on("change","#uep_agencia_nombre_admin",function(){$("#uep_agencia_otro_admin_wrap").toggle($(this).val()==="__otro");});});</script>';

		echo '<p style="margin-bottom:4px;"><label for="uep_agencia_sede_admin"><strong>' . esc_html__( 'Sede de recojo:', 'ubigeo-envio-peru' ) . '</strong></label><br>';
		echo '<input type="text" id="uep_agencia_sede_admin" name="uep_agencia_sede_admin" value="' . esc_attr( $sede ) . '" style="width:100%;max-width:260px;"></p>';

		echo '<p style="margin-bottom:4px;"><label for="uep_agencia_guia"><strong>' . esc_html__( 'N° de guía / tracking:', 'ubigeo-envio-peru' ) . '</strong></label><br>';
		echo '<input type="text" id="uep_agencia_guia" name="uep_agencia_guia" value="' . esc_attr( $guia ) . '" style="width:100%;max-width:260px;"></p>';

		echo '<p style="margin-top:4px;"><label>';
		echo '<input type="checkbox" name="uep_agencia_notificar" value="yes" checked> ';
		echo esc_html__( 'Avisar al cliente por email al guardar la guía', 'ubigeo-envio-peru' );
		echo '</label></p>';

		if ( $notificado ) {
			echo '<p class="description">' . esc_html( sprintf( /* translators: %s: fecha */ __( 'Último aviso enviado: %s', 'ubigeo-envio-peru' ), $notificado ) ) . '</p>';
		}

		// Aviso rápido por WhatsApp con el mensaje ya armado.
		$mensaje_wa = sprintf(
			/* translators: 1: nombre 2: pedido 3: agencia 4: sede 5: guía */
			__( 'Hola %1$s, tu pedido #%2$s ya está en camino a la agencia %3$s%4$s%5$s. El costo del envío lo pagas al recoger. ¡Gracias por tu compra!', 'ubigeo-envio-peru' ),
			$order->get_billing_first_name(),
			$order->get_order_number(),
			$agencia ? $agencia : __( 'de transporte', 'ubigeo-envio-peru' ),
			$sede ? ', sede ' . $sede : '',
			$guia ? ', guía ' . $guia : ''
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
	 * Guarda la guía desde la pantalla del pedido y notifica al cliente.
	 *
	 * @param int $order_id ID del pedido.
	 */
	public static function guardar_admin( $order_id ) {
		if ( ! isset( $_POST['uep_agencia_guia'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || ! self::pedido_por_agencia( $order ) ) {
			return;
		}

		$guia      = sanitize_text_field( wp_unslash( $_POST['uep_agencia_guia'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$notificar = isset( $_POST['uep_agencia_notificar'] );                        // phpcs:ignore WordPress.Security.NonceVerification
		$anterior  = (string) $order->get_meta( '_uep_agencia_guia' );

		// Agencia y sede editables desde el pedido (útil cuando el pedido lo
		// hizo el cliente final, que por ahora no elige estos datos).
		if ( isset( $_POST['uep_agencia_nombre_admin'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$agencia_admin = sanitize_text_field( wp_unslash( $_POST['uep_agencia_nombre_admin'] ) ); // phpcs:ignore

			if ( '__otro' === $agencia_admin ) {
				$agencia_admin = isset( $_POST['uep_agencia_otro_admin'] ) ? sanitize_text_field( wp_unslash( $_POST['uep_agencia_otro_admin'] ) ) : ''; // phpcs:ignore
			}

			$order->update_meta_data( '_uep_agencia_nombre', trim( $agencia_admin ) );
		}
		if ( isset( $_POST['uep_agencia_sede_admin'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$order->update_meta_data( '_uep_agencia_sede', sanitize_text_field( wp_unslash( $_POST['uep_agencia_sede_admin'] ) ) ); // phpcs:ignore
		}

		$order->update_meta_data( '_uep_agencia_guia', $guia );

		if ( '' !== $guia && $guia !== $anterior && $notificar ) {
			$enviado = self::enviar_email_guia( $order, $guia );

			if ( $enviado ) {
				$marca = date_i18n( 'Y-m-d H:i' );
				$order->update_meta_data( '_uep_agencia_guia_notificada', $marca );
				$order->add_order_note(
					sprintf(
						/* translators: %s: guía */
						__( 'Se envió al cliente el aviso de envío por agencia (guía %s).', 'ubigeo-envio-peru' ),
						$guia
					)
				);
			}
		}

		$order->save();
	}

	/**
	 * Email al cliente: su paquete va camino a la agencia, con guía y sede.
	 *
	 * @param WC_Order $order Pedido.
	 * @param string   $guia  Número de guía.
	 * @return bool
	 */
	private static function enviar_email_guia( $order, $guia ) {
		$settings = uep_get_settings();
		$mailer   = WC()->mailer();

		$agencia = (string) $order->get_meta( '_uep_agencia_nombre' );
		$sede    = (string) $order->get_meta( '_uep_agencia_sede' );

		$asunto = sprintf(
			/* translators: %s: número de pedido */
			__( 'Tu pedido #%s va en camino a la agencia', 'ubigeo-envio-peru' ),
			$order->get_order_number()
		);

		$titulo = __( '¡Tu pedido está en camino!', 'ubigeo-envio-peru' );

		$cuerpo  = '<p>' . sprintf(
			/* translators: %s: nombre del cliente */
			esc_html__( 'Hola %s,', 'ubigeo-envio-peru' ),
			esc_html( $order->get_billing_first_name() )
		) . '</p>';

		$cuerpo .= '<p>' . sprintf(
			/* translators: %s: número de pedido */
			esc_html__( 'Tu pedido #%s ya fue entregado a la agencia de transporte.', 'ubigeo-envio-peru' ),
			esc_html( $order->get_order_number() )
		) . '</p>';

		$cuerpo .= '<ul>';
		if ( $agencia ) {
			$cuerpo .= '<li><strong>' . esc_html__( 'Agencia:', 'ubigeo-envio-peru' ) . '</strong> ' . esc_html( $agencia ) . '</li>';
		}
		if ( $sede ) {
			$cuerpo .= '<li><strong>' . esc_html__( 'Sede de recojo:', 'ubigeo-envio-peru' ) . '</strong> ' . esc_html( $sede ) . '</li>';
		}
		$cuerpo .= '<li><strong>' . esc_html__( 'Número de guía:', 'ubigeo-envio-peru' ) . '</strong> ' . esc_html( $guia ) . '</li>';
		$cuerpo .= '</ul>';

		$cuerpo .= '<p>' . esc_html__( 'Recuerda: el costo del envío lo cobra la agencia al momento de recoger tu producto. Lleva tu DNI.', 'ubigeo-envio-peru' ) . '</p>';

		if ( '' !== trim( $settings['agencia_note'] ) ) {
			$cuerpo .= '<p>' . esc_html( $settings['agencia_note'] ) . '</p>';
		}

		$mensaje = $mailer->wrap_message( $titulo, $cuerpo );

		return (bool) $mailer->send(
			$order->get_billing_email(),
			$asunto,
			$mensaje
		);
	}
}
