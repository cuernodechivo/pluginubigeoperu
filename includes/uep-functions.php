<?php
/**
 * Funciones de acceso a datos y ajustes.
 *
 * Reutiliza las tablas wp_ubigeo_departamento / wp_ubigeo_provincia / wp_ubigeo_distrito
 * (compatibles con los plugins anteriores) y agrega wp_ubigeo_envio_tarifa para los costos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajustes del plugin con valores por defecto.
 *
 * @return array
 */
function uep_get_settings() {
	// Sin __(): esta función puede ejecutarse antes de 'init' y las traducciones
	// aún no están disponibles (WordPress 6.7+ lo marca como error).
	// Los valores reflejan la configuración real de la tienda: al instalar,
	// el plugin queda operativo sin configurar nada.
	$defaults = array(
		'checkout_enabled'     => 'yes',
		'method_title'         => 'Envío a domicilio (1 a 2 días hábiles)',
		'default_cost'         => '7',
		'free_over'            => '0',
		'tarifa_currency'      => 'base',
		'tarifa_tc'            => '3.75',
		'express_enabled'      => 'yes',
		'express_title'        => 'Envío Flash (mismo día / 24 horas)',
		'express_default_cost' => '',
		'express_cutoff'       => '',
		'pago_solo_cobertura'  => array( 'cod', 'other_payment' ),
		'pago_recojo'          => array(),
		'pago_solo_admin'      => array(),
		'pickup_enabled'       => 'yes',
		'pickup_title'         => 'Recojo en Tienda (gratis)',
		'pickup_min_days'      => '2',
		'pickup_start'         => '10:00',
		'pickup_end'           => '18:00',
		'pickup_note'          => 'Jirón Domingo Ponte 935, Magdalena del Mar 15076',
		'pickup_closed_days'   => array( 0 ),
		'pickup_holidays'      => '',
		'pickup_zone_enabled'  => 'yes',
		'pickup_zone_depa'     => '15',
		'pickup_zone_prov'     => '127',
		'cobertura_enabled'    => 'yes',
		'cobertura_depa'       => '15',
		'cobertura_prov'       => '127',
		'agencia_title'        => 'Envío por Agencia — pagas el envío al recoger en la agencia',
		'agencia_note'         => 'Enviamos por Shalom o Marvisur. Te avisaremos cuando el paquete esté en la agencia.',
		'agencia_list'         => "Shalom\nMarvisur",
		'admin_metodos'        => "Flex Mercado Libre | 0\nUrbano (Mercado Libre) | 0",
		'erp_state'            => 'yes',
	);

	$settings = get_option( 'uep_settings', array() );
	$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );

	// Migración a zonas múltiples (v1.6.0): la cobertura y el recojo pasan de
	// una sola zona (depa/prov) a una lista de zonas "depa|prov". Si la zona
	// antigua era Lima/Lima (la configuración típica), se agrega Callao, que
	// forma parte de Lima Metropolitana.
	if ( empty( $settings['cobertura_zonas'] ) || ! is_array( $settings['cobertura_zonas'] ) ) {
		$zona                        = absint( $settings['cobertura_depa'] ) . '|' . absint( $settings['cobertura_prov'] );
		$settings['cobertura_zonas'] = array( $zona );

		if ( '15|127' === $zona ) {
			$settings['cobertura_zonas'][] = '7|66'; // CALLAO › CALLAO.
		}
	}

	if ( empty( $settings['pickup_zonas'] ) || ! is_array( $settings['pickup_zonas'] ) ) {
		$zona                     = absint( $settings['pickup_zone_depa'] ) . '|' . absint( $settings['pickup_zone_prov'] );
		$settings['pickup_zonas'] = array( $zona );

		if ( '15|127' === $zona ) {
			$settings['pickup_zonas'][] = '7|66';
		}
	}

	return $settings;
}

/**
 * ¿El ubigeo elegido está dentro de alguna de las zonas?
 *
 * @param array $zonas   Zonas en formato "idDepa|idProv" (idProv 0 = todo el departamento).
 * @param int   $id_depa Departamento elegido.
 * @param int   $id_prov Provincia elegida.
 * @return bool
 */
