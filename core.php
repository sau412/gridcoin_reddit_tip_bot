<?php

// Add private messages to DB
function save_private_messages($reddit_inbox) {
	foreach($reddit_inbox->data->children as $row) {
		$is_new=$row->data->new;
		if(!$is_new) continue;

		$author=$row->data->author;
		$body=$row->data->body;
		$kind=$row->kind;
		$name=$row->data->name;

		$message_escaped=db_escape("Author '$author'\nBody '$body'\nKind '$kind'\nName '$name'\n");

		echo "Message Author '$author' Body '$body' Kind '$kind' Name '$name'\n";
		db_query("INSERT INTO `inbox` (`message`) VALUES ('$message_escaped')");

		reddit_mark_read($name);
	}
}

// Filter private messages
function filter_unread_messages($reddit_inbox) {
	$result=array();

	foreach($reddit_inbox->data->children as $row) {
		$id=$row->data->id;
		$author=$row->data->author;
		$body=$row->data->body;
		$is_new=$row->data->new;
		$kind=$row->kind;
		$name=$row->data->name;
		// t4 is private message
		if($is_new && $kind=="t4") {
			$result[]=array("id"=>$id,"kind"=>$kind,"author"=>$author,"body"=>$body,"name"=>$name);
		}
	}
	return $result;
}

// Get user uid (or create)
function get_user_uid_by_username($username) {
	$username_escaped=db_escape($username);
	$uid=db_query_to_variable("SELECT `uid` FROM `users` WHERE `username`='$username_escaped'");
	if(!$uid) {
		db_query("INSERT INTO `users` (`username`) VALUES ('$username_escaped')");
		$uid=mysql_insert_id();
	}
	return $uid;
}

// Parse private message
function parse_private_message($user_uid,$message_id,$message) {
	global $currency_short;

	$message=trim($message);

	if(preg_match('/^\\+?!?([^ ]+)/',$message,$matches)) {
		$command=$matches[1];
	} else {
		return "Unknown format";
	}

	$command=strtolower($command);

	switch($command) {
		case 'address':
			$address=get_address($user_uid);
			if($address=='') {
				$result="Your address is not received yet";
			} else {
				$result="Your address is $address";
			}
			break;
		case 'balance':
			$balance=get_balance($user_uid);
			if($balance==='') $balance=0;
			$result="Your balance is $balance $currency_short";
			break;
		case 'withdraw':
			if(preg_match('/^ *!?\\+?([^ ]+) ([^ ]+) ([^ ]+) *$/',$message,$matches)) {
				$address=$matches[2];
				$amount=$matches[3];
			} else if(preg_match('/^ *!?\\+?([^ ]+) ([^ ]+) *$/',$message,$matches)) {
				$address=$matches[2];
				$amount=get_balance($user_uid);
			}
			if(isset($amount) && (!is_numeric($amount) || $amount<=0)) {
				$result="Error: amount should be a positive number";
			} else if(isset($address) && $address!='') {
				$message_id_escaped=db_escape($message_id);
				$user_uid_escaped=db_escape($user_uid);
				$address_escaped=db_escape($address);
				$amount_escaped=db_escape($amount);
				db_query("INSERT INTO `withdrawals` (`type`,`message_id`,`from_user_uid`,`address`,`amount`)
						VALUES ('withdraw','$message_id_escaped','$user_uid_escaped','$address_escaped','$amount_escaped')");
				$result="Request to withdraw $amount $currency_short to $address sent";
				recalculate_balance($user_uid);
			} else {
				$result="Withdraw syntax: withdraw {address} [amount]";
			}
			break;
		default:
			$result="";
			break;
		case 'help':
		case 'info':
			$result=<<<_END
This is Gridcoin Reddit tipping bot
Private commands are:
* address - shows your deposit address
* balance - shows your balance
* withdraw {address} [amount] - withdraw funds to address
* help - (or any unknown message) this help
_END;
			break;
	}
	return $result;
}

// Parse public message
function parse_public_message($user_uid,$parent_user_uid,$message_id,$message) {
	global $currency_short;
	global $reddit_username;

	$message=strtolower($message);
	// Remove links
	while(preg_match('/\\[([^\\]]+)\\]\\([^\\)]+\\)/',$message,$matches)) {
		$pattern=$matches[0];
		$replacement=$matches[1];
//echo "Replace '$pattern' to '$replacement'\n";
		$message=str_replace($pattern,$replacement,$message);
	}
	$message=str_replace("\\_","_",$message);
	$message=trim($message);
//echo "$message\n";

	if(preg_match('/\\/?u\\/'.$reddit_username.' +([0-9.,]+) */',$message,$matches)) {
		$amount=$matches[1];
		$amount=str_replace(",",".",$amount);
	}
	if(!isset($amount) || !is_numeric($amount) || $amount<0) {
		$reply="";
	} else if($user_uid==$parent_user_uid) {
		$reply="You cannot tip youself";
	} else if(get_balance($user_uid)<$amount) {
		$reply="You cannot tip more than you have";
	} else {
		$username=get_username($parent_user_uid);
		$message_id_escaped=db_escape($message_id);
		$user_uid_escaped=db_escape($user_uid);
		$parent_user_uid_escaped=db_escape($parent_user_uid);
		$amount_escaped=db_escape($amount);
		db_query("INSERT INTO `withdrawals` (`type`,`message_id`,`from_user_uid`,`to_user_uid`,`amount`)
				VALUES ('tip','$message_id_escaped','$user_uid_escaped','$parent_user_uid_escaped','$amount_escaped')");
		recalculate_balance($user_uid);
		$reply="";
	}
	return $reply;
}

function get_username($user_uid) {
	$user_uid_escaped=db_escape($user_uid);
	return db_query_to_variable("SELECT `username` FROM `users` WHERE `uid`='$user_uid_escaped'");
}

function get_balance($user_uid) {
	$user_uid_escaped=db_escape($user_uid);
	return db_query_to_variable("SELECT `balance` FROM `users` WHERE `uid`='$user_uid_escaped'");
}

function get_address($user_uid) {
	$user_uid_escaped=db_escape($user_uid);
	return db_query_to_variable("SELECT `address` FROM `users` WHERE `uid`='$user_uid_escaped'");
}

function recalculate_balance($user_uid) {
	$user_uid_escaped=db_escape($user_uid);
	$received=db_query_to_variable("SELECT `received` FROM `users` WHERE `uid`='$user_uid_escaped'");
	$bonus=db_query_to_variable("SELECT `bonus` FROM `users` WHERE `uid`='$user_uid_escaped'");
	$withdrawn=db_query_to_variable("SELECT SUM(`amount`) FROM `withdrawals` WHERE `from_user_uid`='$user_uid_escaped' AND `status` IN ('sent','processing','requested')");

	if($received=='') $received=0;
	if($withdrawn=='') $withdrawn=0;
	if($bonus=='') $bonus=0;

//echo "User uid '$user_uid' received '$received' spent '$withdrawn'\n";
	$balance=$received-$withdrawn+$bonus;
	$balance_escaped=db_escape($balance);
	db_query("UPDATE `users` SET `balance`='$balance_escaped' WHERE `uid`='$user_uid_escaped'");
}
?>
