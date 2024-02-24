<?php

// Forked from Server in a Single PHP File
// https://gitlab.com/edent/activitypub-single-php-file
//
// Licensed under the GNU Affero General Public License v3.0 or later
//
// Modifications by Kore Nordmann to make it multi-user ready and use it to
// feed text and image RSS feeds into Mastodon

$users = include(__DIR__ . '/../users.php');
$keyPrivate = file_get_contents(__DIR__ . "/../id_rsa");
$keyPublic = file_get_contents(__DIR__ . "/../id_rsa.pub");

// Internal data
$server = $_SERVER["SERVER_NAME"];

// Ammend user information
foreach ($users as $user) {
    if (file_exists(__DIR__ . '/images/' . $user->user . '.png')) {
        $user->avatar = '/images/' . $user->user . '.png';
    } else {
        $user->avatar = '/images/dummy.svg';
    }

    $user->id = "https://{$server}/users/{$user->user}";
}

// Just for PHPs internal webserver: Ignore static files
if (preg_match('/\.(?:svg|png|jpg|jpeg|gif|css)$/', $_SERVER["REQUEST_URI"])) {
    return false;
}

// log every request by default
$body = json_decode(file_get_contents("php://input"), true);

// Get the type of request - used in the log filename
if (isset($body["type"])) {
    // Sanitise type to only include letter
    $type = " " . preg_replace("/[^a-zA-Z]/", "", $body["type"]);
} else {
    $type = $_SERVER['REQUEST_METHOD'];
}

// Create a timestamp for the filename
// This format has milliseconds, so should avoid logs being overwritten.
// If you have > 1000 requests per second, please use a different server.
$timestamp = (new DateTime())->format(DATE_RFC3339_EXTENDED);

// Filename for the log
$filename = "{$timestamp}-{$type}.txt";

// Save headers and request data to the timestamped file in the logs directory
$logDir = __DIR__ . "/../logs";
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$headers = [];
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
    case "/following" === $requestPath:
        following();
    case "/followers" === $requestPath:
        followers();
    case "/inbox" === $requestPath:
        inbox();
    case "/send" === $requestPath:
        send();
    case "/outbox" === $requestPath:
        outbox();
    case "/viewSource" === $requestPath:
        echo '<html><body><pre>' . e(file_get_contents(__FILE__)) . '</pre></body></html>';
        die();
    case preg_match('(/users/(?P<user>[a-z0-9][a-z0-9-._]*))', $requestPath, $match):
        if (isset($users[$match['user']])) {
            username($users[$match['user']]);
        } // Fallback to default -> 404
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

    $webfinger = array_filter([
        "subject" => "acct:{$user->user}@{$server}",
        "aliases" => isset($user->alias) ? [
            "https://{$user->alias->domain}/@{$user->alias->user}",
            "https://{$user->alias->domain}/users/{$user->alias->user}",
        ] : null,
        "links" => [
            [
                "rel" => "self",
                "type" => "application/activity+json",
                "href" => $user->id,
            ],
        ],
    ]);
    header("Content-Type: application/json");
    echo json_encode($webfinger);
    die();
}

