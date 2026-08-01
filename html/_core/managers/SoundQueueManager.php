<?php

final class SoundQueueManager {
    private const QUEUE_KEY = "soundQueue";

    // MAGIC FUNCTIONS
    public function __construct( private DataStore $dataStore ) { }

    // PUBLIC FUNCTIONS
    public function count( ): int { return $this->dataStore->getListLength( self::QUEUE_KEY ); }

    public function push( string $value ): bool { return $value !== "" && $this->dataStore->addToList( self::QUEUE_KEY, $value ); }

    public function pull( int $timeout = 0 ): ?string {
        $result = $this->dataStore->getFirstFromList( self::QUEUE_KEY, max( 0, min( 300, $timeout ) ) );
        return $result === "" ? null : $result;
    }
}
