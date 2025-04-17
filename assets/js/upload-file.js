jQuery(function($) {
    'use strict';

    var $form = $('#file-upload-form');
    if (!$form.length) {
        return;
    }

    var $uploadField = $('#file_upload');
    var $uploadStatus = $('.upload-status');
    var $submitButton = $form.find('button[type="submit"]');
    var MAX_FILES = yitu_upload.max_files || 3; // 从后端设置获取最大文件数
    var MAX_SIZE = yitu_upload.max_size || 1048576; // 从后端设置获取最大文件大小
    var ALLOWED_TYPES = yitu_upload.allowed_types || ['jpg', 'jpeg', 'png', 'gif', 'webp','pdf']; // 从后端设置获取允许的文件类型

    // 文件选择事件处理
    $uploadField.on('change', function(e) {
        var files = this.files;
        
        if (!files.length) {
            return;
        }

        // 检查文件数量
        if (files.length > MAX_FILES) {
            $uploadStatus.html(yitu_upload.error_messages.max_files).show();
            this.value = '';
            return;
        }

        // 验证每个文件
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            
            // 验证文件大小
            if (file.size > MAX_SIZE) {
                $uploadStatus.html(yitu_upload.error_messages.max_size).show();
                this.value = '';
                return;
            }

            // 验证文件类型
            var ext = file.name.split('.').pop().toLowerCase();
            if ($.inArray(ext, ALLOWED_TYPES) === -1) {
                $uploadStatus.html(yitu_upload.error_messages.invalid_type).show();
                this.value = '';
                return;
            }
        }

        // 显示选择的文件预览
        var $previewContainer = $('.file-preview-container');
        if (!$previewContainer.length) {
            $previewContainer = $('<div class="file-preview-container"></div>');
            $(this).after($previewContainer);
        }
        $previewContainer.empty();

        // 添加文件数量提示
        $previewContainer.append('<div class="file-count">' + 
            files.length + ' / ' + MAX_FILES + ' ' + 
            (files.length === 1 ? 'archivo seleccionado' : 'archivos seleccionados') + 
            '</div>');

        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            var reader = new FileReader();
            
            reader.onload = (function(file) {
                return function(e) {
                    var ext = file.name.split('.').pop().toLowerCase();
                    var $preview = $('<div class="file-preview-item" style="margin: 10px 0;">');
                    
                    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].indexOf(ext) !== -1) {
                        $preview.append('<img src="' + e.target.result + '" style="max-width: 150px; max-height: 150px; margin-right: 10px;" />');
                    } else if (ext === 'pdf') {
                        $preview.append('<div class="file-icon pdf" style="width: 150px; height: 150px; margin-right: 10px; background: #f5f5f5; display: flex; align-items: center; justify-content: center; font-size: 24px;">PDF</div>');
                    }
                    
                    $preview.append('<span>' + file.name + '</span>');
                    $previewContainer.append($preview);
                };
            })(file);
            
            reader.readAsDataURL(file);
        }
    });

    // 表单提交处理
    $form.on('submit', function(e) {
        e.preventDefault();

        if (!$uploadField.val()) {
            $uploadStatus.html(yitu_upload.error_messages.required).show();
            return;
        }

        var formData = new FormData(this);
        formData.append('action', 'yitu_upload_file');

        $submitButton.prop('disabled', true);
        $uploadStatus
            .removeClass('error success')
            .html('<p>' + yitu_upload.messages.uploading + '</p>')
            .show();

        $.ajax({
            url: yitu_upload.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Upload response:', response);
                if (response.success) {
                    $uploadStatus
                        .removeClass('error')
                        .addClass('success')
                        .html('<p>' + response.data.message + '</p>');
                    
                    if (response.data.files && response.data.files.length > 0) {
                        response.data.files.forEach(function(file) {
                            console.log('Processing file:', file);
                            var ext = file.name.split('.').pop().toLowerCase();
                            if (['jpg','jpeg','png','gif','webp'].indexOf(ext) !== -1) {
                                $uploadStatus.append('<img src="' + file.signed_url + '" style="max-width: 200px; margin-top: 10px;" />');
                            } else if (ext === 'pdf') {
                                $uploadStatus.append('<div class="file-success"><i class="dashicons dashicons-pdf"></i> <a href="' + file.signed_url + '" target="_blank">' + file.name + '</a></div>');
                            }
                        });
                    }

                    // 隐藏上传表单
                    $form.find('.form-row').hide();

                    // 清空文件输入框
                    $uploadField.val('');

                    // 延迟1秒后刷新页面，让用户看到成功消息
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $uploadStatus
                        .removeClass('success')
                        .addClass('error')
                        .html('<p>' + (response.data || yitu_upload.messages.error) + '</p>');
                }
                $submitButton.prop('disabled', false);
            },
            error: function(xhr, status, error) {
                console.error('Upload error:', status, error);
                $uploadStatus
                    .removeClass('success')
                    .addClass('error')
                    .html('<p>' + yitu_upload.messages.error + '</p>');
                $submitButton.prop('disabled', false);
            }
        });
    });

    // 添加拖放支持
    $uploadField.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover');
    });

    $uploadField.on('dragleave drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
    });

    // 文件上传表单处理
    $('#yitu-file-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $fileInput = $form.find('input[type="file"]');
        const $submitButton = $form.find('button[type="submit"]');
        const $status = $form.find('.upload-status');
        
        // 验证文件
        if (!$fileInput[0].files.length) {
            alert('Por favor seleccione un archivo');
            return;
        }

        const file = $fileInput[0].files[0];
        const orderId = $form.find('input[name="order_id"]').val();

        // 禁用提交按钮
        $submitButton.prop('disabled', true);
        $status.html('<p>Preparando carga...</p>');

        // 获取上传凭证
        $.ajax({
            url: yitu_upload.ajax_url,
            type: 'POST',
            data: {
                action: 'yitu_get_upload_credentials',
                security: yitu_upload.nonce,
                order_id: orderId,
                filename: file.name
            },
            success: function(response) {
                if (response.success && response.data.credentials) {
                    uploadToOSS(file, response.data.credentials, orderId);
                } else {
                    handleError(response.data || 'Error al obtener credenciales');
                }
            },
            error: function() {
                handleError('Error de conexión');
            }
        });
    });

    // 上传文件到OSS
    function uploadToOSS(file, credentials, orderId) {
        const $status = $('.upload-status');
        const formData = new FormData();
        
        // 添加OSS所需的字段
        formData.append('key', credentials.object_path);
        formData.append('policy', credentials.policy);
        formData.append('OSSAccessKeyId', credentials.access_id);
        formData.append('success_action_status', '200');
        formData.append('signature', credentials.signature);
        formData.append('file', file);

        // 上传到OSS
        $.ajax({
            url: credentials.host,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function() {
                // 保存文件信息到数据库
                saveFileInfo(credentials.object_path, file.name, orderId);
            },
            error: function() {
                handleError('Error al subir el archivo a OSS');
            }
        });
    }

    // 保存文件信息到数据库
    function saveFileInfo(objectPath, fileName, orderId) {
        $.ajax({
            url: yitu_upload.ajax_url,
            type: 'POST',
            data: {
                action: 'yitu_save_uploaded_file',
                security: yitu_upload.nonce,
                order_id: orderId,
                object_path: objectPath,
                file_name: fileName
            },
            success: function(response) {
                if (response.success) {
                    handleSuccess(response.data);
                } else {
                    handleError(response.data || 'Error al guardar la información del archivo');
                }
            },
            error: function() {
                handleError('Error de conexión');
            }
        });
    }

    // 处理成功
    function handleSuccess(data) {
        const $status = $('.upload-status');
        $status.html(`
            <div class="upload-success">
                <p>${data.message}</p>
                <div class="file-preview">
                    <img src="${data.signed_url}" alt="${data.file_name}" style="max-width: 200px;">
                </div>
            </div>
        `);
        $('button[type="submit"]').prop('disabled', false);
    }

    // 处理错误
    function handleError(message) {
        const $status = $('.upload-status');
        $status.html(`<p class="upload-error">${message}</p>`);
        $('button[type="submit"]').prop('disabled', false);
    }
}); 