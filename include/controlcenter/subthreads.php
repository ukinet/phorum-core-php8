<?php

////////////////////////////////////////////////////////////////////////////////
//                                                                            //
//   Copyright (C) 2006  Phorum Development Team                              //
//   http://www.phorum.org                                                    //
//                                                                            //
//   This program is free software. You can redistribute it and/or modify     //
//   it under the terms of either the current Phorum License (viewable at     //
//   phorum.org) or the Phorum License that was distributed with this file    //
//                                                                            //
//   This program is distributed in the hope that it will be useful,          //
//   but WITHOUT ANY WARRANTY, without even the implied warranty of           //
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     //
//                                                                            //
//   You should have received a copy of the Phorum License                    //
//   along with this program.                                                 //
////////////////////////////////////////////////////////////////////////////////

if(!defined("PHORUM_CONTROL_CENTER")) return;

// remove threads fromlist
if(isset($_POST["delthreads"])){
    foreach($_POST["delthreads"] as $thread){
        phorum_user_unsubscribe( $PHORUM['user']['user_id'], $thread );
    }
}

// change any email settings
if(isset($_POST["sub_type"])){
    foreach($_POST["sub_type"] as $thread=>$type){
        if($type!=$_POST["old_sub_type"][$thread]){
            phorum_user_unsubscribe( $PHORUM['user']['user_id'], $thread );
            phorum_user_subscribe( $PHORUM['user']['user_id'], $_POST["thread_forum_id"][$thread], $thread, $type );
        }
    }
}

// the number of days to show
if (isset($_POST['subdays']) && is_numeric($_POST['subdays'])) {
    $subdays = $_POST['subdays'];
} elseif(isset($PHORUM['args']['subdays']) && !empty($PHORUM["args"]['subdays']) && is_numeric($PHORUM["args"]['subdays'])) {
    $subdays = $PHORUM['args']['subdays'];
} elseif(isset($PHORUM["user"]["settings_data"]["cc_subscriptions_subdays"])) {
    $subdays = $PHORUM["user"]["settings_data"]["cc_subscriptions_subdays"];
} else {
    $subdays = 2;
}
$PHORUM['DATA']['SELECTED'] = $subdays;

// Store current selection for the user.
phorum_user_settings_data_save(array("cc_subscriptions_subdays" => $subdays));

// reading all forums for the current vroot
$forums = phorum_db_get_forums(0, -1, $PHORUM["vroot"]);

// reading all subscriptions to messages in the current vroot.
$forum_ids = array($PHORUM["vroot"]);
foreach ($forums as $forum) { $forum_ids[] = $forum["forum_id"]; }
$subscr_array = phorum_db_get_message_subscriptions($PHORUM['user']['user_id'], $subdays, $forum_ids);

// storage for newflags
$PHORUM['user']['newinfo'] = array();

// go through all subscriptions
$subscr_array_final = array();
unset($subscr_array["forum_ids"]);
foreach($subscr_array as $id => $data)
{
    $data['forum'] = $forums[$data['forum_id']]['name'];
    $data['raw_datestamp'] = $data["modifystamp"];
    $data['datestamp'] = phorum_date($PHORUM["short_date_time"], $data["modifystamp"]);
    $data["URL"]["READ"] = phorum_get_url(PHORUM_FOREIGN_READ_URL, $data["forum_id"], $data["thread"]);
    $data["URL"]["NEWPOST"] = phorum_get_url(PHORUM_FOREIGN_READ_URL, $data["forum_id"], $data["thread"], "gotonewpost");

    if(!empty($data["user_id"])) {
        $data["URL"]["PROFILE"] = phorum_get_url(PHORUM_PROFILE_URL, $data["user_id"]);
        // we don't normally put HTML in this code, but this makes it easier on template builders
        $data["linked_author"] = "<a href=\"".$data["URL"]["PROFILE"]."\">".htmlspecialchars($data["author"])."</a>";
    } elseif(!empty($data["email"])) {
        $data["URL"]["EMAIL"] = phorum_html_encode("mailto:$data[email]");
        // we don't normally put HTML in this code, but this makes it easier on template builders
        $data["linked_author"] = "<a href=\"".$data["URL"]["EMAIL"]."\">".htmlspecialchars($data["author"])."</a>";
    } else {
        $data["linked_author"] = htmlspecialchars($data["author"]);
    }

    $data["subject"]=htmlspecialchars($data["subject"]);

    // Check if there are new messages for the current thread.
    if (! isset($PHORUM['user']['newinfo'][$data["forum_id"]])) {
        $PHORUM['user']['newinfo'][$data["forum_id"]] = null;
        if ($PHORUM['cache_newflags']) {
            $newflagkey = $data["forum_id"]."-".$PHORUM['user']['user_id'];
            $PHORUM['user']['newinfo'][$data["forum_id"]] = phorum_cache_get('newflags',$newflagkey,$forums[$data["forum_id"]]['cache_version']);
        }
        if ($PHORUM['user']['newinfo'][$data["forum_id"]] == null) {
            $PHORUM['user']['newinfo'][$data["forum_id"]] = phorum_db_newflag_get_flags($data["forum_id"]);
            if($PHORUM['cache_newflags']) {
                phorum_cache_put('newflags',$newflagkey,$PHORUM['user']['newinfo'][$data["forum_id"]],86400,$forums[$data["forum_id"]]['cache_version']);
            }
        }
    }
    $new = array();
    foreach ($data["meta"]["message_ids"] as $mid) {
        if (!isset($PHORUM['user']['newinfo'][$data["forum_id"]][$mid]) && $mid > $PHORUM['user']['newinfo'][$data["forum_id"]]['min_id']) {
            $new[] = $mid;
        }
    }

    if (count($new)) {
        $data["new"] = $PHORUM["DATA"]["LANG"]["newflag"];
    }

    $subscr_array_final[] = $data;
}

$PHORUM["DATA"]["HEADING"] = $PHORUM["DATA"]["LANG"]["Subscriptions"];

$PHORUM['DATA']['TOPICS'] = $subscr_array_final;
$template = "cc_subscriptions";

?>
