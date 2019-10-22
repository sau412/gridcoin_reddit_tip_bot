<?php
require_once("../lib/settings.php");
require_once("../lib/db.php");

db_connect();

$users_total=db_query_to_variable("SELECT count(*) FROM `users`");
$users_positive=db_query_to_variable("SELECT count(*) FROM `users` WHERE `balance`>0");
$users_balance=db_query_to_variable("SELECT SUM(`balance`) FROM `users`");

$tips_count=db_query_to_variable("SELECT count(*) FROM `withdrawals` WHERE `type`='tip'");
$withdrawal_count=db_query_to_variable("SELECT count(*) FROM `withdrawals` WHERE `type`='withdraw'");
$topics=db_query_to_variable("SELECT count(*) FROM `posts`");
$messages=db_query_to_variable("SELECT count(*) FROM `messages`");
$inbox=db_query_to_variable("SELECT count(*) FROM `inbox`");

echo "users_total:$users_total";
echo " users_positive:$users_positive";
echo " users_balance:$users_balance"
echo " tips_count:$tips_count";
echo " withdrawal_count:$withdrawal_count";
echo " topics:$topics";
echo " messages:$messages";
echo " inbox:$inbox";
?>
