<?php
/**
 * Tarifas de envio por defecto (configuracion real de la tienda: Lima Metropolitana + Callao).
 * Se cargan solo cuando la tabla de tarifas esta vacia.
 * Formato: array( idDepa, idProv, idDist, costo_normal, costo_flash|null )
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

return array(
	array( 7, 66, 680, 8.00, 16.00 ), // BELLAVISTA
	array( 7, 66, 679, 8.00, 16.00 ), // CALLAO
	array( 7, 66, 681, 8.00, 16.00 ), // CARMEN DE LA LEGUA REYNOSO
	array( 7, 66, 682, 8.00, 16.00 ), // LA PERLA
	array( 7, 66, 683, 8.00, 16.00 ), // LA PUNTA
	array( 7, 66, 1881, 12.00, 24.00 ), // MI PERU
	array( 7, 66, 684, 12.00, 24.00 ), // VENTANILLA
	array( 15, 127, 1252, 20.00, 35.00 ), // ANCON
	array( 15, 127, 1253, 7.00, 15.00 ), // ATE
	array( 15, 127, 1254, 7.00, 14.00 ), // BARRANCO
	array( 15, 127, 1255, 5.00, 10.00 ), // BREÑA
	array( 15, 127, 1256, 12.00, 20.00 ), // CARABAYLLO
	array( 15, 127, 1257, 15.00, 25.00 ), // CHACLACAYO
	array( 15, 127, 1258, 7.00, 14.00 ), // CHORRILLOS
	array( 15, 127, 1259, 15.00, 25.00 ), // CIENEGUILLA
	array( 15, 127, 1260, 12.00, 25.00 ), // COMAS
	array( 15, 127, 1261, 6.00, 12.00 ), // EL AGUSTINO
	array( 15, 127, 1262, 7.00, 14.00 ), // INDEPENDENCIA
	array( 15, 127, 1263, 5.00, 10.00 ), // JESUS MARIA
	array( 15, 127, 1264, 7.00, 14.00 ), // LA MOLINA
	array( 15, 127, 1265, 5.00, 10.00 ), // LA VICTORIA
	array( 15, 127, 1251, 5.00, 10.00 ), // LIMA
	array( 15, 127, 1266, 5.00, 10.00 ), // LINCE
	array( 15, 127, 1267, 8.00, 15.00 ), // LOS OLIVOS
	array( 15, 127, 1268, 15.00, 25.00 ), // LURIGANCHO-CHOSICA
	array( 15, 127, 1269, 15.00, 25.00 ), // LURIN
	array( 15, 127, 1270, 5.00, 10.00 ), // MAGDALENA DEL MAR
	array( 15, 127, 1272, 7.00, 14.00 ), // MIRAFLORES
	array( 15, 127, 1273, 20.00, 35.00 ), // PACHACAMAC
	array( 15, 127, 1274, 20.00, 35.00 ), // PUCUSANA
	array( 15, 127, 1271, 5.00, 10.00 ), // PUEBLO LIBRE
	array( 15, 127, 1275, 12.00, 24.00 ), // PUENTE PIEDRA
	array( 15, 127, 1277, 20.00, 35.00 ), // PUNTA NEGRA
	array( 15, 127, 1278, 7.00, 14.00 ), // RIMAC
	array( 15, 127, 1279, 20.00, 35.00 ), // SAN BARTOLO
	array( 15, 127, 1280, 7.00, 14.00 ), // SAN BORJA
	array( 15, 127, 1281, 5.00, 10.00 ), // SAN ISIDRO
	array( 15, 127, 1282, 7.00, 14.00 ), // SAN JUAN DE LURIGANCHO
	array( 15, 127, 1283, 10.00, 20.00 ), // SAN JUAN DE MIRAFLORES
	array( 15, 127, 1284, 7.00, 14.00 ), // SAN LUIS
	array( 15, 127, 1285, 7.00, 14.00 ), // SAN MARTIN DE PORRES
	array( 15, 127, 1286, 6.00, 12.00 ), // SAN MIGUEL
	array( 15, 127, 1287, 7.00, 14.00 ), // SANTA ANITA
	array( 15, 127, 1288, 20.00, 35.00 ), // SANTA MARIA DEL MAR
	array( 15, 127, 1289, 20.00, 35.00 ), // SANTA ROSA
	array( 15, 127, 1290, 7.00, 14.00 ), // SANTIAGO DE SURCO
	array( 15, 127, 1291, 7.00, 14.00 ), // SURQUILLO
	array( 15, 127, 1292, 10.00, 20.00 ), // VILLA EL SALVADOR
	array( 15, 127, 1293, 10.00, 20.00 ), // VILLA MARIA DEL TRIUNFO
	array( 15, 127, 1833, 7.00, 14.00 ), // SALAMANCA
	array( 15, 127, 1276, 20.00, 35.00 ), // PUNTA HERMOSA
);
