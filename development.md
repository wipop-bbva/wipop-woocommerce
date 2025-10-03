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

## 7. QA PHP y estilo de código

- Ejecuta `composer install` dentro de `wipop/` para preparar las dependencias PHP: esto lanza `grumphp git:init`.
- Usa los scripts para validar el código: `composer lint:phpstan`, `composer lint:phpcs` y `composer fix:php`.
- La configuración de PHP-CS-Fixer fuerza indentación con **tabs** para respetar el estándar de WordPress.
- GrumPHP ejecutará PHP-CS-Fixer y PHPStan en cada commit una vez inicializado.

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

## Estilo de código y formateo
Para tener todos el mismo estilo:
* Usar la extensión PHP CS Fixer
* Verificar que usa los settings de .vscode/settings.json
Si queremos hacer un formateo manual:
* vendor/bin/php-cs-fixer fix . --config=.php-cs-fixer.dist.php

## Comandos útiles

### Actualizar .POT
* vendor/bin/wp i18n make-pot . languages/wipop.pot --domain=wipop --allow-root
### Crear los .PO del .POT
 * msginit --no-translator --input=languages/wipop.pot --locale=ca     --output-file=languages/wipop-ca.po
 * msginit --no-translator --input=languages/wipop.pot --locale=eu     --output-file=languages/wipop-eu.po
 * msginit --no-translator --input=languages/wipop.pot --locale=gl_ES --output-file=languages/wipop-gl_ES.po
 * msginit --no-translator --input=languages/wipop.pot --locale=en_US --output-file=languages/wipop-en_US.po
 * msginit --no-translator --input=languages/wipop.pot --locale=es_ES --output-file=languages/wipop-es_ES.po
### Compilar los .PO a .MO
* vendor/bin/wp i18n make-mo languages
### Actualizar mapa de clases
* composer dump-autoload
