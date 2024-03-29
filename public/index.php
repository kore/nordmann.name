<?php

// Forked from Server in a Single PHP File
// https://gitlab.com/edent/activitypub-single-php-file
//
// Licensed under the GNU Affero General Public License v3.0 or later
//
// Modifications by Kore Nordmann to make it multi-user ready and use it to
// feed text and image RSS feeds into Mastodon

$server = $_SERVER["SERVER_NAME"];
$users = include(__DIR__ . '/../users.php');

if (!file_exists(__DIR__ . "/../id_rsa") || !file_exists(__DIR__ . "/../id_rsa.pub")) {
    header("HTTP/1.0 500 Internal Server Error");
    echo "<h1>Missing files</h1><p>The key files id_rsa and/or id_rsa.pub are missing in the directory " . dirname(__DIR__) . ", but are required. You can generate them at <a href=\"https://cryptotools.net/rsagen\">https://cryptotools.net/rsagen</a>.</p>";
    die();
}

$keyPrivate = file_get_contents(__DIR__ . "/../id_rsa");
$keyPublic = file_get_contents(__DIR__ . "/../id_rsa.pub");

// Just for PHPs internal webserver: Ignore static files
if (preg_match('/\.(?:svg|png|jpg|jpeg|gif|css)$/', $_SERVER["REQUEST_URI"])) {
    return false;
}

// log every request by default
$body = json_decode(file_get_contents("php://input"), true);

// Get the type of request - used in the log filename
if (isset($body["type"])) {
    // Sanitise type to only include letter
    $type = preg_replace("/[^a-zA-Z]/", "", $body["type"]);
} else {
    $type = $_SERVER['REQUEST_METHOD'];
}

// Create a timestamp for the filename
// This format has milliseconds, so should avoid logs being overwritten.
// If you have > 1000 requests per second, please use a different server.
$timestamp = (new DateTime())->format('Y-m-d-H-i-s');

// Filename for the log
$filename = "{$timestamp}-{$type}.txt";

// Save headers and request data to the timestamped file in the logs directory
$logDir = __DIR__ . "/../logs";
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$headers = ['Date: ' . $timestamp];
foreach ($_SERVER as $key => $value) {
    if (substr($key, 0, 5) !== 'HTTP_') continue;
    $headers[] = ucwords(str_replace('_', '-', strtolower(substr($key, 5))), '-') . ': ' . $value;
}

file_put_contents(
    "{$logDir}/{$filename}",
    sprintf(
        "%s %s %s\n%s\n\n%s",
        $_SERVER['SERVER_PROTOCOL'],
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI'],
        implode("\n", $headers),
        file_get_contents("php://input")
    )
);

$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
switch (true) {
    case "/" === $requestPath:
        home();
    case "/.well-known/webfinger" === $requestPath:
        webfinger();
    case preg_match('(/users/(?P<user>[a-z0-9][a-z0-9-._]*)/inbox)', $requestPath, $match):
    case "/inbox" === $requestPath:
        inbox();
    case "/send" === $requestPath:
        send();
    case preg_match('(/users/(?P<user>[a-z0-9][a-z0-9-._]*)/following)', $requestPath, $match):
        if (isset($users[$match['user']])) {
            following($users[$match['user']]);
        } // Fallback to default -> 404
    case preg_match('(/users/(?P<user>[a-z0-9][a-z0-9-._]*)/followers)', $requestPath, $match):
        if (isset($users[$match['user']])) {
            followers($users[$match['user']]);
        } // Fallback to default -> 404
    case preg_match('(/users/(?P<user>[a-z0-9][a-z0-9-._]*)/outbox)', $requestPath, $match):
        if (isset($users[$match['user']])) {
            outbox($users[$match['user']]);
        } // Fallback to default -> 404
    case preg_match('(/users/(?P<user>[a-z0-9][a-z0-9-._]*))', $requestPath, $match):
    case preg_match('(/@(?P<user>[a-z0-9][a-z0-9-._]*))', $requestPath, $match):
        if (isset($users[$match['user']])) {
            userpage($users[$match['user']]);
        } // Fallback to default -> 404
    default:
        header("HTTP/1.0 404 Not Found");
        echo "<h1>404 Not Found</h1>";
        echo "<h2>" . e($requestPath) . " is not handled by this server.</h2>";
        die();
}

