<?php
require_once("settings.php");
require_once("reddit_api.php");
require_once("db.php");
require_once("core.php");

$f=fopen("/tmp/gridcoin_tip_bot_lockfile","w");
if($f) {
        echo "Checking locks\n";
        if(!flock($f,LOCK_EX|LOCK_NB)) {
		die("Lockfile locked\n");
	}
}

db_connect();

reddit_access_token();

// Check for new posts
foreach($reddit_allowed_subreddit_array as $subreddit) {
	$new_posts=reddit_get_new_posts($subreddit);
	foreach($new_posts as $post) {
		if(!property_exists($post,"name")) continue;
		if(!property_exists($post,"num_comments")) continue;
		if(!property_exists($post,"author")) continue;

		$post_id=$post->name;
		$comments=$post->num_comments;
		$author=$post->author;
		$post_id_escaped=db_escape($post_id);
		$prev_comments=db_query_to_variable("SELECT `comments` FROM `posts` WHERE `post_id`='$post_id_escaped'");

		echo "New posts: subreddit '$subreddit' post_id '$post_id' prev_comments '$prev_comments' comments '$comments'\n";
		if($comments!=$prev_comments || $prev_comments==='') {
			echo "New comments in post $post_id\n";
			$comments_escaped=db_escape($comments);
			$subreddit_escaped=db_escape($subreddit);
			$author_escaped=db_escape($author);
			db_query("INSERT INTO `posts` (`subreddit`,`post_id`,`author`,`comments`)
					VALUES ('$subreddit_escaped','$post_id_escaped','$author_escaped','$comments_escaped')
					ON DUPLICATE KEY UPDATE `comments`='$comments_escaped',`is_updated`=1");
		}
	}
}

// Check old posts for new comments
$posts_array=db_query_to_array("SELECT `subreddit`,`post_id` FROM `posts` WHERE `is_updated`=0");
foreach($posts_array as $post_data) {
	$post_id=$post_data['post_id'];
	$subreddit=$post_data['subreddit'];
	if(!in_array($subreddit,$reddit_allowed_subreddit_array)) continue;

	$message_info=reddit_get_message_info($post_id);
	if(!property_exists($message_info->data,"num_comments")) {
		echo "No num_comments property for post $post_id\n";
		continue;
	}

	$comments=$message_info->data->num_comments;
	$author=$message_info->data->author;
	$post_id_escaped=db_escape($post_id);
	$prev_comments=db_query_to_variable("SELECT `comments` FROM `posts` WHERE `post_id`='$post_id_escaped'");

	echo "Old posts: subreddit '$subreddit' Post_id '$post_id' prev_comments '$prev_comments' comments '$comments'\n";
	if($comments!=$prev_comments || $prev_comments==='') {
		echo "New comments in post $post_id\n";
		$comments_escaped=db_escape($comments);
		$subreddit_escaped=db_escape($subreddit);
		$author_escaped=db_escape($author);
		db_query("INSERT INTO `posts` (`subreddit`,`post_id`,`author`,`comments`)
				VALUES ('$subreddit_escaped','$post_id_escaped','$author_escaped','$comments_escaped')
				ON DUPLICATE KEY UPDATE `comments`='$comments_escaped',`is_updated`=1");
	}
}

// Check new messages in updated posts
$posts_array=db_query_to_array("SELECT `subreddit`,`post_id`,`author` FROM `posts` WHERE `is_updated`=1");
//$posts_array=array(array("post_id"=>"t1_a9nxnr"));
foreach($posts_array as $post_data) {
	$post_id=$post_data['post_id'];
	$subreddit=$post_data['subreddit'];
	$author=$post_data['author'];

	if(!in_array($subreddit,$reddit_allowed_subreddit_array)) continue;

	$message_tree=reddit_get_message_tree($post_id);
	parse_message_tree($subreddit,$post_id,$message_tree,$author);
//var_dump($message_tree);
	$subreddit_escaped=db_escape($subreddit);
	$post_id_escaped=db_escape($post_id);
	db_query("UPDATE `posts` SET `is_updated`=0 WHERE `post_id`='$post_id_escaped' AND `subreddit`='$subreddit_escaped'");
}

function parse_message_tree($subreddit,$post_id,$message_tree,$author) {
	foreach($message_tree as $branch) {
		if(!property_exists($branch,"kind") || $branch->kind!="Listing") return;
		parse_message_tree_branch($subreddit,$post_id,$branch,$author);
	}
}

function parse_message_tree_branch($subreddit,$post_id,$branch,$parent_author='') {
	if(!property_exists($branch,"data")) return;
	if(!property_exists($branch->data,"children")) return;

	foreach($branch->data->children as $data_item) {
		if(!property_exists($data_item,"data")) continue;
		if(!property_exists($data_item->data,"body")) continue;
		if(!property_exists($data_item->data,"author")) continue;
		if(!property_exists($data_item->data,"name")) continue;
		if(!property_exists($data_item->data,"parent_id")) continue;

		$text=$data_item->data->body;
		$author=$data_item->data->author;
		$message_id=$data_item->data->name;
		$parent_id=$data_item->data->parent_id;

		$text_escaped=db_escape($text);
		$author_escaped=db_escape($author);
		$message_id_escaped=db_escape($message_id);
		$parent_id_escaped=db_escape($parent_id);

//echo "[$message_id] <$author> $text\n";
		$subreddit_escaped=db_escape($subreddit);
		$post_id_escaped=db_escape($post_id);
		$parent_id_escaped=db_escape($parent_id);
		$parent_author_escaped=db_escape($parent_author);

		db_query("INSERT INTO `messages` (`subreddit`,`post_id`,`message_id`,`parent_id`,`message`,`author`,`parent_author`)
				VALUES ('$subreddit_escaped','$post_id_escaped','$message_id_escaped','$parent_id_escaped',
					'$text_escaped','$author_escaped','$parent_author_escaped')
				ON DUPLICATE KEY UPDATE `timestamp`=NOW(),`parent_id`=VALUES(`parent_id`),`parent_author`=VALUES(`parent_author`)");

		if(property_exists($data_item->data,"replies")) {
			parse_message_tree_branch($subreddit,$post_id,$data_item->data->replies,$author);
		}
	}
}

$messages_array=db_query_to_array("SELECT `subreddit`,`post_id`,`message_id`,`message`,`author`,`parent_author` FROM `messages` WHERE `reply_needed`=1");

foreach($messages_array as $message_info) {
	$subreddit=$message_info['subreddit'];
	$post_id=$message_info['post_id'];
	$message_id=$message_info['message_id'];
	$message=$message_info['message'];
	$author=$message_info['author'];
	$parent_author=$message_info['parent_author'];

	$user_uid=get_user_uid_by_username($author);
	$parent_user_uid=get_user_uid_by_username($parent_author);

	$reply=parse_public_message($user_uid,$parent_user_uid,$message_id,$message);
	//echo "Message:\n$message\nReply:$reply\n\n";

	if($reply) {
		$result=reddit_comment($message_id,$reply);
	} else {
		$result=TRUE;
	}

	$reply_escaped=db_escape($reply);
	$subreddit_escaped=db_escape($subreddit);
	$post_id_escaped=db_escape($post_id);
	$message_id_escaped=db_escape($message_id);

	if($result==TRUE) {
		db_query("UPDATE `messages` SET `reply_needed`=0,`reply`='$reply_escaped'
			WHERE `subreddit`='$subreddit_escaped' AND `post_id`='$post_id_escaped' AND `message_id`='$message_id_escaped'");
	} else {
		// If message not sent - stop sending more messages
		break;
	}
}

// Reply to private messages
$reddit_inbox=reddit_inbox();
//var_dump($reddit_inbox);

// Save messages to DB
foreach($reddit_inbox->data->children as $row) {
	if(!property_exists($row,"data")) continue;
	if(!property_exists($row,"kind")) continue;
	if(!property_exists($row->data,"author")) continue;
	if(!property_exists($row->data,"body")) continue;
	if(!property_exists($row->data,"name")) continue;

	$kind=$row->kind;
	// t4 is private messages
	if($kind!='t4') continue;

	$author=$row->data->author;
	$message=$row->data->body;
	$message_id=$row->data->name;

	//var_dump($row);
	$author_escaped=db_escape($author);
	$message_escaped=db_escape($message);
	$message_id_escaped=db_escape($message_id);

	//echo "Inbox message:\nMessage id $message_id\nAuthor '$author'\nMesage: $message\n\n";
	db_query("INSERT INTO `inbox` (`message_id`,`message`,`author`) VALUES ('$message_id_escaped','$message_escaped','$author_escaped')
			ON DUPLICATE KEY UPDATE `timestamp`=NOW()");
}

$messages_array=db_query_to_array("SELECT `message_id`,`author`,`message` FROM `inbox` WHERE `reply_needed`=1");

foreach($messages_array as $message_info) {
	$message_id=$message_info['message_id'];
	$author=$message_info['author'];
	$message=$message_info['message'];
	$user_uid=get_user_uid_by_username($author);
	$reply=parse_private_message($user_uid,$message_id,$message);
	if($reply) reddit_comment($message_id,$reply);

	$reply_escaped=db_escape($reply);
	$message_id_escaped=db_escape($message_id);
	db_query("UPDATE `inbox` SET `reply_needed`=0,`reply`='$reply_escaped' WHERE `message_id`='$message_id_escaped'");
}

require_once("update_gridcoin_data.php");
?>
