# GsNav Bookmark Architecture Ledger

## 职责
本文件是插件的架构事实源（source of truth）。它记录：系统边界、完整数据结构、项目文件职责、模块边界、当前代码现状、后续重构目标。

## Always
以下规则必须在 AI 工具中标记为 `Always`，并在任何代码生成前执行：
1. 写任何代码前，必须完整阅读 `memory-bank/architecture.md`。
2. 写任何代码前，必须完整阅读 `memory-bank/app-design-document.md`。
3. 每完成一个重大功能或里程碑后，必须更新本文件。
4. 任何新增文件，都要在本文件中补充“职责说明”。
5. 如果实现与本文件冲突，先更新本文件，再写代码。
6. `memory-bank/@architecture.md` 只是兼容别名，和本文件是同一个文件，不允许分开维护。
7. 每次完成一个计划步骤后，必须同步更新 `memory-bank/implementation-plan.md`、`memory-bank/progress.md`，并为已完成步骤打勾。

## 系统定位
`GsNav Bookmark` 是一个运行在 WordPress 内的“云端起始页/导航桌面”插件，不是浏览器扩展本体。

这意味着：
- 它可以提供类似 mTab / iTab 的桌面导航、搜索、壁纸、文件夹、组件式首页体验。
- 它不能像 Chrome 扩展那样直接接管浏览器新标签页，除非用户手动将 `/bookmark/` 设为浏览器主页或启动页。
- 它应优先利用 WordPress 现成能力：用户体系、权限、媒体库、REST API、设置页、数据库。

## 当前代码基线（2026-03-31）
当前仓库已完成 Step 6，核心特点如下：
- 入口文件 `gsnav-bookmark.php` 现在只负责 bootstrap：定义常量、加载类文件、注册激活/停用钩子、启动 `Plugin`。
- `/bookmark/` 路由、模板分发、Bing 壁纸 Ajax 已迁移到 `includes/App/PageController.php`。
- 后台设置页和 API key 字段注册已迁移到 `includes/Admin/SettingsPage.php`。
- `includes/Database/Installer.php` 现在负责默认 option 初始化、自定义表创建、`gsnav_db_version` 维护、缺失表兜底升级、激活/停用期 rewrite flush。
- `includes/App/DesktopRepository.php` 与 `includes/App/DesktopService.php` 已接入桌面解析链路，负责系统默认桌面、登录用户个人桌面、默认项目补种、桌面 payload 组装。
- `PageController` 现在会把“当前访问者”的 `desktopPayload` 与 `viewer` 登录态信息一起注入到 `window.GSNAV_CONFIG`，并提供登录用户桌面保存所需的 nonce 与保存开关。
- `includes/App/ViewerService.php` 已接入当前 WordPress 访问者摘要，负责输出是否已登录、显示名、头像、登录 URL、退出 URL、资料页 URL。
- 默认桌面首次补种已增加 option 锁兜底，避免并发首访时重复创建默认桌面或默认项目。
- 登录用户访问时的解析顺序已经明确：优先读取 `usermeta.gsnav_active_desktop_id`，其次读取用户默认桌面，再次读取用户首个桌面；若不存在个人桌面，则从系统默认桌面复制一份个人桌面。
- `gsnav_default_desktop_id` 仍只表示系统默认桌面，不会在登录用户访问时被个人桌面覆盖。
- 前端页面仍由 `templates/app.php` 一次性输出，前端逻辑仍集中在 `assets/js/app.js`，但现有右键菜单和文件夹弹层已经能触发真实保存。
- 登录用户已经可以通过前端完成最小 CRUD：新增链接、新建文件夹、在文件夹内新增链接、编辑、删除、上移/下移排序，并同步写入数据库。
- 壁纸源已经包含 Unsplash / Pixabay / Pexels / Bing，但除 Bing 外的第三方能力仍未服务端化。
- 书签、Dock、文件夹的基础保存能力已经落地，但桌面级设置、搜索引擎状态和壁纸状态仍未持久化。
- `wp_gsnav_desktops` 与 `wp_gsnav_items` 的 schema 已在本地通过 `Installer` 建立并验证。
- phpstudy 的 Web 运行时低于 PHP 7.4；因此新增 PHP 代码必须避免 typed properties、nullable type hints、return type hints、class constant visibility 等较新语法。
- 当前阶段已经具备“后端骨架 + 数据库基础层 + 默认桌面只读链路 + 登录用户桌面解析链路 + 书签/Dock/文件夹前台保存能力 + 书签页登录态闭环”，下一步进入桌面设置持久化。

