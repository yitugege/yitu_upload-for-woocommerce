<?php
defined('ABSPATH') || exit;

/**
 * Yitu Upload前端界面类
 */
class Yitu_Upload_Frontend {
    /**
     * 构造函数
     */
    public function __construct() {
        //  感谢页面
        add_action('woocommerce_thankyou', [$this, 'add_upload_form_to_thankyou'], 10, 1);
        add_action('woocommerce_thankyou', array($this, 'display_uploaded_files_on_thankyou'), 20);
        // 处理AJAX上传
        add_action('wp_ajax_yitu_upload_file', [$this, 'handle_ajax_upload']);
        
        // 处理AJAX文件删除
        add_action('wp_ajax_yitu_delete_file', [$this, 'handle_file_delete']);
        
        // 添加订单页面的文件显示
        add_action('woocommerce_view_order', [$this, 'add_upload_form_to_thankyou'], 10, 1);
        add_action('woocommerce_view_order', array($this, 'display_uploaded_files_on_thankyou'), 20);
        
        // 加载前端脚本和样式
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * 在感谢页面添加上传表单
     */
    public function add_upload_form_to_thankyou($order_id) {
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // 获取设置
        $max_file_count = get_option('yitu_upload_max_file_count', 3);

        // 检查是否已上传
        global $wpdb;
        $uploaded_files = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}yitu_upload_files WHERE order_id = %d",
            $order_id
        ));
        
        $uploaded_count = count($uploaded_files);
        
        // 检查订单创建时间是否在48小时内
        $order_date = $order->get_date_created();
        $hours_passed = (time() - $order_date->getTimestamp()) / 3600;
        $within_time_limit = $hours_passed <= 48;

        // 获取允许上传的订单状态列表
        $allowed_statuses = array(
            'pending',    // 待付款
            'on-hold',    // 待确认
            'processing', // 处理中
            'completed'   // 已完成
        );

        // 检查是否可以继续上传
        $can_upload = $within_time_limit && 
                     $uploaded_count < $max_file_count && 
                     in_array($order->get_status(), $allowed_statuses);

        if ($can_upload) {
            ?>
            <div class="file-upload-section">
                <h2><?php esc_html_e('Envíe su recibo de pago', 'yitu-upload-wc'); ?></h2>
                <p><?php 
                    if ($uploaded_count > 0) {
                        printf(
                            esc_html__('Ya has subido %d archivos. Puedes subir %d archivos más (máximo %d).', 'yitu-upload-wc'),
                            $uploaded_count,
                            $max_file_count - $uploaded_count,
                            $max_file_count
                        );
                    } else {
                        printf(
                            esc_html__('Envíe su recibo de pago para calificar su pedido. Máximo %d archivos permitidos.', 'yitu-upload-wc'),
                            $max_file_count
                        );
                    }
                ?></p>
                
                <form id="file-upload-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                    <input type="hidden" name="security" value="<?php echo wp_create_nonce('yitu-file-upload'); ?>">
                    
                    <div class="form-row">
                        <label for="file_upload">
                            <?php esc_html_e('Select file', 'yitu-upload-wc'); ?>
                            <span class="description">
                                <?php 
                                printf(
                                    esc_html__('Allowed types: %s, Max size: %sMB', 'yitu-upload-wc'),
                                    esc_html(get_option('yitu_upload_allowed_file_types')),
                                    esc_html(get_option('yitu_upload_max_file_size'))
                                ); 
                                ?>
                            </span>
                        </label>
                        <input type="file" name="file_upload[]" id="file_upload" multiple required accept="image/*,application/pdf" 
                               max="<?php echo esc_attr($max_file_count - $uploaded_count); ?>">
                    </div>

                    <div class="form-row">
                        <button type="submit" class="button">
                            <?php 
                            if ($uploaded_count > 0) {
                                esc_html_e('Subir más archivos', 'yitu-upload-wc');
                            } else {
                                esc_html_e('Enviar', 'yitu-upload-wc');
                            }
                            ?>
                        </button>
                    </div>

                    <div class="upload-status" style="display: none;"></div>
                </form>
            </div>
            <?php
        } elseif (!$within_time_limit) {
            echo '<div class="woocommerce-notice woocommerce-notice--info">';
            echo esc_html__('El período de carga ha expirado (48 horas después de realizar el pedido).', 'yitu-upload-wc');
            echo '</div>';
        } elseif ($uploaded_count >= $max_file_count) {
            echo '<div class="woocommerce-notice woocommerce-notice--info">';
            echo esc_html__('Has alcanzado el número máximo de archivos permitidos.', 'yitu-upload-wc');
            echo '</div>';
        }
    }

    /**
     * 处理AJAX上传请求
     */
    public function handle_ajax_upload() {
        check_ajax_referer('yitu-file-upload', 'security');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        if (!$order_id) {
            wp_send_json_error(__('ID de pedido inválido', 'yitu-upload-wc'));
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(__('Pedido no encontrado', 'yitu-upload-wc'));
            return;
        }

        // 检查订单状态
        $allowed_statuses = array('pending', 'on-hold', 'processing', 'completed');
        if (!in_array($order->get_status(), $allowed_statuses)) {
            wp_send_json_error(__('El estado del pedido no permite subir archivos', 'yitu-upload-wc'));
            return;
        }

        if (!isset($_FILES['file_upload'])) {
            wp_send_json_error(__('No se ha subido ningún archivo', 'yitu-upload-wc'));
            return;
        }

        $files = $_FILES['file_upload'];
        $max_file_count = get_option('yitu_upload_max_file_count', 3);
        
        // 检查文件数量
        if (count($files['name']) > $max_file_count) {
            wp_send_json_error(sprintf(
                __('Máximo %d archivos permitidos.', 'yitu-upload-wc'),
                $max_file_count
            ));
            return;
        }

        // 验证文件类型
        $allowed_types = explode(',', get_option('yitu_upload_allowed_file_types'));
        foreach ($files['name'] as $file_name) {
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_types)) {
                wp_send_json_error(sprintf(
                    __('Tipo de archivo inválido. Tipos permitidos: %s', 'yitu-upload-wc'),
                    get_option('yitu_upload_allowed_file_types')
                ));
                return;
            }
        }

        // 验证文件大小
        $max_size = get_option('yitu_upload_max_file_size') * 1024 * 1024;
        foreach ($files['size'] as $file_size) {
            if ($file_size > $max_size) {
                wp_send_json_error(sprintf(
                    __('El archivo es demasiado grande. El tamaño máximo es %s MB.', 'yitu-upload-wc'),
                    get_option('yitu_upload_max_file_size')
                ));
                return;
            }
        }

        require_once YITU_UPLOAD_PLUGIN_DIR . 'includes/class-yitu-upload-oss.php';
        $oss = new Yitu_Upload_OSS();

        $upload_dir = wp_upload_dir();
        $uploaded_files = [];

        foreach ($files['name'] as $index => $file_name) {
            $filename = wp_unique_filename($upload_dir['path'], $file_name);
            $filepath = $upload_dir['path'] . '/' . $filename;

            // 移动上传的文件到临时目录
            if (!move_uploaded_file($files['tmp_name'][$index], $filepath)) {
                wp_send_json_error(__('Error al subir el archivo', 'yitu-upload-wc'));
                return;
            }

            // 上传到OSS
            $oss_url = $oss->upload_file($filepath, $filename, $order_id);
            if (!$oss_url) {
                @unlink($filepath);
                wp_send_json_error(__('Error al subir al OSS', 'yitu-upload-wc'));
                return;
            }

            // 获取签名URL
            $signed_url = $oss->get_signed_url($oss_url, 3600); // 1小时有效期
            if (!$signed_url) {
                wp_send_json_error(__('Error al generar la URL firmada', 'yitu-upload-wc'));
                return;
            }

            // 保存到数据库
            global $wpdb;
            $result = $wpdb->insert(
                $wpdb->prefix . 'yitu_upload_files',
                array(
                    'order_id' => $order_id,
                    'file_url' => $oss_url,
                    'file_name' => $filename,
                    'upload_time' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s')
            );

            // 删除临时文件
            @unlink($filepath);

            if ($result === false) {
                wp_send_json_error(__('Error al guardar la información del archivo', 'yitu-upload-wc'));
                return;
            }

            // 添加订单备注
            $order->add_order_note(
                sprintf(__('Recibo de pago subido: %s', 'yitu-upload-wc'), $oss_url)
            );

            $uploaded_files[] = [
                'url' => $oss_url,
                'signed_url' => $signed_url,
                'name' => $filename
            ];
        }

        wp_send_json_success([
            'message' => __('Recibos de pago subidos correctamente', 'yitu-upload-wc'),
            'files' => $uploaded_files
        ]);
    }

    /**
     * 加载前端脚本和样式
     */
    public function enqueue_scripts() {
        // 检查是否在感谢页面或订单查看页面
        if (!is_wc_endpoint_url('order-received') && !is_wc_endpoint_url('view-order')) {
            return;
        }

        wp_enqueue_style(
            'yitu-upload-style',
            YITU_UPLOAD_PLUGIN_URL . 'assets/css/upload-file.css',
            array(),
            YITU_UPLOAD_VERSION
        );

        wp_enqueue_script(
            'yitu-upload-script',
            YITU_UPLOAD_PLUGIN_URL . 'assets/js/upload-file.js',
            array('jquery'),
            YITU_UPLOAD_VERSION,
            true
        );

        wp_localize_script('yitu-upload-script', 'yitu_upload', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'max_size' => get_option('yitu_upload_max_file_size') * 1024 * 1024,
            'max_files' => get_option('yitu_upload_max_file_count', 3),
            'allowed_types' => explode(',', get_option('yitu_upload_allowed_file_types')),
            'messages' => array(
                'uploading' => __('Subiendo...', 'yitu-upload-wc'),
                'error' => __('Error al subir. Por favor, inténtelo de nuevo.', 'yitu-upload-wc')
            ),
            'error_messages' => array(
                'max_size' => sprintf(
                    __('El archivo es demasiado grande. El tamaño máximo es %s MB.', 'yitu-upload-wc'),
                    get_option('yitu_upload_max_file_size')
                ),
                'invalid_type' => sprintf(
                    __('Tipo de archivo inválido. Tipos permitidos: %s', 'yitu-upload-wc'),
                    get_option('yitu_upload_allowed_file_types')
                ),
                'required' => __('Por favor, seleccione un archivo para subir.', 'yitu-upload-wc'),
                'max_files' => sprintf(
                    __('Máximo %d archivos permitidos.', 'yitu-upload-wc'),
                    get_option('yitu_upload_max_file_count', 3)
                )
            )
        ));
    }

    /**
     * 在感谢页面显示上传的文件记录
     */
    public function display_uploaded_files_on_thankyou($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        // 从数据库表中获取上传的文件记录
        global $wpdb;
        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}yitu_upload_files WHERE order_id = %d ORDER BY upload_time DESC",
            $order_id
        ));
        
        if (!empty($files)) {
            echo '<section class="woocommerce-order-uploads">';
            echo '<h2 class="woocommerce-order-uploads__title">' . esc_html__('Comprobante de pago', 'yitu-upload-wc') . '</h2>';
            echo '<div class="yitu-uploaded-files">';
            echo '<ul class="yitu-files-list">';
            
            foreach ($files as $file) {
                // 只允许订单所有者或管理员查看和删除文件
                if ($order->get_customer_id() === get_current_user_id() || current_user_can('manage_woocommerce')) {
                    // 获取签名后的URL
                    require_once YITU_UPLOAD_PLUGIN_DIR . 'includes/class-yitu-upload-oss.php';
                    $oss = new Yitu_Upload_OSS();
                    $signed_url = $oss->get_signed_url($file->file_url, 300); // 5分钟有效期
                    
                    // 获取文件扩展名
                    $file_ext = strtolower(pathinfo($file->file_name, PATHINFO_EXTENSION));
                    
                    echo '<li class="yitu-file-item" data-file-id="' . esc_attr($file->id) . '">';
                    echo '<div class="file-info">';
                    echo '<span class="file-name">' . esc_html($file->file_name) . '</span>';
                    echo '<span class="file-time">' . esc_html(wp_date(
                        get_option('date_format') . ' ' . get_option('time_format'), 
                        strtotime($file->upload_time)
                    )) . '</span>';
                    echo '</div>';
                    
                    if ($signed_url) {
                        echo '<div class="file-preview">';
                        if ($file_ext === 'pdf') {
                            // PDF文件显示
                            echo '<div class="pdf-preview">';
                            echo '<div class="pdf-icon"><i class="dashicons dashicons-pdf"></i></div>';
                            echo '<a href="' . esc_url($signed_url) . '" class="pdf-link" target="_blank">';
                            echo esc_html__('Ver PDF', 'yitu-upload-wc');
                            echo '</a>';
                            echo '</div>';
                        } else {
                            // 图片文件显示
                            echo '<a href="' . esc_url($signed_url) . '" class="preview-link" data-fancybox="gallery">';
                            echo '<img src="' . esc_url($signed_url) . '" class="preview-image" alt="' . esc_attr($file->file_name) . '" />';
                            echo '</a>';
                        }
                        echo '</div>';
                        
                        // 添加删除按钮
                        echo '<div class="file-actions">';
                        echo '<button type="button" class="delete-file button" data-file-id="' . esc_attr($file->id) . '" data-nonce="' . wp_create_nonce('delete_file_' . $file->id) . '">';
                        echo esc_html__('Eliminar', 'yitu-upload-wc');
                        echo '</button>';
                        echo '</div>';
                    } else {
                        echo '<p class="error">' . esc_html__('Error al cargar el archivo', 'yitu-upload-wc') . '</p>';
                    }
                    echo '</li>';
                }
            }
            
            echo '</ul>';
            echo '</div>';
            
            // 添加样式
            echo '<style>
                .yitu-uploaded-files {
                    margin: 20px 0;
                    padding: 15px;
                    background: #f8f8f8;
                    border-radius: 4px;
                }
                .yitu-files-list {
                    list-style: none;
                    margin: 0;
                    padding: 0;
                }
                .yitu-file-item {
                    padding: 15px;
                    margin-bottom: 15px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    background: #fff;
                }
                .file-info {
                    margin-bottom: 10px;
                }
                .file-name {
                    font-weight: 500;
                    margin-right: 15px;
                }
                .file-time {
                    color: #666;
                    font-size: 0.9em;
                }
                .file-preview {
                    margin: 10px 0;
                }
                .preview-image {
                    max-width: 200px;
                    max-height: 200px;
                    cursor: pointer;
                    border-radius: 4px;
                    transition: transform 0.2s;
                }
                .preview-image:hover {
                    transform: scale(1.05);
                }
                .pdf-preview {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    padding: 15px;
                    background: #f5f5f5;
                    border-radius: 4px;
                }
                .pdf-icon {
                    width: 40px;
                    height: 40px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .pdf-icon .dashicons-pdf {
                    font-size: 32px;
                    width: 32px;
                    height: 32px;
                    color: #e74c3c;
                }
                .pdf-link {
                    color: #333;
                    text-decoration: none;
                    font-weight: 500;
                    padding: 8px 16px;
                    background: #fff;
                    border-radius: 4px;
                    transition: all 0.2s ease;
                }
                .pdf-link:hover {
                    background: #e74c3c;
                    color: #fff;
                    text-decoration: none;
                }
                .file-actions {
                    margin-top: 10px;
                }
                .delete-file {
                    background-color: #dc3545 !important;
                    color: #fff !important;
                    border: none !important;
                    padding: 5px 15px !important;
                    border-radius: 3px !important;
                }
                .delete-file:hover {
                    background-color: #c82333 !important;
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

                    // 处理文件删除
                    $(".delete-file").on("click", function(e) {
                        e.preventDefault();
                        if (!confirm("' . esc_js(__('¿Está seguro de que desea eliminar este archivo?', 'yitu-upload-wc')) . '")) {
                            return;
                        }
                        
                        var $button = $(this);
                        var fileId = $button.data("file-id");
                        var nonce = $button.data("nonce");
                        
                        $.ajax({
                            url: "' . admin_url('admin-ajax.php') . '",
                            type: "POST",
                            data: {
                                action: "yitu_delete_file",
                                file_id: fileId,
                                nonce: nonce
                            },
                            beforeSend: function() {
                                $button.prop("disabled", true);
                            },
                            success: function(response) {
                                if (response.success) {
                                    $button.closest(".yitu-file-item").fadeOut(function() {
                                        $(this).remove();
                                        if ($(".yitu-file-item").length === 0) {
                                            location.reload();
                                        }
                                    });
                                } else {
                                    alert(response.data || "' . esc_js(__('Error al eliminar el archivo', 'yitu-upload-wc')) . '");
                                }
                            },
                            error: function() {
                                alert("' . esc_js(__('Error al eliminar el archivo', 'yitu-upload-wc')) . '");
                            },
                            complete: function() {
                                $button.prop("disabled", false);
                            }
                        });
                    });
                });
            ');
        }
    }

    /**
     * 处理文件删除的AJAX请求
     */
    public function handle_file_delete() {
        $file_id = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;
        
        // 验证nonce
        if (!check_ajax_referer('delete_file_' . $file_id, 'nonce', false)) {
            wp_send_json_error(__('Error de seguridad', 'yitu-upload-wc'));
            return;
        }

        // 获取文件信息
        global $wpdb;
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}yitu_upload_files WHERE id = %d",
            $file_id
        ));

        if (!$file) {
            wp_send_json_error(__('Archivo no encontrado', 'yitu-upload-wc'));
            return;
        }

        // 检查权限
        $order = wc_get_order($file->order_id);
        if (!$order || ($order->get_customer_id() !== get_current_user_id() && !current_user_can('manage_woocommerce'))) {
            wp_send_json_error(__('No tiene permiso para eliminar este archivo', 'yitu-upload-wc'));
            return;
        }

        // 从OSS删除文件
        require_once YITU_UPLOAD_PLUGIN_DIR . 'includes/class-yitu-upload-oss.php';
        $oss = new Yitu_Upload_OSS();
        $oss->delete_file($file->file_url);

        // 从数据库删除记录
        $wpdb->delete(
            $wpdb->prefix . 'yitu_upload_files',
            array('id' => $file_id),
            array('%d')
        );

        wp_send_json_success();
        $this->display_uploaded_files_on_thankyou($file->order_id);
    }
} 