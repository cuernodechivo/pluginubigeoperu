<?php
/**
 * Limpieza al desinstalar (borrar) el plugin.
 *
 * - Siempre elimina la tabla de tarifas y los ajustes del plugin.
 * - Las tablas del catálogo de ubigeo solo se eliminan si los plugins
 *   antiguos (ubigeo-peru / costo-ubigeo-peru) no están instalados,
 *   porque ellos también las usan.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ubigeo_envio_tarifa" );

delete_option( 'uep_settings' );
delete_option( 'uep_version' );

$plugins_antiguos = file_exists( WP_PLUGIN_DIR . '/ubigeo-peru/ubigeo-peru.php' )
	|| file_exists( WP_PLUGIN_DIR . '/costo-ubigeo-peru/costo-ubigeo-peru.php' );

if ( ! $plugins_antiguos ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ubigeo_distrito" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ubigeo_provincia" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ubigeo_departamento" );
}
