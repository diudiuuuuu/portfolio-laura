(() => {
  const isWorksHome = Boolean(document.querySelector(".works-grid")) && !document.querySelector(".admin-wrap");
  if (isWorksHome) {
    document.body.classList.add("works-media-protect");

    const protectedMediaSelector = ".banner img, .banner video, .works-grid img, .works-grid video, .modal-gallery img, .modal-gallery video";

    // Lightweight deterrent for casual downloading from the homepage.
    document.addEventListener("contextmenu", (e) => {
      if (!(e.target instanceof Element)) return;
      if (e.target.closest(protectedMediaSelector)) {
        e.preventDefault();
      }
    });

    document.addEventListener("dragstart", (e) => {
      if (!(e.target instanceof Element)) return;
      if (e.target.closest(protectedMediaSelector)) {
        e.preventDefault();
      }
    });

    document.querySelectorAll(protectedMediaSelector).forEach((el) => {
      if (el.tagName === "IMG") {
        el.setAttribute("draggable", "false");
      }
    });
  }

  const layer = document.querySelector("[data-modal-layer]");
  const modal = layer?.querySelector("[data-modal]");
  const closeBtn = layer?.querySelector("[data-close]");

  if (layer && modal) {
    function openModal(card) {
      const title = card.dataset.title || "";
      const desc = card.dataset.description || "";
      const meta = card.dataset.meta || "";
      const time = card.dataset.time || "";
      const modalSize = Number(card.dataset.modalSize || 28);
      const modalBg = card.dataset.modalBg === "white" ? "white" : "black";
      let media = [];
      try {
        media = JSON.parse(card.dataset.media || "[]");
      } catch (_) {
        media = [];
      }

      modal.querySelector("[data-m-title]").textContent = title;
      modal.querySelector("[data-m-desc]").textContent = desc;
      modal.querySelector("[data-m-time]").textContent = time;
      modal.querySelector("[data-m-meta]").textContent = meta;
      modal.style.setProperty("--modal-font-size", `${modalSize}px`);
      if (modalBg === "white") {
        modal.style.setProperty("--modal-bg", "#ffffff");
        modal.style.setProperty("--modal-fg", "#111111");
        modal.style.setProperty("--modal-border", "#cfcfcf");
        modal.style.setProperty("--modal-divider", "#b9b9b9");
        modal.style.setProperty("--modal-media-bg", "#f3f3f3");
        if (closeBtn) {
          closeBtn.style.color = "#111";
          closeBtn.style.background = "rgba(255,255,255,0.82)";
        }
      } else {
        modal.style.setProperty("--modal-bg", "#000000");
        modal.style.setProperty("--modal-fg", "#ffffff");
        modal.style.setProperty("--modal-border", "#2c2c2c");
        modal.style.setProperty("--modal-divider", "#777777");
        modal.style.setProperty("--modal-media-bg", "#111111");
        if (closeBtn) {
          closeBtn.style.color = "#fff";
          closeBtn.style.background = "rgba(0,0,0,0.75)";
        }
      }

      const gallery = modal.querySelector("[data-m-gallery]");
      gallery.innerHTML = "";
      media.forEach((item) => {
        const isVideo = item.media_type === "video";
        const el = document.createElement(isVideo ? "video" : "img");
        if (isVideo) {
          el.src = item.media_path;
          el.controls = true;
          el.preload = "metadata";
        } else {
          el.src = item.media_path;
          el.alt = title;
          el.loading = "lazy";
        }
        gallery.appendChild(el);
      });

      layer.classList.add("open");
      document.body.style.overflow = "hidden";
    }

    function closeModal() {
      layer.classList.remove("open");
      document.body.style.overflow = "";
    }

    document.querySelectorAll("[data-work-card]").forEach((card) => {
      card.addEventListener("click", () => openModal(card));
    });

    closeBtn?.addEventListener("click", closeModal);
    layer.addEventListener("click", (e) => {
      if (e.target === layer) closeModal();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && layer.classList.contains("open")) closeModal();
    });
  }

  const floatLayer = document.querySelector("[data-banner-float]");
  if (floatLayer) {
    const nodes = Array.from(floatLayer.querySelectorAll(".banner-float-item"));

    function layoutItems() {
      const rect = floatLayer.getBoundingClientRect();
      const n = nodes.length;
      if (!n || rect.width < 20 || rect.height < 20) return;

      const rows = Math.max(1, Math.round(Math.sqrt((n * rect.height) / rect.width)));
      const maxCols = Math.ceil(n / rows);
      const sizeByWidth = rect.width / (maxCols + 0.9);
      const sizeByHeight = rect.height / (rows + 0.35);
      const rawSize = Math.min(sizeByWidth, sizeByHeight) * 1.5;
      const size = Math.max(64, Math.min(rawSize, rect.width / maxCols, rect.height / rows));
      const gapY = rows > 0 ? Math.max(0, (rect.height - rows * size) / (rows + 1)) : 0;

      let cursor = 0;
      for (let row = 0; row < rows; row += 1) {
        const remaining = n - cursor;
        const rowsLeft = rows - row;
        const rowCount = Math.ceil(remaining / rowsLeft);
        const gapX = Math.max(0, (rect.width - rowCount * size) / (rowCount + 1));
        for (let col = 0; col < rowCount; col += 1) {
          const node = nodes[cursor];
          const x = gapX + col * (size + gapX);
          const y = gapY + row * (size + gapY);
          node.style.left = `${x}px`;
          node.style.top = `${y}px`;
          node.style.width = `${size}px`;
          node.style.height = `${size}px`;
          node.dataset.baseScale = "1";
          node.dataset.hoverAmp = "0.16";
          node.style.transform = "translate(0px,0px) scale(1)";
          node.style.zIndex = String(2 + row);
          cursor += 1;
        }
      }
    }

    layoutItems();
    window.addEventListener("resize", layoutItems);

    floatLayer.addEventListener("mousemove", (e) => {
      const rect = floatLayer.getBoundingClientRect();
      const mx = e.clientX - rect.left;
      const my = e.clientY - rect.top;
      const sigma = Math.max(140, Math.min(rect.width, rect.height) * 0.24);
      nodes.forEach((node) => {
        const scale = Number(node.dataset.baseScale || 1);
        const amp = Number(node.dataset.hoverAmp || 0.16);
        const x = parseFloat(node.style.left || "0") + parseFloat(node.style.width || "0") / 2;
        const y = parseFloat(node.style.top || "0") + parseFloat(node.style.height || "0") / 2;
        const dx = x - mx;
        const dy = y - my;
        const d = Math.hypot(dx, dy) || 1;
        const falloff = Math.exp(-(d * d) / (2 * sigma * sigma));
        const ripple = 52 * falloff;
        const tx = (dx / d) * ripple;
        const ty = (dy / d) * ripple;
        const bump = 1 + amp * falloff;
        node.style.transform = `translate(${tx}px, ${ty}px) scale(${scale * bump})`;
      });
    });
    floatLayer.addEventListener("mouseleave", () => {
      nodes.forEach((node) => {
        const scale = Number(node.dataset.baseScale || 1);
        node.style.transform = `translate(0px, 0px) scale(${scale})`;
      });
    });
  }

  const music = document.getElementById("site-music");
  const toggle = document.querySelector("[data-music-toggle]");
  if (music && toggle) {
    let playing = false;
    const syncState = () => {
      playing = !music.paused;
      toggle.textContent = playing ? "❚❚ MUSIC" : "▶ MUSIC";
    };
    const tryAutoPlay = () => {
      music.play().then(syncState).catch(() => {});
    };
    tryAutoPlay();
    window.addEventListener("load", tryAutoPlay, { once: true });
    window.addEventListener("pageshow", tryAutoPlay, { once: true });
    document.addEventListener("visibilitychange", () => {
      if (document.visibilityState === "visible" && music.paused) tryAutoPlay();
    });
    ["pointerdown", "touchstart", "keydown"].forEach((evt) => {
      window.addEventListener(
        evt,
        () => {
          if (music.paused) tryAutoPlay();
        },
        { once: true, passive: true }
      );
    });
    toggle.addEventListener("click", async () => {
      try {
        if (playing) {
          music.pause();
          syncState();
        } else {
          await music.play();
          syncState();
        }
      } catch (_) {}
    });
    music.addEventListener("play", syncState);
    music.addEventListener("pause", syncState);
  }

  document.querySelectorAll("[data-cover-tools]").forEach((wrap) => {
    const fileInput = wrap.parentElement?.querySelector('input[type="file"][name="cover_file"]');
    const openBtn = wrap.parentElement?.querySelector("[data-cover-open]");
    const applyBtn = wrap.querySelector("[data-cover-apply]");
    const closeBtns = wrap.querySelectorAll("[data-cover-close]");
    const previewImage = wrap.querySelector("[data-cover-preview]");
    const stage = wrap.querySelector("[data-cover-stage]");
    const frame = wrap.querySelector("[data-cover-frame]");
    const zoom = wrap.querySelector("[data-cover-zoom]");
    const x = wrap.querySelector("[data-cover-x]");
    const y = wrap.querySelector("[data-cover-y]");
    const bw = wrap.querySelector("[data-cover-border-width]");
    const bc = wrap.querySelector("[data-cover-border-color]");
    const zoomHidden = wrap.querySelector("[data-cover-zoom-hidden]");
    const xHidden = wrap.querySelector("[data-cover-x-hidden]");
    const yHidden = wrap.querySelector("[data-cover-y-hidden]");
    const bwHidden = wrap.querySelector("[data-cover-border-width-hidden]");
    const bcHidden = wrap.querySelector("[data-cover-border-color-hidden]");
    const cropXHidden = wrap.querySelector("[data-cover-crop-x-hidden]");
    const cropYHidden = wrap.querySelector("[data-cover-crop-y-hidden]");
    const cropSizeHidden = wrap.querySelector("[data-cover-crop-size-hidden]");
    const cropXPxHidden = wrap.querySelector("[data-cover-crop-x-px-hidden]");
    const cropYPxHidden = wrap.querySelector("[data-cover-crop-y-px-hidden]");
    const cropSizePxHidden = wrap.querySelector("[data-cover-crop-size-px-hidden]");
    const cropApplyHidden = wrap.querySelector("[data-cover-apply-hidden]");
    const preprocessedHidden = wrap.querySelector("[data-cover-preprocessed-hidden]");
    const currentCover = wrap.dataset.currentCover || "";
    const form = wrap.closest("form");
    if (!fileInput || !previewImage || !stage || !frame) return;

    function initPreviewGeometry(resetOffset = false) {
      naturalW = previewImage.naturalWidth || 0;
      naturalH = previewImage.naturalHeight || 0;
      if (!naturalW || !naturalH) return;
      if (resetOffset) {
        txPx = 0;
        tyPx = 0;
        if (x) x.value = "0";
        if (y) y.value = "0";
      }
      updateView();
    }

    function loadPreviewSource(url, resetOffset = false) {
      previewImage.onload = () => initPreviewGeometry(resetOffset);
      previewImage.src = url;
      // Cached images may already be complete before onload callback runs in some browsers.
      if (previewImage.complete) {
        initPreviewGeometry(resetOffset);
      }
    }

    const openModal = () => {
      if (!wrap.classList.contains("is-active") && currentCover) {
        const bust = currentCover.includes("?") ? "&" : "?";
        loadPreviewSource(`${currentCover}${bust}t=${Date.now()}`, true);
        wrap.classList.add("is-active");
      } else if (wrap.classList.contains("is-active")) {
        initPreviewGeometry(false);
      }
      wrap.classList.add("is-open");
    };
    const closeModal = () => wrap.classList.remove("is-open");
    const setCropIntent = (enabled) => {
      if (cropApplyHidden) cropApplyHidden.value = enabled ? "1" : "0";
    };
    openBtn?.addEventListener("click", openModal);
    closeBtns.forEach((btn) => btn.addEventListener("click", closeModal));
    applyBtn?.addEventListener("click", () => {
      setCropIntent(true);
    });

    let naturalW = 0;
    let naturalH = 0;
    let txPx = 0;
    let tyPx = 0;

    function getFrameSize() {
      const sRect = stage.getBoundingClientRect();
      return Math.min(sRect.width, sRect.height) * 0.78;
    }

    function fitScale() {
      if (!naturalW || !naturalH) return 1;
      const fSize = getFrameSize();
      return Math.max(fSize / naturalW, fSize / naturalH);
    }

    function clampTranslate(scaleNow) {
      if (!naturalW || !naturalH) return;
      const sRect = stage.getBoundingClientRect();
      const fSize = getFrameSize();
      const dispW = naturalW * fitScale() * scaleNow;
      const dispH = naturalH * fitScale() * scaleNow;
      const minTx = (fSize - dispW) / 2;
      const maxTx = (dispW - fSize) / 2;
      const minTy = (fSize - dispH) / 2;
      const maxTy = (dispH - fSize) / 2;
      txPx = Math.max(minTx, Math.min(maxTx, txPx));
      tyPx = Math.max(minTy, Math.min(maxTy, tyPx));
      if (dispW <= fSize) txPx = 0;
      if (dispH <= fSize) tyPx = 0;
      const txPct = (txPx / Math.max(1, sRect.width)) * 100;
      const tyPct = (tyPx / Math.max(1, sRect.height)) * 100;
      if (x) x.value = String(Math.max(-100, Math.min(100, txPct)));
      if (y) y.value = String(Math.max(-100, Math.min(100, tyPct)));
    }

    function updateView() {
      const scale = Number(zoom?.value || 1);
      const txPct = Number(x?.value || 0);
      const tyPct = Number(y?.value || 0);
      const border = Number(bw?.value || 0);
      const bColor = bc?.value || "#000000";
      txPx = (txPct / 100) * stage.getBoundingClientRect().width;
      tyPx = (tyPct / 100) * stage.getBoundingClientRect().height;
      clampTranslate(scale);
      const dispW = naturalW * fitScale() * scale;
      const dispH = naturalH * fitScale() * scale;
      previewImage.style.width = `${Math.max(1, dispW)}px`;
      previewImage.style.height = `${Math.max(1, dispH)}px`;
      previewImage.style.transform = `translate(calc(-50% + ${txPx}px), calc(-50% + ${tyPx}px))`;
      frame.style.border = `${border}px solid ${bColor}`;
      if (zoomHidden) zoomHidden.value = String(scale);
      if (xHidden) xHidden.value = String(x?.value || 0);
      if (yHidden) yHidden.value = String(y?.value || 0);
      if (bwHidden) bwHidden.value = String(border);
      if (bcHidden) bcHidden.value = bColor;

      if (naturalW > 0 && naturalH > 0) {
        const sRect = stage.getBoundingClientRect();
        const fSize = getFrameSize();
        const frameLeft = (sRect.width - fSize) / 2;
        const frameTop = (sRect.height - fSize) / 2;
        const dispW2 = naturalW * fitScale() * scale;
        const dispH2 = naturalH * fitScale() * scale;
        const imgLeft = sRect.width / 2 - dispW2 / 2 + txPx;
        const imgTop = sRect.height / 2 - dispH2 / 2 + tyPx;
        const srcX = ((frameLeft - imgLeft) / dispW2) * naturalW;
        const srcY = ((frameTop - imgTop) / dispH2) * naturalH;
        const srcSizeW = (fSize / dispW2) * naturalW;
        const srcSizeH = (fSize / dispH2) * naturalH;
        const srcSize = Math.min(srcSizeW, srcSizeH);
        const nx = Math.max(0, Math.min(1, srcX / naturalW));
        const ny = Math.max(0, Math.min(1, srcY / naturalH));
        const ns = Math.max(0.01, Math.min(1, srcSize / Math.min(naturalW, naturalH)));
        if (cropXHidden) cropXHidden.value = String(nx);
        if (cropYHidden) cropYHidden.value = String(ny);
        if (cropSizeHidden) cropSizeHidden.value = String(ns);
        if (cropXPxHidden) cropXPxHidden.value = String(Math.max(0, srcX));
        if (cropYPxHidden) cropYPxHidden.value = String(Math.max(0, srcY));
        if (cropSizePxHidden) cropSizePxHidden.value = String(Math.max(1, srcSize));
      }
    }

    [zoom, x, y, bw, bc].forEach((el) => {
      if (!el) return;
      el.addEventListener("input", updateView);
      el.addEventListener("change", updateView);
    });

    fileInput.addEventListener("change", () => {
      const f = fileInput.files?.[0];
      if (!f) return;
      setCropIntent(false);
      if (preprocessedHidden) preprocessedHidden.value = "0";
      const isImage = /^image\//.test(f.type) || /\.(png|jpe?g|gif)$/i.test(f.name);
      if (!isImage) {
        wrap.classList.remove("is-active");
        previewImage.removeAttribute("src");
        return;
      }
      const url = URL.createObjectURL(f);
      previewImage.onload = () => {
        initPreviewGeometry(true);
        URL.revokeObjectURL(url);
      };
      previewImage.src = url;
      wrap.classList.add("is-active");
      openModal();
      if (previewImage.complete) {
        initPreviewGeometry(true);
        URL.revokeObjectURL(url);
      }
    });

    let dragging = false;
    let lastX = 0;
    let lastY = 0;
    const dragTarget = stage;

    function updateOffsetByDrag(clientX, clientY) {
      const dx = clientX - lastX;
      const dy = clientY - lastY;
      lastX = clientX;
      lastY = clientY;
      txPx += dx;
      tyPx += dy;
      const sRect = stage.getBoundingClientRect();
      if (x) x.value = String((txPx / Math.max(1, sRect.width)) * 100);
      if (y) y.value = String((tyPx / Math.max(1, sRect.height)) * 100);
      updateView();
    }

    dragTarget.addEventListener("pointerdown", (e) => {
      if (!wrap.classList.contains("is-active")) return;
      if (e.pointerType === "mouse" && e.button !== 0) return;
      dragging = true;
      lastX = e.clientX;
      lastY = e.clientY;
      dragTarget.setPointerCapture?.(e.pointerId);
      e.preventDefault();
    });
    dragTarget.addEventListener("pointermove", (e) => {
      if (!dragging) return;
      updateOffsetByDrag(e.clientX, e.clientY);
    });
    dragTarget.addEventListener("pointerup", () => {
      dragging = false;
    });
    dragTarget.addEventListener("pointercancel", () => {
      dragging = false;
    });

    form?.addEventListener("submit", (e) => {
      const submitter = e.submitter;
      const isCoverApplySubmit = submitter instanceof HTMLButtonElement && submitter.name === "cover_apply_submit";
      const hasCropIntent = isCoverApplySubmit || (cropApplyHidden?.value || "0") === "1";
      if (!hasCropIntent) {
        setCropIntent(false);
        return;
      }

      // Let backend handle crop/save reliably to avoid async client-side submit dead-ends.
      if (wrap.classList.contains("is-active")) {
        initPreviewGeometry(false);
        updateView();
      }
      setCropIntent(true);
      if (preprocessedHidden) preprocessedHidden.value = "0";
      closeModal();
    });
  });

  async function renderCroppedCoverFile(src, srcX, srcY, srcSize, border, borderColor) {
    const img = new Image();
    img.decoding = "async";
    img.src = src;
    if (!img.complete) {
      await new Promise((resolve, reject) => {
        img.onload = resolve;
        img.onerror = reject;
      });
    }
    const w = img.naturalWidth || 0;
    const h = img.naturalHeight || 0;
    if (!w || !h) return null;
    const x = Math.max(0, Math.min(w - 1, srcX));
    const y = Math.max(0, Math.min(h - 1, srcY));
    const size = Math.max(1, Math.min(Math.min(w - x, h - y), srcSize));
    const canvasSize = 1200;
    const b = Math.max(0, Math.min(120, border));
    const inner = Math.max(1, canvasSize - 2 * b);
    const canvas = document.createElement("canvas");
    canvas.width = canvasSize;
    canvas.height = canvasSize;
    const ctx = canvas.getContext("2d");
    if (!ctx) return null;
    ctx.fillStyle = borderColor || "#000000";
    ctx.fillRect(0, 0, canvasSize, canvasSize);
    ctx.drawImage(img, x, y, size, size, b, b, inner, inner);
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, "image/jpeg", 0.92));
    if (!blob) return null;
    return new File([blob], `cover-${Date.now()}.jpg`, { type: "image/jpeg" });
  }

  const adminFeedback = document.querySelector("[data-admin-feedback]");
  if (adminFeedback) {
    adminFeedback.querySelector("[data-admin-feedback-close]")?.addEventListener("click", () => {
      adminFeedback.classList.remove("open");
    });
    adminFeedback.addEventListener("click", (e) => {
      if (e.target === adminFeedback) adminFeedback.classList.remove("open");
    });
  }

  if (document.querySelector(".admin-wrap")) {
    const key = "portfolio_admin_scroll_y";
    const saved = sessionStorage.getItem(key);
    if (saved !== null) {
      const y = Number(saved);
      if (!Number.isNaN(y)) {
        window.scrollTo({ top: y, behavior: "auto" });
      }
      sessionStorage.removeItem(key);
    }
    document.querySelectorAll(".admin-wrap form").forEach((form) => {
      form.addEventListener("submit", () => {
        sessionStorage.setItem(key, String(window.scrollY || 0));
      });
    });
  }
})();
