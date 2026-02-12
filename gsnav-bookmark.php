<?php
/**
 * Plugin Name: GsNav Bookmark (Modular Pro)
 * Description: 模块化重构版 + 天气集成 + Unsplash后台配置
 * Version: 3.1.0
 * Author: Frontend Master
 */

if (!defined('ABSPATH')) exit;

define('GSNAV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GSNAV_PLUGIN_DIR', plugin_dir_path(__FILE__));

class GsNav_App {
    public function __construct() {
        add_action('init', [$this, 'add_rewrite_rule']);
        add_filter('query_vars', [$this, 'add_query_var']);
        add_action('template_redirect', [$this, 'render_app']);
        
        // 新增：后台设置菜单
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_rewrite_rule() {
        add_rewrite_rule('^bookmark/?$', 'index.php?gsnav_app=1', 'top');
    }

    public function add_query_var($vars) {
        $vars[] = 'gsnav_app';
        return $vars;
    }

    // 新增：添加设置页面
    public function add_admin_menu() {
        add_options_page(
            'GsNav 设置',
            'GsNav 设置',
            'manage_options',
            'gsnav-settings',
            [$this, 'render_settings_page']
        );
    }

    // 新增：注册设置字段
    public function register_settings() {
        register_setting('gsnav_options_group', 'gsnav_unsplash_key');
        
        add_settings_section(
            'gsnav_main_section',
            'API 配置',
            null,
            'gsnav-settings'
        );

        add_settings_field(
            'gsnav_unsplash_key',
            'Unsplash Access Key',
            [$this, 'render_key_field'],
            'gsnav-settings',
            'gsnav_main_section'
        );
    }

    public function render_key_field() {
        $key = get_option('gsnav_unsplash_key');
        echo '<input type="text" name="gsnav_unsplash_key" value="' . esc_attr($key) . '" class="regular-text">';
        echo '<p class="description">请前往 <a href="https://unsplash.com/developers" target="_blank">Unsplash Developers</a> 申请 Access Key。</p>';
    }

    public function render_settings_page() {
        ?>
<div class="wrap">
    <h1>GsNav 导航页设置</h1>
    <form method="post" action="options.php">
        <?php
                settings_fields('gsnav_options_group');
                do_settings_sections('gsnav-settings');
                submit_button();
                ?>
    </form>
</div>
<?php
    }

    public function render_app() {
        if (get_query_var('gsnav_app')) {
            // 获取 Key 并传递给模板
            $unsplash_key = get_option('gsnav_unsplash_key', '');
            
            $template_path = GSNAV_PLUGIN_DIR . 'templates/app.php';
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                wp_die("错误：找不到模板文件。", "文件丢失");
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