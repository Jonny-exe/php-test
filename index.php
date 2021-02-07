<?php
require_once("vendor/autoload.php");
session_start();

$dbhost = 'localhost';
$dbuser = 'php-test';
$dbpass = 'password';
$dbname = 'php_test';

$conn = new PDO('mysql:host=' . $dbhost . ';dbname=' . $dbname, $dbuser, $dbpass);

$name = $_SERVER['REMOTE_ADDR'];
// $name = "test2";
$name = str_replace(".", "_", $name);

if ($_SESSION['name']) {
	$name = $_SESSION['name'];
}

if ($conn->connect_error) {
	die("Connection failed");
}

if ($_REQUEST['friend']) {
	$_SESSION['friend'] = $_REQUEST['friend'];
}


function does_user_exist($conn, $name)
{
	$q = "SELECT * from users where name = '$name'";
	$result = $conn->query($q);
	$result = $result->fetchAll();
	if (count($result) == 0) {
		return false;
	} else {
		return true;
	}
}


function create_keypair()
{
	$keypair = sodium_crypto_box_keypair();
	return $keypair;
}
function encrypt_message($message, $public_key)
{
	$encrypted_message = sodium_crypto_box_seal($message, $public_key);
	return $encrypted_message;
}

function decrypt_message($encrypted_message, $keypair)
{
	$decrypted = sodium_crypto_box_seal_open(
		$encrypted_message,
		$keypair
	);
	return $decrypted;
}



function insert_user($conn, $name, $public_key)
{
	$q = "insert into users (name, public_key) values ('$name', '$public_key')";
	$conn->query($q);
}

function insertFriend($conn, $uid, $friend_name)
{
	$q = "insert into friends (name, uid) values ('$friend_name', $uid)";
	$conn->query($q);
}


$does_user_exist = does_user_exist($conn, $name);
function create_keypair_if_not_exist($conn, $does_user_exist, $name)
{
	if (!$does_user_exist) {
		$keypair = create_keypair();
		$public_key = sodium_crypto_box_publickey($keypair);
		setcookie("keypair_" . $name, $keypair, time() + (86400 * 30));
		insert_user($conn, $name, $public_key);
		return $keypair;
	} else {
		return $keypair = $_COOKIE['keypair_' . $name];
	}
}
$keypair = create_keypair_if_not_exist($conn, $does_user_exist, $name);
print $name;


?>
<link hred="css/style.css.php" rel="stylesheet" type="text/css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-giJF6kkoqNQ00vy+HMDP7azOuL0xtbfIcaT9wjKHr8RbDVddVHyTfAAsrekwKmP1" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js" integrity="sha384-ygbV9kiqUc6oa4msXn9868pTtWMgiQaeYH7/t7LECLbyPA2x65Kgf80OJFdroafW" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js" integrity="sha384-q2kxQ16AaE6UbzuKqyBE9/u/KzioAlnx2maXQHiDX9d4/zp8Ok3f+M7DPm+Ib6IU" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.min.js" integrity="sha384-pQQkAEnwaBkjpqZ8RU1fF1AKtTcHJwFl3pblpTlHXybJjHpMYo79HY3hIi4NKxyj" crossorigin="anonymous"></script>



<form action=index.php>
	<div class="input-group">
		<input class="btn btn-primary" type=submit value="Send message">
		<textarea id=userInput class="form-control" name=text></textarea>
	</div>
</form>

<?php

function insert_message($conn, $sender_text, $receiver_text, $sender, $receiver)
{
	$q = "insert into messages (sender_text, receiver_text, sender, receiver) values ('$sender_text','$receiver_text', '$sender', '$receiver')";
	$conn->query($q);
}

function get_public_key($name, $conn, $keypair)
{
	$result = $conn->query("SELECT public_key from users where name = '$name'");
	$result = $result->fetch()[0];
	// $public_key = sodium_crypto_box_publickey($keypair);
	return $result;
}
if ($_REQUEST['text']) {
	$text = $_REQUEST['text'];
	$friend = $_SESSION['friend'];
	$public_key_user = get_public_key($name, $conn, $keypair);
	$public_key_friend = get_public_key($friend, $conn, $keypair);
	print "Friend key: " . $public_key_friend;
	print "\nUser key: " . $public_key_user;
	$encrypted_text_user = encrypt_message($text, $public_key_user);
	$encrypted_text_friend = encrypt_message($text, $public_key_friend);
	insert_message($conn, $encrypted_text_user, $encrypted_text_friend, $name, $friend);
	print "inserted messages";
	$new_url = strip_param_from_url("text");
	set_url($new_url);
}

