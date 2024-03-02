<?php include(__DIR__ . '/head.html.php') ?>
<h1>Welcome</h1>
<h2>Overview of ActivityPub (mastodon) users on nordmann.name</h2>
<ul class="account__list">
<?php foreach ($users as $user): ?>
  <li class="account__item">
    <a href="<?=isset($user->alias) ? "https://{$user->alias->domain}/@{$user->alias->user}" : "/@{$user->user}"?>">
      <div class="image-wrapper">
        <img src="<?=e($user->avatar)?>" width="64" height="64" alt="<?=e($user->name)?>" />
      </div>
      <h3><?=e($user->name)?></h3>
      <h4>@<?=e($user->user)?>@<?=e($server)?></h4>
  <?php if (isset($user->alias)): ?>
      <h5>→&nbsp;@<?=e($user->alias->user)?>@<?=e($user->alias->domain)?></h5>
  <?php else: ?>
      <h5><?=e($user->followers->count ?? 0);?> followers | <?=e($user->outbox->count ?? 0);?> posts</h5>
  <?php endif; ?>
      <p class="account__text"><?=e($user->summary)?></p>
    </a>
  </li>
<?php endforeach; ?>
</ul>
<?php include(__DIR__ . '/foot.html.php') ?>
