=== Ubigeo y Envío Perú para WooCommerce ===
Tags: woocommerce, peru, ubigeo, envio, shipping
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.15.0
License: GPLv2 or later

Ubigeo (Departamento / Provincia / Distrito) en el checkout + costo de envío por zona. Todo en un solo plugin.

== Description ==

Un solo plugin que reemplaza a la pareja "Ubigeo de Perú para WooCommerce" + "Costo de envío de Ubigeo en Perú":

* Agrega los selects de Departamento, Provincia y Distrito al checkout (solo para Perú) y oculta estado/ciudad/código postal.
* Calcula el costo de envío según la zona elegida, con reglas simples: la más específica gana (distrito → provincia → departamento → costo por defecto).
* Envío gratis a partir de un monto del carrito (opcional) y soporte de cupones de envío gratis de WooCommerce.
* Las direcciones de los pedidos (admin, emails, "gracias por tu compra", mi cuenta y API REST) muestran los nombres del ubigeo automáticamente.
* Compatible con HPOS. Requiere el checkout clásico (shortcode), no el checkout por bloques.

= Migración desde los plugins anteriores =

Usa las mismas tablas de catálogo (wp_ubigeo_departamento / provincia / distrito) y los mismos metadatos de pedido (_billing_departamento, etc.), así que:

* Los pedidos antiguos siguen mostrando su ubigeo.
* No se duplica el catálogo.
* Solo debes desactivar los dos plugins anteriores y registrar tus tarifas en WooCommerce → Envío Perú (el modelo de tarifas es nuevo, más simple).

Además corrige un error de datos del plugin original: la provincia SAN MIGUEL (Cajamarca) faltaba y sus 12 distritos nunca aparecían en el checkout.

== Installation ==

1. Sube la carpeta `ubigeo-envio-peru` a `/wp-content/plugins/` y activa el plugin.
2. Ve a WooCommerce → Envío Perú.
3. En "Ajustes" define el costo por defecto; en "Tarifas de envío" agrega los costos por departamento (y afina por provincia/distrito si lo necesitas).

== Changelog ==

= 1.15.0 =
* Panel mas facil de usar. Nueva pestana "Inicio" con: resumen automatico de lo que ve cada cliente segun su zona; revision de la configuracion con avisos (checkout por bloques, plugins antiguos, distritos de cobertura sin tarifa, contra entrega sin restringir, Flash sin hora limite, ERP); simulador "que vera un cliente?" que muestra opciones de envio, precio, de donde sale el precio y metodos de pago para cualquier distrito, como cliente o como gestor; y atajos a las tareas frecuentes.
* Ajustes reorganizados en 8 secciones con indice (General, Envio a domicilio y cobertura, Flash, Recojo, Agencia, Metodos de pago, Uso interno, ERP), cada una con su explicacion; barra de guardado siempre visible; las secciones Flash y Recojo se atenuan cuando estan apagadas.
* Tarifas: buscador para encontrar un distrito al instante.
* "Ayuda" pasa a ser "Guia rapida": tareas del dia a dia paso a paso con boton "Ir ahora", y glosario de conceptos.
* Correccion: las listas de metodos de pago (solo cobertura, solo recojo, solo administradores) muestran tambien los metodos desactivados en WooCommerce, marcados como tales, para que no se pierda la seleccion al guardar mientras un metodo esta apagado.

= 1.14.0 =
* Nueva opcion "Pagos solo para administradores": los metodos de pago marcados (ej. link de pago) desaparecen del checkout para los clientes y solo los ven los administradores y gestores de la tienda.

= 1.13.0 =
* Integracion con ERP: cada pedido guarda el servicio de envio usado en codigos estables (_uep_servicio: domicilio, flash, pickup, agencia, interno-...; _uep_servicio_nombre; _uep_canal: web o interno) y los expone en la API REST bajo "uep_envio".
* Nueva opcion "Compatibilidad con ERP": guarda el codigo ISO del departamento (LIM, CAL, CUS...) en el campo estandar Estado/Region del pedido, para que Odoo y otros ERP creen el contacto completo. El cliente sigue viendo el nombre del departamento.

= 1.12.0 =
* Metodos de envio internos: lista configurable de metodos que solo ven los administradores y gestores de la tienda (por defecto "Flex Mercado Libre" y "Urbano (Mercado Libre)"), disponibles en cualquier zona para registrar a mano pedidos de otros canales. El cliente final nunca los ve.

