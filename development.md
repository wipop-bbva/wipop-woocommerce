# Guía de Desarrollo - Plugin WooCommerce-Wipop

**Requisitos**  
* Docker + Docker Compose  

# Arrancar WordPress + MySQL
docker compose up -d db wordpress

# Instalar dependencias y generar el primer build
docker compose run --rm node bash 
(dentro)
cd wipop && npm install && npm run build

# Arramcar plugin con hot-reaload
docker compose run --rm node bash 
(dentro)
cd wipop && npm run start

# Acceder a WP: 
http://localhost:8080

# Activar el plugin en **Plugins → Wipop**