function uep_zona_coincide( $zonas, $id_depa, $id_prov ) {
	$id_depa = absint( $id_depa );
	$id_prov = absint( $id_prov );

	foreach ( (array) $zonas as $zona ) {
		if ( ! preg_match( '/^(\d+)\|(\d+)$/', (string) $zona, $m ) ) {
			continue;
		}

		$zona_depa = (int) $m[1];
		$zona_prov = (int) $m[2];

		if ( $id_depa === $zona_depa && ( ! $zona_prov || $id_prov === $zona_prov ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Etiqueta legible de una zona "idDepa|idProv".
 *
 * @param string $zona Zona.
 * @return string
 */
function uep_zona_etiqueta( $zona ) {
	if ( ! preg_match( '/^(\d+)\|(\d+)$/', (string) $zona, $m ) ) {
		return (string) $zona;
	}

	$nombres = uep_get_nombres_ubigeo( (int) $m[1], (int) $m[2], 0 );

	if ( (int) $m[2] > 0 && '' !== $nombres['provincia'] ) {
		return $nombres['departamento'] . ' › ' . $nombres['provincia'];
	}

	return sprintf(
		/* translators: %s: departamento */
		__( '%s (todo el departamento)', 'ubigeo-envio-peru' ),
		$nombres['departamento']
	);
}

/**
 * Lista de departamentos ordenados por nombre.
 *
 * @return array[] Filas con idDepa y departamento.
 */
function uep_get_departamentos() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'ubigeo_departamento';
	$cache = $wpdb->get_results( "SELECT idDepa, departamento FROM {$table} ORDER BY departamento ASC", ARRAY_A );

	return is_array( $cache ) ? $cache : array();
}

/**
 * Provincias de un departamento.
 *
 * @param int $id_depa ID del departamento.
 * @return array[]
 */
function uep_get_provincias( $id_depa ) {
	global $wpdb;
	$table = $wpdb->prefix . 'ubigeo_provincia';

	$rows = $wpdb->get_results(
		$wpdb->prepare( "SELECT idProv, provincia FROM {$table} WHERE idDepa = %d ORDER BY provincia ASC", absint( $id_depa ) ),
		ARRAY_A
	);

	return is_array( $rows ) ? $rows : array();
}

/**
 * Distritos de una provincia.
 *
 * @param int $id_prov ID de la provincia.
 * @return array[]
 */
function uep_get_distritos( $id_prov ) {
	global $wpdb;
	$table = $wpdb->prefix . 'ubigeo_distrito';

	$rows = $wpdb->get_results(
		$wpdb->prepare( "SELECT idDist, distrito FROM {$table} WHERE idProv = %d ORDER BY distrito ASC", absint( $id_prov ) ),
		ARRAY_A
	);

	return is_array( $rows ) ? $rows : array();
}

/**
 * Nombres del ubigeo a partir de sus IDs.
 *
 * @param int $id_depa Departamento.
 * @param int $id_prov Provincia.
 * @param int $id_dist Distrito.
 * @return array { departamento, provincia, distrito } (vacíos si no existen).
 */
function uep_get_nombres_ubigeo( $id_depa, $id_prov = 0, $id_dist = 0 ) {
	global $wpdb;

	$nombres = array(
		'departamento' => '',
		'provincia'    => '',
		'distrito'     => '',
	);

	if ( $id_depa ) {
		$nombres['departamento'] = (string) $wpdb->get_var(
			$wpdb->prepare( "SELECT departamento FROM {$wpdb->prefix}ubigeo_departamento WHERE idDepa = %d", absint( $id_depa ) )
		);
	}
	if ( $id_prov ) {
		$nombres['provincia'] = (string) $wpdb->get_var(
			$wpdb->prepare( "SELECT provincia FROM {$wpdb->prefix}ubigeo_provincia WHERE idProv = %d", absint( $id_prov ) )
		);
	}
	if ( $id_dist ) {
		$nombres['distrito'] = (string) $wpdb->get_var(
			$wpdb->prepare( "SELECT distrito FROM {$wpdb->prefix}ubigeo_distrito WHERE idDist = %d", absint( $id_dist ) )
		);
	}

	return $nombres;
}

/**
 * Resuelve la tarifa aplicable con prioridad: distrito > provincia > departamento.
 *
 * @param int $id_depa Departamento elegido.
 * @param int $id_prov Provincia elegida.
 * @param int $id_dist Distrito elegido.
 * @return array|null Fila de tarifa o null si ninguna regla coincide.
 */
function uep_resolver_tarifa( $id_depa, $id_prov = 0, $id_dist = 0 ) {
	global $wpdb;
	$table = $wpdb->prefix . 'ubigeo_envio_tarifa';

	$id_depa = absint( $id_depa );
	$id_prov = absint( $id_prov );
	$id_dist = absint( $id_dist );

	if ( ! $id_depa ) {
		return null;
	}

	// Distrito exacto.
	if ( $id_dist ) {
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE idDist = %d AND estado = 1 LIMIT 1", $id_dist ),
			ARRAY_A
		);
		if ( $row ) {
			return $row;
		}
	}

	// Toda la provincia.
	if ( $id_prov ) {
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE idProv = %d AND idDist = 0 AND estado = 1 LIMIT 1", $id_prov ),
			ARRAY_A
		);
		if ( $row ) {
			return $row;
		}
	}

	// Todo el departamento.
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE idDepa = %d AND idProv = 0 AND idDist = 0 AND estado = 1 LIMIT 1", $id_depa ),
		ARRAY_A
	);

	return $row ? $row : null;
}

