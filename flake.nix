{
  description = "CMS Laravel development environment";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixpkgs-unstable";
  };

  outputs = { nixpkgs, ... }:
    let
      system = "x86_64-linux";
      pkgs = nixpkgs.legacyPackages.${system};

      php = pkgs.php84.buildEnv {
        extensions = ({ enabled, all }:
          enabled ++ (with all; [
            bcmath
            dom
            exif
            gd
            intl
            pdo_sqlite
            xml
            zip
          ])
        );

        extraConfig = ''
          memory_limit = 700M
          upload_max_filesize = 256M
          post_max_size = 256M
          max_execution_time = 180
          max_input_time = 120
        '';
      };
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

        shellHook = ''
          echo "CMS Laravel dev shell"
          echo "PHP:      $(php --version | head -1)"
          echo "Composer: $(composer --version)"
          echo "Node:     $(node --version)"
          echo "npm:      $(npm --version)"
        '';
      };
    };
}
