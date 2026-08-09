<?php

class FileMap implements Iterator {
    private string $rootPath;
    private array $treeArray = [ ];
    private array $keys = [ ];
    private int $position = 0;

    // MAGIC FUNCTIONS
    public function __construct( string $rootPath ) {
        $this->rootPath = rtrim( $rootPath, "/\\" ) . DIRECTORY_SEPARATOR;
        $this->populateTree( $this->rootPath, $this->treeArray );
    }

    // PUBLIC FUNCTIONS
    public function current( ): mixed { return $this->treeArray[ $this->keys[ $this->position ] ]; }
    public function key( )    : mixed { return $this->keys[ $this->position ]; }
    public function next( )   : void  { $this->position++; }
    public function valid( )  : bool  { return isset( $this->keys[ $this->position ] ); }

    public function findFullFilepath( string $filename ): ?string { return $this->searchTree( $this->treeArray, $filename ); }
    public function copyTree( string $destination ) { $this->copyDirectory( $this->rootPath, $destination ); }

    public function findRelativeFilepath( string $filename, string $basePath = XOG_ROOT ): ?string {
        $fullPath = $this->findFullFilepath( $filename );
        if ( $fullPath === null ) {
            return null;
        }

        $pathPiece = substr( $fullPath, strlen( $this->rootPath ) );
        return rtrim( str_replace( "\\", "/", $basePath ), "/" ) .
            "/" .
            ltrim( str_replace( "\\", "/", $pathPiece ), "/" );
    }

    public function rewind( ): void {
        $this->keys = array_keys( $this->treeArray );
        $this->position = 0;
    }

    // PRIVATE FUNCTIONS
    private function scanDirectory( string $directory ): null|array|bool {
        $items = scandir( $directory );
        return $items;
    }

    private function populateTree( string $directory, array &$treeArray ) {
        $items = $this->scanDirectory( $directory );
        if ( !$items ) {
            return;
        }

        foreach ( $items as $item ) {
            if ( $item === "." || $item === ".." ) {
                continue;
            }

            $path = $directory . $item;
            if ( is_dir( $path ) ) {
                $treeArray[ $item ] = [ ];
                $this->populateTree( $path . DIRECTORY_SEPARATOR, $treeArray[ $item ] );
            } else {
                $treeArray[ $item ] = $path;
            }
        }
    }

    private function searchTree( array $tree, string $fileName ) {
        foreach ( $tree as $key => $value ) {
            if ( is_array( $value ) ) {
                $result = $this->searchTree( $value, $fileName );
                if ( $result !== null ) {
                    return $result;
                }
            } elseif ( $key === $fileName ) {
                return $value;
            }
        }
        return null;
    }

    private function copyDirectory( string $source, string $destination ) {
        $items = $this->scanDirectory( $source );
        if ( !$items ) {
            return;
        }

        foreach ( $items as $item ) {
            if ( $item === "." || $item === ".." ) {
                continue;
            }

            $srcPath = $source . $item;
            $destPath = $destination . DIRECTORY_SEPARATOR . $item;

            if ( is_dir( $srcPath ) ) {
                $this->copyDirectory(
                    $srcPath . DIRECTORY_SEPARATOR,
                    $destPath . DIRECTORY_SEPARATOR,
                );
            } else {
                copy( $srcPath, $destPath );
            }
        }
    }
}
