# 📈 API de Cotizaciones - Guía de Instalación Local

> API Laravel para gestionar cotizaciones de monedas, conversiones, promedios y fluctuaciones.  

---

## 🧰 Requisitos Previos

Asegúrate de tener instalado en tu máquina:

- PHP 8.1 o superior
- Composer
- Git
- MySQL (u otro motor compatible con Laravel)
- Servidor local (como XAMPP, Laragon, o el servidor integrado de Laravel)

---

## 🚀 Paso 1: Clonar el Repositorio

Abre tu terminal y ejecuta:

```bash
git clone https://github.com/Gerardomedinav/api-cotizaciones.git
cd api-cotizaciones
git checkout feature/evolucion-api
```

---

## 📦 Paso 2: Instalar Dependencias

```bash
composer install
```

---

## 🧩 Paso 3: Configurar Entorno

Copia el archivo de entorno y genera la clave de la app:

```bash
cp .env.example .env
php artisan key:generate
```

Luego, edita el archivo `.env` con tu configuración de base de datos:

```env
APP_NAME="API Cotizaciones"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cotizaciones_api
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
```

> 💡 Crea la base de datos `cotizaciones_api` manualmente en MySQL antes de continuar.

---

## 🗃️ Paso 4: Ejecutar Migraciones

```bash
php artisan migrate
```

---

## 🔄 Paso 5: Iniciar el Servidor

```bash
php artisan serve
```

Tu API estará disponible en:

👉 http://localhost:8000

---

## 🧪 Paso 6: Probar Endpoints

Prueba estas rutas en tu navegador o con Postman:

- **Actualizar cotizaciones:**  
  `GET http://localhost:8000/api/actualizar`

- **Listar cotizaciones:**  
  `GET http://localhost:8000/api/cotizaciones`

- **Convertir monedas:**  
  `GET http://localhost:8000/api/convertir?from=USD&to=ARS&amount=100&tipo=venta`

- **Promedio diario:**  
  `GET http://localhost:8000/api/promedios/diario?moneda=USD&tipo=venta&ano=2025&mes=09&dia=22`

- **Promedio mensual:**  
  `GET http://localhost:8000/api/promedios/mensual?moneda=USD&tipo=venta&ano=2025&mes=09`

- **Promedio anual:**  
  `GET http://localhost:8000/api/promedios/anual?moneda=USD&tipo=venta&ano=2025`

- **Fluctuación:**  
  `GET http://localhost:8000/api/fluctuacion?moneda=USD&tipo=venta&periodo=diario&ano=2025&mes=09&dia=23`

---

## 🛠️ Solución de Problemas Comunes

- Si hay errores de clases:  
  ```bash
  composer dump-autoload
  ```

- Si no se conecta a la base de datos:  
  Verifica `.env` y que la base de datos exista.

- Si el puerto 8000 está ocupado:  
  ```bash
  php artisan serve --port=8080
  ```

---

✅ ¡Listo! Ya puedes usar y probar la API localmente.
