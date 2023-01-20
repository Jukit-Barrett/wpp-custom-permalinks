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
     * @param $requestedUrl
     * @return array
     */
    public function selectPostMeta($requestedUrl)
    {
        $result = $this->model->selectPostMeta($requestedUrl);

        return $result;
    }
}
