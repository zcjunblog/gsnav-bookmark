/*
 * @Author: zhaozc
 * @Date: 2026-02-11 16:57:51
 * @Description: 
 * @LastEditors: zhaozc
 * @LastEditTime: 2026-02-11 17:07:22
 */
// assets/js/weather.js
import { reactive } from 'https://unpkg.com/vue@3/dist/vue.esm-browser.js';

const weatherIcons = {
    0: 'ri-sun-fill', 1: 'ri-sun-cloudy-line', 2: 'ri-cloudy-2-line', 3: 'ri-cloudy-fill',
    45: 'ri-foggy-fill', 48: 'ri-foggy-fill', 51: 'ri-drizzle-line', 53: 'ri-drizzle-line',
    55: 'ri-drizzle-line', 61: 'ri-rainy-line', 63: 'ri-showers-line', 65: 'ri-heavy-showers-line',
    71: 'ri-snowy-line', 95: 'ri-thunderstorms-line'
};

export function useWeather() {
    const weather = reactive({
        temp: '--',
        desc: '获取中...',
        icon: 'ri-loader-4-line',
        city: '定位中...'
    });

    // 核心获取逻辑
    const fetchFromApi = async (lat, lon, cityName = '本地') => {
        try {
            const res = await fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true`);
            const data = await res.json();
            const w = data.current_weather;
            
            weather.temp = Math.round(w.temperature);
            weather.icon = weatherIcons[w.weathercode] || 'ri-sun-line';
            weather.desc = w.weathercode <= 3 ? '晴朗' : (w.weathercode > 50 ? '雨雪' : '多云');
            weather.city = cityName;
        } catch (e) {
            console.error(e);
            weather.desc = "暂无数据";
        }
    };

    const fetchWeather = () => {
        if (!navigator.geolocation) {
            // 不支持定位，使用默认坐标 (北京)
            fetchFromApi(39.9042, 116.4074, '北京 (默认)');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                // 定位成功
                fetchFromApi(position.coords.latitude, position.coords.longitude, '当前位置');
            },
            (err) => {
                // 定位被拒 (开发环境常见)，降级使用北京坐标
                console.warn("定位被拒，使用默认坐标");
                fetchFromApi(39.9042, 116.4074, '北京'); 
            }
        );
    };

    return { weather, fetchWeather };
}