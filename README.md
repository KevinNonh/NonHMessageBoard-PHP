# 留言板使用文档

一个基于原生 PHP + SQLite 的个人留言板，支持气泡弹幕、双视图切换、九种风格主题、前后台管理。

---

## 一、运行环境

### 最低要求

| 项目 | 要求 |
|---|---|
| PHP | 8.0 或更高 |
| PHP 扩展 | pdo_sqlite、mbstring、json |
| Web 服务器 | Nginx / Apache / 1Panel 等 |
| 数据库 | SQLite（文件型，无需安装） |
| 磁盘 | 10 MB 起步，随留言增长 |

### 检查 PHP 扩展

在服务器执行：

    php -m | grep -E 'pdo_sqlite|mbstring|json'

三个都出现即可。1Panel 等面板通常在「PHP 设置 → 扩展」里可以一键开启。

### 目录权限

storage/ 目录需要 Web 用户可写：

    chown -R www-data:www-data storage
    chmod -R 775 storage

www-data 换成你的实际 Web 用户。

---

## 二、安装与部署

### 目录结构

    message-board/
    ├── public/              # Web 根目录（必须指向这里）
    │   ├── index.php        # 前台入口
    │   ├── admin.php        # 后台入口
    │   ├── style.css
    │   └── app.js
    ├── includes/
    │   ├── bootstrap.php    # 启动、建表、配置加载
    │   ├── functions.php    # 公共函数
    │   └── views/           # 模板
    ├── config.php           # 站点配置
    └── storage/
        └── message_board.sqlite   # 数据库（自动生成）

### 部署步骤

1. 上传整个目录到服务器

2. Web 根目录指向 public/

   Nginx 示例：

       server {
           listen 80;
           server_name your-domain.com;
           root /path/to/message-board/public;
           index index.php;

           location / {
               try_files $uri $uri/ /index.php?$query_string;
           }

           location ~ \.php$ {
               include fastcgi_params;
               fastcgi_pass unix:/run/php/php8.2-fpm.sock;
               fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
           }
       }

   不想改 Web 根目录？在项目根目录放一个 index.php：

       <?php
       header('Location: public/index.php');
       exit;

3. 设置 storage/ 写权限

4. 访问前台：http://your-domain.com/

   首次访问会自动建表。数据库文件在 storage/message_board.sqlite。

5. 访问后台：http://your-domain.com/admin.php

   默认账号：admin / admin123

---

## 三、前台使用

### 顶栏按钮

| 图标 | 功能 |
|---|---|
| 🎨 | 切换风格主题 |
| 📋 / 🎈 | 在列表视图和气泡视图间切换 |
| 🌓 | 切换浅色 / 深色模式 |
| ⚙️ | 进入后台 |

### 气泡视图

- 每条留言是一个漂浮的气泡
- 拖动：按住气泡拖到任意位置，位置会被记住
- 点击展开：查看完整内容和管理员回复，再点收起
- √ 标记：右上角绿色勾表示管理员已回复该条
- 公告气泡：橙色渐变，固定位置，可拖动
- 加载更多：底部按钮，每次追加一批老留言

### 列表视图

- 经典卡片式布局，一条一条排下来
- 向下滚动自动加载下一批
- 显示完整内容和回复，无需点击展开
- 两种视图共享相同的风格背景

### 发表留言

点击右下角 ✏️ 打开表单：

| 字段 | 必填 | 说明 |
|---|---|---|
| 昵称 | 是 | 最长 30 字 |
| 邮箱 | 是 | 不会公开显示 |
| 网址 | 否 | 会显示在昵称旁边 |
| 内容 | 是 | 最长 1000 字 |
| 仅管理员可见 | 否 | 勾选后前台完全不可见 |

颜文字按钮：一排快捷按钮，点击插入到光标位置，例如 (- v -)、(＾▽＾)。

频率限制：同一 IP 默认 60 秒内只能发一条，防止灌水。

新留言高亮：提交成功后自动跳回前台，刚发的那条气泡 / 卡片会被蓝框高亮 3 秒，方便定位。

---

## 四、后台管理

