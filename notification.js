(function () {
  if (window.pqNotify) return;

  const style = document.createElement("style");
  style.textContent = `
        .pq-global-toast {
            position: fixed; right: 24px; bottom: 24px; z-index: 999999;
            display: flex; align-items: flex-start; gap: 11px;
            min-width: 300px; max-width: 430px; padding: 15px 17px;
            border-radius: 16px; color: #fff;
            box-shadow: 0 14px 34px rgba(15,23,42,.22);
            font: 700 13px/1.45 Inter, Arial, sans-serif;
            transform: translateY(24px); opacity: 0; pointer-events: none;
            transition: .25s ease;
        }
        .pq-global-toast.show { transform: translateY(0); opacity: 1; pointer-events: auto; }
        .pq-global-toast.success { background: linear-gradient(135deg,#15803d,#22c55e); }
        .pq-global-toast.error { background: linear-gradient(135deg,#be123c,#ef4444); }
        .pq-global-toast.info { background: linear-gradient(135deg,#2563eb,#6366f1); }
        .pq-global-toast i { font-size: 18px; margin-top: 1px; }
        .pq-global-toast span { flex: 1; white-space: pre-line; }
        .pq-global-toast button {
            border: 0; background: transparent; color: rgba(255,255,255,.8);
            cursor: pointer; font-size: 18px; line-height: 1;
        }
        .pq-confirm-backdrop {
            position: fixed; inset: 0; z-index: 1000000; display: flex;
            align-items: center; justify-content: center; padding: 20px;
            background: rgba(15,23,42,.5); backdrop-filter: blur(4px);
        }
        .pq-confirm-card {
            width: min(100%, 430px); padding: 25px; border-radius: 22px;
            background: #fff; box-shadow: 0 24px 60px rgba(15,23,42,.25);
            font-family: Inter, Arial, sans-serif;
            animation: pqConfirmIn .2s ease-out;
        }
        .pq-confirm-icon {
            width: 48px; height: 48px; display: flex; align-items: center;
            justify-content: center; margin-bottom: 14px; border-radius: 15px;
            color: #be123c; background: #fff1f2; font-size: 20px;
        }
        .pq-confirm-card h3 { margin: 0 0 7px; color: #1a202c; font-size: 19px; }
        .pq-confirm-card p { margin: 0 0 21px; color: #718096; font-size: 13px; line-height: 1.55; white-space: pre-line; }
        .pq-confirm-actions { display: flex; justify-content: flex-end; gap: 10px; }
        .pq-confirm-actions button {
            border: 0; border-radius: 11px; padding: 10px 15px;
            cursor: pointer; font: 800 12px Inter, Arial, sans-serif;
        }
        .pq-confirm-cancel { color: #475569; background: #f1f5f9; }
        .pq-confirm-approve { color: #fff; background: #e11d48; }
        .pq-confirm-approve:hover { background: #be123c; }
        @keyframes pqConfirmIn { from { opacity: 0; transform: translateY(8px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @media (max-width: 520px) {
            .pq-global-toast { left: 16px; right: 16px; min-width: 0; bottom: 16px; }
        }
    `;
  const styleRoot = document.head || document.documentElement;
  if (styleRoot) styleRoot.appendChild(style);

  let timer;
  function getToast() {
    let toast = document.getElementById("pqGlobalToast");
    if (toast) return toast;
    toast = document.createElement("div");
    toast.id = "pqGlobalToast";
    toast.className = "pq-global-toast";
    toast.setAttribute("role", "status");
    toast.setAttribute("aria-live", "polite");
    toast.innerHTML =
      '<i></i><span></span><button type="button" aria-label="Close notification">&times;</button>';
    toast.querySelector("button").onclick = () =>
      toast.classList.remove("show");
    const toastRoot = document.body || document.documentElement;
    if (toastRoot) toastRoot.appendChild(toast);
    return toast;
  }

  window.pqNotify = function (message, type) {
    const toast = getToast();
    const normalized =
      type ||
      (/✅|success|secured|saved/i.test(String(message)) ? "success" : "error");
    const icon =
      normalized === "success"
        ? "fa-circle-check"
        : normalized === "info"
          ? "fa-circle-info"
          : "fa-circle-exclamation";
    toast.className = `pq-global-toast ${normalized} show`;
    toast.querySelector("i").className = `fa-solid ${icon}`;
    toast.querySelector("span").textContent = String(message).replace(
      /^[✅❌]\s*/,
      "",
    );
    clearTimeout(timer);
    timer = setTimeout(() => toast.classList.remove("show"), 5500);
  };

  // Upload and form handlers can return a script-only response before a
  // document body exists. Show the message first, then navigate safely.
  window.pqNotifyThen = function (message, next, type) {
    window.pqNotify(message, type);
    window.setTimeout(function () {
      if (typeof next === "function") next();
      else if (next) window.location.href = next;
    }, 1200);
  };

  window.pqConfirm = function (message, options) {
    options = options || {};
    return new Promise(function (resolve) {
      const render = function () {
        const backdrop = document.createElement("div");
        backdrop.className = "pq-confirm-backdrop";
        backdrop.setAttribute("role", "dialog");
        backdrop.setAttribute("aria-modal", "true");
        backdrop.innerHTML =
          '<div class="pq-confirm-card">' +
          '<div class="pq-confirm-icon"><i class="fa-solid fa-trash-can"></i></div>' +
          "<h3>" +
          (options.title || "Confirm deletion") +
          "</h3>" +
          "<p>" +
          String(message) +
          "</p>" +
          '<div class="pq-confirm-actions">' +
          '<button type="button" class="pq-confirm-cancel">' +
          (options.cancelLabel || "Cancel") +
          "</button>" +
          '<button type="button" class="pq-confirm-approve">' +
          (options.confirmLabel || "Delete") +
          "</button>" +
          "</div>" +
          "</div>";
        const finish = function (approved) {
          backdrop.remove();
          resolve(approved);
        };
        backdrop.querySelector(".pq-confirm-cancel").onclick = () =>
          finish(false);
        backdrop.querySelector(".pq-confirm-approve").onclick = () =>
          finish(true);
        backdrop.addEventListener("click", function (event) {
          if (event.target === backdrop) finish(false);
        });
        document.addEventListener("keydown", function onKeydown(event) {
          if (event.key === "Escape") {
            document.removeEventListener("keydown", onKeydown);
            finish(false);
          }
        });
        (document.body || document.documentElement).appendChild(backdrop);
      };
      if (document.body) render();
      else
        document.addEventListener("DOMContentLoaded", render, { once: true });
    });
  };

  // Keep older pages working while replacing browser "localhost says" popups.
  window.alert = function (message) {
    window.pqNotify(message);
  };
})();
