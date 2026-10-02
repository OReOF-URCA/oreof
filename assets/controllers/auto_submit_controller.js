import { Controller } from '@hotwired/stimulus'

// Champ à refocaliser après le rendu du turbo-frame qui contient le formulaire
// (le formulaire est remplacé, le contrôleur se reconnecte sur la nouvelle instance).
let pendingFocus = null

/*
 * Soumet automatiquement un formulaire de filtres.
 *  - submit : immédiat (selects)
 *  - debouncedSubmit : après une pause de saisie (champ de recherche)
 */
export default class extends Controller {
  static values = {
    delay: { type: Number, default: 400 }
  }

  connect () {
    if (pendingFocus === null) return

    const field = this.element.querySelector(`[name="${pendingFocus.name}"]`)
    pendingFocus = null
    if (!field) return

    field.focus()
    if (typeof field.setSelectionRange === 'function') {
      const end = field.value.length
      field.setSelectionRange(end, end)
    }
  }

  disconnect () {
    clearTimeout(this.timeout)
  }

  debouncedSubmit () {
    clearTimeout(this.timeout)
    this.timeout = setTimeout(() => this.submit(), this.delayValue)
  }

  submit () {
    clearTimeout(this.timeout)
    const active = document.activeElement
    if (active && active.name && this.element.contains(active)) {
      pendingFocus = { name: active.name }
    }
    this.element.requestSubmit()
  }
}
