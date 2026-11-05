import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  connect () {
    // The same summary may live on a page or inside a freshly rendered modal.
    this.frame = requestAnimationFrame(() => {
      if (this.element.isConnected) this.element.focus()
    })
  }

  disconnect () {
    cancelAnimationFrame(this.frame)
  }

  focusField (event) {
    const link = event.target.closest('[data-field-id]')
    if (!link || !this.element.contains(link)) return
    const target = document.getElementById(link.dataset.fieldId)
    if (!target) return
    event.preventDefault()
    const field = target.matches('input, select, textarea, trix-editor, button')
      ? target : target.querySelector('input:not([type="hidden"]), select, textarea, button')
    if (!field) return
    // Reveal an invalid field inside a client-side tab without changing the rest of the form.
    const panel = field.closest('[data-panel-id]')
    const tabs = panel?.closest('[data-controller~="tabs"]')
    const controller = tabs && window.Stimulus?.getControllerForElementAndIdentifier(tabs, 'tabs')
    if (controller) controller.activate(panel.dataset.panelId)
    if (field.tomselect) field.tomselect.focus()
    else field.focus()
    field.scrollIntoView({ block: 'center' })
  }
}
