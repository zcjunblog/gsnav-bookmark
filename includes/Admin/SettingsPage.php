<?php

namespace GsNavBookmark\Admin;

if (! defined('ABSPATH')) {
    exit;
}

final class SettingsPage
{
    const OPTION_GROUP = 'gsnav_options_group';
    const PAGE_SLUG = 'gsnav-settings';
    const SECTION_ID = 'gsnav_main_section';

    public function register()
    {
        \add_action('admin_menu', [$this, 'addAdminMenu']);
        \add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addAdminMenu()
    {
        \add_options_page(
            'GsNav 设置',
            'GsNav 设置',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderSettingsPage']
        );
    }

    public function registerSettings()
    {
        $fields = [
            'gsnav_unsplash_key' => [
                'label' => 'Unsplash Key (静态图)',
                'description' => '',
            ],
            'gsnav_pixabay_key' => [
                'label' => 'Pixabay Key (动态视频)',
                'description' => '',
            ],
            'gsnav_pexels_key' => [
                'label' => 'Pexels Key (动态视频)',
                'description' => '前往 <a href="https://www.pexels.com/api/" target="_blank" rel="noopener noreferrer">Pexels API</a> 申请免费 Key。',
            ],
        ];

        foreach (\array_keys($fields) as $optionName) {
            \register_setting(
                self::OPTION_GROUP,
                $optionName,
                ['sanitize_callback' => 'sanitize_text_field']
            );
        }

        \add_settings_section(
            self::SECTION_ID,
            'API 配置中心',
            null,
            self::PAGE_SLUG
        );

        foreach ($fields as $optionName => $field) {
            \add_settings_field(
                $optionName,
                $field['label'],
                [$this, 'renderTextField'],
                self::PAGE_SLUG,
                self::SECTION_ID,
                [
                    'option_name' => $optionName,
                    'description' => $field['description'],
                ]
            );
        }
    }

    public function renderTextField($args)
    {
        $optionName = isset($args['option_name']) ? (string) $args['option_name'] : '';
        $description = isset($args['description']) ? (string) $args['description'] : '';
        $value = (string) \get_option($optionName, '');

        echo '<input type="text" name="' . \esc_attr($optionName) . '" value="' . \esc_attr($value) . '" class="regular-text">';

        if ($description !== '') {
            echo '<p class="description">' . \wp_kses_post($description) . '</p>';
        }
    }

    public function renderSettingsPage()
    {
        ?>
        <div class="wrap">
            <h1>GsNav 导航页设置</h1>
            <form method="post" action="options.php">
                <?php
                \settings_fields(self::OPTION_GROUP);
                \do_settings_sections(self::PAGE_SLUG);
                \submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
