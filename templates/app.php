<?php if (!defined('ABSPATH')) exit; ?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GsNav Pro</title>
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <link href="<?php echo GSNAV_PLUGIN_URL . 'assets/css/style.css'; ?>" rel="stylesheet">
    <script src="https://unpkg.com/lunar-javascript@1.6.12/lunar.js"></script>

    <script>
    window.GSNAV_CONFIG = {
        unsplashKey: "<?php echo esc_js($unsplash_key); ?>"
    };
    </script>

    <style>
    [v-cloak] {
        display: none !important;
    }

    /* ... 之前的 CSS 保持不变 (动画、图标样式等) ... */
    .context-menu-enter-active,
    .context-menu-leave-active {
        transition: opacity 0.15s, transform 0.15s;
    }

    .context-menu-enter-from,
    .context-menu-leave-to {
        opacity: 0;
        transform: scale(0.95);
    }

    .folder-zoom-enter-active,
    .folder-zoom-leave-active {
        transition: all 0.25s cubic-bezier(0.1, 0.9, 0.2, 1);
    }

    .folder-zoom-enter-from,
    .folder-zoom-leave-to {
        opacity: 0;
        transform: scale(0.8);
    }

    .app-icon {
        transition: all 0.2s cubic-bezier(0.25, 0.8, 0.25, 1);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .app-item:hover .app-icon {
        transform: translateY(-3px) scale(1.02);
        filter: brightness(1.05);
    }

    /* 简单的布局定义，省略之前的详细部分，请保留原有的 .desktop-mode, .dock-mode 等样式 */
    .desktop-mode .app-item {
        width: 90px;
        height: 100px;
        padding: 5px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .desktop-mode .app-icon {
        width: 60px;
        height: 60px;
        border-radius: 14px;
        font-size: 30px;
    }

    .desktop-mode .app-text {
        display: block;
        margin-top: 8px;
        font-size: 13px;
        line-height: 1.2;
        text-shadow: 0 1px 4px rgba(0, 0, 0, 0.9);
        font-weight: 500;
    }

    .dock-mode .app-item {
        width: 60px;
        height: 60px;
        padding: 0;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .dock-mode .app-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        font-size: 26px;
    }

    .dock-mode .app-text {
        display: none;
    }

    .folder-preview {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        grid-template-rows: repeat(3, 1fr);
        gap: 2px;
        padding: 5px;
        width: 100%;
        height: 100%;
        box-sizing: border-box;
    }

    .folder-mini-icon {
        width: 100%;
        height: 100%;
        border-radius: 2px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        color: white;
    }

    .folder-open-item {
        width: 80px;
        display: flex;
        flex-direction: column;
        align-items: center;
        cursor: pointer;
        padding: 10px;
        border-radius: 8px;
        transition: background 0.2s;
    }

    .folder-open-item:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .folder-open-icon {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        font-size: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        margin-bottom: 6px;
    }
    </style>
</head>

<body class="bg-gray-900 text-white select-none overflow-hidden">

    <div id="gs-app" v-cloak class="relative w-full h-screen flex flex-col">

        <div class="absolute inset-0 bg-cover bg-center transition-all duration-700 ease-in-out"
            :style="{ backgroundImage: `url(${currentWallpaper})` }">
            <div class="absolute inset-0 bg-black/20 backdrop-blur-[var(--b)]" :style="{'--b': settings.bgBlur + 'px'}">
            </div>
        </div>

        <div class="absolute top-6 right-6 z-30 flex gap-3">
            <button @click.stop="isSimpleMode = !isSimpleMode"
                class="w-10 h-10 rounded-full glass-panel flex items-center justify-center hover:bg-white/20 transition cursor-pointer">
                <i class="ri-leaf-line transition-transform duration-500"
                    :class="isSimpleMode ? 'text-green-400 rotate-90' : 'text-white'"></i>
            </button>
            <button @click.stop="openSettings('界面布局')"
                class="w-10 h-10 rounded-full glass-panel flex items-center justify-center hover:bg-white/20 transition cursor-pointer">
                <i class="ri-settings-4-line text-xl"></i>
            </button>
        </div>

        <div class="relative z-10 flex-1 flex flex-col p-6 transition-all duration-500"
            :class="isSimpleMode ? 'justify-center items-center' : 'justify-start'">

            <header class="flex flex-col transition-all duration-500"
                :class="isSimpleMode ? 'items-center text-center scale-110 mb-20 mt-10' : 'items-start mb-8'">
                <h1 class="font-light tracking-tight text-shadow transition-all duration-300 flex items-baseline font-mono"
                    :class="isSimpleMode ? 'text-8xl' : 'text-7xl'">
                    {{ timeHourMinute }}
                    <span class="animate-pulse mx-1" style="font-family: inherit; font-weight: inherit;">:</span>
                    <span class="opacity-90" style="font-size: inherit;">{{ timeSecond }}</span>
                </h1>
                <div class="flex flex-col gap-4 text-shadow transition-all duration-300"
                    :class="isSimpleMode ? 'items-center mt-8' : 'items-start mt-4'">
                    <p class="text-2xl opacity-95 font-medium tracking-wide">{{ dateString }} · {{ lunarData.text }}</p>
                    <div
                        class="flex items-center gap-4 text-base opacity-90 bg-black/30 px-5 py-2.5 rounded-full backdrop-blur-md border border-white/10 shadow-lg">
                        <div class="flex items-center gap-2">
                            <i :class="weather.icon" class="text-yellow-300 text-xl"></i>
                            <span class="font-bold">{{ weather.temp }}°C</span>
                            <span>{{ weather.desc }}</span>
                        </div>
                        <span class="w-px h-4 bg-white/40"></span>
                        <span class="text-yellow-200">宜: {{ lunarData.yi }}</span>
                    </div>
                </div>
            </header>

            <div class="w-full transition-all duration-500 relative z-20 mb-8"
                :class="isSimpleMode ? 'max-w-2xl scale-110' : 'max-w-2xl mx-auto'">
                <div
                    class="glass-panel rounded-full flex items-center px-5 py-4 transition-all focus-within:bg-white/20 shadow-lg">
                    <div class="relative group mr-3 cursor-pointer" @click.stop="showEngineMenu = !showEngineMenu">
                        <div class="flex items-center gap-1 opacity-80 hover:opacity-100">
                            <i :class="currentEngine.icon" class="text-xl"></i>
                            <i class="ri-arrow-down-s-line text-xs"></i>
                        </div>
                        <div v-if="showEngineMenu"
                            class="absolute top-12 left-0 w-32 glass-dark rounded-xl py-2 shadow-xl z-50 border border-white/10">
                            <div v-for="(eng, index) in engines" :key="eng.id" @click="switchEngine(index)"
                                class="px-4 py-3 hover:bg-blue-600/50 flex gap-3 items-center text-sm transition-colors">
                                <i :class="eng.icon"></i> {{ eng.name }}
                            </div>
                        </div>
                    </div>
                    <input type="text" v-model="searchText" @keyup.enter="handleSearch"
                        :placeholder="'在 ' + currentEngine.name + ' 中搜索...'"
                        class="bg-transparent w-full outline-none text-white text-xl h-8 placeholder-gray-400 font-light"
                        @click.stop>
                </div>
            </div>

            <transition name="slide-up">
                <div v-show="!isSimpleMode" class="flex-1 w-full overflow-y-auto no-scrollbar pb-32 px-2">
                    <div id="desktop-list"
                        class="desktop-mode grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-4 justify-items-center pb-24 min-h-[300px]">
                        <div v-for="(item, index) in desktopApps" :key="item.id" :data-id="item.id"
                            @click.stop="handleAppClick(item)" class="app-item group cursor-pointer rounded-xl">
                            <div class="app-icon"
                                :style="{ background: item.type === 'folder' ? 'rgba(255,255,255,0.25)' : item.color, backdropFilter: item.type === 'folder' ? 'blur(12px)' : 'none' }">
                                <div v-if="item.type === 'folder'" class="folder-preview">
                                    <div v-for="sub in item.children.slice(0,9)" :key="sub.id" class="folder-mini-icon"
                                        :style="{background: sub.color}"><i :class="sub.icon"
                                            style="transform: scale(0.6);"></i></div>
                                </div>
                                <i v-else :class="item.icon"></i>
                            </div>
                            <span class="app-text truncate w-full text-center">{{ item.name }}</span>
                        </div>
                    </div>
                </div>
            </transition>
        </div>

        <transition name="slide-up">
            <div v-if="settings.showDock && !isSimpleMode" class="fixed bottom-8 left-1/2 -translate-x-1/2 z-30">
                <div id="dock-list"
                    class="dock-mode glass-panel px-6 py-3 rounded-2xl flex gap-4 shadow-2xl backdrop-blur-xl border border-white/20 items-center min-h-[80px]">
                    <div v-for="item in dockApps" :key="item.id" :data-id="item.id" @click.stop="handleAppClick(item)"
                        class="app-item group cursor-pointer">
                        <div class="app-icon bg-white/10 hover:bg-white/20">
                            <div v-if="item.type === 'folder'" class="folder-preview">
                                <div v-for="sub in item.children.slice(0,9)" :key="sub.id" class="folder-mini-icon"
                                    :style="{background: sub.color}"><i :class="sub.icon"
                                        style="transform: scale(0.6);"></i></div>
                            </div>
                            <i v-else :class="item.icon"></i>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <transition name="context-menu">
            <div v-if="contextMenu.visible"
                class="fixed z-50 glass-dark rounded-xl shadow-2xl py-1 w-48 border border-white/10 text-sm overflow-hidden select-none"
                :style="{ top: contextMenu.y + 'px', left: contextMenu.x + 'px' }" @click.stop>
                <div v-if="contextMenu.target">
                    <div class="px-4 py-2 hover:bg-blue-600 cursor-pointer text-gray-200"
                        @click="handleAppClick(contextMenu.target)">打开应用</div>
                    <div class="px-4 py-2 hover:bg-blue-600 cursor-pointer text-red-400"
                        @click="deleteItem(contextMenu.target)">删除图标</div>
                    <div class="h-px bg-white/10 my-1"></div>
                </div>
                <div class="px-4 py-2 hover:bg-blue-600 cursor-pointer flex justify-between group"
                    @click="createFolder"><span>新建文件夹</span> <i
                        class="ri-folder-add-line opacity-50 group-hover:opacity-100"></i></div>
                <div class="px-4 py-2 hover:bg-blue-600 cursor-pointer flex justify-between group"
                    @click="openSettings('壁纸风格')"><span>切换壁纸</span> <i
                        class="ri-image-line opacity-50 group-hover:opacity-100"></i></div>
                <div class="px-4 py-2 hover:bg-blue-600 cursor-pointer flex justify-between group"
                    @click="openSettings('界面布局')"><span>系统设置</span> <i
                        class="ri-settings-line opacity-50 group-hover:opacity-100"></i></div>
            </div>
        </transition>
        <transition name="folder-zoom">
            <div v-if="openedFolder" class="fixed inset-0 z-40 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="openedFolder = null"></div>
                <div class="glass-dark w-[500px] h-[400px] rounded-3xl p-6 relative flex flex-col shadow-2xl">
                    <h3 class="text-center text-xl font-medium mb-6 text-white/90">{{ openedFolder.name }}</h3>
                    <div id="folder-grid"
                        class="flex-1 grid grid-cols-4 gap-4 content-start overflow-y-auto p-2 min-h-[200px]">
                        <div v-for="sub in openedFolder.children" :key="sub.id" :data-id="sub.id"
                            class="folder-open-item group">
                            <div class="folder-open-icon" :style="{background: sub.color}"><i :class="sub.icon"></i>
                            </div><span class="text-xs opacity-80">{{ sub.name }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <transition name="fade">
            <div v-if="isSettingsOpen" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="isSettingsOpen = false"></div>
                <div class="glass-dark w-[800px] h-[600px] rounded-2xl flex shadow-2xl relative z-10 overflow-hidden">
                    <div class="w-64 bg-black/20 p-6 flex flex-col gap-2 border-r border-white/5">
                        <div class="text-xs font-bold text-gray-400 mb-4 uppercase tracking-wider px-2">设置中心</div>
                        <button v-for="tab in tabs" @click="activeTab = tab"
                            :class="activeTab === tab ? 'bg-blue-600 text-white shadow-lg' : 'hover:bg-white/5 text-gray-300'"
                            class="text-left px-4 py-3 rounded-lg transition text-sm font-medium flex items-center gap-2">
                            {{ tab }}
                        </button>
                    </div>

                    <div class="flex-1 p-8 overflow-y-auto no-scrollbar">
                        <h2 class="text-2xl font-bold mb-6 border-b border-white/10 pb-4">{{ activeTab }}</h2>

                        <div v-if="activeTab === '搜索引擎'">
                            <div class="flex flex-col gap-3" id="engine-list">
                                <div v-for="(eng, index) in engines" :key="eng.id"
                                    class="glass-panel p-4 rounded-lg flex items-center justify-between group">
                                    <div class="flex items-center gap-4 flex-1"><i :class="eng.icon"
                                            class="text-2xl opacity-70"></i>
                                        <div>
                                            <div class="font-bold">{{ eng.name }}</div>
                                            <div class="text-xs opacity-50 truncate max-w-[200px]">{{ eng.url }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="activeTab === '界面布局'">
                            <div class="flex justify-between items-center mb-6"><span>底部 Dock 栏</span><button
                                    @click="settings.showDock = !settings.showDock"
                                    :class="settings.showDock ? 'bg-green-500' : 'bg-gray-600'"
                                    class="w-10 h-5 rounded-full relative transition-colors">
                                    <div :class="settings.showDock ? 'translate-x-5' : 'translate-x-0.5'"
                                        class="w-4 h-4 bg-white rounded-full absolute top-0.5 left-0 transition-transform">
                                    </div>
                                </button></div>
                            <div class="mb-6"><label class="block mb-3 text-sm text-gray-400">背景模糊程度</label><input
                                    type="range" v-model="settings.bgBlur" min="0" max="30"
                                    class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-blue-500">
                            </div>
                        </div>

                        <div v-if="activeTab === '壁纸风格'" class="h-full flex flex-col">

                            <div class="mb-4">
                                <div
                                    class="glass-panel px-4 py-3 rounded-xl flex items-center w-full focus-within:bg-white/10 transition-colors">
                                    <i class="ri-search-line opacity-50 mr-3 text-lg"></i>
                                    <input v-model="unsplashQuery" @keyup.enter="fetchUnsplashWallpapers(null)"
                                        type="text" placeholder="搜索 Unsplash 海量壁纸 (如: Mountain)..."
                                        class="bg-transparent w-full outline-none text-base text-white placeholder-gray-500">
                                </div>
                                <div v-if="!unsplashAccessKey" class="mt-2 text-xs text-yellow-500/80 pl-1">
                                    <i class="ri-alert-line mr-1"></i> 未配置 Unsplash Key，仅显示默认壁纸。请前往后台设置。
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2 mb-6 shrink-0">
                                <button @click="wallpapers = defaultWallpapers"
                                    class="px-4 py-1.5 rounded-full text-xs font-medium border border-white/10 transition-all active:scale-95"
                                    :class="wallpapers === defaultWallpapers ? 'bg-white text-black' : 'hover:bg-white/10 text-gray-300'">
                                    推荐
                                </button>
                                <button v-for="tag in wallpaperTags" :key="tag" @click="fetchUnsplashWallpapers(tag)"
                                    class="px-4 py-1.5 rounded-full text-xs font-medium border border-white/10 hover:bg-white/10 text-gray-300 transition-all active:scale-95">
                                    {{ tag }}
                                </button>
                            </div>

                            <div class="flex-1 overflow-y-auto no-scrollbar relative min-h-[300px]">
                                <div v-if="isLoadingWallpapers"
                                    class="absolute inset-0 flex items-center justify-center z-10 bg-black/20 backdrop-blur-sm rounded-lg">
                                    <i class="ri-loader-4-line animate-spin text-3xl text-blue-400"></i>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                    <div v-for="wp in wallpapers" :key="wp.id" @click="setWallpaper(wp.urls.regular)"
                                        class="aspect-video rounded-xl bg-gray-800 bg-cover bg-center cursor-pointer border-2 transition hover:scale-[1.02] hover:shadow-xl relative group overflow-hidden"
                                        :class="currentWallpaper === wp.urls.regular ? 'border-blue-500' : 'border-transparent'"
                                        :style="{ backgroundImage: `url(${wp.urls.small || wp.urls.regular})` }">

                                        <div v-if="currentWallpaper === wp.urls.regular"
                                            class="absolute inset-0 bg-blue-500/20 flex items-center justify-center">
                                            <div
                                                class="bg-blue-500 rounded-full w-8 h-8 flex items-center justify-center shadow-lg">
                                                <i class="ri-check-line text-white text-lg font-bold"></i>
                                            </div>
                                        </div>
                                        <div v-if="wp.user"
                                            class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-3 pt-6 text-[10px] translate-y-full group-hover:translate-y-0 transition duration-300">
                                            <p class="truncate text-white font-medium">{{ wp.user.name }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="wallpapers.length > 0 && wallpapers !== defaultWallpapers"
                                    class="mt-6 text-center pb-4">
                                    <button @click="fetchUnsplashWallpapers()"
                                        class="px-6 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-sm text-gray-300 transition flex items-center mx-auto gap-2">
                                        <i class="ri-refresh-line"></i> 换一批
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="activeTab === '关于我们'" class="text-center pt-8">
                            <h3 class="text-2xl font-bold">GsNav Pro</h3>
                            <p class="text-white/50">Version 3.1.0</p>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

    </div>
    <script type="module" src="<?php echo GSNAV_PLUGIN_URL . 'assets/js/app.js'; ?>"></script>
</body>

</html>