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

    const isSimpleMode = ref(false);
    const isSettingsOpen = ref(false);
    const activeTab = ref("壁纸风格");
    const contextMenu = reactive({
      visible: false,
      x: 0,
      y: 0,
      target: null,
      area: "desktop",
    });

    const CONFIG = window.GSNAV_CONFIG || {
      unsplashKey: "",
      pixabayKey: "",
      pexelsKey: "",
      ajaxUrl: "",
      viewer: {},
      desktopPayload: {},
      canSaveDesktop: false,
      desktopNonce: "",
    };
    const unsplashAccessKey = ref(CONFIG.unsplashKey);
    const pixabayAccessKey = ref(CONFIG.pixabayKey);
    const pexelsAccessKey = ref(CONFIG.pexelsKey);
    const desktopPayload = CONFIG.desktopPayload || {};
    const payloadSettings = desktopPayload.settings || {};
    const payloadSearch = desktopPayload.search || {};
    const viewer = reactive({
      isLoggedIn: Boolean(CONFIG.viewer?.isLoggedIn),
      loginUrl: CONFIG.viewer?.loginUrl || "",
      logoutUrl: CONFIG.viewer?.logoutUrl || "",
      profileUrl: CONFIG.viewer?.profileUrl || "",
      user: CONFIG.viewer?.user || null,
    });
    const canSaveDesktop = Boolean(CONFIG.canSaveDesktop);
    const desktopNonce = CONFIG.desktopNonce || "";
    const currentDesktop = ref(desktopPayload.desktop || { id: 0, scope: "system_default" });

    const fallbackEngines = [
      {
        id: "baidu",
        name: "百度",
        icon: "ri-baidu-fill",
        url: "https://www.baidu.com/s?wd=",
      },
      {
        id: "google",
        name: "Google",
        icon: "ri-google-fill",
        url: "https://www.google.com/search?q=",
      },
      {
        id: "bing",
        name: "Bing",
        icon: "ri-microsoft-fill",
        url: "https://www.bing.com/search?q=",
      },
    ];

    // --- 壁纸系统 ---
    const activeSource = ref(payloadSettings.wallpaper?.source || "bing");
    const isLoadingWallpapers = ref(false);
    const bgLoaded = ref(false);
    const currentPage = ref(1);

    const defaultWallpapers = [
      {
        id: "def",
        type: "image",
        src: "https://images.unsplash.com/photo-1620641788421-7a1c342ea42e?q=80&w=2574",
        thumbnail:
          "https://images.unsplash.com/photo-1620641788421-7a1c342ea42e?q=80&w=400",
        user: "Unsplash",
      },
    ];

    const currentWallpapers = ref([...defaultWallpapers]);
    const currentWallpaperObj = ref(
      payloadSettings.wallpaper?.current || defaultWallpapers[0],
    );

    // 内置分类体系 (完全代替搜索)
    const activeCategory = ref("backgrounds");

    const wallpaperCategories = [
      { label: "背景", value: "backgrounds" },
      { label: "时尚", value: "fashion" },
      { label: "自然", value: "nature" },
      { label: "科学", value: "science" },
      { label: "教育", value: "education" },
      { label: "情感", value: "feelings" },
      { label: "健康", value: "health" },
      { label: "人物", value: "people" },
      { label: "宗教", value: "religion" },
      { label: "地点", value: "places" },
      { label: "动物", value: "animals" },
      { label: "工业", value: "industry" },
      { label: "计算机", value: "computer" },
      { label: "美食", value: "food" },
      { label: "运动", value: "sports" },
      { label: "交通", value: "transportation" },
      { label: "旅行", value: "travel" },
      { label: "建筑", value: "buildings" },
      { label: "商业", value: "business" },
      { label: "音乐", value: "music" },
      { label: "动漫", value: "anime" },
    ];

    const tabIcon = (tab) => {
      const map = {
        界面布局: "ri-layout-masonry-line",
        壁纸风格: "ri-image-line",
        搜索引擎: "ri-search-eye-line",
        关于我们: "ri-information-line",
      };
      return map[tab] || "ri-settings-3-line";
    };

    // 切换壁纸源
    const switchWallpaperSource = (source) => {
      if (activeSource.value === source) return;
      activeSource.value = source;
      currentWallpapers.value = [];
      currentPage.value = 1;
      fetchWallpapers(false);
    };

    // 选择分类标签
    const selectCategory = (val) => {
      if (activeCategory.value === val) return;
      activeCategory.value = val;
      currentPage.value = 1;
      currentWallpapers.value = [];
      fetchWallpapers(false);
    };

    // 核心拉取逻辑
    const fetchWallpapers = async (isLoadMore = false) => {
      if (isLoadingWallpapers.value) return;

      const q = activeCategory.value;

      if (activeSource.value === "unsplash") {
        await fetchUnsplash(q, isLoadMore);
      } else if (activeSource.value === "pixabay") {
        await fetchPixabay(q, isLoadMore);
      } else if (activeSource.value === "pexels") {
        await fetchPexels(q, isLoadMore); // 新增 Pexels 分支
      } else if (activeSource.value === "bing") {
        await fetchBing();
      }
    };

    const fetchPexels = async (q, isLoadMore) => {
      if (!pexelsAccessKey.value) return;
      isLoadingWallpapers.value = true;
      try {
        // Pexels API: 强制请求横屏视频 (landscape)
        const res = await fetch(
          `https://api.pexels.com/videos/search?query=${encodeURIComponent(q)}&orientation=landscape&per_page=15&page=${currentPage.value}`,
          {
            headers: {
              Authorization: pexelsAccessKey.value,
            },
          },
        );

        if (!res.ok) throw new Error("Pexels API Error");
        const data = await res.json();

        if (data.videos) {
          const newWps = data.videos
            .map((item) => {
              // Pexels 视频质量选择逻辑：
              // 背景：优先找 HD 质量的 mp4
              // 预览：优先找 SD 质量的 mp4 (更省流)
              const hdVideo =
                item.video_files.find(
                  (v) => v.quality === "hd" && v.file_type === "video/mp4",
                ) || item.video_files.find((v) => v.file_type === "video/mp4"); // 兜底

              const sdVideo =
                item.video_files.find(
                  (v) => v.quality === "sd" && v.file_type === "video/mp4",
                ) || hdVideo;

              return {
                id: "px_" + item.id + "_" + Date.now(),
                type: "video",
                src: hdVideo ? hdVideo.link : "",
                previewVideo: sdVideo ? sdVideo.link : "",
                thumbnail: item.image, // Pexels 的封面图通常非常清晰
                user: item.user.name,
              };
            })
            .filter((v) => v.src); // 过滤掉无效链接

          if (isLoadMore) currentWallpapers.value.push(...newWps);
          else currentWallpapers.value = newWps;
        }
      } catch (e) {
        console.error("Pexels error:", e);
      } finally {
        isLoadingWallpapers.value = false;
      }
    };

    const fetchUnsplash = async (q, isLoadMore) => {
      if (!unsplashAccessKey.value) {
        if (!isLoadMore) currentWallpapers.value = [...defaultWallpapers];
        return;
      }
      isLoadingWallpapers.value = true;
      try {
        const res = await fetch(
          `https://api.unsplash.com/photos/random?client_id=${unsplashAccessKey.value}&count=15&query=${encodeURIComponent(q)}&orientation=landscape`,
        );
        if (!res.ok) throw new Error("API Error");
        const data = await res.json();
        const newWps = data.map((item) => ({
          id: item.id + "_" + Date.now(),
          type: "image",
          src: item.urls.regular,
          thumbnail: item.urls.small,
          user: item.user.name,
        }));

        if (isLoadMore) currentWallpapers.value.push(...newWps);
        else currentWallpapers.value = newWps;
      } catch (e) {
        console.error(e);
        if (!isLoadMore) currentWallpapers.value = [...defaultWallpapers];
      } finally {
        isLoadingWallpapers.value = false;
      }
    };

    const fetchPixabay = async (q, isLoadMore) => {
      if (!pixabayAccessKey.value) return;
      isLoadingWallpapers.value = true;
      try {
        const res = await fetch(
          `https://pixabay.com/api/videos/?key=${pixabayAccessKey.value}&q=${encodeURIComponent(q)}&video_type=film&per_page=15&page=${currentPage.value}`,
        );
        if (!res.ok) throw new Error("API Error");
        const data = await res.json();
        if (data.hits) {
          const newWps = data.hits.map((item) => {
            const tinyVideo = item.videos.tiny || item.videos.small;
            return {
              id: "pb_" + item.id + "_" + Date.now(),
              type: "video",
              src: item.videos.large?.url || item.videos.medium?.url,
              previewVideo: tinyVideo?.url,
              thumbnail: item.picture_id
                ? `https://i.vimeocdn.com/video/${item.picture_id}_640x360.jpg`
                : tinyVideo?.thumbnail || "",
              user: item.user,
            };
          });
          if (isLoadMore) currentWallpapers.value.push(...newWps);
          else currentWallpapers.value = newWps;
        }
      } catch (e) {
        console.error(e);
      } finally {
        isLoadingWallpapers.value = false;
      }
    };

    const fetchBing = async () => {
      isLoadingWallpapers.value = true;
      try {
        const res = await fetch(`${CONFIG.ajaxUrl}?action=gsnav_get_bing`);
        const json = await res.json();
        if (json.success) currentWallpapers.value = json.data;
      } catch (e) {
        console.error(e);
      } finally {
        isLoadingWallpapers.value = false;
      }
    };

    // 无限滚动监听器
    const onWallpaperScroll = (e) => {
      const { scrollTop, scrollHeight, clientHeight } = e.target;
      if (scrollTop + clientHeight >= scrollHeight - 100) {
        if (!isLoadingWallpapers.value && activeSource.value !== "bing") {
          currentPage.value++;
          fetchWallpapers(true);
        }
      }
    };

    const setWallpaper = (wp) => {
      bgLoaded.value = false;
      setTimeout(() => {
        currentWallpaperObj.value = wp;
        localStorage.setItem("gs_current_wallpaper_obj", JSON.stringify(wp));
        setTimeout(() => (bgLoaded.value = true), 300);
      }, 200);
    };

    const playPreview = (e) => {
      const video = e.currentTarget.querySelector("video");
      if (video) {
        const playPromise = video.play();
        if (playPromise !== undefined) playPromise.catch(() => {});
      }
    };

    const pausePreview = (e) => {
      const video = e.currentTarget.querySelector("video");
      if (video) {
        video.pause();
        video.currentTime = 0;
      }
    };

    const desktopApps = ref(
      Array.isArray(desktopPayload.desktopApps) ? desktopPayload.desktopApps : [],
    );
    const dockApps = ref(
      Array.isArray(desktopPayload.dockApps) ? desktopPayload.dockApps : [],
    );
    const openedFolder = ref(null);
    const isSavingDesktop = ref(false);

    const closeContextMenu = () => {
      contextMenu.visible = false;
      contextMenu.target = null;
    };

    const showSaveGuard = () => {
      if (!viewer.isLoggedIn && viewer.loginUrl) {
        const shouldLogin = window.confirm(
          "登录后即可同步和保存你的桌面，是否现在前往登录？",
        );

        if (shouldLogin) {
          window.location.href = viewer.loginUrl;
        }

        return;
      }

      window.alert("当前桌面暂不支持保存。请登录后编辑你的个人桌面。");
    };

    const cloneState = () =>
      JSON.parse(
        JSON.stringify({
          desktop: currentDesktop.value,
          desktopApps: desktopApps.value,
          dockApps: dockApps.value,
        }),
      );

    const restoreState = (snapshot) => {
      currentDesktop.value = snapshot.desktop || { id: 0, scope: "system_default" };
      desktopApps.value = Array.isArray(snapshot.desktopApps)
        ? snapshot.desktopApps
        : [];
      dockApps.value = Array.isArray(snapshot.dockApps) ? snapshot.dockApps : [];
      openedFolder.value = null;
    };

    const findFolderByName = (folderName) => {
      const groups = [desktopApps.value, dockApps.value];
      for (const items of groups) {
        const folder = items.find(
          (item) => item.type === "folder" && item.name === folderName,
        );
        if (folder) {
          return folder;
        }
      }
      return null;
    };

    const applyDesktopPayload = (payload, reopenFolderName = "") => {
      currentDesktop.value = payload.desktop || { id: 0, scope: "system_default" };
      desktopApps.value = Array.isArray(payload.desktopApps)
        ? payload.desktopApps
        : [];
      dockApps.value = Array.isArray(payload.dockApps) ? payload.dockApps : [];
      openedFolder.value = reopenFolderName
        ? findFolderByName(reopenFolderName)
        : null;
    };

    const serializeItem = (item) => {
      const serialized = {
        id: item.id,
        name: item.name || "",
        type: item.type === "folder" ? "folder" : "app",
        icon: item.icon || (item.type === "folder" ? "ri-folder-3-fill" : "ri-links-fill"),
        color:
          item.color ||
          (item.type === "folder" ? "rgba(255,255,255,0.25)" : "#4F46E5"),
        url: item.type === "folder" ? "" : item.url || "",
      };

      if (serialized.type === "folder") {
        serialized.children = Array.isArray(item.children)
          ? item.children.map(serializeItem)
          : [];
      }

      return serialized;
    };

    const saveDesktopState = async (reopenFolderName = "") => {
      if (!canSaveDesktop || !currentDesktop.value?.id) {
        return false;
      }

      isSavingDesktop.value = true;

      try {
        const formData = new URLSearchParams();
        formData.set("action", "gsnav_save_desktop_items");
        formData.set("nonce", desktopNonce);
        formData.set("desktopId", String(currentDesktop.value.id));
        formData.set(
          "items",
          JSON.stringify({
            desktopApps: desktopApps.value.map(serializeItem),
            dockApps: dockApps.value.map(serializeItem),
          }),
        );

        const response = await fetch(CONFIG.ajaxUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
          },
          body: formData.toString(),
        });
        const json = await response.json();

        if (!response.ok || !json.success || !json.data?.desktopPayload) {
          throw new Error(json.data?.message || "保存失败");
        }

        applyDesktopPayload(json.data.desktopPayload, reopenFolderName);
        return true;
      } catch (error) {
        window.alert(error.message || "保存桌面失败，请稍后重试。");
        return false;
      } finally {
        isSavingDesktop.value = false;
      }
    };

    const runPersistedMutation = async (mutator, reopenFolderName = "") => {
      if (!canSaveDesktop) {
        showSaveGuard();
        closeContextMenu();
        return;
      }

      const snapshot = cloneState();

      try {
        mutator();
        const saved = await saveDesktopState(reopenFolderName);

        if (!saved) {
          restoreState(snapshot);
        }
      } catch (error) {
        restoreState(snapshot);
        window.alert(error.message || "桌面操作失败。");
      } finally {
        closeContextMenu();
      }
    };

    const createTempId = () => `tmp_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;

    const getAreaItems = (area) => {
      if (area === "dock") {
        return dockApps.value;
      }

      return desktopApps.value;
    };

    const setAreaItems = (area, items) => {
      if (area === "dock") {
        dockApps.value = items;
        return;
      }

      desktopApps.value = items;
    };

    const findItemLocation = (itemId) => {
      const desktopIndex = desktopApps.value.findIndex((item) => item.id === itemId);

      if (desktopIndex >= 0) {
        return {
          area: "desktop",
          index: desktopIndex,
          items: desktopApps.value,
          item: desktopApps.value[desktopIndex],
          parentFolder: null,
        };
      }

      const dockIndex = dockApps.value.findIndex((item) => item.id === itemId);

      if (dockIndex >= 0) {
        return {
          area: "dock",
          index: dockIndex,
          items: dockApps.value,
          item: dockApps.value[dockIndex],
          parentFolder: null,
        };
      }

      for (const folder of desktopApps.value) {
        if (folder.type !== "folder" || !Array.isArray(folder.children)) {
          continue;
        }

        const childIndex = folder.children.findIndex((item) => item.id === itemId);

        if (childIndex >= 0) {
          return {
            area: "folder",
            index: childIndex,
            items: folder.children,
            item: folder.children[childIndex],
            parentFolder: folder,
          };
        }
      }

      for (const folder of dockApps.value) {
        if (folder.type !== "folder" || !Array.isArray(folder.children)) {
          continue;
        }

        const childIndex = folder.children.findIndex((item) => item.id === itemId);

        if (childIndex >= 0) {
          return {
            area: "folder",
            index: childIndex,
            items: folder.children,
            item: folder.children[childIndex],
            parentFolder: folder,
          };
        }
      }

      return null;
    };

    const getCurrentContextArea = () => {
      if (contextMenu.target) {
        const location = findItemLocation(contextMenu.target.id);
        return location ? location.area : contextMenu.area;
      }

      return contextMenu.area || "desktop";
    };

    const handleAppClick = (item) => {
      if (item.type === "folder") {
        openedFolder.value = item;
      } else if (item.url) {
        window.open(item.url, "_blank");
      }
      closeContextMenu();
    };

    const promptForLink = (defaultName = "", defaultUrl = "") => {
      const name = window.prompt("请输入链接名称", defaultName || "新建链接");

      if (name === null) {
        return null;
      }

      const url = window.prompt("请输入完整链接地址", defaultUrl || "https://");

      if (url === null) {
        return null;
      }

      return {
        name: name.trim() || "新建链接",
        url: url.trim(),
      };
    };

    const promptForFolderName = (defaultName = "") => {
      const name = window.prompt("请输入文件夹名称", defaultName || "新建文件夹");

      if (name === null) {
        return null;
      }

      return name.trim() || "新建文件夹";
    };

    const createLink = async () => {
      const area = getCurrentContextArea();
      const linkData = promptForLink();

      if (!linkData) {
        closeContextMenu();
        return;
      }

      const reopenFolderName =
        area === "folder" && openedFolder.value ? openedFolder.value.name : "";

      await runPersistedMutation(() => {
        const linkItem = {
          id: createTempId(),
          name: linkData.name,
          type: "app",
          icon: "ri-links-fill",
          color: "#4F46E5",
          url: linkData.url,
        };

        if (area === "dock") {
          dockApps.value = [...dockApps.value, linkItem];
          return;
        }

        if (area === "folder" && openedFolder.value) {
          openedFolder.value.children = [...openedFolder.value.children, linkItem];
          return;
        }

        desktopApps.value = [...desktopApps.value, linkItem];
      }, reopenFolderName);
    };

    const createFolder = async () => {
      const area = getCurrentContextArea();

      if (area === "folder") {
        window.alert("当前版本不支持在文件夹内继续创建文件夹。");
        closeContextMenu();
        return;
      }

      const folderName = promptForFolderName();

      if (!folderName) {
        closeContextMenu();
        return;
      }

      await runPersistedMutation(() => {
        const folderItem = {
          id: createTempId(),
          name: folderName,
          type: "folder",
          children: [],
          icon: "ri-folder-3-fill",
          color: "rgba(255,255,255,0.25)",
          url: null,
        };

        const items = getAreaItems(area);
        setAreaItems(area, [...items, folderItem]);
      });
    };

    const addLinkToFolder = async (folder) => {
      if (!folder || folder.type !== "folder") {
        return;
      }

      const linkData = promptForLink();

      if (!linkData) {
        closeContextMenu();
        return;
      }

      await runPersistedMutation(() => {
        folder.children = [
          ...(Array.isArray(folder.children) ? folder.children : []),
          {
            id: createTempId(),
            name: linkData.name,
            type: "app",
            icon: "ri-links-fill",
            color: "#4F46E5",
            url: linkData.url,
          },
        ];
      }, folder.name);
    };

    const editItem = async (item) => {
      if (!item) {
        return;
      }

      if (item.type === "folder") {
        const nextName = promptForFolderName(item.name);

        if (!nextName) {
          closeContextMenu();
          return;
        }

        await runPersistedMutation(() => {
          item.name = nextName;
        }, openedFolder.value && openedFolder.value.id === item.id ? nextName : "");

        return;
      }

      const linkData = promptForLink(item.name, item.url);

      if (!linkData) {
        closeContextMenu();
        return;
      }

      await runPersistedMutation(() => {
        item.name = linkData.name;
        item.url = linkData.url;
      }, openedFolder.value ? openedFolder.value.name : "");
    };

    const deleteItem = async (item) => {
      if (!item) {
        return;
      }

      if (!window.confirm(`确认删除「${item.name}」吗？`)) {
        closeContextMenu();
        return;
      }

      const location = findItemLocation(item.id);

      if (!location) {
        closeContextMenu();
        return;
      }

      await runPersistedMutation(() => {
        location.items.splice(location.index, 1);
      }, location.parentFolder ? location.parentFolder.name : "");
    };

    const moveItem = async (item, direction) => {
      const location = item ? findItemLocation(item.id) : null;

      if (!location) {
        closeContextMenu();
        return;
      }

      const nextIndex =
        direction === "up" ? location.index - 1 : location.index + 1;

      if (nextIndex < 0 || nextIndex >= location.items.length) {
        closeContextMenu();
        return;
      }

      await runPersistedMutation(() => {
        const [movedItem] = location.items.splice(location.index, 1);
        location.items.splice(nextIndex, 0, movedItem);
      }, location.parentFolder ? location.parentFolder.name : "");
    };

    const detectContextArea = (event) => {
      if (event.target.closest("#folder-grid")) {
        return "folder";
      }

      if (event.target.closest("#dock-list")) {
        return "dock";
      }

      if (event.target.closest("#desktop-list")) {
        return "desktop";
      }

      return "desktop";
    };

    const onGlobalContextMenu = (e) => {
      e.preventDefault();
      const appItem = e.target.closest("[data-id]");
      let targetItem = null;

      if (appItem) {
        const rawId = appItem.getAttribute("data-id");
        const parsedId = String(rawId).startsWith("tmp_") ? rawId : Number(rawId);
        const location = findItemLocation(parsedId);
        targetItem = location ? location.item : null;
      }

      contextMenu.target = targetItem;
      contextMenu.area = targetItem
        ? findItemLocation(targetItem.id)?.area || detectContextArea(e)
        : detectContextArea(e);

      let x = e.clientX;
      let y = e.clientY;

      if (x + 220 > window.innerWidth) x -= 220;
      if (y + 300 > window.innerHeight) y -= 300;

      contextMenu.x = x;
      contextMenu.y = y;
      contextMenu.visible = true;
    };

    const engines = ref(
      Array.isArray(payloadSearch.engines) && payloadSearch.engines.length > 0
        ? payloadSearch.engines
        : fallbackEngines,
    );
    const defaultEngineId = payloadSearch.defaultEngineId || "baidu";
    const initialEngineIndex = Math.max(
      0,
      engines.value.findIndex((eng) => eng.id === defaultEngineId),
    );
    const currentEngineKey = ref(initialEngineIndex);
    const showEngineMenu = ref(false);
    const searchText = ref("");
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

    const timeHourMinute = ref("");
    const timeSecond = ref("");
    const dateString = ref("");
    const lunarData = reactive({ text: "", yi: "", ji: "" });
    const settings = reactive({
      showDock:
        typeof payloadSettings.showDock === "boolean"
          ? payloadSettings.showDock
          : true,
      bgBlur: Number(payloadSettings.bgBlur || 0),
    });
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
        lunarData.text = `${l.getMonthInChinese()}月${l.getDayInChinese()}`;
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
      const savedWpStr = localStorage.getItem("gs_current_wallpaper_obj");
      if (savedWpStr)
        try {
          currentWallpaperObj.value = JSON.parse(savedWpStr);
        } catch (e) {}

      fetchWallpapers(false);
      isSimpleMode.value = Boolean(payloadSettings.simpleMode);
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
      viewer,
      createLink,
      createFolder,
      addLinkToFolder,
      handleAppClick,
      editItem,
      deleteItem,
      moveItem,
      settings,
      engines,
      currentEngine,
      showEngineMenu,
      switchEngine,
      searchText,
      handleSearch,
      tabs: ["界面布局", "壁纸风格", "搜索引擎", "关于我们"],
      activeTab,
      openSettings,
      tabIcon,

      // 新增和修改的变量
      activeSource,
      switchWallpaperSource,
      wallpaperCategories,
      activeCategory,
      selectCategory,
      currentWallpapers,
      currentWallpaperObj,
      setWallpaper,
      fetchWallpapers,
      isLoadingWallpapers,
      bgLoaded,
      unsplashAccessKey,
      pixabayAccessKey,
      onWallpaperScroll,
      playPreview,
      pausePreview,
      pexelsAccessKey,
    };
  },
}).mount("#gs-app");