function strip_param_from_url($param)
{
	$url = $_SERVER['REQUEST_URI'];
	$base_url = strtok($url, '?');              // Get the base url
	$parsed_url = parse_url($url);              // Parse it 
	$query = $parsed_url['query'];              // Get the query string
	parse_str($query, $parameters);           // Convert Parameters into array
	unset($parameters[$param]);               // Delete the one you want
	$new_query = http_build_query($parameters); // Rebuilt query string
	return $base_url . '?' . $new_query;            // Finally url is ready
}

function set_url($url)
{
	echo ("<script>history.replaceState({},'','$url');</script>");
}

function getUID($conn, $name)
{
	$uid = $conn->query("select uid from users where name='$name' order by uid desc");
	$uid = $uid->fetch();
	return $uid[0];
}

if ($_REQUEST['new_friend']) {
	$friend_name = $_REQUEST['new_friend'];
	$uid = getUID($conn, $name);
	insertFriend($conn, $uid, $friend_name);
}

function get_messages($conn, $friend, $name)
{
	$q = "select sender_text, receiver_text, sender from messages where (sender='$name' and receiver='$friend') or (sender='$friend' and receiver='$name')";
	$messages = $conn->query($q);
	$messages = $messages->fetchAll();
	print_r("MEssages: " . $messages);
	return $messages;
}

if ($_SESSION['friend']) {
	$friend = $_SESSION['friend'];
	print "<h2>User: " . $name . "</h2>";
	$uid = getUID($conn, $name);
	$messages = get_messages($conn, $friend, $name);

	print "<table class='table' border=1>
	<thead><td colspan=2> Messages </td></thead>";
	render_messages($messages, $name, $keypair);
	print "</table>";
}

function render_messages($messages, $name, $keypair)
{
	if ($messages != null)
		foreach ($messages as $message) {
			$sender = $message["sender"];
			if ($sender == $name) {
				print "sender";
				$text = $message["sender_text"];
			} else {
				$text = $message["receiver_text"];
			}

			$text = decrypt_message($text, $keypair);
			$sender = $message["sender"];
			if ($sender == $name) {
				$td = "<tr><td>$text</td><td></td></tr>";
			} else {
				$td = "<tr><td></td><td>$text</td></tr>";
			}
			print $td;
		}
	else {
		print "<tr><td> No messages </td></tr>";
	}
}

function get_friends($conn, $uid)
{
	$result = $conn->query("select name, uid from friends where uid=$uid");
	if (!$result) {
		return [];
	}
	$result = $result->fetchAll();
	return $result;
}

function print_friends($conn, $friend, $name, $friend_name)
{
	print "<h2>Friend: " . $friend . "</h2>";
	$uid = getUID($conn, $name);
	print "UID: " . $uid;
	$friends = get_friends($conn, $uid);
	print_r($friends);
	if ($friends) {
		print $friends;
		print "<table class='table' border=1>
    <thead><td colspan=2> Your friends </td></thead>";
		foreach ($friends as $friend) {
			$id = $friend['uid'];
			$friendname = $friend['name'];
			print <<<EOF
    <tr><td>$id</td><td><a href="index.php?name=$name&new_friend=$friend_name&friend=$friendname"> $friendname </a></td></tr>
    EOF;
		}
		print "</table>";
	}
}
print_friends($conn, $friend, $name, $friend_name)


?>
<form action=index.php>
	<div class="input-group mb-3">
		<button class="btn btn-primary" type="submit" id="button-addon1">Add Friend</button>
		<input type="text" name="new_friend" class="form-control" placeholder="" aria-label="Example text with button addon" aria-describedby="button-addon1">
	</div>
	<!-- <div class="input-group mb-3">
		<button class="btn btn-primary" type="submit" id="button-addon1">Login</button>
		<input type="text" name="name" class="form-control" placeholder="" aria-label="Example text with button addon" aria-describedby="button-addon1">
	</div> -->
</form>
<style>
	body {
		padding: 1%;
	}
</style>