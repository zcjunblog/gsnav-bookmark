<?php
/**
 * Plugin Name: GsNav Bookmark (Modular Pro)
 * Description: 模块化重构版 + 动态视频(Pixabay) + 必应壁纸 + 无限加载
 * Version: 5.0.0
 * Author: Frontend Master
 */

if (! defined('ABSPATH')) {
    exit;
}

define('GSNAV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GSNAV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GSNAV_PLUGIN_FILE', __FILE__);

require_once GSNAV_PLUGIN_DIR . 'includes/Admin/SettingsPage.php';
require_once GSNAV_PLUGIN_DIR . 'includes/App/DesktopRepository.php';
require_once GSNAV_PLUGIN_DIR . 'includes/App/DesktopService.php';
require_once GSNAV_PLUGIN_DIR . 'includes/App/ViewerService.php';
require_once GSNAV_PLUGIN_DIR . 'includes/App/PageController.php';
require_once GSNAV_PLUGIN_DIR . 'includes/Database/Installer.php';
require_once GSNAV_PLUGIN_DIR . 'includes/Plugin.php';

register_activation_hook(__FILE__, ['GsNavBookmark\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['GsNavBookmark\\Plugin', 'deactivate']);

function gsnav_bookmark()
{
    static $plugin = null;

    if ($plugin === null) {
        $plugin = new GsNavBookmark\Plugin();
    }

    return $plugin;
}

gsnav_bookmark()->register();
