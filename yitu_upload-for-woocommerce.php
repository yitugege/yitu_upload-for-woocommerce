<?php
/**
 * Plugin Name: Yitu Upload for WooCommerce
 * Plugin URI: https://www.ele-gate.com
 * Description: 允许用户在结账时上传支付凭证到阿里云OSS，并在HPOS订单管理界面查看
 * Version: 1.0.0
 * Author: Yitu
 * Author URI: https://www.ele-gate.com
 * Text Domain: yitu-upload-wc
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 */

defined('ABSPATH') || exit;

if (!defined('YITU_UPLOAD_VERSION')) {
    define('YITU_UPLOAD_VERSION', '1.0.0');
}

if (!defined('YITU_UPLOAD_PLUGIN_DIR')) {
    define('YITU_UPLOAD_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('YITU_UPLOAD_PLUGIN_URL')) {
    define('YITU_UPLOAD_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// 确保WooCommerce已经安装和激活
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// 加载主类
require_once YITU_UPLOAD_PLUGIN_DIR . 'includes/class-yitu-upload.php';


// 注册插件激活钩子，自动建表
register_activation_hook(__FILE__, function() {
    require_once YITU_UPLOAD_PLUGIN_DIR . 'includes/class-yitu-upload.php';
    $obj = new Yitu_Upload();
    $obj->activate();
});


// 初始化插件
function yitu_upload_init() {
    $plugin = new Yitu_Upload();
    $plugin->init();
}
add_action('plugins_loaded', 'yitu_upload_init'); 