/**
 * Lista todas las tarifas con los nombres de su alcance.
 *
 * @return array[]
 */
function uep_get_tarifas() {
	global $wpdb;
	$t  = $wpdb->prefix . 'ubigeo_envio_tarifa';
	$td = $wpdb->prefix . 'ubigeo_departamento';
	$tp = $wpdb->prefix . 'ubigeo_provincia';
	$ts = $wpdb->prefix . 'ubigeo_distrito';

	$rows = $wpdb->get_results(
		"SELECT tar.*, dep.departamento, prov.provincia, dist.distrito
		 FROM {$t} tar
		 LEFT JOIN {$td} dep  ON dep.idDepa  = tar.idDepa
		 LEFT JOIN {$tp} prov ON prov.idProv = tar.idProv
		 LEFT JOIN {$ts} dist ON dist.idDist = tar.idDist
		 ORDER BY dep.departamento ASC, prov.provincia ASC, dist.distrito ASC",
		ARRAY_A
	);

	return is_array( $rows ) ? $rows : array();
}

/**
 * Crea o actualiza una tarifa. Si ya existe una regla con el mismo alcance, se reemplaza.
 *
 * @param int        $id_depa       Departamento (obligatorio).
 * @param int        $id_prov       Provincia (0 = todo el departamento).
 * @param int        $id_dist       Distrito (0 = toda la provincia / departamento).
 * @param float      $costo         Costo del envío normal.
 * @param float|null $costo_express Costo del envío rápido (null = sin costo propio;
 *                                  se usará el costo Flash por defecto de los ajustes).
 * @return bool
 */
function uep_guardar_tarifa( $id_depa, $id_prov, $id_dist, $costo, $costo_express = null ) {
	global $wpdb;
	$table = $wpdb->prefix . 'ubigeo_envio_tarifa';

	$id_depa = absint( $id_depa );
	$id_prov = absint( $id_prov );
	$id_dist = absint( $id_dist );
	$costo   = round( (float) $costo, 2 );

	if ( null !== $costo_express && '' !== $costo_express ) {
		$costo_express = round( (float) $costo_express, 2 );
		if ( $costo_express < 0 ) {
			$costo_express = null;
		}
	} else {
		$costo_express = null;
	}

	if ( ! $id_depa || $costo < 0 ) {
		return false;
	}

	$datos = array(
		'costo'         => $costo,
		'costo_express' => $costo_express, // wpdb inserta NULL cuando el valor es null.
		'estado'        => 1,
	);

	$existente = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT tarifa_id FROM {$table} WHERE idDepa = %d AND idProv = %d AND idDist = %d LIMIT 1",
			$id_depa,
			$id_prov,
			$id_dist
		)
	);

	uep_purgar_cache_tarifas();

	if ( $existente ) {
		return false !== $wpdb->update(
			$table,
			$datos,
			array( 'tarifa_id' => (int) $existente ),
			array( '%f', '%f', '%d' ),
			array( '%d' )
		);
	}

	return false !== $wpdb->insert(
		$table,
		array_merge(
			array(
				'idDepa' => $id_depa,
				'idProv' => $id_prov,
				'idDist' => $id_dist,
			),
			$datos
		),
		array( '%d', '%d', '%d', '%f', '%f', '%d' )
	);
}

