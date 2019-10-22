<?php
require_once("../lib/settings.php");
require_once("../lib/db.php");

db_connect();

echo "<h1>Gridcoin Reddit Tip Bot Stats</h1>\n";

$users_total=db_query_to_variable("SELECT count(*) FROM `users`");
$users_positive=db_query_to_variable("SELECT count(*) FROM `users` WHERE `balance`>0");

$tips_count=db_query_to_variable("SELECT count(*) FROM `withdrawals` WHERE `type`='tip'");
$topics=db_query_to_variable("SELECT count(*) FROM `posts`");
$messages=db_query_to_variable("SELECT count(*) FROM `messages`");

echo "<h2>Stats</h2>\n";

echo <<<_END
<table>
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
<table>
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

echo "<h2>Last 20 visible messages</h2>\n";

$messages_data=db_query_to_array("SELECT `subreddit`,m.`post_id`,`message_id`,`message`,`author`,`reply`,`timestamp`
FROM `messages` AS m
JOIN `withdrawals` AS w ON w.message_id=m.message_id
ORDER BY `timestamp` DESC LIMIT 10");

echo <<<_END
<table>
<tr><th>Subreddit</th><th>Post</th><th>Message</th><th>Author</th><th>Reply</th></tr>
_END;

foreach($messages_data as $row) {
	$subreddit=$row['subreddit'];
	$post_id=$row['post_id'];
	$message_id=$row['message_id'];
	$author=$row['author'];
	$reply=$row['reply'];

	$message_html=htmlspecialchars($message);
	$reply_html=htmlspecialchars($reply);
	$subreddit_link="<a href='https://reddit.com/$subreddit'>$subreddit</a>";
	$post_part=str_replace("t3_","",$post_id);
	$post_link="<a href='https://reddit.com/$subreddit/comments/$post_part'>$post_part</a>";
	$message_part=str_replace("t1_","",$message_id);
	$message_link="<a href='https://reddit.com/$subreddit/comments/$post_part/$message_part'>$message_part</a>";

	echo "<tr><td>$subreddit_link</td><td>$post_link</td><td>$message_html</td><td>$author_html</td><td>$reply_html</td></tr>\n";
}

echo "</table>\n";

?>
