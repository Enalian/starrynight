<?php

namespace JoanFo\StarryNight;

use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\File;

class StarryNightPlugin implements Plugin, HasPluginSettings
{
    use EnvironmentWriterTrait;

    public function getId(): string
    {
        return 'starrynight';
    }

    public function getSettingsForm(): array
    {
        return [
            Section::make('Визуал и Производительность')
                ->description('Настройте внешний вид темы. Отключение метеоритов и снижение количества звезд повысит FPS на слабых ПК.')
                ->schema([
                    Toggle::make('enable_meteors')
                        ->label('Включить падающие метеориты')
                        ->default(fn () => config('starrynight.enable_meteors') === 'true' || config('starrynight.enable_meteors') === true)
                        ->columnSpanFull(),

                    ColorPicker::make('star_color')
                        ->label('Цвет звезд')
                        ->default(fn () => config('starrynight.star_color', '#ffffff')),

                    Select::make('flicker_speed')
                        ->label('Скорость мерцания')
                        ->options([
                            '5000' => 'Медленно (5 сек)',
                            '3000' => 'Нормально (3 сек)',
                            '1000' => 'Быстро (1 сек)',
                        ])
                        ->default(fn () => config('starrynight.flicker_speed', '3000')),

                    TextInput::make('total_stars')
                        ->label('Общее количество звезд')
                        ->numeric()
                        ->required()
                        ->default(fn () => config('starrynight.total_stars', 75)),

                    TextInput::make('flicker_count')
                        ->label('Количество мерцающих звезд')
                        ->numeric()
                        ->required()
                        ->default(fn () => config('starrynight.flicker_count', 15)),

                    TextInput::make('background_image')
                        ->label('Ссылка на фоновое изображение')
                        ->helperText('Вставьте прямую ссылку на .jpg или .png (например, с Imgur). Оставьте пустым для градиента темы.')
                        ->url()
                        ->nullable(),

                    TextInput::make('background_blur')
                        ->label('Размытие фона (px)')
                        ->numeric()
                        ->default(5)
                        ->helperText('Введите значение от 0 до 50'),
                ])->columns(2)
        ];
    }

    public function saveSettings(array $data): void
    {
        $this->writeToEnvironment([
            'STARRYNIGHT_ENABLE_METEORS'   => $data['enable_meteors'] ? 'true' : 'false',
            'STARRYNIGHT_STAR_COLOR'       => $data['star_color'],
            'STARRYNIGHT_FLICKER_SPEED'    => $data['flicker_speed'],
            'STARRYNIGHT_TOTAL_STARS'      => $data['total_stars'],
            'STARRYNIGHT_FLICKER_COUNT'    => $data['flicker_count'],
            'STARRYNIGHT_BACKGROUND_IMAGE' => $data['background_image'] ?? '',
            'STARRYNIGHT_BACKGROUND_BLUR'  => $data['background_blur'] ?? '0',
        ]);

        Notification::make()
            ->success()
            ->title('Настройки сохранены')
            ->body('Перезагрузите страницу для окончательного применения настроек.')
            ->send();
    }

    public function boot(Panel $panel): void {}

