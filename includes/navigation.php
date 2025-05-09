<?php
// Determine current page to highlight active link
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="menu">
  <!-- Home Link -->
  <a href="<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? '../index.php' : './index.php' ?>" class="link <?= $current_page === 'index.php' ? 'active' : '' ?>">
    <span class="link-icon">
      <img src="<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './assets/images/home-icon.svg' : './public/assets/images/home-icon.svg' ?>" alt="Home">
    </span>
    <span class="link-title">Home</span>
  </a>

  <!-- Marketplace Dropdown -->
  <div class="profile-container">
    <a href="#" class="link <?= $current_page === 'marketplace.php' ? 'active' : '' ?>" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './assets/images/market.svg' : './public/assets/images/market.svg' ?>" alt="Market">
      </span>
      <span class="link-title">Market</span>
    </a>
    <div class="dropdown-content">
      <button class="value" onclick="window.location.href='<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './marketplace.php?view=explore' : './public/marketplace.php?view=explore' ?>';">Explore</button>
      <button class="value" onclick="window.location.href='<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? '../api/listings/view_listings.php' : './api/listings/view_listings.php' ?>';">View Listings</button>
      <button class="value" onclick="window.location.href='<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? '../api/listings/create_listing.php' : './api/listings/create_listing.php' ?>';">List Item</button>
      <button class="value" onclick="window.location.href='<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './saved_listings.php' : './public/saved_listings.php' ?>';">Saved Items</button>
    </div>
  </div>
  
  <!-- Forum Dropdown -->
  <div class="profile-container">
    <a href="#" class="link <?= $current_page === 'forum.php' ? 'active' : '' ?>" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './assets/images/forum-icon.svg' : './public/assets/images/forum-icon.svg' ?>" alt="Forum">
      </span>
      <span class="link-title">Forum</span>
    </a>
    <div class="dropdown-content">
      <button class="value" onclick="window.location.href='<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './forum.php?view=threads' : './public/forum.php?view=threads' ?>';">View Threads</button>
      <button class="value" onclick="window.location.href='<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './forum.php?view=start_thread' : './public/forum.php?view=start_thread' ?>';">Start Thread</button>
      <button class="value" onclick="window.location.href='<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './forum.php?view=post_question' : './public/forum.php?view=post_question' ?>';">Post Question</button>
    </div>
  </div>

  <!-- Profile Dropdown -->
  <div class="profile-container">
    <a href="#" class="link <?= $current_page === 'profile.php' ? 'active' : '' ?>" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './assets/images/profile-icon.svg' : './public/assets/images/profile-icon.svg' ?>" alt="Profile">
      </span>
      <span class="link-title">Profile</span>
    </a>
    <div id="profileDropdown" class="dropdown-content">
    <?php if (isset($_SESSION['user_id'])): ?>
      <button class="value" onclick="window.location.href='<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './profile.php' : './public/profile.php' ?>';">
        <img src="<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './assets/images/profile-icon.svg' : './public/assets/images/profile-icon.svg' ?>" alt="Profile">Account
      </button>
      <button class="value" onclick="window.location.href='<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? '../api/listings/view_listings.php' : './api/listings/view_listings.php' ?>';">My Listings</button>
      <button class="value" onclick="window.location.href='<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './saved_listings.php' : './public/saved_listings.php' ?>';">Saved Items</button>
      <button class="value" onclick="window.location.href='<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './account.php' : './public/account.php' ?>';">Account Settings</button>
      <button class="value" onclick="window.location.href='<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './logout.php' : './public/logout.php' ?>';">Logout</button>
    <?php else: ?>
      <button class="value" onclick="window.location.href='<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './login.php' : './public/login.php' ?>';">Login</button>
      <button class="value" onclick="window.location.href='<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './register.php' : './public/register.php' ?>';">Register</button>
    <?php endif; ?>
    </div>
  </div>

  <?php if (isset($_SESSION['user_id'])): ?>
    <a href="<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './chat.php' : './public/chat.php' ?>" class="link <?= $current_page === 'chat.php' ? 'active' : '' ?>">
      <span class="link-icon">
        <img src="<?= strpos($_SERVER['PHP_SELF'], 'public') !== false ? './assets/images/chat-icon.svg' : './public/assets/images/chat-icon.svg' ?>" alt="Chat">
      </span>
      <span class="link-title">Chat</span>
    </a>
  <?php endif; ?>
</div>