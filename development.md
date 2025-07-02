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
