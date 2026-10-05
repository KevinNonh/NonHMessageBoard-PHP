(function () {
    'use strict';

    // ============================================================
    // 全局状态
    // ============================================================
    var state = {
        characters: [],
        teams: [],
        types: [],
        relations: [],
        charMap: {},
        teamMap: {},
        typeMap: {},
        positions: {},
        view: { x: 0, y: 0, scale: 0.7 },
        filter: { teams: new Set(), types: new Set() },
        search: '',
        searchMatches: [],
        searchIndex: -1,
        searchFocus: null,
        viewTarget: null,
        hovered: null,
        selected: null,
        draggingNode: null,
        draggingCanvas: false,
        dragStart: null,
        draggingOffset: null,
        dpr: Math.min(window.devicePixelRatio || 1, 2),
        avatarCache: {},
        selectionMode: false,
        selectedNodes: new Set(),
        selecting: false,
        selectStart: null,
        selectEnd: null,
        draggingGroup: null,
        lastWasDrag: false,
        pointerDownPos: null
    };

    var canvas = document.getElementById('rel-canvas');
    var ctx = canvas.getContext('2d');
    var wrap = document.getElementById('rel-canvas-wrap');
    var loading = document.getElementById('rel-loading');
    var tooltip = null;
    var W = 0, H = 0;
    var simAlpha = 0;   // 手动模式：默认关闭力导向

    // ============================================================
    // 加载数据
    // ============================================================
    function load() {
        var token = window.REL_API_TOKEN || '';
        var url = 'api.php' + (token ? '?token=' + encodeURIComponent(token) : '');

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                state.characters = data.characters || [];
                state.teams = data.teams || [];
                state.types = data.types || [];
                state.relations = data.relations || [];

                state.characters.forEach(function (c) { state.charMap[c.id] = c; });
                state.teams.forEach(function (t) { state.teamMap[t.id] = t; });
                state.types.forEach(function (t) { state.typeMap[t.id] = t; });

                try { initPositions(); } catch (e) { console.error('initPositions:', e); }
                try { initFilters(); } catch (e) { console.error('initFilters:', e); }
                try { bindInteraction(); } catch (e) { console.error('bindInteraction:', e); }
                try { preloadAvatars(); } catch (e) { console.error('preloadAvatars:', e); }
                try { maybeShowShortcutHelp(); } catch (e) { console.error('shortcut:', e); }
                hideLoading();
                loop();
            })
            .catch(function (err) {
                console.error(err);
                loading.textContent = '加载失败：' + err.message;
            });
    }

    function hideLoading() {
        loading.classList.add('hide');
        setTimeout(function () { loading.style.display = 'none'; }, 350);
    }

    // ============================================================
    // 初始布局（手动模式：只给新角色安排位置）
    // ============================================================
    function initPositions() {
        var chars = state.characters;

        // 已有位置的恢复
        chars.forEach(function (c) {
            if (c.x !== null && c.x !== undefined && c.y !== null && c.y !== undefined) {
                state.positions[c.id] = { x: c.x, y: c.y, vx: 0, vy: 0 };
            }
        });

        // 新角色：螺旋放在中心附近
        var newChars = chars.filter(function (c) { return !state.positions[c.id]; });
        newChars.forEach(function (c, idx) {
            var angle = idx * 2.4;
            var radius = 150 + idx * 60;
            state.positions[c.id] = {
                x: Math.cos(angle) * radius,
                y: Math.sin(angle) * radius,
                vx: 0,
                vy: 0
            };
        });

        simAlpha = 0;
    }

    // ============================================================
    // 力导向（保留接口，默认不跑）
    // ============================================================
    function simulate() {
        if (simAlpha < 0.02) return;

        var chars = state.characters;
        var rels = state.relations;
        var pos = state.positions;

        var REPULSION = 8000;
        var SPRING_LEN = 110;
        var SPRING_K = 0.012;
        var DAMPING = 0.85;

        for (var i = 0; i < chars.length; i++) {
            var a = pos[chars[i].id];
            if (!a) continue;
            for (var j = i + 1; j < chars.length; j++) {
                var b = pos[chars[j].id];
                if (!b) continue;
                var dx = b.x - a.x;
                var dy = b.y - a.y;
                var d2 = dx * dx + dy * dy + 0.01;
                var d = Math.sqrt(d2);
                if (d > 800) continue;
                var f = REPULSION / d2;
                var fx = (dx / d) * f;
                var fy = (dy / d) * f;
                a.vx -= fx;
                a.vy -= fy;
                b.vx += fx;
                b.vy += fy;
            }
        }

        rels.forEach(function (r) {
            var a = pos[r.from];
            var b = pos[r.to];
            if (!a || !b) return;
            var dx = b.x - a.x;
            var dy = b.y - a.y;
            var d = Math.sqrt(dx * dx + dy * dy) + 0.01;
            var f = (d - SPRING_LEN) * SPRING_K;
            var fx = (dx / d) * f;
            var fy = (dy / d) * f;
            a.vx += fx;
            a.vy += fy;
            b.vx -= fx;
            b.vy -= fy;
        });

        chars.forEach(function (c) {
            var p = pos[c.id];
            if (!p) return;
            p.vx += -p.x * 0.0015;
            p.vy += -p.y * 0.0015;
            p.vx *= DAMPING;
            p.vy *= DAMPING;
            p.x += p.vx * simAlpha;
            p.y += p.vy * simAlpha;
        });

        simAlpha *= 0.985;
    }

    // ============================================================
    // 渲染循环
    // ============================================================
    function loop() {
        if (state.viewTarget) {
            var tx = state.viewTarget.x - state.view.x;
            var ty = state.viewTarget.y - state.view.y;
            if (Math.abs(tx) < 0.5 && Math.abs(ty) < 0.5) {
                state.view.x = state.viewTarget.x;
                state.view.y = state.viewTarget.y;
                state.viewTarget = null;
            } else {
                state.view.x += tx * 0.16;
                state.view.y += ty * 0.16;
            }
        }

        simulate();
        draw();
        requestAnimationFrame(loop);
    }

    function resizeCanvas() {
        var r = wrap.getBoundingClientRect();
        W = r.width;
        H = r.height;
        canvas.width = W * state.dpr;
        canvas.height = H * state.dpr;
        ctx.setTransform(state.dpr, 0, 0, state.dpr, 0, 0);
    }

    // ============================================================
    // 绘制主流程
    // uiscale：导出时按比例放大"屏幕像素"类元素（屏幕上为 1）
    // ============================================================
    function draw(opts) {
        opts = opts || {};
        var uiscale = opts.uiscale || 1;

        if (!opts.skipClear) {
            ctx.clearRect(0, 0, W, H);
        }

        var cx = W / 2 + state.view.x;
        var cy = H / 2 + state.view.y;
        var s = state.view.scale;

        ctx.save();
        ctx.translate(cx, cy);
        ctx.scale(s, s);

        var visible = [];
        var visibleMap = {};
        state.characters.forEach(function (c) {
            if (!matchesFilter(c)) return;
            visible.push(c);
            visibleMap[c.id] = true;
        });

        var pendingLabels = [];

        state.relations.forEach(function (r) {
            if (!visibleMap[r.from] || !visibleMap[r.to]) return;
            if (!matchesRelationFilter(r)) return;

            var a = state.positions[r.from];
            var b = state.positions[r.to];
            if (!a || !b) return;
            var type = state.typeMap[r.type];

            var dim = false;
            if (state.search || state.selected) {
                dim = !isRelationHighlighted(r);
            }

            ctx.strokeStyle = dim
                ? 'rgba(120, 120, 140, 0.08)'
                : 'rgba(120, 130, 160, 0.55)';
            ctx.lineWidth = (dim ? 1 : 1.4) * uiscale / s;
            ctx.beginPath();
            ctx.moveTo(a.x, a.y);
            ctx.lineTo(b.x, b.y);
            ctx.stroke();

            if (type && type.directed && !dim) {
                drawArrow(a, b, s, uiscale);
            }

            if (type && !dim && s > 0.9) {
                var dx = b.x - a.x;
                var dy = b.y - a.y;
                var len = Math.sqrt(dx * dx + dy * dy) || 1;
                var nx = -dy / len;
                var ny = dx / len;
                var offset = 14 / s;
                pendingLabels.push({
                    text: type.name,
                    x: (a.x + b.x) / 2 + nx * offset,
                    y: (a.y + b.y) / 2 + ny * offset
                });
            }
        });

        visible.forEach(function (c) {
            drawNode(c, s, uiscale);
        });

        // 关系标签画在最上层
        ctx.font = (14 * uiscale / s) + 'px -apple-system, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.lineWidth = 3 * uiscale / s;
        ctx.lineJoin = 'round';
        ctx.strokeStyle = isDark() ? 'rgba(20, 20, 30, 0.85)' : 'rgba(255, 255, 255, 0.9)';
        ctx.fillStyle = isDark()
            ? 'rgba(200, 210, 240, 0.95)'
            : 'rgba(100, 110, 140, 0.95)';

        pendingLabels.forEach(function (l) {
            ctx.strokeText(l.text, l.x, l.y);
            ctx.fillText(l.text, l.x, l.y);
        });

        ctx.restore();

        // 框选矩形（屏幕坐标）
        if (state.selecting && state.selectStart && state.selectEnd) {
            ctx.save();
            var rx = Math.min(state.selectStart.x, state.selectEnd.x);
            var ry = Math.min(state.selectStart.y, state.selectEnd.y);
            var rw = Math.abs(state.selectEnd.x - state.selectStart.x);
            var rh = Math.abs(state.selectEnd.y - state.selectStart.y);

            ctx.fillStyle = 'rgba(74, 108, 247, 0.12)';
            ctx.fillRect(rx, ry, rw, rh);
            ctx.strokeStyle = 'rgba(74, 108, 247, 0.8)';
            ctx.lineWidth = 1;
            ctx.setLineDash([5, 3]);
            ctx.strokeRect(rx, ry, rw, rh);
            ctx.setLineDash([]);
            ctx.restore();
        }
    }

    function drawArrow(a, b, s, uiscale) {
        uiscale = uiscale || 1;
        var dx = b.x - a.x;
        var dy = b.y - a.y;
        var d = Math.sqrt(dx * dx + dy * dy) + 0.01;
        var ux = dx / d;
        var uy = dy / d;
        var angle = Math.atan2(dy, dx);

        var padding = 28;
        var spacing = 14;
        var usable = d - padding * 2;
        if (usable < 40) return;

        var t = Date.now() / 1000;
        var speed = 30;
        var offset = (t * speed) % spacing;

        ctx.save();
        ctx.translate(a.x + ux * padding, a.y + uy * padding);
        ctx.rotate(angle);

        ctx.font = 'bold ' + (13 * uiscale / s) + 'px ui-monospace, Menlo, Consolas, monospace';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = 'rgba(120, 130, 160, 0.4)';

        for (var pos = offset; pos <= usable; pos += spacing) {
            ctx.fillText('>', pos, 0);
        }

        ctx.restore();
    }

    function drawNode(c, s, uiscale) {
        uiscale = uiscale || 1;
        if (!c || !c.id) return;
        var p = state.positions[c.id];
        if (!p || typeof p.x !== 'number' || typeof p.y !== 'number') return;

        var color = c.color || pickColorForId(c.id);
        var radius = 22;

        var dim = false;
        if (state.search || state.selected) {
            if (state.search && !matchSearch(c)) dim = true;
            if (state.selected && c.id !== state.selected && !isNeighbor(c.id, state.selected)) {
                dim = true;
            }
        }
        if (state.hovered === c.id) dim = false;

        if (c.teams && c.teams.length) {
            var ringColor = state.teamMap[c.teams[0]] ? state.teamMap[c.teams[0]].color : '';
            if (ringColor) {
                ctx.beginPath();
                ctx.arc(p.x, p.y, radius + 5, 0, Math.PI * 2);
                ctx.strokeStyle = dim ? 'rgba(120,120,140,.1)' : hexToRgba(ringColor, 0.7);
                ctx.lineWidth = 2.5 * uiscale / s;
                ctx.stroke();
            }
        }

        ctx.beginPath();
        ctx.arc(p.x, p.y, radius, 0, Math.PI * 2);
        ctx.fillStyle = dim ? hexToRgba(color, 0.2) : color;
        ctx.fill();

        ctx.lineWidth = 2 * uiscale / s;
        ctx.strokeStyle = state.selected === c.id
            ? '#fff'
            : (dim ? 'rgba(255,255,255,.2)' : 'rgba(255,255,255,.85)');
        ctx.stroke();

        if (c.avatar && state.avatarCache[c.avatar]) {
            ctx.save();
            ctx.beginPath();
            ctx.arc(p.x, p.y, radius - 1.5, 0, Math.PI * 2);
            ctx.clip();
            ctx.globalAlpha = dim ? 0.25 : 1;
            ctx.drawImage(
                state.avatarCache[c.avatar],
                p.x - radius + 1.5, p.y - radius + 1.5,
                (radius - 1.5) * 2, (radius - 1.5) * 2
            );
            ctx.restore();
        } else {
            ctx.fillStyle = '#fff';
            ctx.font = 'bold ' + (17 * uiscale / s) + 'px -apple-system, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.globalAlpha = dim ? 0.4 : 1;
            ctx.fillText(c.name.substring(0, 1), p.x, p.y + 0.5);
            ctx.globalAlpha = 1;
        }

        // 多选高亮环
        if (state.selectedNodes && state.selectedNodes.has(c.id)) {
            ctx.beginPath();
            ctx.arc(p.x, p.y, radius + 8, 0, Math.PI * 2);
            ctx.strokeStyle = '#4a6cf7';
            ctx.lineWidth = 3 * uiscale / s;
            ctx.stroke();
        }

        // 搜索脉冲高亮环
        if (state.searchFocus === c.id) {
            var t = Date.now() / 1000;
            var pulse = 0.5 + 0.5 * Math.sin(t * 3);
            var ringR = radius + 8 + pulse * 6;
            var accent = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#4a6cf7';
            ctx.beginPath();
            ctx.arc(p.x, p.y, ringR, 0, Math.PI * 2);
            ctx.strokeStyle = accent;
            ctx.lineWidth = 3 * uiscale / s;
            ctx.globalAlpha = 0.9 - pulse * 0.4;
            ctx.stroke();
            ctx.globalAlpha = 1;
        }

        if (s > 0.85 || state.hovered === c.id || state.selected === c.id) {
            // 名字用世界单位（跟节点圆同体系），导出整图时按比例放大
            ctx.font = (12 * uiscale / s) + 'px -apple-system, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'top';

            var nameX = p.x;
            var nameY = p.y + radius + 4;

            ctx.lineWidth = 3 * uiscale / s;
            ctx.lineJoin = 'round';
            ctx.strokeStyle = isDark()
                ? (dim ? 'rgba(20, 20, 30, 0.3)' : 'rgba(20, 20, 30, 0.85)')
                : (dim ? 'rgba(255, 255, 255, 0.3)' : 'rgba(255, 255, 255, 0.9)');
            ctx.strokeText(c.name, nameX, nameY);

            ctx.fillStyle = isDark()
                ? (dim ? 'rgba(180,180,200,.2)' : 'rgba(230, 235, 250, .95)')
                : (dim ? 'rgba(120,120,140,.3)' : 'rgba(50, 60, 80, .95)');
            ctx.fillText(c.name, nameX, nameY);
        }
    }

    function pickColorForId(id) {
        var palette = ['#ff7aab', '#6aa9ff', '#7bd48d', '#ffb066', '#b388ff', '#66d9d9', '#ff8a80', '#c9a86a'];
        return palette[id % palette.length];
    }

    function isDark() {
        return document.documentElement.dataset.theme === 'dark';
    }

    function hexToRgba(hex, a) {
        if (!hex) return 'rgba(120,120,140,' + a + ')';
        hex = hex.replace('#', '');
        if (hex.length === 3) hex = hex.split('').map(function (c) { return c + c; }).join('');
        var r = parseInt(hex.substr(0, 2), 16);
        var g = parseInt(hex.substr(2, 2), 16);
        var b = parseInt(hex.substr(4, 2), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
    }

    function preloadAvatars() {
        state.characters.forEach(function (c) {
            if (!c.avatar || state.avatarCache[c.avatar]) return;
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () { state.avatarCache[c.avatar] = img; };
            img.onerror = function () {};
            img.src = 'proxy.php?url=' + encodeURIComponent(c.avatar);
        });
    }

    // ============================================================
    // 筛选与搜索
    // ============================================================
    function matchesFilter(c) {
        if (state.filter.teams.size > 0) {
            var hasNone = state.filter.teams.has('__none');
            var hasTeam = false;
            state.filter.teams.forEach(function (v) {
                if (v !== '__none') hasTeam = true;
            });

            var cTeams = c.teams || [];
            var ok = false;

            if (hasTeam) {
                for (var i = 0; i < cTeams.length; i++) {
                    if (state.filter.teams.has(cTeams[i])) { ok = true; break; }
                }
            }
            if (!ok && hasNone && cTeams.length === 0) ok = true;
            if (!ok) return false;
        }
        if (state.filter.types.size > 0) {
            var hasType = state.relations.some(function (r) {
                return (r.from === c.id || r.to === c.id) && state.filter.types.has(r.type);
            });
            if (!hasType) return false;
        }
        return true;
    }

    function matchesRelationFilter(r) {
        if (state.filter.teams.size > 0) {
            var ca = state.charMap[r.from], cb = state.charMap[r.to];
            if (!ca || !cb) return false;
            function teamMatch(c) {
                var cTeams = c.teams || [];
                if (cTeams.length === 0) return state.filter.teams.has('__none');
                for (var i = 0; i < cTeams.length; i++) {
                    if (state.filter.teams.has(cTeams[i])) return true;
                }
                return false;
            }
            if (!teamMatch(ca) && !teamMatch(cb)) return false;
        }
        if (state.filter.types.size > 0 && !state.filter.types.has(r.type)) return false;
        return true;
    }

    function matchSearch(c) {
        if (!state.search) return true;
        return c.name.toLowerCase().indexOf(state.search.toLowerCase()) !== -1;
    }

    function isNeighbor(a, b) {
        return state.relations.some(function (r) {
            return (r.from === a && r.to === b) || (r.from === b && r.to === a);
        });
    }

    function isRelationHighlighted(r) {
        if (state.search) {
            var ca = state.charMap[r.from], cb = state.charMap[r.to];
            return ca && cb && (matchSearch(ca) || matchSearch(cb));
        }
        if (state.selected) {
            return r.from === state.selected || r.to === state.selected;
        }
        return true;
    }

    // ============================================================
    // 坐标与命中
    // ============================================================
    function screenToWorld(sx, sy) {
        return {
            x: (sx - W / 2 - state.view.x) / state.view.scale,
            y: (sy - H / 2 - state.view.y) / state.view.scale
        };
    }

    function hitTest(wx, wy) {
        var best = null;
        var isTouch = window.matchMedia('(pointer: coarse)').matches;
        var bestD = isTouch ? 42 : 26;
        state.characters.forEach(function (c) {
            if (!matchesFilter(c)) return;
            var p = state.positions[c.id];
            if (!p) return;
            var d = Math.hypot(p.x - wx, p.y - wy);
            if (d < bestD) {
                bestD = d;
                best = c.id;
            }
        });
        return best;
    }

    // ============================================================
    // 位置保存
    // ============================================================
    var saveTimer = null;

    function scheduleSavePositions() {
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            saveTimer = null;
            doSavePositions();
        }, 1000);
    }

    function doSavePositions() {
        var list = [];
        state.characters.forEach(function (c) {
            var p = state.positions[c.id];
            if (!p) return;
            list.push({ id: c.id, x: Math.round(p.x * 100) / 100, y: Math.round(p.y * 100) / 100 });
        });

        var token = window.REL_API_TOKEN || '';
        var body = new URLSearchParams();
        body.append('action', 'save_positions');
        body.append('positions', JSON.stringify(list));
        if (token) body.append('token', token);

        fetch('api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    console.warn('[relations] 位置保存失败', data);
                }
            })
            .catch(function (err) {
                console.warn('[relations] 位置保存失败：', err);
            });
    }

    // ============================================================
    // 交互
    // ============================================================
    function bindInteraction() {
        var sidebar = document.getElementById('rel-sidebar');
        var sidebarToggle = document.getElementById('rel-sidebar-toggle');
        var isMobile = window.matchMedia('(max-width: 700px)').matches;

        if (isMobile && sidebar) sidebar.classList.add('collapsed');

        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function () {
                sidebar.classList.toggle('collapsed');
            });
        }

        if (isMobile && sidebar) {
            canvas.addEventListener('pointerdown', function (e) {
                if (e.pointerType !== 'touch') return;
                if (!sidebar.classList.contains('collapsed')) {
                    sidebar.classList.add('collapsed');
                }
            });
        }

        // 缩放按钮
        document.getElementById('rel-zoom-in').addEventListener('click', function () {
            state.view.scale = Math.min(3, state.view.scale * 1.2);
        });
        document.getElementById('rel-zoom-out').addEventListener('click', function () {
            state.view.scale = Math.max(0.2, state.view.scale / 1.2);
        });
        document.getElementById('rel-zoom-reset').addEventListener('click', function () {
            state.view = { x: 0, y: 0, scale: 0.7 };
        });

        // 选择模式按钮
        var selectBtn = document.getElementById('rel-select-mode');
        if (selectBtn) {
            selectBtn.addEventListener('click', function () {
                state.selectionMode = !state.selectionMode;
                selectBtn.classList.toggle('active', state.selectionMode);
                selectBtn.title = state.selectionMode
                    ? '选择模式（点击退出）'
                    : '选择模式（框选多个节点）';
                if (!state.selectionMode) {
                    state.selectedNodes.clear();
                }
            });
        }

        // 滚轮缩放
        canvas.addEventListener('wheel', function (e) {
            e.preventDefault();
            var factor = e.deltaY < 0 ? 1.1 : 1 / 1.1;
            var newScale = Math.max(0.2, Math.min(3, state.view.scale * factor));
            var rect = canvas.getBoundingClientRect();
            var mx = e.clientX - rect.left;
            var my = e.clientY - rect.top;
            var before = screenToWorld(mx, my);
            state.view.scale = newScale;
            var after = screenToWorld(mx, my);
            state.view.x += (after.x - before.x) * newScale * -1;
            state.view.y += (after.y - before.y) * newScale * -1;
        }, { passive: false });

        // ===== 指针按下 =====
        canvas.addEventListener('pointerdown', function (e) {
            state.pointerDownPos = { x: e.clientX, y: e.clientY };
            state.lastWasDrag = false;
            canvas.setPointerCapture(e.pointerId);
            var rect = canvas.getBoundingClientRect();
            var sx = e.clientX - rect.left;
            var sy = e.clientY - rect.top;
            var w = screenToWorld(sx, sy);
            var hit = hitTest(w.x, w.y);

            if (hit) {
                if (!window.REL_CAN_EDIT) {
                    selectCharacter(hit);
                    if (window.matchMedia('(max-width: 700px)').matches && sidebar) {
                        sidebar.classList.remove('collapsed');
                    }
                    return;
                }

                // 已选中多个：拖整组
                if (state.selectedNodes.size > 0 && state.selectedNodes.has(hit)) {
                    state.draggingGroup = {
                        startW: { x: w.x, y: w.y },
                        origPositions: {}
                    };
                    state.selectedNodes.forEach(function (id) {
                        var pp = state.positions[id];
                        if (pp) state.draggingGroup.origPositions[id] = { x: pp.x, y: pp.y };
                    });
                } else {
                    // 单节点拖动（不清空多选）
                    state.draggingNode = hit;
                    var p = state.positions[hit];
                    state.draggingOffset = { x: w.x - p.x, y: w.y - p.y };
                    state.dragStart = { x: e.clientX, y: e.clientY };
                }
            } else {
                // 空白：框选 或 平移
                var wantSelect = state.selectionMode || e.shiftKey;
                if (wantSelect) {
                    state.selecting = true;
                    state.selectStart = { x: sx, y: sy };
                    state.selectEnd = { x: sx, y: sy };
                    canvas.classList.add('selecting');
                } else {
                    state.draggingCanvas = true;
                    state.dragStart = { x: e.clientX, y: e.clientY, vx: state.view.x, vy: state.view.y };
                    canvas.classList.add('grabbing');
                }
            }
        });

        // ===== 指针移动 =====
        canvas.addEventListener('pointermove', function (e) {
            var rect = canvas.getBoundingClientRect();
            var sx = e.clientX - rect.left;
            var sy = e.clientY - rect.top;
            var w = screenToWorld(sx, sy);

            if (state.selecting) {
                state.selectEnd = { x: sx, y: sy };
                return;
            }

            if (state.draggingGroup) {
                var gdx = w.x - state.draggingGroup.startW.x;
                var gdy = w.y - state.draggingGroup.startW.y;
                Object.keys(state.draggingGroup.origPositions).forEach(function (id) {
                    var orig = state.draggingGroup.origPositions[id];
                    var pp = state.positions[id];
                    if (!pp) return;
                    pp.x = orig.x + gdx;
                    pp.y = orig.y + gdy;
                    pp.vx = 0;
                    pp.vy = 0;
                });
                return;
            }

            if (state.draggingNode) {
                var p = state.positions[state.draggingNode];
                if (p) {
                    p.x = w.x - state.draggingOffset.x;
                    p.y = w.y - state.draggingOffset.y;
                    p.vx = 0;
                    p.vy = 0;
                }
                return;
            }
            if (state.draggingCanvas) {
                state.view.x = state.dragStart.vx + (e.clientX - state.dragStart.x);
                state.view.y = state.dragStart.vy + (e.clientY - state.dragStart.y);
                return;
            }

            // 只有鼠标才做 hover / tooltip
            if (e.pointerType === 'touch') return;

            var hit = hitTest(w.x, w.y);
            if (hit !== state.hovered) {
                state.hovered = hit;
                canvas.classList.toggle('hovering', !!hit);
                updateTooltip(e.clientX, e.clientY, hit);
            } else if (hit) {
                updateTooltip(e.clientX, e.clientY, hit);
            }
        });

        // ===== 指针抬起 =====
        canvas.addEventListener('pointerup', function (e) {
            // 计算从按下到抬起的总移动距离
            var totalMoved = state.pointerDownPos
                ? Math.hypot(e.clientX - state.pointerDownPos.x, e.clientY - state.pointerDownPos.y)
                : 0;
            if (totalMoved > 5) {
                state.lastWasDrag = true;
            }

            if (state.selecting) {
                state.selected = null;
                state.searchFocus = null;

                var sw = Math.abs(state.selectEnd.x - state.selectStart.x);
                var sh = Math.abs(state.selectEnd.y - state.selectStart.y);

                if (sw < 5 && sh < 5) {
                    state.selectedNodes.clear();
                } else {
                    var startW = screenToWorld(state.selectStart.x, state.selectStart.y);
                    var endW = screenToWorld(state.selectEnd.x, state.selectEnd.y);
                    var minX = Math.min(startW.x, endW.x);
                    var maxX = Math.max(startW.x, endW.x);
                    var minY = Math.min(startW.y, endW.y);
                    var maxY = Math.max(startW.y, endW.y);

                    if (!e.shiftKey) state.selectedNodes.clear();
                    state.characters.forEach(function (c) {
                        if (!matchesFilter(c)) return;
                        var pp = state.positions[c.id];
                        if (!pp) return;
                        if (pp.x >= minX && pp.x <= maxX && pp.y >= minY && pp.y <= maxY) {
                            state.selectedNodes.add(c.id);
                        }
                    });
                }

                state.selecting = false;
                state.selectStart = null;
                state.selectEnd = null;
                canvas.classList.remove('selecting');
                return;
            }

            if (state.draggingGroup) {
                state.draggingGroup = null;
                state.lastDragEnd = Date.now();
                scheduleSavePositions();
                return;
            }

            if (state.draggingNode) {
                var threshold = e.pointerType === 'touch' ? 12 : 4;
                var moved = state.dragStart
                    ? Math.hypot(e.clientX - state.dragStart.x, e.clientY - state.dragStart.y)
                    : 0;
                if (moved < threshold) {
                    selectCharacter(state.draggingNode);
                    if (window.matchMedia('(max-width: 700px)').matches && sidebar) {
                        sidebar.classList.remove('collapsed');
                    }
                } else {
                    scheduleSavePositions();
                }
                state.draggingNode = null;
                state.draggingOffset = null;
                state.lastDragEnd = Date.now();
                return;
            }
            if (state.draggingCanvas) {
                state.draggingCanvas = false;
                state.dragStart = null;
                state.lastDragEnd = Date.now();
                canvas.classList.remove('grabbing');
            }
        });

        canvas.addEventListener('pointercancel', function () {
            state.selecting = false;
            state.selectStart = null;
            state.selectEnd = null;
            state.draggingNode = null;
            state.draggingGroup = null;
            state.draggingCanvas = false;
            canvas.classList.remove('grabbing', 'selecting');
        });

        canvas.addEventListener('pointerleave', function () {
            state.hovered = null;
            hideTooltip();
        });

        canvas.addEventListener('click', function (e) {
            // 刚才是拖动（包括平移画布 / 拖节点 / 框选），跳过这次 click
            if (state.lastWasDrag) {
                state.lastWasDrag = false;
                return;
            }

            var rect = canvas.getBoundingClientRect();
            var sx = e.clientX - rect.left;
            var sy = e.clientY - rect.top;
            var w = screenToWorld(sx, sy);
            if (!hitTest(w.x, w.y)) {
                state.selected = null;
                state.selectedNodes.clear();
                renderSidebar(null);
            }
        });

        // ===== 双指缩放 =====
        var pinchStartDist = 0;
        var pinchStartScale = 1;
        var pinchStartCenter = null;

        function getTouchDist(t1, t2) {
            return Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY);
        }
        function getTouchCenter(t1, t2) {
            return {
                x: (t1.clientX + t2.clientX) / 2,
                y: (t1.clientY + t2.clientY) / 2
            };
        }

        canvas.addEventListener('touchstart', function (e) {
            if (e.touches.length !== 2) return;
            e.preventDefault();
            pinchStartDist = getTouchDist(e.touches[0], e.touches[1]);
            pinchStartScale = state.view.scale;
            pinchStartCenter = getTouchCenter(e.touches[0], e.touches[1]);
            state.draggingNode = null;
            state.draggingCanvas = false;
        }, { passive: false });

        canvas.addEventListener('touchmove', function (e) {
            if (e.touches.length !== 2 || !pinchStartDist) return;
            e.preventDefault();

            var dist = getTouchDist(e.touches[0], e.touches[1]);
            var ratio = dist / pinchStartDist;
            var newScale = Math.max(0.2, Math.min(3, pinchStartScale * ratio));

            var rect = canvas.getBoundingClientRect();
            var mx = pinchStartCenter.x - rect.left;
            var my = pinchStartCenter.y - rect.top;
            var before = screenToWorld(mx, my);
            state.view.scale = newScale;
            var after = screenToWorld(mx, my);
            state.view.x += (after.x - before.x) * newScale * -1;
            state.view.y += (after.y - before.y) * newScale * -1;
        }, { passive: false });

        canvas.addEventListener('touchend', function (e) {
            if (e.touches.length < 2) {
                pinchStartDist = 0;
                pinchStartCenter = null;
            }
        });

        // ===== 搜索 =====
        var searchInput = document.getElementById('rel-search');
        var clearBtn = document.getElementById('rel-clear');

        var searchTimer = null;
        var lastKw = '';

        function computeMatches() {
            var kw = state.search.toLowerCase();
            if (!kw) {
                state.searchMatches = [];
                state.searchIndex = -1;
                state.searchFocus = null;
                return;
            }
            var matches = [];
            state.characters.forEach(function (c) {
                if (!matchesFilter(c)) return;
                if (c.name.toLowerCase().indexOf(kw) !== -1) matches.push(c.id);
            });
            state.searchMatches = matches;
            state.searchIndex = matches.length ? 0 : -1;
            state.searchFocus = matches.length ? matches[0] : null;
        }

        function focusOnNode(id) {
            var p = state.positions[id];
            if (!p) return;
            state.viewTarget = {
                x: -p.x * state.view.scale,
                y: -p.y * state.view.scale
            };
        }

        function cycleMatch() {
            if (!state.searchMatches.length) return;
            state.searchIndex = (state.searchIndex + 1) % state.searchMatches.length;
            state.searchFocus = state.searchMatches[state.searchIndex];
            focusOnNode(state.searchFocus);
        }

        searchInput.addEventListener('input', function () {
            state.search = searchInput.value.trim();
            clearBtn.hidden = !state.search;

            clearTimeout(searchTimer);
            if (state.search === lastKw) return;
            lastKw = state.search;

            searchTimer = setTimeout(function () {
                computeMatches();
                if (state.searchFocus) focusOnNode(state.searchFocus);
            }, 200);
        });

        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (!state.searchMatches.length) computeMatches();
                cycleMatch();
            } else if (e.key === 'Escape') {
                searchInput.value = '';
                state.search = '';
                clearBtn.hidden = true;
                computeMatches();
            }
        });

        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            state.search = '';
            clearBtn.hidden = true;
            state.searchFocus = null;
            state.viewTarget = null;
            computeMatches();
        });

        window.addEventListener('resize', resizeCanvas);

        // ===== 导出面板 =====
        var exportBtn    = document.getElementById('rel-export-btn');
        var exportPanel  = document.getElementById('rel-export-panel');
        var exportCancel = document.getElementById('rel-export-cancel');
        var exportOK     = document.getElementById('rel-export-confirm');

        if (exportBtn && exportPanel && exportCancel && exportOK) {
            exportBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                exportPanel.hidden = !exportPanel.hidden;
            });
            exportCancel.addEventListener('click', function () {
                exportPanel.hidden = true;
            });
            exportOK.addEventListener('click', function () {
                exportPanel.hidden = true;
                doExport();
            });

            document.addEventListener('click', function (e) {
                if (exportPanel.hidden) return;
                if (exportPanel.contains(e.target)) return;
                if (e.target === exportBtn) return;
                exportPanel.hidden = true;
            });
        }
    }

    function selectCharacter(id) {
        state.selected = id;
        renderSidebar(id);
    }

    // ============================================================
    // Tooltip
    // ============================================================
    function ensureTooltip() {
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.className = 'rel-tooltip';
            tooltip.hidden = true;
            document.body.appendChild(tooltip);
        }
        return tooltip;
    }

    function updateTooltip(mx, my, id) {
        if (!id) { hideTooltip(); return; }
        var t = ensureTooltip();
        var c = state.charMap[id];
        t.textContent = c ? c.name : '';
        t.hidden = false;
        t.style.left = (mx + 14) + 'px';
        t.style.top = (my + 14) + 'px';
    }

    function hideTooltip() {
        if (tooltip) tooltip.hidden = true;
    }

    // ============================================================
    // 筛选面板
    // ============================================================
    function initFilters() {
        var teamsEl = document.getElementById('rel-filter-teams');
        var typesEl = document.getElementById('rel-filter-types');

        var teamsWithCount = state.teams.map(function (t) {
            var count = state.characters.filter(function (c) {
                return c.teams && c.teams.indexOf(t.id) !== -1;
            }).length;
            return { team: t, count: count };
        });
        var noneTeamCount = state.characters.filter(function (c) {
            return !c.teams || !c.teams.length;
        }).length;
        if (noneTeamCount > 0) {
            teamsWithCount.push({ team: { id: '__none', name: '未分组', color: '' }, count: noneTeamCount });
        }

        teamsWithCount.forEach(function (item) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'rel-filter-chip';
            chip.dataset.teamId = item.team.id;
            chip.style.setProperty('--c', item.team.color || '#888');
            chip.innerHTML = '<span class="rel-filter-dot"></span>' +
                escapeHtml(item.team.name) +
                '<span style="opacity:.6;margin-left:4px;">' + item.count + '</span>';
            chip.addEventListener('click', function () {
                var id = item.team.id;
                if (state.filter.teams.has(id)) state.filter.teams.delete(id);
                else state.filter.teams.add(id);
                chip.classList.toggle('active');
            });
            teamsEl.appendChild(chip);
        });

        var grouped = {};
        state.types.forEach(function (t) {
            if (!grouped[t.category]) grouped[t.category] = [];
            grouped[t.category].push(t);
        });

        Object.keys(grouped).forEach(function (cat) {
            var title = document.createElement('div');
            title.style.width = '100%';
            title.style.fontSize = '11px';
            title.style.color = 'var(--text-muted)';
            title.style.marginTop = '6px';
            title.textContent = cat;
            typesEl.appendChild(title);

            grouped[cat].forEach(function (t) {
                var chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'rel-filter-chip';
                chip.style.setProperty('--c', 'var(--accent)');
                chip.textContent = t.name;
                chip.addEventListener('click', function () {
                    if (state.filter.types.has(t.id)) state.filter.types.delete(t.id);
                    else state.filter.types.add(t.id);
                    chip.classList.toggle('active');
                });
                typesEl.appendChild(chip);
            });
        });

        document.querySelectorAll('.rel-filter-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = document.getElementById(btn.dataset.target);
                var isHidden = target.hidden;
                target.hidden = !isHidden;
                btn.textContent = isHidden ? '收起' : '展开';
            });
        });
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // ============================================================
    // 侧栏详情
    // ============================================================
    function renderSidebar(id) {
        var body = document.getElementById('rel-sidebar-body');
        if (!id) {
            body.innerHTML = '<div class="rel-sidebar-hint">点击一个节点查看详情</div>';
            return;
        }
        var c = state.charMap[id];
        if (!c) return;

        var color = c.color || pickColorForId(c.id);
        var initial = c.name.substring(0, 1);

        var html = '';
        html += '<div class="rel-detail-head">';
        html += '<div class="rel-detail-avatar" style="--c:' + color + ';"';
        if (!c.avatar) html += ' data-initial="' + escapeHtml(initial) + '"';
        html += '>';
        if (c.avatar) html += '<img src="' + escapeHtml(c.avatar) + '" alt="" referrerpolicy="no-referrer">';
        html += '</div>';
        html += '<div>';
        html += '<div class="rel-detail-name">' + escapeHtml(c.name) + '</div>';

        if (c.teams && c.teams.length) {
            html += '<div class="rel-detail-teams">';
            c.teams.forEach(function (tid) {
                var t = state.teamMap[tid];
                if (!t) return;
                html += '<span class="rel-team-tag" style="--c:' + (t.color || '#888') + ';">' + escapeHtml(t.name) + '</span>';
            });
            html += '</div>';
        }
        html += '</div>';
        html += '</div>';

        if (c.wiki) {
            html += '<div class="rel-detail-actions">';
            html += '<a href="' + escapeHtml(c.wiki) + '" target="_blank" rel="noopener">查看 Wiki →</a>';
            html += '</div>';
        }

        if (c.note) {
            html += '<div class="rel-detail-note">' + escapeHtml(c.note) + '</div>';
        }

        var myRels = state.relations.filter(function (r) {
            return r.from === c.id || r.to === c.id;
        });

        if (myRels.length) {
            html += '<div class="rel-detail-section-title">关系（' + myRels.length + '）</div>';
            myRels.forEach(function (r) {
                var isFrom = r.from === c.id;
                var otherId = isFrom ? r.to : r.from;
                var other = state.charMap[otherId];
                var type = state.typeMap[r.type];
                var label = type ? (isFrom ? type.name : type.reverse) : '?';
                var dir = isFrom ? '→' : '←';

                html += '<div class="rel-detail-rel" data-other-id="' + otherId + '">';
                html += '<span class="rel-detail-dir">' + dir + '</span>';
                html += '<span class="rel-detail-other">' + escapeHtml(other ? other.name : '?') + '</span>';
                html += '<span class="rel-detail-mid">的</span>';
                html += '<span class="rel-detail-type">' + escapeHtml(label) + '</span>';
                html += '</div>';
            });
        } else {
            html += '<div class="rel-detail-section-title">关系</div>';
            html += '<div class="rel-sidebar-hint" style="padding:10px 0;">暂无关系</div>';
        }

        body.innerHTML = html;

        body.querySelectorAll('.rel-detail-rel').forEach(function (el) {
            el.addEventListener('click', function () {
                var oid = parseInt(el.dataset.otherId, 10);
                if (oid) selectCharacter(oid);
            });
        });
    }

    // ============================================================
    // 导出 PNG
    // ============================================================
    function resolveBgColor() {
        var sel = document.getElementById('rel-export-bg');
        var v = sel ? sel.value : 'theme';
        if (v === 'white') return '#ffffff';
        if (v === 'black') return '#000000';
        if (v === 'transparent') return null;
        var raw = getComputedStyle(document.documentElement).getPropertyValue('--bg').trim();
        if (raw) return raw;
        return isDark() ? '#12131a' : '#f7f7fb';
    }

    function downloadBlob(blob, filename) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }

    function pad2(n) { return n < 10 ? '0' + n : '' + n; }

    function exportFileName(prefix) {
        var d = new Date();
        return prefix + '-'
            + d.getFullYear() + pad2(d.getMonth() + 1) + pad2(d.getDate())
            + '-' + pad2(d.getHours()) + pad2(d.getMinutes()) + pad2(d.getSeconds())
            + '.png';
    }

    function doExport() {
        var scopeSel = document.getElementById('rel-export-scope');
        var scope = scopeSel ? scopeSel.value : 'viewport';
        var bg = resolveBgColor();
        var includeBgEffects = document.getElementById('rel-export-bg-effects');
        var wantEffects = includeBgEffects ? includeBgEffects.checked : true;

        if (scope === 'full') {
            doExportFull(bg);
        } else {
            doExportViewport(bg, wantEffects);
        }
    }

    function doExportViewport(bg, wantEffects) {
        var scaleSel = document.getElementById('rel-export-scale');
        var scale = parseInt(scaleSel ? scaleSel.value : '2', 10) || 2;
        scale = Math.max(1, Math.min(4, scale));

        var maxDim = 8000;
        if (W * scale > maxDim || H * scale > maxDim) {
            var limit = Math.min(maxDim / W, maxDim / H);
            scale = Math.max(1, Math.floor(limit));
        }

        var exportCanvas = document.createElement('canvas');
        exportCanvas.width = Math.floor(W * scale);
        exportCanvas.height = Math.floor(H * scale);
        var ectx = exportCanvas.getContext('2d');

        if (bg) {
            ectx.fillStyle = bg;
            ectx.fillRect(0, 0, exportCanvas.width, exportCanvas.height);
        }

        if (wantEffects) {
            var bgCanvas = document.getElementById('bg-canvas');
            if (bgCanvas && bgCanvas.width > 0 && bgCanvas.height > 0) {
                var wrapRect = wrap.getBoundingClientRect();
                var bgRect = bgCanvas.getBoundingClientRect();
                var bgDprX = bgCanvas.width / bgRect.width;
                var bgDprY = bgCanvas.height / bgRect.height;
                var sx = (wrapRect.left - bgRect.left) * bgDprX;
                var sy = (wrapRect.top - bgRect.top) * bgDprY;
                var sw = wrapRect.width * bgDprX;
                var sh = wrapRect.height * bgDprY;
                sx = Math.max(0, Math.min(sx, bgCanvas.width));
                sy = Math.max(0, Math.min(sy, bgCanvas.height));
                sw = Math.min(sw, bgCanvas.width - sx);
                sh = Math.min(sh, bgCanvas.height - sy);
                if (sw > 0 && sh > 0) {
                    try {
                        ectx.drawImage(bgCanvas, sx, sy, sw, sh, 0, 0, exportCanvas.width, exportCanvas.height);
                    } catch (e) {}
                }
            }
        }

        var savedCtx = ctx;
        ctx = ectx;
        ctx.setTransform(scale, 0, 0, scale, 0, 0);

        try {
            draw({ skipClear: true, uiscale: 1 });
        } finally {
            ctx = savedCtx;
        }

        exportCanvas.toBlob(function (blob) {
            if (!blob) { alert('导出失败，请重试'); return; }
            downloadBlob(blob, exportFileName('relations'));
        }, 'image/png');
    }

    function doExportFull(bg) {
        if (!state.characters.length) {
            alert('没有内容可导出');
            return;
        }

        var btn = document.getElementById('rel-export-confirm');
        var origText = btn ? btn.textContent : '';
        if (btn) {
            btn.textContent = '生成中…';
            btn.disabled = true;
        }

        // 双 rAF 让浏览器先渲染按钮状态
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                try {
                    doExportFullInner(bg);
                } catch (e) {
                    alert('导出失败：' + e.message);
                    console.error(e);
                } finally {
                    if (btn) {
                        btn.textContent = origText;
                        btn.disabled = false;
                    }
                }
            });
        });
    }

    function doExportFullInner(bg) {
        // 1. 世界坐标包围盒
        var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        var any = false;
        state.characters.forEach(function (c) {
            var p = state.positions[c.id];
            if (!p) return;
            any = true;
            var pad = 100;
            if (p.x - pad < minX) minX = p.x - pad;
            if (p.y - pad < minY) minY = p.y - pad;
            if (p.x + pad > maxX) maxX = p.x + pad;
            if (p.y + pad > maxY) maxY = p.y + pad;
        });
        if (!any) throw new Error('没有内容');

        var contentW = maxX - minX;
        var contentH = maxY - minY;
        var centerX = (minX + maxX) / 2;
        var centerY = (minY + maxY) / 2;

        // 2. 计算导出 scale
        var MAX_DIM = 8192;
        var MAX_SCALE = 5;
        var exportScale = Math.min(MAX_DIM / contentW, MAX_DIM / contentH, MAX_SCALE);
        if (!isFinite(exportScale) || exportScale <= 0) exportScale = 1;

        var targetW = Math.max(100, Math.round(contentW * exportScale));
        var targetH = Math.max(100, Math.round(contentH * exportScale));

        if (targetW > MAX_DIM || targetH > MAX_DIM) {
            var adjust = Math.min(MAX_DIM / targetW, MAX_DIM / targetH);
            exportScale *= adjust;
            targetW = Math.round(contentW * exportScale);
            targetH = Math.round(contentH * exportScale);
        }

        // 3. 离屏画布
        var exportCanvas = document.createElement('canvas');
        exportCanvas.width = targetW;
        exportCanvas.height = targetH;
        var ectx = exportCanvas.getContext('2d');

        // 4. 背景
        if (bg) {
            ectx.fillStyle = bg;
            ectx.fillRect(0, 0, targetW, targetH);
        }

        // 5. 临时替换上下文和视图
        var savedCtx = ctx;
        var savedView = { x: state.view.x, y: state.view.y, scale: state.view.scale };
        var savedW = W, savedH = H;

        ctx = ectx;
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        state.view = {
            x: -centerX * exportScale,
            y: -centerY * exportScale,
            scale: exportScale
        };
        W = targetW;
        H = targetH;

        try {
            draw({ uiscale: exportScale });
        } finally {
            ctx = savedCtx;
            state.view = savedView;
            W = savedW;
            H = savedH;
        }

        // 6. 导出
        exportCanvas.toBlob(function (blob) {
            if (!blob) { alert('导出失败，请重试'); return; }
            downloadBlob(blob, exportFileName('relations-full'));
        }, 'image/png');
    }

    // ============================================================
    // 风格切换
    // ============================================================
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
            if (s === 'cute' || s === 'tech' || s === 'green' ||
                s === 'star' || s === 'snow' || s === 'cyber' ||
                s === 'aurora' || s === 'y2k') current = s;
        } catch (e) {}
        markActive(current);
    }

    // ============================================================
    // 背景粒子
    // ============================================================
    function initBgCanvas() {
        var bgCanvas = document.getElementById('bg-canvas');
        if (!bgCanvas) return;
        var bctx = bgCanvas.getContext('2d');
        var bgDpr = Math.min(window.devicePixelRatio || 1, 2);
        var bw = 0, bh = 0;
        var mode = 'none';
        var items = [];
        var lastTime = 0;

        var MATRIX_CHARS = '01アイウエオカキクケコサシスセソタチツテトナニヌネノabcdefghijkmnpqrstuvwxyz{}[]<>=+-*/';

        function isDarkTheme() {
            return document.documentElement.dataset.theme === 'dark';
        }

        function resizeBg() {
            var r = bgCanvas.getBoundingClientRect();
            bw = r.width;
            bh = r.height;
            bgCanvas.width = Math.floor(bw * bgDpr);
            bgCanvas.height = Math.floor(bh * bgDpr);
            bctx.setTransform(bgDpr, 0, 0, bgDpr, 0, 0);
            setupBgMode();
        }

        function setupBgMode() {
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

        function setupPetals() {
            var count = Math.max(15, Math.floor(bw / 36));
            for (var i = 0; i < count; i++) {
                items.push({
                    x: Math.random() * bw, y: Math.random() * bh,
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
            var dark = isDarkTheme();
            items.forEach(function (p) {
                p.y += p.vy * dt;
                p.phase += dt;
                p.rot += p.vr * dt;
                p.x += Math.sin(p.phase) * p.sway * 30 * dt;
                if (p.y > bh + 20) { p.y = -20; p.x = Math.random() * bw; }
                bctx.save();
                bctx.translate(p.x, p.y);
                bctx.rotate(p.rot);
                bctx.fillStyle = 'hsla(' + p.hue + ', 80%, ' + (dark ? '65%' : '78%') + ', ' + (dark ? '0.55' : '0.72') + ')';
                bctx.beginPath();
                bctx.ellipse(0, 0, p.size, p.size * 0.52, 0, 0, Math.PI * 2);
                bctx.fill();
                bctx.strokeStyle = 'hsla(' + p.hue + ', 70%, ' + (dark ? '85%' : '92%') + ', 0.35)';
                bctx.lineWidth = 1;
                bctx.beginPath();
                bctx.moveTo(-p.size * 0.9, 0);
                bctx.lineTo(p.size * 0.9, 0);
                bctx.stroke();
                bctx.restore();
            });
        }

        function setupMatrix() {
            var colWidth = 18;
            var cols = Math.ceil(bw / colWidth);
            for (var i = 0; i < cols; i++) {
                items.push({
                    x: i * colWidth + colWidth / 2,
                    y: -Math.random() * bh,
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
            var dark = isDarkTheme();
            bctx.font = '13px ui-monospace, Menlo, Consolas, monospace';
            bctx.textAlign = 'center';
            bctx.textBaseline = 'middle';
            items.forEach(function (col) {
                col.y += col.speed * dt;
                if (col.y - col.maxLen * col.size > bh) {
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
                    if (y < -20 || y > bh + 20) continue;
                    if (i === 0) {
                        bctx.globalAlpha = 1;
                        bctx.fillStyle = dark ? '#e8ffff' : '#003d5c';
                        bctx.shadowColor = dark ? '#7dfcff' : '#0090d0';
                        bctx.shadowBlur = 10;
                    } else {
                        bctx.globalAlpha = Math.max(0.05, 1 - i / col.chars.length);
                        bctx.fillStyle = dark ? '#00d4ff' : '#0078b8';
                        bctx.shadowBlur = 0;
                    }
                    bctx.fillText(col.chars[i], col.x, y);
                }
                bctx.globalAlpha = 1;
                bctx.shadowBlur = 0;
            });
        }

        function setupOrbs() {
            for (var i = 0; i < 5; i++) {
                items.push({
                    x: Math.random() * bw, y: Math.random() * bh,
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
            var dark = isDarkTheme();
            items.forEach(function (o) {
                o.x += o.vx * dt;
                o.y += o.vy * dt;
                o.phase += o.pulseSpeed * dt;
                if (o.x < -o.r) o.x = bw + o.r;
                if (o.x > bw + o.r) o.x = -o.r;
                if (o.y < -o.r) o.y = bh + o.r;
                if (o.y > bh + o.r) o.y = -o.r;
                var pulse = 0.5 + 0.5 * Math.sin(o.phase);
                var r = o.r * (0.85 + pulse * 0.3);
                var alpha = (dark ? 0.12 : 0.16) + pulse * 0.14;
                var grd = bctx.createRadialGradient(o.x, o.y, 0, o.x, o.y, r);
                var col = 'hsla(' + o.hue + ', 60%, ' + (dark ? '45%' : '60%') + ', ';
                grd.addColorStop(0, col + alpha + ')');
                grd.addColorStop(1, col + '0)');
                bctx.fillStyle = grd;
                bctx.beginPath();
                bctx.arc(o.x, o.y, r, 0, Math.PI * 2);
                bctx.fill();
            });
        }

        function setupStars() {
            var starCount = Math.max(80, Math.floor((bw * bh) / 8000));
            for (var i = 0; i < starCount; i++) {
                items.push({
                    x: Math.random() * bw, y: Math.random() * bh,
                    r: 0.4 + Math.random() * 1.4,
                    phase: Math.random() * Math.PI * 2,
                    speed: 0.6 + Math.random() * 1.6
                });
            }
            items.meteor = null;
            items.nextMeteorIn = 5 + Math.random() * 8;
        }

        function drawStars(dt) {
            var dark = isDarkTheme();
            var twinkleColor = dark ? '255, 255, 255' : '60, 40, 100';
            items.forEach(function (s) {
                s.phase += s.speed * dt;
                var alpha = 0.4 + 0.6 * (0.5 + 0.5 * Math.sin(s.phase));
                bctx.fillStyle = 'rgba(' + twinkleColor + ',' + alpha + ')';
                bctx.beginPath();
                bctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
                bctx.fill();
            });
            items.nextMeteorIn -= dt;
            if (!items.meteor && items.nextMeteorIn <= 0) {
                items.meteor = {
                    x: Math.random() * bw * 0.6,
                    y: Math.random() * bh * 0.4,
                    vx: 500 + Math.random() * 300,
                    vy: 200 + Math.random() * 150,
                    life: 0.9, max: 0.9
                };
            }
            if (items.meteor) {
                var m = items.meteor;
                m.x += m.vx * dt;
                m.y += m.vy * dt;
                m.life -= dt;
                var a = Math.max(0, m.life / m.max);
                var tailLen = 120;
                var grad = bctx.createLinearGradient(
                    m.x, m.y,
                    m.x - m.vx * tailLen / 500, m.y - m.vy * tailLen / 500
                );
                var tone = dark ? '255, 255, 255' : '120, 80, 200';
                grad.addColorStop(0, 'rgba(' + tone + ',' + a + ')');
                grad.addColorStop(1, 'rgba(' + tone + ',0)');
                bctx.strokeStyle = grad;
                bctx.lineWidth = 1.8;
                bctx.beginPath();
                bctx.moveTo(m.x, m.y);
                bctx.lineTo(m.x - m.vx * tailLen / 500, m.y - m.vy * tailLen / 500);
                bctx.stroke();
                if (m.life <= 0) {
                    items.meteor = null;
                    items.nextMeteorIn = 6 + Math.random() * 10;
                }
            }
        }

        function setupSnow() {
            var count = Math.max(30, Math.floor(bw / 22));
            for (var i = 0; i < count; i++) {
                items.push({
                    x: Math.random() * bw, y: Math.random() * bh,
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
            var dark = isDarkTheme();
            items.forEach(function (p) {
                p.y += p.vy * dt;
                p.phase += dt;
                p.rot += p.vr * dt;
                p.x += Math.sin(p.phase) * p.sway * 26 * dt;
                if (p.y > bh + 10) { p.y = -10; p.x = Math.random() * bw; }
                if (p.x < -10) p.x = bw + 10;
                if (p.x > bw + 10) p.x = -10;
                bctx.save();
                bctx.translate(p.x, p.y);
                bctx.rotate(p.rot);
                bctx.fillStyle = dark ? 'rgba(220, 235, 255, 0.85)' : 'rgba(255, 255, 255, 0.98)';
                bctx.beginPath();
                bctx.arc(0, 0, p.r, 0, Math.PI * 2);
                bctx.fill();
                if (p.r > 2.4) {
                    bctx.strokeStyle = dark ? 'rgba(180, 220, 255, 0.35)' : 'rgba(255, 255, 255, 0.75)';
                    bctx.lineWidth = 1;
                    for (var i = 0; i < 3; i++) {
                        var ang = (i * Math.PI * 2) / 3;
                        bctx.beginPath();
                        bctx.moveTo(Math.cos(ang) * p.r * 0.5, Math.sin(ang) * p.r * 0.5);
                        bctx.lineTo(Math.cos(ang) * p.r * 2.4, Math.sin(ang) * p.r * 2.4);
                        bctx.stroke();
                    }
                }
                bctx.restore();
            });
        }

        function setupCyber() {
            var colWidth = 26;
            var cols = Math.ceil(bw / colWidth);
            var palette = ['#ff2bd6', '#7d5cff', '#00e5ff'];
            for (var i = 0; i < cols; i++) {
                items.push({
                    x: i * colWidth + colWidth / 2,
                    y: -Math.random() * bh,
                    len: 40 + Math.random() * 120,
                    speed: 220 + Math.random() * 400,
                    color: palette[Math.floor(Math.random() * palette.length)],
                    thickness: 1.5 + Math.random() * 1.5
                });
            }
            items.flashTimer = 4 + Math.random() * 6;
            items.flash = 0;
        }

        function hexToRgba2(hex, alpha) {
            var h = hex.replace('#', '');
            var r = parseInt(h.substr(0, 2), 16);
            var g = parseInt(h.substr(2, 2), 16);
            var b = parseInt(h.substr(4, 2), 16);
            return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
        }

        function drawCyber(dt) {
            var dark = isDarkTheme();
            items.forEach(function (bar) {
                bar.y += bar.speed * dt;
                if (bar.y - bar.len > bh) {
                    bar.y = -Math.random() * 60;
                    bar.speed = 220 + Math.random() * 400;
                    bar.len = 40 + Math.random() * 120;
                }
                var grad = bctx.createLinearGradient(bar.x, bar.y - bar.len, bar.x, bar.y);
                var baseAlpha = dark ? 0.85 : 0.55;
                grad.addColorStop(0, 'rgba(0, 0, 0, 0)');
                grad.addColorStop(1, hexToRgba2(bar.color, baseAlpha));
                bctx.strokeStyle = grad;
                bctx.lineWidth = bar.thickness;
                bctx.lineCap = 'round';
                bctx.beginPath();
                bctx.moveTo(bar.x, bar.y - bar.len);
                bctx.lineTo(bar.x, bar.y);
                bctx.stroke();
                bctx.fillStyle = hexToRgba2(bar.color, dark ? 0.95 : 0.7);
                bctx.beginPath();
                bctx.arc(bar.x, bar.y, bar.thickness * 0.9, 0, Math.PI * 2);
                bctx.fill();
            });
            items.flashTimer -= dt;
            if (items.flashTimer <= 0) {
                items.flash = 0.08;
                items.flashTimer = 5 + Math.random() * 8;
            }
            if (items.flash > 0) {
                items.flash -= dt;
                var fa = Math.max(0, items.flash / 0.08) * (dark ? 0.06 : 0.04);
                bctx.fillStyle = 'rgba(255, 43, 214, ' + fa + ')';
                bctx.fillRect(0, 0, bw, bh);
            }
        }

        function setupAurora() {
            items.bands = [
                { hue1: 150, hue2: 200, phase: 0,       amp: 60, speed: 0.12, yRatio: 0.35, thick: 140, alpha: 0.25 },
                { hue1: 190, hue2: 260, phase: Math.PI, amp: 80, speed: 0.09, yRatio: 0.55, thick: 160, alpha: 0.22 },
                { hue1: 270, hue2: 320, phase: 1.8,     amp: 70, speed: 0.14, yRatio: 0.7,  thick: 120, alpha: 0.2 }
            ];
            items.time = 0;
        }

        function drawAurora(dt) {
            items.time += dt;
            var dark = isDarkTheme();
            var baseAlpha = dark ? 1 : 0.6;
            items.bands.forEach(function (band) {
                band.phase += band.speed * dt;
                var baseY = bh * band.yRatio;
                var grad = bctx.createLinearGradient(0, 0, bw, 0);
                grad.addColorStop(0,   'hsla(' + band.hue1 + ', 80%, 60%, 0)');
                grad.addColorStop(0.3, 'hsla(' + band.hue1 + ', 80%, 60%, ' + (band.alpha * baseAlpha) + ')');
                grad.addColorStop(0.6, 'hsla(' + band.hue2 + ', 85%, 65%, ' + (band.alpha * baseAlpha) + ')');
                grad.addColorStop(1,   'hsla(' + band.hue2 + ', 80%, 60%, 0)');
                bctx.fillStyle = grad;
                bctx.beginPath();
                bctx.moveTo(0, baseY - band.thick / 2);
                var step = 24;
                for (var x = 0; x <= bw; x += step) {
                    var y = baseY
                        + Math.sin(x * 0.005 + band.phase) * band.amp
                        + Math.sin(x * 0.013 + band.phase * 1.7) * band.amp * 0.4;
                    bctx.lineTo(x, y - band.thick / 2);
                }
                for (var x2 = bw; x2 >= 0; x2 -= step) {
                    var y2 = baseY
                        + Math.sin(x2 * 0.005 + band.phase) * band.amp
                        + Math.sin(x2 * 0.013 + band.phase * 1.7) * band.amp * 0.4;
                    bctx.lineTo(x2, y2 + band.thick / 2);
                }
                bctx.closePath();
                bctx.fill();
            });
        }

        function bgLoop(time) {
            var dt = Math.min((time - lastTime) / 1000, 0.05);
            lastTime = time;
            bctx.clearRect(0, 0, bw, bh);
            if (mode === 'petals')       drawPetals(dt);
            else if (mode === 'matrix')  drawMatrix(dt);
            else if (mode === 'orbs')    drawOrbs(dt);
            else if (mode === 'stars')   drawStars(dt);
            else if (mode === 'snow')    drawSnow(dt);
            else if (mode === 'cyber')   drawCyber(dt);
            else if (mode === 'aurora')  drawAurora(dt);
            requestAnimationFrame(bgLoop);
        }

        var bgObserver = new MutationObserver(function (muts) {
            muts.forEach(function (m) {
                if (m.attributeName === 'data-style' || m.attributeName === 'data-theme') {
                    setupBgMode();
                }
            });
        });
        bgObserver.observe(document.documentElement, { attributes: true });

        window.addEventListener('resize', resizeBg);
        resizeBg();
        lastTime = performance.now();
        requestAnimationFrame(bgLoop);
    }

    // ============================================================
    // 快捷键
    // ============================================================
    var shortcutHelpEl = null;

    function toggleShortcutHelp() {
        if (shortcutHelpEl) {
            shortcutHelpEl.remove();
            shortcutHelpEl = null;
            return;
        }

        var box = document.createElement('div');
        box.className = 'rel-shortcut-help';
        box.innerHTML = ''
            + '<div class="rel-shortcut-title">快捷键 <span class="rel-shortcut-close-x" data-close>✕</span></div>'
            + '<div class="rel-shortcut-row"><kbd>S</kbd><span>聚焦搜索框</span></div>'
            + '<div class="rel-shortcut-row"><kbd>Esc</kbd><span>取消选中</span></div>'
            + '<div class="rel-shortcut-row"><kbd>+</kbd> / <kbd>-</kbd><span>放大 / 缩小</span></div>'
            + '<div class="rel-shortcut-row"><kbd>R</kbd><span>重置视图</span></div>'
            + '<div class="rel-shortcut-row"><kbd>H</kbd> / <kbd>?</kbd><span>显示 / 隐藏本面板</span></div>';

        document.body.appendChild(box);
        shortcutHelpEl = box;

        var closeBtn = box.querySelector('[data-close]');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                if (shortcutHelpEl) {
                    shortcutHelpEl.remove();
                    shortcutHelpEl = null;
                }
            });
        }
    }

    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function (e) {
            if (e.metaKey || e.ctrlKey || e.altKey) return;

            var tag = (e.target && e.target.tagName ? e.target.tagName : '').toLowerCase();
            var isEditable = tag === 'input' || tag === 'textarea' || tag === 'select' || (e.target && e.target.isContentEditable);

            if (e.key === 'Escape') {
                if (isEditable && e.target.blur) e.target.blur();
                state.selected = null;
                state.searchFocus = null;
                state.viewTarget = null;
                state.selectedNodes.clear();
                renderSidebar(null);
                return;
            }

            if (isEditable) return;

            switch (e.key) {
                case 's':
                case 'S':
                    e.preventDefault();
                    var searchInput = document.getElementById('rel-search');
                    if (searchInput) searchInput.focus();
                    break;

                case '+':
                case '=':
                    e.preventDefault();
                    state.view.scale = Math.min(3, state.view.scale * 1.2);
                    break;

                case '-':
                case '_':
                    e.preventDefault();
                    state.view.scale = Math.max(0.2, state.view.scale / 1.2);
                    break;

                case 'r':
                case 'R':
                    e.preventDefault();
                    state.view = { x: 0, y: 0, scale: 0.7 };
                    break;

                case 'h':
                case 'H':
                case '?':
                    e.preventDefault();
                    toggleShortcutHelp();
                    break;
            }
        });

        var helpBtn = document.getElementById('rel-help-btn');
        if (helpBtn) {
            helpBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                toggleShortcutHelp();
            });
        }
    }

    var SHORTCUT_SEEN_KEY = 'rel_shortcuts_seen';

    function maybeShowShortcutHelp() {
        var seen = false;
        try { seen = localStorage.getItem(SHORTCUT_SEEN_KEY) === '1'; } catch (e) {}
        if (seen) return;

        setTimeout(function () {
            toggleShortcutHelp();
            try { localStorage.setItem(SHORTCUT_SEEN_KEY, '1'); } catch (e) {}

            var autoHideTimer = setTimeout(function () {
                if (shortcutHelpEl) {
                    shortcutHelpEl.remove();
                    shortcutHelpEl = null;
                }
            }, 6000);

            var observer = new MutationObserver(function () {
                if (!shortcutHelpEl) {
                    clearTimeout(autoHideTimer);
                    observer.disconnect();
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }, 800);
    }

    // ============================================================
    // 启动
    // ============================================================
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    var themeBtn = document.getElementById('theme-toggle');
    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            var cur = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
            var next = cur === 'dark' ? 'light' : 'dark';
            document.documentElement.dataset.theme = next;
            try { localStorage.setItem('mb_theme', next); } catch (e) {}
        });
    }

    initStylePicker();
    initBgCanvas();
    initKeyboardShortcuts();
    load();
})();