/**
 * Verifica que la jerarquía del ubigeo sea coherente: el distrito pertenece
 * a la provincia y la provincia al departamento.
 *
 * @param int $id_depa Departamento.
 * @param int $id_prov Provincia.
 * @param int $id_dist Distrito.
 * @return bool
 */
function uep_validar_jerarquia( $id_depa, $id_prov, $id_dist ) {
	global $wpdb;

	$id_depa = absint( $id_depa );
	$id_prov = absint( $id_prov );
	$id_dist = absint( $id_dist );

	if ( ! $id_depa || ! $id_prov || ! $id_dist ) {
		return false;
	}

	$prov_ok = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ubigeo_provincia WHERE idProv = %d AND idDepa = %d",
			$id_prov,
			$id_depa
		)
	);

	if ( ! $prov_ok ) {
		return false;
	}

	$dist_ok = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ubigeo_distrito WHERE idDist = %d AND idProv = %d",
			$id_dist,
			$id_prov
		)
	);

	return (bool) $dist_ok;
}

/**
 * Busca un departamento por nombre (para importación CSV).
 *
 * @param string $nombre Nombre tal como aparece en el catálogo.
 * @return int ID o 0 si no existe.
 */
function uep_buscar_depa_por_nombre( $nombre ) {
	global $wpdb;

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT idDepa FROM {$wpdb->prefix}ubigeo_departamento WHERE TRIM(departamento) = %s LIMIT 1",
			mb_strtoupper( trim( $nombre ) )
		)
	);
}

/**
 * Busca una provincia por nombre dentro de un departamento.
 *
 * @param int    $id_depa Departamento.
 * @param string $nombre  Nombre de la provincia.
 * @return int ID o 0.
 */
function uep_buscar_prov_por_nombre( $id_depa, $nombre ) {
	global $wpdb;

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT idProv FROM {$wpdb->prefix}ubigeo_provincia WHERE idDepa = %d AND TRIM(provincia) = %s LIMIT 1",
			absint( $id_depa ),
			mb_strtoupper( trim( $nombre ) )
		)
	);
}

/**
 * Busca un distrito por nombre dentro de una provincia.
 *
 * @param int    $id_prov Provincia.
 * @param string $nombre  Nombre del distrito.
 * @return int ID o 0.
 */
function uep_buscar_dist_por_nombre( $id_prov, $nombre ) {
	global $wpdb;

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT idDist FROM {$wpdb->prefix}ubigeo_distrito WHERE idProv = %d AND TRIM(distrito) = %s LIMIT 1",
			absint( $id_prov ),
			mb_strtoupper( trim( $nombre ) )
		)
	);
}

/**
 * Código ISO 3166-2:PE del departamento (LIM, CAL, CUS…), que es el mismo
 * que usan WooCommerce y Odoo para identificar el estado/región. Permite que
 * un ERP empareje el contacto sin configuración adicional.
 *
 * @param int $id_depa ID del departamento en el catálogo del plugin.
 * @return string Código de 3 letras, o '' si no se reconoce.
 */
function uep_departamento_iso( $id_depa ) {
	$codigos = array(
		1  => 'AMA', // AMAZONAS.
		2  => 'ANC', // ANCASH.
		3  => 'APU', // APURIMAC.
		4  => 'ARE', // AREQUIPA.
		5  => 'AYA', // AYACUCHO.
		6  => 'CAJ', // CAJAMARCA.
		7  => 'CAL', // CALLAO.
		8  => 'CUS', // CUSCO.
		9  => 'HUV', // HUANCAVELICA.
		10 => 'HUC', // HUANUCO.
		11 => 'ICA', // ICA.
		12 => 'JUN', // JUNIN.
		13 => 'LAL', // LA LIBERTAD.
		14 => 'LAM', // LAMBAYEQUE.
		15 => 'LIM', // LIMA.
		16 => 'LOR', // LORETO.
		17 => 'MDD', // MADRE DE DIOS.
		18 => 'MOQ', // MOQUEGUA.
		19 => 'PAS', // PASCO.
		20 => 'PIU', // PIURA.
		21 => 'PUN', // PUNO.
		22 => 'SAM', // SAN MARTIN.
		23 => 'TAC', // TACNA.
		24 => 'TUM', // TUMBES.
		25 => 'UCA', // UCAYALI.
	);

	$id_depa = absint( $id_depa );

	return isset( $codigos[ $id_depa ] ) ? $codigos[ $id_depa ] : '';
}