### 登录

访问 /admin.php，输入管理员账号密码。

登录限流：同一 IP 5 分钟内尝试超过 10 次会被临时锁定。

### 留言列表

| 筛选 | 说明 |
|---|---|
| 全部 | 所有留言 |
| 未回复 | 没有管理员回复的 |
| 已回复 | 有管理员回复的 |
| 仅管理员可见 | 前台隐藏的留言 |

每页 20 条，底部翻页。每条留言可执行：

- 回复：填写后保存，前台气泡会出现 √ 标记
- 编辑：修改昵称、邮箱、网址、内容、可见性
- 删除：永久删除（无回收站，谨慎操作）

### 站点设置

从后台顶栏 ⚙️ 进入。

站点信息
- 站点名称：显示在浏览器标签和前台顶栏
- 站点图标：支持 Emoji（如 💬）或图片 URL
- 时区：影响新留言时间的显示

前台公告
- 显示为前台橙色公告气泡，留空则不显示
- 最长 500 字

页脚信息
- 显示为前台固定底部页脚
- 支持 HTML，可写版权、备案号、友情链接
- 示例：

      © 2026 我的留言板 ·
      <a href="https://beian.miit.gov.cn" target="_blank">京ICP备12345678号</a>

管理员账号
- 用户名可随时修改
- 修改密码需要填「当前密码」才能生效
- 密码最少 6 位

### 数据备份

后台顶栏 💾 或设置页底部，一键下载当前数据库。

下载前会自动做 WAL checkpoint，保证文件包含最新数据。

---

## 五、风格主题

前台顶栏 🎨 可切换。共 9 种风格，每种都有浅色 / 深色两个模式（🌓 按钮切换）。选择会保存在浏览器 localStorage，刷新不丢。

| 风格 | 视觉特征 | 背景动效 |
|---|---|---|
| 默认 | 简洁中性，蓝紫配色 | 缓慢漂移的光斑 |
| 可爱 | 粉色系，圆角更大 | 粉色花瓣旋转飘落 |
| 科技 | 冷蓝配色，方角 | 下落的代码字符雨 |
| 护眼绿 | 柔和绿色，低对比 | 绿色光斑呼吸漂移 |
| 星空 | 紫黑配色 | 星星闪烁 + 偶尔流星 |
| 雪夜 | 灰白天空色 | 雪花飘落（大雪花六角形） |
| 赛博朋克 | 紫粉青霓虹 | 霓虹光条从上方落下 |
| 极光 | 青绿 / 紫色 | 三层曲线光带缓慢流动 |
| 千禧年 | Y2K 复古，宋体，直角边框 | 无动效，经典网页风 |

千禧年风特殊说明：它不是单纯换色，而是整体视觉回退到 2000 年代初期的网页风格——宋体 / Times New Roman、border-radius: 0、按钮 3D 凸起、输入框凹陷、链接蓝色下划线。气泡上有个 ★ 前缀。

### 风格在哪些视图生效

- 气泡视图和列表视图共用同一套风格背景
- 切风格时粒子系统立即切换，不用刷新
- 深色 / 浅色模式下每种风格有不同的配色

---

## 六、数据迁移

### 完整迁移到新服务器

1. 旧站后台点一次 💾，下载数据库
2. 打包整个 message-board/ 目录，排除 storage/message_board.sqlite-wal 和 storage/message_board.sqlite-shm
3. 新服务器解压，Web 根目录指向 public/
4. 设置 storage/ 权限
5. 访问验证

因为代码里用 __DIR__ 相对路径，配置文件里没有硬编码域名，同一 PHP 环境下就是复制粘贴。

### 手动恢复数据库

1. 通过 1Panel / SSH 进入 storage/
2. 把当前 message_board.sqlite 改名为 message_board.old.sqlite
3. 删除 message_board.sqlite-wal 和 message_board.sqlite-shm
4. 上传备份文件，重命名为 message_board.sqlite
5. 刷新页面

---

## 七、配置项

