import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['select', 'customContainer', 'input']
  static values = {
    required: Boolean
  }

  connect () {
    this.syncFromSelect(false)
  }

  onSelectChange () {
    this.syncFromSelect(true)
  }

  syncFromSelect (focusCustom) {
    if (!this.hasSelectTarget || !this.hasInputTarget) {
      return
    }

    const selected = this.selectTarget.value
    const isCustom = selected === 'custom'

    if (this.hasCustomContainerTarget) {
      this.customContainerTarget.hidden = !isCustom
      this.customContainerTarget.classList.toggle('hidden', !isCustom)
    }

    this.inputTarget.required = isCustom && this.requiredValue

    if (isCustom) {
      if (focusCustom) {
        const presetValues = Array.from(this.selectTarget.options)
          .map(opt => opt.value)
          .filter(val => val !== '' && val !== 'custom')
        if (presetValues.includes(this.inputTarget.value)) {
          this.inputTarget.value = ''
        }
        this.inputTarget.focus()
      }
    } else {
      this.inputTarget.value = selected
    }
  }
}