function username(\StdClass $user)
{
    global $username, $realName, $summary, $server, $keyPublic;

    $user = [
        "@context" => [
            "https://www.w3.org/ns/activitystreams",
            "https://w3id.org/security/v1",
        ],
        "id" => $user->id,
        "type" => "Person",
        "following" => "https://{$server}/following",
        "followers" => "https://{$server}/followers",
        "inbox" => "https://{$server}/inbox",
        "outbox" => "https://{$server}/outbox",
        "preferredUsername" => $user->user,
        "name" => $user->name,
        "summary" => $user->summary,
        "url" => $user->id,
        "manuallyApprovesFollowers" => true,
        "discoverable" => true,
        "published" => "2024-02-12T11:51:00Z",
        "icon" => [
            "type" => "Image",
            "mediaType" => "image/png",
            "url" => "https://{$server}{$user->avatar}",
        ],
        "publicKey" => [
            "id" => "https://{$server}/{$user->user}#main-key",
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
function following()
{
    global $server;

    $following = [
        "@context" => "https://www.w3.org/ns/activitystreams",
        "id" => "https://{$server}/following",
        "type" => "Collection",
        "totalItems" => 0,
        "items" => [],
    ];
    header("Content-Type: application/activity+json");
    echo json_encode($following);
    die();
}

function followers()
{
    global $server;
    $followers = [
        "@context" => "https://www.w3.org/ns/activitystreams",
        "id" => "https://{$server}/followers",
        "type" => "Collection",
        "totalItems" => 0,
        "items" => [],
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
    global $body, $server, $username, $keyPrivate;

    // Get the message and type
    $inboxMessage = $body;
    $inboxType = $inboxMessage["type"];

    // This inbox only responds to follow requests
    if ("Follow" != $inboxType) {
        die();
    }

    // Get the parameters
    $inboxId = $inboxMessage["id"];
    $inboxActor = $inboxMessage["actor"];
    $inboxHost = parse_url($inboxActor, PHP_URL_HOST);

    // Does this account have any followers?
    if (file_exists("followers.json")) {
        $followersFile = file_get_contents("followers.json");
        $followersJson = json_decode($followersFile, true);
    } else {
        $followersJson = [];
    }

    // Add user to list. Don't care about duplicate users, server is what's important
    $followersJson[$inboxHost]["users"][] = $inboxActor;

    // Save the new followers file
    file_put_contents(
        "followers.json",
        print_r(json_encode($followersJson), true)
    );

    // Response Message ID
    // This isn't used for anything important so could just be a random number
    $guid = uuid();

    // Create the Accept message
    $message = [
        "@context" => "https://www.w3.org/ns/activitystreams",
        "id" => "https://{$server}/{$guid}",
        "type" => "Accept",
        "actor" => "https://{$server}/{$username}",
        "object" => [
            "@context" => "https://www.w3.org/ns/activitystreams",
            "id" => $inboxId,
            "type" => $inboxType,
            "actor" => $inboxActor,
            "object" => "https://{$server}/{$username}",
        ],
    ];

    // The Accept is sent to the server of the user who requested the follow
    // TODO: The path doesn't *always* end with /inbox
    $host = $inboxHost;
    $path = parse_url($inboxActor, PHP_URL_PATH) . "/inbox";

    // Get the signed headers
    $headers = generate_signed_headers($message, $host, $path);

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
        file_put_contents("error.txt", curl_error($ch));
    }
    curl_close($ch);
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
function generate_signed_headers($message, $host, $path)
{
    global $server, $username, $keyPrivate;

    // Encode the message object to JSON
    $messageJson = json_encode($message);

    // Location of the Public Key
    $keyId = "https://{$server}/{$username}#main-key";

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
    // Encode the signature
    $signatureB64 = base64_encode($signature);

    // Full signature header
    $signatureHeader =
        'keyId="' .
        $keyId .
        '",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="' .
        $signatureB64 .
        '"';

    // Header for POST reply
    $headers = [
        "Host: {$host}",
        "Date: {$date}",
        "Digest: SHA-256={$digest}",
        "Signature: {$signatureHeader}",
        "Content-Type: application/activity+json",
        "Accept: application/activity+json",
    ];

    return $headers;
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

// Send Endpoint:
// @TODO: Convert into JSON API
function send()
{
    global $password, $server, $username, $keyPrivate;

    // Does the posted password match the stored password?
    if ($password != $_POST["password"]) {
        die();
    }

    // Get the posted content
    $content = $_POST["content"];

    // Process the content into HTML to get hashtags etc
    list("HTML" => $content, "TagArray" => $tags) = process_content($content);

    // Is there an image attached?
    if (
        isset($_FILES["image"]["tmp_name"]) &&
        "" != $_FILES["image"]["tmp_name"]
    ) {
        // Get information about the image
        $image = $_FILES["image"]["tmp_name"];
        $imageInfo = getimagesize($image);
        $imageExt = image_type_to_extension($imageInfo[2]);
        $imageMime = $imageInfo["mime"];

        // Files are stored according to their hash
        // A hash of "abc123" is stored in "/images/abc123.jpg"
        $sha1 = sha1_file($image);
        $imagePath = "images";
        $imageFullPath = "{$imagePath}/{$sha1}.{$imageExt}";

        // Move media to the correct location
        // Create a directory if it doesn't exist
        if (!is_dir($imagePath)) {
            mkdir($imagePath);
        }
        move_uploaded_file($image, $imageFullPath);

        // Get the alt text
        if (isset($_POST["alt"])) {
            $alt = $_POST["alt"];
        } else {
            $alt = "";
        }

        // Construct the attachment value for the post
        $attachment = [
            "type" => "Image",
            "mediaType" => "{$imageMime}",
            "url" => "https://{$server}/{$imageFullPath}",
            "name" => $alt,
        ];
    } else {
        $attachment = [];
    }

    // Current time - ISO8601
    $timestamp = date("c");

    // Outgoing Message ID
    $guid = uuid();

    // Construct the Note
    // contentMap is used to prevent unnecessary "translate this post" pop ups
    // hardcoded to English
    $note = [
        "@context" => ["https://www.w3.org/ns/activitystreams"],
        "id" => "https://{$server}/posts/{$guid}.json",
        "type" => "Note",
        "published" => $timestamp,
        "attributedTo" => "https://{$server}/{$username}",
        "content" => $content,
        "contentMap" => ["en" => $content],
        "to" => ["https://www.w3.org/ns/activitystreams#Public"],
        "tag" => $tags,
        "attachment" => $attachment,
    ];

    // Construct the Message
    // The audience is public and it is sent to all followers
    $message = [
        "@context" => "https://www.w3.org/ns/activitystreams",
        "id" => "https://{$server}/posts/{$guid}.json",
        "type" => "Create",
        "actor" => "https://{$server}/{$username}",
        "to" => ["https://www.w3.org/ns/activitystreams#Public"],
        "cc" => ["https://{$server}/followers"],
        "object" => $note,
    ];

    // Create the context for the permalink
    $note = ["@context" => "https://www.w3.org/ns/activitystreams", ...$note];

    // Save the permalink
    $noteJson = json_encode($note);
    // Check for posts/ directory and create it
    if (!is_dir("posts")) {
        mkdir("posts");
    }
    file_put_contents("posts/{$guid}.json", print_r($noteJson, true));

    // Read existing users and get their hosts
    $followersFile = file_get_contents("followers.json");
    $followersJson = json_decode($followersFile, true);
    $hosts = array_keys($followersJson);

    // Prepare to use the multiple cURL handle
    $mh = curl_multi_init();

    // Loop through all the severs of the followers
    // Each server needs its own cURL handle
    // Each POST to an inbox needs to be signed separately
    foreach ($hosts as $host) {
        // TODO: Not every host uses /inbox
        $path = "/inbox";

        // Get the signed headers
        $headers = generate_signed_headers($message, $host, $path);

        // Specify the URL of the remote server
        $remoteServerUrl = "https://{$host}{$path}";

        // POST the message and header to the requester's inbox
        $ch = curl_init($remoteServerUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Add the handle to the multi-handle
        curl_multi_add_handle($mh, $ch);
    }

    // Execute the multi-handle
    do {
        $status = curl_multi_exec($mh, $active);
        if ($active) {
            curl_multi_select($mh);
        }
    } while ($active && $status == CURLM_OK);

    // Close the multi-handle
    curl_multi_close($mh);

    // Render the JSON so the user can see the POST has worked
    header("Location: https://{$server}/posts/{$guid}.json");
    die();
}

// Content can be plain text. But to add clickable links and hashtags, it needs to be turned into HTML.
// Tags are also included separately in the note
function process_content($content)
{
    global $server;

    // Convert any URls into hyperlinks
    $linkPattern = "/\bhttps?:\/\/\S+/iu";
    $replacement = function ($match) {
        $url = htmlspecialchars($match[0], ENT_QUOTES, "UTF-8");
        return "<a href=\"$url\">$url</a>";
    };
    $content = preg_replace_callback($linkPattern, $replacement, $content);

    // Get any hashtags
    $hashtags = [];
    $hashtagPattern = "/(?:^|\s)\#(\w+)/"; // Beginning of string, or whitespace, followed by #
    preg_match_all($hashtagPattern, $content, $hashtagMatches);
    foreach ($hashtagMatches[1] as $match) {
        $hashtags[] = $match;
    }

    // Construct the tag value for the note object
    $tags = [];
    foreach ($hashtags as $hashtag) {
        $tags[] = [
            "type" => "Hashtag",
            "name" => "#{$hashtag}",
        ];
    }

    // Add HTML links for hashtags into the text
    $content = preg_replace(
        $hashtagPattern,
        " <a href='https://{$server}/tag/$1'>#$1</a>",
        $content
    );

    // Detect user mentions
    $usernames = [];
    $usernamesPattern = "/@(\S+)@(\S+)/"; // This is a *very* sloppy regex
    preg_match_all($usernamesPattern, $content, $usernamesMatches);
    foreach ($usernamesMatches[0] as $match) {
        $usernames[] = $match;
    }

    // Construct the mentions value for the note object
    // This goes in the generic "tag" property
    // TODO: Add this to the CC field
    foreach ($usernames as $username) {
        list($null, $user, $domain) = explode("@", $username);
        $tags[] = [
            "type" => "Mention",
            "href" => "https://{$domain}/@{$user}",
            "name" => "{$username}",
        ];

        // Add HTML links to usernames
        $usernameLink = "<a href=\"https://{$domain}/@{$user}\">$username</a>";
        $content = str_replace($username, $usernameLink, $content);
    }

    // Construct the content
    $content = "<p>" . nl2br($content) . "</p>";

    return [
        "HTML" => $content,
        "TagArray" => $tags,
    ];
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

