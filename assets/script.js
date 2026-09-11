function parseLocaleNumber(value) {
  return parseFloat(String(value || "").replace(",", "."));
}

document.addEventListener("DOMContentLoaded", () => {
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
          alert("Заполните, пожалуйста, имя и телефон полностью.");
          return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = "Отправка...";

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
              alert("Не получилось отправить заявку. Попробуйте написать нам в WhatsApp или Telegram.");
            }
          })
          .catch(() => {
            alert("Не получилось отправить заявку. Попробуйте написать нам в WhatsApp или Telegram.");
          })
          .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = "Отправить заявку";
          });
      });
    }
  }
});
