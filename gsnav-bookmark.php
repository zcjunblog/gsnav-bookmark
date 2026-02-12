<?php
/**
 * Plugin Name: GsNav Bookmark (Modular Pro)
 * Description: 模块化重构版 + 天气集成
 * Version: 3.0.0
 * Author: Frontend Master
 */

if (!defined('ABSPATH')) exit;

// 定义插件路径常量，方便引用
define('GSNAV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GSNAV_PLUGIN_DIR', plugin_dir_path(__FILE__));

class GsNav_App {
    public function __construct() {
        add_action('init', [$this, 'add_rewrite_rule']);
        add_filter('query_vars', [$this, 'add_query_var']);
        add_action('template_redirect', [$this, 'render_app']);
    }

    public function add_rewrite_rule() {
        add_rewrite_rule('^bookmark/?$', 'index.php?gsnav_app=1', 'top');
    }

    public function add_query_var($vars) {
        $vars[] = 'gsnav_app';
        return $vars;
    }

    public function render_app() {
    if (get_query_var('gsnav_app')) {
        $template_path = GSNAV_PLUGIN_DIR . 'templates/app.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // 如果文件不存在，给出一个友好的提示，而不是报错
            wp_die("错误：找不到模板文件。请检查 " . $template_path . " 是否存在。", "文件丢失");
        }
        exit;
    }
}
}

new GsNav_App();
register_activation_hook(__FILE__, function() {
    $app = new GsNav_App();
    $app->add_rewrite_rule();
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function() { flush_rewrite_rules(); });