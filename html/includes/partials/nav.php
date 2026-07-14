<?php

  $userContext = $webController->getUserContext();
  $twitchAuthStart = $webController->getTwitchAppContext()->getAuthStart();
  
  $isAdminNav  = $userContext->isAdmin();
  $displayName = $userContext->getDisplayName();

  $requestUri  = $_SERVER['REQUEST_URI'] ?? '/';
  $current     = getCurrentPageName();

?>
<div id="navAuth">
  <?php if ( $userContext->userLoggedIn() ): ?>
    <span class="navAuthLabel">Logged in as</span>
    <span class="navAuthUser"><?php echo $displayName; ?></span>
    <a class="navAuthLink" href="/admin/adminLogout.php">Logout</a>
  <?php else: ?>
    <a class="navAuthLink" href="<?php echo $twitchAuthStart . urlencode( $requestUri ); ?>">Login with Twitch</a>
  <?php endif; ?>
</div>
<div id="navbar"></div>
<div id="navlinks">
    <span class="navlink <?php echo $current === 'about' ? 'active' : ''; ?>"><a href="/about.php">About</a></span>
    <span class="navlink <?php echo $current === 'lore' ? 'active' : ''; ?>"><a href="/lore.php">Lore</a></span>
    <span class="navlink <?php echo $current === 'collections' ? 'active' : ''; ?>"><a href="/collections.php">Collections</a></span>
    <span class="navlink <?php echo $current === 'commands' ? 'active' : ''; ?>"><a href="/commands.php">Commands</a></span>
    <span class="navlink <?php echo $current === 'features' ? 'active' : ''; ?>"><a href="/features.php">Features</a></span>
    <?php if ( $isAdminNav ): ?>
        <span class="navlink <?php echo $current === 'manageXogoria' ? 'active' : ''; ?>"><a href="/admin/manageXogoria.php">Admin</a></span>
    <?php endif; ?>
</div>
<?php require 'liveBanner.php'; ?>
