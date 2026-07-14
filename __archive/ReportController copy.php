<?php

class ReportController {
    private static ?ReportController $instance = null;
    private static Tracker $commonTracker;
    private static Tracker $apiTracker;
    private static Tracker $webTracker;

    private static ?ContextManager $contextManager = null;
    private static ?ConfigStore $configStore = null;
    private static ?TwitchUserBridge $twitchUserBridge = null;
    private static ?CooldownManager $cooldownManager = null;
    private static array $reports;

    public static function prime(
        ContextManager $contextManager, 
        ConfigStore $configStore, 
        TwitchUserBridge $twitchUserBridge,
        CooldownManager $cooldownManager
    ) {
        self::$commonTracker = new Tracker(Tracker::COMMON_LOG);
        self::$apiTracker = new Tracker(Tracker::API_LOG);
        self::$webTracker = new Tracker(Tracker::WEB_LOG);
        
        self::$reports = [];
        self::$contextManager = $contextManager;
        self::$configStore = $configStore;
        self::$twitchUserBridge = $twitchUserBridge;
        self::$cooldownManager = $cooldownManager;
    }

    public static function createReport(string $code) {
        if (self::$configStore == null) {
            self::reportMissingConfigStore();
            return;
        }

        $codeDetails = self::$configStore->getCodeDetails($code);
        $report = new StatusReport($code, $codeDetails);

        self::addReport($code, $report);
    }

    public static function sendReports() {
        foreach (self::$reports as $reportKey => $report) {
            self::replacePlaceholders($report);
            $chatMessage = $report->getChatMessage();
            $logMessage = $report->getLogMessage();

            if (!empty($chatMessage)) {
                self::$twitchUserBridge->sendChatMessage($chatMessage);
            }
            
            if (!empty($logMessage)) {
                self::$commonTracker->info($logMessage, __FILE__);
            }
        }

        self::$reports = [];
    }

    public static function addExtraData(string $key, mixed $value) {
        if (self::$contextManager == null) {
            self::reportMissingContextManager();
            return;
        }

        self::$contextManager->getExtraContext()->addData($key, $value);
    }

    public static function sendDirectToLog(mixed $logMessage) {
        if (!empty($logMessage)) {
            $message = print_r($logMessage, true);
            self::$commonTracker->info($message, __FILE__);
        }
    }

    // PRIVATE FUNCTIONS
    private static function addReport(string $code, StatusReport $report): string {
        $reportKey = "$code+" . time();
        self::$reports[$reportKey] = $report;
        return $reportKey;
    }

    private static function getCooldown() {
        $interaction = self::$contextManager->getInteraction();

        $cooldown = self::$cooldownManager->getCooldown($interaction);
    }

    private static function reportMissingConfigStore() {
        $code = "R101";
        $codeDetails = [
            "constantName" => "REPORT__MISSING_CONFIG_STORE",
            "logTemplate" => "ConfigStore is Null. Did you run ReportController->giveTools?"
        ];

        $report = new StatusReport($code, $codeDetails);
        self::addReport($code, $report);
        self::sendReports();
    }

    private static function reportMissingContextManager() {
        $code = "R102";
        $codeDetails = [
            "constantName" => "REPORT__MISSING_CONTEXT_MANAGER",
            "logTemplate" => "ContextManager is Null. Did you run ReportController->giveTools?"
        ];

        $report = new StatusReport($code, $codeDetails);
        self::addReport($code, $report);
        self::sendReports();
    }

    private static function replacePlaceholders(StatusReport $report) {
        $report->replacePlaceholders([
            "{FILE}" => self::$contextManager->getFile(),
            "{KEY}" => self::$contextManager->getExtraKey(),
            "{VALUE}" => self::$contextManager->getExtraValue(),
            "{FUNCTION_NAME}" => self::$contextManager->getFunctionName(),
            "{URL_KEY}" => self::$contextManager->getUrlApiKey(),
            "{API_KEY}" => self::$contextManager->getWebApiKey(),
            "{SQL_ERROR_NUM}" => self::$contextManager->getSqlErrNum(),
            "{SQL_ERROR}" => self::$contextManager->getSqlErr(),
            "{USER}" => self::$contextManager->getLoginName(),
            "{COST}" => self::$contextManager->getCost(),
            "{BALANCE}" => self::$contextManager->getGemBalance(),
            "{CSS_FILE}" => self::$contextManager->getCssFile(),
            "{JS_FILE}" => self::$contextManager->getJsFile(),
            "{CURL_URL}" => self::$contextManager->getCurlUrl(),
            "{SERVER_ID}" => self::$contextManager->getServerId(),
            "{CURL_HTTP_CODE}" => self::$contextManager->getCurlHttpCode(),
            "{CURL_RESPONSE}" => self::$contextManager->getCurlResponse(),
            "{INTERACTION}" => self::$contextManager->getInteraction(),
            "{INTERACTION_TYPE}" => self::$contextManager->getInteractionType(),
            "{COOLDOWN}" => self::getCooldown(),
            "{GAME}" => self::$contextManager->getGameName()
        ]);
    }
    
}
