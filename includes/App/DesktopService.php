<?php

namespace GsNavBookmark\App;

if (! defined('ABSPATH')) {
    exit;
}

final class DesktopService
{
    const DEFAULT_DESKTOP_LOCK_OPTION = 'gsnav_seed_default_desktop_lock';
    const DEFAULT_DESKTOP_SLUG = 'system-default';

    private $repository;

    public function __construct($repository = null)
    {
        $this->repository = $repository ?: new DesktopRepository();
    }

    public function getDesktopPayloadForCurrentVisitor()
    {
        $desktop = $this->resolveDesktopForCurrentVisitor();

        if (! $desktop || empty($desktop['id'])) {
            return $this->buildEmptyPayload();
        }

        $items = $this->getItemsForDesktop($desktop);

        return [
            'desktop' => [
                'id' => (int) $desktop['id'],
                'name' => $desktop['name'],
                'scope' => $desktop['scope'],
            ],
            'settings' => $this->normalizeSettings($desktop),
            'desktopApps' => $this->buildAreaItems($items, 'desktop'),
            'dockApps' => $this->buildAreaItems($items, 'dock'),
            'search' => $this->buildSearchConfig($desktop),
        ];
    }

    public function getDefaultDesktopPayload()
    {
        return $this->getDesktopPayloadForCurrentVisitor();
    }

    public function saveDesktopItemsForCurrentUser($desktopId, $desktopApps, $dockApps)
    {
        $userId = (int) \get_current_user_id();

        if ($userId <= 0) {
            return new \WP_Error('gsnav_auth_required', '请先登录后再保存桌面。');
        }

        $desktopId = (int) $desktopId;

        if ($desktopId <= 0) {
            return new \WP_Error('gsnav_invalid_desktop', '桌面不存在。');
        }

        $desktop = $this->repository->findUserDesktopById($userId, $desktopId);

        if (! $desktop) {
            return new \WP_Error('gsnav_forbidden_desktop', '当前桌面不属于该用户。');
        }

        $normalizedDesktopItems = $this->normalizeSubmittedItems($desktopApps, 'desktop');

        if (\is_wp_error($normalizedDesktopItems)) {
            return $normalizedDesktopItems;
        }

        $normalizedDockItems = $this->normalizeSubmittedItems($dockApps, 'dock');

        if (\is_wp_error($normalizedDockItems)) {
            return $normalizedDockItems;
        }

        $this->repository->beginTransaction();

        try {
            $deleted = $this->repository->deleteItemsByDesktopId($desktopId);

            if ($deleted === false) {
                throw new \RuntimeException('Failed to clear desktop items.');
            }

            $this->persistNormalizedItems($desktopId, $normalizedDesktopItems);
            $this->persistNormalizedItems($desktopId, $normalizedDockItems);
            $this->repository->updateUserActiveDesktopId($userId, $desktopId);
            $this->repository->commit();
        } catch (\Exception $exception) {
            $this->repository->rollback();

            return new \WP_Error('gsnav_save_failed', '保存桌面失败，请稍后重试。');
        }

        return $this->getDesktopPayloadForCurrentVisitor();
    }

    private function resolveDesktopForCurrentVisitor()
    {
        if (\is_user_logged_in()) {
            $desktop = $this->resolveDesktopForUser(\get_current_user_id());

            if ($desktop) {
                return $desktop;
            }
        }

        return $this->resolveSystemDefaultDesktop();
    }

    private function resolveDesktopForUser($userId)
    {
        if ($userId <= 0) {
            return $this->resolveSystemDefaultDesktop();
        }

        $activeDesktopId = $this->repository->getUserActiveDesktopId($userId);

        if ($activeDesktopId > 0) {
            $desktop = $this->repository->findUserDesktopById($userId, $activeDesktopId);

            if ($desktop) {
                return $desktop;
            }
        }

        $desktop = $this->repository->findDefaultDesktopForUser($userId);

        if ($desktop) {
            $this->repository->updateUserActiveDesktopId($userId, $desktop['id']);

            return $desktop;
        }

        $desktop = $this->repository->findFirstDesktopForUser($userId);

        if ($desktop) {
            $this->repository->updateUserActiveDesktopId($userId, $desktop['id']);

            return $desktop;
        }

        $desktop = $this->createInitialDesktopForUser($userId);

        if ($desktop) {
            $this->repository->updateUserActiveDesktopId($userId, $desktop['id']);

            return $desktop;
        }

        return $this->resolveSystemDefaultDesktop();
    }

