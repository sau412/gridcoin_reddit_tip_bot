<?php
require_once("../lib/settings.php");
require_once("../lib/db.php");

db_connect();

$users_total=db_query_to_variable("SELECT count(*) FROM `users`");
$users_positive=db_query_to_variable("SELECT count(*) FROM `users` WHERE `balance`>0");

$tips_count=db_query_to_variable("SELECT count(*) FROM `withdrawals` WHERE `type`='tip'");
$topics=db_query_to_variable("SELECT count(*) FROM `posts`");
$messages=db_query_to_variable("SELECT count(*) FROM `messages`");

echo <<<_END
<!DOCTYPE HTML>
<html>
<head>
<title>Gridcoin Reddit Tip Bot</title>
<meta charset="utf-8" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<link rel="icon" href="favicon.png" type="image/png">
<link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
<center>
<h1>Gridcoin Reddit Tip Bot Stats</h1>
<h2>Stats</h2>
<table class='table_horizontal'>
<tr><th>Total users</th><td>$users_total</td>
<tr><th>Users with balance</th><td>$users_positive</td>
<tr><th>Tips count</th><td>$tips_count</td>
<tr><th>Reddit posts</th><td>$topics</td>
<tr><th>Reddit messages</th><td>$messages</td>
</table>

_END;


echo "<h2>Last 10 visible topics</h2>\n";

$topics_data=db_query_to_array("SELECT `subreddit`,`post_id`,`author`,`comments` FROM `posts` ORDER BY `timestamp` DESC LIMIT 10");

echo <<<_END
<table class='table_horizontal'>
<tr><th>Subreddit</th><th>Post</th><th>Author</th><th>Comments</th></tr>
_END;

foreach($topics_data as $row) {
	$subreddit=$row['subreddit'];
	$post_id=$row['post_id'];
	$author=$row['author'];
	$comments=$row['comments'];

	$author_html=htmlspecialchars($author);
	$subreddit_link="<a href='https://reddit.com/$subreddit'>$subreddit</a>";
	$post_part=str_replace("t3_","",$post_id);
	$post_link="<a href='https://reddit.com/$subreddit/comments/$post_part'>$post_part</a>";

	echo "<tr><td>$subreddit_link</td><td>$post_link</td><td>$author_html</td><td>$comments</td></tr>\n";
}

echo "</table>\n";

echo "<h2>Last 20 tipping messages</h2>\n";

$messages_data=db_query_to_array("SELECT m.`subreddit`,m.`post_id`,m.`message_id`,
	m.`message`,m.`author`,m.`timestamp`
FROM `messages` AS m
JOIN `withdrawals` AS w ON w.message_id=m.message_id
ORDER BY `timestamp` DESC LIMIT 20");

echo <<<_END
<table class='table_horizontal'>
<tr><th>Subreddit</th><th>Post</th><th>Message</th><th>Author</th><th>Timestamp</th></tr>
_END;

foreach($messages_data as $row) {
	$subreddit=$row['subreddit'];
	$post_id=$row['post_id'];
	$message=$row['message'];
	$message_id=$row['message_id'];
	$author=$row['author'];
	$timestamp=$row['timestamp'];

	$author_html=htmlspecialchars($author);
	$message=str_replace("[/u/grc\\_tip\\_bot](https://www.reddit.com/u/grc_tip_bot)","/u/grc_tip_bot",$message);
	$message_html=htmlspecialchars($message);
	$subreddit_link="<a href='https://reddit.com/$subreddit'>$subreddit</a>";
	$post_part=str_replace("t3_","",$post_id);
	$post_link="<a href='https://reddit.com/$subreddit/comments/$post_part'>$post_part</a>";

	echo "<tr><td>$subreddit_link</td><td>$post_link</td><td>$message_html</td><td>$author_html</td><td>$timestamp</td></tr>\n";
}

echo "</table>\n";
echo "<hr width=10%>\n";
echo "<p>Opensource Reddit Tipping Bot (<a href='https://github.com/sau412/gridcoin_reddit_tip_bot'>github</a>) by sau412, visit <a href='https://arikado.ru'>arikado.ru</a> to check other projects</p>\n";
echo "<p><img src='https://arikado.xyz/counter/?site=grc_tip_bot'></p>\n";
echo "</center>\n";
echo "</body>\n";
echo "</html>\n";
?>
