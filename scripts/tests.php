<?php

require_once(dirname(__FILE__) . '/../vendor/autoload.php');
require_once(dirname(__FILE__) . '/../config.php');

$upload_id = 49360;

$post = get_post($upload_id);

$sc_metadata = get_post_meta($upload_id, 'sc_metadata', true);

var_dump($sc_metadata); die();

$sc_id = $sc_metadata->id;

// $sc_metadata = Helper::sc()->get_track($sc_id);



// $sc_response = \RowingChatAutomation\Helper::sc()->update_track($sc_id, [
//     'track[title]' => 'EXR erg video games',
//     'track[sharing]' => 'private',
// ]);

// var_dump($sc_response);
