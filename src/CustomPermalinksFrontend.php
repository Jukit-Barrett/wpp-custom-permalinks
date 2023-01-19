<?php

namespace Mrzkit\WppCustomPermalinks;

/**
 * 传递自定义链接的类，解析请求的URL并重定向
 */
class CustomPermalinksFrontend
{
    /**
     * @var bool 当 `parse_request()` 成功时将其设置为 `true` 以提高性能
     */
    private $parse_request_status = false;

    /**
     * @var string 访问页面的查询字符串（如果有），否则为空
     */
    private $query_string_uri = '';

    /**
     * @var string 保留 URL 供以后在 parse_request 中使用
     */
    private $registered_url = '';

    /**
     * @var string 为访问此页面而提供的 URI。 默认为空
     */
    private $request_uri = '';

    /**
     * @desc Initialize WordPress Hooks
     */
    public function init()
    {
        if (isset($_SERVER['QUERY_STRING'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $this->query_string_uri = $_SERVER['QUERY_STRING'];
        }

        if (isset($_SERVER['REQUEST_URI'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $this->request_uri = $_SERVER['REQUEST_URI'];
        }

        // 在确定加载哪个模板之前触发
        add_action('template_redirect', array($this, 'make_redirect'), 5);
        // 过滤已解析查询变量的数组
        add_filter('request', array($this, 'parse_request'));
        // Filters the determined post ID
        add_filter('oembed_request_post_id', array($this, 'oembed_request'), 10, 2);
        // Filters the permalink for a post
        add_filter('post_link', array($this, 'custom_post_link'), 10, 2);
        // Filters the permalink for a post of a custom post type
        add_filter('post_type_link', array($this, 'custom_post_link'), 10, 2);
        // Filters the permalink for a page
        add_filter('page_link', array($this, 'custom_page_link'), 10, 2);
        // Filters the term link
        add_filter('term_link', array($this, 'custom_term_link'), 10, 2);
        // 如果站点设置为添加尾部斜线，则检索尾部斜线字符串
        add_filter('user_trailingslashit', array($this, 'custom_trailingslash'));
        // WPSEO Filters.
        add_filter('wpseo_canonical', array($this, 'fix_canonical_double_slash'), 20, 1);
    }

    /**
     * @desc Replace double slash `//` with single slash `/`.
     * @param string $permalink URL in which `//` needs to be replaced with `/`.
     * @return string URL with single slash.
     */
    public function remove_double_slash($permalink = '')
    {
        $protocol = '';
        if (0 === strpos($permalink, 'http://') || 0 === strpos($permalink, 'https://')) {
            $split_protocol = explode('://', $permalink);
            if (1 < count($split_protocol)) {
                $protocol  = $split_protocol[0] . '://';
                $permalink = str_replace($protocol, '', $permalink);
            }
        }

        // 双斜线替换单斜线
        $permalink = str_replace('//', '/', $permalink);

        $permalink = $protocol . $permalink;

        return $permalink;
    }

    /**
     * @desc 使用 `wpml_permalink` 将语言信息添加到永久链接并解决语言切换器问题（如果发现）
     * @param string $permalink Custom Permalink.
     * @param string $language_code The language to convert the URL into.
     * @return string permalink with language information.
     */
    public function wpml_permalink_filter($permalink, $language_code)
    {
        $custom_permalink   = $permalink;
        $trailing_permalink = trailingslashit(home_url()) . $custom_permalink;
        if ($language_code) {
            $permalink = apply_filters('wpml_permalink', $trailing_permalink, $language_code);
            $site_url  = site_url();
            $wpml_href = str_replace($site_url, '', $permalink);
            if (0 === strpos($wpml_href, '//')) {
                if (0 !== strpos($wpml_href, '//' . $language_code . '/')) {
                    $permalink = $site_url . '/' . $language_code . '/' . $custom_permalink;
                }
            }
        } else {
            /**
             * 过滤WordPress永久链接，并根据WPML语言设置中设置的语言URL格式将其转换为特定于语言的永久链接。
             * 这意味着当“语言URL格式”设置为“目录中的不同语言”时，永久链接将以http://domain.com/de形式返回。
             * 当选择“每种语言不同的域”时，永久链接将被转换为包含分配给所请求语言的正确域。
             */
            $permalink = apply_filters('wpml_permalink', $trailing_permalink);
        }

        return $permalink;
    }

    /**
     * @desc 在 posts 表中搜索永久链接并返回其结果
     * @param string $requested_url Requested URL.
     * @return object|null Containing Post ID, Permalink, Post Type, and Post status
     *                     if URL matched otherwise returns null.
     */
    private function query_post($requested_url)
    {
        global $wpdb;

        $cache_name = 'cp$_' . str_replace('/', '-', $requested_url) . '_#cp';
        $posts      = wp_cache_get($cache_name, 'custom_permalinks');

        if ( !$posts) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $sql   = 'SELECT p.ID, pm.meta_value, p.post_type, p.post_status ' .
                     " FROM $wpdb->posts AS p INNER JOIN $wpdb->postmeta AS pm ON (pm.post_id = p.ID) " .
                     " WHERE pm.meta_key = 'custom_permalink' " .
                     ' AND (pm.meta_value = %s OR pm.meta_value = %s) ' .
                     " AND p.post_status != 'trash' AND p.post_type != 'nav_menu_item' " .
                     " ORDER BY FIELD(post_status,'publish','private','pending','draft','auto-draft','inherit')," .
                     " FIELD(post_type,'post','page') LIMIT 1";
            $posts = $wpdb->get_results(
                $wpdb->prepare($sql, $requested_url, $requested_url . '/')
            );

            $remove_like_query = apply_filters('cp_remove_like_query', '__true');
            if ( !$posts && '__true' === $remove_like_query) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $sql   = "SELECT p.ID, pm.meta_value, p.post_type, p.post_status FROM $wpdb->posts AS p " .
                         " LEFT JOIN $wpdb->postmeta AS pm ON (p.ID = pm.post_id) WHERE " .
                         " meta_key = 'custom_permalink' AND meta_value != '' AND " .
                         ' ( LOWER(meta_value) = LEFT(LOWER(%s), LENGTH(meta_value)) OR ' .
                         '   LOWER(meta_value) = LEFT(LOWER(%s), LENGTH(meta_value)) ) ' .
                         "  AND post_status != 'trash' AND post_type != 'nav_menu_item'" .
                         ' ORDER BY LENGTH(meta_value) DESC, ' .
                         " FIELD(post_status,'publish','private','pending','draft','auto-draft','inherit')," .
                         " FIELD(post_type,'post','page'), p.ID ASC LIMIT 1";
                $posts = $wpdb->get_results(
                    $wpdb->prepare($sql, $requested_url, $requested_url . '/')
                );
            }

            wp_cache_set($cache_name, $posts, 'custom_permalinks');
        }

        return $posts;
    }

    /**
     * @desc 检查条件是否匹配然后返回 true 以停止处理特定查询，例如站点地图
     * @param array $query Requested Query.
     * @return bool Whether to process the query or not.
     */
    private function exclude_query_proccess($query)
    {
        if ( !isset($query)) {
            return false;
        }

        if (isset($query['sitemap']) && !empty($query['sitemap'])) {
            return true;
        } else if (isset($query['seopress_sitemap']) && !empty($query['seopress_sitemap'])) {
            return true;
        } else if (isset($query['seopress_cpt']) && !empty($query['seopress_cpt'])) {
            return true;
        } else if (isset($query['seopress_sitemap_xsl']) && 1 === (int) $query['seopress_sitemap_xsl']) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @desc 如果我们有匹配的帖子，则过滤以重写查询
     * @param array $query The array of requested query variables.
     * @return array the URL which has to be parsed.
     */
    public function parse_request($query)
    {
        // 初始化请求地址
        if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== $this->request_uri) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $this->request_uri = $_SERVER['REQUEST_URI'];
        }

        // 站点地图页面的返回查询
        $stop_query = $this->exclude_query_proccess($query);
        if ($stop_query) {
            // 如果不需要处理查询，则避免重定向
            $this->parse_request_status = true;

            return $query;
        }

        // 首先，搜索匹配的自定义永久链接，如果找到则生成相应的原始 URL
        $original_url = null;

        // Get request URI, strip parameters and /'s.
        $url     = wp_parse_url(get_bloginfo('url'));
        $url     = isset($url['path']) ? $url['path'] : '';
        $request = ltrim(substr($this->request_uri, strlen($url)), '/');
        $pos     = strpos($request, '?');
        if ($pos) {
            $request = substr($request, 0, $pos);
        }

        if ( !$request) {
            return $query;
        }

        $ignore = apply_filters('custom_permalinks_request_ignore', $request);

        if ('__true' === $ignore) {
            return $query;
        }

        if (defined('POLYLANG_VERSION')) {
            $cp_form = new CustomPermalinksForm();
            $request = $cp_form->check_conflicts($request);
        }

        // 将N个斜线转换为单个斜线
        $request_no_slash  = preg_replace('@/+@', '/', trim($request, '/'));
        $posts             = $this->query_post($request_no_slash);
        $permalink_matched = false;
        $found_permalink   = '';

        if ($posts) {
            /*
             * A post matches our request. Preserve this URL for later use. If it's
             * the same as the permalink (no extra stuff).
             */
            if (trim($posts[0]->meta_value, '/') === $request_no_slash) {
                $this->registered_url = $request;
                $permalink_matched    = true;
            }

            $found_permalink = $posts[0]->meta_value;
            // 草稿或等待审核状态时
            if ('draft' === $posts[0]->post_status || 'pending' === $posts[0]->post_status) {
                if ('page' === $posts[0]->post_type) {
                    $original_url = '?page_id=' . $posts[0]->ID;
                } else {
                    $original_url = '?post_type=' . $posts[0]->post_type . '&p=' . $posts[0]->ID;
                }
            } else {
                // 转换小写，抹掉两边的 斜线
                $post_meta = trim(strtolower($posts[0]->meta_value), '/');
                if ('page' === $posts[0]->post_type) {
                    // Get Page Link
                    $get_original_url = $this->original_page_link($posts[0]->ID);
                } else {
                    // Get Post Link
                    $get_original_url = $this->original_post_link($posts[0]->ID);
                }
                $replaceString = str_replace($post_meta, $get_original_url, strtolower($request_no_slash));
                $original_url  = preg_replace('@/+@', '/', $replaceString);
            }
        }

        if (null === $original_url || (null !== $original_url && !$permalink_matched)) {
            // See if any terms have a matching permalink.
            $table = get_option('custom_permalink_table');
            if ($table) {
                $term_permalink = false;
                foreach (array_keys($table) as $permalink) {
                    $perm_length = strlen($permalink);
                    if ( !$term_permalink && null !== $original_url && trim($permalink, '/') !== $request_no_slash) {
                        continue;
                    }

                    if (substr($request_no_slash, 0, $perm_length) === $permalink
                        || substr($request_no_slash . '/', 0, $perm_length) === $permalink
                    ) {
                        $term           = $table[$permalink];
                        $term_permalink = true;

                        // 如果它与固定链接相同（没有额外的东西），请保留此 URL 以备后用。
                        if (trim($permalink, '/') === $request_no_slash) {
                            $this->registered_url = $request;
                        }

                        $found_permalink = $permalink;
                        $term_link       = $this->original_term_link($term['id']);

                        $original_url = str_replace(trim($permalink, '/'), $term_link, trim($request, '/'));
                    }
                }
            }
        }

        $this->parse_request_status = false;
        if (null !== $original_url) {
            $this->parse_request_status = true;

            /**
             * 如果永久链接与请求的 URL 不完全匹配，则允许重定向功能起作用。
             * 像尾部斜线（请求的 URL 不包含尾部斜线，但固定链接有尾部斜线，反之亦然）和字母大小写问题等
             */
            if ( !empty($found_permalink) && $found_permalink !== $request) {
                $this->parse_request_status = false;
            }

            $original_url = str_replace('//', '/', $original_url);
            $pos          = strpos($this->request_uri, '?');
            if (false !== $pos) {
                $query_vars = substr($this->request_uri, $pos + 1);
                if (false === strpos($original_url, '?')) {
                    $original_url .= '?' . $query_vars;
                } else {
                    $original_url .= '&' . $query_vars;
                }
            }

            /**
             * 现在我们有了原始 URL，通过 WP->parse_request 运行它，以便正确解析参数。 我们设置 `$_SERVER` 变量来欺骗函数
             */

            $_SERVER['REQUEST_URI'] = '/' . ltrim($original_url, '/');

            $path_info = apply_filters('custom_permalinks_path_info', '__false');

            if ('__false' !== $path_info) {
                $_SERVER['PATH_INFO'] = '/' . ltrim($original_url, '/');
            }

            $_SERVER['QUERY_STRING'] = '';

            $pos = strpos($original_url, '?');

            if (false !== $pos) {
                $_SERVER['QUERY_STRING'] = substr($original_url, $pos + 1);
            }

            $old_values  = array();
            $query_array = array();
            if (isset($_SERVER['QUERY_STRING'])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                parse_str($_SERVER['QUERY_STRING'], $query_array);
            }

            if (is_array($query_array) && count($query_array) > 0) {
                foreach ($query_array as $key => $value) {
                    $old_values[$key] = '';
                    // phpcs:disable WordPress.Security.NonceVerification.Recommended
                    if (isset($_REQUEST[$key])) {
                        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                        $old_values[$key] = $_REQUEST[$key];
                    }
                    // phpcs:enable WordPress.Security.NonceVerification.Recommended

                    $_GET[$key]     = $value;
                    $_REQUEST[$key] = $value;
                }
            }

            // Re-run the filter, now with original environment in place.
            remove_filter('request', array($this, 'parse_request'));
            global $wp;
            if (isset($wp->matched_rule)) {
                $wp->matched_rule = null;
            }
            $wp->parse_request();
            $query = $wp->query_vars;
            add_filter('request', array($this, 'parse_request'));

            // Restore values.
            $_SERVER['REQUEST_URI']  = $this->request_uri;
            $_SERVER['QUERY_STRING'] = $this->query_string_uri;
            foreach ($old_values as $key => $value) {
                $_REQUEST[$key] = $value;
            }
        }

        return $query;
    }

    /**
     * @desc 过滤确定的帖子 ID，如果我们在 CP 中有匹配的 URL，则更改它
     * @param int $post_id Post ID or 0.
     * @param string $oembed_url The requested URL.
     * @return int Post ID or 0.
     */
    public function oembed_request($post_id, $oembed_url)
    {
        /**
         * 首先，搜索匹配的自定义永久链接，如果找到则生成相应的原始 URL
         */

        $oembed_url = str_replace(home_url(), '', $oembed_url);

        // Get request URI, strip parameters and /'s.
        $url     = wp_parse_url(get_bloginfo('url'));
        $url     = isset($url['path']) ? $url['path'] : '';
        $request = ltrim(substr($oembed_url, strlen($url)), '/');
        $pos     = strpos($request, '?');
        if ($pos) {
            $request = substr($request, 0, $pos);
        }

        if ( !$request) {
            return $post_id;
        }

        $ignore = apply_filters('custom_permalinks_request_ignore', $request);

        if ('__true' === $ignore) {
            return $post_id;
        }

        if (defined('POLYLANG_VERSION')) {
            $cp_form = new CustomPermalinksForm();
            $request = $cp_form->check_conflicts($request);
        }
        // 将N个斜线，替换成单个斜线
        $request_no_slash = preg_replace('@/+@', '/', trim($request, '/'));
        $posts            = $this->query_post($request_no_slash);

        if ($posts && $posts[0]->ID && $posts[0]->ID > 0) {
            $post_id = $posts[0]->ID;
        }

        return $post_id;
    }

    /**
     * @desc 重定向到自定义永久链接的操作
     * @return void
     */
    public function make_redirect()
    {
        // 如果 `parse_request()` 成功，则提前返回以提高性能。
        if ($this->parse_request_status) {
            return;
        }

        // 初始化请求地址
        if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== $this->request_uri) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $this->request_uri = $_SERVER['REQUEST_URI'];
        }

