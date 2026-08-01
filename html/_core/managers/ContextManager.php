<?php

class ContextManager {
    private ?UserContext $user = null;
    private ?InputDataContext $inputData = null;
    private ?TwitchAppContext $twitchApp = null;
    private ?TwitchUserContext $twitchUser = null;
    private ?DataStoreContext $dataStore = null;
    private ?PrivateApiContext $privateApi = null;
    private ?WorkerContext $workerContext = null;

    // MAGIC FUNCTIONS
    public function __construct(
        private DataController $dataController,
        private ConfigManager $configManager,
    ) { }

    // PUBLIC FUNCTIONS
    public function getDataStore( ) : DataStoreContext  { return $this->dataStore ??= $this->dataController->getDataStoreContext( ); }
    public function getUser( )      : UserContext       { return $this->user ??= $this->dataController->getUserContext( ); }
    public function getTwitchApp( ) : TwitchAppContext  { return $this->twitchApp ??= $this->dataController->getTwitchAppContext( ); }
    public function getTwitchUser( ): TwitchUserContext { return $this->twitchUser ??= $this->dataController->getTwitchUserContext( ); }
    public function getPrivateApi( ): PrivateApiContext { return $this->privateApi ??= $this->dataController->getPrivateApiContext( ); }
    public function getWorker( )    : WorkerContext     { return $this->workerContext ??= $this->dataController->getWorkerContext( ); }
    // DELEGATION METHODS: Input Data
    // User
    public function getInputUserId( ) { return $this->getInputData( )->getUserId( ); }
    public function getInputUsername( ) { return $this->getInputData( )->getUsername( ); }
    public function getInputDisplayName( ) { return $this->getInputData( )->getDisplayName( ); }
    // All Workers
    public function getType( ) { return $this->getInputData( )->getType( ); }
    public function getAction( ) { return $this->getInputData( )->getAction( ); }
    public function getUrlApiKey( ) { return $this->getInputData( )->getUrlApiKey( ); }
    // Currency Worker
    public function getAmount( ) { return $this->getInputData( )->getAmount( ); }
    // Collection Worker
    public function getMonster( ) { return $this->getInputData( )->getMonster( ); }
    public function getObjective( ) { return $this->getInputData( )->getObjective( ); }
    public function getQuote( ) { return $this->getInputData( )->getQuote( ); }
    // DELEGATION METHODS: User
    public function userLoggedIn( ) { return $this->getUser( )->userLoggedIn( ); }
    public function getUserId( ) { return $this->getUser( )->getUserId( ); }
    public function getLoginName( ) { return $this->getUser( )->getLoginName( ); }
    public function getDisplayName( ) { return $this->getUser( )->getDisplayName( ); }
    public function getGemBalance( ) { return $this->getUser( )->getGemBalance( ); }
    public function getRole( ) { return $this->getUser( )->getRole( ); }
    public function isAdmin( ) { return $this->getUser( )->isAdmin( ); }
    // DELEGATION METHODS: Interaction
    public function getServerId( ) { return $this->configManager->getActiveProfile( )->getServerId( ); }
    public function getInteractionType( ) { return $this->getInputData( )->getType( ); }
    public function getInteraction( ) { return $this->getInputData( )->getAction( ); }
    public function getGameName( ) { return $this->configManager->getActiveGame( ); }
    // DELEGATION METHODS: Private API
    public function getWebApiKey( ) { return $this->getPrivateApi( )->getWebApiKey( ); }

    // OTHER
    public function refreshIdentity( InputDataContext $inputData ): void { $this->getUser( )->refreshIdentity( $inputData ); }

    public function getInputData( bool $refresh = false ): InputDataContext {
        if ( $refresh || $this->inputData === null ) {
            $this->inputData = $this->dataController->getInputDataContext( $refresh );
        }
        return $this->inputData;
    }


    // public function __call($name, $arguments) {
    //     return "(NO METHOD: $name)";
    // }
 }