## 产品边界与约束

### 需要保留的现有优势
- 已有接近成品感的桌面视觉。
- 已有时间、天气、农历、搜索框、Dock、文件夹、壁纸中心等关键界面骨架。
- `/bookmark/` 的独立页面入口已经存在。

### 需要明确规避的方向
- 不直接复制浏览器扩展专属能力，例如“读取当前标签页后一键收藏”“覆盖浏览器新标签页”“调用浏览器书签 API”。
- 不在首版引入复杂多租户 SaaS 设计。
- 不在首版做深层嵌套文件夹。
- 不在首版做组件市场、AI 组件、复杂分享链路。

## 权威数据结构
插件的数据存储分为三层：
1. `wp_options`：插件全局设置。
2. 自定义表：桌面与桌面项目数据。
3. `wp_usermeta`：轻量级用户状态。

### 1. WordPress Options
以下 option key 由插件维护：

| Key | 类型 | 说明 |
| --- | --- | --- |
| `gsnav_db_version` | string | 当前数据库结构版本号，用于迁移。 |
| `gsnav_unsplash_key` | string | Unsplash 服务端请求密钥。 |
| `gsnav_pixabay_key` | string | Pixabay 服务端请求密钥。 |
| `gsnav_pexels_key` | string | Pexels 服务端请求密钥。 |
| `gsnav_default_desktop_id` | int | 系统默认桌面的 ID。 |
| `gsnav_allow_guest_mode` | bool | 是否允许访客使用公共桌面。 |
| `gsnav_guest_can_customize` | bool | 访客是否允许本地自定义（仅 localStorage，不落库）。 |
| `gsnav_route_slug` | string | 默认访问路由，初始值建议为 `bookmark`。 |
| `gsnav_weather_mode` | string | 天气来源模式，初始值建议 `open-meteo`。 |
| `gsnav_enable_wallpaper_proxy` | bool | 是否启用服务端壁纸代理。正式版必须开启。 |

### 2. 自定义表：`{$wpdb->prefix}gsnav_desktops`
一行代表一个“桌面/工作台”。

| 字段 | 类型 | 允许空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | 否 | AUTO_INCREMENT | 主键。 |
| `user_id` | BIGINT UNSIGNED | 是 | NULL | 拥有者用户 ID。系统默认桌面可为空。 |
| `scope` | VARCHAR(20) | 否 | `user_private` | `system_default` / `user_private` / `public_template`。 |
| `slug` | VARCHAR(100) | 是 | NULL | 公开模板或分享场景的稳定标识。 |
| `name` | VARCHAR(120) | 否 |  | 桌面名称。 |
| `description` | TEXT | 是 | NULL | 桌面描述。 |
| `status` | VARCHAR(20) | 否 | `active` | `active` / `archived`。 |
| `is_default` | TINYINT(1) | 否 | `0` | 对当前拥有者是否为默认桌面。 |
| `settings_json` | LONGTEXT | 是 | NULL | 桌面级设置，JSON 格式。 |
| `version` | INT UNSIGNED | 否 | `1` | 乐观锁或结构演进版本。 |
| `created_by` | BIGINT UNSIGNED | 是 | NULL | 创建人。 |
| `updated_by` | BIGINT UNSIGNED | 是 | NULL | 最后修改人。 |
| `created_at` | DATETIME | 否 | CURRENT_TIMESTAMP | 创建时间。 |
| `updated_at` | DATETIME | 否 | CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | 更新时间。 |

索引要求：
- PRIMARY KEY (`id`)
- KEY `idx_user_scope` (`user_id`, `scope`)
- KEY `idx_scope_default` (`scope`, `is_default`)
- UNIQUE KEY `uniq_slug` (`slug`) 仅在 `slug` 非空时使用应用层约束

`settings_json` 的规范结构如下：

```json
{
  "showDock": true,
  "bgBlur": 0,
  "simpleMode": false,
  "wallpaper": {
    "source": "bing",
    "category": "backgrounds",
    "current": {
      "id": "bing_20260331",
      "type": "image",
      "src": "https://example.com/image.jpg",
      "thumbnail": "https://example.com/thumb.jpg",
      "user": "Bing"
    }
  },
  "search": {
    "defaultEngineId": "baidu",
    "engines": [
      {
        "id": "baidu",
        "name": "百度",
        "icon": "ri-baidu-fill",
        "url": "https://www.baidu.com/s?wd="
      }
    ]
  },
  "weather": {
    "mode": "geo",
    "fallbackCity": "beijing"
  },
  "layout": {
    "desktopColumns": {
      "mobile": 2,
      "tablet": 4,
      "desktop": 8
    }
  }
}
```

