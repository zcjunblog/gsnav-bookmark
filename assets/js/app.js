// assets/js/app.js
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

    // --- 状态 ---
    const isSimpleMode = ref(false);
    const isSettingsOpen = ref(false);
    const activeTab = ref("界面布局");
    // 核心菜单状态
    const contextMenu = reactive({ visible: false, x: 0, y: 0, target: null });

    // --- 核心修复：全局右键菜单逻辑 ---
    const onGlobalContextMenu = (e) => {
      // 1. 阻止浏览器默认右键菜单
      e.preventDefault();

      // 2. 判断点击目标
      // 尝试向上查找是否点击了 .app-item (图标)
      const appItem = e.target.closest(".app-item");

      // 如果点到了图标，target 就是那个图标的数据；否则 target 为 null (代表背景)
      let targetData = null;
      if (appItem) {
        const id = parseInt(appItem.getAttribute("data-id"));
        // 在桌面或 Dock 中查找对应的数据
        targetData =
          desktopApps.value.find((i) => i.id === id) ||
          dockApps.value.find((i) => i.id === id);

        // 如果是文件夹里的图标（需要额外处理，这里暂简化为不处理文件夹内的右键，或者你可以加逻辑）
        if (!targetData && openedFolder.value) {
          targetData = openedFolder.value.children.find((i) => i.id === id);
        }
      }

      // 3. 设置菜单目标
      contextMenu.target = targetData;

      // 4. 计算菜单位置 (防止溢出屏幕)
      let x = e.clientX;
      let y = e.clientY;
      const menuWidth = 200;
      const menuHeight = targetData ? 220 : 150; // 有目标时菜单更高

      if (x + menuWidth > window.innerWidth) x -= menuWidth;
      if (y + menuHeight > window.innerHeight) y -= menuHeight;

      contextMenu.x = x;
      contextMenu.y = y;
      contextMenu.visible = true;
    };

    // 点击左键关闭菜单
    const closeContextMenu = () => (contextMenu.visible = false);

    // --- 文件夹逻辑 ---
    const openedFolder = ref(null);

    const handleAppClick = (item) => {
      if (item.type === "folder") {
        openedFolder.value = item;
      } else {
        // 这里模拟打开链接
        console.log("打开链接:", item.url || item.name);
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

      if (openedFolder.value) {
        openedFolder.value.children = removeById(openedFolder.value.children);
      }
      closeContextMenu();
    };

    // --- 数据 ---
    const desktopApps = ref([
      {
        id: 1,
        name: "哔哩哔哩",
        type: "app",
        color: "#00A1D6",
        icon: "ri-bilibili-fill",
      },
      {
        id: 2,
        name: "GitHub",
        type: "app",
        color: "#171515",
        icon: "ri-github-fill",
      },
      {
        id: 3,
        name: "Code",
        type: "app",
        color: "#2F80ED",
        icon: "ri-code-line",
      },
      {
        id: 4,
        name: "ChatGPT",
        type: "app",
        color: "#10A37F",
        icon: "ri-openai-fill",
      },
      {
        id: 5,
        name: "知乎",
        type: "app",
        color: "#0084FF",
        icon: "ri-zhihu-fill",
      },
    ]);
    const dockApps = ref([
      { id: 101, name: "Finder", icon: "ri-finder-fill" },
      { id: 102, name: "Safari", icon: "ri-safari-fill" },
    ]);

    // --- 搜索引擎 & 其他 ---
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

    const timeHourMinute = ref("");
    const timeSecond = ref("");
    const dateString = ref("");
    const lunarData = reactive({ text: "", yi: "", ji: "" });
    const settings = reactive({ showDock: true, bgBlur: 0 });
    const wallpapers = [
      "https://images.unsplash.com/photo-1506744038136-46273834b3fb?q=80&w=2670&auto=format&fit=crop",
      "https://images.unsplash.com/photo-1620641788421-7a1c342ea42e?q=80&w=2574&auto=format&fit=crop",
      "https://images.unsplash.com/photo-1550745165-9bc0b252726f?q=80&w=2670&auto=format&fit=crop",
    ];
    const currentWallpaper = ref(wallpapers[0]);

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

    // --- 生命周期 ---
    onMounted(() => {
      updateTime();
      setInterval(updateTime, 1000);
      fetchWeather();

      // 绑定全局右键事件 (关键修复)
      document.addEventListener("contextmenu", onGlobalContextMenu);
      // 绑定全局点击事件 (关闭菜单)
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
      contextMenu, // 无需导出 handleContextMenu，因为是全局监听
      openedFolder,
      createFolder,
      handleAppClick,
      deleteItem,
      settings,
      currentWallpaper,
      wallpapers,
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
    };
  },
}).mount("#gs-app");
