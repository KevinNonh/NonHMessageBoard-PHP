(function () {
    'use strict';

    var THEME_KEY = 'mb_theme';
    var POS_PREFIX = 'mb_pos_';

    // ===== 工具 =====
    function clamp(v, min, max) {
        if (max < min) return min;
        return Math.max(min, Math.min(max, v));
    }
    function readPos(id) {
        try {
            var raw = localStorage.getItem(POS_PREFIX + id);
            if (!raw) return null;
            var o = JSON.parse(raw);
            if (typeof o.x === 'number' && typeof o.y === 'number') return o;
        } catch (e) {}
        return null;
    }
    function writePos(id, pos) {
        try { localStorage.setItem(POS_PREFIX + id, JSON.stringify(pos)); } catch (e) {}
    }
    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
    function nl2br(s) {
        return escHtml(s).replace(/\n/g, '<br>');
    }
    function setPos(b, x, y) {
        b.style.left = x + 'px';
        b.style.top = y + 'px';
        b.dataset.x = x;
        b.dataset.y = y;
    }

    // ===== 主题 =====
    function initTheme() {
        var btn = document.getElementById('theme-toggle');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var cur = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
            var next = cur === 'dark' ? 'light' : 'dark';
            document.documentElement.dataset.theme = next;
            try { localStorage.setItem(THEME_KEY, next); } catch (e) {}
        });
    }

    // ===== 留言模态 =====
    function initModal() {
    var modal = document.getElementById('post-modal');
    if (!modal) return;

    function open() {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        var first = modal.querySelector('input[name="nickname"]');
        if (first) setTimeout(function () { first.focus(); }, 30);
    }
    function close() {
        modal.hidden = true;
        document.body.style.overflow = '';
    }

    // 用 document + 捕获阶段委托，避免任何冒泡拦截
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;

        if (t.closest('#open-post')) {
            e.preventDefault();
            open();
            return;
        }

        if (modal.hidden) return;

        if (t.closest('[data-close]')) {
            close();
        }
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) close();
    });

    if (modal.querySelector('.alert-error')) open();
}

    // ===== 颜文字 =====
    function initKaomoji() {
        var ta = document.querySelector('.post-form textarea[name="content"]');
        if (!ta) return;
        document.querySelectorAll('.kaomoji').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.dataset.k || '';
                var s = ta.selectionStart, e = ta.selectionEnd;
                ta.value = ta.value.slice(0, s) + text + ta.value.slice(e);
                ta.selectionStart = ta.selectionEnd = s + text.length;
                ta.focus();
            });
        });
    }

    // ===== 视图切换 =====
    function initViewToggle() {
    var btn = document.getElementById('view-toggle');
    var listView = document.getElementById('list-view');
    if (!btn || !listView) return;

    var VIEW_KEY = 'mb_view';
    var listLoaded = false;
    var listLoading = false;
    var listHasMore = true;
    var listOldestId = 0;
    var listInner = null;
    var sentinel = null;
    var observer = null;

    function setButtonState(isList) {
        btn.textContent = isList ? '🎈' : '📋';
        btn.title = isList ? '切换到气泡视图' : '切换到列表视图';
    }

    function applyView(view) {
        var isList = view === 'list';
        if (isList) {
            document.body.classList.add('view-list');
            listView.style.display = 'block';
            setButtonState(true);
            if (!listLoaded) {
                listLoaded = true;
                initList();
            }
        } else {
            document.body.classList.remove('view-list');
            listView.style.display = '';
            setButtonState(false);
            if (typeof window.mbRelayoutBubbles === 'function') {
                requestAnimationFrame(window.mbRelayoutBubbles);
            }
        }
    }

    function initList() {
        listView.innerHTML = '';
        listInner = document.createElement('div');
        listInner.className = 'list-view-inner';
        listView.appendChild(listInner);

        sentinel = document.createElement('div');
        sentinel.className = 'list-sentinel';
        sentinel.textContent = '加载中…';
        listView.appendChild(sentinel);

        observer = new IntersectionObserver(function (entries) {
            if (entries[0].isIntersecting) loadMore();
        }, { root: listView, rootMargin: '200px' });
        observer.observe(sentinel);

        loadMore();
    }

    function loadMore() {
        if (listLoading || !listHasMore) return;
        listLoading = true;
        sentinel.textContent = '加载中…';

        var url = 'index.php?list=all';
        if (listOldestId > 0) url += '&before_id=' + encodeURIComponent(listOldestId);

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                var msgs = (data && data.messages) || [];
                msgs.forEach(function (m) {
                    listInner.appendChild(createListCard(m));
                    var id = parseInt(m.id, 10);
                    if (id && (listOldestId === 0 || id < listOldestId)) listOldestId = id;
                });

                listHasMore = !!(data && data.has_more);
                listLoading = false;

                if (listHasMore) {
                    sentinel.textContent = '滚动加载更多…';
                } else {
                    sentinel.textContent = '— 没有更多留言了 —';
                    if (observer) { observer.disconnect(); observer = null; }
                }
            })
            .catch(function (err) {
                console.error('列表加载失败:', err);
                listLoading = false;
                sentinel.textContent = '加载失败，点击重试';
                sentinel.style.cursor = 'pointer';
                sentinel.onclick = function () {
                    sentinel.style.cursor = '';
                    sentinel.onclick = null;
                    loadMore();
                };
            });
    }

    function createListCard(m) {
        var card = document.createElement('article');
        card.className = 'list-card';
		card.dataset.id = String(m.id);

        var head = document.createElement('div');
        head.className = 'list-card-head';

        var nick = document.createElement('strong');
        if (m.website) {
            var a = document.createElement('a');
            a.href = m.website;
            a.target = '_blank';
            a.rel = 'noopener nofollow';
            a.textContent = m.nickname;
            nick.appendChild(a);
        } else {
            nick.textContent = m.nickname;
        }
        head.appendChild(nick);

        if (m.reply) {
            var check = document.createElement('span');
            check.className = 'list-card-check';
            check.textContent = '✓ 已回复';
            head.appendChild(check);
        }

        var time = document.createElement('span');
        time.className = 'list-card-time';
        time.textContent = m.created_at;
        head.appendChild(time);

        card.appendChild(head);

        var content = document.createElement('div');
        content.className = 'list-card-content';
        content.innerHTML = nl2br(m.content);
        card.appendChild(content);

        if (m.reply) {
            var reply = document.createElement('div');
            reply.className = 'list-card-reply';

            var label = document.createElement('div');
            label.className = 'reply-label';
            label.textContent = '管理员回复';
            reply.appendChild(label);

            var text = document.createElement('div');
            text.className = 'reply-text';
            text.innerHTML = nl2br(m.reply);
            reply.appendChild(text);

            card.appendChild(reply);
        }

        return card;
    }

    var savedView = 'bubbles';
    try {
        var v = localStorage.getItem(VIEW_KEY);
        if (v === 'list' || v === 'bubbles') savedView = v;
    } catch (e) {}
    applyView(savedView);

    btn.addEventListener('click', function () {
        var next = document.body.classList.contains('view-list') ? 'bubbles' : 'list';
        applyView(next);
        try { localStorage.setItem(VIEW_KEY, next); } catch (e) {}
    });
}

    // ===== 气泡 =====
    function initBubbles() {
        var field = document.getElementById('bubble-field');
        if (!field) return;

        var allBubbles = [];

        function boxesOverlap(a, b) {
            return !(a.x + a.w < b.x || b.x + b.w < a.x || a.y + a.h < b.y || b.y + b.h < a.y);
        }

        function findFreeSpot(rect, w, h, occupied) {
            var maxX = Math.max(10, rect.width - w - 20);
            var maxY = Math.max(10, rect.height - h - 20);
            var best = null;
            for (var i = 0; i < 40; i++) {
                var x = 10 + Math.random() * (maxX - 10);
                var y = 10 + Math.random() * (maxY - 10);
                var count = 0;
                var box = { x: x, y: y, w: w, h: h };
                for (var j = 0; j < occupied.length; j++) {
                    if (boxesOverlap(box, occupied[j])) count++;
                }
                if (count === 0) return { x: x, y: y };
                if (!best || count < best.count) {
                    best = { x: x, y: y, count: count };
                }
            }
            return best || { x: 10 + Math.random() * (maxX - 10), y: 10 + Math.random() * (maxY - 10) };
        }

        function applyLayout(bubbles, existing) {
            var rect = field.getBoundingClientRect();
			if (rect.width < 10 || rect.height < 10) return;   // 新增：容器不可见时跳过
            var occupied = (existing || []).map(function (b) {
                return {
                    x: parseFloat(b.dataset.x) || 0,
                    y: parseFloat(b.dataset.y) || 0,
                    w: b.offsetWidth,
                    h: b.offsetHeight,
                };
            });

            bubbles.forEach(function (b) {
                var id = b.dataset.id;
                var isAnnounce = b.dataset.announcement === '1';
                var w = b.offsetWidth || 180;
                var h = b.offsetHeight || 70;
                var saved = readPos(id);
                var x, y;

                if (saved) {
                    x = saved.x;
                    y = saved.y;
                } else if (isAnnounce) {
                    x = (rect.width - w) / 2;
                    y = Math.max(20, rect.height * 0.12);
                } else {
                    var spot = findFreeSpot(rect, w, h, occupied);
                    x = spot.x;
                    y = spot.y;
                    occupied.push({ x: x, y: y, w: w, h: h });
                }

                x = clamp(x, 6, Math.max(6, rect.width - w - 6));
                y = clamp(y, 6, Math.max(6, rect.height - h - 6));
                setPos(b, x, y);

                b.style.animationDelay = (-Math.random() * 8).toFixed(2) + 's';
                b.style.animationDuration = (6 + Math.random() * 4).toFixed(2) + 's';
            });
        }

        function toggleExpand(b) {
            var wasOpen = b.classList.contains('expanded');
            document.querySelectorAll('.bubble.expanded').forEach(function (o) {
                if (o !== b) o.classList.remove('expanded');
            });
            if (wasOpen) {
                b.classList.remove('expanded');
                return;
            }
            b.classList.add('expanded');

            requestAnimationFrame(function () {
                var r = field.getBoundingClientRect();
                var w = b.offsetWidth, h = b.offsetHeight;
                var x = parseFloat(b.dataset.x) || 0;
                var y = parseFloat(b.dataset.y) || 0;
                x = clamp(x, 0, Math.max(0, r.width - w));
                y = clamp(y, 0, Math.max(0, r.height - h));
                setPos(b, x, y);
                writePos(b.dataset.id, { x: x, y: y });
            });
        }

        function attachHandlers(b) {
            var pointerId = null;
            var startX = 0, startY = 0;
            var origX = 0, origY = 0;
            var cachedRect = null;
            var moved = false;

            b.addEventListener('pointerdown', function (e) {
                if (e.button !== 0) return;
                if (e.target.closest('a')) return;

                pointerId = e.pointerId;
                try { b.setPointerCapture(pointerId); } catch (err) {}

                startX = e.clientX;
                startY = e.clientY;
                origX = parseFloat(b.dataset.x) || 0;
                origY = parseFloat(b.dataset.y) || 0;
                cachedRect = field.getBoundingClientRect();
                moved = false;
                b.classList.add('dragging');
            });

            b.addEventListener('pointermove', function (e) {
                if (e.pointerId !== pointerId) return;
                var dx = e.clientX - startX;
                var dy = e.clientY - startY;
                if (!moved && Math.sqrt(dx * dx + dy * dy) > 5) moved = true;
                if (!moved) return;

                var w = b.offsetWidth;
                var h = b.offsetHeight;
                var x = clamp(origX + dx, 0, Math.max(0, cachedRect.width - w));
                var y = clamp(origY + dy, 0, Math.max(0, cachedRect.height - h));
                setPos(b, x, y);
            });

            function finish(e) {
                if (e.pointerId !== pointerId) return;
                b.classList.remove('dragging');
                try { b.releasePointerCapture(pointerId); } catch (err) {}
                pointerId = null;

                if (moved) {
                    writePos(b.dataset.id, {
                        x: parseFloat(b.dataset.x) || 0,
                        y: parseFloat(b.dataset.y) || 0,
                    });
                } else {
                    toggleExpand(b);
                }
            }
            b.addEventListener('pointerup', finish);
            b.addEventListener('pointercancel', function () {
                b.classList.remove('dragging');
                pointerId = null;
            });
        }

        function createBubble(m) {
            var div = document.createElement('div');
            div.className = 'bubble';
            div.dataset.id = String(m.id);

            var head = document.createElement('div');
            head.className = 'bubble-head';

            var nick = document.createElement('span');
            nick.className = 'bubble-nick';
            if (m.website) {
                var a = document.createElement('a');
                a.href = m.website;
                a.target = '_blank';
                a.rel = 'noopener nofollow';
                a.textContent = m.nickname;
                nick.appendChild(a);
            } else {
                nick.textContent = m.nickname;
            }
            head.appendChild(nick);

            if (m.reply) {
                var check = document.createElement('span');
                check.className = 'bubble-check';
                check.title = '管理员已回复';
                check.textContent = '✓';
                head.appendChild(check);
            }
            div.appendChild(head);

            var content = document.createElement('div');
            content.className = 'bubble-content';

            var clip = document.createElement('div');
            clip.className = 'bubble-clip';
            clip.textContent = m.content;
            content.appendChild(clip);

            var full = document.createElement('div');
            full.className = 'bubble-full';

            var fullText = document.createElement('div');
            fullText.className = 'full-text';
            fullText.innerHTML = nl2br(m.content);
            full.appendChild(fullText);

            if (m.reply) {
                var fullReply = document.createElement('div');
                fullReply.className = 'full-reply';

                var replyLabel = document.createElement('div');
                replyLabel.className = 'reply-label';
                replyLabel.textContent = '管理员回复';
                fullReply.appendChild(replyLabel);

                var replyText = document.createElement('div');
                replyText.className = 'reply-text';
                replyText.innerHTML = nl2br(m.reply);
                fullReply.appendChild(replyText);

                if (m.replied_at) {
                    var replyTime = document.createElement('div');
                    replyTime.className = 'reply-time';
                    replyTime.textContent = m.replied_at;
                    fullReply.appendChild(replyTime);
                }
                full.appendChild(fullReply);
            }

            var fullTime = document.createElement('div');
            fullTime.className = 'full-time';
            fullTime.textContent = m.created_at;
            full.appendChild(fullTime);

            content.appendChild(full);
            div.appendChild(content);

            return div;
        }

        // 初始化
        allBubbles = Array.prototype.slice.call(field.querySelectorAll('.bubble'));
        applyLayout(allBubbles, []);
        allBubbles.forEach(attachHandlers);

        field.addEventListener('click', function (e) {
            if (e.target === field) {
                allBubbles.forEach(function (b) { b.classList.remove('expanded'); });
            }
        });

        var resizeTimer = null;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                var r = field.getBoundingClientRect();
                allBubbles.forEach(function (b) {
                    var w = b.offsetWidth, h = b.offsetHeight;
                    var x = parseFloat(b.dataset.x) || 0;
                    var y = parseFloat(b.dataset.y) || 0;
                    x = clamp(x, 0, Math.max(0, r.width - w));
                    y = clamp(y, 0, Math.max(0, r.height - h));
                    setPos(b, x, y);
                    writePos(b.dataset.id, { x: x, y: y });
                });
            }, 120);
        });

        // 加载更多
        var loadMoreBtn = document.getElementById('load-more');
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function () {
                if (loadMoreBtn.disabled) return;

                var minId = Infinity;
                allBubbles.forEach(function (b) {
                    var id = parseInt(b.dataset.id, 10);
                    if (!isNaN(id) && id < minId) minId = id;
                });
                if (minId === Infinity) return;

                loadMoreBtn.disabled = true;
                var originalText = loadMoreBtn.textContent;
                loadMoreBtn.textContent = '加载中…';

                fetch('index.php?before_id=' + encodeURIComponent(minId), {
                    credentials: 'same-origin',
                })
                    .then(function (r) {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(function (data) {
                        var msgs = (data && data.messages) || [];
                        if (!msgs.length) {
                            loadMoreBtn.textContent = '没有更多留言了';
                            loadMoreBtn.disabled = true;
                            return;
                        }

                        var newBubbles = msgs.map(createBubble);
                        newBubbles.forEach(function (b) { field.appendChild(b); });

                        requestAnimationFrame(function () {
                            applyLayout(newBubbles, allBubbles);
                            newBubbles.forEach(attachHandlers);
                            allBubbles = allBubbles.concat(newBubbles);

                            if (data.has_more) {
                                loadMoreBtn.disabled = false;
                                loadMoreBtn.textContent = originalText;
                            } else {
                                loadMoreBtn.textContent = '没有更多留言了';
                                loadMoreBtn.disabled = true;
                            }
                        });
                    })
                    .catch(function (err) {
                        console.error('加载更多失败:', err);
                        loadMoreBtn.disabled = false;
                        loadMoreBtn.textContent = '加载失败，点击重试';
                    });
            });
        }
		
		// 对外暴露：重新布局所有气泡（含重叠检测）
        window.mbRelayoutBubbles = function () {
            var rect = field.getBoundingClientRect();
            if (rect.width < 10 || rect.height < 10) return;

            // 先把所有气泡夹回可视区
            allBubbles.forEach(function (b) {
                var w = b.offsetWidth, h = b.offsetHeight;
                var x = parseFloat(b.style.left) || 0;
                var y = parseFloat(b.style.top) || 0;
                x = clamp(x, 0, Math.max(0, rect.width - w));
                y = clamp(y, 0, Math.max(0, rect.height - h));
                setPos(b, x, y);
            });

            // 气泡之间的最小额外间距（视觉上不贴着就行）
            var PAD = 14;

            // 公告气泡不参与重排，只占位
            var announce = [];
            var movable = [];
            allBubbles.forEach(function (b) {
                if (b.dataset.announcement === '1') announce.push(b);
                else movable.push(b);
            });

            // placed 里存已经占位的矩形（含 PAD）
            var placed = [];

            // 1. 公告优先占位
            announce.forEach(function (b) {
                var w = b.offsetWidth, h = b.offsetHeight;
                var x = parseFloat(b.style.left) || 0;
                var y = parseFloat(b.style.top) || 0;
                placed.push({ x: x - PAD / 2, y: y - PAD / 2, w: w + PAD, h: h + PAD });
            });

            // 2. 普通气泡：ID 大的（较新）优先保留原位
            movable.sort(function (a, b) {
                return (parseInt(b.dataset.id, 10) || 0) - (parseInt(a.dataset.id, 10) || 0);
            });

            movable.forEach(function (b) {
                var w = b.offsetWidth;
                var h = b.offsetHeight;
                var x = parseFloat(b.style.left) || 0;
                var y = parseFloat(b.style.top) || 0;

                var box = { x: x - PAD / 2, y: y - PAD / 2, w: w + PAD, h: h + PAD };

                var overlapped = false;
                for (var i = 0; i < placed.length; i++) {
                    if (boxesOverlap(box, placed[i])) {
                        overlapped = true;
                        break;
                    }
                }

                if (overlapped) {
                    var spot = findFreeSpot(rect, w + PAD, h + PAD, placed);
                    var newX = clamp(spot.x + PAD / 2, 0, Math.max(0, rect.width - w));
                    var newY = clamp(spot.y + PAD / 2, 0, Math.max(0, rect.height - h));

                    setPos(b, newX, newY);
                    writePos(b.dataset.id, { x: newX, y: newY });

                    placed.push({
                        x: newX - PAD / 2,
                        y: newY - PAD / 2,
                        w: w + PAD,
                        h: h + PAD,
                    });
                } else {
                    placed.push(box);
                }
            });
        };
		
    }