### 3. 自定义表：`{$wpdb->prefix}gsnav_items`
一行代表一个桌面元素。元素可以是链接、文件夹、未来的小组件。

| 字段 | 类型 | 允许空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | 否 | AUTO_INCREMENT | 主键。 |
| `desktop_id` | BIGINT UNSIGNED | 否 |  | 所属桌面 ID。 |
| `parent_item_id` | BIGINT UNSIGNED | 是 | NULL | 所属文件夹 ID；顶层元素为空。 |
| `area` | VARCHAR(20) | 否 | `desktop` | `desktop` / `dock` / `folder`。 |
| `item_type` | VARCHAR(20) | 否 | `link` | `link` / `folder` / `widget`。 |
| `title` | VARCHAR(120) | 否 |  | 展示名称。 |
| `url` | TEXT | 是 | NULL | 仅 `link` 类型使用。 |
| `icon_type` | VARCHAR(20) | 否 | `remixicon` | `remixicon` / `favicon` / `image` / `emoji`。 |
| `icon_value` | VARCHAR(255) | 是 | NULL | 图标值，例如 `ri-bilibili-fill`。 |
| `color` | VARCHAR(32) | 是 | NULL | 图标背景色。 |
| `open_mode` | VARCHAR(20) | 否 | `new_tab` | `new_tab` / `same_tab`。 |
| `sort_order` | INT UNSIGNED | 否 | `0` | 同级排序。 |
| `size` | VARCHAR(20) | 否 | `1x1` | 预留给未来 widget 布局。 |
| `meta_json` | LONGTEXT | 是 | NULL | 扩展数据，如描述、标签、widget 配置。 |
| `created_at` | DATETIME | 否 | CURRENT_TIMESTAMP | 创建时间。 |
| `updated_at` | DATETIME | 否 | CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | 更新时间。 |

索引要求：
- PRIMARY KEY (`id`)
- KEY `idx_desktop_area_order` (`desktop_id`, `area`, `sort_order`)
- KEY `idx_parent_item` (`parent_item_id`)
- KEY `idx_item_type` (`item_type`)

应用层约束：
- 文件夹只允许一层嵌套。即：`parent_item_id` 指向的父元素必须是顶层 `folder`，并且该父元素自己的 `parent_item_id` 必须为 `NULL`。
- `area = folder` 的元素必须拥有 `parent_item_id`。
- `item_type = folder` 的元素自身不能拥有 `url`。
- 删除桌面时，应用层级联删除其 `items`。
- 删除文件夹时，应用层级联删除其子元素，除非未来明确支持“解散文件夹”。

### 4. WordPress User Meta
只存“轻状态”，不存结构化桌面主体数据。

| Meta Key | 类型 | 说明 |
| --- | --- | --- |
| `gsnav_active_desktop_id` | int | 用户最近一次打开的桌面 ID。 |
| `gsnav_last_wallpaper_source` | string | 用户最近一次使用的壁纸来源，便于恢复体验。 |
| `gsnav_last_search_engine_id` | string | 用户最近一次使用的搜索引擎。 |

## 数据流约束
- 插件全局配置从 `wp_options` 读取。
- 登录用户的桌面内容从自定义表读取并保存。
- 当前访问者的桌面解析顺序为：登录用户活跃桌面 -> 登录用户默认桌面 -> 登录用户首个桌面 -> 从系统默认桌面复制个人桌面 -> 系统默认桌面。
- 登录用户当前桌面的项目保存采用“前端提交完整当前状态，服务端验证后整桌面重建”的策略；当前只允许一层文件夹嵌套。
- `/bookmark/` 页面的登录入口必须复用 WordPress 原生登录态，不额外实现独立认证系统。
- 当访问者未登录时，前端初始化数据应提供登录 URL，且 `redirect_to` 必须回跳当前书签页。
- 当访问者已登录时，前端初始化数据应提供当前用户最小资料对象，至少包含 `id`、`displayName`、`avatarUrl`。
- 访客模式只读取系统默认桌面；若允许自定义，则只保存在浏览器 `localStorage`。
- 任何第三方壁纸 API key 不再下发到浏览器；浏览器只能访问服务端代理接口。

## 当前文件职责（现状）

