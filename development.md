# Guía de Desarrollo - Plugin WooCommerce-Wipop

## Requisitos
- **Docker** y **Docker Compose**


## 1. Arrancar WordPress + MySQL
```bash
docker compose up -d db wordpress
```

## 2. Instalar WordPress y WooCommerce
1. Abre <http://localhost:8080> y crea tu usuario y contraseña de administrador.  
2. En **Plugins → Añadir nuevo** busca e instala **WooCommerce**.

## 3. Instalar dependencias y generar el primer build
```bash
docker compose run --rm node bash
cd wipop
npm install
npm run build
```

## 4. Arrancar el plugin con Hot-Reload
```bash
docker compose run --rm node bash
cd wipop
npm run start
```

## 5. Acceder a WordPress
<http://localhost:8080>

## 6. Activar el plugin
En **Plugins → Wipop** activa el plugin.

# Wipop WooCommerce plugin

Para tener linting de WooCommerce en el IDE con Intelephense
tenemos que instalar los *stubs* de WooCommerce como dependencia de desarrollo:

## PHP Linting
```bash
cd wipop
composer require --dev php-stubs/woocommerce-stubs
```

Después añadimos en el IDE la ruta al paquete en la configuración de Intelephense:

```json

  "intelephense.environment.includePaths": [
    "${workspaceFolder}/vendor/php-stubs/woocommerce-stubs"
  ]
```

Es posible que tengamos que subir el límite de max memory de la extensión para que pueda leer los stubs pues superan 1MB


## Comandos útiles

### Actualizar POT
* vendor/bin/wp i18n make-pot . languages/wipop.pot --domain=wipop --allow-root
### Compilar los .PO a .MO
* vendor/bin/wp i18n make-mo languages
### Actualizar mapa de clases
* composer dump-autoload
