import { Controller } from '@hotwired/stimulus'

// Copie une URL dans le presse-papiers (composant Twig CopyLinkButton).
export default class extends Controller {
  static targets = ['button', 'status']
  static values = { url: String, copiedLabel: { type: String, default: 'Lien copié' } }

  async copy (event) {
    event.preventDefault()

    try {
      await this.write(this.urlValue)
      this.feedback(this.copiedLabelValue)
    } catch {
      this.feedback('Copie impossible')
    }
  }

  async write (text) {
    if (navigator.clipboard && window.isSecureContext) {
      try {
        await navigator.clipboard.writeText(text)
        return
      } catch {
        // Presse-papiers refusé (permissions) : on tente l'ancienne méthode.
      }
    }

    const textarea = document.createElement('textarea')
    textarea.value = text
    textarea.setAttribute('readonly', '')
    textarea.style.position = 'fixed'
    textarea.style.opacity = '0'
    document.body.appendChild(textarea)
    textarea.select()
    const ok = document.execCommand('copy')
    textarea.remove()
    if (!ok) throw new Error('copy failed')
  }

  feedback (message) {
    const label = this.hasButtonTarget ? this.buttonTarget.querySelector('span') : null
    if (label) {
      if (this.originalLabel === undefined) this.originalLabel = label.textContent
      label.textContent = message
    }
    if (this.hasStatusTarget) this.statusTarget.textContent = message

    window.clearTimeout(this.timer)
    this.timer = window.setTimeout(() => {
      if (label && this.originalLabel !== undefined) label.textContent = this.originalLabel
      if (this.hasStatusTarget) this.statusTarget.textContent = ''
    }, 2000)
  }

  disconnect () {
    window.clearTimeout(this.timer)
  }
}