// ===== 高亮刚发的留言 =====
function initHighlight() {
    var id = parseInt(document.body.dataset.highlight, 10);
    if (!id) return;

    // 清掉 URL 参数，刷新不会重复高亮
    if (window.history && window.history.replaceState) {
        try {
            var url = new URL(window.location.href);
            url.searchParams.delete('highlight');
            window.history.replaceState({}, '', url.pathname + url.search + url.hash);
        } catch (e) {}
    }

    var isList = document.body.classList.contains('view-list');

    if (isList) {
        // 列表视图：轮询等卡片出现（列表是异步加载的）
        var tries = 0;
        var timer = setInterval(function () {
            tries++;
            var card = document.querySelector('.list-card[data-id="' + id + '"]');
            if (card) {
                clearInterval(timer);
                highlightEl(card, true);
            } else if (tries > 50) {
                clearInterval(timer);
            }
        }, 100);
    } else {
        // 气泡视图：等布局稳定
        setTimeout(function () {
            var bubble = document.querySelector('.bubble[data-id="' + id + '"]');
            if (bubble) highlightEl(bubble, false);
        }, 250);
    }

    function highlightEl(el, scroll) {
        el.classList.add('highlight');
        if (scroll && el.scrollIntoView) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        setTimeout(function () {
            el.classList.remove('highlight');
        }, 3000);
    }
}

