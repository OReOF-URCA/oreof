// assets/controllers/inline_create_controller.js
import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['select', 'newInput', 'toggleButton']

  connect () {
    this.onAutocompleteConnect = () => {
      this.applyState()
    }
    this.selectTarget.addEventListener('autocomplete:connect', this.onAutocompleteConnect)

    this.applyState()
    this.updateButtonLabel()
  }

  disconnect () {
    if (this.onAutocompleteConnect) {
      this.selectTarget.removeEventListener('autocomplete:connect', this.onAutocompleteConnect)
    }
  }

  get selectContainer () {
    if (this.selectTarget.tomselect?.wrapper) {
      return this.selectTarget.tomselect.wrapper
    }
    const next = this.selectTarget.nextElementSibling
    if (next && next.classList.contains('ts-wrapper')) {
      return next
    }
    return this.selectTarget
  }

  toggleNewField () {
    this.newInputTarget.classList.toggle('hidden')

    if (!this.newInputTarget.classList.contains('hidden')) {
      this.newInputTarget.focus()
    }

    this.applyState()
    this.updateButtonLabel()
  }

  applyState () {
    const creating = !this.newInputTarget.classList.contains('hidden')

    const originalRequired =
      (this.selectTarget.dataset.inlineCreateOriginalRequired === '1')

    const container = this.selectContainer

    if (creating) {
      // Masquer le select / TomSelect
      container.classList.add('hidden')
      this.selectTarget.classList.add('hidden')

      // Select inoffensif pour la validation HTML5
      this.selectTarget.required = false
      this.selectTarget.disabled = true
      if (this.selectTarget.tomselect) {
        this.selectTarget.tomselect.disable()
      }

      // Input visible -> devient required si c’était requis
      this.newInputTarget.disabled = false
      this.newInputTarget.required = originalRequired
    } else {
      // Mode select
      container.classList.remove('hidden')
      if (!this.selectTarget.tomselect) {
        this.selectTarget.classList.remove('hidden')
      }

      this.selectTarget.disabled = false
      this.selectTarget.required = originalRequired
      if (this.selectTarget.tomselect) {
        this.selectTarget.tomselect.enable()
      }

      // Input caché
      this.newInputTarget.required = false
      this.newInputTarget.disabled = true
      this.newInputTarget.value = ''
    }
  }

  updateButtonLabel () {
    const creating = !this.newInputTarget.classList.contains('hidden')
    this.toggleButtonTarget.textContent = creating ? '← Revenir à la liste' : '+ Créer nouveau'
  }
}
