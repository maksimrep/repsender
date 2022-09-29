<?php
/*
 * Plugin Name: Rep Sender
 * Description: WordPress plugin for group mailing
 * Author: Maksym Repetskyi
 * Version: 0.0.1
 * Text Domain:  repsender
 * Domain Path:  /languages/
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
};

//Text Domain variable
define('TEXTDOMAIN', 'repsender');

require "admin/wp_menu.php";