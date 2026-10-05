# Ubigeo y Envío Perú para WooCommerce

Plugin de WordPress que agrega **Departamento / Provincia / Distrito** (ubigeo) al checkout de WooCommerce y calcula el **costo de envío según la zona**. Todo en un solo plugin, con un panel pensado para que cualquier persona del equipo lo pueda manejar.

## Qué hace

- **Ubigeo en el checkout:** selects encadenados de Departamento, Provincia y Distrito (solo para Perú); oculta estado, ciudad y código postal.
- **Tarifas por zona:** la regla más específica gana (distrito → provincia → departamento → costo por defecto). Se pueden importar y exportar en CSV.
- **Servicios de envío:**
  - **Envío a domicilio** (1 a 2 días hábiles) y **Envío Flash** (mismo día, con hora límite configurable) en las zonas de cobertura.
  - **Recojo en tienda** gratis, con calendario (días mínimos tras la compra, días cerrados y feriados).
  - **Envío por agencia** para el resto del país: el cliente paga el envío en la agencia al recoger.
  - **Métodos internos** (por ejemplo Flex o Urbano de Mercado Libre) que solo ven los administradores.
- **Métodos de pago por zona:** limita contra entrega a la cobertura, elige los pagos del recojo y oculta a los clientes los pagos solo para el equipo (link de pago).
- **Pedidos y correos:** tarjeta "Detalles del envío" en los correos, número de guía de agencia con aviso al cliente, aviso de "listo para recoger" y botón de WhatsApp.
- **ERP:** cada pedido guarda el servicio de envío en metadatos estables y el código ISO del departamento (LIM, CAL, CUS…), también en la API REST.
- **Panel fácil:** pestaña **Inicio** con resumen de la tienda, revisión de la configuración y simulador "¿qué verá un cliente?"; ajustes por secciones; buscador de tarifas; **Guía rápida** paso a paso.

## Requisitos

- WordPress 6.0 o superior
- WooCommerce 6.0 o superior (probado hasta 10.4, compatible con HPOS)
- PHP 7.4 o superior
- Checkout **clásico** (shortcode). Si tu página de pago usa el checkout por bloques, el plugin lo detecta y ofrece convertirla con un clic.

## Instalación

> **Importante:** descarga el plugin desde **[Releases](../../releases)**, no con el botón verde **Code → Download ZIP**. Ese ZIP trae la carpeta `pluginubigeoperu-main` y WordPress lo instalaría como un plugin distinto, duplicado del que ya tienes.

1. Entra a [Releases](../../releases) y descarga `ubigeo-envio-peru-X.Y.Z.zip`.
2. En WordPress ve a **Plugins → Añadir nuevo → Subir plugin** y elige el ZIP.
3. Si ya tenías el plugin, pulsa **"Reemplazar la versión actual"**. Tus tarifas y ajustes se conservan.
4. Ve a **WooCommerce → Envío Perú → Inicio** y revisa que la revisión diga "Todo en orden".

### Configuración de fábrica

Una instalación nueva viene lista para usar con la configuración de nuestra tienda: cobertura en Lima › Lima y Callao › Callao, 51 tarifas por distrito (en la moneda base de WooCommerce), dirección y horario de recojo, y agencias Shalom y Marvisur. Si lo instalas en otra tienda, cambia esos datos en **Ajustes** y **Tarifas de envío**, o importa tu propia configuración en **Ajustes → Copia de seguridad**. Si el sitio ya tiene tarifas o ajustes guardados, no se tocan.

## Integración con ERP / API REST

Cada pedido guarda:

| Metadato | Valores |
| --- | --- |
| `_uep_servicio` | `domicilio`, `flash`, `pickup`, `agencia` o `interno-<nombre>` |
| `_uep_servicio_nombre` | Nombre del servicio tal como lo vio el cliente |
| `_uep_canal` | `web` (cliente) o `interno` (pedido del equipo) |

Los mismos datos aparecen en la API REST de WooCommerce bajo la clave `uep_envio`. Con la opción **Compatibilidad con ERP** (activa por defecto), el código ISO del departamento se guarda en el campo estándar Estado/Región y el distrito en Ciudad.

## Publicar una versión nueva

1. Actualiza el número de versión en `ubigeo-envio-peru.php` (cabecera `Version` y constante `UEP_VERSION`) y en `readme.txt` (`Stable tag`), y agrega la entrada al changelog de `readme.txt`.
2. Sube los cambios a `main`.
3. Crea y sube la etiqueta:

   ```bash
   git tag v1.16.0 && git push origin v1.16.0
   ```

GitHub Actions comprueba que la etiqueta coincida con la versión del plugin, arma el ZIP instalable y crea la versión en **Releases** con las notas del changelog.

## Licencia

[GPL-2.0-or-later](LICENSE)
