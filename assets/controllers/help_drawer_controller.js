import { Controller } from "@hotwired/stimulus";

const WIDTH_STORAGE_KEY = "oreof:help-drawer:width";
const MIN_WIDTH = 320;
const MAX_WIDTH_RATIO = 0.9;

/**
 * Help Drawer Controller
 * Gère l'ouverture/fermeture du drawer d'aide, ainsi que son redimensionnement
 * (poignée sur le bord gauche, largeur mémorisée par navigateur).
 * Persiste à travers les navigations Turbo
 */
export default class extends Controller {
  static targets = ["fab", "drawer", "close", "backdrop"];

  connect() {
    this.drawer = document.getElementById("helpOffcanvas");
    this.backdrop = document.getElementById("helpBackdrop");
    this.fab = document.getElementById("helpFabToggle");
    this.closeBtn = document.getElementById("helpOffcanvasClose");
    this.resizeHandle = document.getElementById("helpResizeHandle");

    this._onPointerMove = this._onPointerMove.bind(this);
    this._onPointerUp = this._onPointerUp.bind(this);
    this._resizing = false;

    this._restoreWidth();
  }

  disconnect() {
    this._onPointerUp();
  }

  toggle(event) {
    event?.preventDefault();
    if (this.isOpen()) {
      this.close();
    } else {
      this.open();
    }
  }

  open(event) {
    event?.preventDefault();

    if (!this.drawer) return;

    this.drawer.classList.remove("translate-x-full");
    this.drawer.classList.add("translate-x-0");
    this.drawer.setAttribute("aria-hidden", "false");

    if (this.fab) {
      this.fab.setAttribute("aria-expanded", "true");
    }

    if (this.backdrop) {
      this.backdrop.classList.remove("hidden");
      this.backdrop.classList.add("block");
      this.backdrop.setAttribute("aria-hidden", "false");
    }
  }

  close(event) {
    event?.preventDefault();

    if (!this.drawer) return;

    this.drawer.classList.remove("translate-x-0");
    this.drawer.classList.add("translate-x-full");
    this.drawer.setAttribute("aria-hidden", "true");

    if (this.fab) {
      this.fab.setAttribute("aria-expanded", "false");
    }

    if (this.backdrop) {
      this.backdrop.classList.add("hidden");
      this.backdrop.classList.remove("block");
      this.backdrop.setAttribute("aria-hidden", "true");
    }
  }

  isOpen() {
    return this.drawer && !this.drawer.classList.contains("translate-x-full");
  }

  onBackdropClick(event) {
    event?.preventDefault();
    this.close();
  }

  onEscape(event) {
    if (event.key === "Escape" && this.isOpen()) {
      this.close();
    }
  }

  startResize(event) {
    event?.preventDefault();
    if (!this.drawer) return;

    this._resizing = true;
    document.body.classList.add("select-none", "cursor-ew-resize");
    window.addEventListener("mousemove", this._onPointerMove);
    window.addEventListener("mouseup", this._onPointerUp);
  }

  _onPointerMove(event) {
    if (!this._resizing || !this.drawer) return;

    const maxWidth = Math.round(window.innerWidth * MAX_WIDTH_RATIO);
    // Le panneau est ancré à droite : sa largeur = distance entre le curseur et le bord droit de l'écran.
    const width = Math.min(
      maxWidth,
      Math.max(MIN_WIDTH, window.innerWidth - event.clientX),
    );
    this.drawer.style.width = `${width}px`;
  }

  _onPointerUp() {
    if (!this._resizing) return;

    this._resizing = false;
    document.body.classList.remove("select-none", "cursor-ew-resize");
    window.removeEventListener("mousemove", this._onPointerMove);
    window.removeEventListener("mouseup", this._onPointerUp);
    this._saveWidth();
  }

  _saveWidth() {
    if (!this.drawer) return;
    try {
      localStorage.setItem(WIDTH_STORAGE_KEY, this.drawer.style.width);
    } catch {
      // Stockage indisponible (navigation privée, quota, etc.) : on ignore silencieusement.
    }
  }

  _restoreWidth() {
    if (!this.drawer) return;
    try {
      const saved = localStorage.getItem(WIDTH_STORAGE_KEY);
      if (saved) {
        this.drawer.style.width = saved;
      }
    } catch {
      // Stockage indisponible : le panneau garde sa largeur par défaut.
    }
  }
}
