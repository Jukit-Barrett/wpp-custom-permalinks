<?php

namespace Mrzkit\WppCustomPermalinks;

use Mrzkit\WppCustomPermalinks\Admin\CustomPermalinksAdmin;

class CustomPermalinks
{
    /**
     * @var string Custom Permalinks version
     */
    public $version = '2.4.0';

    public function __construct()
    {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * @desc Define Custom Permalinks Constants
     */
    private function define_constants()
    {
        if ( !defined('CUSTOM_PERMALINKS_BASENAME')) {
            define('CUSTOM_PERMALINKS_BASENAME', plugin_basename(CUSTOM_PERMALINKS_FILE));
        }
        if ( !defined('CUSTOM_PERMALINKS_PATH')) {
            define('CUSTOM_PERMALINKS_PATH', plugin_dir_path(CUSTOM_PERMALINKS_FILE));
        }
        if ( !defined('CUSTOM_PERMALINKS_VERSION')) {
            define('CUSTOM_PERMALINKS_VERSION', $this->version);
        }
    }

    /**
     * @desc
     */
    private function includes()
    {

        new CustomPermalinksAdmin();

        $cp_form = new CustomPermalinksForm();
        $cp_form->init();

        $cp_frontend = new CustomPermalinksFrontend();
        $cp_frontend->init();
    }

    /**
     * @desc Hook into actions and filters
     */
    private function init_hooks()
    {
        register_activation_hook(CUSTOM_PERMALINKS_FILE, array(self::class, 'add_roles'));

        register_activation_hook(CUSTOM_PERMALINKS_FILE, array(self::class, 'activate_details'));

        register_deactivation_hook(CUSTOM_PERMALINKS_FILE, array(self::class, 'deactivate_details'));

        add_action('plugins_loaded', array($this, 'check_loaded_plugins'));
    }

    /**
     * @desc 为查看帖子和类别永久链接添加角色，如果存在管理员角色，则默认将其分配给管理员。
     */
    public static function add_roles()
    {
        $admin_role = get_role('administrator');
        $cp_role    = get_role('custom_permalinks_manager');

        if ( !empty($admin_role)) {
            // 为角色分配能力
            $admin_role->add_cap('cp_view_post_permalinks');
            // 为角色分配能力
            $admin_role->add_cap('cp_view_category_permalinks');
        }

        if (empty($cp_role)) {
            $capabilities = array(
                'cp_view_post_permalinks'     => true,
                'cp_view_category_permalinks' => true,
            );
            $displayName  = __('Custom Permalinks Manager');
            add_role('custom_permalinks_manager', $displayName, $capabilities);
        }
    }

    /**
     * @desc 当插件被激活/更新安装时发送详细信息选项表中的版本。
     */
    public static function activate_details()
    {
        // 更新插件版本
        update_option('custom_permalinks_plugin_version', CUSTOM_PERMALINKS_VERSION);
    }

    /**
     * @desc 当插件被停用时发送详细信息
     */
    public static function deactivate_details()
    {
    }

    /**
     * @desc 检查角色是否不存在然后调用函数添加它。如果插件更新，请更新站点详细信息。 此外，将插件语言文件加载到支持不同的语言。
     */
    public function check_loaded_plugins()
    {
        if (is_admin()) {
            $current_version = get_option('custom_permalinks_plugin_version', -1);

            if (-1 === $current_version || $current_version < CUSTOM_PERMALINKS_VERSION) {
                self::activate_details();
                self::add_roles();
            }
        }

        $pluginRelPath = basename(dirname(CUSTOM_PERMALINKS_FILE)) . '/languages/';

        load_plugin_textdomain('custom-permalinks', false, $pluginRelPath);
    }
}
