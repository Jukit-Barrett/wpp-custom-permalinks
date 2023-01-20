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

    /**
     * @desc 搜索永久链接并返回其结果
     * @param $requestedUrl
     * @return array|false|mixed
     */
    public function queryPost($requestedUrl)
    {
        $requestedUrl = (string) $requestedUrl;

        $cache_name = 'cp$_' . str_replace('/', '-', $requestedUrl) . '_#cp';

        $posts = wp_cache_get($cache_name, 'custom_permalinks');

        // 如果缓存里面没有就查库
        if ( !$posts) {
            $postMetaRepository = new PostMetaRepository();

            $posts = $this->selectPosts($requestedUrl);

            $remove_like_query = apply_filters('cp_remove_like_query', '__true');
            // 如果仍然查不到且钩子返回 __true
            if ( !$posts && '__true' === $remove_like_query) {
                $posts = $postMetaRepository->selectPostMeta($requestedUrl);
            }

            if ( !empty($posts)) {
                wp_cache_set($cache_name, $posts, 'custom_permalinks');
            }
        }

        return $posts;
    }
}
