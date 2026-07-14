<?php

class DataStore {
    private static ?Redis $redis = null;

    public function __construct(private DataStoreContext $context) {
        $this->connect();
    }

    public function __destruct() {
        if ( self::$redis !== null ) {
            self::$redis->close();
            self::$redis = null;
        }
    }

    public function setExpiringKey( string $key, int $expiration, string $value ): bool {
        $success = self::$redis->setex( $key, $expiration, $value );
        return $success;
    }

    public function setStableKey( string $key, string $value ): bool {
        $success = self::$redis->set( $key, $value );
        return $success;
    }

    public function checkExpiringKey( string $key ): int {
        $ttl = self::$redis->ttl( $key );
        return $ttl;
    }

    public function getKey( string $key ): string {
        if ( self::$redis->exists( $key ) === 0 ) { return ''; }
        return self::$redis->get( $key );
    }

    public function addToList( string $key, string $value ): bool {
        $success = self::$redis->rPush( $key, $value );
        return $success;
    }

    public function getFirstFromList( string $key, int $timeout = 0 ): string {
        $value = self::$redis->blPop( [$key], $timeout );
        if ( $value === false ) { return ''; }
        return $value[1];
    }

    public function getListLength(string $key): int {
        return self::$redis->lLen($key);
    }

    // PRIVATE FUNCTIONS
    private function connect(): void {
        if ( self::$redis !== null ) {return;}

        self::$redis = new Redis();
        self::$redis->connect( $this->context->getHost(), $this->context->getPort(), 0.2 );
        self::$redis->ping();
    }
}
