<?php

// Crontab:
//
// * * * * * cd ~/public_html/wp-content/plugins/rowing-chat-automation/scripts/ && /usr/local/bin/ea-php70 ~/wp-cli.phar eval-file processor.php > /dev/null 2>&1

require_once(dirname(__FILE__) . '/../vendor/autoload.php');
require_once(dirname(__FILE__) . '/../config.php');

use \RowingChatAutomation\ProcessingTask;

$base_dir = ABSPATH . 'wp-content/plugins/rowing-chat-automation';
$lockfile = __FILE__ . '.lock';

if (file_exists($lockfile)) {
    printf("Exiting. Instance already running%s", PHP_EOL);
    exit();
}

touch($lockfile);

echo "Processing...\n";

$task = new ProcessingTask();
$task->run();

unlink($lockfile);

echo "Finished...\n";
