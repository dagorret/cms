# README - Desarrollo con Nix

Este proyecto usa **Nix** para describir y reproducir su entorno de desarrollo sin convertir Debian en parte del proyecto.

La idea general es simple:

```text
Debian        = sistema base
Home Manager  = herramientas personales
flake.nix     = entorno del proyecto
/nix/store    = resultados construidos por Nix
flake.lock    = versión exacta del catálogo de recetas usado
```

---

## 1. El mapa mental de un `flake.nix`

Para leer un flake sin pelearse con la sintaxis:

```text
inputs  = lo que entra
outputs = lo que sale
let     = preparo piezas / variables auxiliares
in      = construyo el resultado
```

Ejemplo simplificado:

```nix
{
  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixpkgs-unstable";
  };

  outputs = { nixpkgs, ... }:
    let
      system = "x86_64-linux";
      pkgs = nixpkgs.legacyPackages.${system};
    in
    {
      # resultado
    };
}
```

### `inputs`

```nix
inputs = {
  nixpkgs.url = "github:NixOS/nixpkgs/nixpkgs-unstable";
};
```

`nixpkgs` es el **conjunto de recetas** disponible para Nix.

No significa que el proyecto se actualice solo.

El archivo `flake.lock` fija una revisión exacta de ese conjunto de recetas.

---

### `outputs`

```nix
outputs = { nixpkgs, ... }:
```

Se puede leer así:

> Con `nixpkgs`, construí lo que viene después.

---

### `let`

En `let` se preparan las piezas que después vamos a usar.

Por ejemplo:

```nix
let
  system = "x86_64-linux";
  pkgs = nixpkgs.legacyPackages.${system};

  php = pkgs.php84.buildEnv {
    # opciones de PHP
  };
```

En este caso:

```text
system = arquitectura
pkgs   = conjunto de paquetes para esa arquitectura
php    = nuestro PHP ya configurado
```

---

### `in`

Después de `in` se arma el resultado usando las piezas preparadas arriba.

```nix
in
{
  devShells.${system}.default = pkgs.mkShell {
    packages = [
      php
      php.packages.composer
      pkgs.nodejs_24
      pkgs.sqlite
      pkgs.libwebp
    ];
  };
}
```

La idea es:

```text
let = preparo las piezas
in  = construyo el entorno con esas piezas
```

---

# 2. `nixpkgs`, `flake.lock` y versiones

Hay tres conceptos distintos.

```text
nixpkgs
= catálogo de recetas

pkgs.php84
= receta concreta que elegimos

flake.lock
= revisión exacta del catálogo que quedó congelada
```

Por ejemplo:

```nix
php = pkgs.php84.buildEnv {
  ...
};
```

dice explícitamente:

> Quiero PHP 8.4.

Actualizar `nixpkgs` puede cambiar PHP de:

```text
8.4.24
a
8.4.25
```

si la receta `php84` fue actualizada.

Pero para cambiar de PHP 8.4 a PHP 8.5 hay que cambiar explícitamente el flake:

```nix
pkgs.php84
```

por:

```nix
pkgs.php85
```

Eso evita saltos importantes de versión accidentales.

---

# 3. Entrar al entorno

Desde la raíz del proyecto:

```bash
nix develop
```

Nix lee:

```text
flake.nix
flake.lock
```

y crea el entorno definido por:

```nix
devShells.${system}.default
```

Dentro de esa shell estarán disponibles las herramientas declaradas por el proyecto.

Para salir:

```bash
exit
```

---

# 4. El `/nix/store`

Los programas reales que Nix descarga o construye viven en:

```text
/nix/store
```

Por ejemplo:

```text
/nix/store/...-php-8.4.x
/nix/store/...-nodejs-24.x
/nix/store/...-composer-x.x
```

El store es compartido por Nix.

El proyecto no copia PHP, Node o Composer dentro de su directorio.

La receta está en el proyecto; los resultados están en `/nix/store`.

---

# 5. Entorno temporal y entorno conservado

Un simple:

```bash
nix develop
```

crea un entorno reproducible, pero ese entorno puede ser eliminado por el garbage collector cuando deja de estar referenciado.

Si queremos conservar físicamente ese entorno:

```bash
nix develop --profile .nix-dev-profile
```

Esto crea un **perfil local del proyecto**.

Conceptualmente:

```text
~/work/cms/.nix-dev-profile
        ->
~/work/cms/.nix-dev-profile-1-link
        ->
/nix/store/...-nix-shell-env
```

El perfil mantiene una referencia al entorno y evita que el garbage collector elimine los objetos que necesita.

El perfil es estado local, por eso conviene ignorarlo en Git:

```gitignore
.nix-dev-profile*
```

La parte portable sigue siendo:

```text
flake.nix
flake.lock
```

---

# 6. ¿Qué es una generación de perfil?

Si se vuelve a ejecutar:

```bash
nix develop --profile .nix-dev-profile
```

con el mismo nombre de perfil, Nix actualiza ese perfil y puede crear una nueva generación.

Por ejemplo:

```text
.nix-dev-profile
    -> .nix-dev-profile-2-link
```

El perfil principal representa la generación activa.

Esto permite conservar estados anteriores mientras sigan existiendo sus generaciones.

