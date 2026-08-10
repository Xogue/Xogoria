<?php

final class AdminResourceManager {
    private const CONFIG_PATH = XOG_ROOT . "/_core/_init/config/adminResources.json";
    private array $resources;

    // MAGIC FUNCTIONS
    public function __construct(
        private MySqlManager $database,
        JsonHandler $json,
        private Logger $logger,
    ) {
        $this->resources = $json->safeLoad( self::CONFIG_PATH );
    }

    // PUBLIC FUNCTIONS
    public function definitions( ): array { return $this->resources; }

    public function definition( string $resource ): array {
        $definition = $this->resources[ $resource ] ?? null;
        if ( !is_array( $definition ) ) {
            throw new InvalidArgumentException( "Unknown admin resource" );
        }
        return $definition;
    }

    public function list( string $resource ): array {
        $definition = $this->definition( $resource );
        $columns = array_keys( $definition[ "fields" ] );
        $primaryKey = (string) $definition[ "primaryKey" ];
        $sql =
            "SELECT " .
            implode( ", ", $columns ) .
            " FROM " .
            $definition[ "table" ] .
            " ORDER BY {$primaryKey} ASC";
        return $this->database->selectFromJson( $sql, "", [ ] );
    }

    public function save( string $resource, array $input ): bool {
        $definition = $this->definition( $resource );
        $primaryKey = (string) $definition[ "primaryKey" ];
        $originalKey = $input[ "_originalKey" ] ?? null;
        if ( $originalKey !== null && $originalKey !== "" && ( $input[ $primaryKey ] ?? "" ) === "" ) {
            $input[ $primaryKey ] = $originalKey;
        }
        if ( $originalKey === null || $originalKey === "" ) {
            foreach ( $definition[ "fields" ] as $name => $field ) {
                if ( !array_key_exists( "defaultOnAdd", $field ) || ( $input[ $name ] ?? "" ) !== "" ) {
                    continue;
                }
                $input[ $name ] = $field[ "defaultOnAdd" ] === "@today"
                    ? date( "Y-m-d" )
                    : $field[ "defaultOnAdd" ];
            }
        }
        $data = $this->normalize( $definition, $input );
        if ( $originalKey === null || $originalKey === "" ) {
            unset( $data[ $primaryKey ] );
            if ( empty( $definition[ "fields" ][ $primaryKey ][ "generated" ] ) ) {
                $data[ $primaryKey ] = $this->normalizeValue(
                    $definition[ "fields" ][ $primaryKey ],
                    $input[ $primaryKey ] ?? null,
                );
            }
            return $this->database->insert( $definition[ "table" ], $data );
        }

        unset( $data[ $primaryKey ] );

        return $this->database->update(
            $definition[ "table" ],
            $data,
            "{$primaryKey} = ?",
            $this->typeChar( $definition[ "fields" ][ $primaryKey ] ),
            [ $this->normalizeValue( $definition[ "fields" ][ $primaryKey ], $originalKey ) ],
        );
    }

    public function delete( string $resource, mixed $key ): bool {
        $definition = $this->definition( $resource );
        $primaryKey = (string) $definition[ "primaryKey" ];

        return $this->database->delete(
            $definition[ "table" ],
            "{$primaryKey} = ?",
            $this->typeChar( $definition[ "fields" ][ $primaryKey ] ),
            [ $this->normalizeValue( $definition[ "fields" ][ $primaryKey ], $key ) ],
        );
    }

    // PRIVATE FUNCTIONS
    private function typeChar( array $field ): string { return in_array( $field[ "type" ] ?? "", [ "integer", "boolean" ], true ) ? "i" : "s"; }

    private function normalize( array $definition, array $input ): array {
        $data = [ ];
        foreach ( $definition[ "fields" ] as $name => $field ) {
            if ( !empty( $field[ "generated" ] ) && ( $input[ $name ] ?? "" ) === "" ) {
                continue;
            }
            $value = $this->normalizeValue( $field, $input[ $name ] ?? null );
            if ( !empty( $field[ "required" ] ) && ( $value === "" || $value === null ) ) {
                throw new InvalidArgumentException( "{$field["label"]} is required" );
            }
            $data[ $name ] = $value;
        }
        return $data;
    }

    private function normalizeValue( array $field, mixed $value ): mixed {
        return match ( $field[ "type" ] ?? "text" ) {
            "integer" => (int) $value,
            "boolean" => filter_var( $value, FILTER_VALIDATE_BOOL ) ? 1 : 0,
            "select" => in_array( trim( (string) $value ), $field[ "options" ] ?? [ ], true )
                ? trim( (string) $value )
                : throw new InvalidArgumentException( "Invalid {$field["label"]} option" ),
            "url" => $value === ""
                ? ""
                : ( filter_var( $value, FILTER_VALIDATE_URL ) ?:
                throw new InvalidArgumentException( "Invalid URL" ) ),
            default => trim( (string) $value ),
        };
    }
}
