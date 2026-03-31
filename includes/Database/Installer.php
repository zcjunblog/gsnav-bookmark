<?php

namespace GsNavBookmark\Database;

use GsNavBookmark\App\PageController;

if (! defined('ABSPATH')) {
    exit;
}

final class Installer
{
    const DB_VERSION = '1.0.0';

    const DEFAULT_OPTIONS = [
        'gsnav_unsplash_key' => '',
        'gsnav_pixabay_key' => '',
        'gsnav_pexels_key' => '',
        'gsnav_default_desktop_id' => 0,
        'gsnav_allow_guest_mode' => 0,
        'gsnav_guest_can_customize' => 0,
        'gsnav_route_slug' => 'bookmark',
        'gsnav_weather_mode' => 'open-meteo',
        'gsnav_enable_wallpaper_proxy' => 0,
    ];

    public static function register()
    {
        \add_action('plugins_loaded', [self::class, 'maybeUpgrade']);
    }

    public static function activate()
    {
        self::installOrUpdate();
        PageController::registerRewriteRule();
        \flush_rewrite_rules();
    }

    public static function deactivate()
    {
        \flush_rewrite_rules();
    }

    public static function maybeUpgrade()
    {
        if (! self::needsUpgrade()) {
            return;
        }

        self::installOrUpdate();
    }

    private static function installOrUpdate()
    {
        self::ensureDefaultOptions();
        self::createOrUpdateTables();
        \update_option('gsnav_db_version', self::DB_VERSION);
    }

    private static function ensureDefaultOptions()
    {
        foreach (self::DEFAULT_OPTIONS as $optionName => $defaultValue) {
            if (\get_option($optionName, null) === null) {
                \add_option($optionName, $defaultValue);
            }
        }
    }

    private static function needsUpgrade()
    {
        $currentVersion = (string) \get_option('gsnav_db_version', '');

        if ($currentVersion !== self::DB_VERSION) {
            return true;
        }

        foreach (self::getRequiredTables() as $tableName) {
            if (! self::tableExists($tableName)) {
                return true;
            }
        }

        return false;
    }

    private static function createOrUpdateTables()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $desktopsTable = self::getDesktopsTableName();
        $itemsTable = self::getItemsTableName();

        $schemas = [
            "CREATE TABLE {$desktopsTable} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NULL DEFAULT NULL,
                scope VARCHAR(20) NOT NULL DEFAULT 'user_private',
                slug VARCHAR(100) NULL DEFAULT NULL,
                name VARCHAR(120) NOT NULL,
                description TEXT NULL DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                settings_json LONGTEXT NULL DEFAULT NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                created_by BIGINT UNSIGNED NULL DEFAULT NULL,
                updated_by BIGINT UNSIGNED NULL DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY uniq_slug (slug),
                KEY idx_user_scope (user_id, scope),
                KEY idx_scope_default (scope, is_default)
            ) {$charsetCollate};",
            "CREATE TABLE {$itemsTable} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                desktop_id BIGINT UNSIGNED NOT NULL,
                parent_item_id BIGINT UNSIGNED NULL DEFAULT NULL,
                area VARCHAR(20) NOT NULL DEFAULT 'desktop',
                item_type VARCHAR(20) NOT NULL DEFAULT 'link',
                title VARCHAR(120) NOT NULL,
                url TEXT NULL DEFAULT NULL,
                icon_type VARCHAR(20) NOT NULL DEFAULT 'remixicon',
                icon_value VARCHAR(255) NULL DEFAULT NULL,
                color VARCHAR(32) NULL DEFAULT NULL,
                open_mode VARCHAR(20) NOT NULL DEFAULT 'new_tab',
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                size VARCHAR(20) NOT NULL DEFAULT '1x1',
                meta_json LONGTEXT NULL DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY idx_desktop_area_order (desktop_id, area, sort_order),
                KEY idx_parent_item (parent_item_id),
                KEY idx_item_type (item_type)
            ) {$charsetCollate};",
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ($schemas as $schema) {
            \dbDelta($schema);
        }
    }

    private static function getRequiredTables()
    {
        return [
            self::getDesktopsTableName(),
            self::getItemsTableName(),
        ];
    }

    private static function getDesktopsTableName()
    {
        global $wpdb;

        return $wpdb->prefix . 'gsnav_desktops';
    }

    private static function getItemsTableName()
    {
        global $wpdb;

        return $wpdb->prefix . 'gsnav_items';
    }

    private static function tableExists($tableName)
    {
        global $wpdb;

        $sql = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tableName));
        $foundTable = $wpdb->get_var($sql);

        return $foundTable === $tableName;
    }
}
