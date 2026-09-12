function parseLocaleNumber(value) {
  return parseFloat(String(value || "").replace(",", "."));
}

function initPressDepth() {
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  document.querySelectorAll(".press-depth").forEach((wrapper) => {
    const btn = wrapper.querySelector(":scope > .btn");
    if (!btn) return;

    let pointerId = null;

    const setPressed = (isPressed) => {
      wrapper.classList.toggle("is-pressed", isPressed);
    };

    const contains = (event) => {
      const r = btn.getBoundingClientRect();
      return (
        event.clientX >= r.left &&
        event.clientX <= r.right &&
        event.clientY >= r.top &&
        event.clientY <= r.bottom
      );
    };

    const onMove = (event) => {
      if (event.pointerId !== pointerId) return;
      setPressed(contains(event));
    };

    const stop = (event) => {
      if (event && event.pointerId !== pointerId) return;
      pointerId = null;
      setPressed(false);
      window.removeEventListener("pointermove", onMove);
      window.removeEventListener("pointerup", stop);
      window.removeEventListener("pointercancel", stop);
    };

    btn.addEventListener("pointerdown", (event) => {
      if (event.pointerType === "mouse" && event.button !== 0) return;

      if (!reduceMotion) {
        const r = btn.getBoundingClientRect();
        const rx = Math.max(-1, Math.min(1, ((event.clientY - r.top) / r.height) * 2 - 1));
        const ry = Math.max(-1, Math.min(1, ((event.clientX - r.left) / r.width) * 2 - 1));
        wrapper.style.setProperty("--press-rx", -rx * 6 + "deg");
        wrapper.style.setProperty("--press-ry", ry * 6 + "deg");
      }

      pointerId = event.pointerId;
      setPressed(true);
      window.addEventListener("pointermove", onMove);
      window.addEventListener("pointerup", stop);
      window.addEventListener("pointercancel", stop);
    });

    btn.addEventListener("keydown", (event) => {
      if (event.repeat) return;
      if (event.key === " " || event.key === "Enter") setPressed(true);
    });

    btn.addEventListener("keyup", (event) => {
      if (event.key === " " || event.key === "Enter" || event.key === "Escape") setPressed(false);
    });

    btn.addEventListener("blur", () => stop());
  });
}

