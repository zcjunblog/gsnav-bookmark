<?php

namespace GsNavBookmark\App;

if (! defined('ABSPATH')) {
    exit;
}

final class DesktopRepository
{
    public function findConfiguredDefaultDesktop()
    {
        $desktopId = (int) \get_option('gsnav_default_desktop_id', 0);

        if ($desktopId <= 0) {
            return null;
        }

        $desktop = $this->findDesktopById($desktopId);

        if (! $desktop) {
            return null;
        }

        if ($desktop['scope'] !== 'system_default' || $desktop['status'] !== 'active') {
            return null;
        }

        return $desktop;
    }

    public function findSystemDefaultDesktop()
    {
        global $wpdb;

        $tableName = $this->getDesktopsTableName();
        $sql = "SELECT * FROM {$tableName} WHERE scope = %s AND status = %s ORDER BY is_default DESC, id ASC LIMIT 1";
        $query = $wpdb->prepare($sql, 'system_default', 'active');

        return $wpdb->get_row($query, ARRAY_A);
    }

    public function findUserDesktopById($userId, $desktopId)
    {
        global $wpdb;

        $tableName = $this->getDesktopsTableName();
        $sql = "SELECT * FROM {$tableName} WHERE id = %d AND user_id = %d AND scope = %s AND status = %s LIMIT 1";
        $query = $wpdb->prepare($sql, $desktopId, $userId, 'user_private', 'active');

        return $wpdb->get_row($query, ARRAY_A);
    }

    public function findDefaultDesktopForUser($userId)
    {
        global $wpdb;

        $tableName = $this->getDesktopsTableName();
        $sql = "SELECT * FROM {$tableName} WHERE user_id = %d AND scope = %s AND status = %s AND is_default = %d ORDER BY id ASC LIMIT 1";
        $query = $wpdb->prepare($sql, $userId, 'user_private', 'active', 1);

        return $wpdb->get_row($query, ARRAY_A);
    }

    public function findFirstDesktopForUser($userId)
    {
        global $wpdb;

        $tableName = $this->getDesktopsTableName();
        $sql = "SELECT * FROM {$tableName} WHERE user_id = %d AND scope = %s AND status = %s ORDER BY is_default DESC, id ASC LIMIT 1";
        $query = $wpdb->prepare($sql, $userId, 'user_private', 'active');

        return $wpdb->get_row($query, ARRAY_A);
    }

    public function findDesktopById($desktopId)
    {
        global $wpdb;

        $tableName = $this->getDesktopsTableName();
        $sql = "SELECT * FROM {$tableName} WHERE id = %d LIMIT 1";
        $query = $wpdb->prepare($sql, $desktopId);

        return $wpdb->get_row($query, ARRAY_A);
    }

    public function createDesktop($data)
    {
        global $wpdb;

        $wpdb->insert(
            $this->getDesktopsTableName(),
            $data,
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%d',
                '%d',
                '%d',
            ]
        );

        return (int) $wpdb->insert_id;
    }

    public function createItem($data)
    {
        global $wpdb;

        $wpdb->insert(
            $this->getItemsTableName(),
            $data,
            [
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
            ]
        );

        return (int) $wpdb->insert_id;
    }

    public function findItemsByDesktopId($desktopId)
    {
        global $wpdb;

        $tableName = $this->getItemsTableName();
        $sql = "SELECT * FROM {$tableName} WHERE desktop_id = %d ORDER BY sort_order ASC, id ASC";
        $query = $wpdb->prepare($sql, $desktopId);

        return $wpdb->get_results($query, ARRAY_A);
    }

    public function deleteItemsByDesktopId($desktopId)
    {
        global $wpdb;

        return $wpdb->delete(
            $this->getItemsTableName(),
            [
                'desktop_id' => (int) $desktopId,
            ],
            [
                '%d',
            ]
        );
    }

    public function updateDefaultDesktopId($desktopId)
    {
        \update_option('gsnav_default_desktop_id', (int) $desktopId);
    }

    public function getUserActiveDesktopId($userId)
    {
        return (int) \get_user_meta($userId, 'gsnav_active_desktop_id', true);
    }

    public function updateUserActiveDesktopId($userId, $desktopId)
    {
        \update_user_meta($userId, 'gsnav_active_desktop_id', (int) $desktopId);
    }

    public function beginTransaction()
    {
        global $wpdb;

        $wpdb->query('START TRANSACTION');
    }

    public function commit()
    {
        global $wpdb;

        $wpdb->query('COMMIT');
    }

    public function rollback()
    {
        global $wpdb;

        $wpdb->query('ROLLBACK');
    }

    private function getDesktopsTableName()
    {
        global $wpdb;

        return $wpdb->prefix . 'gsnav_desktops';
    }

    private function getItemsTableName()
    {
        global $wpdb;

        return $wpdb->prefix . 'gsnav_items';
    }
}
