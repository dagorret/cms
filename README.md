# CMS Faro

**CMS y generador de sitios estáticos basado en Laravel, Filament y SQLite**

![PHP](https://img.shields.io/badge/PHP-8.4%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Filament](https://img.shields.io/badge/Filament-5-FDAE4B?style=for-the-badge&logo=filament&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-3-003B57?style=for-the-badge&logo=sqlite&logoColor=white)
![Static HTML](https://img.shields.io/badge/Output-Static_HTML-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![Debian](https://img.shields.io/badge/Production-Debian-A81D33?style=for-the-badge&logo=debian&logoColor=white)
![License](https://img.shields.io/badge/license-CC_BY--NC_4.0-7C3AED?style=for-the-badge)

---

## A) ¿Qué es CMS Faro?

**CMS Faro es un CMS con un generador de sitios estáticos.**

Laravel, Filament, PHP y SQLite forman el entorno de administración y compilación. No forman parte del sitio público resultante.

Faro permite escribir, organizar y administrar contenido mediante una interfaz web y luego compilar ese contenido a un árbol de archivos estáticos autocontenido.

El resultado de la compilación es `dist/`.

Dentro de `dist/` viven los HTML, CSS, JavaScript, XML, JSON, imágenes y demás recursos que constituyen el sitio publicado.

Una vez terminada la compilación, el sitio público:

- no ejecuta Laravel;
- no ejecuta PHP;
- no consulta SQLite;
- no necesita Node.js;
- no necesita workers;
- no necesita sesiones;
- no realiza queries para servir una página;
- no necesita contenedores;
- no necesita un servidor de aplicaciones.

Sólo necesita un servidor capaz de entregar archivos.

Nginx, Apache, Caddy, un CDN, object storage o un hosting estático son suficientes.

En ese sentido, el resultado de Faro pertenece a la misma familia conceptual que los sitios generados por **Hugo, Astro, Pelican o Jekyll**.

La diferencia principal está en la experiencia de autoría: Faro utiliza un CMS web y una base SQLite como fuente editorial.

> **Faro necesita PHP, Laravel y SQLite para administrar y construir el sitio. El sitio publicado no necesita ninguno de ellos.**

---

## B) La arquitectura

La separación entre administración y publicación es deliberada.

```text
                 AUTORÍA / ADMINISTRACIÓN

        ┌───────────────────────────────┐
        │           CMS Faro            │
        │                               │
        │ Laravel + Filament            │
        │ SQLite                        │
        │ EditorJS                      │
        │ Markdown / HTML               │
        │ Media Library                 │
        │ MathJax                       │
        └───────────────┬───────────────┘
                        │
                        │ site:build
                        ▼
        ┌───────────────────────────────┐
        │             dist/             │
        │                               │
        │ HTML                          │
        │ CSS                           │
        │ JavaScript                    │
        │ XML / JSON                    │
        │ imágenes y medios             │
        └───────────────┬───────────────┘
                        │
                        │ publicación
                        ▼

                     PRODUCCIÓN

        ┌───────────────────────────────┐
        │            Nginx              │
        │                               │
        │     archivos estáticos        │
        └───────────────────────────────┘

              PHP        ✗
              Laravel    ✗
              SQLite     ✗
              Node.js    ✗
              workers    ✗
              sesiones   ✗
              queries    ✗
```

El CMS puede incluso estar temporalmente fuera de servicio después de una compilación y el sitio público continuará funcionando normalmente.

`dist/` es el producto de Faro.

---

## C) SQLite como fuente editorial

Faro utiliza **SQLite** como fuente de verdad del contenido.

Esto elimina la necesidad de mantener un servidor MySQL, PostgreSQL u otro servicio de base de datos independiente para el CMS.

La base editorial es esencialmente un archivo:

```text
database/database.sqlite
```

Allí se almacenan los sitios, posts, páginas, categorías, etiquetas y demás estructuras editoriales.

Los medios viven separadamente en:

```text
storage/app/public/
```

Esta arquitectura tiene una consecuencia operativa importante:

> **Respaldar Faro consiste fundamentalmente en respaldar un archivo SQLite y el directorio de medios.**

El sitio generado (`dist/`) es reproducible y puede volver a construirse a partir de esos datos.

---

## D) Edición de contenido

Faro permite trabajar con diferentes formas de contenido sin acoplar el sitio publicado al editor utilizado para crearlo.

El CMS soporta:

- **EditorJS**, para edición estructurada por bloques;
- **Markdown**, incluyendo bloques Markdown dentro de EditorJS;
- **HTML enriquecido**, cuando se utiliza el editor correspondiente;
- imágenes y medios mediante **Spatie Media Library**;
- fórmulas matemáticas mediante **MathJax**.

El contenido es normalizado durante el proceso de renderizado y posteriormente convertido al HTML estático que se publica.

### Matemáticas

Los posts pueden marcarse como contenido matemático y utilizar expresiones LaTeX, tanto inline como display:

```latex
$E = mc^2$
```

o:

```latex
$$
D = \operatorname{diag}(d_1, d_2, \dots, d_n)
$$
```

MathJax se incorpora únicamente donde corresponde, evitando imponer ese costo al resto del sitio.

---

## E) Modelo editorial

Faro diferencia la naturaleza del contenido de su clasificación editorial.

Los tipos estructurales principales son:

```text
post
page
```

Los posts pueden pertenecer a categorías administrables desde el CMS.

Las categorías son datos reales de la base y no constantes hardcodeadas en la aplicación. Esto permite ampliar y reorganizar la estructura editorial sin modificar código.

Faro también dispone de etiquetas para relaciones editoriales adicionales.

El objetivo es mantener una estructura sencilla:

```text
Sitio
 ├── Posts
 │    ├── Categoría
 │    └── Tags
 │
 ├── Pages
 ├── Menús
 └── Medios
```

---

## F) Generación estática

Faro traslada el costo computacional al momento de publicación.

Cuando se ejecuta una compilación, el motor lee SQLite, renderiza las plantillas Blade y escribe el resultado final en el filesystem.

El servidor público no realiza ese trabajo nuevamente por cada visitante.

Conceptualmente:

```text
modelo + contenido + Blade
            │
            ▼
         compiler
            │
            ▼
          HTML
            │
            ▼
          dist/
```

Esto transforma un problema de renderizado dinámico por petición en un problema de compilación.

La misma página solicitada diez, cien o diez millones de veces continúa siendo el mismo archivo.

---

## G) Qué genera Faro

Una compilación completa puede generar, entre otras estructuras:

- posts individuales;
- páginas;
- portada;
- paginación;
- categorías;
- archivo histórico;
- feeds;
- sitemap;
- datos estructurados;
- assets;
- medios necesarios para la publicación.

Una estructura simplificada puede verse así:

```text
dist/
├── index.html
├── page/
│   ├── 2/
│   │   └── index.html
│   └── ...
├── categoria/
│   └── ...
├── archivo/
│   └── ...
├── slug-del-post/
│   └── index.html
├── assets/
├── media/
├── feed.xml
└── sitemap.xml
```

La estructura exacta depende de la configuración y del sitio compilado.

---

## H) Compilación completa, incremental e individual

Faro no necesita reconstruir necesariamente todo el corpus ante cada modificación.

El sistema distingue entre compilaciones completas e incrementales.

### Sitio completo

```bash
./php artisan site:build ensayos
```

### Sitio completo forzado

```bash
./php artisan site:build ensayos --force
```

### Un único post

```bash
./php artisan site:build ensayos --post=5
```

### Un único post forzado

```bash
./php artisan site:build ensayos --post=5 --force
```

El CMS registra el estado de compilación y puede evitar regenerar entradas que no cambiaron.

Si detecta que falta físicamente una salida publicada, puede recuperar esa salida aunque el registro figure previamente como compilado.

El objetivo es combinar dos propiedades:

- builds cotidianos pequeños;
- capacidad de reconstruir el sitio completo cuando sea necesario.

---

## I) Medios

Los medios se administran mediante **Spatie Media Library**.

Faro puede almacenar:

- archivo original;
- conversiones;
- previews;
- responsive images.

El CMS dispone además de una biblioteca de medios para inspeccionar:

- nombre;
- MIME;
- tamaño;
- colección;
- post propietario;
- uso actual;
- fecha.

Los medios referenciados por un post están protegidos contra eliminación accidental desde la biblioteca.

Cuando un archivo deja de estar referenciado puede identificarse como huérfano y eliminarse junto con sus conversiones.

---

# J) Rendimiento y escala

Faro fue diseñado con una premisa concreta: el tamaño del corpus editorial no debería obligar al sitio público a mantener una infraestructura dinámica equivalente.

Las pruebas realizadas muestran además que el consumo de memoria puede mantenerse moderado incluso al procesar cientos de miles de entradas.

Es importante distinguir entre **mediciones reales** y **proyecciones**.

## Benchmark optimizado de 30.000 posts

Una prueba posterior del compilador procesó:

```text
Posts:          30.000
Tiempo:         33 segundos
Memoria pico:   124 MB
Throughput:     ~900 posts/s
Salida dist/:   ~520 MB
```

La prueba se realizó mediante CLI en un único proceso.

El tamaño de `dist/` no representa solamente el peso lógico del HTML. Miles de archivos pequeños introducen overhead de bloques, metadata e inodos en el filesystem utilizado.

---

## Stress test masivo

También se realizó una corrida específicamente orientada a estudiar el comportamiento con un corpus mucho mayor.

La prueba estaba configurada para:

```text
300.000 posts
300 lotes
1.000 posts por lote
```

El log conservado permite confirmar mediciones hasta **245.000 posts procesados**.

| Posts procesados | Tiempo | Memoria pico | Estado |
|------------------:|-------:|-------------:|:------|
| 30.000 | 100,99 s | 74,5 MB | Medido |
| 100.000 | 341,02 s | 114,5 MB | Medido |
| 150.000 | 515,47 s | 142,5 MB | Medido |
| 200.000 | 720,52 s | 170,5 MB | Medido |
| 245.000 | 885,44 s | 196,5 MB | Medido |
| 300.000 | ~18 min | ~220–230 MB | Proyección |

A los **245.000 posts**, la corrida llevaba:

```text
Tiempo:          885,44 s
                 14 min 45 s

Memoria actual:  187,51 MB
Memoria pico:    196,5 MB
```

El throughput observado durante esa prueba se mantuvo aproximadamente entre:

```text
275–295 posts/s
```

La línea correspondiente a `300000` no está presente en el log conservado. Por eso los valores de 300K son una **extrapolación**, no un benchmark medido.

### Qué mostró realmente la prueba

La conclusión más interesante no fue el tiempo.

Fue la memoria.

Incluso cerca de un cuarto de millón de posts, el proceso continuaba por debajo de **200 MB de RAM pico**.

El límite práctico comenzó a aparecer antes en otro lugar:

> **filesystem, I/O y cantidad de archivos/inodos.**

Al generar cientos de miles de documentos estáticos, cada entrada termina transformándose en uno o varios objetos físicos del filesystem.

En esa escala empiezan a importar:

- cantidad disponible de inodos;
- costo de creación de archivos;
- metadata del filesystem;
- tamaño de bloque;
- directorios;
- I/O sostenido;
- características de ext4, XFS, btrfs u otro filesystem;
- estrategia de despliegue.

La aplicación puede continuar teniendo memoria disponible mientras el almacenamiento se convierte en el verdadero cuello de botella.

---

## K) ¿Un millón de posts?

Las pruebas no constituyen un benchmark medido de uno o dos millones de documentos.

Sin embargo, permiten observar una propiedad importante: el consumo de memoria crece suficientemente despacio como para que la RAM no aparezca como el primer límite inmediato.

Con estrategias adecuadas de:

- chunking;
- consultas selectivas;
- procesamiento incremental;
- liberación temprana de colecciones;
- escritura secuencial;
- organización del árbol de salida;
- filesystem dimensionado correctamente;
- suficiente cantidad de inodos;

un corpus del orden de **1 a 2 millones de documentos** resulta técnicamente abordable sin requerir cantidades extraordinarias de memoria.

Para esas escalas, un horizonte operativo del orden de **2–3 horas** debe entenderse como una **estimación de planificación**, no como un benchmark actualmente demostrado.

La siguiente etapa de optimización de Faro está precisamente orientada a perfilar el compilador y determinar qué funciones retienen memoria, qué estructuras pueden convertirse a streaming y cuánto del tiempo total pertenece realmente a PHP frente al filesystem.

La optimización se hará sobre mediciones, no sobre suposiciones.

---

## L) Metodología del stress test

Los posts utilizados para las pruebas masivas no eran entradas vacías.

El `StressTestSeeder` generó cuerpos de prueba con una estructura equivalente a:

```php
$cuerpoAleatorio =
    "## ".$faker->sentence()."\n\n".
    $faker->paragraphs(rand(20, 40), true);
```

Cada entrada contenía entre 20 y 40 párrafos generados.

Por lo tanto, el ensayo buscaba aproximarse a artículos long-form y no simplemente medir la creación de archivos HTML vacíos.

---

# M) Backups

Una de las ventajas operativas de utilizar SQLite es que la fuente editorial resulta muy sencilla de respaldar.

Hay dos elementos esenciales:

```text
database/database.sqlite
storage/app/public/
```

`dist/` puede respaldarse si se desea, pero no constituye la fuente de verdad: puede regenerarse.

## Backup de SQLite

Para una instalación con actividad editorial controlada, una copia periódica puede automatizarse mediante cron.

Una opción simple:

```bash
#!/bin/sh

set -e

BACKUP_DIR="/var/backups/faro"
STAMP="$(date +%Y-%m-%d_%H-%M-%S)"

mkdir -p "$BACKUP_DIR"

cp /var/www/faro/database/database.sqlite \
   "$BACKUP_DIR/database-$STAMP.sqlite"
```

Por ejemplo, mediante cron:

```cron
0 3 * * * /usr/local/sbin/backup-faro-db
```

En instalaciones con escrituras concurrentes o donde se quiera garantizar un snapshot consistente mientras SQLite está activo, debe utilizarse el mecanismo de backup de SQLite en lugar de depender únicamente de `cp`.

Por ejemplo:

```bash
sqlite3 /var/www/faro/database/database.sqlite \
    ".backup '/var/backups/faro/database-latest.sqlite'"
```

La estrategia concreta debe ajustarse al patrón de escrituras del CMS.

---

## Backup de medios

Los medios pueden empaquetarse con `tar`:

```bash
tar -czf \
    "/var/backups/faro/media-$(date +%Y-%m-%d_%H-%M-%S).tar.gz" \
    -C /var/www/faro/storage/app \
    public
```

Esto respalda originales, conversiones y demás archivos gestionados por Media Library dentro de `storage/app/public`.

También puede ejecutarse periódicamente mediante cron.

---

## Qué respaldar

### Esencial

```text
database/database.sqlite
storage/app/public/
.env
```

El código fuente debería estar además versionado en Git.

### Regenerable

```text
dist/
```

Si se conserva:

```text
código
+ configuración
+ SQLite
+ medios
```

el sitio estático puede reconstruirse.

---

# N) Despliegue recomendado

Una instalación típica puede separar conceptualmente el CMS del sitio publicado:

```text
faro.dagorret.com.ar
        │
        │ Laravel + Filament + SQLite
        │ administración
        │
        ▼
      site:build
        │
        ▼
       dist/
        │
        ▼
dagorret.com.ar
        │
        ▼
      Nginx
```

Ambos dominios pueden estar en el mismo servidor.

La separación es lógica:

```text
faro.dagorret.com.ar = aplicación administrativa
dagorret.com.ar      = sitio estático
```

El tráfico público normal nunca necesita entrar en Laravel.

---

# O) Instalación nativa en Debian

Docker es útil para desarrollo y para entornos reproducibles, pero **no es un requisito arquitectónico de Faro**.

El CMS puede ejecutarse nativamente en Debian.

La instalación concreta depende de la versión de Debian y de PHP disponible, pero conceptualmente requiere:

- Nginx;
- PHP 8.4+;
- PHP-FPM;
- extensiones PHP requeridas por Laravel;
- SQLite;
- Composer;
- Node.js/npm para construir assets;
- Git.

Ejemplo orientativo:

```bash
sudo apt update

sudo apt install \
    nginx \
    sqlite3 \
    git \
    unzip \
    curl
```

PHP y sus extensiones deben instalarse desde los paquetes apropiados para la versión de Debian utilizada.

Entre las extensiones normalmente requeridas por la aplicación se encuentran:

```text
cli
fpm
sqlite3
mbstring
xml
curl
zip
intl
gd
```

No conviene copiar ciegamente nombres de paquetes entre versiones de Debian: deben verificarse contra la versión de PHP instalada.

---

## 1. Clonar Faro

```bash
sudo mkdir -p /var/www
cd /var/www

sudo git clone https://github.com/dagorret/cms.git faro
sudo chown -R "$USER":www-data /var/www/faro

cd /var/www/faro
```

---

## 2. Instalar dependencias PHP

```bash
composer install \
    --no-dev \
    --optimize-autoloader
```

---

## 3. Configurar Laravel

```bash
cp .env.example .env
php artisan key:generate
```

Configurar como mínimo:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://faro.dagorret.com.ar
```

La configuración SQLite debe apuntar a la base del proyecto.

Crear la base si corresponde:

```bash
touch database/database.sqlite
```

Ejecutar migraciones:

```bash
php artisan migrate --force
```

---

## 4. Assets

Node.js sólo es necesario en el servidor si se desea compilar allí los assets.

```bash
npm ci
npm run build
```

Una vez generados los assets de producción, Node.js no participa en las peticiones HTTP del CMS ni del sitio estático.

También es posible compilar assets en otro entorno y desplegar el resultado correspondiente.

---

## 5. Permisos

Laravel necesita escribir al menos en:

```text
storage/
bootstrap/cache/
database/
```

Por ejemplo:

```bash
sudo chown -R www-data:www-data \
    storage \
    bootstrap/cache \
    database

sudo chmod -R ug+rwX \
    storage \
    bootstrap/cache \
    database
```

Los permisos exactos deben adaptarse al usuario utilizado para realizar builds y al usuario de PHP-FPM.

No utilizar `chmod 777`.

---

## 6. Optimizar Laravel

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

# P) Nginx para el CMS

Ejemplo conceptual para:

```text
faro.dagorret.com.ar
```

```nginx
server {
    listen 80;
    server_name faro.dagorret.com.ar;

    root /var/www/faro/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

La ruta del socket PHP-FPM debe ajustarse a la instalación real.

En producción debe configurarse HTTPS.

---

# Q) Nginx para el sitio generado

El sitio público no necesita PHP.

Si Faro genera directamente en:

```text
/var/www/dist
```

Nginx puede servir ese directorio:

```nginx
server {
    listen 80;
    server_name dagorret.com.ar www.dagorret.com.ar;

    root /var/www/dist;
    index index.html;

    location / {
        try_files $uri $uri/ $uri/index.html =404;
    }
}
```

Eso es todo lo que necesita el sitio para responder páginas.

No hay `fastcgi_pass`.

No hay Laravel.

No hay conexión a SQLite.

No hay proceso PHP involucrado en una visita pública.

---

# R) Publicación

Una publicación completa puede ejecutarse desde el servidor donde vive Faro:

```bash
cd /var/www/faro
php artisan site:build ensayos
```

Si `dist_path` apunta directamente al directorio servido por Nginx, la salida queda inmediatamente disponible.

Otra posibilidad es compilar en un directorio separado y sincronizar:

```bash
rsync -a --delete \
    /var/www/faro/dist/ \
    /var/www/dist/
```

Para despliegues de gran tamaño resulta recomendable evolucionar hacia publicación atómica:

```text
dist-next/
     │
     │ build completo
     ▼
validación
     │
     ▼
swap / rename
     │
     ▼
dist/
```

Así Nginx nunca observa un árbol parcialmente generado durante una reconstrucción completa.

---

# S) Shared Hosting

Faro tampoco obliga a que el CMS y el sitio público vivan en el mismo servidor.

`dist/` puede empaquetarse:

```bash
tar -czf sitio.tar.gz -C dist .
```

y enviarse mediante:

- SFTP;
- FTP;
- rsync;
- panel de hosting;
- almacenamiento de objetos;
- CDN.

Un hosting que sólo sepa entregar HTML puede publicar el resultado.

---

# T) Docker

El proyecto dispone de un entorno Docker para desarrollo y ejecución reproducible.

Docker resulta especialmente útil para:

- desarrollo local;
- aislar versiones;
- reproducir PHP/Node;
- ejecutar tests;
- evitar contaminar el host.

No debe confundirse esto con un requisito del sitio publicado.

La arquitectura es:

```text
Docker          opcional
Laravel         sólo CMS/build
PHP             sólo CMS/build
SQLite          sólo CMS/build
Nginx estático  suficiente para producción pública
```

---

# U) Por qué creé Faro

## 1. La fricción del flujo editorial tradicional

Antes de Faro estaba Hugo, y con Hugo estaba la fricción.

Publicar significaba entrar por SSH y correr el despliegue, o subir Markdown a GitHub y bajarlo del otro lado, o sincronizar el sitio completo de forma diferencial vía SSH.

Eso dependía de una computadora concreta y de una red que permitiera ese flujo.

La fricción no aparecía solamente ante builds masivos. También existía al publicar un artículo sencillo.

En términos editoriales, quien decidía qué publicar y cuándo terminaba condicionado por su propia infraestructura técnica.

Faro nació para separar esas cosas.

Quería conservar las propiedades de un sitio estático sin renunciar a una interfaz editorial web.

---

## 2. Un CMS delante de un generador estático

La idea inicial no era construir simplemente otro CMS dinámico.

Tampoco quería abandonar las ventajas que había encontrado en generadores como Hugo o Pelican.

La pregunta pasó a ser:

> ¿Por qué no conservar un CMS cómodo para escribir y administrar, pero hacer que su producto final vuelva a ser un conjunto de archivos estáticos?

Ahí apareció Faro.

Laravel y Filament podían resolver muy bien el problema editorial.

SQLite podía mantener la fuente de datos sencilla y portable.

Y una etapa posterior podía transformar todo eso en HTML.

```text
comodidad editorial
        +
base de datos sencilla
        +
compilación
        =
sitio estático
```

---

## 3. Los grandes medios como referencia de escala

El objetivo no era copiar visualmente a los grandes medios.

The New York Times, BBC y otras publicaciones de gran escala sirvieron como referencia para formular otra pregunta:

> ¿Cómo se organiza un corpus enorme para que siga siendo navegable, indexable y operativamente razonable?

De allí surgen estructuras familiares:

- una portada jerárquica;
- paginación;
- archivos;
- categorías;
- feeds;
- sitemaps;
- enlaces navegables.

Son estructuras editoriales, pero también estructuras de descubrimiento.

Faro intenta trasladar esa disciplina a un sistema mucho más pequeño y controlable.

---

## 4. El costo debe pagarse una vez

Un CMS dinámico tradicional puede repetir parte del trabajo ante cada request.

Faro intenta pagar ese costo durante la compilación:

```text
                BUILD

SQLite ──► Blade ──► HTML
                     │
                     ▼
                    disco


             REQUEST PÚBLICO

cliente ──► Nginx ──► archivo
```

La segunda ruta es deliberadamente aburrida.

Y esa es una característica.

---

## 5. El filesystem también es infraestructura

Las pruebas masivas mostraron algo importante.

Cuando el corpus crece suficientemente, la discusión deja de ser solamente:

```text
¿cuánta RAM consume PHP?
```

y pasa a ser también:

```text
¿cuántos archivos estoy creando?
¿cuántos inodos tengo?
¿cómo responde el filesystem?
¿cuánto cuesta el I/O?
¿cómo despliego cientos de miles de objetos?
```

A 245.000 posts, la memoria pico medida seguía por debajo de 200 MB.

El filesystem comenzó a ser una preocupación antes que la memoria.

Eso cambia el foco de la siguiente fase de optimización.

No se trata solamente de hacer PHP más rápido.

Se trata de diseñar correctamente el proceso completo:

```text
SQLite
   │
   ▼
queries
   │
   ▼
render
   │
   ▼
memoria
   │
   ▼
filesystem
   │
   ▼
publicación
   │
   ▼
Nginx
```

---

## 6. Iluminar lo escondido

La razón de fondo por la que existe Faro no es únicamente el rendimiento.

Un día busqué quién había sido el gobernador de Nueva York en 1904 y qué había hecho durante su gestión.

El dato existía.

Estaba en archivos y textos.

Pero no aparecía fácilmente en la superficie de Internet.

Hay una cantidad enorme de información legítima que no está deliberadamente oculta: simplemente está demasiado profunda, mal estructurada o insuficientemente conectada para ser descubierta.

Faro nace también de esa preocupación.

Publicar no consiste solamente en guardar documentos.

Consiste en darles:

- una URL;
- una estructura;
- enlaces;
- contexto;
- categorías;
- archivo;
- sitemap;
- posibilidad de ser rastreados.

Por eso el nombre.

**Faro no sólo busca compilar rápido. Busca iluminar lo que estaba escondido.**

---

# V) Estado del proyecto

Faro ya dispone de los componentes fundamentales de un CMS y generador estático operativo:

- administración mediante Filament;
- SQLite;
- múltiples sitios;
- posts y páginas;
- categorías;
- etiquetas;
- menús;
- EditorJS;
- Markdown;
- HTML enriquecido;
- MathJax;
- Media Library;
- previews y conversiones de imágenes;
- biblioteca de medios;
- builds completos;
- builds incrementales;
- builds individuales;
- portada;
- paginación;
- archivo;
- categorías estáticas;
- feeds;
- sitemap;
- publicación a `dist/`.

La siguiente etapa principal no consiste en agregar más funcionalidades editoriales.

Consiste en **perfilar y afilar el compilador**:

- localizar retenciones innecesarias de memoria;
- medir cada etapa;
- reducir colecciones residentes;
- mejorar streaming/chunking;
- estudiar filesystem e inodos;
- optimizar builds de escala extrema;
- preparar publicación atómica.

---

# W) Repositorio

Código fuente:

https://github.com/dagorret/cms

---

# X) Licencia

**Creative Commons Atribución-NoComercial 4.0 Internacional (CC BY-NC 4.0)**

CMS Faro se distribuye bajo esta licencia.

Cualquier persona es libre de:

- **Compartir** — copiar y redistribuir el material en cualquier medio o formato.
- **Adaptar** — remezclar, transformar y construir a partir del material.

Bajo los siguientes términos:

- **Atribución** — debe darse crédito de manera adecuada, proveer un enlace a la licencia e indicar si se realizaron cambios.
- **No Comercial** — el material no puede utilizarse con fines comerciales sin autorización expresa del autor.

Texto legal:

https://creativecommons.org/licenses/by-nc/4.0/deed.es

---

## CMS Faro

**Un CMS para escribir. Un compilador para publicar. Archivos estáticos para servir.**


---

# Y) Misión cumplida

Faro nació de una necesidad concreta: recuperar la comodidad de publicar desde un CMS sin renunciar a las propiedades de un sitio completamente estático.

Ese objetivo ya está cumplido.

El contenido se escribe y administra desde una interfaz web, se almacena en SQLite y finalmente se transforma en archivos estáticos que Nginx puede servir sin Laravel, PHP ni una base de datos en el camino público.

Faro ya se utiliza para publicar este sitio:

- [Soberanía digital 00.1](https://dagorret.com.ar/soberania-digital-00-1/)
- [La odisea del MathML estático en Hugo: operadores elásticos y soberanía local](https://dagorret.com.ar/la-odisea-del-mathml-estatico-en-hugo-operadores-elasticos-y-soberania-local/)

Esos documentos recorren el pipeline completo:

```text
autor
  │
  ▼
CMS Faro
  │
  ├── Filament
  ├── EditorJS / Markdown
  ├── MathJax
  ├── Media Library
  └── SQLite
  │
  ▼
compilador
  │
  ▼
dist/
  │
  ├── HTML
  ├── CSS
  ├── JavaScript
  ├── XML / JSON
  └── medios
  │
  ▼
Nginx
  │
  ▼
Internet
```

# Z) Roadmap

## Faro 2.5 — Pulido funcional

La rama 2.x ya cumple el objetivo arquitectónico principal de Faro.

La versión **2.5** estará dedicada principalmente a completar y pulir funcionalidades existentes, corregir problemas encontrados durante el uso real y mejorar la experiencia diaria del CMS.

No pretende cambiar la arquitectura del generador.

Entre los trabajos previstos se encuentran:

- correcciones funcionales surgidas del uso cotidiano;
- mejoras de la interfaz administrativa;
- ajustes de EditorJS, Markdown y HTML;
- mejoras en el manejo de medios;
- pequeños ajustes de categorías, menús y navegación;
- correcciones del pipeline matemático MathJax/LaTeX;
- mejoras en mensajes, validaciones y diagnósticos;
- endurecimiento de casos límite del build incremental;
- mejoras menores en la operación y despliegue.

La prioridad de 2.5 será:

> **hacer más sólido y cómodo lo que Faro ya sabe hacer.**

---

# Faro 3 — El compilador afilado

Faro 3 tendrá un objetivo diferente.

No será principalmente una versión de nuevas funciones editoriales.

Será una revisión profunda del proceso que transforma SQLite en cientos de miles —y potencialmente millones— de archivos estáticos.

Las pruebas realizadas hasta ahora dieron una pista importante.

A los 245.000 posts:

```
Tiempo:          885,44 s                 14 min 45 sMemoria actual:  187,51 MBMemoria pico:    196,5 MB
```

La RAM todavía no era el límite inmediato.

Antes comenzaron a importar el filesystem, los inodos y el I/O.

Faro 3 parte de esa observación.

La pregunta ya no será solamente:

> ¿Cómo hacemos más rápido el código PHP?

La pregunta será:

> **¿Cuál es la forma más eficiente de transformar una base SQLite enorme en un árbol estático enorme?**

## 1. Medir antes de optimizar

La primera tarea de Faro 3 será instrumentar completamente el compilador.

Cada etapa importante deberá poder informar, como mínimo:

```
tiempomemoria actualmemoria picocantidad de registroscantidad de archivos escritosbytes escritos
```

El build deberá poder descomponerse conceptualmente en:

```
inicio  │  ├── selección de posts  ├── carga desde SQLite  ├── render de posts  ├── escritura de posts  ├── páginas  ├── portada  ├── categorías  ├── archivos  ├── feeds  ├── sitemap  ├── schemas / metadata  ├── medios  └── finalización
```

Antes de cambiar algoritmos habrá que saber exactamente dónde se consume:

- CPU;
- memoria;
- tiempo de consultas;
- tiempo de Blade/render;
- tiempo de serialización;
- tiempo de escritura;
- tiempo de filesystem.

Faro 3 no optimizará por intuición.

**Primero perfilar. Después cortar.**

---

## 2. Encontrar las retenciones de memoria

Uno de los objetivos principales será localizar funciones que mantienen estructuras vivas durante más tiempo del necesario.

Se revisarán especialmente:

- colecciones Eloquent grandes;
- modelos hidratados innecesariamente;
- relaciones eager-loaded;
- cuerpos completos de posts;
- excerpts;
- estructuras auxiliares;
- índices mantenidos en PHP;
- arrays acumulativos;
- closures que retengan referencias;
- resultados globales reutilizados entre etapas.

El objetivo será pasar, siempre que sea posible, de:

```
cargar corpus     │     ▼mantener corpus en RAM     │     ▼generar múltiples estructuras
```

a:

```
consultar lo necesario     │     ▼procesar     │     ▼escribir     │     ▼liberar
```

La memoria debería depender principalmente del tamaño del lote de trabajo y no del tamaño total del sitio.

---

## 3. Consultas específicas

No todas las salidas necesitan un `Post` completo.

Por ejemplo:

```
sitemap→ slug + updated_atarchivo→ id + slug + title + published_atfeed→ últimos N postscategoría→ metadata mínima + posts correspondientespost individual→ contenido completo
```

Faro 3 revisará cada etapa para evitar cargar columnas, relaciones y cuerpos que no utiliza.

La regla será sencilla:

> **pedirle a SQLite solamente los datos que la salida necesita.**

---

## 4. Chunking, cursors y streaming

Se evaluará sistemáticamente el uso de:

- `chunkById()`;
- `lazyById()`;
- cursors;
- generators;
- streaming;
- escritura incremental.

El objetivo es que procesar:

```
10.000 posts100.000 posts1.000.000 posts
```

no implique mantener esas cantidades de objetos simultáneamente en memoria.

---

## 5. Liberación entre etapas

Una compilación completa genera varias familias de documentos.

No existe razón para que las estructuras utilizadas para generar una familia permanezcan necesariamente vivas durante la siguiente.

Faro 3 buscará que cada etapa tenga un ciclo claro:

```
crear contexto     │     ▼generar salida     │     ▼persistir     │     ▼liberar contexto
```

Las mediciones de memoria entre etapas permitirán comprobar que esa liberación ocurre realmente.

---

## 6. SQLite

SQLite también será perfilado.

Se estudiarán:

- índices utilizados durante el build;
- planes de consulta;
- ordenamiento;
- paginación;
- búsquedas por estado;
- categorías;
- fechas;
- selección incremental;
- columnas realmente utilizadas;
- costo de hidratación Eloquent frente a consultas más directas cuando corresponda.

La intención no es abandonar Eloquent indiscriminadamente.

La intención es utilizar el nivel de abstracción adecuado para cada parte del compilador.

---

## 7. El filesystem como parte del compilador

Las pruebas masivas mostraron que generar sitios gigantes no es solamente un problema de aplicación.

También es un problema de filesystem.

Faro 3 medirá y documentará:

- archivos generados;
- directorios creados;
- inodos consumidos;
- bytes lógicos;
- espacio físico utilizado;
- operaciones de escritura;
- costo de creación de archivos pequeños;
- distribución de archivos entre directorios;
- comportamiento del filesystem utilizado.

Se evaluará el impacto de diferentes estrategias de layout y publicación.

En sitios pequeños esto es irrelevante.

En sitios con millones de objetos deja de serlo.

---

## 8. Evitar escrituras innecesarias

Un build incremental no debería escribir un archivo cuyo contenido final es exactamente igual al que ya existe, salvo que exista una razón operativa para hacerlo.

Se estudiarán estrategias como:

```
contenido nuevo    │    ▼comparación / fingerprint    │    ├── igual ─────► no escribir    │    └── distinto ──► reemplazar
```

Esto puede reducir:

- I/O;
- modificaciones de mtime;
- invalidaciones posteriores;
- trabajo de sincronización;
- escrituras sobre almacenamiento.

---

## 9. Escrituras atómicas

Cuando un archivo deba reemplazarse, Faro 3 deberá estudiar una estrategia segura:

```
archivo.tmp    │    ▼escritura completa    │    ▼fsync / cierre    │    ▼rename    │    ▼archivo final
```

El lector nunca debería recibir medio HTML porque coincidió con el instante exacto de una publicación.

---

## 10. Builds completos atómicos

Para reconstrucciones completas se estudiará formalizar:

```
dist-current/dist-next/
```

El nuevo sitio se construye fuera del árbol público.

Sólo cuando termina y pasa sus validaciones:

```
dist-next    │    ▼validación    │    ▼swap    │    ▼producción
```

Esto permitirá que un build de gran escala no exponga estados intermedios a Nginx.

---

## 11. Separar generación y despliegue

Faro 3 distinguirá más claramente dos operaciones:

```
BUILDSQLite → árbol estáticoDEPLOYárbol estático → producción
```

Hoy ambas pueden ocurrir prácticamente en el mismo lugar.

La separación permitirá en el futuro construir en una máquina y publicar en otra mediante:

- `rsync`;
- SSH;
- object storage;
- CDN;
- artefactos comprimidos;
- otros mecanismos de despliegue.

El compilador no debería necesitar saber cómo será servido finalmente el resultado.

---

## 12. Medios a gran escala

Los medios merecen un pipeline propio.

Se revisará:

- cuándo copiar;
- cuándo reutilizar;
- cuándo convertir;
- cómo detectar cambios;
- cómo evitar reprocesamientos;
- cómo manejar originales y previews;
- cómo calcular qué medios necesita realmente una publicación;
- costo de optimización de imágenes;
- impacto del almacenamiento de miles o millones de archivos.

El contenido HTML y el contenido binario tienen características diferentes y no necesariamente deben compartir la misma estrategia.

---

## 13. Paralelismo: sólo si las mediciones lo justifican

Faro 3 no asumirá que más procesos significan automáticamente mayor velocidad.

Antes de introducir paralelismo se determinará cuál es el cuello de botella real.

Si el límite es CPU, dividir trabajo puede ayudar.

Si el límite es el disco, multiplicar escritores puede empeorar el rendimiento.

Por eso el orden será:

```
medir  ↓identificar cuello  ↓optimizar algoritmo  ↓volver a medir  ↓recién entonces evaluar paralelismo
```

---

## 14. Benchmarks reproducibles

Faro 3 deberá disponer de benchmarks repetibles para distintos tamaños.

Como mínimo:

```
10K30K100K300K500K1M2M
```

Cada prueba debería registrar:

```
hardwarefilesystemPHPSQLitecantidad de poststamaño medio del contenidotiempoposts/sRAM picoarchivos generadosinodosbytes escritostamaño lógico de disttamaño físico de dist
```

Los resultados publicados deberán distinguir siempre:

```
MEDIDO
```

de:

```
PROYECTADO
```

---

## 15. Objetivo de escala

Faro 3 no promete que dos millones de posts se compilarán en un tiempo determinado antes de medirlo.

Sí establece una meta arquitectónica:

> **El tamaño total del corpus no debería traducirse linealmente en memoria residente.**

Con procesamiento por lotes, consultas específicas y un filesystem correctamente dimensionado, la intención es poder trabajar con corpus del orden de:

```
1.000.0002.000.000
```

de documentos utilizando cantidades moderadas de RAM.

Los ensayos actuales sugieren que es posible.

Faro 3 deberá demostrarlo.

---

# Después de Faro 3

Faro empezó resolviendo un problema editorial.

Luego se convirtió en un generador estático.

La siguiente etapa consiste en convertir ese generador en un compilador medible y predecible.

```
Faro 1   
│   
└── ideaFaro 2   
│   
└── CMS + generador estático funcionalFaro 2.5   
│   
└── pulido funcionalFaro 3   
│   └── optimización integral del compilador       
    │
    ├── CPU       
    ├── memoria       
    ├── SQLite       
    ├── filesystem       
    ├── I/O       
    ├── atomicidad       
    └── escala
```

La misión original ya está cumplida.

Ahora toca afilar la máquina.


