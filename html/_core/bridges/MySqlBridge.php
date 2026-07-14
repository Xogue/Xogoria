<?php

class MySqlBridge {
    private string $dbHost;
    private string $dbUser;
    private string $dbPass;
    private string $dbName;
    private int $dbPort;

    private ?mysqli $connection = null;
    private PrivateStore $privateStore;

    public function __construct(PrivateStore $privateStore) {
        $this->privateStore = $privateStore;

        $this->dbHost        = $this->privateStore->databaseHost();
        $this->dbUser        = $this->privateStore->databaseUser();
        $this->dbPass        = $this->privateStore->databasePass();
        $this->dbName        = $this->privateStore->databaseName();
        $this->dbPort        = (int) $this->privateStore->databasePort();

        $this->connect();
    }

    public function __destruct() {
        if ( $this->connection instanceof mysqli ) {
            $this->connection->close();
        }
    }

    public function select( string $query, string $types = '', array $params = [] ): array|false {
        $statement = $this->executeStatement( $query, $types, $params );
        if ( $statement === false ) {
            return false;
        }

        $result = $statement->get_result();
        if ( $result === false ) {
            $statement->close();
            return false;
        }

        $rows = $result->fetch_all( MYSQLI_ASSOC );
        $statement->close();

        return $rows;
    }

    public function execute( string $query, string $types = '', array $params = [] ): bool {
        $statement = $this->executeStatement( $query, $types, $params );
        if ( $statement === false ) {
            return false;
        }

        $statement->close();
        return true;
    }

    public function insert( string $table, array $fields ): bool {
        if ( empty( $fields ) ) {
            return false;
        }

        $columns      = implode( ', ', array_keys( $fields ) );
        $placeholders = implode( ', ', array_fill( 0, count( $fields ), '?' ) );
        $query        = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $params       = array_values( $fields );

        return $this->execute( $query, $this->getTypes( $params ), $params );
    }

    public function update( string $table, array $fields, string $whereClause, string $whereTypes = '', array $whereParams = [] ): bool {
        if ( empty( $fields ) ) {
            return false;
        }

        if ( trim( $whereClause ) === '' ) {
            return false;
        }

        $setParts = [];
        foreach ( array_keys( $fields ) as $column ) {
            $setParts[] = "{$column} = ?";
        }

        $fieldParams = array_values( $fields );
        $query       = "UPDATE {$table} SET " . implode( ', ', $setParts ) . " WHERE {$whereClause}";
        $types       = $this->getTypes( $fieldParams ) . $whereTypes;
        $params      = array_merge( $fieldParams, $whereParams );

        return $this->execute( $query, $types, $params );
    }

    public function delete( string $table, string $whereClause, string $whereTypes = '', array $whereParams = [] ): bool {
        if ( trim( $whereClause ) === '' ) {
            return false;
        }

        $query = "DELETE FROM {$table} WHERE {$whereClause}";

        return $this->execute( $query, $whereTypes, $whereParams );
    }

    private function connect(): void {
        if ( $this->isConnected() ) {
            return;
        }

        if (!class_exists(mysqli::class)) {
            (new Logger())->error('The mysqli PHP extension is not installed');
            return;
        }

        try {
            $this->connection = new mysqli($this->dbHost, $this->dbUser, $this->dbPass, $this->dbName, $this->dbPort);
        } catch (mysqli_sql_exception $error) {
            (new Logger())->error('MySQL connection failed', ['error_number' => $error->getCode(), 'error' => $error->getMessage()]);
            $this->connection = null;
            return;
        }
        if ( $this->connection->connect_errno !== 0 ) {
            $errNo  = (int) $this->connection->connect_errno;
            $errMsg = $this->connection->connect_error;
            (new Logger())->error('MySQL connection failed', ['error_number' => $errNo, 'error' => $errMsg]);
            $this->connection = null;
            return;
        }

        if ( !$this->connection->set_charset( 'utf8mb4' ) ) {
        }
    }

    private function isConnected(): bool {
        return $this->connection instanceof mysqli;
    }

    private function executeStatement( string $query, string $types, array $params ): mysqli_stmt|false {
        if ( !$this->isConnected() ) {
            return false;
        }

        if ( strlen( $types ) !== count( $params ) ) {
            return false;
        }

        $statement = $this->connection->prepare( $query );
        if ( !$statement ) {
            return false;
        }

        if ( !empty( $params ) ) {
            $bindParams = $this->makeBindParams( $types, $params );
            if ( !call_user_func_array( [$statement, 'bind_param'], $bindParams ) ) {
                $statement->close();
                return false;
            }
        }

        if ( !$statement->execute() ) {
            $statement->close();
            return false;
        }

        return $statement;
    }

    private function makeBindParams( string $types, array &$params ): array {
        $bindParams = [&$types];
        foreach ( $params as &$param ) {
            $bindParams[] = &$param;
        }

        return $bindParams;
    }

    private function getTypes( array $params ): string {
        $types = '';
        foreach ( $params as $param ) {
            $types .= $this->detectTypeChar( $param );
        }

        return $types;
    }

    private function detectTypeChar( mixed $value ): string {
        return match ( true ) {
            is_int( $value )   => 'i',
            is_float( $value ) => 'd',
            is_bool( $value )  => 'i',
            default            => 's',
        };
    }
}