| 文件 | 职责 | 现状判断 |
| --- | --- | --- |
| `gsnav-bookmark.php` | 插件 bootstrap，负责定义常量、加载类文件、注册激活/停用钩子并启动插件。 | Step 1 已完成收缩。 |
| `includes/Plugin.php` | 插件装配器，负责注册后台设置页和页面控制器。 | 新增于 Step 1。 |
| `includes/Admin/SettingsPage.php` | 管理后台设置页与当前 API key 字段注册。 | 新增于 Step 1。 |
| `includes/App/DesktopRepository.php` | 负责 `gsnav_desktops` 与 `gsnav_items` 的基础查询、创建、删除、默认桌面 option 写入、用户活跃桌面 `usermeta` 读写、事务控制。 | Step 5 已扩展。 |
| `includes/App/DesktopService.php` | 负责系统默认桌面解析、登录用户桌面回退顺序、首次复制个人桌面、桌面项目结构校验、整桌面保存、默认种子数据补齐、桌面 payload 组装、并发兜底。 | Step 5 已扩展。 |
| `includes/App/ViewerService.php` | 负责封装当前 WordPress 访问者的登录态、用户摘要信息、登录/退出/资料页 URL，并统一书签页登录回跳地址。 | 新增于 Step 6。 |
| `includes/App/PageController.php` | 负责 `/bookmark/` 路由、查询变量、模板输出、Bing 壁纸 Ajax、桌面项目保存 Ajax、登录态初始化数据注入。 | Step 6 已扩展。 |
| `includes/Database/Installer.php` | 负责激活/停用期初始化、数据库 schema 创建/升级、`gsnav_db_version` 维护、缺表兜底修复。 | Step 2 已完成。 |
| `templates/app.php` | 输出 `/bookmark/` 页面完整 HTML，注入前端配置，承载大量内联样式，并提供当前最小可用的桌面 CRUD 入口。 | 可视原型完成度高，结构仍需拆分。 |
| `assets/js/app.js` | Vue 前端主逻辑，管理桌面、搜索、壁纸、设置状态，并在当前阶段负责把桌面 CRUD 动作同步到服务端。 | 当前最大耦合点，后续仍需拆模块。 |
| `assets/js/weather.js` | 天气获取与图标映射逻辑。 | 可单独保留为前端服务模块。 |
| `assets/css/style.css` | 基础样式与玻璃态通用样式。 | 可沉淀为全局 UI 基础层。 |
| `docs/project-history-and-handoff.md` | 面向跨电脑 / 跨会话 Codex 接手的辅助交接文档，记录历史脉络、当前状态、环境注意事项与建议接手顺序。 | 新增于本次交接。 |
| `tree.txt` | 文件树快照。 | 仅用于人工查看，不参与运行。 |

## 目标文件职责（重构目标）
以下是目标结构，不要求一次性创建完，但后续新增文件必须遵循单一职责。

| 文件 | 职责 |
| --- | --- |
| `gsnav-bookmark.php` | 仅做插件 bootstrap：定义常量、注册激活/停用钩子、加载 `Plugin`。 |
| `includes/Plugin.php` | 统一编排插件生命周期、挂载 hooks、装配服务。 |
| `includes/Database/Installer.php` | 创建和升级自定义表，维护 `gsnav_db_version`。 |
| `includes/App/PageController.php` | 负责 `/bookmark/` 路由、模板输出和前端初始化数据。 |
| `includes/App/DesktopRepository.php` | 读写 `gsnav_desktops` 与 `gsnav_items`。 |
| `includes/App/DesktopService.php` | 处理桌面解析、默认桌面回退、文件夹规则、排序等业务逻辑。 |
| `includes/App/ViewerService.php` | 负责封装当前 WordPress 访问者的登录态、用户摘要信息、登录/退出/资料页 URL。 |
| `includes/Admin/SettingsPage.php` | 后台设置页 UI 与字段注册。 |
| `includes/Admin/SettingsRepository.php` | 统一读取/写入插件全局配置。 |
| `includes/Http/RestController.php` | 暴露前端需要的 REST API，包括读取桌面、保存桌面、搜索引擎、壁纸代理。 |
| `includes/Http/WallpaperProxyController.php` | 第三方壁纸服务端代理与结果标准化。 |
| `templates/app-shell.php` | 仅输出前端挂载壳和经过净化的初始化配置，不承载业务逻辑。 |
| `assets/js/app/main.js` | Vue 应用入口。 |
| `assets/js/app/stores/desktopStore.js` | 桌面状态管理。 |
| `assets/js/app/services/api.js` | 统一封装 REST 请求。 |
| `assets/js/app/modules/search.js` | 搜索引擎切换与跳转。 |
| `assets/js/app/modules/wallpaper.js` | 壁纸面板、预览、选择、分页。 |
| `assets/js/app/modules/weather.js` | 天气数据获取与展示适配。 |
| `assets/js/app/modules/context-menu.js` | 右键菜单与交互动作。 |
| `assets/css/app.css` | 编译后的主要样式入口。 |
| `docs/project-history-and-handoff.md` | 跨机器 / 跨会话接手文档，辅助后续 Codex 快速恢复上下文，但不替代 `memory-bank`。 |
| `memory-bank/architecture.md` | 架构事实源，必须在编码前阅读。 |
| `memory-bank/app-design-document.md` | 产品与交互事实源，必须在编码前阅读。 |
| `memory-bank/tech-stack.md` | 技术栈选择与约束说明。 |
| `memory-bank/implementation-plan.md` | 分步实施指令，不含代码。 |
| `memory-bank/progress.md` | 里程碑进度记录。 |

