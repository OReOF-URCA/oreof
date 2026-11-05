import { Controller } from '@hotwired/stimulus'

// Disclosure navigation: ordinary links keep native Tab behaviour (not an ARIA menu).
export default class extends Controller {
  static targets = ['content', 'trigger']

  connect () {
    this.timeout = null
    if (this.hasContentTarget && this.hasTriggerTarget) {
      this.contentTarget.id ||= `nav-disclosure-${crypto.randomUUID()}`
      this.triggerTarget.setAttribute('aria-controls', this.contentTarget.id)
    }
    this.setOpen(false)
  }

  disconnect () {
    clearTimeout(this.timeout)
  }

  setOpen (open) {
    clearTimeout(this.timeout)
    if (this.hasContentTarget) this.contentTarget.classList.toggle('hidden', !open)
    if (this.hasTriggerTarget) this.triggerTarget.setAttribute('aria-expanded', String(open))
  }

  open (event) {
    if (event?.type === 'pointerenter' && event.pointerType !== 'mouse') return
    this.setOpen(true)
  }

  close (event) {
    if (event?.type === 'pointerleave') {
      if (event.pointerType !== 'mouse') return
      clearTimeout(this.timeout)
      this.timeout = setTimeout(() => {
        if (!this.element.contains(document.activeElement)) this.setOpen(false)
      }, 150)
      return
    }
    if (!event || !this.element.contains(event.target)) this.setOpen(false)
  }

  toggle () {
    if (this.hasContentTarget) this.setOpen(this.contentTarget.classList.contains('hidden'))
  }

  focusIn (event) {
    clearTimeout(this.timeout)
    // A button gets native Enter/Space activation; opening on focus would invert its first click.
    if (!this.hasTriggerTarget || !this.triggerTarget.contains(event.target)) this.open()
  }

  focusOut (event) {
    if (!this.element.contains(event.relatedTarget)) this.setOpen(false)
  }

  keydown (event) {
    if (event.key !== 'Escape' || !this.hasContentTarget || this.contentTarget.classList.contains('hidden')) return
    event.preventDefault()
    event.stopPropagation()
    this.setOpen(false)
    if (this.hasTriggerTarget) this.triggerTarget.focus()
  }
}
