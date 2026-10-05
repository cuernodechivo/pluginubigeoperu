<?php
/**
 * Plugin Name:       Ubigeo y Envío Perú para WooCommerce
 * Plugin URI:        https://github.com/cuernodechivo/pluginubigeoperu
 * Description:       Agrega Departamento / Provincia / Distrito (ubigeo) al checkout de WooCommerce y calcula el costo de envío según la ubicación. Todo en un solo plugin, fácil de configurar.
 * Version:           1.15.0
 * Author:            Envío Perú
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ubigeo-envio-peru
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:   10.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UEP_VERSION', '1.15.0' );
define( 'UEP_PLUGIN_FILE', __FILE__ );
define( 'UEP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UEP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/*
 * Compatibilidad con WooCommerce:
 * - HPOS (tablas de pedidos personalizadas): compatible.
 * - Checkout por bloques: NO compatible (usa el checkout clásico / shortcode).
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
	}
} );

require_once UEP_PLUGIN_DIR . 'includes/uep-functions.php';
require_once UEP_PLUGIN_DIR . 'includes/class-uep-install.php';
require_once UEP_PLUGIN_DIR . 'includes/class-uep-ajax.php';
require_once UEP_PLUGIN_DIR . 'includes/class-uep-checkout.php';
require_once UEP_PLUGIN_DIR . 'includes/class-uep-shipping.php';
require_once UEP_PLUGIN_DIR . 'includes/class-uep-pickup.php';
require_once UEP_PLUGIN_DIR . 'includes/class-uep-agencia.php';

if ( is_admin() ) {
	require_once UEP_PLUGIN_DIR . 'includes/class-uep-admin.php';
}

register_activation_hook( __FILE__, array( 'UEP_Install', 'activate' ) );
add_action( 'plugins_loaded', array( 'UEP_Install', 'maybe_upgrade' ), 5 );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Ubigeo y Envío Perú necesita que WooCommerce esté instalado y activo.', 'ubigeo-envio-peru' );
			echo '</p></div>';
		} );
		return;
	}

	UEP_Ajax::init();
	UEP_Checkout::init();
	UEP_Shipping::init();
	UEP_Pickup::init();
	UEP_Agencia::init();

	if ( is_admin() ) {
		UEP_Admin::init();
	}
} );

// Enlace directo a los ajustes desde la lista de plugins.
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	$settings = '<a href="' . esc_url( admin_url( 'admin.php?page=uep-envio-peru' ) ) . '">' . esc_html__( 'Configurar', 'ubigeo-envio-peru' ) . '</a>';
	array_unshift( $links, $settings );
	return $links;
} );

// Aviso si los plugins antiguos siguen activos (duplicarían campos y costos).
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$activos  = (array) get_option( 'active_plugins', array() );
	$antiguos = array();
	if ( in_array( 'ubigeo-peru/ubigeo-peru.php', $activos, true ) ) {
		$antiguos[] = 'Ubigeo de Perú para WooCommerce';
	}
	if ( in_array( 'costo-ubigeo-peru/costo-ubigeo-peru.php', $activos, true ) ) {
		$antiguos[] = 'Costo de envío de Ubigeo en Perú';
	}
	if ( $antiguos ) {
		echo '<div class="notice notice-warning"><p><strong>Ubigeo y Envío Perú:</strong> ';
		printf(
			/* translators: %s: lista de plugins */
			esc_html__( 'Detectamos que sigue(n) activo(s): %s. Desactívalo(s) para evitar campos y costos duplicados en el checkout. Este plugin reutiliza los mismos datos, no perderás nada.', 'ubigeo-envio-peru' ),
			esc_html( implode( ', ', $antiguos ) )
		);
		echo '</p></div>';
	}
} );
