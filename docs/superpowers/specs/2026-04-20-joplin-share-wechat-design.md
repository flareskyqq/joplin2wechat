# Joplin 分享到微信美化 — 设计文档

## 概述

目标：让 Joplin 笔记分享到微信时，呈现为精美的公众号风格网页，而非裸露的链接。

用户操作极简：在 Joplin 复制分享链接 → 插件自动转换为美化链接 → 直接发送微信

## 工作流程

```
Joplin 笔记 → 点"复制共享链接"
    ↓
插件检测到 Joplin 分享链接 (https://home.flaresky.top:8443/shares/{shareID})
    ↓
提取 shareID，拼成美化链接 http://stock.flaresky.top:40080/fs/{shareID}
    ↓
复制到剪贴板
    ↓
用户发送美化链接到微信
    ↓
对方打开链接
    ↓
PHP 服务抓取原始 Joplin 分享页面
去掉 "Joplin Last updated..." header
用公众号风格模板渲染正文
返回精美页面
```

## 架构

### 组件

| 组件 | 技术 | 职责 |
|---|---|---|
| Joplin 插件 | TypeScript/JavaScript | 监听复制事件，URL 转换 |
| PHP 渲染服务 | PHP | 抓取 Joplin 页面，去头，美化渲染 |

### 目录结构

```
joplin_share/
├── docs/
│   └── superpowers/
│       └── specs/
│           └── 2026-04-20-joplin-share-wechat-design.md
├── joplin-plugin/           # Joplin 插件
│   ├── src/
│   │   └── main.ts
│   ├── package.json
│   └── plugin.config.json
├── php-service/            # PHP 渲染服务
│   ├── index.php
│   ├── templates/
│   │   └── wechat.html
│   └── composer.json (可选)
└── SPEC.md
```

## 组件详情

### 1. Joplin 插件

**配置项：**
- `sourceBaseUrl`：源站前缀，默认 `https://home.flaresky.top:8443`
- `shareBaseUrl`：美化链接前缀，默认 `http://stock.flaresky.top:40080/fs/`

**工作逻辑：**
1. 监听 `copy` 事件
2. 检测剪贴板内容是否匹配 Joplin 分享链接格式：`https://home.flaresky.top:8443/shares/{shareID}`
3. 如果匹配，提取 shareID，拼成美化链接，覆盖剪贴板
4. 如果不匹配，不做处理

**Joplin 分享链接格式：**
- 格式：`{源站前缀}/shares/{shareID}`
- 源站前缀配置项：`sourceBaseUrl`，默认 `https://home.flaresky.top:8443`
- 提取 shareID 后与 `shareBaseUrl` 拼接生成美化链接

### 2. PHP 渲染服务

**入口：** `http://stock.flaresky.top:40080/fs/{shareID}`

**处理流程：**
1. 接收 shareID
2. 拼回原始链接：`https://home.flaresky.top:8443/shares/{shareID}`
3. 用 cURL 请求原始页面
4. 解析 HTML：
   - 去掉 `<header>` 或包含 "Joplin Last updated" 的部分
   - 提取 `<main>` 或正文容器内容
   - 保留正文、图片、代码高亮等
5. 用公众号风格模板包裹内容，输出最终 HTML

**公众号风格模板要求：**
- 白底黑字，舒服留白
- 标题醒目，正文 16-18px，行高 1.6-1.8
- 代码高亮（highlight.js）
- 图片自适应宽度
- 移动端友好

### 3. 错误处理

| 场景 | 处理方式 |
|---|---|
| Joplin 分享链接已失效/不存在 | 返回友好提示页面 |
| PHP 服务无法抓取原始链接 | 返回错误提示 |
| shareID 格式不正确 | 返回 404 页面 |

## 技术选型

- **插件**：Joplin 插件 API，原生 JavaScript/TypeScript
- **PHP 服务**：原生 PHP（单文件），无需框架
- **模板**：手写 HTML/CSS + highlight.js（CDN 引入）

## 已确认

1. **Joplin 分享链接格式**：插件配置源站前缀 + 提取 shareID + 拼美化链接前缀。格式为 `https://home.flaresky.top:8443/shares/{shareID}`，shareID 提取方式由插件配置决定。
2. **PHP 服务部署**：nginx + php-fpm
3. **图片处理**：直接使用 Joplin 分享页面输出的绝对 URL，无需额外处理