= 1.11.1 =
* Recojo en Tienda: los administradores y gestores de la tienda pueden elegir cualquier fecha y hora (sin minimo de dias, sin dias cerrados ni feriados, hora libre). El cliente final sigue las reglas configuradas.

= 1.11.0 =
* Envio Flash con hora limite: pasada la hora configurada, el Flash sigue disponible pero muestra el aviso "se envia el mismo dia solo en compras antes de las [hora]" (antes se ocultaba).

= 1.10.4 =
* Los campos de agencia y sede (visibles solo para administradores) ahora son opcionales en el checkout: se pueden completar despues desde la pantalla del pedido.

= 1.10.3 =
* Correos reorganizados: tarjeta unica "Detalles del envio" (cliente y administrador) con el metodo elegido, Departamento/Provincia/Distrito, y los datos del servicio: direccion y telefono (domicilio), fecha/hora y direccion de tienda (recojo), o agencia/sede/guia con la nota del pago (agencia).

= 1.10.2 =
* Los correos (cliente y administrador) incluyen un bloque "Ubicacion de entrega" con el ubigeo detallado: Departamento, Provincia y Distrito, cada uno en su linea.

= 1.10.1 =
* La moneda de las tarifas por defecto es la moneda base de WooCommerce (las tarifas de la tienda estan en dolares). La opcion de escribirlas en soles con conversion automatica queda disponible en Ajustes.

= 1.10.0 =
* Moneda de las tarifas: las tarifas se escriben en soles aunque la moneda base de WooCommerce sea otra (ej. USD). El plugin convierte automaticamente con el tipo de cambio del plugin de multimoneda (CURCY/WOOCS) o un tipo de cambio manual de respaldo. El cliente que ve soles paga el monto exacto (S/7) y el que ve dolares paga el equivalente.

= 1.9.1 =
* Pagos por metodo de envio: opcion para definir que metodos de pago se aceptan cuando el cliente elige Recojo en Tienda (ej. solo transferencia). Sin marcar ninguno, se aceptan todos.

= 1.9.0 =
* Configuracion de fabrica completa: al instalar en un sitio nuevo, el plugin queda operativo con la configuracion real de la tienda (cobertura Lima Metropolitana + Callao, Flash, recojo con direccion y horarios, agencias Shalom/Marvisur, pagos restringidos y las 51 tarifas por distrito). Los sitios ya configurados no se tocan.

= 1.8.2 =
* El estimador del carrito calcula automaticamente al elegir el distrito (el boton "Calcular envio" se mantiene como respaldo).

= 1.8.1 =
* El ubigeo elegido en el estimador del carrito precarga los campos del checkout (con prioridad sobre el guardado en la cuenta del cliente): el costo calculado y el del checkout siempre coinciden.

= 1.8.0 =
* Copia de seguridad / migracion: exportar toda la configuracion (ajustes + tarifas) a un archivo JSON e importarla en otro sitio. Los ajustes importados pasan por la misma sanitizacion que el formulario.

= 1.7.3 =
* La cobertura por zonas, el Flash y el Recojo en Tienda ahora vienen activados por defecto (modelo: domicilio/Flash/recojo solo Lima Metropolitana + Callao; resto del pais por agencia). Los ajustes guardados explicitamente se respetan.

= 1.7.2 =
* Tarifas por defecto completas: 51 tarifas precargadas (los 44 distritos de Lima Metropolitana + los 7 del Callao, con costo normal y Flash). Solo se cargan cuando la tabla de tarifas esta vacia.

= 1.7.1 =
* Tarifas por defecto: en instalaciones sin tarifas se precargan 49 tarifas de Lima Metropolitana y Callao (costo normal y Flash por distrito). Nunca se tocan las tarifas ya existentes.

= 1.7.0 =
* Pagos por zona: los métodos de pago marcados (ej. contra entrega) solo se muestran a clientes dentro de las zonas de cobertura.
* Botón "Avisar por WhatsApp" en los paneles de agencia y recojo del pedido, con el mensaje ya armado (guía, sede, fecha de recojo) y el número peruano normalizado.
* Aviso "listo para recoger": casilla en el pedido que envía un email al cliente con su fecha/hora de recojo, más nota en el pedido.
* Hora límite del Flash: pasada la hora configurada, el envío Flash deja de ofrecerse ese día.
* Estimador de envío en el carrito: el cliente elige su ubigeo y ve el costo antes de llegar al checkout.

