<?php

require_once(dirname(__FILE__) . '/../vendor/autoload.php');
require_once(dirname(__FILE__) . '/../config.php');

use \RowingChatAutomation\ProcessingTask;
use \RowingChatAutomation\Helper;

// $token = get_transient('soundcloud_token');
// $result = Helper::sc()->refresh_token($token->refresh_token);
// var_dump($result);
// set_transient('soundcloud_token', $result);

$upload_ids = [50110]; // Does not seem to work withmultiple though?

// $sc_metadata = get_post_meta($upload_id, 'sc_metadata', true);
// var_dump($sc_metadata); die();

foreach ($upload_ids as $upload_id) {
    $task = new ProcessingTask();
    $state = $task->process_upload($upload_id);
    var_dump($state);
}
