# 🚀 WP CMS - Laravel 11 + Filament + Docker (PHP 8.4)

¡Bienvenido al motor de CMS ultra-optimizado! Este proyecto está diseñado para volar sobre **CachyOS (Arch Linux)**, corriendo un entorno de contenedores minimalista, seguro y rápido como un rayo.

---

## 🔥 Los "Molon Puntos" de la Arquitectura

* **⚡ CachyOS Native Power:** Configurado y optimizado para exprimir el rendimiento de kernels avanzados (`x86-64-v3`/`v4`).
* **🐋 Docker Containers de Alta Pureza:**
    * **`cms-php`**: PHP 8.4-CLI sobre Alpine Linux. Incluye drivers nativos compilados en vivo para SQLite (`pdo_sqlite`), GD, e internacionalización (`intl`).
    * **`cms-node`**: Entorno Node de última generación para compilar assets en tiempo real con Vite.
* **📦 Scripts de Abstracción Total:** Te olvidás de escribir choclos de comandos Docker. Tenés wrappers locales en la raíz (`./artisan`, `./composer`, `./php`) que teletransportan los comandos directo adentro de los contenedores encendidos.
* **💾 Almacenamiento Cero Estresante:** Base de datos **SQLite** embebida en el proyecto. No consume RAM extra levantando servicios pesados de MySQL/Postgres. Ideal para desarrollo ágil y monolitos eficientes.

---

## 🛠️ Comandos de Control Diario

Ya no necesitás interactuar con Docker de forma directa para programar. Usás los scripts locales:

### 🛸 Gestión de Paquetes (Composer)
```bash
./composer require filament/filament
