<?php
defined('ABSPATH') || exit;

/**
 * Yitu Upload主类
 */
class Yitu_Upload {
    /**
     * 插件实例
     */
    private static $instance = null;

    /**
     * 阿里云OSS配置
     */
    private $oss_config = [];

    /**
     * 构造函数
     */
    public function __construct() {
        $this->oss_config = [
            'access_key_id' => get_option('yitu_upload_access_key_id'),
            'access_key_secret' => get_option('yitu_upload_access_key_secret'),
            'endpoint' => get_option('yitu_upload_endpoint'),
            'bucket' => get_option('yitu_upload_bucket'),
        ];
    }

    /**
     * 初始化插件
     */
    public function init() {
        // 加载依赖
        require_once YITU_UPLOAD_PLUGIN_DIR . 'includes/class-yitu-upload-admin.php';
        require_once YITU_UPLOAD_PLUGIN_DIR . 'includes/class-yitu-upload-frontend.php';

        // 注册激活钩子
        register_activation_hook(YITU_UPLOAD_PLUGIN_DIR . 'yitu_upload-for-woocommerce.php', [$this, 'activate']);

        // 初始化管理员界面
        if (current_user_can('edit_posts')) {
            new Yitu_Upload_Admin();
        }

        // 初始化前端界面
        new Yitu_Upload_Frontend();

        // 添加设置链接
        add_filter('plugin_action_links_' . plugin_basename(YITU_UPLOAD_PLUGIN_DIR . 'yitu_upload-for-woocommerce.php'), 
            [$this, 'add_settings_link']
        );
    }

    /**
     * 插件激活时的操作
     */
    public function activate() {
        // 创建自定义表
        $this->create_tables();
        
        // 设置默认选项
        $this->set_default_options();
    }

    /**
     * 创建自定义表
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}yitu_upload_files (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            file_url varchar(255) NOT NULL,
            file_name varchar(255) NOT NULL,
            upload_time datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * 设置默认选项
     */
    private function set_default_options() {
        add_option('yitu_upload_access_key_id', '');
        add_option('yitu_upload_access_key_secret', '');
        add_option('yitu_upload_endpoint', '');
        add_option('yitu_upload_bucket', '');
        add_option('yitu_upload_allowed_file_types', 'jpg,jpeg,png,pdf');
        add_option('yitu_upload_max_file_size', '5');
    }

    /**
     * 添加设置链接到插件页面
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=yitu-upload-settings') . '">' . __('Settings', 'yitu-upload-wc') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * 获取插件实例
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
} 