document.addEventListener("DOMContentLoaded", () => {
  initPressDepth();

  const accountLinks = document.querySelectorAll('a[href="account-login.html"]');
  if (accountLinks.length) {
    fetch("/api/me.php")
      .then((r) => r.json())
      .then((data) => {
        if (data.ok) {
          accountLinks.forEach((link) => {
            link.href = "account-dashboard.html";
          });
        }
      })
      .catch(() => {});
  }

  document.querySelectorAll(".faq-question").forEach((btn) => {
    btn.addEventListener("click", () => {
      const item = btn.closest(".faq-item");
      const wasOpen = item.classList.contains("open");
      document.querySelectorAll(".faq-item.open").forEach((openItem) => {
        if (openItem !== item) openItem.classList.remove("open");
      });
      item.classList.toggle("open", !wasOpen);
    });
  });

  const toggle = document.querySelector(".menu-toggle");
  if (toggle) {
    toggle.addEventListener("click", () => {
      document.body.classList.toggle("nav-open");
    });
  }

  const calcOptions = document.querySelectorAll(".calc-option");
  const calcWeight = document.getElementById("calc-weight");
  const calcResult = document.getElementById("calc-result");
  if (calcOptions.length && calcWeight && calcResult) {
    let pricePerKg = parseFloat(
      document.querySelector(".calc-option.active")?.dataset.price || calcOptions[0].dataset.price
    );

    const updateResult = () => {
      const weight = parseLocaleNumber(calcWeight.value) || 0;
      const total = weight * pricePerKg;
      calcResult.textContent = "$" + total.toFixed(2);
    };

    calcOptions.forEach((btn) => {
      btn.addEventListener("click", () => {
        calcOptions.forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        pricePerKg = parseFloat(btn.dataset.price);
        updateResult();
      });
    });

    calcWeight.addEventListener("input", updateResult);
    updateResult();
  }

  const dimWidth = document.getElementById("dim-width");
  const dimLength = document.getElementById("dim-length");
  const dimHeight = document.getElementById("dim-height");
  const volumeResultBox = document.getElementById("volume-result");
  const volumeResultValue = document.getElementById("volume-result-value");
  const volumeAlert = document.getElementById("volume-alert");

  if (dimWidth && dimLength && dimHeight && volumeResultBox && volumeAlert) {
    const MAX_DIM_SUM = 120;
    const US_VOLUME_DIVISOR = 5000;

    const updateVolume = () => {
      const w = parseLocaleNumber(dimWidth.value) || 0;
      const l = parseLocaleNumber(dimLength.value) || 0;
      const h = parseLocaleNumber(dimHeight.value) || 0;

      if (!w || !l || !h) {
        volumeResultBox.hidden = false;
        volumeAlert.hidden = true;
        volumeResultValue.textContent = "—";
        return;
      }

      const sum = w + l + h;

      if (sum > MAX_DIM_SUM) {
        volumeResultBox.hidden = true;
        volumeAlert.hidden = false;
        return;
      }

      volumeResultBox.hidden = false;
      volumeAlert.hidden = true;
      const volumeWeight = (w * l * h) / US_VOLUME_DIVISOR;
      const kgLabel = document.documentElement.lang === "en" ? "kg" : "кг";
      volumeResultValue.textContent = volumeWeight.toFixed(2) + " " + kgLabel;
    };

    [dimWidth, dimLength, dimHeight].forEach((el) => {
      el.addEventListener("input", updateVolume);
    });
    updateVolume();
  }

  document.querySelectorAll(".step-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      const target = document.getElementById(btn.dataset.target);
      if (!target) return;
      const step = parseLocaleNumber(btn.dataset.step) || 0;
      const current = parseLocaleNumber(target.value) || 0;
      const next = Math.max(0, current + step);
      target.value = Number.isInteger(step) ? next.toString() : next.toFixed(1);
      target.dispatchEvent(new Event("input", { bubbles: true }));
    });
  });

  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    document.querySelectorAll(".routes animateMotion").forEach((el) => el.setAttribute("begin", "indefinite"));
  } else {
    document.querySelectorAll(".routes animateMotion").forEach((anim) => {
      anim.addEventListener("beginEvent", () => {
        const plane = anim.closest(".plane");
        if (plane) plane.classList.add("ready");
      });
    });
  }

  const revealEls = document.querySelectorAll(".reveal");
  if (revealEls.length) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add("in-view");
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15, rootMargin: "0px 0px -60px 0px" }
    );
    revealEls.forEach((el) => observer.observe(el));
  }

  function i18nText(key, fallback) {
    if (window.USK_I18N && window.USK_I18N.currentLang) {
      const dict = window.USK_I18N.dict[window.USK_I18N.currentLang] || {};
      if (dict[key]) return dict[key];
    }
    return fallback;
  }

  const prohibitedOverlay = document.getElementById("prohibited-modal-overlay");
  if (prohibitedOverlay) {
    const closeProhibited = () => {
      prohibitedOverlay.hidden = true;
    };

    document.addEventListener("click", (e) => {
      if (e.target.closest("[data-open-prohibited-modal]")) {
        e.preventDefault();
        prohibitedOverlay.hidden = false;
      }
    });

    const prohibitedCloseBtn = document.getElementById("prohibited-modal-close");
    if (prohibitedCloseBtn) prohibitedCloseBtn.addEventListener("click", closeProhibited);

    prohibitedOverlay.addEventListener("click", (e) => {
      if (e.target === prohibitedOverlay) closeProhibited();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && !prohibitedOverlay.hidden) closeProhibited();
    });
  }

  const modalOverlay = document.getElementById("request-modal-overlay");
  if (modalOverlay) {
    const openBtns = document.querySelectorAll("[data-open-request-modal]");
    const closeBtn = document.getElementById("request-modal-close");
    const submitBtn = document.getElementById("modal-submit");
    const intro = document.getElementById("modal-intro");
    const success = document.getElementById("modal-success");
    const nameInput = document.getElementById("modal-name");
    const linkInput = document.getElementById("modal-link");
    const phoneInput = document.getElementById("modal-phone");
    const prohibitedCheck = document.getElementById("modal-prohibited-check");

    const openModal = (e) => {
      if (e) e.preventDefault();
      modalOverlay.hidden = false;
      document.body.classList.add("modal-open");
    };

    const closeModal = () => {
      modalOverlay.hidden = true;
      document.body.classList.remove("modal-open");
    };

    openBtns.forEach((btn) => btn.addEventListener("click", openModal));
    if (closeBtn) closeBtn.addEventListener("click", closeModal);

    modalOverlay.addEventListener("click", (e) => {
      if (e.target === modalOverlay) closeModal();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && !modalOverlay.hidden) closeModal();
    });

    if (submitBtn) {
      submitBtn.addEventListener("click", () => {
        const name = nameInput.value.trim();
        const link = linkInput.value.trim();
        const phone = phoneInput.value.trim();

        if (!name || phone.replace(/\D/g, "").length < 10) {
          alert(i18nText("modal_err_fill_name_phone", "Please fill in your name and phone number completely."));
          return;
        }

        if (prohibitedCheck && !prohibitedCheck.checked) {
          alert(i18nText("modal_checkbox_error", "Please confirm that you've reviewed the list of prohibited items."));
          return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = i18nText("modal_sending_btn", "Sending...");

        const formData = new FormData();
        formData.append("name", name);
        formData.append("link", link);
        formData.append("phone", phone);

        fetch("/send-request.php", { method: "POST", body: formData })
          .then((r) => r.json())
          .then((data) => {
            if (data.ok) {
              intro.hidden = true;
              success.hidden = false;
            } else {
              alert(i18nText("modal_err_submit_fail", "Could not send the request. Please message us on WhatsApp or Telegram."));
            }
          })
          .catch(() => {
            alert(i18nText("modal_err_submit_fail", "Could not send the request. Please message us on WhatsApp or Telegram."));
          })
          .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = i18nText("modal_submit_btn", "Send Request");
          });
      });
    }
  }
});