## 迁移原则
- 先把“职责拆分”与“数据落库”做完，再做高级组件。
- 先让现有界面接入真实数据，再增加新界面。
- 优先服务登录用户同步，访客模式只做只读/轻自定义。
- 所有服务端输出都要经过能力校验与 nonce 校验。
- 所有第三方密钥都必须改为服务端持有。

## 已记录里程碑
- `2026-03-31`：完成首版 `memory-bank` 初始化；基于现有本地代码与 mTab / iTab 产品形态，确定 WordPress 版产品边界、完整数据结构与目标模块分层。
- `2026-03-31`：完成 Step 1；将插件入口职责拆分为 `Plugin`、`SettingsPage`、`PageController`、`Installer` 四个类，同时保持现有 `/bookmark/` 路由、后台设置页和 Bing Ajax 能力不变。
- `2026-03-31`：完成 Step 2；建立 `gsnav_desktops`、`gsnav_items` 与 `gsnav_db_version`，并接入缺表兜底升级。
- `2026-03-31`：完成 Step 3；新增 `DesktopRepository` 与 `DesktopService`，将默认桌面、Dock、文件夹、搜索引擎基础配置改为服务端读取并注入 `desktopPayload`。
- `2026-03-31`：完成 Step 4；建立登录用户桌面解析顺序、首次复制个人桌面与 `gsnav_active_desktop_id` 持久化。
- `2026-03-31`：完成 Step 5；登录用户可在前台真实保存桌面、Dock、文件夹的最小 CRUD 与排序。
- `2026-03-31`：完成 Step 6；书签页接入 WordPress 原生登录态，未登录用户可直接前往登录并回跳 `/bookmark/`，已登录用户直接显示头像、昵称和账户操作入口。
- `2026-03-31`：新增 `docs/project-history-and-handoff.md`，用于后续在其他电脑上的 Codex 接手开发时快速恢复上下文与执行顺序。
- `2026-03-31`：合并 `architecture.md` / `@architecture.md` 与 `app-design-document.md` / `@app-design-document.md`；规范路径改为无 `@` 文件名，`@` 文件仅保留为兼容别名。
- `2026-03-31`：完成 Step 2；`Installer` 接入 `plugins_loaded` 升级检查，使用 `dbDelta` 创建 `gsnav_desktops` 与 `gsnav_items`，并写入 `gsnav_db_version=1.0.0`。本地 WordPress 环境已验证表存在且重复激活无报错。
- `2026-03-31`：修复 Step 1/2 的 PHP 兼容性问题；由于 phpstudy Web 运行时无法解析 typed properties，已将新增类统一降级为保守语法，避免 Web 端再次触发 parse error。
- `2026-03-31`：完成 Step 3；新增 `DesktopRepository` 与 `DesktopService`，将默认桌面、Dock、文件夹、搜索引擎基础配置改为服务端读取并注入 `desktopPayload`。本地已验证默认桌面自动补种、并发首访不重复创建、修改数据库后刷新可见变更。
- `2026-03-31`：完成 Step 4；登录用户访问时优先解析个人桌面，首次访问会从系统默认桌面复制一份个人桌面，并写入 `usermeta.gsnav_active_desktop_id`。本地已验证两个临时用户会拿到不同的个人桌面，且修改用户 A 的桌面不会污染用户 B 或访客默认桌面。
- `2026-03-31`：完成 Step 5；前端右键菜单和文件夹弹层已接入真实保存链路，登录用户可以新增链接、创建文件夹、添加文件夹子链接、编辑、删除和排序，并通过 Ajax + nonce 将整桌面状态保存到 `gsnav_items`。本地已验证新增、删除、文件夹子链接和桌面/Dock 排序都能落库且互不污染。