= 1.6.0 =
* Zonas múltiples: la cobertura del envío a domicilio y la zona del recojo ahora aceptan varias zonas (ej. LIMA › LIMA y CALLAO › CALLAO), administrables con "Agregar zona" / "Quitar" en Ajustes.
* Migración automática: las instalaciones con la zona única Lima/Lima pasan a Lima/Lima + Callao (Lima Metropolitana completa).

= 1.5.2 =
* Opción "Otro (escribir)" en el selector de agencia (checkout y pantalla del pedido): permite escribir el nombre de una agencia que no está en la lista.

= 1.5.1 =
* Los campos de agencia y sede en el checkout solo se muestran a administradores y gestores de la tienda (que generan pedidos por los clientes); el cliente final solo ve la explicación del envío por agencia.
* La agencia y la sede ahora también son editables desde la pantalla del pedido en el admin, para completar los datos de pedidos hechos por clientes.

= 1.5.0 =
* Envío por Agencia: el cliente elige la agencia (lista configurable, ej. Shalom / Olva) y escribe la sede donde recogerá; todo queda guardado en el pedido.
* Número de guía: campo editable en el pedido (admin). Al guardarlo se envía un email automático al cliente con la agencia, sede y guía, y se registra una nota en el pedido.
* Multimoneda: el umbral de "envío gratis desde" se convierte a la moneda que el cliente está viendo (compatible con CURCY y WOOCS) para que la comparación sea correcta.
* Corrección: la detección de pedidos por agencia usa un marcador propio (WooCommerce no guarda el ID de la tarifa en el pedido).

= 1.4.2 =
* El Recojo en Tienda tiene su propia zona (por defecto departamento LIMA + provincia LIMA): solo los clientes de esa zona ven la opción, aunque la cobertura del envío a domicilio sea más amplia.

= 1.4.1 =
* Fuera de la zona de cobertura ya no se ofrece "Recojo en Tienda": la única opción es el envío por agencia.

= 1.4.0 =
* Seguridad: el servidor valida que el distrito pertenezca a la provincia y esta al departamento (no se confía en los IDs del navegador).
* Recojo en Tienda: días de la semana sin atención y feriados excluidos del calendario (validados también en el servidor).
* Tarifas: exportación e importación por CSV (detecta separador coma o punto y coma, reporta filas con error).
* Detección del checkout por bloques con aviso en el admin y conversión al checkout clásico en un clic (con copia de seguridad restaurable).

= 1.3.0 =
* Nuevo: zona de cobertura del envío a domicilio. El envío normal y el Flash solo se ofrecen dentro de la zona configurada (ej. Lima Metropolitana); fuera de ella se ofrece "Envío por Agencia" con costo S/ 0 (el cliente paga el envío a la agencia al recoger su producto).
* El envío por agencia muestra una explicación en el checkout, el pedido, los emails y el panel del administrador, con nota personalizable (ej. Shalom / Olva).

= 1.2.0 =
* Nuevo: "Recojo en Tienda" gratis con calendario de fecha y hora en el checkout (fecha mínima configurable, ej. 2 días después de la compra, y horario de atención en intervalos de 30 minutos).
* La fecha y hora de recojo se guardan en el pedido y se muestran en la página de confirmación, "mi cuenta", los emails y el panel del administrador.

= 1.1.1 =
* Nuevo: enlace "Editar" en la tabla de tarifas para cambiar los costos sin volver a crear la regla.
* Los cambios de tarifas purgan el caché de envíos para aplicarse al instante.

= 1.1.0 =
* Nuevo: segundo servicio de envío "Flash" (más rápido, precio mayor), con costo por zona o costo por defecto.
* El envío gratis por monto mínimo y los cupones aplican solo al envío normal.

= 1.0.1 =
* Corrección: se purgan los cachés de envío de WooCommerce al activar/actualizar (la sección de envío podía quedar oculta por un conteo de métodos cacheado en 0).
* El distrito elegido se sincroniza con el campo "ciudad" para que la opción "ocultar costos de envío hasta introducir una dirección" no bloquee el envío.

= 1.0.0 =
* Versión inicial: ubigeo en checkout + costos de envío unificados en un solo plugin.
