<?php
defined('ABSPATH') || exit;

/**
 * Yitu Upload管理员界面类
 */
class Yitu_Upload_Admin {
    /**
     * 构造函数
     */
    public function __construct() {
        // 添加管理菜单
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // 注册设置
        add_action('admin_init', [$this, 'register_settings']);

        // HPOS兼容性声明
        add_action('before_woocommerce_init', function() {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });

        // 添加订单列
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_upload_file_column']);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'display_order_upload_file'], 10, 2);

        // 添加订单元数据框
       // add_action('add_meta_boxes', [$this, 'add_order_meta_boxes']);
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Yitu Upload Settings', 'yitu-upload-wc'),
            __('Yitu Upload', 'yitu-upload-wc'),
            'manage_woocommerce',
            'yitu-upload-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * 注册设置
     */
    public function register_settings() {
        register_setting('yitu_upload_settings', 'yitu_upload_access_key_id');
        register_setting('yitu_upload_settings', 'yitu_upload_access_key_secret');
        register_setting('yitu_upload_settings', 'yitu_upload_endpoint');
        register_setting('yitu_upload_settings', 'yitu_upload_bucket');
        register_setting('yitu_upload_settings', 'yitu_upload_allowed_file_types');
        register_setting('yitu_upload_settings', 'yitu_upload_max_file_size');
        register_setting('yitu_upload_settings', 'yitu_upload_max_file_count');
    }

    /**
     * 渲染设置页面
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Yitu Upload Settings', 'yitu-upload-wc'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('yitu_upload_settings');
                do_settings_sections('yitu_upload_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Access Key ID', 'yitu-upload-wc'); ?></th>
                        <td>
                            <input type="text" name="yitu_upload_access_key_id" 
                                value="<?php echo esc_attr(get_option('yitu_upload_access_key_id')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Access Key Secret', 'yitu-upload-wc'); ?></th>
                        <td>
                            <input type="password" name="yitu_upload_access_key_secret" 
                                value="<?php echo esc_attr(get_option('yitu_upload_access_key_secret')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Endpoint', 'yitu-upload-wc'); ?></th>
                        <td>
                            <input type="text" name="yitu_upload_endpoint" 
                                value="<?php echo esc_attr(get_option('yitu_upload_endpoint')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Bucket', 'yitu-upload-wc'); ?></th>
                        <td>
                            <input type="text" name="yitu_upload_bucket" 
                                value="<?php echo esc_attr(get_option('yitu_upload_bucket')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Allowed File Types', 'yitu-upload-wc'); ?></th>
                        <td>
                            <input type="text" name="yitu_upload_allowed_file_types" 
                                value="<?php echo esc_attr(get_option('yitu_upload_allowed_file_types')); ?>" class="regular-text">
                            <p class="description"><?php echo esc_html__('Comma separated file extensions (e.g. jpg,jpeg,png,pdf)', 'yitu-upload-wc'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Max File Size (MB)', 'yitu-upload-wc'); ?></th>
                        <td>
                            <input type="number" name="yitu_upload_max_file_size" 
                                value="<?php echo esc_attr(get_option('yitu_upload_max_file_size')); ?>" class="small-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Max File Count', 'yitu-upload-wc'); ?></th>
                        <td>
                            <input type="number" name="yitu_upload_max_file_count" 
                                value="<?php echo esc_attr(get_option('yitu_upload_max_file_count')); ?>" class="small-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * 添加订单支付凭证列
     */
    public function add_upload_file_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ($key === 'order_status') {
                $new_columns['upload_file'] = __('Comprobante de pago', 'yitu-upload-wc');
            }
        }
        return $new_columns;
    }

    /**
     * 显示订单支付凭证
     */
    public function display_order_upload_file($column, $order) {
        if ($column !== 'upload_file') {
            return;
        }

        if (!$order instanceof WC_Order) {
            $order = wc_get_order($order);
        }

        if (!$order) {
            return;
        }

        // 获取订单ID
        $order_id = $order->get_id();
        
        // 从数据库获取上传的文件记录
        global $wpdb;
        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}yitu_upload_files WHERE order_id = %d ORDER BY upload_time DESC",
            $order_id
        ));
        
        if (!empty($files)) {
            echo '<div class="yitu-uploaded-files">';
            echo '<ul class="yitu-files-list">';
            
            foreach ($files as $file) {
                // 获取签名后的URL
                require_once YITU_UPLOAD_PLUGIN_DIR . 'includes/class-yitu-upload-oss.php';
                $oss = new Yitu_Upload_OSS();
                $signed_url = $oss->get_signed_url($file->file_url, 300); // 5分钟有效期
                
                // 获取文件扩展名
                $file_ext = strtolower(pathinfo($file->file_name, PATHINFO_EXTENSION));
                
                echo '<li class="yitu-file-item">';
                if ($signed_url) {
                    if ($file_ext === 'pdf') {
                        // PDF文件显示
                        echo '<div class="pdf-preview" onclick="window.open(\'' . esc_url($signed_url) . '\', \'_blank\');">';
                        echo '<div class="pdf-icon"><i class="dashicons dashicons-pdf"></i></div>';
                        echo '<span class="pdf-text">' . esc_html__('Ver PDF', 'yitu-upload-wc') . '</span>';
                        echo '</div>';
                    } else {
                        // 图片文件显示
                        echo '<a href="' . esc_url($signed_url) . '" class="preview-link" data-fancybox="gallery" title="' . esc_attr($file->file_name) . '">';
                        echo '<img src="' . esc_url($signed_url) . '" class="preview-image" alt="' . esc_attr($file->file_name) . '" />';
                        echo '</a>';
                    }
                } else {
                    echo '<p class="error">' . esc_html__('Error al cargar el archivo', 'yitu-upload-wc') . '</p>';
                }
                echo '</li>';
            }
            
            echo '</ul>';
            echo '</div>';
            
            // 添加样式
            echo '<style>
                .yitu-uploaded-files {
                    margin: 0;
                    padding: 0;
                }
                .yitu-files-list {
                    list-style: none;
                    margin: 0;
                    padding: 0;
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                }
                .yitu-file-item {
                    margin: 0;
                    padding: 0;
                    border: none;
                    background: none;
                }
                .preview-link {
                    display: block;
                    width: 50px;
                    height: 50px;
                    border-radius: 4px;
                    overflow: hidden;
                    border: 1px solid #ddd;
                    transition: all 0.2s ease;
                }
                .preview-link:hover {
                    border-color: #2271b1;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .preview-image {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    display: block;
                }
                .pdf-preview {
                    width: 50px;
                    height: 50px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    background: #f5f5f5;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    transition: all 0.2s ease;
                    cursor: pointer;
                }
                .pdf-preview:hover {
                    border-color: #2271b1;
                    background: #fff;
                }
                .pdf-icon {
                    width: 24px;
                    height: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-bottom: 2px;
                }
                .pdf-icon .dashicons-pdf {
                    font-size: 24px;
                    width: 24px;
                    height: 24px;
                    color: #e74c3c;
                }
                .pdf-text {
                    font-size: 10px;
                    color: #666;
                    text-align: center;
                    line-height: 1;
                }
                .error {
                    color: #dc3545;
                    font-size: 12px;
                    margin: 0;
                    padding: 4px;
                    background: #fff;
                    border: 1px solid #dc3545;
                    border-radius: 4px;
                }
            </style>';

            // 添加JavaScript
            wp_enqueue_script('fancybox', 'https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js', array('jquery'), '5.0', true);
            wp_enqueue_style('fancybox', 'https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css');
            wp_enqueue_style('dashicons');
            
            wp_add_inline_script('fancybox', '
                jQuery(document).ready(function($) {
                    // 初始化Fancybox
                    Fancybox.bind("[data-fancybox]", {
                        // 配置项
                    });
                });
            ');
        }
    }

    /**
     * 添加订单元数据框
     */
    public function add_order_meta_boxes() {
        add_meta_box(
            'yitu_upload_upload_file',
            __('Comprobante de pago', 'yitu-upload-wc'),
            [$this, 'render_upload_file_meta_box'],
            'shop_order',
            'side',
            'default'
        );
    }

    /**
     * 渲染上传文件元框
     */
    public function render_upload_file_meta_box($post) {
        $order = wc_get_order($post);
        if (!$order) {
            return;
        }

        global $wpdb;
        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}yitu_upload_files WHERE order_id = %d",
            $order->get_id()
        ));

        if (false) {
            // 加载Fancybox资源
            wp_enqueue_script('fancybox', 'https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js', array('jquery'), '5.0', true);
            wp_enqueue_style('fancybox', 'https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css');

            echo '<div class="upload-file-images">';
            foreach ($files as $file) {
                // 加载OSS类
                require_once YITU_UPLOAD_PLUGIN_DIR . 'includes/class-yitu-upload-oss.php';
                $oss = new Yitu_Upload_OSS();
                $signed_url = $oss->get_signed_url($file->file_url, 300); // 5分钟有效期
                if ($signed_url) {
                    echo '<div class="upload-file-image">';
                    echo '<a href="' . esc_url($signed_url) . '" data-fancybox="gallery" data-caption="' . esc_attr($file->file_name) . '">';
                    echo '<img src="' . esc_url($signed_url) . '" style="max-width: 150px; height: auto; border-radius: 4px; cursor: pointer;" />';
                    echo '</a>';
                    echo '<p class="filename">' . esc_html($file->file_name) . '</p>';
                    echo '<p class="upload-time">' . esc_html(wp_date(
                        get_option('date_format') . ' ' . get_option('time_format'),
                        strtotime($file->upload_time)
                    )) . '</p>';
                    echo '</div>';
                } else {
                    echo '<p class="error">' . esc_html__('Error loading image', 'yitu-upload-wc') . '</p>';
                }
            }
            echo '</div>';

            // 添加样式
            echo '<style>
                .upload-file-images {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                    gap: 20px;
                    padding: 15px;
                }
                .upload-file-image {
                    background: #fff;
                    padding: 10px;
                    border-radius: 6px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    text-align: center;
                }
                .upload-file-image img {
                    transition: transform 0.2s;
                    display: block;
                    margin: 0 auto;
                }
                .upload-file-image img:hover {
                    transform: scale(1.05);
                }
                .upload-file-image .filename {
                    margin: 8px 0 4px;
                    font-weight: 500;
                    font-size: 13px;
                    color: #333;
                    word-break: break-all;
                }
                .upload-file-image .upload-time {
                    margin: 4px 0;
                    font-size: 12px;
                    color: #666;
                }
                .error {
                    color: #dc3545;
                    margin: 10px 0;
                }
            </style>';

            // 初始化Fancybox
            wp_add_inline_script('fancybox', '
                jQuery(document).ready(function($) {
                    Fancybox.bind("[data-fancybox]", {
                        // 配置Fancybox选项
                        Carousel: {
                            infinite: false
                        },
                        Thumbs: {
                            autoStart: false
                        }
                    });
                });
            ');
        } else {
            echo '<p>' . __('No comprobante de pago subido todavía.', 'yitu-upload-wc') . '</p>';
        }
    }
} 