/**
 * Métodos de envío visibles solo para administradores y gestores de la tienda
 * (ej. Flex y Urbano de Mercado Libre, para pedidos que se registran a mano).
 *
 * Formato en los ajustes: un método por línea, "Nombre | costo" (costo opcional).
 *
 * @return array[] Cada elemento: array( 'nombre' => string, 'costo' => float ).
 */
function uep_metodos_admin() {
	$crudo   = (string) uep_get_settings()['admin_metodos'];
	$metodos = array();

	foreach ( preg_split( '/[\r\n]+/', $crudo ) as $linea ) {
		$linea = trim( $linea );

		if ( '' === $linea ) {
			continue;
		}

		$partes = array_map( 'trim', explode( '|', $linea, 2 ) );
		$nombre = $partes[0];

		if ( '' === $nombre ) {
			continue;
		}

		$metodos[] = array(
			'nombre' => $nombre,
			'costo'  => isset( $partes[1] ) ? (float) str_replace( ',', '.', $partes[1] ) : 0.0,
		);
	}

	return $metodos;
}

/**
 * ¿El usuario actual es administrador o gestor de la tienda?
 */
function uep_es_gestor() {
	return current_user_can( 'manage_woocommerce' );
}

/**
 * URL de WhatsApp (wa.me) hacia el teléfono de un pedido, con mensaje prellenado.
 * Normaliza celulares peruanos de 9 dígitos agregando el código 51.
 *
 * @param WC_Order $order   Pedido.
 * @param string   $mensaje Mensaje a prellenar.
 * @return string URL o '' si el pedido no tiene teléfono.
 */
function uep_whatsapp_url( $order, $mensaje ) {
	if ( ! $order instanceof WC_Order ) {
		return '';
	}

	$telefono = preg_replace( '/\D+/', '', (string) $order->get_billing_phone() );

	if ( '' === $telefono ) {
		return '';
	}

	if ( 9 === strlen( $telefono ) && '9' === $telefono[0] ) {
		$telefono = '51' . $telefono;
	}

	return 'https://wa.me/' . $telefono . '?text=' . rawurlencode( $mensaje );
}

/**
 * Convierte un monto de tarifa (definido en soles) a la moneda base de
 * WooCommerce. Si la base ya es PEN, o las tarifas están configuradas en la
 * moneda base, no convierte nada.
 *
 * El tipo de cambio se toma del plugin de multimoneda (CURCY free/premium o
 * WOOCS); si no hay ninguno, se usa el tipo de cambio manual de los ajustes.
 *
 * @param float $monto Monto en soles (o en moneda base según configuración).
 * @return float Monto en la moneda base de WooCommerce.
 */
function uep_tarifa_a_base( $monto ) {
	$monto = (float) $monto;

	if ( $monto <= 0 ) {
		return $monto;
	}

	$settings = uep_get_settings();

	// Tarifas configuradas en la moneda base: sin conversión.
	if ( 'PEN' !== $settings['tarifa_currency'] ) {
		return $monto;
	}

	// La base ya es soles: sin conversión.
	if ( 'PEN' === get_option( 'woocommerce_currency' ) ) {
		return $monto;
	}

	$tc = 0.0;

	// CURCY (WooCommerce Multi Currency) free y premium.
	foreach ( array( 'WOOMULTI_CURRENCY_F_Data', 'WOOMULTI_CURRENCY_Data' ) as $clase ) {
		if ( $tc <= 0 && class_exists( $clase ) && method_exists( $clase, 'get_ins' ) ) {
			$lista = $clase::get_ins()->get_list_currencies();
			if ( isset( $lista['PEN']['rate'] ) && (float) $lista['PEN']['rate'] > 0 ) {
				$tc = (float) $lista['PEN']['rate'];
			}
		}
	}

	// WOOCS - WooCommerce Currency Switcher.
	global $WOOCS;
	if ( $tc <= 0 && is_object( $WOOCS ) && isset( $WOOCS->currencies['PEN']['rate'] ) ) {
		$tc = (float) $WOOCS->currencies['PEN']['rate'];
	}

	// Tipo de cambio manual de los ajustes.
	if ( $tc <= 0 ) {
		$tc = (float) $settings['tarifa_tc'];
	}

	if ( $tc <= 0 ) {
		return $monto;
	}

	// Sin redondear: así la conversión de vuelta a soles da el monto exacto.
	return $monto / $tc;
}

