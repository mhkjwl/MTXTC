const progressBars = document.querySelectorAll(".queue-progress span");
const filterButtons = document.querySelectorAll(".filter-pill");
const galleryCards = document.querySelectorAll(".gallery-card");

progressBars.forEach((bar) => {
  const target = bar.dataset.progress || "0";
  bar.style.width = "0";

  window.requestAnimationFrame(() => {
    setTimeout(() => {
      bar.style.width = `${target}%`;
    }, 180);
  });
});

// 图库筛选
filterButtons.forEach((button) => {
  if (!button.dataset.filter) return;
  button.addEventListener("click", () => {
    filterButtons
      .filter((item) => item.dataset.filter)
      .forEach((item) => item.classList.remove("active"));
    button.classList.add("active");

    const currentFilter = button.dataset.filter;

    galleryCards.forEach((card) => {
      const matches = currentFilter === "全部" || card.dataset.category === currentFilter;
      card.classList.toggle("is-hidden", !matches);
    });
  });
});

// 侧边栏导航 active 联动
const sideNavLinks = document.querySelectorAll(".side-nav a");
sideNavLinks.forEach((link) => {
  link.addEventListener("click", () => {
    sideNavLinks.forEach((item) => item.classList.remove("active"));
    link.classList.add("active");
  });
});

// 个人中心：标签页切换
const profileTabButtons = document.querySelectorAll(".profile-tab-row .filter-pill");
const profilePanels = document.querySelectorAll(".profile-tabs .tab-panel");

profileTabButtons.forEach((button) => {
  button.addEventListener("click", () => {
    profileTabButtons.forEach((item) => item.classList.remove("active"));
    button.classList.add("active");

    const target = button.dataset.tab;
    profilePanels.forEach((panel) => {
      panel.classList.toggle("active", panel.dataset.panel === target);
    });
  });
});

// 个人中心：通知开关切换
const toggleSwitches = document.querySelectorAll(".toggle-switch");
toggleSwitches.forEach((toggle) => {
  toggle.addEventListener("click", () => {
    const isOn = toggle.classList.toggle("is-on");
    toggle.setAttribute("aria-checked", String(isOn));
  });
});
