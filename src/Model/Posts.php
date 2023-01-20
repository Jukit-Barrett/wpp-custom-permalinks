<?php

namespace Mrzkit\WppCustomPermalinks\Model;

use Mrzkit\SimpleDatabase\Query\Model;

class Posts extends Model
{
    /**
     * @var string 表前缀
     */
    protected $tablePrefix = 'wp_';

    /**
     * @var string 表名
     */
    protected $table = 'posts';

    public function selectPosts($requestedUrl)
    {
        $requestedUrl = (string) $requestedUrl;

        $postTable = $this->getTablePrefix() . $this->getTable();

        $postMetaTable = 'wp_postmeta';

        $sql = 'SELECT p.ID, pm.meta_value, p.post_type, p.post_status ' .
               " FROM {$postTable} AS p INNER JOIN {$postMetaTable} AS pm ON (pm.post_id = p.ID) " .
               " WHERE pm.meta_key = 'custom_permalink' " .
               ' AND (pm.meta_value = ? OR pm.meta_value = ?) ' .
               " AND p.post_status != 'trash' AND p.post_type != 'nav_menu_item' " .
               " ORDER BY FIELD(post_status,'publish','private','pending','draft','auto-draft','inherit')," .
               " FIELD(post_type,'post','page') LIMIT 1";

        $result = $this->newQuery()->getConnection()->select($sql, [$requestedUrl, $requestedUrl . '/']);

        return $result;
    }
}