        $custom_permalink   = '';
        $original_permalink = '';

        // Get request URI, strip parameters.
        $url     = wp_parse_url(get_bloginfo('url'));
        $url     = isset($url['path']) ? $url['path'] : '';
        $request = ltrim(substr($this->request_uri, strlen($url)), '/');
        $pos     = strpos($request, '?');
        if ($pos) {
            $request = substr($request, 0, $pos);
        }

        // 如果过滤器返回“true”，则禁用要处理的重定向
        $avoid_redirect = apply_filters('custom_permalinks_avoid_redirect', $request);

        if (is_bool($avoid_redirect) && $avoid_redirect) {
            return;
        }

        if (defined('POLYLANG_VERSION')) {
            $cp_form = new CustomPermalinksForm();
            $request = $cp_form->check_conflicts($request);
        }

        $request_no_slash = preg_replace('@/+@', '/', trim($request, '/'));
        $posts            = $this->query_post($request_no_slash);

        if ( !isset($posts[0]->ID) || !isset($posts[0]->meta_value) || empty($posts[0]->meta_value)) {
            global $wp_query;

            /*
             * If the post/tag/category we're on has a custom permalink, get it
             * and check against the request.
             */
            if ((is_single() || is_page()) && !empty($wp_query->post)) {
                $post             = $wp_query->post;
                $custom_permalink = get_post_meta(
                    $post->ID,
                    'custom_permalink',
                    true
                );
                if ('page' === $post->post_type) {
                    $original_permalink = $this->original_page_link($post->ID);
                } else {
                    $original_permalink = $this->original_post_link($post->ID);
                }
            } elseif (is_tag() || is_category()) {
                $the_term           = $wp_query->get_queried_object();
                $custom_permalink   = $this->term_permalink($the_term->term_id);
                $original_permalink = $this->original_term_link($the_term->term_id);
            }
        } else {
            $custom_permalink = $posts[0]->meta_value;
            if ('page' === $posts[0]->post_type) {
                $original_permalink = $this->original_page_link($posts[0]->ID);
            } else {
                $original_permalink = $this->original_post_link($posts[0]->ID);
            }
        }

