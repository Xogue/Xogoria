<?php

class DataController {
    private RequestData $requestData;
    private SessionData $sessionData;

    private InputDataContext $inputDataContext;
    private UserContext $userContext;

    public function __construct(
        private MySqlManager $mySqlManager, 
        private ConfigManager $configManager,
        private LoreManager $loreManager
    ) {
        $this->requestData = new RequestData();
        $this->sessionData = new SessionData();
    }

    // GET DATA BITS
    public function getApiKey(): string { return $this->requestData->getApiKey(); }
    public function getRequestType(): string { return $this->requestData->getRequestType(); }

    // SIMPLE CONTEXTS
    public function getTwitchAppContext() { return new TwitchAppContext($this->configManager); }
    public function getTwitchUserContext() { return new TwitchUserContext($this->configManager); }
    public function getDataStoreContext() { return new DataStoreContext($this->configManager); }
    public function getInputDataContext(bool $refresh = false) {
        if ($refresh) {
            $this->requestData = new RequestData();
            $this->sessionData = new SessionData();
        }
        return $this->inputDataContext ??= new InputDataContext($this->requestData, $this->sessionData);
    }
    public function getPrivateApiContext() { return new PrivateApiContext($this->configManager->getPrivateStore()); }
    public function getWorkerContext() { return new WorkerContext($this->configManager); }

    // COMPLEX CONTEXTS
    public function getUserContext() {
        $inputContext = $this->getInputDataContext();
        return $this->userContext ??= new UserContext($inputContext, $this->mySqlManager);
    }

    public function buildLore() {
        $loreChapters = $this->mySqlManager->fetchData('allChapters');
        $loreAudio = $this->mySqlManager->fetchData('allAudio');
        $loreStreams = $this->mySqlManager->fetchData('allStreams');

        $this->loreManager->buildStory($loreChapters, $loreAudio, $loreStreams);
    }

    
}