// ===== 后台留言编辑切换 =====
function initEditToggle() {
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;

        var toggle = t.closest('[data-edit-toggle]');
        if (toggle) {
            var card = toggle.closest('.msg-card');
            if (!card) return;
            var view = card.querySelector('.msg-view');
            var edit = card.querySelector('.msg-edit');
            if (view) view.hidden = true;
            if (edit) edit.hidden = false;
            var first = edit && edit.querySelector('input[name="nickname"]');
            if (first) setTimeout(function () { first.focus(); }, 30);
            return;
        }

        var cancel = t.closest('[data-edit-cancel]');
        if (cancel) {
            var card2 = cancel.closest('.msg-card');
            if (!card2) return;
            var view2 = card2.querySelector('.msg-view');
            var edit2 = card2.querySelector('.msg-edit');
            if (view2) view2.hidden = false;
            if (edit2) edit2.hidden = true;
            return;
        }
    });
}

// ===== 风格选择器 =====
function initStylePicker() {
    var picker = document.querySelector('.style-picker');
    var btn = document.getElementById('style-toggle');
    var menu = document.getElementById('style-menu');
    if (!picker || !btn || !menu) return;

    function openMenu() { menu.hidden = false; }
    function closeMenu() { menu.hidden = true; }

    function markActive(style) {
        menu.querySelectorAll('[data-style-option]').forEach(function (o) {
            o.classList.toggle('active', o.dataset.styleOption === style);
        });
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (menu.hidden) openMenu(); else closeMenu();
    });

    menu.querySelectorAll('[data-style-option]').forEach(function (opt) {
        opt.addEventListener('click', function () {
            var style = opt.dataset.styleOption || 'default';
            if (style === 'default') {
                delete document.documentElement.dataset.style;
            } else {
                document.documentElement.dataset.style = style;
            }
            try { localStorage.setItem('mb_style', style); } catch (e) {}
            markActive(style);
            closeMenu();
        });
    });

    document.addEventListener('click', function (e) {
        if (!menu.hidden && !picker.contains(e.target)) closeMenu();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !menu.hidden) closeMenu();
    });

    var current = 'default';
    try {
        var s = localStorage.getItem('mb_style');
        var allowed = ['cute', 'tech', 'green', 'star', 'snow', 'cyber', 'aurora', 'y2k'];
        if (s && allowed.indexOf(s) !== -1) current = s;
        } catch (e) {}
    markActive(current);
}

