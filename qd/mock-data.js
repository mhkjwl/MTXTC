// PrismNest 个人中心 · 模拟图床数据
// 用于验证个人中心页面的显示效果，后续可替换为真实接口返回的数据。
window.PROFILE_MOCK = {
  // 用户基本信息
  user: {
    displayName: "林夕",
    username: "linxi.design",
    email: "linxi@prismnest.app",
    avatarText: "林夕",
    plan: "Pro",
    joinDate: "2024-03",
    userId: "A82F31",
    apiActive: true,
    bio: "专注品牌视觉素材管理与图床分发，喜欢把上传、压缩和外链流程调成顺手的样子。"
  },

  // 资料卡顶部 4 项指标
  stats: [
    { key: "uploads", label: "累计上传", display: "1,284" },
    { key: "galleries", label: "活跃图库", display: "38" },
    { key: "traffic", label: "本月流量", display: "562 GB" },
    { key: "external", label: "外链成功率", display: "99.4%" }
  ],

  // 当前订阅与用量
  subscription: {
    plan: "Pro 套餐",
    price: "¥99 / 月",
    renewDate: "2026-09-01",
    active: true,
    usage: [
      { key: "storage", label: "存储空间", used: 380, total: 500, display: "380 / 500 GB" },
      { key: "api", label: "API 调用", used: 18200, total: 50000, display: "18.2k / 50k" },
      { key: "bandwidth", label: "带宽流量", used: 562, total: 2000, display: "562 / 2000 GB" }
    ]
  },

  // 上传记录
  uploadRecords: [
    { name: "landing-banner.webp", format: "WEBP", size: "6.7 MB", dim: "2400×1600", gallery: "夏季活动", time: "2 分钟前", status: "synced", thumb: "a" },
    { name: "cover-summer-campaign.png", format: "PNG", size: "18.2 MB", dim: "3200×1800", gallery: "夏季活动", time: "1 小时前", status: "synced", thumb: "b" },
    { name: "poster-night-event.jpg", format: "JPG", size: "12.4 MB", dim: "3840×2160", gallery: "活动海报", time: "3 小时前", status: "processing", thumb: "c" },
    { name: "blog-cover.svg", format: "SVG", size: "0.4 MB", dim: "矢量可缩放", gallery: "博客素材", time: "昨天 18:20", status: "synced", thumb: "d" },
    { name: "brand-hero.webp", format: "WEBP", size: "8.1 MB", dim: "1920×1080", gallery: "品牌素材", time: "昨天 15:05", status: "synced", thumb: "a" },
    { name: "icon-pack.png", format: "PNG", size: "2.3 MB", dim: "1024×1024", gallery: "产品图", time: "2 天前", status: "failed", thumb: "c" }
  ],

  // 流量统计（最近 7 天）
  traffic: {
    period: "最近 7 天",
    bars: [42, 62, 56, 74, 68, 92, 84],
    labels: ["一", "二", "三", "四", "五", "六", "日"],
    footer: [
      { value: "562 GB", label: "本周流量" },
      { value: "23,891", label: "独立访问" },
      { value: "+18%", label: "周环比" }
    ]
  },

  // 我的动态
  activity: [
    { title: "上传了 12 张素材到「夏季活动」", meta: "2 小时前 · /campaign/summer" },
    { title: "更新了默认上传偏好", meta: "昨天 16:42 · 上传偏好" },
    { title: "为「品牌素材」图库开启了防盗链", meta: "2026-07-03 · /gallery/brand" },
    { title: "创建了新的 API Token", meta: "2026-06-28 · 自动化机器人" }
  ]
};
