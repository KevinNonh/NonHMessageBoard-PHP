(function () {
    'use strict';

    var cards = document.querySelectorAll('[data-oc-target]');
    if (!cards.length) return;

    var active = [];
    cards.forEach(function (card) {
        if (card.dataset.ocToday === '1') return;
        active.push(card);
    });
    if (!active.length) return;

    var visible = new Set();
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) visible.add(e.target);
            else visible.delete(e.target);
        });
    }, { threshold: 0 });
    active.forEach(function (c) { observer.observe(c); });

    var lastSecond = -1;

    function pad2(n) { return n < 10 ? '0' + n : '' + n; }

    function updateCard(card, now) {
        var target = parseInt(card.dataset.ocTarget, 10);
        if (!target) return;

        var daysEl = card.querySelector('.oc-countdown-days');
        var clockEl = card.querySelector('.oc-countdown-clock');

        var diff = target - now;
        if (diff <= 0) {
            if (daysEl) daysEl.textContent = '今天';
            if (clockEl) clockEl.textContent = '🎉';
            return;
        }

        var totalSec = Math.floor(diff / 1000);
        var d = Math.floor(totalSec / 86400);
        var h = Math.floor((totalSec % 86400) / 3600);
        var m = Math.floor((totalSec % 3600) / 60);
        var s = totalSec % 60;

        if (daysEl) daysEl.textContent = d + ' 天';
        if (clockEl) clockEl.textContent = pad2(h) + ':' + pad2(m) + ':' + pad2(s);
    }

    var now0 = Date.now();
    active.forEach(function (c) { updateCard(c, now0); });

    function tick() {
        var now = Date.now();
        var sec = Math.floor(now / 1000);
        if (sec !== lastSecond) {
            lastSecond = sec;
            visible.forEach(function (card) { updateCard(card, now); });
        }
        requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
	
	(function () {
    'use strict';

    var input = document.getElementById('oc-search');
    if (!input) return;

    var clearBtn = document.getElementById('oc-search-clear');
    var countEl  = document.getElementById('oc-search-count');

    var allCards = Array.prototype.slice.call(document.querySelectorAll('[data-oc-name]'));
    if (!allCards.length) return;

    var todaySection = document.querySelector('.oc-today-section');
    var listSection  = document.querySelector('.oc-list-section');
    var listTitle    = listSection ? listSection.querySelector('.oc-section-title') : null;

    // 缓存每张卡的可搜索文本
    allCards.forEach(function (card) {
        card.dataset.ocSearch = (card.dataset.ocName || '').toLowerCase();
    });

    // 空状态元素（懒创建）
    var emptyEl = null;
    function ensureEmpty() {
        if (emptyEl) return emptyEl;
        emptyEl = document.createElement('div');
        emptyEl.className = 'oc-search-empty';
        emptyEl.textContent = '没有匹配的角色';
        var main = document.querySelector('.oc-main');
        if (main) main.appendChild(emptyEl);
        return emptyEl;
    }

    function countVisible(section) {
        if (!section) return 0;
        var n = 0;
        section.querySelectorAll('[data-oc-name]').forEach(function (c) {
            if (c.style.display !== 'none') n++;
        });
        return n;
    }

    function applyFilter() {
        var kw = input.value.trim().toLowerCase();
        var total = 0;

        allCards.forEach(function (card) {
            var hay = card.dataset.ocSearch || '';
            var show = kw === '' || hay.indexOf(kw) !== -1;
            card.style.display = show ? '' : 'none';
            if (show) total++;
        });

        clearBtn.hidden = kw === '';
        countEl.textContent = kw ? ('匹配 ' + total + ' 个') : '';

        if (kw === '') {
            // 恢复全部
            if (todaySection) todaySection.style.display = '';
            if (listSection)  listSection.style.display = '';
            if (listTitle)    listTitle.style.display = '';
            var e0 = ensureEmpty();
            e0.classList.remove('visible');
            return;
        }

        // 搜索中：按可见数量决定是否隐藏整段
        var todayVisible = countVisible(todaySection);
        var listVisible  = countVisible(listSection);

        if (todaySection) todaySection.style.display = todayVisible ? '' : 'none';
        if (listSection)  listSection.style.display  = listVisible  ? '' : 'none';
        if (listTitle)    listTitle.style.display    = listVisible  ? '' : 'none';

        var e1 = ensureEmpty();
        e1.classList.toggle('visible', todayVisible + listVisible === 0);
    }

    input.addEventListener('input', applyFilter);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            input.value = '';
            applyFilter();
            input.blur();
        }
    });

    clearBtn.addEventListener('click', function () {
        input.value = '';
        applyFilter();
        input.focus();
    });

    // 快捷键：/ 聚焦搜索
    document.addEventListener('keydown', function (e) {
        if (e.key !== '/' || e.metaKey || e.ctrlKey || e.altKey) return;
        var tag = (e.target && e.target.tagName ? e.target.tagName : '').toLowerCase();
        if (tag === 'input' || tag === 'textarea') return;
        e.preventDefault();
        input.focus();
    });

    // 初始化（可能带 query string 里的搜索词，比如从别处跳过来）
    try {
        var params = new URLSearchParams(location.search);
        var pre = params.get('q');
        if (pre) input.value = pre;
    } catch (e) {}
    applyFilter();
})();
	
})();