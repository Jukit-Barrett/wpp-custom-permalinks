<?php

namespace Mrzkit\WppCustomPermalinks\Model;

use Mrzkit\SimpleDatabase\Query\Model;

class PostMeta extends Model
{
    /**
     * @var string 表前缀
     */
    protected $tablePrefix = 'wp_';

    /**
     * @var string 表名
     */
    protected $table = 'postmeta';

    /**
     * @desc
     * @param string $requestedUrl
     * @return array
     */
    public function selectPostMeta($requestedUrl)
    {
        $requestedUrl = (string) $requestedUrl;

        $postTable = 'wp_posts';

        $postMetaTable = $this->getTablePrefix() . $this->getTable();

        $sql = "SELECT p.ID, pm.meta_value, p.post_type, p.post_status FROM {$postTable} AS p " .
               " LEFT JOIN {$postMetaTable} AS pm ON (p.ID = pm.post_id) WHERE " .
               " meta_key = 'custom_permalink' AND meta_value != '' AND " .
               ' ( LOWER(meta_value) = LEFT(LOWER(?), LENGTH(meta_value)) OR ' .
               '   LOWER(meta_value) = LEFT(LOWER(?), LENGTH(meta_value)) ) ' .
               "  AND post_status != 'trash' AND post_type != 'nav_menu_item'" .
               ' ORDER BY LENGTH(meta_value) DESC, ' .
               " FIELD(post_status,'publish','private','pending','draft','auto-draft','inherit')," .
               " FIELD(post_type,'post','page'), p.ID ASC LIMIT 1";

        $result = $this->newQuery()->getConnection()->select($sql, [$requestedUrl, $requestedUrl . '/']);

        return $result;
    }
}
