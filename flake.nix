{
  description = "CMS Laravel development environment";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixpkgs-unstable";
  };

  outputs = { nixpkgs, ... }:
    let
      system = "x86_64-linux";
      pkgs = nixpkgs.legacyPackages.${system};

      # Configuración de extensiones para PHP
      phpExtensions = { enabled, all }: enabled ++ (with all; [
        bcmath
        dom
        exif
        gd
        intl
        pdo_sqlite
        zip
      ]);

      # PHP 8.4 con extensiones y composer
      php84Custom = pkgs.php84.buildEnv {
        extensions = phpExtensions;
        extraConfig = ''
          memory_limit = 700M
          upload_max_filesize = 256M
          post_max_size = 256M
          max_execution_time = 180
          opcache.enable = 1
          opcache.enable_cli = 1
        '';
      };

      # PHP 8.5 con extensiones y composer
      php85Custom = pkgs.php85.buildEnv {
        extensions = phpExtensions;
        extraConfig = ''
          memory_limit = 700M
          upload_max_filesize = 256M
          post_max_size = 256M
          max_execution_time = 180
          opcache.enable = 1
          opcache.enable_cli = 1
        '';
      };
    in
    {
      devShells.${system} = {
        # Por defecto usa PHP 8.4
        default = pkgs.mkShell {
          nativeBuildInputs = [
            php84Custom
            pkgs.php84Packages.composer
            pkgs.nodejs_24
            pkgs.sqlite
            pkgs.libwebp
          ];

          shellHook = ''
            export PATH="${php84Custom}/bin:${pkgs.php84Packages.composer}/bin:$PATH"
            echo "⚡ Entorno activo: PHP 8.4"
          '';
        };

        # Entorno alternativo PHP 8.5
        php85 = pkgs.mkShell {
          nativeBuildInputs = [
            php85Custom
            pkgs.php85Packages.composer
            pkgs.nodejs_24
            pkgs.sqlite
            pkgs.libwebp
          ];

          shellHook = ''
            export PATH="${php85Custom}/bin:${pkgs.php85Packages.composer}/bin:$PATH"
            echo "⚡ Entorno activo: PHP 8.5"
          '';
        };
      };
    };
}
