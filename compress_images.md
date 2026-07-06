# Estrategia Adaptativa de Optimización de Imágenes — CMS FARO

Este documento detalla la arquitectura de procesamiento y entrega de medios implementada en el comando `site:build` de **CMS FARO**. El diseño resuelve el desafío de unificar múltiples editores (Markdown, RichEditor, NextJS, CKEditor) sin comprometer los recursos de memoria (límite estricto de 512MB RAM) ni requerir reescrituras complejas de cadenas (HTML/JSON) en la base de datos.

---

## 1. ¿Qué hacemos? (El Enfoque Unificado)

En lugar de procesar las imágenes en el momento de la subida (lo que penalizaría la experiencia de usuario en Filament o causaría inconsistencias de enlaces entre editores), **la optimización ocurre de manera agnóstica en el orquestador estático durante la fase de publicación (`site:build`)**.

1. **Desacoplamiento de Editores:** Todos los editores insertan y enlazan imágenes usando extensiones tradicionales (`.jpg`, `.jpeg`, `.png`) apuntando a la ruta física (ej. `/media/foto.jpg`).
2. **Duplicación Coexistente en Destino:** El comando clona los archivos originales hacia el `dist_path` y genera una versión optimizada `.webp` **exactamente al lado de la original**. 
3. **Negociación de Contenido por Servidor Web:** Las páginas HTML estáticas conservan intacto el enlace al `.jpg` original. Es el servidor web quien intercepta la petición del navegador en microsegundos y le sirve de manera transparente el archivo `.webp` en su lugar si el cliente lo soporta.

---

## 2. ¿Qué usamos? (Los 3 Niveles de Ejecución)

El sistema lee la directiva `CMS_MEDIA_DRIVER` del entorno (`.env`) y adopta tres perfiles operativos independientes:

### Nivel A: `none` (Hosting Básico / Por Defecto)

* **Comportamiento:** Copia las imágenes JPG/PNG intactas y finaliza el proceso.
* **Propósito:** Compatibilidad universal asegurada. No requiere extensiones de procesamiento ni permisos especiales en el host.

### Nivel B: `gd` (Hosting Tradicional Estilo WordPress)

* **Comportamiento:** Recorre de forma perezosa (`Lazy`) el directorio de destino. Carga secuencialmente la imagen actual usando las funciones nativas de PHP (`imagecreatefromjpeg`/`imagecreatefrompng`), genera el equivalente `.webp` con `imagewebp()` conservando transparencias, y **destruye el recurso inmediatamente**.
* **Protección OOM:** Invoca explícitamente `imagedestroy($image)` y `gc_collect_cycles()` en cada iteración para garantizar que la memoria RAM de PHP se mantenga estable e inmune a fugas, procesando lotes masivos de a un solo archivo a la vez.

### Nivel C: `cwebp` (Turbo VPS - Nuestro Entorno Dockerizado)

* **Comportamiento:** Ejecuta el binario nativo de Google escrito en C (`cwebp`) a través de un aislamiento de comandos `@exec()`. El procesamiento por streaming se transfiere al procesador a nivel de sistema operativo.
* **Consumo RAM en PHP:** **0 MB adicionales**. La conversión por streaming lee bloques de disco con un consumo de RAM fijo y bajísimo (4-8MB) por fuera de las restricciones del proceso PHP de Laravel.

---

## 3. ¿Cómo habilitarlo?

### Paso 1: Configuración del Sistema (`config/static_cms.php`)

```php
<?php

return [
    'media' => [
        'base_path' => 'media',
        'optimize' => env('CMS_MEDIA_OPTIMIZE', true),
        'driver' => env('CMS_MEDIA_DRIVER', 'none'), // 'none', 'gd', 'cwebp'
        'cwebp_path' => env('CMS_CWEBP_PATH', 'cwebp'),
    ],
];
```

### Paso 2: Variables de Entorno en el VPS (`.env`)

Para activar la máxima potencia de la NASA usando el contenedor Alpine con soporte `libwebp-tools`:

Fragmento de código

```
CMS_MEDIA_OPTIMIZE=true
CMS_MEDIA_DRIVER=cwebp
CMS_MEDIA_PATH=cwebp
```

## 4. Configuración del Servidor Web (Negociación de Contenido)

Para que el HTML del sitio siga llamando a `.jpg` pero el lector reciba un `.webp` ultraliviano de forma transparente sin modificar URLs, configura tu servidor según corresponda:

### ⚡ En Apache (`.htaccess` en la raíz de `dist_path`)

Requiere que el módulo `mod_rewrite` esté activo en el hosting:

Apache

```
<IfModule mod_rewrite.c>
    RewriteEngine On

    # 1. ¿El navegador del lector acepta explícitamente formato WebP?
    RewriteCond %{HTTP_ACCEPT} image/webp

    # 2. ¿Existe físicamente el archivo .webp gemelo al lado del original?
    RewriteCond %{DOCUMENT_ROOT}/$1.webp -f

    # 3. Reescribe internamente la petición sirviendo el WebP con su Content-Type correcto
    RewriteRule ^(.*)\.(jpe?g|png)$ $1.webp [T=image/webp,L]
</IfModule>
```

### 🚀 En Nginx (`nginx.conf` o bloque de servidor en el VPS)

Añade esta directiva dentro del bloque `server` para interceptar las peticiones del directorio estático:

Nginx

```
# Mapeo global fuera del bloque server para verificar soporte WebP
map $http_accept $webp_suffix {
    default   "";
    "~*image/webp" ".webp";
}

server {
    # ... tu configuración de sitio ...

    location ~* ^/media/(?<path>.+)\.(jpe?g|png)$ {
        # Si el cliente acepta webp, intenta buscar "archivo.webp"
        # Si no existe en disco o el cliente no lo soporta, entrega el JPG/PNG original
        try_files /media/$path$webp_suffix $uri =404;
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }
}
```

## 📌 Servidores Heredados y Exóticos

- **IIS (Internet Information Services):** Si te toca desplegar esto en Windows Server, debes configurar un módulo llamado **URL Rewrite** mediante el archivo `web.config` empleando reglas de tipo `<preConditions>` que evalúen el encabezado `{HTTP_ACCEPT}` de forma homóloga a Apache. (Revisar documentación de Microsoft para sintaxis XML).

- **Tomcat / Servidores Java:** "Pregúntale al barbudo". Como Tomcat es un contenedor de servlets estructurado para lógica dinámica de aplicaciones Enterprise y no está optimizado nativamente para servir assets estáticos de alto rendimiento, la recomendación de oro de la vieja escuela es **no poner a Tomcat a servir la carpeta `dist/`**. Pon un Apache o Nginx adelante actuando como Proxy Inverso para despachar los medios estáticos con las reglas de arriba y deja que Tomcat respire tranquilo de fondo.

