<?php

namespace Mrzkit\WppCustomPermalinks\Repository;

use Mrzkit\WppCustomPermalinks\Model\Posts;

class PostsRepository
{
    private $model;

    public function __construct()
    {
        $this->model = new Posts();
    }

    public function selectPosts($requestedUrl)
    {
        $result = $this->model->selectPosts($requestedUrl);

        return $result;
    }
}
