<?php

class MySqlManager {
    private ConfigManager $configManager;
    private MySqlBridge $mySqlBridge;

    // MAGIC FUNCTIONS
    public function __construct( ConfigManager $configManager ) {
        $this->configManager = $configManager;
        $this->mySqlBridge = new MySqlBridge( $configManager->getPrivateStore( ) );
    }

    // PUBLIC FUNCTIONS
    public function getUserBalance( string $userId ): int { return $this->selectUserWithId( $userId )[ "gemBalance" ] ?? 0; }

    public function selectUserWithId( string $userId ): array {
        $rows = $this->mySqlBridge->select(
            "SELECT * FROM users WHERE platformUserId = ? LIMIT 1",
            "s",
            [ $userId ],
        );

        return is_array( $rows ) ? $rows[ 0 ] ?? [ ] : [ ];
    }

    public function selectUserWithUsername( string $username ): array {
        $rows = $this->mySqlBridge->select( "SELECT * FROM users WHERE username = ? LIMIT 1", "s", [
            $username,
        ] );

        return is_array( $rows ) ? $rows[ 0 ] ?? [ ] : [ ];
    }

    public function selectFromJson( string $query, string $types, array $params ): array {
        $rows = $this->mySqlBridge->select( $query, $types, $params );
        return is_array( $rows ) ? $rows : [ ];
    }

    public function execute( string $query, string $types = "", array $params = [ ] ): bool {
        return $this->mySqlBridge->execute( $query, $types, $params );
    }

    public function update(
        string $table,
        array $fields,
        string $whereClause,
        string $whereTypes = "",
        array $whereParams = [ ],
    ): bool {
        return $this->mySqlBridge->update( $table, $fields, $whereClause, $whereTypes, $whereParams );
    }

    public function insert( string $table, array $fields ): bool {
        return $this->mySqlBridge->insert( $table, $fields );
    }

    public function delete(
        string $table,
        string $whereClause,
        string $whereTypes = "",
        array $whereParams = [ ],
    ): bool {
        return $this->mySqlBridge->delete( $table, $whereClause, $whereTypes, $whereParams );
    }

    public function setUserBalance( string $userId, int $amount ): bool {
        return $this->mySqlBridge->update(
            "users",
            [ "gemBalance" => $amount ],
            "platformUserId = ?",
            "s",
            [ $userId ],
        );
    }

    public function addToUserBalance( string $userId, int $amount ): bool|int {
        $currentBalance = $this->selectUserWithId( $userId )[ "gemBalance" ] ?? 0;
        if ( $currentBalance + $amount > 1500 && $userId !== "66369644" ) {
            // exclude Xogue from limits
            if ( $currentBalance <= 1500 ) {
                $newBalance = 1500;
            } else {
                return false;
            }
        } else {
            $newBalance = $currentBalance + $amount;
        }

        $success = $this->mySqlBridge->update(
            "users",
            [ "gemBalance" => $newBalance ],
            "platformUserId = ?",
            "s",
            [ $userId ],
        );

        return $success ? $newBalance : false;
    }

    public function subtractFromUserBalance( string $userId, int $amount ): bool {
        $currentBalance = $this->selectUserWithId( $userId )[ "gemBalance" ] ?? 0;
        $newBalance = $currentBalance - $amount;

        return $this->mySqlBridge->update(
            "users",
            [ "gemBalance" => $newBalance ],
            "platformUserId = ?",
            "s",
            [ $userId ],
        );
    }

    public function insertUser( string $userId, string $username, string $displayName ): bool {
        $dbDefaultData = [
            "platform" => "twitch",
            "platformUserId" => $userId,
            "username" => $username,
            "displayName" => $displayName,
            "gemBalance" => 0,
            "role" => "none",
        ];

        return $this->mySqlBridge->insert( "users", $dbDefaultData );
    }

    // OTHERS
    public function fetchData( string $keyword ) {
        return match ( $keyword ) {
            "allCommands" => $this->getAllCommands( ),
            "allMonsters" => $this->getAllMonsters( ),
            "allObjectives" => $this->getAllObjectives( ),
            "allQuotes" => $this->getAllQuotes( ),
            "allChapters" => $this->getLoreChapters( ),
            "allAudio" => $this->getLoreAudio( ),
            "allStreams" => $this->getLoreStreams( ),
            "randomQuote" => $this->getRandQuote( ),
            "randomMonster" => $this->getRandMonster( ),
            "randomObjective" => $this->getRandObjective( ),
            default => null,
        };
    }

    public function fetchDataByDetail( string $keyword, string|int $detail ) {
        if ( $this->isInt( $detail ) ) {
            return match ( $keyword ) {
                "quote" => $this->getQuoteById( $detail ),
                "monster" => $this->getMonsterById( $detail ),
                "objective" => $this->getObjectiveById( $detail ),
                default => null,
            };
        } else {
            return match ( $keyword ) {
                "quote" => $this->searchQuoteByText( $detail ),
                "monster" => $this->searchMonsterByName( $detail ),
                "objective" => $this->searchObjectiveByRequirement( $detail ),
                default => null,
            };
        }
    }

    // PRIVATE FUNCTIONS
    private function getAllCommands( ) { return $this->runQueryFromJson( "allCommands", "", [ ] ); }
    private function getAllMonsters( ) { return $this->runQueryFromJson( "allMonsters", "", [ ] ); }
    private function getAllObjectives( ) { return $this->runQueryFromJson( "allObjectives", "", [ ] ); }
    private function getAllQuotes( ) { return $this->runQueryFromJson( "allQuotes", "", [ ] ); }
    private function getLoreChapters( ) { return $this->runQueryFromJson( "allChapters", "", [ ] ); }
    private function getLoreAudio( ) { return $this->runQueryFromJson( "allChapterAudio", "", [ ] ); }
    private function getLoreStreams( ) { return $this->runQueryFromJson( "allChapterStreams", "", [ ] ); }
    private function getRandQuote( ) { return $this->runQueryFromJson( "randomQuote", "", [ ] ); }
    private function getRandMonster( ) { return $this->runQueryFromJson( "randomMonster", "", [ ] ); }
    private function getRandObjective( ) { return $this->runQueryFromJson( "randomObjective", "", [ ] ); }

    private function getQuoteById( int $id ) { return $this->runQueryFromJson( "quoteById", "i", [ $id ] ); }
    private function getMonsterById( int $id ) { return $this->runQueryFromJson( "monsterById", "i", [ $id ] ); }
    private function getObjectiveById( int $id ) { return $this->runQueryFromJson( "objectiveById", "i", [ $id ] ); }
    private function searchQuoteByText( string $text ) { return $this->runQueryFromJson( "searchQuoteByText", "s", [ "%{$text}%" ] ); }
    private function searchMonsterByName( string $name ) { return $this->runQueryFromJson( "searchMonsterByName", "s", [ "%{$name}%" ] ); }
    private function searchObjectiveByRequirement( string $requirement ) { return $this->runQueryFromJson( "searchObjectiveByRequirement", "s", [ "%{$requirement}%" ] ); }
    private function isInt( mixed $value ) { return filter_var( $value, FILTER_VALIDATE_INT ) !== false; }

    private function runQueryFromJson( string $query, string $types, array $params ) {
        $query = $this->configManager->getSqlQuery( $query );
        return $this->selectFromJson( $query, $types, $params );
    }
}
