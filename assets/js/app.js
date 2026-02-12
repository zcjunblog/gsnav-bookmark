import {
  createApp,
  ref,
  reactive,
  computed,
  onMounted,
  onUnmounted,
} from "https://unpkg.com/vue@3/dist/vue.esm-browser.js";
import { useWeather } from "./weather.js";

createApp({
  setup() {
    const { weather, fetchWeather } = useWeather();

    // --- 基础状态 ---
    const isSimpleMode = ref(false);
    const isSettingsOpen = ref(false);
    const activeTab = ref("界面布局");

    // --- 右键菜单 ---
    const contextMenu = reactive({ visible: false, x: 0, y: 0, target: null });

    // --- 壁纸系统 ---
    const defaultWallpapers = [
      {
        id: "def1",
        urls: {
          regular:
            "https://images.unsplash.com/photo-1506744038136-46273834b3fb?q=80&w=2670&auto=format&fit=crop",
        },
      },
      {
        id: "def2",
        urls: {
          regular:
            "https://images.unsplash.com/photo-1620641788421-7a1c342ea42e?q=80&w=2574&auto=format&fit=crop",
        },
      },
      {
        id: "def3",
        urls: {
          regular:
            "https://images.unsplash.com/photo-1550745165-9bc0b252726f?q=80&w=2670&auto=format&fit=crop",
        },
      },
      {
        id: "def4",
        urls: {
          regular:
            "https://images.unsplash.com/photo-1477346611705-65d1883cee1e?q=80&w=2670&auto=format&fit=crop",
        },
      },
    ];
    const wallpapers = ref([...defaultWallpapers]);
    const currentWallpaper = ref(defaultWallpapers[0].urls.regular);
    const unsplashQuery = ref("");

    // 关键修改：从全局配置读取 Key，不再从 localStorage 读取
    const unsplashAccessKey = ref(
      window.GSNAV_CONFIG ? window.GSNAV_CONFIG.unsplashKey : "",
    );

    const isLoadingWallpapers = ref(false);
    const wallpaperTags = [
      "Nature",
      "Architecture",
      "Cyberpunk",
      "Minimalist",
      "Space",
      "Night",
      "Anime",
    ];

    // --- 右键逻辑 (保持不变) ---
    const onGlobalContextMenu = (e) => {
      e.preventDefault();
      const appItem = e.target.closest(".app-item");
      let targetData = null;
      if (appItem) {
        const id = parseInt(appItem.getAttribute("data-id"));
        targetData =
          desktopApps.value.find((i) => i.id === id) ||
          dockApps.value.find((i) => i.id === id);
        if (!targetData && openedFolder.value) {
          targetData = openedFolder.value.children.find((i) => i.id === id);
        }
      }
      contextMenu.target = targetData;
      let x = e.clientX;
      let y = e.clientY;
      const menuWidth = 200;
      const menuHeight = targetData ? 220 : 160;
      if (x + menuWidth > window.innerWidth) x -= menuWidth;
      if (y + menuHeight > window.innerHeight) y -= menuHeight;
      contextMenu.x = x;
      contextMenu.y = y;
      contextMenu.visible = true;
    };

    const closeContextMenu = () => (contextMenu.visible = false);

    // --- 壁纸逻辑修改 ---
    const fetchUnsplashWallpapers = async (query = "") => {
      // 如果没有配置 Key，不再弹窗，直接显示默认（UI层有提示）
      if (!unsplashAccessKey.value) {
        console.warn("Unsplash Key 未配置，请在后台设置。");
        wallpapers.value = [...defaultWallpapers];
        return;
      }

      isLoadingWallpapers.value = true;
      const q = query || unsplashQuery.value || "random";

      try {
        const res = await fetch(
          `https://api.unsplash.com/photos/random?client_id=${unsplashAccessKey.value}&count=12&query=${q}&orientation=landscape`,
        );

        if (res.status === 403) throw new Error("API 调用次数超限或 Key 无效");
        if (res.status === 401) throw new Error("Key 无效");

        const data = await res.json();
        if (Array.isArray(data)) {
          wallpapers.value = data;
          unsplashQuery.value = q;
        }
      } catch (e) {
        console.error(e);
        // 失败静默回退，避免弹窗打扰
        wallpapers.value = [...defaultWallpapers];
      } finally {
        isLoadingWallpapers.value = false;
      }
    };

    // 移除了 saveUnsplashKey 函数，因为现在是在后台保存

    const setWallpaper = (url) => {
      currentWallpaper.value = url;
      localStorage.setItem("gs_current_wallpaper", url);
    };

    // --- 文件夹与应用逻辑 (保持不变) ---
    const openedFolder = ref(null);
    const handleAppClick = (item) => {
      if (item.type === "folder") {
        openedFolder.value = item;
      } else {
        if (item.url) window.open(item.url, "_blank");
      }
      closeContextMenu();
    };
    const createFolder = () => {
      desktopApps.value.push({
        id: Date.now(),
        name: "新建文件夹",
        type: "folder",
        children: [],
        icon: "ri-folder-3-fill",
        color: "rgba(255,255,255,0.2)",
      });
      closeContextMenu();
    };
    const deleteItem = (item) => {
      if (!item) return;
      const removeById = (list) => list.filter((i) => i.id !== item.id);
      desktopApps.value = removeById(desktopApps.value);
      dockApps.value = removeById(dockApps.value);
      if (openedFolder.value)
        openedFolder.value.children = removeById(openedFolder.value.children);
      closeContextMenu();
    };

    // --- 数据与引擎 (保持不变) ---
    const desktopApps = ref([
      {
        id: 1,
        name: "哔哩哔哩",
        type: "app",
        color: "#00A1D6",
        icon: "ri-bilibili-fill",
        url: "https://bilibili.com",
      },
      {
        id: 2,
        name: "GitHub",
        type: "app",
        color: "#171515",
        icon: "ri-github-fill",
        url: "https://github.com",
      },
      {
        id: 3,
        name: "ChatGPT",
        type: "app",
        color: "#10A37F",
        icon: "ri-openai-fill",
        url: "https://chat.openai.com",
      },
      {
        id: 4,
        name: "知乎",
        type: "app",
        color: "#0084FF",
        icon: "ri-zhihu-fill",
        url: "https://zhihu.com",
      },
      {
        id: 5,
        name: "YouTube",
        type: "app",
        color: "#FF0000",
        icon: "ri-youtube-fill",
        url: "https://youtube.com",
      },
    ]);
    const dockApps = ref([
      {
        id: 101,
        name: "Google",
        icon: "ri-google-fill",
        url: "https://google.com",
      },
      {
        id: 102,
        name: "Settings",
        icon: "ri-settings-3-fill",
        action: "settings",
      },
    ]);
    const engines = ref([
      {
        id: "baidu",
        name: "百度",
        icon: "ri-baidu-fill",
        url: "https://www.baidu.com/s?wd=",
      },
      {
        id: "bing",
        name: "必应",
        icon: "ri-microsoft-fill",
        url: "https://cn.bing.com/search?q=",
      },
      {
        id: "google",
        name: "谷歌",
        icon: "ri-google-fill",
        url: "https://www.google.com/search?q=",
      },
    ]);
    const currentEngineKey = ref(0);
    const showEngineMenu = ref(false);
    const searchText = ref("");
    const editingEngine = ref(null);
    const currentEngine = computed(
      () => engines.value[currentEngineKey.value] || engines.value[0],
    );
    const switchEngine = (i) => {
      currentEngineKey.value = i;
      showEngineMenu.value = false;
    };
    const handleSearch = () => {
      if (searchText.value)
        window.open(currentEngine.value.url + searchText.value);
    };
    const startEditEngine = (e) => {
      editingEngine.value = JSON.parse(JSON.stringify(e));
    };
    const saveEngine = () => {
      const idx = engines.value.findIndex(
        (e) => e.id === editingEngine.value.id,
      );
      if (idx !== -1) engines.value[idx] = editingEngine.value;
      else engines.value.push(editingEngine.value);
      editingEngine.value = null;
    };
    const deleteEngine = (id) =>
      (engines.value = engines.value.filter((e) => e.id !== id));
    const addEngine = () =>
      (editingEngine.value = {
        id: Date.now(),
        name: "新引擎",
        icon: "ri-search-line",
        url: "https://",
      });

    // --- 时间与天气 ---
    const timeHourMinute = ref("");
    const timeSecond = ref("");
    const dateString = ref("");
    const lunarData = reactive({ text: "", yi: "", ji: "" });
    const settings = reactive({ showDock: true, bgBlur: 0 });
    const updateTime = () => {
      const now = new Date();
      timeHourMinute.value = now.toLocaleTimeString("en-GB", {
        hour: "2-digit",
        minute: "2-digit",
      });
      timeSecond.value = now.getSeconds().toString().padStart(2, "0");
      dateString.value = now.getMonth() + 1 + "月" + now.getDate() + "日";
      if (window.Lunar) {
        const l = Lunar.fromDate(now);
        lunarData.text = `${l.getMonthInChinese()}月${l.getDayInChinese()} ${l.getWeekInChinese()}`;
        lunarData.yi = l.getDayYi().slice(0, 3).join(" ");
      }
    };
    const openSettings = (tab) => {
      activeTab.value = tab;
      isSettingsOpen.value = true;
      closeContextMenu();
    };

    onMounted(() => {
      updateTime();
      setInterval(updateTime, 1000);
      fetchWeather();
      const savedWp = localStorage.getItem("gs_current_wallpaper");
      if (savedWp) currentWallpaper.value = savedWp;
      document.addEventListener("contextmenu", onGlobalContextMenu);
      document.addEventListener("click", closeContextMenu);
    });
    onUnmounted(() => {
      document.removeEventListener("contextmenu", onGlobalContextMenu);
      document.removeEventListener("click", closeContextMenu);
    });

    return {
      weather,
      isSimpleMode,
      isSettingsOpen,
      timeHourMinute,
      timeSecond,
      dateString,
      lunarData,
      desktopApps,
      dockApps,
      contextMenu,
      openedFolder,
      createFolder,
      handleAppClick,
      deleteItem,
      settings,
      engines,
      currentEngine,
      showEngineMenu,
      switchEngine,
      searchText,
      handleSearch,
      editingEngine,
      startEditEngine,
      saveEngine,
      deleteEngine,
      addEngine,
      tabs: ["界面布局", "壁纸风格", "搜索引擎", "关于我们"],
      activeTab,
      openSettings,
      wallpapers,
      currentWallpaper,
      setWallpaper,
      unsplashQuery,
      unsplashAccessKey,
      fetchUnsplashWallpapers,
      isLoadingWallpapers,
      wallpaperTags,
      defaultWallpapers,
    };
  },
}).mount("#gs-app");
