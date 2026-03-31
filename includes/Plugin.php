<?php

namespace GsNavBookmark;

use GsNavBookmark\Admin\SettingsPage;
use GsNavBookmark\App\PageController;
use GsNavBookmark\Database\Installer;

if (! defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    private $settingsPage;

    private $pageController;

    public function __construct($settingsPage = null, $pageController = null)
    {
        $this->settingsPage = $settingsPage ?: new SettingsPage();
        $this->pageController = $pageController ?: new PageController();
    }

    public function register()
    {
        Installer::register();
        $this->settingsPage->register();
        $this->pageController->register();
    }

    public static function activate()
    {
        Installer::activate();
    }

    public static function deactivate()
    {
        Installer::deactivate();
    }
}
