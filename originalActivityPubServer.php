<?php

	/*
	*	"This code is not a code of honour... no highly esteemed code is commemorated here... nothing valued is here."
	*	"What is here is dangerous and repulsive to us. This message is a warning about danger."
	*	This is a rudimentary, single-file, low complexity, minimum functionality, ActivityPub server.
	*	For educational purposes only.
	*	The Server produces an Actor who can be followed.
	*	The Actor can send messages to followers.
	*	The message can have linkable URls, hashtags, and mentions.
	*	An image and alt text can be attached to the message.
	*	The Server saves logs about requests it receives and sends.
	*	This code is NOT suitable for production use.
	*	SPDX-License-Identifier: AGPL-3.0-or-later
	*	This code is also "licenced" under CRAPL v0 - https://matt.might.net/articles/crapl/
	*	"Any appearance of design in the Program is purely coincidental and should not in any way be mistaken for evidence of thoughtful software construction."
	*	For more information, please re-read.
	*/

	//	Preamble: Set your details here
	//	This is where you set up your account's name and bio. You also need to provide a public/private keypair. The posting page is protected with a password that also needs to be set here.

	//	Set up the Actor's information
	//	Edit these:
	$username = rawurlencode("example");	//	Type the @ username that you want. Do not include an "@". 
	$realName = "E. Xample. Jr.";	//	This is the user's "real" name.
	$summary  = "Some text about the user.";	//	This is the bio of your user.

	//	Generate locally or from https://cryptotools.net/rsagen
	//	Newlines must be replaced with "\n"
	$key_private = "-----BEGIN RSA PRIVATE KEY-----\n...\n-----END RSA PRIVATE KEY-----";
	$key_public  = "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----";

	//	Password for sending messages
	$password = "P4ssW0rd";

	/** No need to edit anything below here. **/

	//	Internal data
	$server   = $_SERVER["SERVER_NAME"];	//	Do not change this!

	//	Logging:
	//	ActivityPub is a "chatty" protocol. This takes all the requests your server receives and saves them in `/logs/` as a datestamped text file.

	// Get all headers and requests sent to this server
	$headers     = print_r( getallheaders(), true );
	$postData    = print_r( $_POST,    true );
	$getData     = print_r( $_GET,     true );
	$filesData   = print_r( $_FILES,   true );
	$body        = json_decode( file_get_contents( "php://input" ), true );
	$bodyData    = print_r( $body,     true );
	$requestData = print_r( $_REQUEST, true );
	$serverData  = print_r( $_SERVER,  true );

	//	Get the type of request - used in the log filename
	if ( isset( $body["type"] ) ) {
		//	Sanitise type to only include letter
		$type = " " . preg_replace( '/[^a-zA-Z]/', '', $body["type"] );
	} else {
		$type = "";
	}

	//	Create a timestamp for the filename
	//	This format has milliseconds, so should avoid logs being overwritten.
	//	If you have > 1000 requests per second, please use a different server.
	$timestamp = ( new DateTime() )->format( DATE_RFC3339_EXTENDED );

	//	Filename for the log
	$filename  = "{$timestamp}{$type}.txt";

	//	Save headers and request data to the timestamped file in the logs directory
	if( ! is_dir( "logs" ) ) { mkdir( "logs"); }

	file_put_contents( "logs/{$filename}", 
		"Headers:     \n$headers    \n\n" .
		"Body Data:   \n$bodyData   \n\n" .
		"POST Data:   \n$postData   \n\n" .
		"GET Data:    \n$getData    \n\n" .
		"Files Data:  \n$filesData  \n\n" .
		"Request Data:\n$requestData\n\n" .
		"Server Data: \n$serverData \n\n"
	);

	//	Routing:
	//	The .htaccess changes /whatever to /?path=whatever
	//	This runs the function of the path requested.
	!empty( $_GET["path"] )  ? $path = $_GET["path"] : home();
	switch ($path) {
		case ".well-known/webfinger":
			webfinger();   //	Mandatory. Static.
		case rawurldecode( $username ):
			username();    //	Mandatory. Static
		case "following":
			following();   //	Mandatory. Static
		case "followers":
			followers();   //	Mandatory. Could be dynamic
		case "inbox":
			inbox();       //	Mandatory. Only accepts follow requests.
		case "write":
			write();       //	User interface for writing posts
		case "send":      //	API for posting content to the Fediverse
			send();
		case "outbox":    //	Optional. Dynamic.
			outbox();
		default:
			die();
	}

	//	The [WebFinger Protocol](https://docs.joinmastodon.org/spec/webfinger/) is used to identify accounts.
	//	It is requested with `example.com/.well-known/webfinger?resource=acct:username@example.com`
	//	This server only has one user, so it ignores the query string and always returns the same details.
	function webfinger() {
		global $username, $server;

		$webfinger = array(
			"subject" => "acct:{$username}@{$server}",
 			  "links" => array(
				array(
					 "rel" => "self",
					"type" => "application/activity+json",
					"href" => "https://{$server}/{$username}"
				)
			)
		);
		header( "Content-Type: application/json" );
		echo json_encode( $webfinger );
		die();
	}

	//	User:
	//	Requesting `example.com/username` returns a JSON document with the user's information.
	function username() {
		global $username, $realName, $summary, $server, $key_public;

		$user = array(
			"@context" => [
				"https://www.w3.org/ns/activitystreams",
				"https://w3id.org/security/v1"
			],
			                       "id" => "https://{$server}/{$username}",
			                     "type" => "Person",
			                "following" => "https://{$server}/following",
			                "followers" => "https://{$server}/followers",
			                    "inbox" => "https://{$server}/inbox",
			                   "outbox" => "https://{$server}/outbox",
			        "preferredUsername" =>  rawurldecode($username),
			                     "name" => "{$realName}",
			                  "summary" => "{$summary}",
			                      "url" => "https://{$server}/{$username}",
			"manuallyApprovesFollowers" =>  true,
			             "discoverable" =>  true,
			                "published" => "2024-02-12T11:51:00Z",
			"icon" => [
				     "type" => "Image",
				"mediaType" => "image/png",
				      "url" => "https://{$server}/icon.png"
			],
			"publicKey" => [
				"id"           => "https://{$server}/{$username}#main-key",
				"owner"        => "https://{$server}/{$username}",
				"publicKeyPem" => $key_public
			]
		);
		header( "Content-Type: application/activity+json" );
		echo json_encode( $user );
		die();
	}

	//	Follower / Following:
	// These JSON documents show how many users are following / followers-of this account.
	// The information here is self-attested. So you can lie and use any number you want.
	function following() {
		global $server;

		$following = array(
			  "@context" => "https://www.w3.org/ns/activitystreams",
			        "id" => "https://{$server}/following",
			      "type" => "Collection",
			"totalItems" => 0,
			     "items" => []
		);
		header( "Content-Type: application/activity+json" );
		echo json_encode( $following );
		die();
	}
	function followers() {
		global $server;
		$followers = array(
			  "@context" => "https://www.w3.org/ns/activitystreams",
			        "id" => "https://{$server}/followers",
			      "type" => "Collection",
			"totalItems" => 0,
			     "items" => []
		);
		header( "Content-Type: application/activity+json" );
		echo json_encode( $followers );
		die();
	}

	//	Inbox:
	//	The `/inbox` is the main server. It receives all requests. 
	//	This server only responds to "Follow" requests.
	//	A remote server sends a follow request which is a JSON file saying who they are.
	//	This code does not cryptographically validate the headers of the received message.
	//	The name of the remote user's server is saved to a file so that future messages can be delivered to it.
	//	An accept request is cryptographically signed and POST'd back to the remote server.
	function inbox() {
		global $body, $server, $username, $key_private;

		//	Get the message and type
		$inbox_message = $body;
		$inbox_type = $inbox_message["type"];

		//	This inbox only responds to follow requests
		if ( "Follow" != $inbox_type ) { die(); }

		//	Get the parameters
		$inbox_id    = $inbox_message["id"];
		$inbox_actor = $inbox_message["actor"];
		$inbox_host  = parse_url( $inbox_actor, PHP_URL_HOST );

		//	Does this account have any followers?
		if( file_exists( "followers.json" ) ) {
			$followers_file = file_get_contents( "followers.json" );
			$followers_json = json_decode( $followers_file, true );
		} else {
			$followers_json = array();
		}

		//	Add user to list. Don't care about duplicate users, server is what's important
		$followers_json[$inbox_host]["users"][] = $inbox_actor;

		//	Save the new followers file
		file_put_contents( "followers.json", print_r( json_encode( $followers_json ), true ) );

		//	Response Message ID
		//	This isn't used for anything important so could just be a random number
		$guid = uuid();

		//	Create the Accept message
		$message = [
			"@context" => "https://www.w3.org/ns/activitystreams",
			"id"       => "https://{$server}/{$guid}",
			"type"     => "Accept",
			"actor"    => "https://{$server}/{$username}",
			"object"   => [
				"@context" => "https://www.w3.org/ns/activitystreams",
				"id"       =>  $inbox_id,
				"type"     =>  $inbox_type,
				"actor"    =>  $inbox_actor,
				"object"   => "https://{$server}/{$username}",
			]
		];

		//	The Accept is sent to the server of the user who requested the follow
		//	TODO: The path doesn't *always* end with /inbox
		$host = $inbox_host;
		$path = parse_url( $inbox_actor, PHP_URL_PATH ) . "/inbox";

		//	Get the signed headers
		$headers = generate_signed_headers( $message, $host, $path );
	
		//	Specify the URL of the remote server's inbox
		//	TODO: The path doesn't *always* end with /inbox
		$remoteServerUrl = $inbox_actor . "/inbox";

		//	POST the message and header to the requester's inbox
		$ch = curl_init( $remoteServerUrl );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
		curl_setopt( $ch, CURLOPT_POSTFIELDS,     json_encode($message) );
		curl_setopt( $ch, CURLOPT_HTTPHEADER,     $headers );
		curl_exec( $ch );

		//	Check for errors
		if( curl_errno( $ch ) ) {
			file_put_contents( "error.txt",  curl_error( $ch ) );
		}
		curl_close($ch);
		die();
	}

	//	Unique ID:
	// Every message sent should have a unique ID. 
	// This can be anything you like. Some servers use a random number.
	// I prefer a date-sortable string.
	function uuid() {
		return sprintf( "%08x-%04x-%04x-%04x-%012x",
			time(),
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffffffffffff)
		);
	}

	//	Headers:
	// Every message that your server sends needs to be cryptographically signed with your Private Key.
	// This is a complicated process. Please read https://blog.joinmastodon.org/2018/07/how-to-make-friends-and-verify-requests/ for more information.
	function generate_signed_headers( $message, $host, $path ) {
		global $server, $username, $key_private;

		//	Encode the message object to JSON
		$message_json = json_encode( $message );

		//	Location of the Public Key
		$keyId = "https://{$server}/{$username}#main-key";

		//	Generate signing variables
		$hash   = hash( "sha256", $message_json, true );
		$digest = base64_encode( $hash );
		$date   = date( "D, d M Y H:i:s \G\M\T" );

		//	Get the Private Key
		$signer = openssl_get_privatekey( $key_private );

		//	Sign the path, host, date, and digest
		$stringToSign = "(request-target): post $path\nhost: $host\ndate: $date\ndigest: SHA-256=$digest";
		
		//	The signing function returns the variable $signature
		//	https://www.php.net/manual/en/function.openssl-sign.php
		openssl_sign(
			$stringToSign, 
			$signature, 
			$signer, 
			OPENSSL_ALGO_SHA256
		);
		//	Encode the signature
		$signature_b64 = base64_encode( $signature );

		//	Full signature header
		$signature_header = 'keyId="' . $keyId . '",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="' . $signature_b64 . '"';

		//	Header for POST reply
		$headers = array(
			        "Host: {$host}",
			        "Date: {$date}",
			      "Digest: SHA-256={$digest}",
			   "Signature: {$signature_header}",
			"Content-Type: application/activity+json",
			      "Accept: application/activity+json",
		);

		return $headers;
	}

	// User Interface for Homepage:
	// This creates a basic HTML page. This content appears when someone visits the root of your site.
	function home() {
		global $username, $server, $realName, $summary;
echo <<< HTML
<!DOCTYPE html>
<html lang="en-GB">
	<head>
		<meta charset="UTF-8">
		<title>{$realName}</title>
		<style>
			body { text-align: center; font-family:sans-serif; font-size:1.1em; }
		</style>
	</head>
	<body>
		<span class="h-card">
			<img src="icon.png" alt="icon" class="u-photo " width="140px" />
			<h1><span class="p-name">{$realName}</span></h1>
			<h2><a class="p-nickname u-url" href="https://{$server}/{$username}">@{$username}@{$server}</a></h2>
			<p class="note">{$summary}</p>
		</span>
		<p><a href="https://gitlab.com/edent/activitypub-single-php-file/">This software is licenced under AGPL 3.0</a>.</p>
		<p>This site is a basic <a href="https://www.w3.org/TR/activitypub/">ActivityPub</a> server designed to be <a href="https://shkspr.mobi/blog/2024/02/activitypub-server-in-a-single-file/">a lightweight educational tool</a>.</p>
	</body>
</html>
HTML;
die();
	}
	
	// User Interface for Writing:
	// This creates a basic HTML form. Type in your message and your password. It then POSTs the data to the `/send` endpoint.
	function write() {
echo <<< HTML
<!DOCTYPE html>
<html lang="en-GB">
	<head>
		<meta charset="UTF-8">
		<title>Send Message</title>
		<style>
			*{font-family:sans-serif;font-size:1.1em;}
		</style>
	</head>
	<body>
		<form action="/send" method="post" enctype="multipart/form-data">
			<label   for="content">Your message:</label><br>
			<textarea id="content"  name="content" rows="5" cols="32"></textarea><br>
			<label   for="image">Attach an image</label><br>
			<input  type="file"     name="image" id="image" accept="image/*"><br>
			<label   for="alt">Alt Text</label>
			<input  type="text"     name="alt" id="alt" size="32" /><br>
			<label   for="password">Password</label><br>
			<input  type="password" name="password" id="password" size="32"><br>
			<input  type="submit"  value="Post Message"> 
		</form>
	</body>
</html>
HTML;
		die();
	}

	//	Send Endpoint:
	//	This takes the submitted message and checks the password is correct.
	//	It reads the `followers.json` file and sends the message to every server that is following this account.
	function send() {
		global $password, $server, $username, $key_private;

		//	Does the posted password match the stored password?
		if( $password != $_POST["password"] ) { die(); }

		//	Get the posted content
		$content = $_POST["content"];

		//	Process the content into HTML to get hashtags etc
		list( "HTML" => $content, "TagArray" => $tags ) = process_content( $content );

		//	Is there an image attached?
		if ( isset( $_FILES['image']['tmp_name'] ) && ("" != $_FILES['image']['tmp_name'] ) ) {
			//	Get information about the image
			$image      = $_FILES['image']['tmp_name'];
			$image_info = getimagesize( $image );
			$image_ext  = image_type_to_extension( $image_info[2] );
			$image_mime = $image_info["mime"];

			//	Files are stored according to their hash
			//	A hash of "abc123" is stored in "/images/abc123.jpg"
			$sha1 = sha1_file( $image );
			$image_path = "images";
			$image_full_path = "{$image_path}/{$sha1}.{$image_ext}";

			//	Move media to the correct location
			//	Create a directory if it doesn't exist
			if( ! is_dir( $image_path ) ) { 
				mkdir( $image_path ); 
			}
			move_uploaded_file($image, $image_full_path );

			//	Get the alt text
			if ( isset( $_POST["alt"] ) ) {
				$alt = $_POST["alt"];
			} else {
				$alt = "";
			}

			//	Construct the attachment value for the post
			$attachment = [
				"type"      => "Image",
				"mediaType" => "{$image_mime}",
				"url"       => "https://{$server}/{$image_full_path}",
				"name"      => $alt
		  ];

		} else {
			$attachment = [];
		}

		//	Current time - ISO8601
		$timestamp = date( "c" );

		//	Outgoing Message ID
		$guid = uuid();

		//	Construct the Note
		//	contentMap is used to prevent unnecessary "translate this post" pop ups
		// hardcoded to English
		$note = [
			"@context"     => array(
				"https://www.w3.org/ns/activitystreams"
			),
			"id"           => "https://{$server}/posts/{$guid}.json",
			"type"         => "Note",
			"published"    => $timestamp,
			"attributedTo" => "https://{$server}/{$username}",
			"content"      => $content,
			"contentMap"   => ["en" => $content],
			"to"           => ["https://www.w3.org/ns/activitystreams#Public"],
			"tag"          => $tags,
			"attachment"   => $attachment
		];

		//	Construct the Message
		//	The audience is public and it is sent to all followers
		$message = [
			"@context" => "https://www.w3.org/ns/activitystreams",
			"id"       => "https://{$server}/posts/{$guid}.json",
			"type"     => "Create",
			"actor"    => "https://{$server}/{$username}",
			"to"       => [
				"https://www.w3.org/ns/activitystreams#Public"
			],
			"cc"       => [
				"https://{$server}/followers"
			],
			"object"   => $note
		];

		//	Create the context for the permalink
		$note = [ "@context" => "https://www.w3.org/ns/activitystreams", ...$note ];
		
		//	Save the permalink
		$note_json = json_encode( $note );
		//	Check for posts/ directory and create it
		if( ! is_dir( "posts" ) ) { mkdir( "posts"); }
		file_put_contents( "posts/{$guid}.json", print_r( $note_json, true ) );

		//	Read existing users and get their hosts
		$followers_file = file_get_contents( "followers.json" );
		$followers_json = json_decode( $followers_file, true );		
		$hosts = array_keys( $followers_json );

		//	Prepare to use the multiple cURL handle
		$mh = curl_multi_init();

		//	Loop through all the severs of the followers
		//	Each server needs its own cURL handle
		//	Each POST to an inbox needs to be signed separately
		foreach ( $hosts as $host ) {
			//	TODO: Not every host uses /inbox
			$path = "/inbox";

			//	Get the signed headers
			$headers = generate_signed_headers( $message, $host, $path );
		
			// Specify the URL of the remote server
			$remoteServerUrl = "https://{$host}{$path}";
		
			//	POST the message and header to the requester's inbox
			$ch = curl_init( $remoteServerUrl );		
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
			curl_setopt( $ch, CURLOPT_POSTFIELDS,     json_encode($message) );
			curl_setopt( $ch, CURLOPT_HTTPHEADER,     $headers );

			//	Add the handle to the multi-handle
			curl_multi_add_handle( $mh, $ch );
		}

		//	Execute the multi-handle
		do {
			$status = curl_multi_exec( $mh, $active );
			if ( $active ) {
				curl_multi_select( $mh );
			}
		} while ( $active && $status == CURLM_OK );

		//	Close the multi-handle
		curl_multi_close( $mh );

		//	Render the JSON so the user can see the POST has worked
		header( "Location: https://{$server}/posts/{$guid}.json" );
		die();
	}

	//	Content can be plain text. But to add clickable links and hashtags, it needs to be turned into HTML.
	//	Tags are also included separately in the note
	function process_content( $content ) {
		global $server;

		//	Convert any URls into hyperlinks
		$link_pattern = '/\bhttps?:\/\/\S+/iu';
		$replacement = function ( $match ) {
			$url = htmlspecialchars( $match[0], ENT_QUOTES, "UTF-8" );
			return "<a href=\"$url\">$url</a>";
		};
		$content = preg_replace_callback( $link_pattern, $replacement, $content );	  

		//	Get any hashtags
		$hashtags = [];
		$hashtag_pattern = '/(?:^|\s)\#(\w+)/';	//	Beginning of string, or whitespace, followed by #
		preg_match_all( $hashtag_pattern, $content, $hashtag_matches );
		foreach ($hashtag_matches[1] as $match) {
			$hashtags[] = $match;
		}

		//	Construct the tag value for the note object
		$tags = [];
		foreach ( $hashtags as $hashtag ) {
			$tags[] = array(
				"type" => "Hashtag",
				"name" => "#{$hashtag}",
			);
		}

		//	Add HTML links for hashtags into the text
		$content = preg_replace(
			$hashtag_pattern, 
			" <a href='https://{$server}/tag/$1'>#$1</a>", 
			$content
		);

		//	Detect user mentions
		$usernames = [];
		$usernames_pattern = '/@(\S+)@(\S+)/'; //	This is a *very* sloppy regex
		preg_match_all( $usernames_pattern, $content, $usernames_matches );
		foreach ( $usernames_matches[0] as $match ) {
			$usernames[] = $match;
		}

		//	Construct the mentions value for the note object
		//	This goes in the generic "tag" property
		//	TODO: Add this to the CC field
		foreach ( $usernames as $username ) {
			list( $null, $user, $domain ) = explode( "@", $username );
			$tags[] = array(
				"type" => "Mention",
				"href" => "https://{$domain}/@{$user}",
				"name" => "{$username}"
			);

			//	Add HTML links to usernames
			$username_link = "<a href=\"https://{$domain}/@{$user}\">$username</a>";
			$content = str_replace( $username, $username_link, $content );

		}

		// Construct HTML breaks from carriage returns and line breaks
		$linebreak_patterns = array("\r\n", "\r", "\n"); // Variations of line breaks found in raw text
		$content = str_replace($linebreak_patterns, "<br/>", $content);
		
		//	Construct the content
		$content = "<p>{$content}</p>";

		return [
			"HTML" => $content, 
			"TagArray" => $tags
		];
	}

	//	The Outbox contains a date-ordered list (newest first) of all the user's posts
	//	This is optional.
	function outbox() {
		global $server, $username;

		//	Get all posts
		$posts = array_reverse( glob("posts/" . "*.json") );
		//	Number of posts
		$totalItems = count( $posts );
		//	Create an ordered list
		$orderedItems = [];
		foreach ($posts as $post) {
			$orderedItems[] = array(
				"type"   => "Create",
				"actor"  => "https://{$server}/{$username}",
				"object" => "https://{$server}/{$post}"
			);
		}

		//	Create User's outbox
		$outbox = array(
			"@context"     => "https://www.w3.org/ns/activitystreams",
			"id"           => "https://{$server}/outbox",
			"type"         => "OrderedCollection",
			"totalItems"   =>  $totalItems,
			"summary"      => "All the user's posts",
			"orderedItems" =>  $orderedItems
		);

		//	Render the page
		header( "Content-Type: application/activity+json" );
		echo json_encode( $outbox );
		die();
	}

//	"One to stun, two to kill, three to make sure"
die();
die();
die();