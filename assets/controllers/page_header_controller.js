import { Controller } from '@hotwired/stimulus'

// Marque l'en-tête de page comme « collé » (data-stuck="true") quand il rejoint la topbar.
// Le style (description masquée, titre réduit) est porté par les variantes Tailwind group-data-[stuck=true].
// Une sentinelle 1px placée juste avant l'en-tête sert de repère : elle n'est pas affectée par le
// changement de hauteur de l'en-tête, ce qui évite les va-et-vient au seuil.
export default class extends Controller {
  // Hauteur de la topbar (h-16)
  static values = { offset: { type: Number, default: 64 } }

  connect () {
    this.sentinel = document.createElement('div')
    this.sentinel.setAttribute('aria-hidden', 'true')
    this.sentinel.style.cssText = 'height:1px;margin-bottom:-1px;pointer-events:none'
    this.element.insertAdjacentElement('beforebegin', this.sentinel)

    this.observer = new window.IntersectionObserver(([entry]) => {
      this.element.dataset.stuck = entry.isIntersecting ? 'false' : 'true'
    }, { rootMargin: `-${this.offsetValue}px 0px 0px 0px`, threshold: 0 })
    this.observer.observe(this.sentinel)

    // Publie la zone occupée en haut de page (topbar + en-tête collé) pour les éléments sticky de la page
    // (ex. menu latéral) : `top-[calc(var(--page-header-offset,4rem)+0.5rem)]`.
    this.resizeObserver = new window.ResizeObserver(() => {
      const bottom = this.offsetValue + this.element.getBoundingClientRect().height
      document.documentElement.style.setProperty('--page-header-offset', `${bottom}px`)
    })
    this.resizeObserver.observe(this.element)
  }

  disconnect () {
    this.observer?.disconnect()
    this.resizeObserver?.disconnect()
    document.documentElement.style.removeProperty('--page-header-offset')
    this.sentinel?.remove()
  }
}