// ===== 风格背景粒子 =====
function initBgCanvas() {
    var canvas = document.getElementById('bg-canvas');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    var w = 0, h = 0;
    var mode = 'none';
    var items = [];
    var lastTime = 0;

    var MATRIX_CHARS = '01アイウエオカキクケコサシスセソタチツテトナニヌネノabcdefghijkmnpqrstuvwxyz{}[]<>=+-*/';

    function isDark() {
        return document.documentElement.dataset.theme === 'dark';
    }

    function resize() {
        var r = canvas.getBoundingClientRect();
        w = r.width;
        h = r.height;
        canvas.width = Math.floor(w * dpr);
        canvas.height = Math.floor(h * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        setupMode();
    }

    function setupMode() {
        var s = document.documentElement.dataset.style || 'default';
        items = [];
        if (s === 'cute')        { mode = 'petals'; setupPetals(); }
        else if (s === 'tech')   { mode = 'matrix'; setupMatrix(); }
        else if (s === 'green')  { mode = 'orbs';   setupOrbs(); }
        else if (s === 'star')   { mode = 'stars';  setupStars(); }
        else if (s === 'snow')   { mode = 'snow';   setupSnow(); }
        else if (s === 'cyber')  { mode = 'cyber';  setupCyber(); }
        else if (s === 'aurora') { mode = 'aurora'; setupAurora(); }
        else                     { mode = 'none'; }
    }

    /* ---------- 可爱风：飘花瓣 ---------- */
    function setupPetals() {
        var count = Math.max(15, Math.floor(w / 36));
        for (var i = 0; i < count; i++) {
            items.push({
                x: Math.random() * w,
                y: Math.random() * h,
                size: 6 + Math.random() * 8,
                vy: 18 + Math.random() * 40,
                sway: 0.6 + Math.random() * 1.0,
                phase: Math.random() * Math.PI * 2,
                rot: Math.random() * Math.PI * 2,
                vr: (Math.random() - 0.5) * 1.6,
                hue: 325 + Math.random() * 25 - 12
            });
        }
    }

    function drawPetals(dt) {
        var dark = isDark();
        items.forEach(function (p) {
            p.y += p.vy * dt;
            p.phase += dt;
            p.rot += p.vr * dt;
            p.x += Math.sin(p.phase) * p.sway * 30 * dt;
            if (p.y > h + 20) {
                p.y = -20;
                p.x = Math.random() * w;
            }

            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.rot);

            ctx.fillStyle = 'hsla(' + p.hue + ', 80%, ' +
                (dark ? '65%' : '78%') + ', ' + (dark ? '0.55' : '0.72') + ')';
            ctx.beginPath();
            ctx.ellipse(0, 0, p.size, p.size * 0.52, 0, 0, Math.PI * 2);
            ctx.fill();

            ctx.strokeStyle = 'hsla(' + p.hue + ', 70%, ' +
                (dark ? '85%' : '92%') + ', 0.35)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(-p.size * 0.9, 0);
            ctx.lineTo(p.size * 0.9, 0);
            ctx.stroke();

            ctx.restore();
        });
    }

    /* ---------- 科技风：代码雨 ---------- */
    function setupMatrix() {
        var colWidth = 18;
        var cols = Math.ceil(w / colWidth);
        for (var i = 0; i < cols; i++) {
            items.push({
                x: i * colWidth + colWidth / 2,
                y: -Math.random() * h,
                speed: 40 + Math.random() * 140,
                size: 14,
                maxLen: 6 + Math.floor(Math.random() * 12),
                chars: []
            });
        }
    }

    var matrixTimer = 0;
    function drawMatrix(dt) {
        matrixTimer += dt;
        var refresh = matrixTimer > 0.09;
        if (refresh) matrixTimer = 0;

        var dark = isDark();
        ctx.font = '13px ui-monospace, Menlo, Consolas, monospace';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        items.forEach(function (col) {
            col.y += col.speed * dt;
            if (col.y - col.maxLen * col.size > h) {
                col.y = -Math.random() * 40;
                col.speed = 40 + Math.random() * 140;
                col.maxLen = 6 + Math.floor(Math.random() * 12);
                col.chars = [];
            }

            while (col.chars.length < col.maxLen) {
                col.chars.push(MATRIX_CHARS[Math.floor(Math.random() * MATRIX_CHARS.length)]);
            }
            if (refresh && Math.random() < 0.25) {
                var idx = Math.floor(Math.random() * col.chars.length);
                col.chars[idx] = MATRIX_CHARS[Math.floor(Math.random() * MATRIX_CHARS.length)];
            }

            for (var i = 0; i < col.chars.length; i++) {
                var y = col.y - i * col.size;
                if (y < -20 || y > h + 20) continue;

                if (i === 0) {
                    ctx.globalAlpha = 1;
                    ctx.fillStyle = dark ? '#e8ffff' : '#003d5c';
                    ctx.shadowColor = dark ? '#7dfcff' : '#0090d0';
                    ctx.shadowBlur = 10;
                } else {
                    ctx.globalAlpha = Math.max(0.05, 1 - i / col.chars.length);
                    ctx.fillStyle = dark ? '#00d4ff' : '#0078b8';
                    ctx.shadowBlur = 0;
                }
                ctx.fillText(col.chars[i], col.x, y);
            }
            ctx.globalAlpha = 1;
            ctx.shadowBlur = 0;
        });
    }

    /* ---------- 护眼绿：呼吸渐变光斑 ---------- */
    function setupOrbs() {
        for (var i = 0; i < 5; i++) {
            items.push({
                x: Math.random() * w,
                y: Math.random() * h,
                r: 130 + Math.random() * 150,
                vx: (Math.random() - 0.5) * 18,
                vy: (Math.random() - 0.5) * 18,
                phase: Math.random() * Math.PI * 2,
                pulseSpeed: 0.25 + Math.random() * 0.4,
                hue: 100 + Math.random() * 40
            });
        }
    }

    function drawOrbs(dt) {
        var dark = isDark();
        items.forEach(function (o) {
            o.x += o.vx * dt;
            o.y += o.vy * dt;
            o.phase += o.pulseSpeed * dt;

            if (o.x < -o.r) o.x = w + o.r;
            if (o.x > w + o.r) o.x = -o.r;
            if (o.y < -o.r) o.y = h + o.r;
            if (o.y > h + o.r) o.y = -o.r;

            var pulse = 0.5 + 0.5 * Math.sin(o.phase);
            var r = o.r * (0.85 + pulse * 0.3);
            var alpha = (dark ? 0.12 : 0.16) + pulse * 0.14;

            var grd = ctx.createRadialGradient(o.x, o.y, 0, o.x, o.y, r);
            var col = 'hsla(' + o.hue + ', 60%, ' + (dark ? '45%' : '60%') + ', ';
            grd.addColorStop(0, col + alpha + ')');
            grd.addColorStop(1, col + '0)');
            ctx.fillStyle = grd;
            ctx.beginPath();
            ctx.arc(o.x, o.y, r, 0, Math.PI * 2);
            ctx.fill();
        });
    }

    /* ---------- 星空风：星星闪烁 + 流星 ---------- */
    function setupStars() {
        var starCount = Math.max(80, Math.floor((w * h) / 8000));
        for (var i = 0; i < starCount; i++) {
            items.push({
                x: Math.random() * w,
                y: Math.random() * h,
                r: 0.4 + Math.random() * 1.4,
                phase: Math.random() * Math.PI * 2,
                speed: 0.6 + Math.random() * 1.6
            });
        }
        items.meteor = null;
        items.meteorTimer = 3 + Math.random() * 5;
        items.nextMeteorIn = 5 + Math.random() * 8;
    }

    function drawStars(dt) {
        var dark = isDark();
        var twinkleColor = dark ? '255, 255, 255' : '60, 40, 100';

        items.forEach(function (s) {
            s.phase += s.speed * dt;
            var alpha = 0.4 + 0.6 * (0.5 + 0.5 * Math.sin(s.phase));
            ctx.fillStyle = 'rgba(' + twinkleColor + ',' + alpha + ')';
            ctx.beginPath();
            ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
            ctx.fill();
        });

        // 流星
        items.nextMeteorIn -= dt;
        if (!items.meteor && items.nextMeteorIn <= 0) {
            var startX = Math.random() * w * 0.6;
            var startY = Math.random() * h * 0.4;
            items.meteor = {
                x: startX, y: startY,
                vx: 500 + Math.random() * 300,
                vy: 200 + Math.random() * 150,
                life: 0.9,
                max: 0.9
            };
        }
        if (items.meteor) {
            var m = items.meteor;
            m.x += m.vx * dt;
            m.y += m.vy * dt;
            m.life -= dt;
            var a = Math.max(0, m.life / m.max);
            var tailLen = 120;
            var grad = ctx.createLinearGradient(
                m.x, m.y,
                m.x - m.vx * tailLen / 500, m.y - m.vy * tailLen / 500
            );
            var tone = dark ? '255, 255, 255' : '120, 80, 200';
            grad.addColorStop(0, 'rgba(' + tone + ',' + a + ')');
            grad.addColorStop(1, 'rgba(' + tone + ',0)');
            ctx.strokeStyle = grad;
            ctx.lineWidth = 1.8;
            ctx.beginPath();
            ctx.moveTo(m.x, m.y);
            ctx.lineTo(m.x - m.vx * tailLen / 500, m.y - m.vy * tailLen / 500);
            ctx.stroke();
            if (m.life <= 0) {
                items.meteor = null;
                items.nextMeteorIn = 6 + Math.random() * 10;
            }
        }
    }

    /* ---------- 雪夜风：雪花 ---------- */
    function setupSnow() {
        var count = Math.max(30, Math.floor(w / 22));
        for (var i = 0; i < count; i++) {
            items.push({
                x: Math.random() * w,
                y: Math.random() * h,
                r: 1 + Math.random() * 3,
                vy: 20 + Math.random() * 50,
                phase: Math.random() * Math.PI * 2,
                sway: 0.4 + Math.random() * 0.8,
                rot: Math.random() * Math.PI * 2,
                vr: (Math.random() - 0.5) * 0.8
            });
        }
    }

    function drawSnow(dt) {
        var dark = isDark();
        items.forEach(function (p) {
            p.y += p.vy * dt;
            p.phase += dt;
            p.rot += p.vr * dt;
            p.x += Math.sin(p.phase) * p.sway * 26 * dt;
            if (p.y > h + 10) {
                p.y = -10;
                p.x = Math.random() * w;
            }
            if (p.x < -10) p.x = w + 10;
            if (p.x > w + 10) p.x = -10;

            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.rot);
            ctx.fillStyle = dark
                ? 'rgba(220, 235, 255, 0.85)'
                : 'rgba(255, 255, 255, 0.98)';
            ctx.beginPath();
            ctx.arc(0, 0, p.r, 0, Math.PI * 2);
            ctx.fill();

            // 大雪花加个光晕
            if (p.r > 2.4) {
                ctx.strokeStyle = dark
                    ? 'rgba(180, 220, 255, 0.35)'
                    : 'rgba(255, 255, 255, 0.75)';
                ctx.lineWidth = 1;
                for (var i = 0; i < 3; i++) {
                    var ang = (i * Math.PI * 2) / 3;
                    ctx.beginPath();
                    ctx.moveTo(Math.cos(ang) * p.r * 0.5, Math.sin(ang) * p.r * 0.5);
                    ctx.lineTo(Math.cos(ang) * p.r * 2.4, Math.sin(ang) * p.r * 2.4);
                    ctx.stroke();
                }
            }
            ctx.restore();
        });
    }

    /* ---------- 赛博朋克风：霓虹光条雨 ---------- */
    function setupCyber() {
        var colWidth = 26;
        var cols = Math.ceil(w / colWidth);
        var palette = ['#ff2bd6', '#7d5cff', '#00e5ff'];
        for (var i = 0; i < cols; i++) {
            items.push({
                x: i * colWidth + colWidth / 2,
                y: -Math.random() * h,
                len: 40 + Math.random() * 120,
                speed: 220 + Math.random() * 400,
                color: palette[Math.floor(Math.random() * palette.length)],
                thickness: 1.5 + Math.random() * 1.5
            });
        }
        items.flashTimer = 4 + Math.random() * 6;
        items.flash = 0;
    }

    function drawCyber(dt) {
        var dark = isDark();
        items.forEach(function (bar) {
            bar.y += bar.speed * dt;
            if (bar.y - bar.len > h) {
                bar.y = -Math.random() * 60;
                bar.speed = 220 + Math.random() * 400;
                bar.len = 40 + Math.random() * 120;
            }
            var grad = ctx.createLinearGradient(bar.x, bar.y - bar.len, bar.x, bar.y);
            var baseAlpha = dark ? 0.85 : 0.55;
            grad.addColorStop(0, 'rgba(0, 0, 0, 0)');
            grad.addColorStop(1, hexToRgba(bar.color, baseAlpha));
            ctx.strokeStyle = grad;
            ctx.lineWidth = bar.thickness;
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.moveTo(bar.x, bar.y - bar.len);
            ctx.lineTo(bar.x, bar.y);
            ctx.stroke();

            // 头部一点亮
            ctx.fillStyle = hexToRgba(bar.color, dark ? 0.95 : 0.7);
            ctx.beginPath();
            ctx.arc(bar.x, bar.y, bar.thickness * 0.9, 0, Math.PI * 2);
            ctx.fill();
        });

        // 偶尔全屏闪一下
        items.flashTimer -= dt;
        if (items.flashTimer <= 0) {
            items.flash = 0.08;
            items.flashTimer = 5 + Math.random() * 8;
        }
        if (items.flash > 0) {
            items.flash -= dt;
            var fa = Math.max(0, items.flash / 0.08) * (dark ? 0.06 : 0.04);
            ctx.fillStyle = 'rgba(255, 43, 214, ' + fa + ')';
            ctx.fillRect(0, 0, w, h);
        }
    }

    function hexToRgba(hex, alpha) {
        var h = hex.replace('#', '');
        var r = parseInt(h.substr(0, 2), 16);
        var g = parseInt(h.substr(2, 2), 16);
        var b = parseInt(h.substr(4, 2), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }

    /* ---------- 极光风：流动的曲线光带 ---------- */
    function setupAurora() {
        items.bands = [
            { hue1: 150, hue2: 200, phase: 0,        amp: 60, speed: 0.12, yRatio: 0.35, thick: 140, alpha: 0.25 },
            { hue1: 190, hue2: 260, phase: Math.PI,  amp: 80, speed: 0.09, yRatio: 0.55, thick: 160, alpha: 0.22 },
            { hue1: 270, hue2: 320, phase: 1.8,      amp: 70, speed: 0.14, yRatio: 0.7,  thick: 120, alpha: 0.2 }
        ];
        items.time = 0;
    }

    function drawAurora(dt) {
        items.time += dt;
        var dark = isDark();
        var baseAlpha = dark ? 1 : 0.6;

        items.bands.forEach(function (band) {
            band.phase += band.speed * dt;
            var baseY = h * band.yRatio;

            var grad = ctx.createLinearGradient(0, 0, w, 0);
            grad.addColorStop(0,   'hsla(' + band.hue1 + ', 80%, 60%, 0)');
            grad.addColorStop(0.3, 'hsla(' + band.hue1 + ', 80%, 60%, ' + (band.alpha * baseAlpha) + ')');
            grad.addColorStop(0.6, 'hsla(' + band.hue2 + ', 85%, 65%, ' + (band.alpha * baseAlpha) + ')');
            grad.addColorStop(1,   'hsla(' + band.hue2 + ', 80%, 60%, 0)');

            ctx.fillStyle = grad;
            ctx.beginPath();
            ctx.moveTo(0, baseY - band.thick / 2);

            var step = 24;
            for (var x = 0; x <= w; x += step) {
                var y = baseY
                    + Math.sin(x * 0.005 + band.phase) * band.amp
                    + Math.sin(x * 0.013 + band.phase * 1.7) * band.amp * 0.4;
                ctx.lineTo(x, y - band.thick / 2);
            }
            for (var x2 = w; x2 >= 0; x2 -= step) {
                var y2 = baseY
                    + Math.sin(x2 * 0.005 + band.phase) * band.amp
                    + Math.sin(x2 * 0.013 + band.phase * 1.7) * band.amp * 0.4;
                ctx.lineTo(x2, y2 + band.thick / 2);
            }
            ctx.closePath();
            ctx.fill();
        });
    }

    /* ---------- 主循环 ---------- */
    function loop(time) {
        var dt = Math.min((time - lastTime) / 1000, 0.05);
        lastTime = time;

        ctx.clearRect(0, 0, w, h);

        if (mode === 'petals')       drawPetals(dt);
        else if (mode === 'matrix')  drawMatrix(dt);
        else if (mode === 'orbs')    drawOrbs(dt);
        else if (mode === 'stars')   drawStars(dt);
        else if (mode === 'snow')    drawSnow(dt);
        else if (mode === 'cyber')   drawCyber(dt);
        else if (mode === 'aurora')  drawAurora(dt);

        requestAnimationFrame(loop);
    }

    // 风格 / 主题变化时重新初始化
    var observer = new MutationObserver(function (muts) {
        muts.forEach(function (m) {
            if (m.attributeName === 'data-style' || m.attributeName === 'data-theme') {
                setupMode();
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });

    window.addEventListener('resize', resize);
    resize();
    lastTime = performance.now();
    requestAnimationFrame(loop);
}

    document.addEventListener('DOMContentLoaded', function () {
        initTheme();
        initModal();
        initKaomoji();
        initViewToggle();
        initBubbles();
		initHighlight();
		initEditToggle();
		initStylePicker();
		initBgCanvas();
    });
})();