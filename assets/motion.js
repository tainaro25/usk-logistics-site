(function () {
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // header gets a stronger blur once the page is scrolled
  var hdr = document.querySelector(".site-header");
  if (hdr) {
    var onScroll = function () { hdr.classList.toggle("is-scrolled", window.scrollY > 12); };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }

  // staggered reveal for common blocks (separate from the legacy .reveal)
  var SEL = [
    ".section-head", ".step-card", ".price-card", ".feature", ".contact-card", ".calc-card",
    ".faq-item", ".cta-band", ".news-card", ".price-table-wrap", ".routes-wrap",
    ".prose > *", ".account-panel", ".account-sidebar", ".shops-controls"
  ].join(",");

  var io = "IntersectionObserver" in window ? new IntersectionObserver(function (es) {
    es.forEach(function (e) {
      if (e.isIntersecting) { e.target.classList.add("rv-in"); io.unobserve(e.target); }
    });
  }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" }) : null;

  function tag(root) {
    if (!io || reduce) return;
    root.querySelectorAll(SEL).forEach(function (el) {
      if (el.classList.contains("rv") || el.closest(".modal-overlay") || el.closest("[hidden]")) return;
      if (el.classList.contains("reveal")) return; // legacy system already handles it
      var sibs = el.parentElement ? Array.prototype.indexOf.call(el.parentElement.children, el) : 0;
      el.style.transitionDelay = Math.min(sibs, 6) * 70 + "ms";
      el.classList.add("rv");
      io.observe(el);
    });
  }
  tag(document);

  // dynamic content (shops grid, orders, ...) - spotlight only, no reveal
  document.addEventListener("pointermove", function (e) {
    var c = e.target.closest && e.target.closest(".step-card, .price-card, .feature, .contact-card, .news-card, .shop-card");
    if (!c) return;
    var r = c.getBoundingClientRect();
    c.style.setProperty("--mx", e.clientX - r.left + "px");
    c.style.setProperty("--my", e.clientY - r.top + "px");
  }, { passive: true });

  // soft tilt on price cards (pointer devices only)
  if (!reduce && window.matchMedia("(hover: hover)").matches) {
    document.querySelectorAll(".price-card").forEach(function (c) {
      c.addEventListener("pointermove", function (e) {
        var r = c.getBoundingClientRect();
        var px = (e.clientX - r.left) / r.width - 0.5, py = (e.clientY - r.top) / r.height - 0.5;
        c.style.transform = "perspective(900px) rotateY(" + px * 8 + "deg) rotateX(" + -py * 8 + "deg) translateY(-4px)";
      });
      c.addEventListener("pointerleave", function () { c.style.transform = ""; });
    });
  }

  // hero ambient canvas: twinkling dots + drifting light packets
  var hero = document.querySelector(".hero-animated");
  if (hero && !reduce) {
    var bg = hero.querySelector(".hero-bg");
    if (bg && !bg.querySelector("svg")) {
      var cv = document.createElement("canvas");
      cv.id = "hero-glow";
      cv.setAttribute("aria-hidden", "true");
      bg.appendChild(cv);
      var ctx = cv.getContext("2d"), dpr = Math.min(window.devicePixelRatio || 1, 2), W = 0, H = 0, stars = [], orbs = [];
      var accent = function () { return getComputedStyle(document.documentElement).getPropertyValue("--accent").trim() || "#3d7bff"; };

      var resize = function () {
        W = cv.clientWidth; H = cv.clientHeight;
        cv.width = W * dpr; cv.height = H * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        stars = []; for (var i = 0; i < Math.min(90, W / 12); i++) stars.push([Math.random() * W, Math.random() * H, Math.random() * 6.28, 0.6 + Math.random() * 1.2]);
        orbs = []; for (var j = 0; j < 7; j++) orbs.push({ x: Math.random() * W, y: Math.random() * H, v: 0.15 + Math.random() * 0.35, r: 1.2 + Math.random() * 1.6, p: Math.random() * 6.28 });
      };

      var t0 = performance.now(), running = true;
      var frame = function (now) {
        if (!running) return;
        var t = (now - t0) / 1000;
        ctx.clearRect(0, 0, W, H);
        var dark = document.documentElement.getAttribute("data-theme") === "dark";
        stars.forEach(function (s) {
          var a = 0.1 + 0.18 * (0.5 + 0.5 * Math.sin(t * s[3] + s[2]));
          ctx.fillStyle = dark ? "rgba(200,215,255," + a + ")" : "rgba(40,70,160," + a * 0.8 + ")";
          ctx.fillRect(s[0], s[1], 1.4, 1.4);
        });
        var col = accent();
        orbs.forEach(function (o) {
          o.x += o.v; o.y += Math.sin(t * 0.6 + o.p) * 0.25;
          if (o.x > W + 40) { o.x = -40; o.y = Math.random() * H; }
          var g = ctx.createRadialGradient(o.x, o.y, 0, o.x, o.y, 16);
          g.addColorStop(0, col); g.addColorStop(1, "transparent");
          ctx.globalAlpha = 0.5; ctx.fillStyle = g;
          ctx.beginPath(); ctx.arc(o.x, o.y, 16, 0, 6.28); ctx.fill();
          ctx.globalAlpha = 0.9; ctx.fillStyle = col;
          ctx.beginPath(); ctx.arc(o.x, o.y, o.r, 0, 6.28); ctx.fill();
          ctx.globalAlpha = 1;
        });
        requestAnimationFrame(frame);
      };

      resize();
      window.addEventListener("resize", resize);
      document.addEventListener("visibilitychange", function () {
        running = document.visibilityState === "visible";
        if (running) requestAnimationFrame(frame);
      });
      requestAnimationFrame(frame);
    }
  }
})();
