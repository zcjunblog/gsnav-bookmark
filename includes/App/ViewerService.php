<?php

namespace GsNavBookmark\App;

if (! defined('ABSPATH')) {
    exit;
}

final class ViewerService
{
    public function getViewerPayload()
    {
        $bookmarkUrl = $this->getBookmarkUrl();

        if (! \is_user_logged_in()) {
            return [
                'isLoggedIn' => false,
                'loginUrl' => \wp_login_url($bookmarkUrl),
                'logoutUrl' => '',
                'profileUrl' => '',
                'user' => null,
            ];
        }

        $user = \wp_get_current_user();
        $avatarUrl = \get_avatar_url(
            $user->ID,
            [
                'size' => 96,
            ]
        );

        return [
            'isLoggedIn' => true,
            'loginUrl' => '',
            'logoutUrl' => \wp_logout_url($bookmarkUrl),
            'profileUrl' => \get_edit_profile_url($user->ID),
            'user' => [
                'id' => (int) $user->ID,
                'displayName' => $user->display_name ? $user->display_name : $user->user_login,
                'avatarUrl' => $avatarUrl ? $avatarUrl : '',
            ],
        ];
    }

    private function getBookmarkUrl()
    {
        $routeSlug = \trim((string) \get_option('gsnav_route_slug', PageController::DEFAULT_ROUTE_SLUG), '/');

        if ($routeSlug === '') {
            $routeSlug = PageController::DEFAULT_ROUTE_SLUG;
        }

        return \home_url('/' . $routeSlug . '/');
    }
}
