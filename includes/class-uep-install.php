<?php
/**
 * Instalación: creación de tablas y carga del catálogo de ubigeo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UEP_Install {

	/**
	 * Se ejecuta al activar el plugin.
	 */
	public static function activate() {
		self::create_tables();
		self::seed_ubigeo();
		self::seed_tarifas();
		self::fix_missing_data();
		self::purgar_caches_envio();

		if ( false === get_option( 'uep_settings', false ) ) {
			add_option( 'uep_settings', uep_get_settings() );
		}

		update_option( 'uep_version', UEP_VERSION );
	}

	/**
	 * WooCommerce cachea hasta 30 días el conteo de métodos de envío
	 * (transient wc_shipping_method_count) y las tarifas por paquete. Si el
	 * conteo quedó en 0 antes de activar este plugin, WooCommerce oculta toda
	 * la sección de envío en el checkout. Al activar/actualizar, se purga.
	 */
	private static function purgar_caches_envio() {
		delete_transient( 'wc_shipping_method_count' );

		if ( class_exists( 'WC_Cache_Helper' ) ) {
			// Invalida la versión de los cachés de envío (conteo y tarifas).
			WC_Cache_Helper::get_transient_version( 'shipping', true );
		}
	}

	/**
	 * Repara la instalación si el plugin se actualizó sin reactivarse.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'uep_version' ) !== UEP_VERSION ) {
			self::activate();
		}
	}

	/**
	 * Crea las tablas del catálogo (si no existen) y la tabla de tarifas.
	 *
	 * Los nombres de tabla y columnas son los mismos que usaban los plugins
	 * "ubigeo-peru" y "costo-ubigeo-peru", así que una instalación previa
	 * conserva sus datos y los pedidos antiguos siguen mostrando su ubigeo.
	 */
	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}ubigeo_departamento (
				idDepa int(5) NOT NULL DEFAULT '0',
				departamento varchar(50) DEFAULT NULL,
				PRIMARY KEY  (idDepa)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}ubigeo_provincia (
				idProv int(5) NOT NULL DEFAULT '0',
				provincia varchar(50) DEFAULT NULL,
				idDepa int(5) DEFAULT NULL,
				PRIMARY KEY  (idProv),
				KEY idDepa (idDepa)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}ubigeo_distrito (
				idDist int(5) NOT NULL DEFAULT '0',
				distrito varchar(50) DEFAULT NULL,
				idProv int(5) DEFAULT NULL,
				PRIMARY KEY  (idDist),
				KEY idProv (idProv)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}ubigeo_envio_tarifa (
				tarifa_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				idDepa int(5) NOT NULL DEFAULT '0',
				idProv int(5) NOT NULL DEFAULT '0',
				idDist int(5) NOT NULL DEFAULT '0',
				costo decimal(10,2) NOT NULL DEFAULT '0.00',
				costo_express decimal(10,2) DEFAULT NULL,
				estado tinyint(1) NOT NULL DEFAULT '1',
				PRIMARY KEY  (tarifa_id),
				KEY alcance (idDepa,idProv,idDist)
			) {$charset};"
		);

		// dbDelta no siempre agrega columnas nuevas en tablas existentes: refuerzo manual.
		$columna = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}ubigeo_envio_tarifa LIKE 'costo_express'" );
		if ( empty( $columna ) ) {
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}ubigeo_envio_tarifa ADD COLUMN costo_express decimal(10,2) DEFAULT NULL AFTER costo" );
		}
	}

	/**
	 * Carga el catálogo de ubigeo solo si las tablas están vacías.
	 */
	private static function seed_ubigeo() {
		global $wpdb;

		if ( ! (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ubigeo_departamento" ) ) {
			$departamentos = include UEP_PLUGIN_DIR . 'includes/data/departamentos.php';
			foreach ( $departamentos as $id => $nombre ) {
				$wpdb->insert(
					$wpdb->prefix . 'ubigeo_departamento',
					array(
						'idDepa'       => $id,
						'departamento' => $nombre,
					),
					array( '%d', '%s' )
				);
			}
		}

		if ( ! (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ubigeo_provincia" ) ) {
			$provincias = include UEP_PLUGIN_DIR . 'includes/data/provincias.php';
			self::bulk_insert( 'ubigeo_provincia', array( 'idProv', 'provincia', 'idDepa' ), $provincias );
		}

		if ( ! (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ubigeo_distrito" ) ) {
			$distritos = include UEP_PLUGIN_DIR . 'includes/data/distritos.php';
			self::bulk_insert( 'ubigeo_distrito', array( 'idDist', 'distrito', 'idProv' ), $distritos );
		}
	}

	/**
	 * Inserta filas (id => array(nombre, padre)) en lotes.
	 *
	 * @param string $table   Nombre de tabla sin prefijo.
	 * @param array  $columns Columnas: id, nombre, padre.
	 * @param array  $data    Datos del archivo include.
	 */
	private static function bulk_insert( $table, $columns, $data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . $table;
		$rows       = array();

		foreach ( $data as $id => $item ) {
			$rows[] = $wpdb->prepare( '(%d, %s, %d)', $id, $item[0], $item[1] );
		}

		foreach ( array_chunk( $rows, 200 ) as $chunk ) {
			$wpdb->query(
				"INSERT INTO {$table_name} ({$columns[0]}, {$columns[1]}, {$columns[2]}) VALUES " . implode( ',', $chunk )
			);
		}
	}

	/**
	 * Carga las tarifas por defecto (Lima Metropolitana + Callao, con costo
	 * normal y Flash por distrito) solo si aún no hay ninguna tarifa creada.
	 * Una instalación con tarifas propias nunca se toca.
	 */
	private static function seed_tarifas() {
		global $wpdb;

		$existentes = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ubigeo_envio_tarifa" );

		if ( $existentes > 0 ) {
			return;
		}

		$archivo = UEP_PLUGIN_DIR . 'includes/data/tarifas-default.php';

		if ( ! file_exists( $archivo ) ) {
			return;
		}

		$tarifas = include $archivo;

		foreach ( (array) $tarifas as $t ) {
			if ( ! is_array( $t ) || count( $t ) < 5 ) {
				continue;
			}

			uep_guardar_tarifa( $t[0], $t[1], $t[2], $t[3], $t[4] );
		}
	}

	/**
	 * Corrige datos de instalaciones antiguas: el plugin original no incluía
	 * la provincia SAN MIGUEL (Cajamarca, idProv 194) y dejaba 12 distritos
	 * huérfanos que nunca aparecían en el checkout.
	 */
	private static function fix_missing_data() {
		global $wpdb;

		$existe = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ubigeo_provincia WHERE idProv = %d", 194 )
		);

		if ( ! $existe ) {
			$wpdb->insert(
				$wpdb->prefix . 'ubigeo_provincia',
				array(
					'idProv'    => 194,
					'provincia' => 'SAN MIGUEL',
					'idDepa'    => 6,
				),
				array( '%d', '%s', '%d' )
			);
		}
	}
}
