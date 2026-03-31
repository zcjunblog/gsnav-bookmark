# GsNav Bookmark Tech Stack

## 职责
本文件定义“最简单但最健壮”的技术选型、约束和替换原则。目标不是追新，而是保证 WordPress 插件可维护、可部署、可迭代。

## 核心原则
- 优先复用 WordPress 原生能力。
- 优先减少运行时外部依赖。
- 优先让现有原型平滑演进，而不是推倒重来。
- 所有密钥都保留在服务端。

## 运行环境
- WordPress：6.5+
- PHP：以部署环境为准。当前 phpstudy Web 运行时明确低于 PHP 7.4，因此在运行时升级并确认前，插件代码必须保持保守语法兼容。
- MySQL：8.0+ 或 MariaDB 10.6+
- 浏览器：现代 Chromium / Safari / Firefox 最近两个大版本

## 当前兼容性约束
- 不要假设 CLI 的 PHP 版本等于 Web 运行时版本。
- 当前代码必须避免以下语法：typed properties、nullable type hints、return type hints、class constant visibility。
- 如果后续确认并升级 Web 运行时，再决定是否恢复更高版本语法。

## 后端技术栈
- 插件框架：原生 WordPress Plugin API
- 路由入口：`add_rewrite_rule` + `template_redirect`
- 配置存储：`wp_options`
- 用户轻状态：`wp_usermeta`
- 结构化业务数据：自定义表，通过 `dbDelta` 管理
- 权限控制：`current_user_can` + 登录态校验 + REST nonce
- HTTP 请求：`wp_remote_get` / `wp_remote_post`
- 返回接口：优先使用 WordPress REST API；仅在兼容过渡期保留少量 `admin-ajax.php`

## 前端技术栈
- UI 框架：Vue 3
- 模块方式：ES Modules
- 状态管理：Vue `ref` / `reactive` / `computed`，不引入额外状态库
- 样式策略：本地静态 CSS + CSS 变量；若保留 Tailwind 风格，必须编译为本地产物，不依赖浏览器 CDN
- 图标：Remix Icon，本地化静态资源，不依赖运行时外链

## 第三方服务策略
- 天气：可继续使用 Open-Meteo 作为默认公开数据源
- 壁纸：Bing 作为零配置兜底；Unsplash / Pixabay / Pexels 通过服务端代理访问
- 农历：如果继续使用现有库，应改为插件内本地静态依赖，不依赖运行时 CDN

## 明确禁止
- 禁止在浏览器端暴露第三方 API key
- 禁止正式版继续依赖 `unpkg` 和 `tailwindcss/browser` 作为生产运行时依赖
- 禁止把大量业务逻辑长期堆在 `gsnav-bookmark.php`
- 禁止在未更新 `memory-bank/architecture.md` 的情况下擅自新增核心数据结构

## 推荐目录策略
- `includes/`：PHP 业务代码
- `templates/`：模板壳
- `assets/js/app/`：前端模块
- `assets/css/`：样式入口和编译产物
- `memory-bank/`：设计、架构、计划、进度

## 测试策略

### 后端
- 使用 PHPUnit 验证数据库安装器、仓储层、权限判断、数据规范化逻辑
- 使用 WordPress REST 请求进行接口级冒烟验证

### 前端
- 先以浏览器手工冒烟测试为主，覆盖桌面加载、书签 CRUD、壁纸切换、搜索切换、设置保存
- 当核心 CRUD 稳定后，再引入 Playwright 做关键路径回归

## 发布标准
达到以下条件才算“可发布”：
- 插件激活/停用无报错
- `/bookmark/` 路由稳定可访问
- 登录用户的数据可保存并恢复
- 访客模式符合后台配置
- 所有第三方密钥仅在服务端使用
- 页面不依赖运行时外部 CDN 才能工作
