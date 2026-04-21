# Plugin WooCommerce de Wipop

Plugin de WooCommerce para integrar la pasarela de pagos de Wipöp, permitiendo a los usuarios de tu e-commerce con WordPress realizar pagos de forma sencilla.
Incluye pasarelas listas para producción de tarjeta y Bizum. Configura las credenciales entre todos los métodos y mantiene los pedidos de WooCommerce sincronizados con las transacciones que reporta Wipöp.

## Funcionalidades

- **Pagos con tarjeta**
  - Experiencia de checkout con redirección usando los enlaces de pago de Wipöp.
  - Tokenización COF integrada para guardar tarjetas y habilitar cargos one-click o recurrencias personalizadas.
  - Soporte de preautorizaciones con acciones de pedido para capturar o anular.
  - Reembolsos totales o parciales desde la pantalla del pedido en WooCommerce.
- **Pagos Bizum**
  - Botón específico en el checkout que crea un cargo Bizum.
- **Google Pay**
  - Soporte pendiente.
- **Sincronización del comercio y herramientas de administración**
  - Página de ajustes centralizada en `WooCommerce > Wipöp` con entorno, claves, terminal y modo de captura.
  - Verificación de credenciales que guarda en los métodos permitidos.
  - Pestaña “Pago recurrente” en los productos más el WP-Cron que programa cargos MIT mensuales/anuales reutilizando los tokens COF guardados.
- **Actualización de los estados de los pedidos**
  - Endpoint de webhook `https://tu-tienda.com/?wc-api=wipop_bbva` que actualiza pedidos ante eventos de confirmación, fallo, reembolso, captura y anulación.
  - Registros a través del logger de WooCommerce para auditar cada acción.

## Requisitos

- PHP 8.1 o superior.
- WordPress con WooCommerce instalado y activo.
- Cuenta de comercio Wipöp con credenciales de Sandbox y Producción (Merchant ID, Terminal ID, Public Key, Private Key).

## Instalación

### Instalar desde ZIP (tiendas)

1. Descarga el paquete `wipop.zip` generado en el pipeline o en la build que utilices.
2. En WordPress ve a `Plugins → Añadir nuevo → Subir plugin`, selecciona el ZIP y pulsa **Instalar ahora**.
3. Activa el plugin y continúa con la configuración.

### Desarrollo local
- Sigue la guía de desarrollo en [development.md](development.md).

## Antes de empezar

- Completa el alta con Wipöp para disponer de terminales de Sandbox y Producción.
- Reúne todas las credenciales del plugin: Merchant ID, Terminal ID, Public Key y Private Key.
- Define el entorno con el que vas a trabajar y asegúrate de que la URL de la tienda admite HTTPS para poder recibir webhooks.
- Copia en el portal el usuario y contraseña de webhook que se muestran en `WooCommerce > Wipop` al igual que la url del webhook.

## Inicio rápido

1. Entra en `WooCommerce > Wipop` y completa Merchant ID, Terminal, claves públicas/privadas y el entorno deseado.
2. Elige el modo de **Preautorizaciones** (`Cobrar en el momento de la compra` o `Reservar el importe para cobrarlo después`) para los cobros con tarjeta.
3. Pulsa **Verificar datos** para validar las credenciales y obtener los métodos de pago permitidos.
4. Ve a `WooCommerce > Pagos` y activa las pasarelas que quieres mostrar (Tarjeta, Bizum, Google Pay).
5. Realiza un pedido en Sandbox para comprobar el flujo. Asegúrate de que los metadatos de Wipöp se guardan y de que el webhook actualiza el estado.
6. Opcional: marca un producto como recurrente (Editar producto → pestaña Pago recurrente) para que, tras el primer cobro, el plugin programe los MIT automáticos según la periodicidad elegida.

## Configuración

### Credenciales y entorno

Los ajustes se guardan en la opción `wipop_settings`. Campos obligatorios:

- `Merchant ID`: en Wipöp.
- `Public Key` / `Private Key`: claves criptográficas para firmar las peticiones.
- `Terminal ID`: número entre 0 y 99.
- `Entorno`: Sandbox o Producción.

### Captura manual / preautorización

Selecciona `Reservar el importe para cobrarlo después` en **Preautorizaciones** si quieres que los cargos con tarjeta queden autorizados pero pendientes de captura. Cuando está activo:

- El pedido pasa a `en espera` con una nota indicando que falta capturar.
- Aparecen las acciones *Capturar preautorización con Wipöp* y *Anular preautorización con Wipöp* en el desplegable de acciones del pedido.
- Al ejecutarlas se llaman a `charge.capture` o `charge.reversal` mediante el SDK.
- Las preautorizaciones suelen tener un periodo de validez de una semana.

Con `Cobrar en el momento de la compra` (comportamiento por defecto) la captura se confirma en el mismo momento del cargo.

### Métodos de pago

El plugin consulta los métodos de pago disponibles y solo registra las pasarelas devueltas por Wipöp. Activa o desactiva cada método desde `WooCommerce > Pagos`.

### Webhooks

Expón `https://{tu-dominio}/?wc-api=wipop_bbva` en el portal de Wipöp usando las credenciales mostradas en `WooCommerce > Wipop`. El webhook sincroniza:

- Cambios de estado del pedido (pendiente → en espera → procesando/completado → fallido).
- Metadatos de captura manual.
- Identificadores de transacción, datos enmascarados de tarjeta y tokens almacenados.
- Eventos de reembolso.
- Eventos de verificación para marcar el webhook como conectado en el panel de administración.

### Productos recurrentes

Al editar productos verás la pestaña **Pago recurrente**. Márcala y escoge `Mensual` o `Anual` para que los artículos queden etiquetados como recurrentes. Cuando el primer cobro se marca como pagado el plugin:
- Verifica que el cliente tiene un token guardado.
- Agrupa los importes por periodicidad y guarda en el pedido el metadato con la cantidad, próxima fecha y contadores de ejecución.
- Agenda el job mediante WP-Cron. 
- Añade notas en el pedido con cada intento y detiene la recurrencia si el pedido queda inactivo, faltan datos de tarjeta/token, el importe es inválido o falla un cargo recurrente.

Además, los cobros recurrentes se pueden detener manualmente desde las acciones del pedido con **Detener cobros recurrentes con Wipop**.

Los cobros recurrentes se desactivan automáticamente si el pedido pasa a cancelado/reembolsado/fallido, si se elimina el pedido o si el cliente borra la tarjeta (no se encuentra token COF).

## Gestión de errores y logs

- Los fallos de la API muestran avisos en el administrador
- Los registros detallados están en `WooCommerce > Estado > Registros`.
