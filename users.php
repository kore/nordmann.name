<?php

$users = [
    "arne" => (object) [
        'user' => "arne",
        'name' => "Arne Nordmann",
        'summary' => "Robotics enthusiast. Curious about the future, worried about the climate.",
        'alias' => (object) [
            'user' => 'arnenordmann',
            'domain' => 'det.social',
        ],
        'secret' => null,
    ],
    "kore" => (object) [
        'user' => "kore",
        'name' => "Kore Nordmann",
        'summary' => "Currently I focus on scaling products as a former founder & CTO inside of commercetools. My passion is empathically building sustainable software.",
        'alias' => (object) [
            'user' => 'kore',
            'domain' => 'chaos.social',
        ],
        'secret' => null,
    ],
    "kores-blog" => (object) [
        'user' => "kores-blog",
        'name' => "Kore Nordmann",
        'summary' => "Blog posts by @kore@nordmann.name",
        'secret' => 'EBy43xHREueW48',
        'isBot' => true,
        'rssSource' => 'https://kore-nordmann.de/blog.rss',
    ],
    "kores-photos" => (object) [
        'user' => "kores-photos",
        'name' => "Kore Nordmann",
        'summary' => "Photos by @kore@nordmann.name",
        'secret' => 'ZVC5utS3ubWXAu',
        'isBot' => true,
        'rssSource' => 'https://kore-nordmann.de/photos.rss',
    ],
];
ksort($users);

// Ammend user information
foreach ($users as $user) {
    if (file_exists(__DIR__ . '/public/images/' . $user->user . '.png')) {
        $user->avatar = '/images/' . $user->user . '.png';
    } else {
        $user->avatar = '/images/dummy.svg';
    }

    $user->id = "https://{$server}/users/{$user->user}";
    $user->type = $user->isBot ?? false ? 'Service' : 'Person';

    $user->dataDirectory = __DIR__ . '/data/' . $user->user;
    if (!file_exists($user->dataDirectory . '/posts')) {
        mkdir($user->dataDirectory . '/posts', 0750, true);
    }
    $user->followers = file_exists($user->dataDirectory . '/followers.json') ? json_decode(file_get_contents($user->dataDirectory . '/followers.json')) : [];
    $user->outbox = array_map('json_decode', array_map('file_get_contents', glob($user->dataDirectory . '/posts/*.json')));
}

return $users;

