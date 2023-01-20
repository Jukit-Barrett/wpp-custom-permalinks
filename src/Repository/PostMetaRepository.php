<?php

namespace Mrzkit\WppCustomPermalinks\Repository;

use Mrzkit\WppCustomPermalinks\Model\PostMeta;

class PostMetaRepository
{
    private $model;

    public function __construct()
    {
        $this->model = new PostMeta();
    }

    /**
     * @desc
     * @param $metaKey
     * @param $metaValue
     * @return object|null
     */
    public function selectOneMetaKv($metaKey, $metaValue)
    {
        $wheres = [
            'meta_key'   => (string) $metaKey,
            'meta_value' => (string) $metaValue,
        ];

        $list = $this->model->newQuery()->where($wheres)->first();

        return $list;
    }

}
