import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['fields']

  connect () {
    this.sync()
  }

  sync () {
    if (!this.hasFieldsTarget) return
    const open = !this.fieldsTarget.classList.contains('hidden')
    this.element.querySelectorAll('[data-crud-panel-toggle]').forEach(button => {
      button.setAttribute('aria-expanded', String(open))
      button.setAttribute('aria-controls', this.fieldsTarget.id)
    })
  }

  toggle () {
    if (!this.hasFieldsTarget) return
    this.fieldsTarget.classList.toggle('hidden')
    this.sync()
  }
}
