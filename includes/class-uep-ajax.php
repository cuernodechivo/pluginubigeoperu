<?php
/**
 * Endpoints AJAX para los selects encadenados (checkout y admin).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UEP_Ajax {

	public static function init() {
		add_action( 'wp_ajax_uep_provincias', array( __CLASS__, 'provincias' ) );
		add_action( 'wp_ajax_nopriv_uep_provincias', array( __CLASS__, 'provincias' ) );
		add_action( 'wp_ajax_uep_distritos', array( __CLASS__, 'distritos' ) );
		add_action( 'wp_ajax_nopriv_uep_distritos', array( __CLASS__, 'distritos' ) );
		add_action( 'wp_ajax_uep_estimar', array( __CLASS__, 'estimar' ) );
		add_action( 'wp_ajax_nopriv_uep_estimar', array( __CLASS__, 'estimar' ) );
	}

	/**
	 * Guarda el ubigeo elegido en el carrito para estimar el costo de envío.
	 */
	public static function estimar() {
		check_ajax_referer( 'uep_ubigeo', 'nonce' );

		$id_depa = isset( $_POST['idDepa'] ) ? absint( $_POST['idDepa'] ) : 0;
		$id_prov = isset( $_POST['idProv'] ) ? absint( $_POST['idProv'] ) : 0;
		$id_dist = isset( $_POST['idDist'] ) ? absint( $_POST['idDist'] ) : 0;

		if ( ! $id_depa || ! WC()->session ) {
			wp_send_json_error();
		}

		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		WC()->session->set( 'uep_ubigeo', array( $id_depa, $id_prov, $id_dist ) );

		if ( WC()->customer ) {
			WC()->customer->set_billing_country( 'PE' );
			WC()->customer->set_shipping_country( 'PE' );
			WC()->customer->save();
		}

		wp_send_json_success();
	}

	public static function provincias() {
		check_ajax_referer( 'uep_ubigeo', 'nonce' );

		$id_depa = isset( $_POST['idDepa'] ) ? absint( $_POST['idDepa'] ) : 0;

		wp_send_json_success( $id_depa ? uep_get_provincias( $id_depa ) : array() );
	}

	public static function distritos() {
		check_ajax_referer( 'uep_ubigeo', 'nonce' );

		$id_prov = isset( $_POST['idProv'] ) ? absint( $_POST['idProv'] ) : 0;

		wp_send_json_success( $id_prov ? uep_get_distritos( $id_prov ) : array() );
	}
}
