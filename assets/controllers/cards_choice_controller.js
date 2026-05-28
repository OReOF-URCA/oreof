/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/cards_choice_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 27/01/2026 20:04
 */

// assets/controllers/cards_choice_controller.js
import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static values = {
    eventName: String,
    updateTarget: String
  }

  changed (event) {
    const input = event.target
    const value = input.value

    // Event custom (optionnel)
    if (this.hasEventNameValue && this.eventNameValue) {
      this.element.dispatchEvent(new CustomEvent(this.eventNameValue, {
        bubbles: true,
        detail: { value, input }
      }))
    }

    // Update target (optionnel) : exemple simple (classe, scroll, etc.)
    if (this.hasUpdateTargetValue && this.updateTargetValue) {
      const el = document.querySelector(this.updateTargetValue)
      if (el) {
        // au choix : scroll / highlight / refresh turbo frame...
        el.scrollIntoView({ behavior: 'smooth', block: 'start' })
        el.classList.add('ring-2', 'ring-primary-200')
        setTimeout(() => el.classList.remove('ring-2', 'ring-primary-200'), 600)
      }
    }
  }
}
