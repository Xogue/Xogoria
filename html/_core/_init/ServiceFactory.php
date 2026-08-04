<?php

// Production deployments may update application classes without rebuilding Composer's
// generated classmap immediately. Keep newly introduced services available during that window.
$deploymentClassFallbacks = [
    UserTextPolicy::class => dirname( __DIR__ ) . "/services/UserTextPolicy.php",
    ApiResponseNormalizer::class => dirname( __DIR__ ) . "/services/ApiResponseNormalizer.php",
    ResponseLibrary::class => __DIR__ . "/_status/ResponseLibrary.php",
];
foreach ( $deploymentClassFallbacks as $className => $classPath ) {
    if ( !class_exists( $className, false ) ) {
        require_once $classPath;
    }
}
unset( $deploymentClassFallbacks, $className, $classPath );

final class ServiceFactory {
    private array $cachedService = [ ];

    // PUBLIC FUNCTIONS
    public function templateManager( )  : TemplateManager   { return $this->service( __FUNCTION__, fn( ) => new TemplateManager( ) ); }
    public function assetManager( )     : AssetManager      { return $this->service( __FUNCTION__, fn( ) => new AssetManager( ) ); }
    public function adminController( )  : AdminController   { return $this->service( __FUNCTION__, fn( ) => new AdminController( $this ) ); }
    public function gameManager( )      : GameManager       { return $this->service( __FUNCTION__, fn( ) => new GameManager( $this->templateManager( ) ) ); }
    public function loreManager( )      : LoreManager       { return $this->service( __FUNCTION__, fn( ) => new LoreManager( $this->templateManager( ) ) ); }
    public function configManager( )    : ConfigManager     { return $this->service( __FUNCTION__, fn( ) => new ConfigManager( $this->gameManager( ) ) ); }
    public function mySqlManager( )     : MySqlManager      { return $this->service( __FUNCTION__, fn( ) => new MySqlManager( $this->configManager( ) ) ); }
    public function twitchController( ) : TwitchController  { return $this->service( __FUNCTION__, fn( ) => new TwitchController( $this->dataController( ) ) ); }
    public function soundQueueManager( ): SoundQueueManager { return $this->service( __FUNCTION__, fn( ) => new SoundQueueManager( $this->dataStore( ) ) ); }
    public function apiResponseNormalizer( ): ApiResponseNormalizer {
        return $this->service( __FUNCTION__, fn( ) => new ApiResponseNormalizer( new JsonHandler( ) ) );
    }
    public function userTextPolicy( ): UserTextPolicy {
        return $this->service(
            __FUNCTION__,
            fn( ) => new UserTextPolicy( $this->configManager( )->getConfigStore( )->getRestrictedWords( ) ),
        );
    }

    public function logger( string $channel = Logger::CHANNEL_COMMON ): Logger {
        return $this->service( "logger.{$channel}", fn( ) => new Logger( $channel ) );
    }

    public function collectionManager( ): CollectionManager {
        return $this->service(
            __FUNCTION__,
            fn( ) => new CollectionManager( $this->templateManager( ) ),
        );
    }

    public function dataController( ): DataController {
        return $this->service(
            __FUNCTION__,
            fn( ) => new DataController(
                $this->mySqlManager( ),
                $this->configManager( ),
                $this->loreManager( ),
            ),
        );
    }

    public function contextManager( ): ContextManager {
        return $this->service(
            __FUNCTION__,
            fn( ) => new ContextManager( $this->dataController( ), $this->configManager( ) ),
        );
    }

    public function userController( ): UserController {
        return $this->service(
            __FUNCTION__,
            fn( ) => new UserController(
                $this->mySqlManager( ),
                $this->contextManager( )->getUser( ),
                $this->contextManager( )->getInputData( ),
            ),
        );
    }

    public function interactionManager( ): InteractionManager {
        return $this->service(
            __FUNCTION__,
            fn( ) => new InteractionManager(
                $this->configManager( ),
                $this->contextManager( )->getUser( ),
            ),
        );
    }

    public function dataStore( ): DataStore {
        return $this->service(
            __FUNCTION__,
            fn( ) => new DataStore( $this->contextManager( )->getDataStore( ) ),
        );
    }

    public function clipManager( ): ClipManager {
        return $this->service(
            __FUNCTION__,
            fn( ) => new ClipManager( $this->mySqlManager( ), $this->logger( ) ),
        );
    }

    public function adminResourceManager( ): AdminResourceManager {
        return $this->service(
            __FUNCTION__,
            fn( ) => new AdminResourceManager(
                $this->mySqlManager( ),
                new JsonHandler( ),
                $this->logger( Logger::CHANNEL_WEB ),
            ),
        );
    }

    public function adminConfigManager( ): AdminConfigManager {
        return $this->service(
            __FUNCTION__,
            fn( ) => new AdminConfigManager( new JsonHandler( ), $this->logger( Logger::CHANNEL_WEB ) ),
        );
    }

    public function twitchClipService( ): TwitchClipService {
        return $this->service(
            __FUNCTION__,
            fn( ) => new TwitchClipService(
                $this->configManager( ),
                $this->logger( Logger::CHANNEL_API ),
            ),
        );
    }

    public function clipDeletionRegistry( ): ClipDeletionRegistry {
        return $this->service(
            __FUNCTION__,
            fn( ) => new ClipDeletionRegistry( $this->logger( Logger::CHANNEL_WEB ) ),
        );
    }

    public function clipReviewManager( ): ClipReviewManager {
        return $this->service(
            __FUNCTION__,
            fn( ) => new ClipReviewManager(
                $this->clipManager( ),
                $this->twitchClipService( ),
                $this->clipDeletionRegistry( ),
            ),
        );
    }

    public function createWorker( string $request ): ?WorkerInterface {
        return match ( $request ) {
            "collection" => new CollectionWorker( $this->mySqlManager( ) ),
            "currency" => new CurrencyWorker(
                $this->contextManager( )->getUser( ),
                $this->mySqlManager( ),
            ),
            "interaction" => new InteractionWorker(
                $this->interactionManager( ),
                $this->contextManager( )->getUser( ),
                $this->mySqlManager( ),
                $this->userTextPolicy( ),
            ),
            "configure" => new ConfigureWorker( $this->contextManager( )->getUser( ) ),
            default => null,
        };
    }

    // PRIVATE FUNCTIONS
    private function service( string $id, callable $factory ): object {
        return $this->cachedService[ $id ] ??= $factory( );
    }
}