// The [WebFinger Protocol](https://docs.joinmastodon.org/spec/webfinger/) is
// used to identify accounts. It is requested with
// `example.com/.well-known/webfinger?resource=acct:username@example.com`
function webfinger()
{
    global $users, $server;

    if (empty($_GET['resource']) ||
        !preg_match('(^acct:(?P<user>[a-z0-9][a-z0-9-._]*)@(?P<domain>.*)$)', $_GET['resource'], $match) ||
        !isset($users[$match['user']]) ||
        $server !== $match['domain']) {
        header("HTTP/1.0 404 Not Found");
        echo "<h1>404 Not Found</h1>";
        echo "<h2>User not found on this server.</h2>";
        die();
    }

    $user = $users[$match['user']];

    if (!empty($user->alias)) {
        // It's easier to just redirect requests for alias'd accounts then
        // generating the correct response ourselves…
        header("Location: https://{$user->alias->domain}/.well-known/webfinger?resource=acct:{$user->alias->user}@{$user->alias->domain}");
        die();
    }

    $webfinger = array_filter([
        "links" => [
            [
                "href" => "https://{$server}/@{$user->name}",
                "rel" => "http://webfinger.net/rel/profile-page",
                "type" => "text/html",
            ],
            [
                "href" => $user->id,
                "rel" => "self",
                "type" => "application/activity+json",
            ],
        ],
        "subject" => "acct:{$user->user}@{$server}",
    ]);

    header("Content-Type: application/json");
    echo json_encode($webfinger);
    die();
}

function userpage(\StdClass $user)
{
    global $server, $keyPublic;

    if (!empty($user->alias)) {
        if (strpos($_SERVER['REQUEST_URI'], '@') !== false) {
            $target = 'https://' . $user->alias->domain . '/@' . $user->alias->user;
        } else {
            $target = 'https://' . $user->alias->domain . '/users/' . $user->alias->user;
        }

        header("Location: " . $target);
        die();
    }

    // For non browser calls fall back to JSON user repsose:
    if (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false) return username($user);

    include(__DIR__ . "/../templates/user.html.php");
    die();
}

function username(\StdClass $user)
{
    global $server, $keyPublic;

    $user = [
        "@context" => [
            "https://www.w3.org/ns/activitystreams",
            "https://w3id.org/security/v1",
        ],
        "id" => $user->id,
        "type" => $user->type,
        "following" => $user->id . "/following",
        "followers" => $user->id . "/followers",
        "inbox" => $user->id . "/inbox",
        "outbox" => $user->id . "/outbox",
        "preferredUsername" => $user->user,
        "name" => $user->name,
        "summary" => $user->summary,
        "url" => $user->id,
        "manuallyApprovesFollowers" => false,
        "discoverable" => true,
        "published" => "2024-02-12",
        "indexable" => true,
        "memorial" => false,
        "icon" => [
            "type" => "Image",
            "mediaType" => "image/png",
            "url" => "https://{$server}{$user->avatar}",
        ],
        "publicKey" => [
            "id" => "{$user->id}#main-key",
            "owner" => $user->id,
            "publicKeyPem" => $keyPublic,
        ],
    ];
    header("Content-Type: application/activity+json");
    echo json_encode($user);
    die();
}

// Follower / Following:
// These JSON documents show how many users are following / followers-of this account.
// The information here is self-attested. So you can lie and use any number you want.
function following(\StdClass $user)
{
    global $server;

    $following = [
        "@context" => "https://www.w3.org/ns/activitystreams",
        "id" => $user->id . "/following",
        "type" => "Collection",
        "totalItems" => 0,
    ];
    header("Content-Type: application/activity+json");
    echo json_encode($following);
    die();
}

function followers(\StdClass $user)
{
    global $server;
    $followers = [
        "@context" => "https://www.w3.org/ns/activitystreams",
        "id" => $user->id . "/followers",
        "type" => "Collection",
        "totalItems" => $user->followers->count,
    ];
    header("Content-Type: application/activity+json");
    echo json_encode($followers);
    die();
}

