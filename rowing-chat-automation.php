<?php
/*
 * Plugin Name:      Rowing Chat Automation
 * Plugin URI:       https://github.com/shakib6472/
 * Description:      A plugin to automate functions to wordpress from youtube & soundcloud
 * Version:          1.3.0
* Requires at least: 5.2
* Requires PHP:      7.2
* Author:            Shakib Shown
* Author URI:        https://github.com/shakib6472/
* License:           GPL v2 or later
* License URI:       https://www.gnu.org/licenses/gpl-2.0.html
* Text Domain:       rca
* Domain Path:       /languages
*/

namespace RowingChatAutomationPlugin;  


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
} 

// Load Composer's autoloader
require_once('vendor/autoload.php'); 
require_once('config.php');

function init()
{
    $plugin = new \RowingChatAutomation\Plugin(); 
    $plugin->init();
}
add_action('plugins_loaded', __NAMESPACE__ . '\init'); 
 