config.php 里可调项：

    return [
        'site_name' => '留言板',           // 首次初始化用，之后在后台改
        'site_icon' => '💬',
        'timezone'  => 'Asia/Shanghai',

        'db_path' => __DIR__ . '/storage/message_board.sqlite',

        'admin_username' => 'admin',        // 首次初始化用
        'admin_password' => 'admin123',     // 首次初始化用
        'admin_password_hash' => '',

        'bubble_limit'  => 30,              // 前台首屏显示多少条
        'load_batch'    => 10,              // 点一次“加载更多”追加多少条
        'post_cooldown' => 60,              // 同一 IP 发言冷却（秒）
    ];

注意：admin_username 和 admin_password 只在数据库首次创建时使用一次。之后所有认证走数据库，在后台修改。建议首次部署后清空这两项为 ''，避免明文密码留在文件里。

---

## 八、按需使用脚本（public/按需使用/）

这个目录里的脚本用于调试、测试、应急，正常使用不需要。

危险等级说明：
- 🔴 高：必须在用完后立即删除，否则暴露在公网极其危险
- 🟡 中：可能被滥用，用完即删
- 🟢 低：相对安全，但也没有保留必要

| 文件 | 作用 | 危险等级 |
|---|---|---|
| debug.php | 输出 settings 表全部内容、管理员用户名、密码哈希长度，并验证 admin123 是否正确。 | 🔴 |
| reset.php | 把管理员用户名重置为 admin，密码重置为 admin123。忘记密码时用。 | 🔴 |
| seed.php | 插入 25 条固定内容的测试留言（user_agent = 'seed'）。 | 🟡 |
| seed_more.php | 插入自定义数量测试留言，访问 ?n=60 插 60 条（上限 500）。 | 🟡 |
| clear_seed.php | 删除 user_agent = 'seed' 的所有留言。 | 🟢 |
| clear_seed_more.php | 同 clear_seed.php，用于清理 seed_more.php 插入的数据。 | 🟢 |

推荐做法：
1. 把整个“按需使用”目录移到项目根目录外做备份
2. 需要时临时上传到 public/
3. 访问对应脚本
4. 用完立刻删除，不要留在线上

如果一定要保留在服务器上，至少在目录里加 .htaccess 或 Nginx 规则拒绝外部访问。

---

## 九、常见问题

Q：刷新后风格变回默认？
A：检查 includes/views/home.php 里 head 内联脚本的 allowed 白名单是否包含所有风格名。

Q：前台时间比北京时间少 8 小时？
A：后台 → 站点设置 → 时区，改成 Asia/Shanghai。旧留言时间不变，新留言生效。

Q：气泡全部堆在左上角？
A：检查 .bubble-field 是否可见、app.js 控制台是否报错。布局算法读到 0×0 容器时会跳过。

Q：留言弹窗点不开？
A：initModal 用的是 document + 捕获阶段委托，别改回绑在按钮上。看 Console 有无报错。

Q：403 Forbidden？
A：Web 根目录没指向 public/。要么改 Nginx / Apache 配置，要么在项目根目录放跳转 index.php。

Q：忘记管理员密码？
A：用按需使用目录里的 reset.php，用完立即删。

Q：气泡太多看不清？
A：调 config.php 里的 bubble_limit 和 load_batch，或改用列表视图。

Q：想清空所有留言？
A：用 SQLite 工具执行 DELETE FROM messages;，或后台逐条删。

Q：可以改回原来的默认样式吗？
A：清空浏览器 localStorage 里的 mb_style 键，或点 🎨 → 默认。

---

## 十、安全建议

- 定期备份数据库，尤其是加了重要留言后
- config.php 不要提交到公开仓库
- storage/ 目录不能被 Web 访问（Web 根指向 public/ 就安全）
- 不要用 admin / admin123 长期在线，部署后立刻改密码
- 公告和页脚支持 HTML，只写自己信任的内容
- 不开放注册，单管理员账号，登录限流已内置
- public/按需使用/ 目录不要留在线上

---

## 十一、许可与致谢

个人项目，按需使用。SQLite 由 PHP 内置支持，canvas 粒子由浏览器原生实现，无第三方依赖。

祝用得开心。