// Inbox:
// The `/inbox` is the main server. It receives all requests. This server only
// responds to "Follow" requests. A remote server sends a follow request which
// is a JSON file saying who they are. This code does not cryptographically
// validate the headers of the received message. The name of the remote user's
// server is saved to a file so that future messages can be delivered to it.
// An accept request is cryptographically signed and POST'd back to the remote
// server.
function inbox()
{
    global $body, $server, $users, $keyPrivate;

    // Get the message and type
    $inboxMessage = $body;
    $inboxType = $inboxMessage["type"];

    // This inbox only responds to follow requests
    // @TODO: Implement unfollow & undo
    if ("Follow" != $inboxType) {
        die();
    }

    // Get the parameters
    $inboxId = $inboxMessage["id"];
    $inboxActor = $inboxMessage["actor"];
    $followTarget = basename($inboxMessage["object"]);
    $inboxHost = parse_url($inboxActor, PHP_URL_HOST);

    if (!isset($users[$followTarget])) {
        header("HTTP/1.0 404 Not Found");
        echo "<h1>404 Not Found</h1>";
        echo "<h2>" . e($followTarget) . " could not be found.</h2>";
        die();
    }
    $user = $users[$followTarget];

    // Does this account have any followers?
    $followerFile = $user->dataDirectory . '/followers.json';

    if (file_exists($followerFile)) {
        $followers = json_decode(file_get_contents($followerFile), true);
    } else {
        $followers = [];
    }

    $followers[$inboxHost]["users"][] = $inboxActor;
    $count = 0;
    foreach ($followers as $host => $data) {
        if ($host === 'count') continue;

        $followers[$host]["users"] = array_unique($data["users"] ?? []);
        $count += count($followers[$host]["users"]);
    }
    $followers["count"] = $count;

    // Save the new followers file
    file_put_contents($followerFile, json_encode($followers, JSON_PRETTY_PRINT));

    // Response Message ID
    // This isn't used for anything important so could just be a random number
    $guid = uuid();

    // Create the Accept message
    $message = [
        "@context" => "https://www.w3.org/ns/activitystreams",
        "id" => "https://{$server}/{$guid}",
        "type" => "Accept",
        "actor" => $user->id,
        "object" => [
            "@context" => "https://www.w3.org/ns/activitystreams",
            "id" => $inboxId,
            "type" => $inboxType,
            "actor" => $inboxActor,
            "object" => $user->id,
        ],
    ];

    // The Accept is sent to the server of the user who requested the follow
    // TODO: The path doesn't *always* end with /inbox
    $host = $inboxHost;
    $path = parse_url($inboxActor, PHP_URL_PATH) . "/inbox";

    // Get the signed headers
    $headers = generateSignedHeaders($user, $message, $host, $path);

    // Specify the URL of the remote server's inbox
    // TODO: The path doesn't *always* end with /inbox
    $remoteServerUrl = $inboxActor . "/inbox";

    // POST the message and header to the requester's inbox
    $ch = curl_init($remoteServerUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_exec($ch);

    // Check for errors
    if (curl_errno($ch)) {
        header("HTTP/1.0 500 Internal Server Error");
        file_put_contents("error.txt", curl_error($ch));
        echo json_encode(['ok' => false, 'error' => curl_error($ch)]);
    }
    curl_close($ch);

    echo json_encode(['ok' => true]);
    die();
}

// Unique ID:
// Every message sent should have a unique ID.
// This can be anything you like. Some servers use a random number.
// I prefer a date-sortable string.
function uuid()
{
    return sprintf(
        "%08x-%04x-%04x-%04x-%012x",
        time(),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffffffffffff)
    );
}

// Headers:
// Every message that your server sends needs to be cryptographically signed with your Private Key.
// This is a complicated process. Please read https://blog.joinmastodon.org/2018/07/how-to-make-friends-and-verify-requests/ for more information.
function generateSignedHeaders($user, $message, $host, $path)
{
    global $server, $keyPrivate;

    // Encode the message object to JSON
    $messageJson = json_encode($message);

    // Location of the Public Key
    $keyId = $user->id . "#main-key";

    // Generate signing variables
    $hash = hash("sha256", $messageJson, true);
    $digest = base64_encode($hash);
    $date = date("D, d M Y H:i:s \G\M\T");

    // Get the Private Key
    $signer = openssl_get_privatekey($keyPrivate);

    // Sign the path, host, date, and digest
    $stringToSign = "(request-target): post $path\nhost: $host\ndate: $date\ndigest: SHA-256=$digest";

    // The signing function returns the variable $signature
    // https://www.php.net/manual/en/function.openssl-sign.php
    openssl_sign($stringToSign, $signature, $signer, OPENSSL_ALGO_SHA256);

    // Full signature header
    $signatureB64 = base64_encode($signature);
    $signatureHeader = 'keyId="' .  $keyId .  '",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="' .  $signatureB64 .  '"';

    return [
        "Host: {$host}",
        "Date: {$date}",
        "Digest: SHA-256={$digest}",
        "Signature: {$signatureHeader}",
        "Content-Type: application/activity+json",
        "Accept: application/activity+json",
    ];
}

function e(string $string): string
{
    return htmlspecialchars($string);
}

// User Interface for Homepage:
// This creates a basic HTML page. This content appears when someone visits the root of your site.
function home()
{
    global $users, $server;
    include(__DIR__ . "/../templates/overview.html.php");
    die();
}

// The Outbox contains a date-ordered list (newest first) of all the user's posts
// This is optional.
function outbox()
{
    global $server, $username;

    // Get all posts
    $posts = array_reverse(glob("posts/" . "*.json"));
    // Number of posts
    $totalItems = count($posts);
    // Create an ordered list
    $orderedItems = [];
    foreach ($posts as $post) {
        $orderedItems[] = [
            "type" => "Create",
            "actor" => "https://{$server}/{$username}",
            "object" => "https://{$server}/{$post}",
        ];
    }

    // Create User's outbox
    $outbox = [
        "@context" => "https://www.w3.org/ns/activitystreams",
        "id" => "https://{$server}/outbox",
        "type" => "OrderedCollection",
        "totalItems" => $totalItems,
        "summary" => "All the user's posts",
        "orderedItems" => $orderedItems,
    ];

    // Render the page
    header("Content-Type: application/activity+json");
    echo json_encode($outbox);
    die();
}

