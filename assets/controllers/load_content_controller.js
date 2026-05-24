import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static values = {
    url: String,
    inital: {
      type: String,
      default: '',
    },
    loadOnConnect: {
      type: Boolean,
      default: false,
    },
  }

  isOpen = false
  currentValue = null
  static targets = ['content']

  connect() {
    if (this.initialValue !== '') {
      if (this.loadOnConnectValue === true) {
        this._loadContent(this._normalizeValue(this.initialValue))
      }
    }
  }

  change(event) {
    const targetValue = event?.target?.value
    const fallbackValue = event?.params?.value

    const rawValue = targetValue !== '' && targetValue !== undefined
      ? targetValue
      : fallbackValue

    const value = this._normalizeValue(rawValue)

    if (this.isOpen) {
      this.contentTarget.innerHTML = ''
      this.isOpen = false
    } else {
      this._loadContent(value)
      this.isOpen = true
    }
  }

  async refresh () {
    if (this.isOpen === false) {
      return
    }

    await this._loadContent(this.currentValue)
  }

  _normalizeValue (rawValue) {
    if (rawValue === null || rawValue === undefined) {
      return null
    }

    if (typeof rawValue === 'string') {
      const cleaned = rawValue.trim()
      if (cleaned === '' || cleaned === '[object Object]') {
        return null
      }
      return cleaned
    }

    if (typeof rawValue === 'number' || typeof rawValue === 'boolean') {
      return String(rawValue)
    }

    // Objet, tableau, fonction => on ignore
    return null
  }

  async _loadContent(value) {
    this.currentValue = value
    const url = new window.URL(this.urlValue, window.location.origin)

    if (value === null) {
      url.searchParams.delete('value')
    } else {
      url.searchParams.set('value', value)
    }

    this.contentTarget.innerHTML = window.da.loaderStimulus
    const response = await fetch(url.toString())
    this.contentTarget.innerHTML = await response.text()
  }
}
