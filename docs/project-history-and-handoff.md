# GsNav Bookmark 历史记录与 Codex 交接文档

## 文档职责
这是一份给“下一台电脑上的 Codex / AI 开发者”使用的交接文档。

它解决两个问题：
- 让接手者快速理解这个插件已经做到了什么、还没做到什么。
- 让接手者知道应该先读什么、先验证什么、下一步该继续哪里。

注意：本文件不是架构事实源。架构与产品的权威来源仍然是 `memory-bank/architecture.md` 与 `memory-bank/app-design-document.md`。

## 接手前必须先读
任何继续开发这个插件的 Codex，都必须先按这个顺序阅读：
1. `memory-bank/architecture.md`
2. `memory-bank/app-design-document.md`
3. `memory-bank/implementation-plan.md`
4. `memory-bank/progress.md`
5. 本文件

如果实现和 `memory-bank` 冲突，以 `memory-bank` 为准；必要时先更新 `memory-bank`，再写代码。

## 项目当前定位
`GsNav Bookmark` 是一个运行在 WordPress 里的“云端导航桌面 / 站内工作台”插件。

它参考了 mTab / iTab 的桌面感、壁纸、搜索、文件夹、Dock、极简模式，但不做浏览器扩展专属能力。

当前 MVP 的核心方向是：
- `/bookmark/` 作为独立书签页
- 登录即同步
- 访客默认只读系统桌面
- 桌面、Dock、文件夹逐步数据化
- 第三方壁纸与配置逐步服务端化

## 历史里程碑

### Step 0. 建立 memory-bank 基线
已完成。

结果：
- 建立了 `memory-bank/` 文档体系。
- 明确了产品边界、数据库结构、文件职责、目标重构结构。

### Step 1. 收缩插件入口职责
已完成。

结果：
- `gsnav-bookmark.php` 只保留 bootstrap 职责。
- 新增并接入：
  - `includes/Plugin.php`
  - `includes/Admin/SettingsPage.php`
  - `includes/App/PageController.php`
  - `includes/Database/Installer.php`

### Step 2. 建立数据库安装器与版本管理
已完成。

结果：
- 建表：`gsnav_desktops`、`gsnav_items`
- 建立 `gsnav_db_version`
- 建立激活期初始化与缺表兜底升级

### Step 3. 建立默认桌面数据读取链路
已完成。

结果：
- 新增：
  - `includes/App/DesktopRepository.php`
  - `includes/App/DesktopService.php`
- 前端桌面数据不再完全依赖硬编码。
- 系统默认桌面、Dock、基础搜索引擎配置改为服务端注入。
- 默认桌面首访补种增加并发锁。

### Step 4. 实现登录用户桌面解析逻辑
已完成。

结果：
- 登录用户解析顺序明确：
  - 活跃桌面
  - 用户默认桌面
  - 用户首个桌面
  - 从系统默认桌面复制个人桌面
  - 系统默认桌面
- `gsnav_active_desktop_id` 写入 `usermeta`
- `gsnav_default_desktop_id` 只表示系统默认桌面，不再被个人桌面污染

### Step 5. 实现书签、文件夹、Dock 的保存能力
已完成。

结果：
- 登录用户可对桌面和 Dock 做最小可用 CRUD：
  - 新增链接
  - 新建文件夹
  - 文件夹内新增链接
  - 编辑
  - 删除
  - 上移 / 下移排序
- 保存策略不是局部打补丁，而是：
  - 前端提交完整当前桌面状态
  - 服务端校验
  - 校验通过后整桌面重建 `gsnav_items`
- 当前只允许一层文件夹嵌套

### 插入需求：书签页登录态闭环
在完成 Step 5 后，插入了一个强制需求：
- 书签页未登录时必须能直接登录 WordPress
- 已登录用户必须直接显示当前用户信息

### Step 6. 接入书签页登录态与用户信息
已完成。

结果：
- 新增 `includes/App/ViewerService.php`
- `PageController` 会把 `viewer` 注入到前端配置
- `/bookmark/` 左上角账户区：
  - 未登录：显示“登录以同步你的书签桌面”
  - 已登录：显示头像、昵称、资料页入口、退出入口
- 未登录用户尝试保存桌面时，前端会提示并支持直接跳转到 WordPress 原生登录页
- 登录成功后回跳 `/bookmark/`

## 当前功能状态

### 已经可用的能力
- `/bookmark/` 路由可用
- WordPress 后台设置页可用
- 数据库表自动创建可用
- 系统默认桌面读取可用
- 登录用户个人桌面解析可用
- 桌面 / Dock / 文件夹最小 CRUD 可用
- 书签页登录入口和用户信息展示可用

### 还没完成的能力
- Step 7：桌面设置持久化
- Step 8：搜索引擎模块化与持久化
- Step 9：壁纸系统服务端化
- Step 10：后台设置页升级
- Step 11：整体打磨与回归

## 当前应继续的步骤
下一位 Codex 应直接继续 `Step 7`。

目标：
- 把这些前端状态写入 `gsnav_desktops.settings_json`：
  - `showDock`
  - `bgBlur`
  - `simpleMode`
  - 默认搜索引擎

