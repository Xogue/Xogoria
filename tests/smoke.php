<?php

declare( strict_types=1 );
define( "XOG_ROOT", dirname( __DIR__ ) . "/html" );
require dirname( __DIR__ ) . "/vendor/autoload.php";

$services = new ServiceFactory( );
$web = new WebController( $services );
assert( $web->getActiveGame( ) instanceof Game );
assert( $web->getActiveProfile( ) instanceof Profile );
assert( $services->contextManager( ) === $services->contextManager( ) );
assert( $services->contextManager( )->getInputData( ) === $services->contextManager( )->getInputData( ) );
assert( isset( $services->adminResourceManager( )->definitions( )[ "users" ] ) );
assert( isset( $services->adminConfigManager( )->files( )[ "minecraft" ] ) );
assert( is_string( $services->communityContentManager( )->source( ) ) );
$renderedCommunity = ( new CommunityMarkdownRenderer( ) )->render(
    "## Rules\n{toc: How community members should behave}\n\n**Be kind.** [Site](/about.php)\n\n:::warning Notice\nNo scripts: <script>alert(1)</script>\n:::",
);
assert( str_contains( $renderedCommunity, '<h2 id="rules"' ) );
assert( str_contains( $renderedCommunity, 'data-toc-description="How community members should behave"' ) );
assert( !str_contains( $renderedCommunity, "{toc:" ) );
assert( str_contains( $renderedCommunity, "<strong>Be kind.</strong>" ) );
assert( !str_contains( $renderedCommunity, "<script>" ) );
$richCommunity = ( new CommunityMarkdownRenderer( ) )->render(
    "| Name | Detail |\n| --- | --- |\n| One | Two |\n\n[Unsafe](javascript:alert(1))\n\n:::cards Picks\n### First\nText\n+++\n### Second\nText\n:::",
);
assert( str_contains( $richCommunity, '<table class="uiTable">' ) );
assert( !str_contains( $richCommunity, 'href="javascript:' ) );
assert( substr_count( $richCommunity, '<article class="uiPanel communityCard">' ) === 2 );
assert( str_contains( $richCommunity, 'id="picks"' ) );
$nestedCardCommunity = ( new CommunityMarkdownRenderer( ) )->render(
    ":::cards Details\n### Setup\nFirst line\nSecond line\n\n:::note Remember\nNested note text\n:::\n:::",
);
assert( str_contains( $nestedCardCommunity, "First line<br>\nSecond line" ) );
assert( str_contains( $nestedCardCommunity, 'communityBlock--note' ) );
assert( str_contains( $nestedCardCommunity, "Nested note text" ) );
assert( $services->clipDeletionRegistry( )->all( ) === [ ] );
$textPolicy = $services->userTextPolicy( );
assert( $textPolicy->isAllowed( "Flappy" ) );
assert( $textPolicy->isAllowed( "Classy Bat" ) );
assert( !$textPolicy->isAllowed( "f.u.c.k" ) );
assert( !$textPolicy->isAllowed( "sh1tbat" ) );
assert( !$textPolicy->isAllowed( "kill-yourself" ) );
$success = new WorkerResult( [ "item" => "value" ], "Completed." );
assert( $success->isSuccess( ) );
assert( json_decode( $success->toJson( ), true, flags: JSON_THROW_ON_ERROR )[ "success" ] === true );
$failure = WorkerResult::failure( "Bad request.", "bad_request", 422 );
assert( !$failure->isSuccess( ) );
assert( $failure->getHttpStatus( ) === 422 );

