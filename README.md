# MTXTC 聚合图床系统

> 基于 Eecms 内核构建的多源聚合图床平台，集成 48+ 主流图床接口，提供用户套餐、API 密钥、S3 存储与完整的后台管理能力。

![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-%3E%3D5.7-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-免费可商用-green)
![Version](https://img.shields.io/badge/Version-1.1.0--dev-orange)
![SoftReg](https://img.shields.io/badge/软著-2022SR0050653-blue)

---

## ✨ 项目特性

- **多源聚合上传**：内置 48+ 图床接口（360、ImgBB、Chevereto、S3、阿里、支付宝、美团、苏宁、本地存储等），可在后台自由启用与配置
- **套餐与权限体系**：支持自定义套餐（存储上限、有效天数、接口分组），用户订阅与到期自动回退默认套餐
- **兑换码系统**：批量生成兑换码，支持自定义天数、批次管理与过期控制
- **API 密钥鉴权**：基于 Bearer Token 的对外 API，密钥明文仅创建时返回一次，数据库仅存 SHA-256 哈希
- **接口分组**：将图床接口按分组组织，绑定到不同套餐，实现精细化权限控制
- **S3 兼容存储**：支持多个 S3 存储配置，作为自建图床上传后端
- **RESTful API**：`/api/v1/` 提供相册、图片、令牌、策略、上传等标准化接口
- **用户中心**：注册、登录、邮箱验证、头像、上传记录统计
- **管理后台**：基于 Bootstrap + AdminLTE，含仪表盘、用户、图片、套餐、兑换码、API 分组、API 密钥、S3、访问控制、系统设置
- **安全加固**：CSRF 防护、mysqli 预处理语句、安装锁、管理员操作日志、邮箱验证码
- **伪静态友好**：内置 Apache `.htaccess` 规则，附 Nginx 配置示例

---

## 🛠️ 技术栈

| 层级 | 技术 |
| --- | --- |
| 后端语言 | PHP ≥ 7.4（`declare(strict_types=1)`） |
| 数据库 | MySQL ≥ 5.7（InnoDB + utf8mb4） |
| 后台 UI | Bootstrap + AdminLTE |
| 前端 | 原生响应式 + Vue（部分页面） |
| 邮件 | SMTP（`inc/smtp_mailer.php`） |
| HTTP 鉴权 | Bearer Token（`Authorization: Bearer sk-<hash>`） |
| 证书 | 内置 CA 证书包（`inc/cacert.pem`，用于 HTTPS 图床请求） |

---

## 📁 目录结构

```
.
├── index.php              # 前台图床主入口（上传界面）
├── api_doc.php            # API 对接文档页面（动态展示当前用户可用接口）
├── config.php             # 数据库连接配置（占位模板，安装时填写）
├── router.php             # 路由分发
├── module.php             # 模块加载
├── header.php / footer.php
├── 404.php
├── .htaccess              # Apache 伪静态规则
│
├── admin/                 # 管理后台
│   ├── dashboard.php      #   仪表盘
│   ├── setting.php        #   系统设置
│   ├── users.php          #   用户管理
│   ├── images.php         #   图片管理
│   ├── packages.php      #   套餐管理
│   ├── redeem.php         #   兑换码管理
│   ├── apigroups.php     #   接口分组
│   ├── apikeys.php        #   API 密钥管理
│   ├── api.php            #   图床接口配置
│   ├── s3.php             #   S3 存储配置
│   ├── access.php         #   访问控制
│   ├── regconfig.php      #   注册配置
│   ├── upimg.php          #   后台上传
│   ├── user.php           #   个人资料
│   └── login.php          #   管理员登录
│
├── api/                   # 图床接口实现（48+）
│   ├── upload.php         #   统一上传入口
│   ├── api_upload.php     #   对外 API 上传（Bearer 鉴权）
│   ├── api_keys.php       #   API 密钥核心库
│   ├── s3.php             #   S3 上传实现
│   ├── stats.php          #   统计
│   ├── user_api.php       #   用户接口
│   ├── local.php          #   本地上传
│   ├── 360.php / imgbb.php / chevereto.php / ...  # 第三方图床
│   └── v1/                #   RESTful API（albums / images / tokens / strategies / upload / profile）
│
├── inc/                   # 核心类库
│   ├── common.php         #   公共引导（加载配置、Session、鉴权）
│   ├── db.class.php        #   数据库封装（mysqli 预处理）
│   ├── function.php        #   通用函数
│   ├── api_keys.php        #   API 密钥库（建表 / 生成 / 鉴权）
│   ├── package.php         #   套餐逻辑
│   ├── user_auth.php       #   用户鉴权
│   ├── security.php        #   安全工具
│   ├── smtp_mailer.php     #   邮件发送
│   ├── submit.php          #   提交处理
│   ├── lang.php            #   多语言
│   ├── love.php / member.php / sae.php
│   └── cacert.pem          #   CA 证书包
│
├── user/                  # 用户中心前台
│   ├── login.php / register.php / logout.php / index.php
│
├── bd/                    # 前端资源（axios / vue / qd）
│
└── install/               # 安装向导
    ├── index.php           #   安装入口（环境检测 + CSRF + 安装锁）
    ├── install.sql         #   数据库结构（16 张表）
    ├── db.class.php        #   安装期数据库操作
    └── readme.txt
```

---

## 🚀 快速开始

### 环境要求

- PHP **≥ 7.4**（建议 8.x），需开启扩展：`mysqli`、`mbstring`、`curl`、`gd` 或 `imagick`
- MySQL **≥ 5.7**（建议 5.7+ / 8.x，需 InnoDB + utf8mb4）
- Web 服务器：Apache（推荐，已内置 `.htaccess`）或 Nginx

### 安装步骤

1. **上传代码**：将本项目上传到网站根目录
2. **设置权限**：确保 `config.php` 与 `install/` 目录可写
3. **访问安装向导**：浏览器打开 `http://你的域名/install/`
4. **环境检测**：向导会自动检测 PHP 版本、扩展、目录权限
5. **填写数据库**：输入 MySQL 主机、端口、用户名、密码、库名
6. **创建管理员**：设置后台管理员账号与密码
7. **完成安装**：安装成功后会生成 `install/install.lock` 锁文件，防止重复安装
8. **登录后台**：访问 `http://你的域名/admin/`，默认账号在安装时设置
   - ⚠️ **安装后请立即修改默认密码，并删除或重命名 `install/` 目录**

### Nginx 伪静态配置

Apache 用户无需额外配置（已内置 `.htaccess`）。Nginx 用户请在 `server` 块中加入：

```nginx
rewrite ^/index.html$ /index.php;
rewrite ^/about.html$ /about.php;
rewrite ^/search.html$ /search.php;
rewrite ^/apply.html$ /apply.php;
rewrite ^/404.html$ /404.php;
rewrite ^/sort/([1-9]+[0-9]*).html$ /sort.php?id=$1;
rewrite ^/sort/([a-zA-Z]+).html$ /sort.php?alias=$1;
rewrite ^/site-([1-9]+[0-9]*).html$ /site.php?id=$1;
rewrite ^/([a-zA-Z]+).html$ /site.php?alias=$1;
```

---

## 🔑 API 使用

### 鉴权方式

所有对外 API 接口通过 **Bearer Token** 鉴权，在请求头中携带：

```
Authorization: Bearer sk-<your-api-key>
```

API 密钥格式为 `sk-` + 32 位十六进制字符串，可在 **用户中心 → API 密钥** 创建。明文密钥**仅在创建时显示一次**，数据库仅存 SHA-256 哈希，请妥善保存。

### 接口文档

访问 `http://你的域名/api_doc.php` 可查看完整的 API 对接文档。文档会根据当前登录身份（Session 或 Bearer Token）动态展示该用户套餐允许使用的图床接口列表。

### RESTful API（v1）

```
GET  /api/v1/profile        # 用户信息
GET  /api/v1/strategies     # 可用上传策略
POST /api/v1/tokens         # 创建上传令牌
POST /api/v1/upload         # 上传图片
GET  /api/v1/images         # 图片列表
GET  /api/v1/albums         # 相册列表
```

---

## 🗄️ 数据库概览

共 16 张表（前缀 `eecms_`，API 密钥表无前缀）：

| 表名 | 用途 |
| --- | --- |
| `eecms_config` | 系统配置（键值对） |
| `eecms_users` | 用户（含 `super_admin` / `user` 角色） |
| `eecms_images` | 图片上传记录 |
| `eecms_packages` | 套餐定义（存储上限、天数、接口分组） |
| `eecms_user_subs` | 用户订阅（到期时间、自定义存储） |
| `eecms_redeem_codes` | 兑换码（批次、目标套餐、自定义天数） |
| `eecms_api_groups` | 接口分组 |
| `eecms_api_group_items` | 分组下的图床接口（支持 S3） |
| `api_keys` | API 密钥（SHA-256 哈希存储） |
| `eecms_admin_logs` | 管理员操作日志 |
| `eecms_email_codes` | 邮箱验证码 |
| `eecms_nav` / `eecms_sort` / `eecms_list` / `eecms_apply` / `eecms_notice` / `eecms_love` | 导航、分类、列表、申请、公告、收藏 |

---

## 🔒 安全建议

1. **修改默认密码**：安装后立即在后台修改管理员密码
2. **删除安装目录**：安装完成后删除或重命名 `install/`（`install.lock` 已提供基础防护）
3. **配置 HTTPS**：生产环境强制启用 HTTPS，保护 Bearer Token 与用户会话
4. **限制后台访问**：建议通过 Web 服务器配置对 `/admin/` 做 IP 白名单或额外认证
5. **保护 `config.php`**：确保数据库密码不外泄，定期备份
6. **API 密钥保管**：明文密钥仅显示一次，丢失只能重新生成

---

## 📦 内置图床接口（48+）

| 类别 | 接口 |
| --- | --- |
| **自建存储** | 本地上传、自建图床（Cloudflare Bed）、S3 兼容存储 |
| **国际图床** | ImgBB、Imgur.LA、StarDots、Hello、Telegraph、Telegraph2、ImgURL |
| **国内图床** | 360、极速、狗狗、宝马、盘络、绿色、肥喵、131、FE、友藤、CZL、TUTU、悠悠、图屋、UR、云、云朵、奶昔、怡云、SCDN、Hello、Remit、WENTIAN |
| **聚合/中转** | 凌云、PHOTO、IMG520、IMGLOC、LOC、有、风筝、云间、TUTU |
| **电商图床** | 阿里巴巴、美团创作、苏宁易购、美拍网、支付宝、有赞 |
| **Chevereto** | PlcGO（兼容 Chevereto API） |

> 完整接口列表与各接口最大上传限制详见 `api_doc.php` 动态文档。

---

## 📜 授权声明

- **授权类型**：免费可商用（基于 Eecms 内核）
- **软著登记号**：2022SR0050653

您出于自愿使用本系统，需了解使用风险。本项目不提供任何形式的使用担保，也不承担因使用本系统而产生的任何相关责任。本项目不对使用本软件构建的网站中的任何信息内容，以及由此导致的版权纠纷和法律争议及后果承担责任。

---

## 🤝 贡献与反馈

- 发现 Bug 或有功能建议，欢迎提交 [Issue](https://github.com/mhkjwl/MTXTC/issues)
- 提交代码请发 Pull Request 到 `main` 分支

---

## 📌 仓库信息

- **GitHub**：[https://github.com/mhkjwl/MTXTC](https://github.com/mhkjwl/MTXTC)
- **默认分支**：`main`
- **版本**：1.1.0-dev（API 文档 1.3.0-dev）