建议实施顺序：
1. 在 `DesktopRepository` 中增加 `settings_json` 读取与更新能力。
2. 在 `DesktopService` 中建立“默认设置 + DB 设置”合并逻辑。
3. 让 `PageController` 输出的 `desktopPayload.settings` 成为唯一可信来源。
4. 让前端设置改动触发真实保存，而不是只改内存状态。
5. 为访客模式保留后门：是否允许仅写 localStorage，由后台配置决定。
6. 完成后必须更新 `memory-bank` 四个文件，并在 `implementation-plan.md` 中给 Step 7 打勾。

## 关键代码文件
接手时优先看这些文件：
- `gsnav-bookmark.php`
- `includes/Plugin.php`
- `includes/Database/Installer.php`
- `includes/App/DesktopRepository.php`
- `includes/App/DesktopService.php`
- `includes/App/ViewerService.php`
- `includes/App/PageController.php`
- `templates/app.php`
- `assets/js/app.js`
- `memory-bank/architecture.md`
- `memory-bank/app-design-document.md`
- `memory-bank/implementation-plan.md`
- `memory-bank/progress.md`

## 当前结构判断
当前最大的两个耦合点仍然是：
- `templates/app.php`
- `assets/js/app.js`

原因：
- 这两处仍承载大量 UI、状态与交互逻辑。
- 目前继续做功能时，不要急着重构成太多模块；先完成 Step 7 到 Step 9 的主链路。
- 等主链路通了，再考虑按 `memory-bank/architecture.md` 里的目标结构继续拆。

## 环境与兼容性注意事项

### 1. Web PHP 版本低于 CLI PHP
这个项目已经踩过一次坑。

结论：
- 当前 phpstudy 的 Web 运行时低于 PHP 7.4。
- 不要因为本机 CLI `php -l` 能过，就假设 Web 端也能跑。

因此新增 PHP 代码时要避免：
- typed properties
- nullable type hints
- return type hints
- class constant visibility

### 2. 当前没有依赖 wp-cli
本地验证主要使用：
- `php -l`
- `node --check`
- `php -r 'require ".../wp-load.php"; ...'`

如果接手机器上没有 `wp-cli`，也不会阻塞开发。

### 3. 不要把本地数据库当成产品种子数据
这个项目的验证过程中会在本地库里创建测试桌面、测试用户、测试项目。

接手时要注意：
- 本地数据库内容是环境状态，不是产品发布状态。
- 不要因为本地库里有某个用户桌面，就把它当成默认演示数据。

## 推荐验证方式

### 语法检查
```bash
php -l gsnav-bookmark.php
php -l includes/App/PageController.php
php -l includes/App/DesktopService.php
php -l includes/App/DesktopRepository.php
php -l includes/App/ViewerService.php
php -l templates/app.php
node --check assets/js/app.js
```

### 运行态验证
使用 `wp-load.php` 做最小验证，重点确认：
- `/bookmark/` 初始化配置是否包含 `desktopPayload`
- `viewer` 是否存在
- 未登录时 `loginUrl` 是否带 `redirect_to=/bookmark/`
- 登录用户是否能拿到 `user_private` 桌面
- 设置保存后是否写入 `settings_json`

### 页面手工验证
至少手工走这几条路径：
1. 访客打开 `/bookmark/`
2. 登录用户打开 `/bookmark/`
3. 新建链接并刷新
4. 新建文件夹并加入子链接后刷新
5. 登录入口跳转并回跳
6. 退出登录后再次访问 `/bookmark/`

## 已知问题与风险
- `showDock`、`bgBlur`、`simpleMode`、默认搜索引擎、壁纸状态仍未持久化。
- 第三方壁纸 key 目前仍会进入前端配置；这要在 Step 9 解决。
- 删除 WordPress 用户时，插件当前不会自动级联清理该用户的个人桌面。
- `assets/js/app.js` 体量已经很大，继续开发时必须控制改动范围，避免一次性做大重构。
- `templates/app.php` 当前仍包含大量内联样式和结构，后续可拆，但不应抢在主链路前面做。

## 当前仓库状态提醒
截至本次交接时，仓库里已有未提交改动。

这意味着：
- 下一位 Codex 接手前，先运行 `git status --short`。
- 不要误把当前未提交代码当成“上游干净基线”。
- 在不明确用户意图之前，不要自行清理、重置或回滚现有改动。

## 给下一位 Codex 的执行建议
1. 先完整阅读 `memory-bank` 两份权威文档，再读本文件。
2. 不要重做 Step 0 到 Step 6；它们已经完成并验证过。
3. 直接继续 Step 7。
4. Step 7 做完后，立刻更新 `memory-bank`。
5. 若中途新增文件，必须把职责登记进 `memory-bank/architecture.md`。
6. 若发现实现与文档冲突，先改文档，再改代码。

## 文档维护规则
本文件是交接辅助文档，不替代 `memory-bank`。

后续如果继续迭代：
- 产品与架构变化：先改 `memory-bank`
- 交接信息变化：再同步更新本文件
