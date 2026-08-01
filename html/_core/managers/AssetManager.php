<?php

final class AssetManager {
    private const CSS_PATH = "/assets/css/";
    private const JS_PATH = "/assets/js/";
    private FileMap $cssMap;
    private FileMap $jsMap;

    // MAGIC FUNCTIONS
    public function __construct( ) {
        $this->cssMap = new FileMap( XOG_ROOT . self::CSS_PATH );
        $this->jsMap = new FileMap( XOG_ROOT . self::JS_PATH );
    }

    // PUBLIC FUNCTIONS
    public function useCSS( array $keys ): void {
        if ( empty( $keys ) ) {
            return;
        }
        foreach ( $keys as $key ) {
            $cssPath = $this->cssMap->findRelativeFilepath( $key . ".css", self::CSS_PATH );
            if ( $cssPath === null ) {
                new Logger( Logger::CHANNEL_WEB )->warning( "CSS asset not found", [
                    "file" => $key . ".css",
                ] );

                return;
            }

            $cssPath .= $this->addCacheBuster( $cssPath );
            echo $this->getCssLine( $cssPath );
        }
    }

    public function useJS( array $keys ): void {
        if ( empty( $keys ) ) {
            return;
        }
        foreach ( $keys as $key ) {
            $jsPath = $this->jsMap->findRelativeFilepath( $key . ".js", self::JS_PATH );
            if ( $jsPath === null ) {
                new Logger( Logger::CHANNEL_WEB )->warning( "JavaScript asset not found", [
                    "file" => $key . ".js",
                ] );

                return;
            }
            $jsPath .= $this->addCacheBuster( $jsPath );
            echo $this->getJsLine( $jsPath );
        }
    }

    // PRIVATE FUNCTIONS
    private function getCssLine( string $href ): string { return '<link rel="stylesheet" href="' . $href . '">' . "\n"; }
    private function getJsLine( string $src )  : string { return '<script src="' . $src . '"></script>' . "\n"; }

    private function addCacheBuster( string $webPath ): string {
        $pathOnly = parse_url( $webPath, PHP_URL_PATH ) ?? $webPath;

        $root = rtrim( XOG_ROOT, "/" );
        $rel = ltrim( $pathOnly, "/" );
        $full = $root . "/" . $rel;

        if ( !is_file( $full ) ) {
            // Quietly skip cachebuster if file not present (no warnings, no broken HTML)
            return "";
        }

        $sep = str_contains( $webPath, "?" ) ? "&" : "?";
        return $sep . "v=" . filemtime( $full );
    }
}
