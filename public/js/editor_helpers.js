(function () {
  var editorHistory = new WeakMap();
  var lastFocusedEditor = null;

  function isTrackedTextarea(textarea) {
    if (!textarea || !textarea.matches) return false;
    return (
      textarea.matches('textarea[name="help[content]"]') ||
      textarea.matches('textarea[name="faq[reponse]"]')
    );
  }

  function getEditorHistory(textarea) {
    if (!textarea) return null;
    var history = editorHistory.get(textarea);
    if (!history) {
      history = {
        states: [textarea.value],
        index: 0,
      };
      editorHistory.set(textarea, history);
    }
    return history;
  }

  function syncEditorHistory(textarea) {
    if (!isTrackedTextarea(textarea)) return;
    var history = getEditorHistory(textarea);
    if (!history) return;
    var currentValue = textarea.value;
    if (history.states[history.index] === currentValue) return;
    history.states = history.states.slice(0, history.index + 1);
    history.states.push(currentValue);
    history.index = history.states.length - 1;
  }

  function getCurrentEditor(selector) {
    if (selector) {
      var target = document.querySelector(selector);
      if (target) return target;
    }

    // Si un éditeur a eu le focus récemment et est toujours vivant dans la page, on le prend
    if (lastFocusedEditor && document.body.contains(lastFocusedEditor) && lastFocusedEditor.clientHeight > 0) {
      return lastFocusedEditor;
    }

    // Sinon, on cherche de manière intelligente le textarea visible dans la page active
    var faqTextarea = document.querySelector('textarea[name="faq[reponse]"]');
    if (faqTextarea && faqTextarea.clientHeight > 0) {
      return faqTextarea;
    }

    var helpTextarea = document.querySelector('textarea[name="help[content]"]');
    if (helpTextarea && helpTextarea.clientHeight > 0) {
      return helpTextarea;
    }

    // Fallback ultime si l'élément n'est pas encore affiché/mesurable
    return faqTextarea || helpTextarea;
  }

  function applyEditorValue(textarea, value) {
    if (!textarea) return;
    textarea.dataset.editorHistorySkip = "1";
    textarea.value = value;
    textarea.dispatchEvent(new Event("input", { bubbles: true }));
  }

  // Charger marked.js si nécessaire
  function loadMarked(cb) {
    if (window.marked) {
      cb();
      return;
    }
    var s = document.createElement("script");
    s.src = "https://cdn.jsdelivr.net/npm/marked/marked.min.js";
    s.onload = cb;
    document.head.appendChild(s);
  }

  function renderEmbeds(html) {
    return html.replace(
      /<a href="([^"]+)">(embed|video)<\/a>/gi,
      function (match, url) {
        url = url
          .replace("watch?v=", "embed/")
          .replace("youtu.be/", "youtube.com/embed/")
          .replace("vimeo.com/", "player.vimeo.com/video/");
        return (
          '<div class="ratio ratio-16x9 my-3 rounded overflow-hidden shadow-sm"><iframe src="' +
          url +
          '" allowfullscreen></iframe></div>'
        );
      },
    );
  }

  function updatePreviewFor(selector, previewId) {
    var textarea = document.querySelector(selector);
    var preview = document.getElementById(previewId);
    if (!preview) return;
    var content = textarea ? textarea.value : "";
    if (content.trim() === "") {
      preview.innerHTML =
        '<p class="text-muted fst-italic">Commencez à rédiger pour voir l\'aperçu...</p>';
      return;
    }

    function render() {
      try {
        var html = window.marked.parse(content);
        preview.innerHTML = renderEmbeds(html);
      } catch (e) {
        preview.textContent = content;
      }
    }

    if (window.marked) {
      render();
    } else {
      loadMarked(render);
    }
  }

  // Écoute les événements input pour mettre à jour les previews correspondants
  document.addEventListener(
    "input",
    function (e) {
      if (!e.target) return;
      if (e.target.dataset && e.target.dataset.editorHistorySkip === "1") {
        delete e.target.dataset.editorHistorySkip;
      } else {
        syncEditorHistory(e.target);
      }
      if (
        e.target.matches &&
        e.target.matches('textarea[name="help[content]"]')
      ) {
        updatePreviewFor('textarea[name="help[content]"]', "help-preview");
      }
      if (
        e.target.matches &&
        e.target.matches('textarea[name="faq[reponse]"]')
      ) {
        updatePreviewFor('textarea[name="faq[reponse]"]', "faq-preview");
      }
    },
    true,
  );

  // Initial render au chargement
  document.addEventListener("DOMContentLoaded", function () {
    document.addEventListener(
      "focusin",
      function (e) {
        if (isTrackedTextarea(e.target)) {
          lastFocusedEditor = e.target;
          getEditorHistory(e.target);
        }
      },
      true,
    );
    updatePreviewFor('textarea[name="help[content]"]', "help-preview");
    updatePreviewFor('textarea[name="faq[reponse]"]', "faq-preview");
  });

  // Helpers globaux (définir seulement s'ils n'existent pas déjà)
  if (typeof window.updateTextarea === "undefined") {
    window.updateTextarea = function (
      textarea,
      start,
      end,
      newText,
      newCursorPos,
    ) {
      textarea.focus();
      textarea.setRangeText(newText, start, end, "end");
      textarea.selectionStart = newCursorPos;
      textarea.selectionEnd = newCursorPos;
      textarea.dispatchEvent(new Event("input", { bubbles: true }));
    };
  }

  if (typeof window.editorHistoryUndo === "undefined") {
    window.editorHistoryUndo = function (selector) {
      var textarea = getCurrentEditor(selector);
      if (!textarea) return;
      var history = getEditorHistory(textarea);
      if (!history || history.index <= 0) return;
      history.index -= 1;
      applyEditorValue(textarea, history.states[history.index]);
    };
  }

  if (typeof window.editorHistoryRedo === "undefined") {
    window.editorHistoryRedo = function (selector) {
      var textarea = getCurrentEditor(selector);
      if (!textarea) return;
      var history = getEditorHistory(textarea);
      if (!history || history.index >= history.states.length - 1) return;
      history.index += 1;
      applyEditorValue(textarea, history.states[history.index]);
    };
  }

  if (typeof window.insertMarkdown === "undefined") {
    window.insertMarkdown = function (prefix, suffix, selector) {
      var textarea = getCurrentEditor(selector);
      if (!textarea) return;
      var start = textarea.selectionStart;
      var end = textarea.selectionEnd;
      var selected = textarea.value.substring(start, end);
      var newText, newCursorPos;
      if (selected.length > 0) {
        newText = prefix + selected + suffix;
        newCursorPos = start + newText.length;
      } else {
        newText = prefix + suffix;
        newCursorPos = start + prefix.length;
      }
      window.updateTextarea(textarea, start, end, newText, newCursorPos);
    };
  }

  if (typeof window.insertLink === "undefined") {
    window.insertLink = function (selector) {
      var textarea = getCurrentEditor(selector);
      if (!textarea) return;
      var url = prompt("Collez l'URL du lien (ex: https://...) :");
      if (!url) return;
      var start = textarea.selectionStart;
      var end = textarea.selectionEnd;
      var selectedText =
        textarea.value.substring(start, end) || "Texte du lien";
      var newText = "[" + selectedText + "](" + url + ")";
      window.updateTextarea(
        textarea,
        start,
        end,
        newText,
        start + newText.length,
      );
    };
  }

  if (typeof window.insertImage === "undefined") {
    window.insertImage = function (selector) {
      var textarea = getCurrentEditor(selector);
      if (!textarea) return;
      var url = prompt("Collez l'URL de l'image (ex: https://.../image.png) :");
      if (!url) return;
      var start = textarea.selectionStart;
      var end = textarea.selectionEnd;
      var newText = "![" + "Description de l'image" + "](" + url + ")";
      window.updateTextarea(
        textarea,
        start,
        end,
        newText,
        start + newText.length,
      );
    };
  }

  if (typeof window.openImageModal === "undefined") {
    window.openImageModal = function (selector) {
      if (typeof loadImages === "function") {
        try {
          loadImages();
        } catch (e) {
          /* ignore */
        }
      }
      var btn = document.getElementById("hiddenOpenModalBtn");
      if (btn) btn.click();
    };
  }

  if (typeof window.insertSelectedImage === "undefined") {
    window.insertSelectedImage = function (url, altName, selector) {
      var textarea = getCurrentEditor(selector);
      if (!textarea) return;
      var start = textarea.selectionStart;
      var end = textarea.selectionEnd;
      var safeAltName = altName || "Image";
      var newText = "![" + safeAltName + "](" + url + ")";
      window.updateTextarea(
        textarea,
        start,
        end,
        newText,
        start + newText.length,
      );

      var closeBtn = document.querySelector("#imageModal .btn-close");
      if (closeBtn) closeBtn.click();
    };
  }

  if (typeof window.insertVideo === "undefined") {
    window.insertVideo = function (selector) {
      var textarea = getCurrentEditor(selector);
      if (!textarea) return;
      var url = prompt(
        "Collez l'URL de la vidéo (ex: https://youtube.com/...) :",
      );
      if (!url) return;
      var start = textarea.selectionStart;
      var end = textarea.selectionEnd;
      var newText = "[embed](" + url + ")";
      window.updateTextarea(
        textarea,
        start,
        end,
        newText,
        start + newText.length,
      );
    };
  }

  if (typeof window.insertAccordion === "undefined") {
    window.insertAccordion = function (selector) {
      var textarea = getCurrentEditor(selector);
      if (!textarea) return;
      var title = prompt("Titre de la section déroulante :");
      if (!title) return;
      var start = textarea.selectionStart;
      var end = textarea.selectionEnd;
      var selectedText =
        textarea.value.substring(start, end) || "Contenu de la section ici...";
      var newText =
        "\n<details>\n<summary><strong>" +
        title +
        "</strong></summary>\n\n" +
        selectedText +
        "\n\n</details>\n";
      window.updateTextarea(
        textarea,
        start,
        end,
        newText,
        start + newText.length,
      );
    };
  }
})();
