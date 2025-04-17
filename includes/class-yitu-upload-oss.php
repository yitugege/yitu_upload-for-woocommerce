<?php
defined('ABSPATH') || exit;

/**
 * Yitu Upload OSS操作类
 */
class Yitu_Upload_OSS {
    /**
     * OSS客户端实例
     */
    private $ossClient;

    /**
     * 构造函数
     */
    public function __construct() {
        // 加载阿里云OSS SDK
        require_once YITU_UPLOAD_PLUGIN_DIR . 'vendor/autoload.php';

        $access_key_id = get_option('yitu_upload_access_key_id');
        $access_key_secret = get_option('yitu_upload_access_key_secret');
        $endpoint = get_option('yitu_upload_endpoint');

        try {
            $this->ossClient = new OSS\OssClient($access_key_id, $access_key_secret, $endpoint);
        } catch (OSS\Core\OssException $e) {
            error_log('Error creating OSS client: ' . $e->getMessage());
        }

        // 注册AJAX接口
        add_action('wp_ajax_yitu_get_signed_url', [$this, 'get_signed_url']);
        add_action('wp_ajax_nopriv_yitu_get_signed_url', [$this, 'get_signed_url']);
    }

    /**
     * 上传文件到OSS
     * 
     * @param string $filepath 本地文件路径
     * @param string $filename 文件名
     * @param int $orderid 订单ID
     * @return string|false 成功返回文件URL，失败返回false
     */
    public function upload_file($filepath, $filename,$orderid) {
        if (!$this->ossClient) {
            return false;
        }

        $bucket = get_option('yitu_upload_bucket');
        
        // 构建OSS对象路径：uploads/订单ID_时间戳_文件名
        $object = 'uploads/' . $orderid . '_' . $filename;

        try {
            $this->ossClient->uploadFile($bucket, $object, $filepath);
            return $object;
        } catch (OSS\Core\OssException $e) {
            error_log('Error uploading file to OSS: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取带签名的临时访问URL
     * @param string $object OSS对象名称
     * @param int $timeout 有效期（秒）
     * @return string|false
     */
    public function get_signed_url($object, $timeout = 300) {
        if (!$this->ossClient) {
            return false;
        }
        $bucket = get_option('yitu_upload_bucket');
        try {
            // 生成带签名的临时URL
            return $this->ossClient->signUrl($bucket, $object, $timeout);
        } catch (OSS\Core\OssException $e) {
            error_log('Error generating signed URL: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 处理AJAX请求获取OSS文件的带签名URL
     */
    public function ajax_get_oss_url() {
        if (empty($_POST['object'])) {
            wp_send_json_error('Missing object');
        }

        $object = sanitize_text_field($_POST['object']);
        $signed_url = $this->get_signed_url($object, 7200); // 2小时有效期

        if ($signed_url) {
            wp_send_json_success(['signed_url' => $signed_url]);
        } else {
            wp_send_json_error('Failed to get signed url');
        }
    }

    /**
     * 删除OSS文件
     * 
     * @param string $object OSS对象名称
     * @return bool 删除成功返回true，失败返回false
     */
    public function delete_file($object) {
        if (!$this->ossClient) {
            return false;
        }

        try {
            $bucket = get_option('yitu_upload_bucket');
            $this->ossClient->deleteObject($bucket, $object);
            return true;
        } catch (OSS\Core\OssException $e) {
            error_log('Error deleting file from OSS: ' . $e->getMessage());
            return false;
        }
    }
} 