    private function resolveSystemDefaultDesktop()
    {
        $desktop = $this->repository->findConfiguredDefaultDesktop();

        if ($desktop) {
            return $desktop;
        }

        $desktop = $this->repository->findSystemDefaultDesktop();

        if ($desktop) {
            $this->repository->updateDefaultDesktopId($desktop['id']);

            return $desktop;
        }

        $desktop = $this->createOrGetSeedDesktop();

        if ($desktop) {
            $this->repository->updateDefaultDesktopId($desktop['id']);
        }

        return $desktop;
    }

    private function createInitialDesktopForUser($userId)
    {
        $sourceDesktop = $this->resolveSystemDefaultDesktop();

        if (! $sourceDesktop || empty($sourceDesktop['id'])) {
            return null;
        }

        $sourceItems = $this->getItemsForDesktop($sourceDesktop);
        $settingsJson = ! empty($sourceDesktop['settings_json'])
            ? $sourceDesktop['settings_json']
            : \wp_json_encode($this->getDefaultSettings());

        $desktopId = $this->repository->createDesktop(
            [
                'user_id' => $userId,
                'scope' => 'user_private',
                'slug' => null,
                'name' => $this->buildUserDesktopName($userId),
                'description' => '由系统默认桌面初始化',
                'status' => 'active',
                'is_default' => 1,
                'settings_json' => $settingsJson,
                'version' => 1,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );

        if ($desktopId <= 0) {
            return null;
        }

        $this->cloneDesktopItems($sourceItems, $desktopId);

        return $this->repository->findDesktopById($desktopId);
    }

    private function createOrGetSeedDesktop()
    {
        $hasLock = \add_option(self::DEFAULT_DESKTOP_LOCK_OPTION, (string) \time(), '', 'no');

        if ($hasLock) {
            $desktopId = $this->repository->createDesktop(
                [
                    'user_id' => 0,
                    'scope' => 'system_default',
                    'slug' => self::DEFAULT_DESKTOP_SLUG,
                    'name' => '默认桌面',
                    'description' => 'GsNav 默认桌面',
                    'status' => 'active',
                    'is_default' => 1,
                    'settings_json' => \wp_json_encode($this->getDefaultSettings()),
                    'version' => 1,
                    'created_by' => 0,
                    'updated_by' => 0,
                ]
            );

            \delete_option(self::DEFAULT_DESKTOP_LOCK_OPTION);

            if ($desktopId > 0) {
                $desktop = $this->repository->findDesktopById($desktopId);

                if ($desktop) {
                    return $desktop;
                }
            }
        }

        return $this->waitForSystemDefaultDesktop();
    }

    private function getItemsForDesktop($desktop)
    {
        $desktopId = isset($desktop['id']) ? (int) $desktop['id'] : 0;
        $scope = isset($desktop['scope']) ? $desktop['scope'] : '';

        if ($desktopId <= 0) {
            return [];
        }

        $items = $this->repository->findItemsByDesktopId($desktopId);

        if (! empty($items)) {
            return $items;
        }

        if ($scope !== 'system_default') {
            return [];
        }

        return $this->ensureSeedItems($desktopId);
    }

    private function ensureSeedItems($desktopId)
    {
        $items = $this->repository->findItemsByDesktopId($desktopId);

        if (! empty($items)) {
            return $items;
        }

        $lockKey = $this->getSeedItemsLockKey($desktopId);
        $hasLock = \add_option($lockKey, (string) \time(), '', 'no');

        if ($hasLock) {
            $items = $this->repository->findItemsByDesktopId($desktopId);

            if (empty($items)) {
                $this->seedDesktopItems($desktopId);
            }

            \delete_option($lockKey);
        }

        return $this->waitForDesktopItems($desktopId);
    }

    private function cloneDesktopItems($sourceItems, $targetDesktopId)
    {
        $folderIdMap = [];

        foreach ($sourceItems as $item) {
            if (! empty($item['parent_item_id'])) {
                continue;
            }

            $newItemId = $this->repository->createItem(
                [
                    'desktop_id' => $targetDesktopId,
                    'parent_item_id' => null,
                    'area' => $item['area'],
                    'item_type' => $item['item_type'],
                    'title' => $item['title'],
                    'url' => $item['url'],
                    'icon_type' => $item['icon_type'],
                    'icon_value' => $item['icon_value'],
                    'color' => $item['color'],
                    'open_mode' => $item['open_mode'],
                    'sort_order' => (int) $item['sort_order'],
                    'size' => $item['size'],
                    'meta_json' => $item['meta_json'],
                ]
            );

            if ($item['item_type'] === 'folder' && $newItemId > 0) {
                $folderIdMap[(int) $item['id']] = $newItemId;
            }
        }

        foreach ($sourceItems as $item) {
            $parentItemId = isset($item['parent_item_id']) ? (int) $item['parent_item_id'] : 0;

            if ($parentItemId <= 0) {
                continue;
            }

            if (! isset($folderIdMap[$parentItemId])) {
                continue;
            }

            $this->repository->createItem(
                [
                    'desktop_id' => $targetDesktopId,
                    'parent_item_id' => $folderIdMap[$parentItemId],
                    'area' => $item['area'],
                    'item_type' => $item['item_type'],
                    'title' => $item['title'],
                    'url' => $item['url'],
                    'icon_type' => $item['icon_type'],
                    'icon_value' => $item['icon_value'],
                    'color' => $item['color'],
                    'open_mode' => $item['open_mode'],
                    'sort_order' => (int) $item['sort_order'],
                    'size' => $item['size'],
                    'meta_json' => $item['meta_json'],
                ]
            );
        }
    }

    private function waitForSystemDefaultDesktop()
    {
        $attempt = 0;

        while ($attempt < 5) {
            $desktop = $this->repository->findSystemDefaultDesktop();

            if ($desktop) {
                return $desktop;
            }

            $attempt++;
            \usleep(100000);
        }

        return null;
    }

    private function waitForDesktopItems($desktopId)
    {
        $attempt = 0;

        while ($attempt < 5) {
            $items = $this->repository->findItemsByDesktopId($desktopId);

            if (! empty($items)) {
                return $items;
            }

            $attempt++;
            \usleep(100000);
        }

        return [];
    }

    private function getSeedItemsLockKey($desktopId)
    {
        return 'gsnav_seed_items_lock_' . (int) $desktopId;
    }

    private function buildUserDesktopName($userId)
    {
        $user = \get_userdata($userId);

        if ($user && ! empty($user->display_name)) {
            return $user->display_name . '的桌面';
        }

        return '我的桌面';
    }

    private function buildEmptyPayload()
    {
        return [
            'desktop' => [
                'id' => 0,
                'name' => '默认桌面',
                'scope' => 'system_default',
            ],
            'settings' => $this->getDefaultSettings(),
            'desktopApps' => [],
            'dockApps' => [],
            'search' => [
                'defaultEngineId' => 'baidu',
                'engines' => $this->getDefaultEngines(),
            ],
        ];
    }

    private function normalizeSubmittedItems($items, $area)
    {
        if (! \is_array($items)) {
            return new \WP_Error('gsnav_invalid_items', '桌面项目数据格式错误。');
        }

        $normalizedItems = [];

        foreach ($items as $index => $item) {
            if (! \is_array($item)) {
                return new \WP_Error('gsnav_invalid_item', '桌面项目格式错误。');
            }

            $normalizedItem = $this->normalizeSubmittedItem($item, $area, false, (int) $index);

            if (\is_wp_error($normalizedItem)) {
                return $normalizedItem;
            }

            $normalizedItems[] = $normalizedItem;
        }

        return $normalizedItems;
    }

    private function normalizeSubmittedItem($item, $area, $isChild, $sortOrder)
    {
        $rawType = isset($item['type']) ? (string) $item['type'] : 'app';
        $itemType = $rawType === 'folder' ? 'folder' : 'link';
        $title = isset($item['name']) ? \sanitize_text_field($item['name']) : '';
        $icon = isset($item['icon']) ? \sanitize_text_field($item['icon']) : '';
        $color = isset($item['color']) ? \sanitize_text_field($item['color']) : '';
        $url = isset($item['url']) ? \esc_url_raw($item['url']) : '';

        if ($title === '') {
            $title = $itemType === 'folder' ? '新建文件夹' : '新建链接';
        }

        if ($isChild && $itemType === 'folder') {
            return new \WP_Error('gsnav_nested_folder_forbidden', '文件夹内不允许继续创建文件夹。');
        }

        if ($itemType !== 'folder' && $url === '') {
            return new \WP_Error('gsnav_invalid_url', '链接地址不能为空。');
        }

        $normalizedItem = [
            'item_type' => $itemType,
            'title' => $title,
            'url' => $itemType === 'folder' ? null : $url,
            'icon_type' => 'remixicon',
            'icon_value' => $icon !== '' ? $icon : ($itemType === 'folder' ? 'ri-folder-3-fill' : 'ri-links-fill'),
            'color' => $color !== '' ? $color : ($itemType === 'folder' ? 'rgba(255,255,255,0.25)' : '#4F46E5'),
            'open_mode' => 'new_tab',
            'size' => '1x1',
            'meta_json' => null,
            'area' => $isChild ? 'folder' : $area,
            'sort_order' => $sortOrder,
            'children' => [],
        ];

        if ($itemType === 'folder') {
            $children = isset($item['children']) && \is_array($item['children']) ? $item['children'] : [];

            foreach ($children as $childIndex => $child) {
                if (! \is_array($child)) {
                    return new \WP_Error('gsnav_invalid_child_item', '文件夹子项目格式错误。');
                }

                $normalizedChild = $this->normalizeSubmittedItem($child, $area, true, (int) $childIndex);

                if (\is_wp_error($normalizedChild)) {
                    return $normalizedChild;
                }

                $normalizedItem['children'][] = $normalizedChild;
            }
        }

        return $normalizedItem;
    }

    private function persistNormalizedItems($desktopId, $items)
    {
        foreach ($items as $item) {
            $parentItemId = null;

            if ($item['item_type'] === 'folder') {
                $parentItemId = $this->repository->createItem(
                    [
                        'desktop_id' => $desktopId,
                        'parent_item_id' => null,
                        'area' => $item['area'],
                        'item_type' => 'folder',
                        'title' => $item['title'],
                        'url' => null,
                        'icon_type' => $item['icon_type'],
                        'icon_value' => $item['icon_value'],
                        'color' => $item['color'],
                        'open_mode' => $item['open_mode'],
                        'sort_order' => $item['sort_order'],
                        'size' => $item['size'],
                        'meta_json' => $item['meta_json'],
                    ]
                );

                if ($parentItemId <= 0) {
                    throw new \RuntimeException('Failed to create folder item.');
                }

                foreach ($item['children'] as $child) {
                    $childItemId = $this->repository->createItem(
                        [
                            'desktop_id' => $desktopId,
                            'parent_item_id' => $parentItemId,
                            'area' => 'folder',
                            'item_type' => $child['item_type'],
                            'title' => $child['title'],
                            'url' => $child['url'],
                            'icon_type' => $child['icon_type'],
                            'icon_value' => $child['icon_value'],
                            'color' => $child['color'],
                            'open_mode' => $child['open_mode'],
                            'sort_order' => $child['sort_order'],
                            'size' => $child['size'],
                            'meta_json' => $child['meta_json'],
                        ]
                    );

                    if ($childItemId <= 0) {
                        throw new \RuntimeException('Failed to create folder child item.');
                    }
                }

                continue;
            }

            $itemId = $this->repository->createItem(
                [
                    'desktop_id' => $desktopId,
                    'parent_item_id' => null,
                    'area' => $item['area'],
                    'item_type' => $item['item_type'],
                    'title' => $item['title'],
                    'url' => $item['url'],
                    'icon_type' => $item['icon_type'],
                    'icon_value' => $item['icon_value'],
                    'color' => $item['color'],
                    'open_mode' => $item['open_mode'],
                    'sort_order' => $item['sort_order'],
                    'size' => $item['size'],
                    'meta_json' => $item['meta_json'],
                ]
            );

            if ($itemId <= 0) {
                throw new \RuntimeException('Failed to create desktop item.');
            }
        }
    }

    private function seedDesktopItems($desktopId)
    {
        $commonFolderId = $this->repository->createItem(
            [
                'desktop_id' => $desktopId,
                'parent_item_id' => null,
                'area' => 'desktop',
                'item_type' => 'folder',
                'title' => '常用',
                'url' => null,
                'icon_type' => 'remixicon',
                'icon_value' => 'ri-folder-5-fill',
                'color' => 'rgba(255,255,255,0.25)',
                'open_mode' => 'new_tab',
                'sort_order' => 0,
                'size' => '1x1',
                'meta_json' => null,
            ]
        );

        if ($commonFolderId <= 0) {
            return;
        }

        $items = [
            [
                'desktop_id' => $desktopId,
                'parent_item_id' => null,
                'area' => 'desktop',
                'item_type' => 'link',
                'title' => 'Bilibili',
                'url' => 'https://www.bilibili.com',
                'icon_type' => 'remixicon',
                'icon_value' => 'ri-bilibili-fill',
                'color' => '#00A1D6',
                'open_mode' => 'new_tab',
                'sort_order' => 1,
                'size' => '1x1',
                'meta_json' => null,
            ],
            [
                'desktop_id' => $desktopId,
                'parent_item_id' => null,
                'area' => 'dock',
                'item_type' => 'link',
                'title' => 'Google',
                'url' => 'https://www.google.com',
                'icon_type' => 'remixicon',
                'icon_value' => 'ri-google-fill',
                'color' => '#4285F4',
                'open_mode' => 'new_tab',
                'sort_order' => 0,
                'size' => '1x1',
                'meta_json' => null,
            ],
            [
                'desktop_id' => $desktopId,
                'parent_item_id' => $commonFolderId,
                'area' => 'folder',
                'item_type' => 'link',
                'title' => 'WordPress',
                'url' => \home_url('/'),
                'icon_type' => 'remixicon',
                'icon_value' => 'ri-wordpress-fill',
                'color' => '#21759B',
                'open_mode' => 'new_tab',
                'sort_order' => 0,
                'size' => '1x1',
                'meta_json' => null,
            ],
            [
                'desktop_id' => $desktopId,
                'parent_item_id' => $commonFolderId,
                'area' => 'folder',
                'item_type' => 'link',
                'title' => 'GitHub',
                'url' => 'https://github.com',
                'icon_type' => 'remixicon',
                'icon_value' => 'ri-github-fill',
                'color' => '#24292F',
                'open_mode' => 'new_tab',
                'sort_order' => 1,
                'size' => '1x1',
                'meta_json' => null,
            ],
        ];

        foreach ($items as $item) {
            $this->repository->createItem($item);
        }
    }

    private function buildAreaItems($items, $area)
    {
        $folderChildrenMap = [];
        $topLevelItems = [];

        foreach ($items as $item) {
            if ($item['area'] === 'folder' && ! empty($item['parent_item_id'])) {
                $parentId = (int) $item['parent_item_id'];

                if (! isset($folderChildrenMap[$parentId])) {
                    $folderChildrenMap[$parentId] = [];
                }

                $folderChildrenMap[$parentId][] = $this->mapItem($item);
            }
        }

        foreach ($items as $item) {
            if ($item['area'] !== $area) {
                continue;
            }

            $mappedItem = $this->mapItem($item);

            if ($mappedItem['type'] === 'folder') {
                $mappedItem['children'] = isset($folderChildrenMap[$mappedItem['id']]) ? $folderChildrenMap[$mappedItem['id']] : [];
            }

            $topLevelItems[] = $mappedItem;
        }

        return $topLevelItems;
    }

    private function mapItem($item)
    {
        $mappedItem = [
            'id' => (int) $item['id'],
            'name' => $item['title'],
            'type' => $item['item_type'] === 'folder' ? 'folder' : 'app',
            'icon' => $item['icon_value'] ? $item['icon_value'] : 'ri-links-fill',
            'color' => $item['color'] ? $item['color'] : '#4F46E5',
            'url' => $item['url'],
        ];

        if ($mappedItem['type'] === 'folder') {
            $mappedItem['children'] = [];
        }

        return $mappedItem;
    }

    private function buildSearchConfig($desktop)
    {
        $settings = $this->normalizeSettings($desktop);
        $search = isset($settings['search']) && \is_array($settings['search']) ? $settings['search'] : [];
        $engines = isset($search['engines']) && \is_array($search['engines']) ? $search['engines'] : $this->getDefaultEngines();
        $defaultEngineId = isset($search['defaultEngineId']) ? $search['defaultEngineId'] : 'baidu';

        return [
            'defaultEngineId' => $defaultEngineId,
            'engines' => $engines,
        ];
    }

    private function normalizeSettings($desktop)
    {
        $settings = [];

        if (! empty($desktop['settings_json'])) {
            $decoded = \json_decode($desktop['settings_json'], true);

            if (\is_array($decoded)) {
                $settings = $decoded;
            }
        }

        return \array_replace_recursive($this->getDefaultSettings(), $settings);
    }

    private function getDefaultSettings()
    {
        return [
            'showDock' => true,
            'bgBlur' => 0,
            'simpleMode' => false,
            'wallpaper' => [
                'source' => 'bing',
                'category' => 'backgrounds',
                'current' => null,
            ],
            'search' => [
                'defaultEngineId' => 'baidu',
                'engines' => $this->getDefaultEngines(),
            ],
        ];
    }

    private function getDefaultEngines()
    {
        return [
            [
                'id' => 'baidu',
                'name' => '百度',
                'icon' => 'ri-baidu-fill',
                'url' => 'https://www.baidu.com/s?wd=',
            ],
            [
                'id' => 'google',
                'name' => 'Google',
                'icon' => 'ri-google-fill',
                'url' => 'https://www.google.com/search?q=',
            ],
            [
                'id' => 'bing',
                'name' => 'Bing',
                'icon' => 'ri-microsoft-fill',
                'url' => 'https://www.bing.com/search?q=',
            ],
        ];
    }
}