/**
 * Convierte un monto de la moneda base a la moneda que el cliente está viendo,
 * cuando hay un plugin de multimoneda activo (CURCY / WOOCS). Sin plugin de
 * moneda, devuelve el monto sin cambios.
 *
 * @param float $monto Monto en la moneda base de WooCommerce.
 * @return float
 */
function uep_convertir_monto_a_moneda_actual( $monto ) {
	$monto = (float) $monto;

	if ( $monto <= 0 ) {
		return $monto;
	}

	// WooCommerce Multi Currency (CURCY, free y premium).
	if ( function_exists( 'wmc_get_price' ) ) {
		return (float) wmc_get_price( $monto );
	}

	// WOOCS - WooCommerce Currency Switcher.
	global $WOOCS;
	if ( is_object( $WOOCS ) && method_exists( $WOOCS, 'woocs_exchange_value' ) ) {
		return (float) $WOOCS->woocs_exchange_value( $monto );
	}

	return $monto;
}

/**
 * Invalida los cachés de tarifas de WooCommerce para que un cambio de
 * costos se refleje de inmediato en carritos ya calculados.
 */
function uep_purgar_cache_tarifas() {
	if ( class_exists( 'WC_Cache_Helper' ) ) {
		WC_Cache_Helper::get_transient_version( 'shipping', true );
	}
}

/**
 * Actualiza los costos de una tarifa existente por su ID.
 *
 * @param int        $tarifa_id     ID de la tarifa.
 * @param float      $costo         Costo del envío normal.
 * @param float|null $costo_express Costo Flash (null = usar el Flash por defecto).
 * @return bool
 */
function uep_actualizar_tarifa( $tarifa_id, $costo, $costo_express = null ) {
	global $wpdb;

	$tarifa_id = absint( $tarifa_id );
	$costo     = round( (float) $costo, 2 );

	if ( null !== $costo_express && '' !== $costo_express ) {
		$costo_express = round( (float) $costo_express, 2 );
		if ( $costo_express < 0 ) {
			$costo_express = null;
		}
	} else {
		$costo_express = null;
	}

	if ( ! $tarifa_id || $costo < 0 ) {
		return false;
	}

	uep_purgar_cache_tarifas();

	return false !== $wpdb->update(
		$wpdb->prefix . 'ubigeo_envio_tarifa',
		array(
			'costo'         => $costo,
			'costo_express' => $costo_express,
		),
		array( 'tarifa_id' => $tarifa_id ),
		array( '%f', '%f' ),
		array( '%d' )
	);
}

/**
 * Elimina una tarifa por su ID.
 *
 * @param int $tarifa_id ID de la tarifa.
 * @return bool
 */
function uep_eliminar_tarifa( $tarifa_id ) {
	global $wpdb;

	uep_purgar_cache_tarifas();

	return false !== $wpdb->delete(
		$wpdb->prefix . 'ubigeo_envio_tarifa',
		array( 'tarifa_id' => absint( $tarifa_id ) ),
		array( '%d' )
	);
}

/**
 * Etiqueta legible del alcance de una tarifa.
 *
 * @param array $tarifa Fila de uep_get_tarifas().
 * @return string
 */
function uep_tarifa_alcance( $tarifa ) {
	if ( ! empty( $tarifa['idDist'] ) ) {
		return sprintf(
			'%s › %s › %s',
			$tarifa['departamento'],
			$tarifa['provincia'],
			$tarifa['distrito']
		);
	}
	if ( ! empty( $tarifa['idProv'] ) ) {
		return sprintf(
			/* translators: 1: departamento 2: provincia */
			__( '%1$s › %2$s (toda la provincia)', 'ubigeo-envio-peru' ),
			$tarifa['departamento'],
			$tarifa['provincia']
		);
	}
	return sprintf(
		/* translators: %s: departamento */
		__( '%s (todo el departamento)', 'ubigeo-envio-peru' ),
		$tarifa['departamento']
	);
}
