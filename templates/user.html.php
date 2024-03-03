<?php include(__DIR__ . '/head.html.php') ?>
<h1><?=e($user->name)?></h1>
<h2>@<?=e($user->user)?>@<?=e($server)?></h2>
<div class="container">
  <div class="row">
    <strong class="column center"><?=e($user->followers->count ?? 0);?> follower(s)</strong>
    <strong class="column center"><?=e(count($user->outbox));?> toot(s)</strong>
  </div>
</div>
<ul class="toots">
<?php foreach ($user->outbox as $post): ?>
    <li class="toot row">
        <div class="column">
            <img src="<?=e($user->avatar)?>" width="64" height="64" alt="<?=e($user->name)?>" />
        </div>
        <div class="column">
            <div class="toot__meta">
                <span class="toot__author"><?=e($user->name)?></span>
                <span class="toot__user">@<?=e($user->user)?>@<?=e($server)?></span>
                <span class="toot__date"><?=e((new \DateTime($post->published))->format("j. M 'y"))?></span>
            </div>
            <div class="toot__content">
                <?=$post->content?>
            </div>
            <?php if (!empty($post->attachment)): ?>
            <div class="toot__media">
                <img
                    src="<?=e($post->attachment->preview_url ?: $post->attachment->url)?>"
                    alt="<?=e($post->attachment->description)?>"
                />
            </div>
            <?php endif; ?>
        </div>
    </li>
<?php endforeach; ?>
</ul>
<?php include(__DIR__ . '/foot.html.php') ?>