---

# 7. Comandos importantes

Entrar al entorno normal:

```bash
nix develop
```

Entrar y conservar el entorno frente al GC:

```bash
nix develop --profile .nix-dev-profile
```

Ver cuánto ocupa Nix:

```bash
du -sh /nix
```

Ver cuánto ocupa solamente el store:

```bash
du -sh /nix/store
```

Ver los objetos más grandes:

```bash
du -sh /nix/store/* | sort -h | tail -20
```

Ver qué borraría el garbage collector sin borrar nada:

```bash
nix store gc --dry-run
```

Ejecutar el garbage collector:

```bash
nix store gc
```

Actualizar la revisión de los inputs del flake:

```bash
nix flake update
```

Después de actualizar la receta o el lock:

```bash
nix develop --profile .nix-dev-profile
```

---

# 8. ¿Cómo actualizar?

Hay dos tipos de actualización diferentes.

## Actualizar el catálogo de recetas

```bash
nix flake update
```

Esto actualiza `flake.lock`.

El `flake.nix` puede seguir diciendo:

```nix
pkgs.php84
```

pero ahora la receta concreta de PHP 8.4 puede ser más nueva.

Después:

```bash
nix develop --profile .nix-dev-profile
```

---

## Cambiar explícitamente una versión importante

Para probar una nueva rama de PHP, por ejemplo:

```nix
pkgs.php84
```

se cambia por:

```nix
pkgs.php85
```

Después:

```bash
nix develop --profile .nix-dev-profile
```

Así el cambio de versión importante está escrito explícitamente en la receta.

---

# 9. Varios entornos sin romper el principal

Un flake puede declarar más de un entorno.

Por ejemplo:

```nix
devShells.${system} = {
  default = pkgs.mkShell {
    packages = [
      php84
    ];
  };

  testing = pkgs.mkShell {
    packages = [
      php85
    ];
  };
};
```

Entonces:

```bash
nix develop
```

entra al entorno `default`.

Y:

```bash
nix develop .#testing
```

entra al entorno de pruebas.

Esto permite probar herramientas o versiones distintas sin modificar el entorno principal.

---

# 10. Perfiles separados para pruebas

También podemos conservar ambos entornos físicamente:

```bash
nix develop --profile .nix-dev-profile
```

y:

```bash
nix develop .#testing --profile .nix-test-profile
```

Conceptualmente:

```text
.nix-dev-profile
    -> entorno normal

.nix-test-profile
    -> entorno experimental
```

Por ejemplo:

```text
desarrollo normal
    PHP 8.4

testing
    PHP 8.5
```

Los dos pueden coexistir porque Nix los guarda en paths distintos del store.

No hay que reemplazar PHP 8.4 para probar PHP 8.5.

---

# 11. Desarrollo, testing y producción

La misma idea puede usarse para separar objetivos.

```text
desarrollo
= herramientas cómodas para programar

testing
= versiones o dependencias que queremos probar

producción
= entorno mínimo y controlado para ejecutar la aplicación
```

No necesariamente deben tener los mismos paquetes.

Por ejemplo, desarrollo puede incluir:

```text
PHP
Composer
Node
npm
SQLite
herramientas de diagnóstico
```

Testing puede utilizar:

```text
otra versión de PHP
otra versión de Node
herramientas adicionales
```

Y producción puede seguir usando un contenedor Docker mínimo.

Nix no obliga a reemplazar Docker.

Una separación razonable para este proyecto es:

```text
Debian
    sistema operativo

Nix / flake.nix
    entorno reproducible de desarrollo y testing

Composer
    dependencias PHP de la aplicación

npm
    dependencias JavaScript de la aplicación

Docker
    entorno de producción / distribución
```

Cada herramienta resuelve una capa distinta.

---

# 12. Probar sin romper nada

La ventaja principal de Nix es que una prueba no necesita reemplazar el entorno actual.

Podemos tener simultáneamente:

```text
PHP 8.4
PHP 8.5
Node 24
otra versión de Node
```

porque cada construcción vive en un path diferente:

```text
/nix/store/<hash>-php-8.4...
/nix/store/<otro-hash>-php-8.5...
```

El entorno decide cuáles aparecen en el `PATH`.

Por eso probar una nueva versión significa:

```text
crear otro entorno
        ↓
entrar en él
        ↓
probar
        ↓
salir
```

y no:

```text
desinstalar lo actual
        ↓
instalar lo nuevo
        ↓
esperar que nada se rompa
```

---

# 13. Regla práctica

Para este proyecto:

```text
flake.nix
= qué necesita el entorno

flake.lock
= versiones exactas de las recetas

/nix/store
= materialización real

perfil
= este entorno construido lo quiero conservar
```

Y para actualizar:

```text
quiero recetas más nuevas
    -> nix flake update

quiero cambiar una versión importante
    -> editar flake.nix

quiero conservar el nuevo entorno
    -> nix develop --profile .nix-dev-profile

quiero probar sin tocar el normal
    -> crear otro devShell y, si hace falta, otro perfil
```

La idea central es que **la receta describe el entorno y Nix permite construir varios resultados aislados a partir de distintas recetas sin pisarse entre sí**.
