# 作品集网站（前台 + 后台）

## 已实现内容
- 前台 `Works`：顶部导航、长条 Banner、参考站式网格（4列/3列/2列自适应）、hover 10% 黑色蒙层 + 左上标题、点击打开 3/4 屏弹窗（三列信息 + 统一宽度媒体流）。
- 前台 `Introduction`：保持同一导航，三列排版，第一列顶部图片可配置。
- 后台 `Admin`：
  - 管理员登录（默认：`admin / admin123456`，支持后台修改）。
  - 作品创建/编辑/删除。
  - 作品信息三列输入（作品名/简介/时间）+ 标题样式编辑（字号/粗细/字体/颜色/下划线/斜体）。
  - 详情媒体多文件上传（`mp4/gif/png/jpg/jpeg/webm`）并按顺序展示。
  - 封面上传（前台方形裁切显示）。
  - “强调”开关，强调作品优先进入首页大正方形位。
  - Introduction 内容与样式编辑 + 第一列顶部图片上传。
  - 顶部菜单（logo/works/introduction）与 Banner（比例 + 顶部 PNG）编辑。

## 项目结构
- `public/index.php`：Works 首页
- `public/introduction.php`：Introduction 页面
- `public/login.php` / `public/admin.php`：后台登录与管理
- `public/bootstrap.php`：JSON 数据读写、鉴权、上传、通用函数
- `public/assets/style.css` / `public/assets/app.js`：样式与交互
- `data/site-content.json`：站点数据（设置/分类/作品/媒体/简介/管理员/日志）
- `public/uploads/*`：上传资源目录

## 本地启动
当前代码为 `PHP + JSON`（无数据库依赖），需要本机有 PHP 8+。

```bash
cd /Users/diu/Desktop/portfolio-site
./scripts/start-local.sh
```

可选端口（例如 9000）：

```bash
./scripts/start-local.sh 9000
```

打开：
- 前台：`http://127.0.0.1:8080/index.php`
- Introduction：`http://127.0.0.1:8080/introduction.php`
- 后台：`http://127.0.0.1:8080/login.php`
