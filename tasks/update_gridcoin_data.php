<?php
// Only for command line
if(!isset($argc)) die();

// Gridcoinresearch send rewards
require_once("../lib/settings.php");
require_once("../lib/db.php");
require_once("../lib/core.php");
require_once("../lib/gridcoin_web_wallet.php");
require_once("../lib/reddit_api.php");

// Check if unsent rewards exists
db_connect();

// Get addresses for new users
$new_array=db_query_to_array("SELECT `uid` FROM `users` WHERE `wallet_uid` IS NULL");
foreach($new_array as $user_info) {
	$uid=$user_info['uid'];
	$result=grc_web_get_new_receiving_address();
	$wallet_uid=$result->uid;
	$uid_escaped=db_escape($uid);
	$wallet_uid_escaped=db_escape($wallet_uid);
	db_query("UPDATE `users` SET `wallet_uid`='$wallet_uid_escaped' WHERE `uid`='$uid_escaped'");
}

// Update addresses data for all users
$pending_array=db_query_to_array("SELECT `uid`,`wallet_uid`,`received` FROM `users` WHERE `wallet_uid` IS NOT NULL");
foreach($pending_array as $user_info) {
	$uid=$user_info['uid'];
	$address_uid=$user_info['wallet_uid'];
	$prev_received=$user_info['received'];
	$result=grc_web_get_receiving_address($address_uid);
	$address=$result->address;
	$received=$result->received;

	if($address!='') {
		$uid_escaped=db_escape($uid);
		$address_escaped=db_escape($address);
		$received_escaped=db_escape($received);
		db_query("UPDATE `users` SET `address`='$address_escaped',`received`='$received_escaped' WHERE `uid`='$uid_escaped'");
		//recalculate_balance($uid);
	}

	if($prev_received!=$received) {
		recalculate_balance($uid);
	}
}

// Send tips if possible
$unsent_tips_array=db_query_to_array("SELECT `uid`,`to_user_uid` FROM `withdrawals` WHERE `address` IS NULL");
foreach($unsent_tips_array as $tip_info) {
	$uid=$tip_info['uid'];
	$to_user_uid=$tip_info['to_user_uid'];

	$address_to=get_address($to_user_uid);

	if($address_to!='') {
		$uid_escaped=db_escape($uid);
		$address_to_escaped=db_escape($address_to);
		db_query("UPDATE `withdrawals` SET `address`='$address_to_escaped' WHERE `uid`='$uid_escaped'");
	}
}

// Get balance
$current_balance=grc_web_get_balance();
echo "Current balance: $current_balance\n";

// Get payout information for GRC
$payout_data_array=db_query_to_array("SELECT `uid`,`message_id`,`type`,`from_user_uid`,`to_user_uid`,`address`,`amount`,`wallet_uid` FROM `withdrawals`
					WHERE `status` IN ('requested','processing') AND `address` IS NOT NULL");

foreach($payout_data_array as $payout_data) {
	$uid=$payout_data['uid'];
	$message_id=$payout_data['message_id'];
	$type=$payout_data['type'];
	$from_user_uid=$payout_data['from_user_uid'];
	$to_user_uid=$payout_data['to_user_uid'];
	$address=$payout_data['address'];
	$amount=$payout_data['amount'];
	$wallet_uid=$payout_data['wallet_uid'];

	$uid_escaped=db_escape($uid);

	// If we have funds for this
	if($wallet_uid) {
		$tx_data=grc_web_get_tx_status($wallet_uid);
//var_dump($tx_data);
		if($tx_data) {
			switch($tx_data->status) {
				case 'address error':
					echo "Address error wallet uid '$wallet_uid' for address '$address' amount '$amount' GRC\n";
					//write_log("Address error wallet uid '$wallet_uid' for address '$address' amount '$amount' GRC");
					db_query("UPDATE `withdrawals` SET `tx_id`='address error',`status`='error' WHERE `uid`='$uid_escaped'");
					break;
				case 'sending error':
					echo "Sending error wallet uid '$wallet_uid' for address '$address' amount '$amount' GRC\n";
					//write_log("Sending error wallet uid '$wallet_uid' for address '$address' amount '$amount' GRC");
					db_query("UPDATE `withdrawals` SET `tx_id`='sending error',`status`='error' WHERE `uid`='$uid_escaped'");
					break;
				case 'received':
				case 'pending':
				case 'sent':
					$tx_id=$tx_data->tx_id;
					$tx_id_escaped=db_escape($tx_id);
					//write_log("Sent wallet uid '$wallet_uid' for address '$address' amount '$amount' GRC");
					echo "Sent wallet uid '$wallet_uid' for address '$address' amount '$amount' GRC\n";
					db_query("UPDATE `withdrawals` SET `tx_id`='$tx_id_escaped',`status`='sent' WHERE `uid`='$uid_escaped'");
					break;
			}
		}
	} else if($amount<$current_balance) {
		echo "Sending $amount to $address\n";

		// Send coins, get txid
		$wallet_uid=grc_web_send($address,$amount);
		$wallet_uid_escaped=db_escape($wallet_uid);
		if($wallet_uid && is_numeric($wallet_uid)) {
			db_query("UPDATE `withdrawals` SET `status`='processing',`wallet_uid`='$wallet_uid_escaped' WHERE `uid`='$uid_escaped'");
		} else {
			write_log("Sending error, no wallet uid for address '$address' amount '$amount' GRC");
		}
		echo "----\n";
	} else {
		// No funds
		echo "Insufficient funds for sending rewards\n";
		write_log("Insufficient funds for sending rewards");
		break;
	}
}

$reply_data_array=db_query_to_array("SELECT `uid`,`message_id`,`type`,`from_user_uid`,`to_user_uid`,`status`,`amount`,`address`,`tx_id`
					FROM `withdrawals` WHERE `reply_sent`=0 AND `status` IN ('error','sent')");
foreach($reply_data_array as $reply_data) {
	$uid=$reply_data['uid'];
	$message_id=$reply_data['message_id'];
	$type=$reply_data['type'];
	$from_user_uid=$reply_data['from_user_uid'];
	$to_user_uid=$reply_data['to_user_uid'];
	$status=$reply_data['status'];
	$amount=$reply_data['amount'];
	$address=$reply_data['address'];
	$tx_id=$reply_data['tx_id'];

	$uid_escaped=db_escape($uid);
	$result=FALSE;

	if($type=='tip') {
		$from_username=get_username($from_user_uid);
		$to_username=get_username($to_user_uid);
		$result=reddit_comment($message_id,"Sent $amount $currency_short from /u/$from_username to /u/$to_username transaction [txid](https://www.gridcoinstats.eu/tx/$tx_id)");
	} else if($type=='withdraw') {
		if($status=='error') {
			if($tx_id=='address error') {
				$result=reddit_comment($message_id,"Error while sending $amount $currency_short to $address (address error)");
			} else {
				$result=reddit_comment($message_id,"Error while sending $amount $currency_short to $address (sending error)");
			}
		} else if($status=='sent') {
			$result=reddit_comment($message_id,"Sent $amount $currency_short to $address ([txid](https://www.gridcoinstats.eu/tx/$tx_id))");
		}
	}

	if($result) {
		db_query("UPDATE `withdrawals` SET `reply_sent`=1 WHERE `uid`='$uid_escaped'");
	} else {
		break;
	}
}
?>

