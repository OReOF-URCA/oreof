/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/assets/controllers/accessibility_controller.js
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 06/05/2026 10:50
 */

import { Controller } from '@hotwired/stimulus'

const STORAGE_KEY = 'oreof-a11y'

const DEFAULTS = {
  theme: 'system',
  font: 'default',
  size: 'normal',
  contrast: false,
  motion: false,
}

export default class extends Controller {
  static targets = ['contrast', 'motion']

  connect () {
    this.settings = this._load()
    this._apply()
    this._syncToggles()
  }

  setTheme (event) {
    this.settings.theme = event.params.theme || 'system'
    this._persistAndApply()
  }

  setFont (event) {
    this.settings.font = event.params.font || 'default'
    this._persistAndApply()
  }

  setSize (event) {
    this.settings.size = event.params.size || 'normal'
    this._persistAndApply()
  }

  toggleContrast (event) {
    this.settings.contrast = event.target.checked
    this._persistAndApply(false)
  }

  toggleMotion (event) {
    this.settings.motion = event.target.checked
    this._persistAndApply(false)
  }

  reset () {
    this.settings = { ...DEFAULTS }
    this._persistAndApply()

    // Thème de couleur : retour au thème par défaut (géré par theme_controller)
    localStorage.removeItem('oreof-color-theme')
    document.documentElement.removeAttribute('data-color-theme')
    window.dispatchEvent(new CustomEvent('color-theme:reset'))
  }

  _persistAndApply (syncToggles = true) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(this.settings))
    this._apply()
    if (syncToggles) {
      this._syncToggles()
    }
  }

  _load () {
    try {
      const raw = localStorage.getItem(STORAGE_KEY)
      if (!raw) {
        return { ...DEFAULTS }
      }

      const settings = { ...DEFAULTS, ...JSON.parse(raw) }
      // « Très grande » a été supprimée : l'ancienne valeur correspond désormais à « Grande »
      if (settings.size === 'xlarge') {
        settings.size = 'large'
      }

      return settings
    } catch {
      return { ...DEFAULTS }
    }
  }

  _apply () {
    const root = document.documentElement

    const resolvedTheme = this.settings.theme === 'system'
      ? this._systemTheme()
      : this.settings.theme

    root.setAttribute('data-theme', resolvedTheme)
    if (this.settings.theme === 'system') {
      localStorage.removeItem('oreof-theme')
    } else {
      localStorage.setItem('oreof-theme', resolvedTheme)
    }

    root.setAttribute('data-a11y-font', this.settings.font)
    root.setAttribute('data-a11y-size', this.settings.size)
    root.setAttribute('data-a11y-contrast', this.settings.contrast ? 'high' : 'normal')
    root.setAttribute('data-a11y-motion', this.settings.motion ? 'reduced' : 'normal')
  }

  _syncToggles () {
    if (this.hasContrastTarget) {
      this.contrastTarget.checked = !!this.settings.contrast
    }

    if (this.hasMotionTarget) {
      this.motionTarget.checked = !!this.settings.motion
    }
  }

  _systemTheme () {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
  }
}


