<?php

class UserContext {
    private string $userId = "";
    private string $loginName = "";
    private string $displayName = "";
    private int $gemBalance = 0;
    private bool $userIdentified = false;
    private bool $isAdmin = false;
    private string $role = "";

    // MAGIC FUNCTIONS
    public function __construct(
        private InputDataContext $inputData,
        private MySqlManager $mySqlManager,
    ) {
        $lookupId = $this->inputData->getUserId( );
        $lookupName = $this->inputData->getUsername( );
        if ( $lookupId === "" ) {
            return;
        }

        $identity = $this->mySqlManager->selectUserWithId( $lookupId );

        if ( !$identity && $lookupName !== "" ) {
            $identity = $this->mySqlManager->selectUserWithUsername( $lookupName );
        }

        if ( !empty( $identity ) ) {
            $this->userId = $identity[ "platformUserId" ] ?? "";
            $this->loginName = $identity[ "username" ] ?? "";
            $this->displayName = $identity[ "displayName" ] ?? $this->loginName;
            $this->role = $identity[ "role" ] ?? "none";
            $this->gemBalance = (int) ( $identity[ "gemBalance" ] ?? 0 );
            $this->isAdmin = $this->role === "admin" || $this->role === "owner";

            $this->userIdentified = true;
        }
    }

    // PUBLIC FUNCTIONS
    public function userLoggedIn( )  : bool   { return $this->userIdentified; }
    public function getUserId( )     : string { return $this->userId; }
    public function getLoginName( )  : string { return $this->loginName; }
    public function getDisplayName( ): string { return $this->displayName; }
    public function getGemBalance( ) : int    { return $this->gemBalance; }
    public function getRole( )       : string { return $this->role; }
    public function isAdmin( )       : bool   { return $this->isAdmin; }

    public function refreshIdentity( InputDataContext $inputData ): void {
        $this->inputData = $inputData;

        $lookupId = $this->inputData->getUserId( );
        $lookupName = $this->inputData->getUsername( );
        if ( $lookupId === "" ) {
            return;
        }

        $identity = $this->mySqlManager->selectUserWithId( $lookupId );

        if ( !$identity && $lookupName !== "" ) {
            $identity = $this->mySqlManager->selectUserWithUsername( $lookupName );
        }

        if ( !empty( $identity ) ) {
            $this->userId = $identity[ "platformUserId" ] ?? "";
            $this->loginName = $identity[ "username" ] ?? "";
            $this->displayName = $identity[ "displayName" ] ?? "";
            $this->role = $identity[ "role" ] ?? "none";
            $this->gemBalance = (int) ( $identity[ "gemBalance" ] ?? -1 );
            $this->isAdmin = $this->role === "admin" || $this->role === "owner";

            $this->userIdentified = true;
        }
    }

    public function logout( ): void {
        $this->userId = "";
        $this->loginName = "";
        $this->displayName = "";
        $this->gemBalance = 0;
        $this->userIdentified = false;
        $this->isAdmin = false;
        $this->role = "";
    }
}
