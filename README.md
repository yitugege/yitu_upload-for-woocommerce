# Yitu Upload for WooCommerce

一个专门为 WooCommerce 开发的支付凭证上传插件，支持阿里云 OSS 存储。

## 功能特点

- 支持在订单完成页面和订单详情页面上传支付凭证
- 支持多文件上传（可配置最大数量）
- 支持图片预览和文件管理
- 集成阿里云 OSS 存储
- 支持文件类型和大小限制
- 支持48小时内上传限制
- 支持订单状态控制
- 多语言支持（默认支持西班牙语和中文）
- 美观的现代界面设计
- 完整的后台管理功能

## 系统要求

- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.2+
- MySQL 5.6+

## 安装方法

1. 下载插件压缩包
2. 在 WordPress 后台进入"插件"页面，点击"添加插件"
3. 选择"上传插件"，选择下载的压缩包
4. 安装完成后启用插件

## 配置说明

1. 在 WordPress 后台进入 WooCommerce → Yitu Upload 设置页面
2. 配置以下参数：
   - 阿里云 AccessKey ID
   - 阿里云 AccessKey Secret
   - OSS Endpoint
   - OSS Bucket
   - 允许的文件类型
   - 最大文件大小
   - 最大文件数量

## 使用说明

### 客户端

1. 客户完成订单后，在订单感谢页面可以上传支付凭证
2. 在订单详情页面也可以上传和管理支付凭证
3. 上传限制：
   - 订单创建后48小时内可以上传
   - 文件数量不超过设置的最大值
   - 文件类型必须符合设置要求
   - 文件大小不超过限制

### 管理员

1. 在订单详情页面可以查看客户上传的支付凭证
2. 支付凭证以图片形式显示，支持放大预览
3. 可以在订单备注中看到上传记录

## 开发说明

### 目录结构

```
yitu-upload-for-woocommerce/
├── assets/
│   ├── css/
│   │   └── upload-file.css
│   └── js/
│       └── upload-file.js
├── includes/
│   ├── class-yitu-upload-admin.php
│   ├── class-yitu-upload-frontend.php
│   └── class-yitu-upload-oss.php
├── languages/
│   ├── yitu-upload-wc-es_ES.po
│   └── yitu-upload-wc-zh_CN.po
├── README.md
└── yitu-upload-for-woocommerce.php
```

### 主要类说明

- `Yitu_Upload_Admin`: 管理后台相关功能
- `Yitu_Upload_Frontend`: 前端显示和上传处理
- `Yitu_Upload_OSS`: 阿里云 OSS 集成

## 更新日志

### 1.0.0
- 初始版本发布
- 基础上传功能
- OSS 存储集成
- 后台管理功能

## 贡献指南

欢迎提交 Issues 和 Pull Requests 来改进这个插件。

## 许可证

GPL v2 或更高版本 