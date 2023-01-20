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
}
