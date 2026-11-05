import { Controller } from '@hotwired/stimulus'

// Marque l'en-tête de page comme « collé » (data-stuck="true") quand il rejoint la topbar.
// Le style (description masquée, titre réduit) est porté par les variantes Tailwind group-data-[stuck=true].
// Une sentinelle 1px placée juste avant l'en-tête sert de repère : elle n'est pas affectée par le
// changement de hauteur de l'en-tête, ce qui évite les va-et-vient au seuil.
// Sur une page trop courte pour absorber la hauteur perdue, le défilement serait ramené sous le seuil et l'en-tête
// oscillerait : on ne réduit alors pas l'en-tête.
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
    // La page peut s'allonger après coup (contenu chargé en différé) : on réévalue le collage
    this.rootObserver = new window.ResizeObserver(() => this._update())
    this.rootObserver.observe(document.getElementById('root') ?? document.body)
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
      this.passed = !entry.isIntersecting
      this._update()
    }, { rootMargin: `-${this.observedOffset}px 0px 0px 0px`, threshold: 0 })
    this.observer.observe(this.sentinel)
  }

  _update () {
    const stuck = this.element.dataset.stuck === 'true'
    if (!this.passed) {
      if (stuck) {
        this.element.dataset.stuck = 'false'
      }
      return
    }
    if (stuck || !this._hasRoom()) {
      return
    }
    this.fullHeight = this.element.getBoundingClientRect().height
    this.element.dataset.stuck = 'true'
    window.setTimeout(() => {
      // Hauteur réellement perdue (après les transitions de 200 ms), réutilisée pour les décisions suivantes
      if (this.element.dataset.stuck === 'true') {
        this.lostHeight = Math.max(0, this.fullHeight - this.element.getBoundingClientRect().height)
      }
    }, 300)
  }

  // Une fois l'en-tête réduit, le défilement possible doit rester au-delà du seuil de collage (+ marge).
  // Tant que la hauteur perdue n'est pas connue, on retient le pire cas : toute la hauteur de l'en-tête.
  _hasRoom () {
    const threshold = this.sentinel.getBoundingClientRect().top + window.scrollY - this.observedOffset
    const scrollable = document.documentElement.scrollHeight - window.innerHeight
    const lost = this.lostHeight ?? this.element.getBoundingClientRect().height
    return scrollable - lost >= threshold + 8
  }

  disconnect () {
    this.observer?.disconnect()
    this.resizeObserver?.disconnect()
    this.rootObserver?.disconnect()
    document.documentElement.style.removeProperty('--page-header-offset')
    this.sentinel?.remove()
  }
}
