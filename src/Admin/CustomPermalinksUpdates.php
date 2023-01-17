<?php

namespace Mrzkit\WppCustomPermalinks\Admin;

class CustomPermalinksUpdates
{
    /**
     * @var string 检查插件是否被激活或停用
     */
    private $method = 'install';

    /**
     * 调用函数发送详细信息
     * @param string $action 插件是激活还是停用
     */
    public function __construct($action)
    {
        if ($action && 'deactivate' === $action) {
            $this->method = 'uninstall';
        }

//        $this->update_version_details();
    }

    /**
     * @desc 获取站点详细信息并将其发送给 CP
     */
//    private function update_version_details()
//    {
//        $admin_email = get_bloginfo('admin_email');
//        $request_url = 'https://www.custompermalinks.com/plugin-update/';
//        $site_name   = get_bloginfo('name');
//        $site_url    = get_bloginfo('wpurl');
//        $wp_version  = get_bloginfo('version');
//
//        $updates = array(
//            'action'         => $this->method,
//            'admin_email'    => $admin_email,
//            'plugin_version' => CUSTOM_PERMALINKS_VERSION,
//            'site_name'      => $site_name,
//            'site_url'       => $site_url,
//            'wp_version'     => $wp_version,
//        );
//
//        // Performs an HTTP request using the POST method.
//        wp_remote_post(
//            $request_url,
//            array(
//                'method' => 'POST',
//                'body'   => $updates,
//            )
//        );
//    }
}
