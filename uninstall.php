<?php
/**
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'wp-telegram-bridge.php';
require_once WTB_PLUGIN_DIR . 'includes/class-activator.php';

// Delete all plugin data
WTB_Activator::uninstall();
