<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="nordmann.name overview and ActivityPub server index" />
  <meta name="theme-color" content="#748e63">
  <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16.png">
  <title>Nordmann</title>
  <link rel="stylesheet" href="/styles.css">
</head>
<body>
  <header>
    <div class="container">
      <img src="/images/header.svg" width="100%" alt="nortmann.name" />
    </div>
  </header>
  <main>
    <div class="container">
      <h1>Welcome</h1>
      <h2>Overview of ActivityPub / mastodon users on the domain nordmann.name</h2>
      <ul class="account__list">
      <?php foreach ($users as $user): ?>
        <li class="account__item">
          <div class="image-wrapper">
            <img src="<?=e($user->avatar)?>" width="64" height="64" alt="<?=e($user->name)?>" />
          </div>
          <h3><?=e($user->name)?></h3>
          <h4>@<?=e($user->user)?>@<?=e($server)?></h4>
		  <?php if (isset($user->alias)): ?>
	        <h5>alias for @<?=e($user->alias->user)?>@<?=e($user->alias->domain)?></h5>
		  <?php else: ?>
	        <h5>23 followers | 10 posts</h5>
		  <?php endif; ?>
          <p class="account__text"><?=e($user->summary)?></p>
        </li>
      <?php endforeach; ?>
      </ul>
    </div>
  </main>
  <footer>
    <p class="footer__copyright">
      &copy; Kore Nordmann, 2006 to <?=date("Y")?> – <a href="https://kore-nordmann.de/imprint/">Imprint / Impressum</a>
    </p>
  </footer>
</body>
</html>
