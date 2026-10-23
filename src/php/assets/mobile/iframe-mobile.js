(function () {
  const state = {
    imageUrl: "",
    videoUrl: "",
    downloadUrl: "",
    cameraStream: null,
    microphoneStream: null,
    audioContext: null,
    microphoneAnimationFrame: 0,
    cameraRequest: 0,
    microphoneRequest: 0,
    results: {},
  };

  const GEOLOCATION_ERRORS = {
    1: "PERMISSION_DENIED",
    2: "POSITION_UNAVAILABLE",
    3: "TIMEOUT",
  };

  function $(selector) {
    return document.querySelector(selector);
  }

  function card(check) {
    return document.querySelector(`[data-check="${check}"]`);
  }

  function setStatus(section, message, kind) {
    const element = section.querySelector(".status");
    element.textContent = message;
    element.classList.remove("is-success", "is-error");
    if (kind) {
      element.classList.add(kind);
    }
  }

  function setBadge(check, text, kind) {
    const badge = card(check).querySelector(".badge");
    badge.textContent = text;
    badge.classList.remove("is-ok", "is-error");
    badge.classList.add(kind);
  }

  function setResult(check, ok, detail, hint) {
    state.results[check] = { ok, detail };
    setBadge(check, ok ? "Работает" : "Ошибка", ok ? "is-ok" : "is-error");
    setStatus(card(check), hint ? `${detail}. ${hint}` : detail, ok ? "is-success" : "is-error");
  }

  function notify(check, message) {
    setStatus(card(check), message, "");
  }

  function errorMessage(error) {
    if (!error) {
      return "Неизвестная ошибка";
    }
    if (error.message) {
      return error.name && error.name !== "Error" ? `${error.name}: ${error.message}` : error.message;
    }
    return String(error);
  }

  function revokeUrl(key) {
    if (state[key]) {
      URL.revokeObjectURL(state[key]);
      state[key] = "";
    }
  }

  function formatFileSize(size) {
    if (size < 1024) {
      return `${size} Б`;
    }
    if (size < 1024 * 1024) {
      return `${(size / 1024).toFixed(1)} КБ`;
    }
    return `${(size / 1024 / 1024).toFixed(1)} МБ`;
  }

  function hasClipboardWrite() {
    return Boolean(navigator.clipboard && typeof navigator.clipboard.writeText === "function");
  }

  function hasGetUserMedia() {
    return Boolean(navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === "function");
  }

  function stopStream(stream) {
    if (stream) {
      stream.getTracks().forEach((track) => track.stop());
    }
  }

  function describeTrack(stream) {
    const track = stream.getTracks()[0];
    const settings = typeof track.getSettings === "function" ? track.getSettings() : {};
    const size = settings.width && settings.height ? `, ${settings.width}×${settings.height}` : "";
    return `${track.label || track.kind}${size}`;
  }

  function definitionRow(term, value) {
    const row = document.createElement("div");
    const dt = document.createElement("dt");
    const dd = document.createElement("dd");
    dt.textContent = term;
    dd.textContent = value;
    row.append(dt, dd);
    return row;
  }

  function safeAreaInsets() {
    const style = getComputedStyle($(".safe-area-probe"));
    return ["top", "right", "bottom", "left"]
      .map((side) => `${side} ${Math.round(parseFloat(style.getPropertyValue(`padding-${side}`)) || 0)}`)
      .join(", ");
  }

  function orientation() {
    if (screen.orientation && screen.orientation.type) {
      return screen.orientation.type;
    }
    return window.matchMedia("(orientation: portrait)").matches ? "portrait" : "landscape";
  }

  function environment() {
    const viewport = window.visualViewport;
    return [
      ["userAgent", navigator.userAgent],
      ["Окно", `${window.innerWidth}×${window.innerHeight}`],
      ["Видимая область", viewport ? `${Math.round(viewport.width)}×${Math.round(viewport.height)}` : "visualViewport недоступен"],
      ["Экран", `${screen.width}×${screen.height}, devicePixelRatio ${window.devicePixelRatio}`],
      ["Ориентация", orientation()],
      ["Safe area, px", safeAreaInsets()],
      ["Защищённый контекст", window.isSecureContext ? "да" : "нет — камера, микрофон и буфер обмена недоступны"],
      ["Язык", navigator.language],
    ];
  }

  function renderEnvironment() {
    $("#environment").replaceChildren(...environment().map(([term, value]) => definitionRow(term, value)));
  }

  function reportText() {
    const lines = ["PHP Demo App — проверка мобильного WebView", `Время: ${new Date().toISOString()}`];
    document.querySelectorAll("[data-report]").forEach((element) => {
      lines.push(`${element.dataset.report}: ${element.textContent.trim()}`);
    });
    environment().forEach(([term, value]) => lines.push(`${term}: ${value}`));
    lines.push("", "Проверки:");
    document.querySelectorAll("[data-check]").forEach((section) => {
      const result = state.results[section.dataset.check];
      const outcome = result
        ? `${result.ok ? "работает" : "ошибка"} — ${result.detail.replace(/\s+/g, " ").slice(0, 160)}`
        : "не проверено";
      lines.push(`- ${section.dataset.title}: ${outcome}`);
    });
    return `${lines.join("\n")}\n`;
  }

  async function copyText() {
    const text = $("#textInput").value || $("#textArea").value || "Тестовый текст из мобильного WebView";
    if (!hasClipboardWrite()) {
      throw new Error("Clipboard API недоступен");
    }

    await navigator.clipboard.writeText(text);
    setResult("text", true, `Скопировано: ${text}`);
  }

  async function readClipboard() {
    if (!navigator.clipboard || typeof navigator.clipboard.readText !== "function") {
      throw new Error("Clipboard read API недоступен");
    }

    const text = await navigator.clipboard.readText();
    $("#textArea").value = text;
    setResult("text", true, text ? `Прочитано из буфера: ${text.length} симв.` : "Буфер пуст");
  }

  function showImage(file) {
    if (!file) {
      return;
    }

    const preview = $("#imagePreview");
    revokeUrl("imageUrl");
    state.imageUrl = URL.createObjectURL(file);
    preview.onload = () => setResult("media", true, `Изображение показано: ${file.name}, ${preview.naturalWidth}×${preview.naturalHeight}`);
    preview.onerror = () => setResult("media", false, `Изображение выбрано, но не отображается: ${file.name}`);
    preview.src = state.imageUrl;
    preview.hidden = false;
  }

  function showVideo(file) {
    if (!file) {
      return;
    }

    const preview = $("#videoPreview");
    revokeUrl("videoUrl");
    state.videoUrl = URL.createObjectURL(file);
    preview.onloadedmetadata = () =>
      setResult("media", true, `Видео загружено: ${file.name}, ${preview.videoWidth}×${preview.videoHeight}, ${Math.round(preview.duration)} с`);
    preview.onerror = () => setResult("media", false, `Видео выбрано, но не декодируется: ${file.name}`);
    preview.src = state.videoUrl;
    preview.hidden = false;
    preview.load();
  }

  function showFiles(files) {
    const list = $("#fileList");
    list.replaceChildren(
      ...Array.from(files).map((file) => {
        const item = document.createElement("li");
        item.textContent = `${file.name} (${formatFileSize(file.size)}, ${file.type || "тип не указан"})`;
        return item;
      }),
    );
    if (files.length) {
      setResult("media", true, `Файлы доступны в WebView: ${files.length}`);
    } else {
      notify("media", "Файлы не выбраны");
    }
  }

  function downloadFile() {
    revokeUrl("downloadUrl");
    const blob = new Blob([reportText()], { type: "text/plain;charset=utf-8" });
    state.downloadUrl = URL.createObjectURL(blob);

    const link = document.createElement("a");
    link.href = state.downloadUrl;
    link.download = "webview-report.txt";
    document.body.appendChild(link);
    link.click();
    link.remove();
    notify("media", "Скачивание webview-report.txt запущено — проверьте, что файл появился в загрузках");
  }

  function releaseCamera() {
    const preview = $("#cameraPreview");
    state.cameraRequest += 1;
    stopStream(state.cameraStream);
    state.cameraStream = null;
    preview.pause();
    preview.srcObject = null;
    preview.hidden = true;
  }

  async function startCamera() {
    if (!hasGetUserMedia()) {
      throw new Error("getUserMedia недоступен");
    }

    releaseCamera();
    const request = state.cameraRequest;
    const stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: "environment" } },
      audio: false,
    });
    if (request !== state.cameraRequest) {
      stopStream(stream);
      return;
    }
    state.cameraStream = stream;

    const preview = $("#cameraPreview");
    preview.srcObject = stream;
    preview.hidden = false;
    const playError = await preview.play().then(() => null, (error) => error);
    if (request !== state.cameraRequest) {
      return;
    }
    if (playError) {
      releaseCamera();
      throw playError;
    }
    setResult("camera", true, `Камера включена: ${describeTrack(state.cameraStream)}`);
  }

  function releaseMicrophone() {
    state.microphoneRequest += 1;
    if (state.microphoneAnimationFrame) {
      window.cancelAnimationFrame(state.microphoneAnimationFrame);
      state.microphoneAnimationFrame = 0;
    }
    if (state.audioContext) {
      state.audioContext.close();
      state.audioContext = null;
    }
    stopStream(state.microphoneStream);
    state.microphoneStream = null;
    $("#microphoneLevel").style.width = "0";
  }

  async function startMicrophone() {
    if (!hasGetUserMedia()) {
      throw new Error("getUserMedia недоступен");
    }

    releaseMicrophone();
    const request = state.microphoneRequest;
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
    if (request !== state.microphoneRequest) {
      stopStream(stream);
      return;
    }
    state.microphoneStream = stream;

    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) {
      setResult("microphone", true, "Доступ к микрофону получен, AudioContext недоступен для уровня сигнала");
      return;
    }

    let analyser;
    try {
      state.audioContext = new AudioContextClass();
      analyser = state.audioContext.createAnalyser();
      analyser.fftSize = 256;
      state.audioContext.createMediaStreamSource(stream).connect(analyser);
    } catch (error) {
      releaseMicrophone();
      throw error;
    }

    const data = new Uint8Array(analyser.frequencyBinCount);
    const level = $("#microphoneLevel");

    function updateLevel() {
      analyser.getByteFrequencyData(data);
      const average = data.reduce((sum, value) => sum + value, 0) / data.length;
      level.style.width = `${Math.min(100, Math.round((average / 255) * 140))}%`;
      state.microphoneAnimationFrame = window.requestAnimationFrame(updateLevel);
    }

    updateLevel();
    setResult("microphone", true, `Микрофон включен: ${describeTrack(state.microphoneStream)}`, "Скажите что-нибудь — полоса должна двигаться");
  }

  function currentPosition() {
    return new Promise((resolve, reject) => {
      navigator.geolocation.getCurrentPosition(
        resolve,
        (error) => reject(Object.assign(new Error(error.message), { name: GEOLOCATION_ERRORS[error.code] || `code ${error.code}` })),
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
      );
    });
  }

  async function getLocation() {
    if (!navigator.geolocation) {
      throw new Error("Geolocation API недоступен");
    }

    const output = $("#locationOutput");
    output.hidden = true;
    notify("geolocation", "Запрашиваем координаты...");
    const { coords, timestamp } = await currentPosition();
    output.replaceChildren(
      definitionRow("Широта", coords.latitude.toFixed(6)),
      definitionRow("Долгота", coords.longitude.toFixed(6)),
      definitionRow("Точность", `${Math.round(coords.accuracy)} м`),
      definitionRow("Время", new Date(timestamp).toLocaleString()),
    );
    output.hidden = false;
    setResult("geolocation", true, `${coords.latitude.toFixed(5)}, ${coords.longitude.toFixed(5)} ±${Math.round(coords.accuracy)} м`);
  }

  async function copyReport() {
    const report = $("#report");
    const output = $("#reportOutput");
    output.value = reportText();
    output.hidden = false;
    if (!hasClipboardWrite()) {
      output.select();
      throw new Error("Clipboard API недоступен — выделите текст отчёта и скопируйте вручную");
    }

    await navigator.clipboard.writeText(output.value);
    setStatus(report, "Отчёт скопирован в буфер обмена", "is-success");
  }

  const ACTIONS = {
    "copy-text": copyText,
    "read-clipboard": readClipboard,
    "download-file": downloadFile,
    "start-camera": startCamera,
    "stop-camera": () => {
      releaseCamera();
      notify("camera", "Камера остановлена");
    },
    "start-microphone": startMicrophone,
    "stop-microphone": () => {
      releaseMicrophone();
      notify("microphone", "Микрофон остановлен");
    },
    "get-location": getLocation,
    "copy-report": copyReport,
  };

  function bindActions() {
    document.addEventListener("click", async (event) => {
      const button = event.target.closest("[data-action]");
      if (!button) {
        return;
      }

      button.disabled = true;
      try {
        await ACTIONS[button.dataset.action]();
      } catch (error) {
        const section = button.closest("[data-check]");
        if (section) {
          setResult(section.dataset.check, false, errorMessage(error));
        } else {
          setStatus(button.closest("section"), errorMessage(error), "is-error");
        }
      } finally {
        button.disabled = false;
      }
    });

    const imagePicker = $("#imagePicker");
    imagePicker.addEventListener("change", () => showImage(imagePicker.files[0]));

    const videoPicker = $("#videoPicker");
    videoPicker.addEventListener("change", () => showVideo(videoPicker.files[0]));

    const filePicker = $("#filePicker");
    filePicker.addEventListener("change", () => showFiles(filePicker.files));
  }

  function markMissingApis() {
    const missing = {
      text: hasClipboardWrite() ? "" : "Clipboard API недоступен",
      camera: hasGetUserMedia() ? "" : "getUserMedia недоступен",
      microphone: hasGetUserMedia() ? "" : "getUserMedia недоступен",
      geolocation: navigator.geolocation ? "" : "Geolocation API недоступен",
    };
    Object.entries(missing).forEach(([check, reason]) => {
      if (reason) {
        state.results[check] = { ok: false, detail: reason };
        setBadge(check, "Нет API", "is-error");
        setStatus(card(check), reason, "is-error");
      }
    });
  }

  window.addEventListener("pagehide", () => {
    releaseCamera();
    releaseMicrophone();
    revokeUrl("imageUrl");
    revokeUrl("videoUrl");
    revokeUrl("downloadUrl");
  });

  window.addEventListener("resize", renderEnvironment);
  if (window.visualViewport) {
    window.visualViewport.addEventListener("resize", renderEnvironment);
  }

  markMissingApis();
  renderEnvironment();
  bindActions();
})();
