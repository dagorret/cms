# CMS Faro

**El Generador Estático Híbrido de Alto Rendimiento para Sitios Masivos**

![PHP](https://img.shields.io/badge/PHP-8.4%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13.18.1-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Filament](https://img.shields.io/badge/Filament-5-FDAE4B?style=for-the-badge&logo=filament&logoColor=white)
![Build](https://img.shields.io/badge/CLI%20Build-30K%20posts%20%2F%2033s-0B3D91?style=for-the-badge)
![Throughput](https://img.shields.io/badge/Throughput-~900%20p%C3%A1g%2Fs-9A3412?style=for-the-badge)
![License](https://img.shields.io/badge/license-CC%20BY--NC%204.0-7C3AED?style=for-the-badge)

---

## A) CMS Faro

CMS Faro es un motor de generación estática de grado de infraestructura diseñado específicamente para resolver el problema de la escala y los costos de cómputo. Permite gestionar millones de artículos estructurados en una base de datos local y transformarlos bajo demanda en una arquitectura estática hiper-optimizada, lista para ser servida directamente por servidores HTTP planos (Nginx/Apache) o plataformas de Hosting Compartido (Shared Hosting), sin penalizaciones de CPU ni dependencias vivas en producción.

## B) ¿Qué es?

Faro es un generador estático híbrido inspirado en la filosofía de libertad absoluta de Hugo y el procesamiento semántico moderno. A diferencia de las plataformas tradicionales pesadas (WordPress, Django) que renderizan las vistas en tiempo real consumiendo recursos en cada petición HTTP, Faro traslada el 100% del costo computacional al momento de la compilación. El resultado final de su ejecución es una estructura de archivos planos HTML y JSON que no requiere base de datos ni intérpretes dinámicos en producción.

## C) ¿Qué hace?

- **Compilación Masiva Ultra-Veloz:** procesa más de 30.000 artículos en un tiempo récord de T = 33s utilizando un solo hilo síncrono de ejecución en CLI (~900 posts/s).
- **Purga Quirúrgica del HTML:** implementa un `StaticHtmlCleaner` nativo que procesa, unifica etiquetas y remueve código fantasma en memoria RAM antes de escribir en disco.
- **Inyección Dinámica de Metadatos:** precalcula índices, estructuras de taxonomías, esquemas estructurados JSON-LD y mapas de navegación sin realizar consultas redundantes a la base de datos.
- **Independencia del Servidor:** distribuye el contenido para que pueda ser alojado de manera inmediata en arquitecturas de bajo costo, eliminando cuellos de botella por inodos.

## D) Flujo Arquitectónico: Posts, Home, Paginación, Feed, Archivo y Páginas

El core del compilador organiza el procesamiento en bloques secuenciales optimizados mediante streaming de memoria, para evitar el desbordamiento de RAM en datasets de millones de registros.

### 1. Los Posts Simples

Se cargan de la base de datos mediante técnicas de fragmentación masiva (chunking). El motor asocia cada registro a una ruta física e inyecta las variables limpias en su respectiva máscara visual. Cada post genera su contraparte indexada en formato `.json` para dar soporte a transiciones SPA instantáneas en el lado del cliente.

### 2. El Home y la Paginación

La página principal se construye bajo un esquema dinámico de indexación. El motor calcula el total de artículos y divide el árbol de contenidos en segmentos fijos. Genera la página raíz (`index.html`) y las páginas de navegación secuencial (`page/2/index.html`, `page/3/index.html`) de forma automatizada, calculando los punteros previos y siguientes de forma estática.

### 3. El Archivo Histórico

El módulo de archivo compacta la totalidad de la metadata histórica en agrupaciones optimizadas por años, meses o categorías. En lugar de generar miles de páginas para índices, crea un mapa consolidado que reduce drásticamente el consumo físico de bloques del sistema de archivos.

### 4. El Feed RSS / Atom y Sitemaps

Emana de forma directa un archivo de sindicación XML estructurado con los últimos artículos procesados junto con un mapa del sitio indexado completo (`sitemap.xml`), fragmentado para cumplir estrictamente con los estándares de indexación de motores de búsqueda masivos, sin procesamiento en tiempo de ejecución.

### 5. Las Páginas Estáticas

Las secciones institucionales independientes (Contacto, Quiénes Somos, Políticas) omiten las restricciones de las líneas de tiempo cronológicas y se compilan directamente desde sus propios esquemas planos en el directorio de salida.

## E) El CMS y el Editor Trix

El panel administrativo de entrada de datos utiliza una interfaz limpia y minimalista potenciada por el editor enriquecido Trix de Basecamp, embebido dentro de un panel construido sobre **Filament 5**. La lógica de persistencia está diseñada para almacenar código HTML plano y semántico, evitando la inyección de estilos embebidos (inline styles) o clases propietarias de editores complejos. Esto garantiza que el contenido almacenado en la base de datos esté "puro", actuando como la fuente única de verdad lista para ser procesada por el compilador.

## F) Fundamento de la Estructura Web

Cada elemento estructural de Faro responde a una razón técnica de SEO, pero también a un principio más antiguo, propio de las teorías clásicas de la comunicación y la edición periodística. La disciplina editorial de los grandes medios no nació con internet: preexiste a ella, y Faro la traduce a la infraestructura de un sitio estático.

- **Home Principal:** el punto de entrada central optimizado para SEO que provee la máxima autoridad de dominio (Link Juice) hacia los contenidos más frescos. Editorialmente, replica la lógica de la **pirámide invertida** del periodismo clásico —lo más relevante y reciente arriba, lo secundario debajo— aplicada no a un artículo, sino a la jerarquía de todo el sitio.
- **Paginación Secuencial:** obligatoria para permitir que los rastreadores (crawlers) de los buscadores descubran el millón de posts mediante enlaces transitables, sin saturar el presupuesto de rastreo (Crawl Budget) — una aplicación técnica literal del viejo problema de la **economía de la atención**.
- **Sitemaps XML:** el mapa de navegación explícito que indica a los buscadores la ubicación exacta y la prioridad de indexación de cada URL creada. Es **arquitectura de la información** (en el sentido de Richard Saul Wurman) llevada a un archivo de máquina.
- **Feeds RSS:** herramienta nativa para la sindicación automatizada de contenidos, alertas en tiempo real y lectura externa descentralizada — la contraparte técnica del ideal editorial de sindicación sin **gatekeeping** algorítmico.
- **Páginas Independientes:** proveen la estructura de confianza, legitimidad legal y puntos de contacto fijos necesarios para la validación algorítmica del sitio — el equivalente digital del pie de imprenta.

## G) Personalización y Ajustes vía Blade

El motor ofrece un entorno de abstracción libre. El diseñador trabaja sobre plantillas puras de Laravel Blade que no imponen reglas estructurales rígidas. Al compilar, las directivas se resuelven en el entorno local convirtiéndose en strings planos. Para modificar la cabecera, la navegación o el pie de página, basta con alterar los componentes de vistas:

```blade
{{-- resources/views/components/navigation.blade.php --}}
<nav class="menu-faro">
 @foreach(config('static_cms.menus.principal') as $item)
 <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
 @endforeach
</nav>
```

## H) Gestión de Assets

Todos los recursos estáticos (CSS minificado, JavaScript de la SPA, fuentes tipográficas e imágenes optimizadas) residen originalmente en el directorio de desarrollo `resources/`. Al ejecutar el compilador, estos recursos son procesados por Vite y clonados de forma directa en la raíz de salida: `dist/assets/`. Las rutas generadas en los HTML estáticos apuntan siempre a estas ubicaciones absolutas o relativas directas, eliminando la necesidad de reescritura de URLs en el servidor.

## I) Destino de la Compilación

La ejecución completa del comando genera un único directorio autocontenido denominado `dist/` en la raíz del proyecto:

```
dist/
├── assets/     # CSS, JS e imágenes optimizadas
├── data/       # Micro-JSONs individuales para la Hydra SPA
├── category/   # JSONs indexados por etiquetas y taxonomías
├── page/       # HTMLs de la paginación secuencial (/page/2/, etc.)
├── index.html  # Home del sitio web
├── sitemap.xml # Mapa de indexación para buscadores
└── [slug-del-post].html  # Los 30.000+ artículos planos individuales
```

## J) Configuración General (`config/static_cms.php`)

Toda la lógica operacional del motor se controla centralizadamente a través de un archivo de configuración nativo en PHP:

```php
<?php
return [
    'build_output_path' => base_path('dist'),
    'posts_per_page' => 15,
    'minify_html' => true,
    'menus' => [
        'principal' => [
            ['title' => 'Inicio', 'url' => '/'],
            ['title' => 'Archivo', 'url' => '/archive'],
        ],
        'footer' => [
            ['title' => 'Privacidad', 'url' => '/privacy'],
        ]
    ]
];
```

## K) Entorno Dockerizado

Para asegurar un ambiente de compilación determinista e independiente del sistema operativo del desarrollador, CMS Faro provee una receta de Docker optimizada con soporte completo para la CLI de **PHP 8.4+** y Node.js en la misma capa de ejecución, permitiendo procesar el backend y los assets en paralelo sin fricciones.

## L) Descarga, Instalación y Arranque

Sigue esta secuencia exacta en tu terminal local para inicializar el entorno y lanzar la compilación masiva:

**1. Clonar el repositorio y levantar contenedores**

```bash
git clone https://github.com/tu-usuario/cms-faro.git
cd cms-faro
docker-compose up -d --build
```

**2. Instalar dependencias e inicializar variables**

```bash
docker-compose exec app composer install
docker-compose exec app npm install && npm run build
docker-compose exec app cp .env.example .env
docker-compose exec app php artisan key:generate
```

**3. Ejecutar la compilación del sitio estático**

```bash
docker-compose exec app php artisan site:build
```

> **Métrica de Ejecución Estándar:** con una base cargada de 30.000 posts, la terminal completará la escritura del directorio `dist/` en 33 segundos, consumiendo un pico máximo estable de memoria RAM de apenas 124 MB, corriendo sobre PHP 8.4+ y Laravel 13.18.1.
>
> El directorio `dist/` resultante ocupa **~520 MB en disco** para esos 30.000 posts. Esta cifra no es puramente proporcional al peso del contenido: refleja también la sobrecarga estructural propia del sistema de archivos (en este caso, btrfs), producto de la consistencia por inodos y la asignación en bloques de los miles de archivos HTML y JSON individuales que componen la salida.

## M) Estrategia de Despliegue a Producción (VPS o Shared Hosting)

Al ser un sitio web compuesto estrictamente de archivos planos, el despliegue es completamente inmune a fallos de bases de datos relacionales en caliente. Existen dos canales de salida profesionales.

### Opción 1: Sincronización Profesional vía SSH + Rsync (Recomendada para VPS)

Es el método más rápido y eficiente. `rsync` compara los archivos locales con los del servidor en caliente y solo transmite por la red los bytes de los archivos HTML o JSON que sufrieron modificaciones reales. Ejecuta en tu entorno local:

```bash
rsync -avz --delete dist/ usuario@tu-vps-ip:/var/www/html/tu-sitio/
```

> Nota: el modificador `--delete` remueve del servidor los artículos que hayas eliminado localmente en la base de datos, manteniendo una simetría exacta.

### Opción 2: Transferencia Convencional vía FTP/SFTP (Apto Shared Hosting)

Si tu destino final es un Hosting Compartido sin acceso a la consola de SSH, empaqueta el directorio `dist/` localmente para evitar la penalización de transferir miles de micro-archivos sueltos uno a uno:

1. Genera un archivo comprimido en tu terminal local: `tar -czvf sitio.tar.gz -C dist .`
2. Sube el único archivo `sitio.tar.gz` mediante tu cliente FTP (FileZilla, Cyberduck) a la carpeta raíz de tu hosting (habitualmente `public_html/`).
3. Utiliza el Administrador de Archivos web del panel de tu hosting (cPanel/Plesk) para extraer el archivo comprimido directamente en el servidor. La propagación es instantánea.

## N) Por Qué Creé Faro

### 1. La fricción del flujo editorial tradicional

Antes de Faro estaba Hugo, y con Hugo estaba la fricción. Publicar significaba entrar por SSH y correr el despliegue, o subir el markdown a GitHub y bajarlo del otro lado, o —en el mejor de los casos— sincronizar el sitio completo de forma diferencial vía SSH, pero eso dependía de mi notebook, sólo la mía, y de redes que me permitieran salir por ese puerto. La fricción no se limitaba a los builds masivos; aparecía igual al publicar un artículo sin ninguna complejidad. En términos de teoría editorial clásica, el propio *gatekeeper* —quien decide qué se publica y cuándo— estaba bloqueado por su propia infraestructura técnica.

### 2. El techo tecnológico de las alternativas existentes

La primera idea no fue construir un generador nuevo, sino un frontend de publicación para lo que ya existía: Hugo o Pelican. Los dos tenían un techo estructural. Hugo, pese a su velocidad respaldada en el paralelismo nativo de Go, tiene un consumo de RAM poco predecible a escala porque carga la totalidad del árbol de nodos y taxonomías en memoria: está pensado para el sitio de tamaño humano, no para el millón de posts. Pelican es prolijo, pero su naturaleza síncrona en Python vuelve el tiempo de build un cuello de botella inaceptable a escala real.

### 3. La búsqueda de eficiencia: los grandes medios como referencia, no como modelo

Conviene precisar el orden real de la motivación, porque suele malinterpretarse como afán de imitación: el objetivo **no** era construir "un Home y una lista como los grandes medios". El New York Times y la BBC no fueron el modelo a copiar, sino la **referencia empírica** para formular la pregunta correcta. Lo que se buscaba era **eficiencia** — la pregunta fue "¿cómo resuelven esto los grandes, con la escala que manejan?", no "quiero que mi sitio se vea como el de ellos". Esa distinción importa: convierte la observación de la arquitectura mediática en un método de investigación aplicada (análisis de patrones exitosos bajo restricciones de escala), no en un ejercicio de imitación estética.

Bajo esa lógica, la respuesta que ambos medios dan al problema de distribuir volúmenes masivos —un Home jerárquico y listas paginadas, en lugar de renderizado dinámico por petición— se tomó como hipótesis de eficiencia a validar, no como plantilla a reproducir. Las primeras pruebas se plantearon en Python, Node.js y Rust; la restricción real del destino de producción —Shared Hosting sin runtime persistente— fue lo que inclinó la decisión hacia PHP. Ya tenía un CMS construido en PHP con Filament para la administración de contenido, y en algún momento vi que la capa de publicación podía ser radicalmente óptima sin romper nada de lo que ya funcionaba, sin necesitar la escala de cómputo que sí requieren los grandes medios. La solución, una vez que apareció, fue sencilla.

### 4. La proyección lineal de escala y el comportamiento del hardware

Si 30.000 posts tardan 33 segundos, 1,3 millones de posts —la escala de un medio como el New York Times— tardarían aproximadamente **1.430 segundos (23 minutos y 50 segundos)**, bajo un solo hilo síncrono de CLI. Es una proyección lineal teórica: no incorpora efectos no lineales como la presión sobre el filesystem a medida que crece la cantidad de archivos, ni el costo de I/O sostenido durante casi media hora. Pero como cota superior, alcanza para saber que el orden de magnitud sigue siendo minutos, no horas.

Lo mismo aplica al disco. Los 30.000 posts de la prueba ocupan **~520 MB** en `dist/`, en buena parte por la sobrecarga de consistencia por inodos que introduce btrfs al escribir miles de archivos chicos, más que por el peso bruto del contenido. Proyectado linealmente, 1,3 millones de posts rondarían los **~22,5 GB** — otra cota, no una medición, porque a esa escala el filesystem deja de comportarse linealmente.

**Metodología del stress test:** el `StressTestSeeder` genera el cuerpo de cada post así:

```php
$cuerpoAleatorio = "## " . $faker->sentence() . "\n\n" . $faker->paragraphs(rand(20, 40), true);
```

Cada post de prueba lleva entre 20 y 40 párrafos generados por Faker, con la media de la muestra cerca del extremo largo del rango: no es un stress test optimista con posts cortos, sino uno que castiga al compilador con artículos long-form reales.

### 5. Costo de desarrollo

Dediqué 21 días a construir Faro, entre aprender lo básico de Filament y descartar, en el camino, Python y Rust como alternativas. El tiempo final en PHP fue casi ridículo: 33 segundos para 30.000 posts, un hito técnico y también un test de estrés inicial del compilador.

### 6. Filosofía de fondo: iluminar lo escondido

La razón de fondo por la que existe Faro no es de rendimiento, sino de otra clase. Un día le pregunté a los buscadores quién había sido el gobernador de Nueva York en 1904 y qué había hecho durante su gestión. Lo estaba leyendo en otro lado y quería contrastarlo. No encontré nada. El dato existía, en algún archivo, en algún texto — pero no salía a la superficie de internet, porque estaba enterrado demasiado profundo. En términos de ciencia de la información, ese contenido no está en la *dark web* ni oculto deliberadamente: pertenece a lo que se conoce como **deep web informativa** — material real y legítimo, simplemente no rastreable por no estar estructurado para serlo. Ese es, precisamente, el vacío que Faro busca cerrar en la escala de su propio dominio.

Por eso Faro. Su función no es sólo compilar rápido: es iluminar lo que está escondido. Ustedes ya saben cómo hacerlo.

---

## Ñ) Licencia de Uso

**Creative Commons Atribución-NoComercial 4.0 Internacional (CC BY-NC 4.0)**

CMS Faro se distribuye bajo esta licencia. Cualquier persona es libre de:

- **Compartir** — copiar y redistribuir el material en cualquier medio o formato.
- **Adaptar** — remezclar, transformar y construir a partir del material.

Bajo los siguientes términos:

- **Atribución** — debe darse crédito de manera adecuada, proveer un enlace a la licencia e indicar si se realizaron cambios.
- **No Comercial** — el material no puede utilizarse con fines comerciales sin autorización expresa del autor.

Texto legal completo: https://creativecommons.org/licenses/by-nc/4.0/deed.es