$responseDefinitions = json_decode(
    file_get_contents( XOG_ROOT . "/_core/_init/config/responseCodes.json" ),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$responseConstants = array_flip( ( new ReflectionClass( ResponseLibrary::class ) )->getConstants( ) );
foreach ( $responseDefinitions as $responseCode => $definition ) {
    if ( str_starts_with( $responseCode, "SECTION" ) ) {
        continue;
    }
    assert( isset( $definition[ "constantName" ], $definition[ "success" ], $definition[ "httpStatus" ], $definition[ "messageTemplate" ] ) );
    assert( isset( $responseConstants[ $responseCode ] ) );
    preg_match_all( '/\{[^}]+\}/', $definition[ "messageTemplate" ], $placeholderMatches );
    foreach ( array_unique( $placeholderMatches[ 0 ] ) as $placeholder ) {
        assert( isset( $definition[ "replaceMap" ][ $placeholder ] ) );
    }
}
foreach ( array_keys( $responseConstants ) as $responseCode ) {
    assert( isset( $responseDefinitions[ $responseCode ] ) );
}

$normalizer = $services->apiResponseNormalizer( );
$currencyResult = WorkerResult::success( ResponseLibrary::R_CURRENCY__GEMS_ADD, 14, [
    "foundGems" => 4,
    "totalGems" => 14,
] );
$normalized = $normalizer->normalize( $currencyResult, "currency", "", "addToUser" );
assert( array_keys( $normalized ) === [ "success", "code", "message", "value", "request", "meta" ] );
assert( $normalized[ "code" ] === "C100R" );
assert( $normalized[ "message" ] === "You found 4 gems! Your new total is 14." );
assert( $normalized[ "request" ] === [ "name" => "currency", "type" => "", "action" => "addToUser" ] );
assert( $normalized[ "meta" ]->foundGems === 4 );
assert( $normalizer->getHttpStatus( $currencyResult ) === 200 );

$missingRequest = WorkerResult::failureCode( ResponseLibrary::R_API__MISSING_REQUEST );
assert( $normalizer->getHttpStatus( $missingRequest ) === 400 );
assert( $normalizer->normalize( $missingRequest )[ "message" ] === "A request type is required." );

$zeroBalance = WorkerResult::success( ResponseLibrary::R_CURRENCY__BALANCE_CHECK, 0, [
    "totalGems" => 0,
] );
assert( $zeroBalance->isSuccess( ) );

$_SERVER[ "REQUEST_METHOD" ] = "GET";
$_GET = [
    "request" => "currency",
    "action" => "checkBalance",
    "userId" => "new-twitch-user",
    "username" => "newuser",
    "displayName" => "New User",
];
$_SESSION = [ ];
$newUserInput = new InputDataContext( new RequestData( ), new SessionData( ) );
$newUserBank = new class extends MySqlManager {
    public array $users = [ ];
    public function __construct( ) { }
    public function selectUserWithId( string $userId ): array { return $this->users[ $userId ] ?? [ ]; }
    public function selectUserWithUsername( string $username ): array { return [ ]; }
    public function insertUser( string $userId, string $username, string $displayName ): bool {
        $this->users[ $userId ] = [
            "platformUserId" => $userId,
            "username" => $username,
            "displayName" => $displayName,
            "gemBalance" => 0,
            "role" => "none",
        ];
        return true;
    }
};
$newUserContext = new UserContext( $newUserInput, $newUserBank );
assert( !$newUserContext->userLoggedIn( ) );
$newUserController = new UserController( $newUserBank, $newUserContext, $newUserInput );
assert( $newUserController->ensureTwitchUser( ) );
assert( $newUserContext->userLoggedIn( ) );
assert( $newUserContext->getUserId( ) === "new-twitch-user" );
assert( $newUserContext->getGemBalance( ) === 0 );

$_GET = [
    "request" => "currency",
    "action" => "checkBalance",
    "userId" => "id-only-twitch-user",
];
$idOnlyInput = new InputDataContext( new RequestData( ), new SessionData( ) );
$idOnlyContext = new UserContext( $idOnlyInput, $newUserBank );
$idOnlyController = new UserController( $newUserBank, $idOnlyContext, $idOnlyInput );
assert( $idOnlyController->ensureTwitchUser( ) );
assert( $idOnlyContext->userLoggedIn( ) );
assert( $idOnlyContext->getLoginName( ) === "id-only-twitch-user" );

$_GET = [
    "userId" => "aliased-twitch-user",
    "userName" => "Aliased User",
];
$aliasedInput = new InputDataContext( new RequestData( ), new SessionData( ) );
assert( $aliasedInput->getUsername( ) === "Aliased User" );
assert( $aliasedInput->getDisplayName( ) === "Aliased User" );

$workerContext = ( new ReflectionClass( WorkerContext::class ) )->newInstanceWithoutConstructor( );
$currencyUser = new class extends UserContext {
    public function __construct( ) { }
    public function userLoggedIn( ): bool { return true; }
    public function getUserId( ): string { return "test-user"; }
    public function getDisplayName( ): string { return "Test User"; }
    public function getGemBalance( ): int { return 10; }
};
for ( $randomTest = 0; $randomTest < 20; $randomTest++ ) {
    $currencyBank = new class extends MySqlManager {
        public function __construct( ) { }
        public function addToUserBalance( string $userId, int $amount ): bool|int { return 10 + $amount; }
    };
    $_SERVER[ "REQUEST_METHOD" ] = "GET";
    $_GET = [
        "request" => "currency",
        "action" => "addToUser",
        "amount" => -2147483648,
    ];
    $_SESSION = [ ];
    $currencyInput = new InputDataContext( new RequestData( ), new SessionData( ) );
    $currencyWorker = new CurrencyWorker( $currencyUser, $currencyBank );
    $currencyWorker->prime( $workerContext, $currencyInput );
    $randomAward = $currencyWorker->process( );
    assert( $randomAward->getCode( ) === ResponseLibrary::R_CURRENCY__GEMS_ADD );
    assert( $randomAward->getMeta( )[ "foundGems" ] >= 1 );
    assert( $randomAward->getMeta( )[ "foundGems" ] <= 5 );
    assert( str_contains( $normalizer->normalize( $randomAward )[ "message" ], "Your new total is" ) );
}

$collectionDatabase = new class extends MySqlManager {
    public function __construct( ) { }
    public function fetchData( string $keyword ): array {
        assert( $keyword === "randomQuote" );
        return [ [ "text" => "A normalized quote." ] ];
    }
};
$_GET = [ "request" => "collection", "action" => "quote", "quote" => "random" ];
$collectionInput = new InputDataContext( new RequestData( ), new SessionData( ) );
$collectionWorker = new CollectionWorker( $collectionDatabase );
$collectionWorker->prime( $workerContext, $collectionInput );
$quoteResult = $collectionWorker->process( );
assert( $quoteResult->getValue( ) === [ "text" => "A normalized quote." ] );
assert( $normalizer->normalize( $quoteResult )[ "message" ] === "A normalized quote." );

echo "Smoke checks passed\n";
