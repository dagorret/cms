# TODO.md

# FARO CMS V2
## Auditoría y Rediseño del Pipeline de Generación Estática para Sitios Masivos

---

# Estado actual

La arquitectura principal quedó resuelta.

Se validó exitosamente:

- generación incremental;
- procesamiento por lotes (`chunkById`);
- memoria prácticamente constante;
- build de **300.000 posts** con:

- límite PHP: **512 MB**
- pico observado: **226,5 MB**

El cuello de botella ya no es la memoria.

El nuevo cuello es la **generación de estructuras globales**.

---

# Objetivo

Reducir el tiempo total de build para sitios extremadamente grandes
(300.000 → 3.000.000 publicaciones)
sin alterar el HTML generado ni romper el contrato público del CMS.

---

# Problema observado

Después de finalizar los 300.000 singles:

```
✔ Fin del procesamiento HTML
```

la generación de estructuras globales continúa durante muchísimo tiempo.

Durante la prueba se detectó:

- CPU ~100%
- memoria estable
- escritura continua de miles de archivos JSON

Ejemplo:

```
dist/data/categories/conversacion/page-1187.json
dist/data/categories/conversacion/page-1188.json
...
```

---

# Hipótesis

El problema ya no es el render.

El problema es la cantidad de índices secundarios generados.

Actualmente FARO genera:

- portada
- categorías
- archivos
- sitemap
- feeds
- json auxiliares

Todos completamente materializados.

Con cientos de miles de publicaciones aparecen miles de páginas auxiliares.

---

# Objetivos de la auditoría

No comenzar optimizando.

Primero comprender exactamente:

- qué se genera
- por qué se genera
- quién lo consume
- si realmente es necesario

---

# Revisar

## 1. Categorías

Auditar:

```
dist/data/categories/
```

Responder:

¿Cuántos archivos produce?

¿Por qué?

¿Cuántos realmente necesita el frontend?

¿Puede reducirse?

---

## 2. Portadas

Auditar:

```
dist/data/index/
```

Determinar:

- cantidad de páginas
- tamaño
- consumo
- posibilidad de streaming

---

## 3. Archivo cronológico

Auditar:

```
dist/archive/
```

Determinar:

- cantidad real de páginas
- duplicación de información
- posibilidad de agregación

---

## 4. Sitemap

Auditar:

- cantidad de XML
- fragmentación
- memoria
- tiempo

---

## 5. Feed

Verificar:

RSS

Atom

JSON Feed

¿Todos son necesarios?

---

## 6. JSON auxiliares

Auditar cada archivo generado.

Responder:

¿Quién lo consume?

¿Puede reconstruirse?

¿Debe existir?

---

# Preguntas arquitectónicas

## Categorías

Actualmente:

```
Categoría

page-1.json
page-2.json
...
page-1200.json
```

Preguntar:

¿Hace falta generar las 1200 páginas?

---

## Portadas

¿Toda la paginación debe existir previamente?

---

## Archivo

¿Puede agregarse por año?

¿Mes?

¿Día?

¿Evitar niveles innecesarios?

---

## Índices

¿Conviene:

muchos archivos pequeños

o

menos archivos grandes?

---

# Streaming

Evaluar si cada índice puede escribirse de forma totalmente incremental.

Ejemplo:

```
leer 50 posts

↓

generar page-1

↓

descartar

↓

leer siguientes 50

↓

page-2
```

Sin mantener estructuras acumuladas.

---

# Frontend

Investigar si realmente necesita:

```
page-843.json
```

o puede reconstruir navegación desde un índice mucho menor.

---

# Cantidad de archivos

Medir:

- total de archivos generados

- distribución por tipo

- tamaño promedio

- tamaño total

---

# Sistema de archivos

Investigar:

¿El cuello es CPU?

¿SQLite?

¿Blade?

¿Filesystem?

¿cantidad de archivos?

Medir.

No asumir.

---

# Posibles rediseños

Evaluar sin implementar todavía.

Opciones:

## A)

Mantener comportamiento actual.

---

## B)

Limitar páginas máximas por categoría.

Ejemplo:

```
primeras 100 páginas
```

Configurable.

---

## C)

Generación diferida.

Solo crear páginas cuando existan.

---

## D)

Índices compactos.

Menos archivos.

Más información por archivo.

---

## E)

Compresión.

Evaluar.

---

## F)

Manifest único.

Un índice principal reutilizable.

---

## G)

Modelo híbrido.

Pequeños índices + navegación dinámica del frontend.

---

# Benchmarks

Repetir:

30.000

300.000

1.000.000 (simulado)

Comparar:

- tiempo
- memoria
- cantidad de archivos

---

# Restricciones

No romper:

- HTML
- URLs
- SEO
- sitemap
- incrementalidad
- contratos públicos
- renderer
- Markdown
- Editor.js

---

# No hacer

No introducir:

- cachés opacos
- hacks
- límites arbitrarios
- duplicación de información
- optimizaciones sin medición

---

# Objetivo final

Que FARO pueda generar sitios con millones de publicaciones manteniendo:

- memoria prácticamente constante
- procesamiento incremental
- tiempo de build razonable
- estructura simple
- código mantenible
- arquitectura limpia

La siguiente optimización debe atacar exclusivamente el nuevo cuello de botella:

**la generación masiva de estructuras globales e índices secundarios**, no el renderizado de publicaciones individuales.