        $custom_length = strlen($custom_permalink);
        if ($custom_permalink
            && (
                substr($request, 0, $custom_length) !== $custom_permalink
                || $request === $custom_permalink . '/'
            )
        ) {
            // Request doesn't match permalink - redirect.
            $url             = $custom_permalink;
            $original_length = strlen($original_permalink);

            if (substr($request, 0, $original_length) === $original_permalink
                && trim($request, '/') !== trim($original_permalink, '/')
            ) {
                // This is the original link; we can use this URL to derive the new one.
                $url = preg_replace(
                    '@//*@',
                    '/',
                    str_replace(
                        trim($original_permalink, '/'),
                        trim($custom_permalink, '/'),
                        $request
                    )
                );
                $url = preg_replace('@([^?]*)&@', '\1?', $url);
            }

            // Append any query compenent.
            $url .= strstr($this->request_uri, '?');

            wp_safe_redirect(home_url() . '/' . $url, 301);
            exit(0);
        }
    }

    /**
     * @desc 过滤以用自定义链接替换帖子永久链接
     * @param string $permalink Default WordPress Permalink of Post.
     * @param object $post Post Details.
     * @return string customized Post Permalink.
     */
    public function custom_post_link($permalink, $post)
    {
        // 获取 custom_permalink 数据
        $custom_permalink = get_post_meta($post->ID, 'custom_permalink', true);
        if ($custom_permalink) {
            $post_type = 'post';
            if (isset($post->post_type)) {
                $post_type = $post->post_type;
            }

            //获取可翻译元素的语言代码
            $language_code = apply_filters(
                'wpml_element_language_code',
                null,
                array(
                    'element_id'   => $post->ID,
                    'element_type' => $post_type,
                )
            );

            $permalink = $this->wpml_permalink_filter($custom_permalink, $language_code);
        } else {
            if (class_exists('SitePress')) {
                $wpml_lang_format = apply_filters(
                    'wpml_setting',
                    0,
                    'language_negotiation_type'
                );

                if (1 === intval($wpml_lang_format)) {
                    $get_original_url = $this->original_post_link($post->ID);
                    $permalink        = $this->remove_double_slash($permalink);
                    if (strlen($get_original_url) === strlen($permalink)) {
                        $permalink = $get_original_url;
                    }
                }
            }
        }

        $permalink = $this->remove_double_slash($permalink);

        return $permalink;
    }

    /**
     * @desc 过滤以用自定义页面替换页面永久链接
     * @param string $permalink Default WordPress Permalink of Page.
     * @param int $page Page ID.
     * @return string customized Page Permalink.
     */
    public function custom_page_link($permalink, $page)
    {
        $custom_permalink = get_post_meta($page, 'custom_permalink', true);
        if ($custom_permalink) {
            $language_code = apply_filters(
                'wpml_element_language_code',
                null,
                array(
                    'element_id'   => $page,
                    'element_type' => 'page',
                )
            );

            $permalink = $this->wpml_permalink_filter($custom_permalink, $language_code);
        } else {
            if (class_exists('SitePress')) {
                $wpml_lang_format = apply_filters(
                    'wpml_setting',
                    0,
                    'language_negotiation_type'
                );

                if (1 === intval($wpml_lang_format)) {
                    $get_original_url = $this->original_page_link($page);
                    $permalink        = $this->remove_double_slash($permalink);
                    if (strlen($get_original_url) === strlen($permalink)) {
                        $permalink = $get_original_url;
                    }
                }
            }
        }

        $permalink = $this->remove_double_slash($permalink);

        return $permalink;
    }

    /**
     * @desc 过滤以将术语永久链接替换为自定义链接
     * @param string $permalink Term link URL.
     * @param object $term Term object.
     * @return string customized Term Permalink.
     */
    public function custom_term_link($permalink, $term)
    {
        if (isset($term)) {
            if (isset($term->term_id)) {
                $custom_permalink = $this->term_permalink($term->term_id);
            }

            if ($custom_permalink) {
                $language_code = null;
                if (isset($term->term_taxonomy_id)) {
                    $term_type = 'category';
                    if (isset($term->taxonomy)) {
                        $term_type = $term->taxonomy;
                    }

                    $language_code = apply_filters(
                        'wpml_element_language_code',
                        null,
                        array(
                            'element_id'   => $term->term_taxonomy_id,
                            'element_type' => $term_type,
                        )
                    );
                }

                $permalink = $this->wpml_permalink_filter($custom_permalink, $language_code);
            } elseif (isset($term->term_id)) {
                if (class_exists('SitePress')) {
                    $wpml_lang_format = apply_filters(
                        'wpml_setting',
                        0,
                        'language_negotiation_type'
                    );

                    if (1 === intval($wpml_lang_format)) {
                        $get_original_url = $this->original_term_link(
                            $term->term_id
                        );
                        $permalink        = $this->remove_double_slash($permalink);
                        if (strlen($get_original_url) === strlen($permalink)) {
                            $permalink = $get_original_url;
                        }
                    }
                }
            }
        }

        $permalink = $this->remove_double_slash($permalink);

        return $permalink;
    }

    /**
     * @desc 删除 post_link 和 user_trailingslashit 过滤器以获取默认和自定义帖子类型的原始永久链接，然后立即应用。
     * @param int $post_id Post ID.
     * @return string Original Permalink for Posts.
     */
    public function original_post_link($post_id)
    {
        remove_filter('post_link', array($this, 'custom_post_link'));
        remove_filter('post_type_link', array($this, 'custom_post_link'));

        include_once ABSPATH . '/wp-admin/includes/post.php';

        list($permalink, $post_name) = get_sample_permalink($post_id);
        $permalink = str_replace(
            array('%pagename%', '%postname%'),
            $post_name,
            $permalink
        );
        $permalink = ltrim(str_replace(home_url(), '', $permalink), '/');

        add_filter('post_link', array($this, 'custom_post_link'), 10, 3);
        add_filter('post_type_link', array($this, 'custom_post_link'), 10, 2);

        return $permalink;
    }

    /**
     * @desc 删除 page_link 和 user_trailingslashit 过滤器以获取页面的原始永久链接并在之后立即应用
     * @param int $post_id Page ID.
     * @return string Original Permalink for the Page.
     */
    public function original_page_link($post_id)
    {
        remove_filter('page_link', array($this, 'custom_page_link'));
        remove_filter(
            'user_trailingslashit',
            array($this, 'custom_trailingslash')
        );

        include_once ABSPATH . '/wp-admin/includes/post.php';

        list($permalink, $post_name) = get_sample_permalink($post_id);
        $permalink = str_replace(
            array('%pagename%', '%postname%'),
            $post_name,
            $permalink
        );
        $permalink = ltrim(str_replace(home_url(), '', $permalink), '/');

        add_filter('user_trailingslashit', array($this, 'custom_trailingslash'));
        add_filter('page_link', array($this, 'custom_page_link'), 10, 2);

        return $permalink;
    }

    /**
     * @desc 删除 term_link 和 user_trailingslashit 过滤器以获取 Term 的原始永久链接并在此之后立即应用
     * @param int $term_id Term ID.
     * @return string Original Permalink for Posts.
     */
    public function original_term_link($term_id)
    {
        remove_filter('term_link', array($this, 'custom_term_link'));
        remove_filter(
            'user_trailingslashit',
            array($this, 'custom_trailingslash')
        );

        $term      = get_term($term_id);
        $term_link = get_term_link($term);

        add_filter('user_trailingslashit', array($this, 'custom_trailingslash'));
        add_filter('term_link', array($this, 'custom_term_link'), 10, 2);

        if (is_wp_error($term_link)) {
            return '';
        }

        $original_permalink = ltrim(str_replace(home_url(), '', $term_link), '/');

        return $original_permalink;
    }

    /**
     * @desc 过滤以正确处理尾部斜线
     * @param string $url_string URL with or without a trailing slash.
     * @return string Adds/removes a trailing slash based on the permalink structure.
     */
    public function custom_trailingslash($url_string)
    {
        remove_filter(
            'user_trailingslashit',
            array($this, 'custom_trailingslash')
        );

        $trailingslash_string = $url_string;
        $url                  = wp_parse_url(get_bloginfo('url'));

        if (isset($url['path'])) {
            $request = substr($url_string, strlen($url['path']));
        } else {
            $request = $url_string;
        }

        $request = ltrim($request, '/');

        add_filter('user_trailingslashit', array($this, 'custom_trailingslash'));

        if (trim($request)) {
            if (trim($this->registered_url, '/') === trim($request, '/')) {
                if ('/' === $url_string[0]) {
                    $trailingslash_string = '/';
                } else {
                    $trailingslash_string = '';
                }

                if (isset($url['path'])) {
                    $trailingslash_string .= trailingslashit($url['path']);
                }

                $trailingslash_string .= $this->registered_url;
            }
        }

        return $trailingslash_string;
    }

    /**
     * @desc Get permalink for term.
     * @param int $term_id Term id.
     * @return bool Term link.
     */
    public function term_permalink($term_id)
    {
        $table = get_option('custom_permalink_table');
        if ($table) {
            foreach ($table as $link => $info) {
                if ($info['id'] === $term_id) {
                    return $link;
                }
            }
        }

        return false;
    }

    /**
     * @desc 使用 WPML 特别修复 Yoast SEO 规范的双斜杠问题
     * @param string $canonical The canonical.
     * @return string the canonical after removing double slash if exist.
     */
    public function fix_canonical_double_slash($canonical)
    {
        $canonical = $this->remove_double_slash($canonical);

        return $canonical;
    }
}
