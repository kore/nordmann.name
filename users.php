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
    ],
    "kore" => (object) [
        'user' => "kore",
        'name' => "Kore Nordmann",
        'summary' => "Currently I focus on scaling products as a former founder & CTO inside of commercetools. My passion is empathically building sustainable software.",
        'alias' => (object) [
            'user' => 'kore',
            'domain' => 'chaos.social',
        ],
    ],
    "kores-blog" => (object) [
        'user' => "kores-blog",
        'name' => "Kore Nordmann",
        'summary' => "Blog posts by @kore@nordmann.name",
    ],
    "kores-photos" => (object) [
        'user' => "kores-photos",
        'name' => "Kore Nordmann",
        'summary' => "Photos by @kore@nordmann.name",
    ],
];
ksort($users);

return $users;

