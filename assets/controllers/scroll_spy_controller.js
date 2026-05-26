/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/assets/controllers/scroll_spy_controller.js
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 26/05/2026 20:31
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['link', 'section']

  static values = {
    /** Classe CSS ajoutée sur le lien actif */
    activeClass: { type: String, default: 'app-scrollspy-link-active' },
    /**
     * rootMargin pour l'IntersectionObserver.
     * Valeur par défaut : déclenchement quand la section atteint ≈ 10 % du haut
     * et qu'il reste encore 70 % en bas de la fenêtre.
     */
    rootMargin: { type: String, default: '-10% 0px -70% 0px' },
    threshold: { type: Number, default: 0 },
  }

  connect () {
    const options = {
      root: null,
      rootMargin: this.rootMarginValue,
      threshold: this.thresholdValue,
    }

    this.observer = new IntersectionObserver(this.#onIntersection.bind(this), options)
    this.sectionTargets.forEach(section => this.observer.observe(section))
  }

  disconnect () {
    this.observer?.disconnect()
  }

  // ── Privé ──────────────────────────────────────────────────────────────────

  #onIntersection (entries) {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        this.#activateLink(entry.target.id)
      }
    })
  }

  #activateLink (id) {
    const activeClass = this.activeClassValue
    this.linkTargets.forEach(link => {
      const isActive = link.getAttribute('href') === `#${id}`
      link.classList.toggle(activeClass, isActive)
    })
  }
}

