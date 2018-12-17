<?php
require_once("settings.php");
require_once("reddit_api.php");
require_once("core.php");

reddit_access_token();

$reddit_inbox=reddit_inbox();

$unread_messages=filter_unread_messages($reddit_inbox);
$unread_mentions=filter_unread_mentions($reddit_inbox);

//var_dump($unread_messages);
//die();
foreach($unread_messages as $row) {
        $id=$row['id'];
        $name=$row['name'];
        $text="reply test";
        //reddit_comment($name,$text);
        reddit_mark_read($name);
        die();
}
?>
