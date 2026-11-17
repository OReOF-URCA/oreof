import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static values = { url: String }

  connect () {
    this.timer = null
    this.abort = null
      this.pendingTargets = new Set()

      this.onTrixEvent = (e) => {
          const editor = e.target
          const inputId = editor.getAttribute('input')
          const input = inputId ? document.getElementById(inputId) : null
          if (input) {
              this.queue({target: input})
          }
      }

      this.element.querySelectorAll('trix-editor').forEach(editor => {
          editor.addEventListener('trix-change', this.onTrixEvent)
          editor.addEventListener('trix-blur', this.onTrixEvent)
      })
  }

    disconnect() {
        this.element.querySelectorAll('trix-editor').forEach(editor => {
            editor.removeEventListener('trix-change', this.onTrixEvent)
            editor.removeEventListener('trix-blur', this.onTrixEvent)
        })

        if (this.timer) {
            clearTimeout(this.timer)
            this.timer = null
            this.savePendingFields()
        }
    }

    resolveTarget(el) {
        if (!el) return null
        if (el.tagName === 'TRIX-EDITOR') {
            const inputId = el.getAttribute('input')
            return inputId ? document.getElementById(inputId) : null
        }
        return el
  }

  queue (e) {
      const target = this.resolveTarget(e.target)
      if (!target || !target.name) return

      if (!this.pendingTargets) this.pendingTargets = new Set()
      this.pendingTargets.add(target)

    clearTimeout(this.timer)
      this.timer = setTimeout(() => this.savePendingFields(), 650)
  }

    async savePendingFields() {
        if (!this.pendingTargets || this.pendingTargets.size === 0) return

        const targets = Array.from(this.pendingTargets)
        this.pendingTargets.clear()

        for (const target of targets) {
            await this.saveTarget(target)
        }
    }

    // Alias for backward compatibility
  async saveField () {
      return this.savePendingFields()
  }

    async saveTarget(target) {
        if (!target?.name) return

    const fd = new FormData()
        fd.append('field', target.name)
        fd.append('value', this.fieldValue(target))
        fd.append('_token', this.csrfToken())

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: {'Accept': 'text/vnd.turbo-stream.html'},
                body: fd,
            })

            if (response.ok) {
                const stream = await response.text()
                if (stream && stream.trim().startsWith('<turbo-stream')) {
                    Turbo.renderStreamMessage(stream)
                }
            }
        } catch (err) {
            console.error('Autosave error:', err)
        }
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

