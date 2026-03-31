<?php

namespace GsNavBookmark\App;

if (! defined('ABSPATH')) {
    exit;
}

final class PageController
{
    const QUERY_VAR = 'gsnav_app';
    const AJAX_ACTION = 'gsnav_get_bing';
    const SAVE_ITEMS_ACTION = 'gsnav_save_desktop_items';
    const DEFAULT_ROUTE_SLUG = 'bookmark';

    private $desktopService;
    private $viewerService;

    public function __construct($desktopService = null, $viewerService = null)
    {
        $this->desktopService = $desktopService ?: new DesktopService();
        $this->viewerService = $viewerService ?: new ViewerService();
    }

    public function register()
    {
        \add_action('init', [self::class, 'registerRewriteRule']);
        \add_filter('query_vars', [$this, 'addQueryVar']);
        \add_action('template_redirect', [$this, 'renderApp']);
        \add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'ajaxGetBingWallpaper']);
        \add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [$this, 'ajaxGetBingWallpaper']);
        \add_action('wp_ajax_' . self::SAVE_ITEMS_ACTION, [$this, 'ajaxSaveDesktopItems']);
    }

    public static function registerRewriteRule()
    {
        $routeSlug = \trim((string) \get_option('gsnav_route_slug', self::DEFAULT_ROUTE_SLUG), '/');

        if ($routeSlug === '') {
            $routeSlug = self::DEFAULT_ROUTE_SLUG;
        }

        \add_rewrite_rule(
            '^' . \preg_quote($routeSlug, '/') . '/?$',
            'index.php?' . self::QUERY_VAR . '=1',
            'top'
        );
    }

    public function addQueryVar($vars)
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    public function renderApp()
    {
        if (! \get_query_var(self::QUERY_VAR)) {
            return;
        }

        $gsnavConfig = $this->buildAppConfig();
        $templatePath = GSNAV_PLUGIN_DIR . 'templates/app.php';

        if (\file_exists($templatePath)) {
            include $templatePath;
        }

        exit;
    }

    public function ajaxGetBingWallpaper()
    {
        $results = [];

        for ($page = 0; $page < 4; $page++) {
            $index = $page * 8;
            $bingApi = 'https://www.bing.com/HPImageArchive.aspx?format=js&idx=' . $index . '&n=8&mkt=zh-CN';
            $response = \wp_remote_get($bingApi, ['timeout' => 10]);

            if (\is_wp_error($response)) {
                continue;
            }

            $body = \wp_remote_retrieve_body($response);
            $data = \json_decode($body, true);

            if (empty($data['images']) || ! \is_array($data['images'])) {
                continue;
            }

            foreach ($data['images'] as $image) {
                $id = 'bing_' . $image['startdate'];

                if (isset($results[$id])) {
                    continue;
                }

                $results[$id] = [
                    'id' => $id,
                    'type' => 'image',
                    'src' => 'https://www.bing.com' . $image['url'],
                    'thumbnail' => 'https://www.bing.com' . $image['urlbase'] . '_800x600.jpg',
                    'user' => $image['copyright'],
                ];
            }
        }

        \wp_send_json_success(\array_values($results));
    }

    public function ajaxSaveDesktopItems()
    {
        if (! \is_user_logged_in()) {
            \wp_send_json_error(
                [
                    'message' => '请先登录后再保存桌面。',
                ],
                403
            );
        }

        $nonce = isset($_POST['nonce']) ? \sanitize_text_field(\wp_unslash($_POST['nonce'])) : '';

        if (! \wp_verify_nonce($nonce, self::SAVE_ITEMS_ACTION)) {
            \wp_send_json_error(
                [
                    'message' => '桌面保存请求已失效，请刷新页面后重试。',
                ],
                403
            );
        }

        $desktopId = isset($_POST['desktopId']) ? (int) $_POST['desktopId'] : 0;
        $itemsJson = isset($_POST['items']) ? \wp_unslash($_POST['items']) : '';
        $itemsData = \json_decode($itemsJson, true);

        if (! \is_array($itemsData)) {
            \wp_send_json_error(
                [
                    'message' => '桌面数据格式错误。',
                ],
                400
            );
        }

        $desktopApps = isset($itemsData['desktopApps']) && \is_array($itemsData['desktopApps']) ? $itemsData['desktopApps'] : [];
        $dockApps = isset($itemsData['dockApps']) && \is_array($itemsData['dockApps']) ? $itemsData['dockApps'] : [];
        $savedPayload = $this->desktopService->saveDesktopItemsForCurrentUser($desktopId, $desktopApps, $dockApps);

        if (\is_wp_error($savedPayload)) {
            \wp_send_json_error(
                [
                    'message' => $savedPayload->get_error_message(),
                ],
                400
            );
        }

        \wp_send_json_success(
            [
                'desktopPayload' => $savedPayload,
            ]
        );
    }

    private function buildAppConfig()
    {
        $desktopPayload = $this->desktopService->getDesktopPayloadForCurrentVisitor();

        return [
            'unsplashKey' => (string) \trim(\get_option('gsnav_unsplash_key', '')),
            'pixabayKey' => (string) \trim(\get_option('gsnav_pixabay_key', '')),
            'pexelsKey' => (string) \trim(\get_option('gsnav_pexels_key', '')),
            'ajaxUrl' => \admin_url('admin-ajax.php'),
            'viewer' => $this->viewerService->getViewerPayload(),
            'desktopPayload' => $desktopPayload,
            'canSaveDesktop' => \is_user_logged_in() && ! empty($desktopPayload['desktop']['id']) && $desktopPayload['desktop']['scope'] === 'user_private',
            'desktopNonce' => \is_user_logged_in() ? \wp_create_nonce(self::SAVE_ITEMS_ACTION) : '',
        ];
    }
}
