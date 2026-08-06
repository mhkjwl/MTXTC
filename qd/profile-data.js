// 个人中心渲染脚本：将 mock-data 填充到页面，验证显示效果。
// 数据缺失或脚本异常时不影响原有静态内容（渐进增强）。
(function () {
  const data = window.PROFILE_MOCK;
  if (!data) return;

  const statusMap = {
    synced: { text: "已同步", cls: "status-synced" },
    processing: { text: "转码中", cls: "status-processing" },
    failed: { text: "转码失败", cls: "status-failed" }
  };

  // 1) 资料卡顶部 4 项指标
  data.stats.forEach((stat) => {
    const node = document.querySelector(`[data-stat="${stat.key}"]`);
    if (!node) return;
    const strong = node.querySelector("strong");
    const span = node.querySelector("span");
    if (strong) strong.textContent = stat.display;
    if (span) span.textContent = stat.label;
  });

  // 2) 当前订阅用量与进度条
  data.subscription.usage.forEach((item) => {
    const row = document.querySelector(`[data-usage="${item.key}"]`);
    if (!row) return;
    const top = row.querySelector(".usage-top");
    if (top) {
      const label = top.querySelector("span");
      const value = top.querySelector("b");
      if (label) label.textContent = item.label;
      if (value) value.textContent = item.display;
    }
    const meter = row.querySelector(".meter span");
    if (meter) {
      const percent = Math.round((item.used / item.total) * 100);
      meter.style.width = `${percent}%`;
    }
  });

  // 3) 上传记录
  const recordList = document.getElementById("record-list");
  if (recordList) {
    recordList.innerHTML = data.uploadRecords
      .map((r) => {
        const s = statusMap[r.status] || statusMap.synced;
        return `
          <div class="record-item">
            <div class="record-thumb thumb-${r.thumb}"></div>
            <div class="record-main">
              <strong>${r.name}</strong>
              <span>${r.format} · ${r.dim} · ${r.size}</span>
            </div>
            <span class="record-tag">${r.gallery}</span>
            <span class="record-time">${r.time}</span>
            <span class="record-status ${s.cls}">${s.text}</span>
          </div>`;
      })
      .join("");
  }

  // 4) 流量统计图表
  const chart = document.getElementById("traffic-chart");
  if (chart) {
    const bars = chart.querySelector(".chart-bars");
    const labels = chart.querySelector(".chart-labels");
    const footer = chart.querySelector(".insight-footer");
    if (bars) {
      bars.innerHTML = data.traffic.bars
        .map((h) => `<span style="height: ${h}%"></span>`)
        .join("");
    }
    if (labels) {
      labels.innerHTML = data.traffic.labels
        .map((l) => `<span>${l}</span>`)
        .join("");
    }
    if (footer) {
      footer.innerHTML = data.traffic.footer
        .map((f) => `<div><strong>${f.value}</strong><span>${f.label}</span></div>`)
        .join("");
    }
  }

  // 5) 我的动态时间线
  const activity = document.getElementById("activity-list");
  if (activity) {
    activity.innerHTML = data.activity
      .map(
        (a) => `
          <div class="timeline-item">
            <strong>${a.title}</strong>
            <span>${a.meta}</span>
          </div>`
      )
      .join("");
  }
})();
