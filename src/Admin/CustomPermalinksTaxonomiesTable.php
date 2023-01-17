<?php

namespace Mrzkit\WppCustomPermalinks\Admin;

use Mrzkit\WppCustomPermalinks\CustomPermalinksForm;
use Mrzkit\WppCustomPermalinks\CustomPermalinksFrontend;

if ( !class_exists('WP_List_Table')) {
    include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Taxonomies Permalinks table class.
 */
final class CustomPermalinksTaxonomiesTable extends \WP_List_Table
{
    /**
     * @var object Singleton instance variable
     */
    private static $instance;

    /**
     * 初始化 Taxonomies Permalinks 表列表
     */
    public function __construct()
    {
        parent::__construct(
            array(
                'singular' => __('Permalink', 'custom-permalinks'),
                'plural'   => __('Permalinks', 'custom-permalinks'),
                'ajax'     => false,
            )
        );

        // Handle screen options.
        $this->screen_options();
    }

    /**
     * @desc Singleton instance method.
     * @return CustomPermalinksTaxonomiesTable|object
     */
    public static function instance()
    {
        if ( !isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @desc 处理显示和保存屏幕选项
     */
    private function screen_options()
    {
        $per_page_option = "{$this->screen->id}_per_page";

        // phpcs:disable WordPress.Security.NonceVerification.Missing
        // Save screen options if the form has been submitted.
        if (isset($_POST['screen-options-apply'])) {
            // Save posts per page option.
            if (isset($_POST['wp_screen_options']['value'])) {
                update_user_option(
                    get_current_user_id(),
                    $per_page_option,
                    sanitize_text_field($_POST['wp_screen_options']['value'])
                );
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        // Add per page option to the screen options.
        $this->screen->add_option(
            'per_page',
            array(
                'option' => $per_page_option,
            )
        );
    }

    /**
     * @desc No items found text.
     */
    public function no_items()
    {
        esc_html_e('No permalinks found.', 'custom-permalinks');
    }

    /**
     * @desc 获取数组形式的列列表
     * @return array
     */
    public function get_columns()
    {
        $columns = array(
            'cb'        => '<input type="checkbox" />',
            'title'     => esc_html__('Title', 'custom-permalinks'),
            'type'      => esc_html__('Type', 'custom-permalinks'),
            'permalink' => esc_html__('Permalink', 'custom-permalinks'),
        );

        return $columns;
    }

    /**
     * @desc 返回页面的输出
     */
    public static function output()
    {
        $user_id              = get_current_user_id();
        $permalink_deleted    = filter_input(INPUT_GET, 'deleted');
        $search_permalink     = filter_input(INPUT_GET, 's');
        $taxonomy_types_table = self::instance();
        $taxonomy_types_table->prepare_items();
        ?>

        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php esc_html_e('Taxonomies Permalinks', 'custom-permalinks'); ?>
            </h1>

            <?php if (isset($search_permalink) && !empty($search_permalink)) : ?>
                <span class="subtitle">
				<?php
                esc_html_e('Search results for: ', 'custom-permalinks');
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                printf('<strong>%s</strong>', esc_html($search_permalink));
                ?>
				</span>
            <?php endif; ?>

            <hr class="wp-header-end">

            <?php if (isset($permalink_deleted) && 0 < $permalink_deleted) : ?>
                <div id="message" class="updated notice is-dismissible">
                    <p>
                        <?php
                        printf(
                        // translators: Placeholder would bee replaced with the number.
                            _n( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                '%s permalink deleted.',
                                '%s permalinks deleted.',
                                $permalink_deleted,
                                'custom-permalinks'
                            ),
                            esc_html($permalink_deleted)
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>
            <form id="posts-filter" method="GET">
                <input type="hidden" name="page" value="cp-taxonomy-permalinks"/>
                <?php
                wp_nonce_field(
                    'custom-permalinks-taxonomy_' . $user_id,
                    '_custom_permalinks_taxonomy_nonce'
                );
                $taxonomy_types_table->search_box(
                    esc_html__(
                        'Search Permalinks',
                        'custom-permalinks'
                    ),
                    'search-submit'
                );
                $taxonomy_types_table->display();
                ?>
            </form>
        </div>

        <?php
    }

    /**
     * @desc 为 WP_List_Table 设置列标题
     * @return mixed|null
     */
    protected function get_hidden_columns()
    {
        $columns = get_user_option("manage{$this->screen->id}columnshidden");

        return apply_filters('custom_permalinks_taxonomy_table_hidden_columns', (array) $columns);
    }

    /**
     * @desc 渲染批量操作的复选框
     * @param $item
     * @return string|void
     */
    protected function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="permalink[]" value="%s" />',
            $item['ID']
        );
    }

    /**
     * @desc 为 `Title` 设置列内容
     * @param $item
     * @return string
     */
    protected function column_title($item)
    {
        $edit_link  = '';
        $term_title = 'NOT SET';

        if (isset($item['ID']) && isset($item['type'])) {
            $taxonomy_type = 'category';
            if ('tag' === $item['type']) {
                $taxonomy_type = 'post_tag';
            }

            $edit_link = get_edit_term_link($item['ID'], $taxonomy_type);
            $term      = get_term($item['ID'], $taxonomy_type);

            if (isset($term) && isset($term->name) && !empty($term->name)) {
                $term_title = $term->name;
            }
        }

        $title_with_edit_link = $term_title;
        if ( !empty($edit_link)) {
            $title_with_edit_link = sprintf(
                '<a href="%s" target="_blank" title="' . esc_html__('Edit ', 'custom-permalinks') . ' ' . $term_title . '">%s</a>',
                $edit_link,
                $term_title
            );
        }

        return $title_with_edit_link;
    }

    /**
     * @desc  为 `Type` 设置列内容
     * @param $item
     * @return string
     */
    protected function column_type($item)
    {
        $taxonomy_type = 'category';

        if (isset($item['type'])) {
            $taxonomy_type = ucwords($item['type']);
        }

        return $taxonomy_type;
    }

    /**
     * @desc 为 `Permalink` 设置列内容
     * @param $item
     * @return string
     */
    protected function column_permalink($item)
    {
        $permalink = '';

        if ($item['permalink']) {
            $cp_frontend      = new CustomPermalinksFrontend();
            $custom_permalink = '/' . $item['permalink'];
            $home_url         = home_url();
            $taxonomy_type    = 'category';

            if (class_exists('SitePress')) {
                $wpml_lang_format = apply_filters(
                    'wpml_setting',
                    0,
                    'language_negotiation_type'
                );

                if (1 === intval($wpml_lang_format)) {
                    $home_url = site_url();
                }
            }

            if ('tag' === $item['type']) {
                $taxonomy_type = 'post_tag';
            }

            $language_code = apply_filters(
                'wpml_element_language_code',
                null,
                array(
                    'element_id'   => $item['ID'],
                    'element_type' => $taxonomy_type,
                )
            );

            $permalink = $cp_frontend->wpml_permalink_filter(
                $custom_permalink,
                $language_code
            );
            $permalink = $cp_frontend->remove_double_slash($permalink);
            $perm_text = str_replace($home_url, '', $permalink);

            $term_title = '';
            if (isset($item['ID']) && isset($item['type'])) {
                $term = get_term($item['ID'], $item['type']);
                if (isset($term) && isset($term->name) && !empty($term->name)) {
                    $term_title = $term->name;
                }
            }

            $permalink = sprintf(
                '<a href="%s" target="_blank" title="' . esc_html__('Visit', 'custom-permalinks') . ' ' . $term_title . '">%s</a>',
                $permalink,
                $perm_text
            );
        }

        return $permalink;
    }

    /**
     * @desc 获取批量操作
     * @return array
     */
    public function get_bulk_actions()
    {
        return array(
            'delete' => esc_html__('Delete Permalinks', 'custom-permalinks'),
        );
    }

    /**
     * @desc 处理批量操作
     */
    public function process_bulk_action()
    {
        if (isset($_REQUEST['_custom_permalinks_taxonomy_nonce'])) {
            $deleted = 0;
            $user_id = get_current_user_id();

            // Detect when a bulk action is being triggered.
            if ('delete' === $this->current_action()
                && wp_verify_nonce(
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    $_REQUEST['_custom_permalinks_taxonomy_nonce'],
                    'custom-permalinks-taxonomy_' . $user_id
                )
            ) {
                if (isset($_REQUEST['permalink'])) {
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    $del_permalinks = $_REQUEST['permalink'];
                }

                if (isset($del_permalinks)
                    && !empty($del_permalinks)
                    && is_array($del_permalinks)
                    && 0 < count($del_permalinks)
                ) {
                    $cp_form = new CustomPermalinksForm();
                    foreach ($del_permalinks as $term_id) {
                        if (is_numeric($term_id)) {
                            $cp_form->delete_term_permalink($term_id);
                            ++$deleted;
                        }
                    }
                }
            }

            $cp_page     = filter_input(INPUT_GET, 'page');
            $cp_paged    = filter_input(INPUT_GET, 'paged');
            $perm_search = filter_input(INPUT_GET, 's');
            $page_args   = array();

            if ( !empty($cp_page)) {
                $page_args['page'] = $cp_page;
            } else {
                $page_args['page'] = 'cp-taxonomy-permalinks';
            }

            if ( !empty($perm_search)) {
                $page_args['s'] = $perm_search;
            }

            if ( !empty($cp_paged) && is_numeric($cp_paged)) {
                $page_args['paged'] = $cp_paged;
            }

            if (0 < $deleted) {
                $page_args['deleted'] = $deleted;
            }

            wp_safe_redirect(add_query_arg($page_args, admin_url('admin.php')));
            exit;
        }
    }

    /**
     * @desc 在表格上方或下方生成表格导航
     * @param $which
     */
    protected function display_tablenav($which)
    {
        ?>
        <div class="tablenav <?php echo esc_attr($which); ?>">
            <?php if ($this->has_items()) : ?>
                <div class="alignleft actions bulkactions">
                    <?php $this->bulk_actions($which); ?>
                </div>
            <?php
            endif;

            $this->extra_tablenav($which);
            $this->pagination($which);
            ?>

            <br class="clear"/>
        </div>
        <?php
    }

    /**
     * @desc 预处理表格项目
     */
    public function prepare_items()
    {
        $columns  = $this->get_columns();
        $hidden   = $this->get_hidden_columns();
        $sortable = array();

        $this->_column_headers = array($columns, $hidden, $sortable);

        // Process bulk action.
        $this->process_bulk_action();

        $per_page     = $this->get_items_per_page("{$this->screen->id}_per_page");
        $current_page = $this->get_pagenum();
        $total_items  = CustomPermalinksTaxonomies::total_permalinks();
        $this->items  = CustomPermalinksTaxonomies::get_permalinks($per_page, $current_page);

        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => ceil($total_items / $per_page),
            )
        );
    }
}
