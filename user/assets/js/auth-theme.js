/* 
作者：殒狐FOX
文件创建时间：2025-11-13
最后编辑时间：2025-11-15
文件描述：黑夜主题切换保存功能

*/
(function () {
    const storageKey = 'auth-theme';
    const root = document.documentElement;
    const mediaQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    const getStoredTheme = () => {
        try {
            return window.localStorage.getItem(storageKey);
        } catch (error) {
            console.warn('无法访问本地存储，主题偏好将不会持久化。', error);
            return null;
        }
    };

    const storeTheme = (theme) => {
        try {
            window.localStorage.setItem(storageKey, theme);
        } catch (error) {
            console.warn('无法保存主题偏好。', error);
        }
    };

    const applyTheme = (theme, options = {}) => {
        const nextTheme = theme === 'dark' ? 'dark' : 'light';
        const body = document.body;
        const toDark = nextTheme === 'dark';
        const shouldAnimate = options.withTransition ?? applyTheme.initialized;

        const commitTheme = () => {
            root.setAttribute('data-theme', nextTheme);
            storeTheme(nextTheme);
            updateToggle(nextTheme);
        };

        if (!shouldAnimate || !body) {
            commitTheme();
            applyTheme.initialized = true;
            if (body) {
                body.classList.remove('theme-transition', 'theme-transition--to-dark', 'theme-transition--to-light');
            }
            return;
        }

        body.classList.remove('theme-transition', 'theme-transition--to-dark', 'theme-transition--to-light');
        void body.offsetWidth;
        body.classList.add('theme-transition', toDark ? 'theme-transition--to-dark' : 'theme-transition--to-light');

        window.clearTimeout(applyTheme._themeTimer);
        window.clearTimeout(applyTheme._cleanupTimer);

        applyTheme._themeTimer = window.setTimeout(() => {
            commitTheme();
        }, 140);

        applyTheme._cleanupTimer = window.setTimeout(() => {
            body.classList.remove('theme-transition', 'theme-transition--to-dark', 'theme-transition--to-light');
        }, 820);

        applyTheme.initialized = true;
    };
    applyTheme.initialized = false;

    const updateToggle = (theme) => {
        const toggle = document.querySelector('[data-theme-toggle]');
        if (!toggle) {
            return;
        }
        
        // 检查是否是新的开关样式（checkbox）
        const checkbox = toggle.querySelector('input[type="checkbox"]');
        if (checkbox) {
            // 新开关样式：同步 checkbox 状态
            // checked=true 表示黑夜模式（蓝色背景），checked=false 表示明亮模式（黑色背景）
            checkbox.checked = theme === 'dark';
            toggle.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
            toggle.setAttribute('data-theme-current', theme);
            toggle.setAttribute('title', theme === 'dark' ? '切换到明亮模式' : '切换到黑夜模式');
            return;
        }
        
        // 旧按钮样式：更新图标和文本
        const icon = toggle.querySelector('.theme-toggle__icon');
        const text = toggle.querySelector('.theme-toggle__text');

        if (icon) {
            icon.textContent = theme === 'dark' ? '☀' : '☾';
        }
        if (text) {
            text.textContent = theme === 'dark' ? '明亮模式' : '黑夜模式';
        }
        toggle.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
        toggle.setAttribute('data-theme-current', theme);
        toggle.setAttribute('title', theme === 'dark' ? '切换到明亮模式' : '切换到黑夜模式');
        toggle.classList.toggle('is-dark', theme === 'dark');
    };

    const initTheme = () => {
        const stored = getStoredTheme();
        const systemPrefersDark = mediaQuery ? mediaQuery.matches : false;
        applyTheme(stored || (systemPrefersDark ? 'dark' : 'light'), { withTransition: false });
    };

    document.addEventListener('DOMContentLoaded', () => {
        initTheme();

        const toggle = document.querySelector('[data-theme-toggle]');
        if (toggle) {
            // 检查是否是新的开关样式（checkbox）
            const checkbox = toggle.querySelector('input[type="checkbox"]');
            if (checkbox) {
                // 新开关样式：监听 checkbox 变化
                // checked=true 表示切换到黑夜模式（蓝色背景），checked=false 表示切换到明亮模式（黑色背景）
                checkbox.addEventListener('change', (e) => {
                    const newTheme = e.target.checked ? 'dark' : 'light';
                    applyTheme(newTheme);
                });
            } else {
                // 旧按钮样式：监听点击事件
                toggle.addEventListener('click', () => {
                    const current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                    applyTheme(current === 'dark' ? 'light' : 'dark');
                });
            }
        }
    });

    if (mediaQuery && typeof mediaQuery.addEventListener === 'function') {
        mediaQuery.addEventListener('change', (event) => {
            const stored = getStoredTheme();
            if (!stored) {
                applyTheme(event.matches ? 'dark' : 'light');
            }
        });
    }
})();

