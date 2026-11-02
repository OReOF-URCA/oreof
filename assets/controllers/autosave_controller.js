import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static values = { url: String }

  connect () {
    this.timer = null
    this.abort = null
    this.lastTarget = null

    const editors = this.element.querySelectorAll('trix-editor')
    editors.forEach(editor => editor.addEventListener('trix-blur', this.queue.bind(this)))
  }

  queue (e) {
    // on garde le dernier champ modifié
    this.lastTarget = e.target

    clearTimeout(this.timer)
    this.timer = setTimeout(() => this.saveField(), 650)
  }

  async saveField () {
    if (!this.lastTarget?.name) return

    if (this.abort) this.abort.abort()
    this.abort = new AbortController()

    const fd = new FormData()
    fd.append('field', this.lastTarget.name)
    fd.append('value', this.fieldValue(this.lastTarget))
    fd.append('_token', this.csrfToken()) // optionnel, selon ton choix CSRF

    const response = await fetch(this.urlValue, {
      method: 'POST',
      headers: { 'Accept': 'text/vnd.turbo-stream.html' },
      body: fd,
      signal: this.abort.signal,
    })

    const stream = await response.text()
    Turbo.renderStreamMessage(stream)
  }

  fieldValue (el) {
    if (el.tagName === 'SELECT' && el.multiple) {
      const selected = Array.from(el.selectedOptions).map(opt => opt.value)
      return JSON.stringify(selected)
    }

    if (el.type === 'checkbox' && el.name.endsWith('[]')) {
      const checked = [...this.element.querySelectorAll(`input[name="${CSS.escape(el.name)}"]:checked`)]
        .map(x => x.value)
      return JSON.stringify(checked) // côté PHP -> toArray() -> toEnumArray()
    }

    if (el.type === 'checkbox') return el.checked ? '1' : '0'

    if (el.type === 'radio') {
      const checked = this.element.querySelector(`input[name="${CSS.escape(el.name)}"]:checked`)
      return checked ? checked.value : ''
    }

    return el.value ?? ''
  }

  csrfToken () {
    // si tu as un meta <meta name="csrf-token" content="...">
    const m = document.querySelector('meta[name="csrf-token"]')
    return m ? m.getAttribute('content') : ''
  }
}

