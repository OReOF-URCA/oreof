import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['input', 'iconShow', 'iconHide', 'copyFeedback']

  toggle (event) {
    if (event) {
      event.preventDefault()
    }

    if (!this.hasInputTarget) {
      return
    }

    const isPassword = this.inputTarget.type === 'password'
    this.inputTarget.type = isPassword ? 'text' : 'password'

    if (this.hasIconShowTarget && this.hasIconHideTarget) {
      this.iconShowTarget.classList.toggle('hidden', isPassword)
      this.iconHideTarget.classList.toggle('hidden', !isPassword)
    }
  }

  async copy (event) {
    if (event) {
      event.preventDefault()
    }

    if (!this.hasInputTarget || !this.inputTarget.value) {
      return
    }

    try {
      await navigator.clipboard.writeText(this.inputTarget.value)
      if (this.hasCopyFeedbackTarget) {
        this.copyFeedbackTarget.classList.remove('hidden')
        setTimeout(() => {
          this.copyFeedbackTarget.classList.add('hidden')
        }, 2000)
      }
    } catch (e) {
      console.warn('Clipboard write failed', e)
    }
  }
}
