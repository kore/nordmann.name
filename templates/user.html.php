<?php include(__DIR__ . '/head.html.php') ?>
<h1><?=e($user->name)?></h1>
<h2>@<?=e($user->user)?>@<?=e($server)?></h2>
<div class="container">
  <div class="row">
    <strong class="column center"><?=e($user->followers->count ?? 0);?> follower(s)</strong>
  </div>
</div>
<p><b>@TODO:</b> Posts…</p>
<?php include(__DIR__ . '/foot.html.php') ?>
