<?php

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

function filter_unread_mentions($reddit_inbox) {
        global $reddit_allowed_subreddit_array;
        $result=array();

        foreach($reddit_inbox->data->children as $row) {
                $id=$row->data->id;
                $author=$row->data->author;
                $body=$row->data->body;
                $is_new=$row->data->new;
                $subreddit=$row->data->subreddit_name_prefixed;
                $parent_id=$row->data->parent_id;
                $name=$row->data->name;
                // Only allowed subreddits
                if(!in_array($subreddit,$reddit_allowed_subreddit_array)) continue;
                $kind=$row->kind;
                // t1 is mention
                if($is_new && $kind=="t1") {
                        $result[]=array("id"=>$id,"kind"=>$kind,"author"=>$author,"body"=>$body,"parent_id"=>$parent_id,"name"=>$name);
                }
        }
        return $result;
}

?>
