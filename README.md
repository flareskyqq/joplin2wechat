# Joplin to WeChat Share

将 Joplin 笔记分享到微信的完整解决方案。

## 项目结构

```
joplin_share/
├── joplin-plugin/     # Joplin 插件 - 一键分享笔记
├── php-service/       # PHP 服务 - 转换页面为微信友好格式
└── docs/              # 设计文档
```

## 工作原理

```
Joplin 笔记 → [Joplin 插件] → 生成分享链接
                                    ↓
                            PHP 服务抓取页面
                                    ↓
                            转换为微信友好格式
                                    ↓
                                微信分享
```

1. **Joplin 插件** (`joplin-plugin/`)：在 Joplin 中点击分享按钮，将当前笔记发布并生成分享链接
2. **PHP 服务** (`php-service/`)：代理 Joplin 分享页面，优化为微信友好的展示格式，支持微信内置浏览器预览

## 快速开始

### 1. 安装 Joplin 插件

```bash
cd joplin-plugin
npm install
npm run dist
```

生成的插件包在 `publish/` 目录，可通过 Joplin 设置导入。

### 2. 部署 PHP 服务

将 `php-service/` 部署到支持 PHP 的服务器：

```bash
# 访问方式
# https://your-domain.com/?share_id=<joplin-share-id>
```

### 3. 配置

在 `php-service/index.php` 中修改 Joplin 服务器地址：

```php
define('JOPLIN_BASE_URL', 'https://home.flaresky.top:8443');
```

## 技术栈

- **Joplin 插件**：TypeScript + Webpack
- **PHP 服务**：纯 PHP（无依赖），兼容微信内置浏览器

## License

MIT