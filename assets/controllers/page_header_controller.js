import { Controller } from '@hotwired/stimulus'

// Marque l'en-tête de page comme « collé » (data-stuck="true") quand il rejoint la topbar.
// Le style (description masquée, titre réduit) est porté par les variantes Tailwind group-data-[stuck=true].
// Une sentinelle 1px placée juste avant l'en-tête sert de repère : elle n'est pas affectée par le
// changement de hauteur de l'en-tête, ce qui évite les va-et-vient au seuil.
export default class extends Controller {
  // Hauteur de la topbar, utilisée si .topbar-nav est absente (sinon mesurée : 1 ou 2 lignes selon l'écran)
  static values = { offset: { type: Number, default: 64 } }

  connect () {
    this.sentinel = document.createElement('div')
    this.sentinel.setAttribute('aria-hidden', 'true')
    this.sentinel.style.cssText = 'height:1px;margin-bottom:-1px;pointer-events:none'
    this.element.insertAdjacentElement('beforebegin', this.sentinel)

    this.topbar = document.querySelector('.topbar-nav')
    this._observe()

    // Publie la zone occupée en haut de page (topbar + en-tête collé) pour les éléments sticky de la page
    // (ex. menu latéral) : `top-[calc(var(--page-header-offset,4rem)+0.5rem)]`.
    // Observe aussi la topbar : sa hauteur change quand elle passe sur deux lignes.
    this.resizeObserver = new window.ResizeObserver(() => {
      if (this._topbarHeight() !== this.observedOffset) {
        this._observe()
      }
      const bottom = this.observedOffset + this.element.getBoundingClientRect().height
      document.documentElement.style.setProperty('--page-header-offset', `${bottom}px`)
    })
    this.resizeObserver.observe(this.element)
    if (this.topbar) {
      this.resizeObserver.observe(this.topbar)
    }
  }

  _topbarHeight () {
    return this.topbar ? Math.round(this.topbar.getBoundingClientRect().height) : this.offsetValue
  }

  _observe () {
    this.observer?.disconnect()
    this.observedOffset = this._topbarHeight()
    this.observer = new window.IntersectionObserver(([entry]) => {
      this.element.dataset.stuck = entry.isIntersecting ? 'false' : 'true'
    }, { rootMargin: `-${this.observedOffset}px 0px 0px 0px`, threshold: 0 })
    this.observer.observe(this.sentinel)
  }

  disconnect () {
    this.observer?.disconnect()
    this.resizeObserver?.disconnect()
    document.documentElement.style.removeProperty('--page-header-offset')
    this.sentinel?.remove()
  }
}