    public function register(Panel $panel): void
    {
        $pairs = [
            [__DIR__.'/../../css/starry-night.css', public_path('plugins/starrynight/css/starry-night.css')],
            [__DIR__.'/../../css/starry-night-light.css', public_path('plugins/starrynight/css/starry-night-light.css')],
        ];

        foreach ($pairs as [$source, $destination]) {
            try {
                if (!File::exists($destination) && File::exists($source)) {
                    $dir = dirname($destination);
                    if (!File::isDirectory($dir)) {
                        File::makeDirectory($dir, 0755, true);
                    }
                    File::copy($source, $destination);
                }
            } catch (\Throwable $e) {

            }
        }

        $panel
            ->font('Jura')
            ->colors([
                'danger' => Color::Rose,
                'gray' => Color::Slate,
                'info' => Color::Pink,
                'primary' => Color::hex('#1E90FF'),
                'success' => Color::Emerald,
                'warning' => Color::Amber,
            ]);

        $panel->renderHook('panels::head.end', function () {
            $dark = asset('plugins/starrynight/css/starry-night.css');
            $light = asset('plugins/starrynight/css/starry-night-light.css');

            return <<<HTML
<link id="starrynight-css" rel="stylesheet">
<script>
(function () {
    var dark = "{$dark}";
    var light = "{$light}";

    function detectThemeDetail() {
        if (document.documentElement && document.documentElement.classList.contains && document.documentElement.classList.contains('dark')) {
            return { useDark: true, source: 'html.class' };
        }

        var htmlTheme = document.documentElement && document.documentElement.getAttribute && document.documentElement.getAttribute('data-theme');
        if (htmlTheme) {
            var v = ('' + htmlTheme).toLowerCase();
            return { useDark: v === 'dark', source: 'html.data-theme', details: htmlTheme };
        }

        try {
            var keys = ['filament_theme', 'theme', 'filamentTheme'];
            for (var i = 0; i < keys.length; i++) {
                var k = keys[i];
                var val = localStorage.getItem(k);
                if (val) {
                    var lv = ('' + val).toLowerCase();
                    if (lv === 'dark' || lv === 'light') return { useDark: lv === 'dark', source: 'localStorage.' + k, details: val };
                }
            }

            var cap = Math.min(localStorage.length || 0, 50);
            for (var j = 0; j < cap; j++) {
                var key = localStorage.key(j);
                if (!key) continue;
                if (!/filament|theme/i.test(key)) continue;
                var v2 = localStorage.getItem(key);
                if (v2) {
                    var vl = ('' + v2).toLowerCase();
                    if (vl === 'dark' || vl === 'light') return { useDark: vl === 'dark', source: 'localStorage.' + key, details: v2 };
                }
            }
        } catch (e) {
        }

        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) return { useDark: true, source: 'prefers-color-scheme' };

        return { useDark: false, source: 'default' };
    }

    function apply() {
        var info = detectThemeDetail();

        if (!info) {
            return info;
        }

        var useDark = !!info.useDark;
        var el = document.getElementById('starrynight-css');
        if (!el) {
            el = document.createElement('link');
            el.rel = 'stylesheet';
            el.id = 'starrynight-css';
            document.head.appendChild(el);
        }
        var target = useDark ? dark : light;
        if (el.getAttribute('href') !== target) el.setAttribute('href', target);
        try {
            var starColor = useDark ? '#ffffff' : '#b86b1a';
            var starTrail = useDark ? 'rgba(255,255,255,0.9)' : 'rgba(184,107,26,0.95)';
            var starGlow = useDark ? 'rgba(255,255,255,0.1)' : 'rgba(184,107,26,0.12)';
            var meteorColor = useDark ? '#ffffff' : '#b86b1a';
            document.documentElement.style.setProperty('--sn-star-color', starColor);
            document.documentElement.style.setProperty('--sn-star-trail-color', starTrail);
            document.documentElement.style.setProperty('--sn-star-glow', starGlow);
            document.documentElement.style.setProperty('--sn-meteor-color', meteorColor);
        } catch (e) {}
        return info;
    }

    try {
        var obs = new MutationObserver(apply);
        if (document.documentElement) obs.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme'] });
        if (document.body) obs.observe(document.body, { attributes: true, attributeFilter: ['class', 'data-theme'] });
    } catch (e) {}

    window.addEventListener('storage', function (e) { if (e && e.key === 'filament_theme') apply(); });
    window.addEventListener('theme-changed', apply);
    if (window.matchMedia) {
        try { window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', apply); } catch (e) { try { window.matchMedia('(prefers-color-scheme: dark)').addListener(apply); } catch (e) {} }
    }

    try { apply(); } catch (e) {}
})();
</script>
HTML;
        });

        $panel->renderHook('panels::body.start', function () {
            $totalStars = config('starrynight.total_stars', 75);
            $flickerCount = config('starrynight.flicker_count', 15);
            $enableMeteors = config('starrynight.enable_meteors', 'true');
            $starColor = config('starrynight.star_color', '#ffffff');
            $flickerSpeed = config('starrynight.flicker_speed', '3000');
            $bgUrl = config('starrynight.background_image', '');
            $blur = config('starrynight.background_blur', '0');

            $bgCss = '';
            if (!empty($bgUrl)) {
                $bgCss = <<<CSS
                <style>
                body::before {
                    content: "";
                    position: fixed;
                    top: 0; left: 0; width: 100%; height: 100%;
                    background-image: url('{$bgUrl}');
                    background-size: cover;
                    background-position: center;
                    filter: blur({$blur}px);
                    z-index: -1;
                    pointer-events: none;
                }
                </style>
CSS;
            }

            $customSettingsCss = <<<CSS
            <style>
                :root {
                    --sn-star-color: {$starColor} !important;
                }
            </style>
            CSS;

            $meteorHtml = '';
            $meteorCss = '';
            
            if ($enableMeteors === true || $enableMeteors === 'true' || $enableMeteors === '1') {
                $meteorHtml = '<section class="starrynight-meteors">
                    <span></span><span></span><span></span><span></span><span></span>
                    <span></span><span></span><span></span><span></span><span></span>
                </section>';
                $meteorCss = <<<'CSS'
                <style>
                .starrynight-meteors { position: absolute; top: 0; left: 0; width: 100%; height: 100vh; pointer-events: none; z-index: 2; overflow: hidden; }
                .starrynight-meteors span { position: absolute; top: 50%; left: 50%; width: 4px; height: 4px; background: var(--sn-meteor-color, #fff); border-radius: 50%; box-shadow: 0 0 0 4px var(--sn-star-glow, rgba(255,255,255,0.1)),0 0 0 8px var(--sn-star-glow, rgba(255,255,255,0.1)),0 0 20px var(--sn-star-glow, rgba(255,255,255,0.1)); animation: animate 3s linear infinite; }
                .starrynight-meteors span::before { content: ""; position: absolute; top: 50%; transform: translateY(-50%); width: 300px; height: 1px; background: linear-gradient(90deg,var(--sn-star-trail-color, #fff),transparent); }
                @keyframes animate { 0% { transform: rotate(315deg) translateX(0); opacity: 1; } 70% { opacity: 1; } 100% { transform: rotate(315deg) translateX(-1000px); opacity: 0; } }
                .starrynight-meteors span:nth-child(1) { top: 0; right: 0; left: initial; animation-delay: 0s; animation-duration: 1s; }
                .starrynight-meteors span:nth-child(2) { top: 0; right: 80px; left: initial; animation-delay: 0.2s; animation-duration: 3s; }
                .starrynight-meteors span:nth-child(3) { top: 80px; right: 0px; left: initial; animation-delay: 0.4s; animation-duration: 2s; }
                .starrynight-meteors span:nth-child(4) { top: 0; right: 180px; left: initial; animation-delay: 0.6s; animation-duration: 1.5s; }
                .starrynight-meteors span:nth-child(5) { top: 0; right: 400px; left: initial; animation-delay: 0.8s; animation-duration: 2.5s; }
                .starrynight-meteors span:nth-child(6) { top: 0; right: 600px; left: initial; animation-delay: 1s; animation-duration: 3s; }
                .starrynight-meteors span:nth-child(7) { top: 300px; right: 0px; left: initial; animation-delay: 1.2s; animation-duration: 1.75s; }
                .starrynight-meteors span:nth-child(8) { top: 0px; right: 700px; left: initial; animation-delay: 1.4s; animation-duration: 1.25s; }
                .starrynight-meteors span:nth-child(9) { top: 0px; right: 1000px; left: initial; animation-delay: 0.75s; animation-duration: 2.25s; }
                .starrynight-meteors span:nth-child(10) { top: 0px; right: 450px; left: initial; animation-delay: 2.75s; animation-duration: 2.75s; }
                </style>
CSS;
            }

            $starDiv = '<div id="starrynight-stars" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 3;"></div>';
            $starStyle = <<<'CSS'
            <style>
            .static-star, .flickering-star {
                transition: opacity 1.2s ease-in-out;
                background: var(--sn-star-color, #fff);
            }
            .static-star {
                opacity: 0.5;
            }
            .flickering-star {
                opacity: 1;
                box-shadow: 0 0 6px var(--sn-star-color, #fff);
            }
            </style>
CSS;

            $starJs = <<<JS
<script>
(function () {
    const totalStars = {$totalStars};
    const flickerCount = {$flickerCount};
    const flickerSpeed = {$flickerSpeed};

    function createContainerIfMissing() {
        let container = document.getElementById('starrynight-stars');
        if (!container) {
            container = document.createElement('div');
            container.id = 'starrynight-stars';
            container.style.position = 'fixed';
            container.style.top = '0';
            container.style.left = '0';
            container.style.width = '100%';
            container.style.height = '100%';
            container.style.pointerEvents = 'none';
            container.style.zIndex = '3';
            document.body.appendChild(container);
        }
        return container;
    }

    function initStars() {
        const container = createContainerIfMissing();
        if (container.dataset.ctInitialized === '1') return;
        container.innerHTML = '';

        const stars = [];
        const starPositions = [];
        function isFarEnough(top, left, minDist) {
            for (const pos of starPositions) {
                const dx = left - pos.left;
                const dy = top - pos.top;
                if (Math.sqrt(dx*dx + dy*dy) < minDist) return false;
            }
            return true;
        }
        
        let attempts = 0;
        for (let i = 0; i < totalStars; i++) {
            let top, left, size;
            do {
                top = 5 + Math.random() * 90;
                left = 5 + Math.random() * 90;
                size = 1 + Math.random() * 2;
                attempts++;
            } while (!isFarEnough(top, left, 2.5) && attempts < 1000);
            
            starPositions.push({top, left});
            const star = document.createElement('div');
            star.style.position = 'absolute';
            star.style.top = top + '%';
            star.style.left = left + '%';
            star.style.width = size + 'px';
            star.style.height = size + 'px';
            star.className = 'static-star';
            container.appendChild(star);
            stars.push(star);
        }

        function updateFlickeringStars() {
            if (!stars.length) return;
            stars.forEach(star => {
                star.classList.remove('flickering-star');
                star.classList.add('static-star');
            });
            const flickerIndices = new Set();
            while (flickerIndices.size < flickerCount && flickerIndices.size < stars.length) {
                flickerIndices.add(Math.floor(Math.random() * stars.length));
            }
            flickerIndices.forEach(idx => {
                const star = stars[idx];
                if (!star) return;
                star.classList.remove('static-star');
                star.classList.add('flickering-star');
                star.style.transitionDuration = (flickerSpeed / 1000).toFixed(1) + 's';
            });
        }

        if (window.__starrynight_stars_interval) {
            clearInterval(window.__starrynight_stars_interval);
        }
        
        updateFlickeringStars();
        window.__starrynight_stars_interval = setInterval(updateFlickeringStars, flickerSpeed);
        container.dataset.ctInitialized = '1';
    }

    window.StarryNight = window.StarryNight || {};
    window.StarryNight.initStars = initStars;

    function runInit() {
        try { initStars(); } catch (e) { console.error('StarryNight init error', e); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runInit);
    } else {
        runInit();
    }

    window.addEventListener('turbo:load', runInit);
    window.addEventListener('turbo:render', runInit);
    window.addEventListener('pjax:end', runInit);
    window.addEventListener('popstate', runInit);
    window.addEventListener('spa:navigate', runInit);

    const observer = new MutationObserver(() => {
        runInit();
    });
    observer.observe(document.documentElement || document.body, { childList: true, subtree: true });

})();
</script>
JS;

            return $bgCss.$customSettingsCss.$meteorCss.$meteorHtml.$starDiv.$starStyle.$starJs;
        });
    }
}
