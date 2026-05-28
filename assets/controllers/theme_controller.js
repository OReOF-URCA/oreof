/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/assets/controllers/theme_controller.js
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 02/05/2026 08:54
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['label', 'colorButton']

  connect () {
    this._render()

    const currentColorTheme = localStorage.getItem('oreof-color-theme') || 'normal'
    this._updateActiveColorButton(currentColorTheme)
  }

  toggle (event) {
    event.preventDefault()
    const nextTheme = this._currentTheme() === 'dark' ? 'light' : 'dark'
    this._apply(nextTheme)
  }

  _currentTheme () {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'
  }

  _apply (theme) {
    document.documentElement.setAttribute('data-theme', theme)
    localStorage.setItem('oreof-theme', theme)
    this._render()
  }

  _render () {
    if (!this.hasLabelTarget) {
      return
    }

    const isDark = this._currentTheme() === 'dark'
    this.labelTarget.textContent = isDark ? 'Passer en mode clair' : 'Passer en mode sombre'
  }

  changeColorTheme(event) {
    const theme = event.currentTarget.dataset.themeName

    if (theme === 'normal') {
      document.documentElement.removeAttribute('data-color-theme')
    } else {
      document.documentElement.setAttribute('data-color-theme', theme)
    }

    localStorage.setItem('oreof-color-theme', theme)
    this._updateActiveColorButton(theme)
  }

  _updateActiveColorButton(activeTheme) {
    // On ignore si le contrôleur est instancié à un endroit qui n'a pas les boutons de couleur (ex: navbar)
    if (!this.hasColorButtonTarget) return

    this.colorButtonTargets.forEach(btn => {
      if (btn.dataset.themeName === activeTheme) {
        // Bouton ACTIF : on ajoute un anneau de sélection (adapté au mode clair et sombre)
        btn.classList.add('ring-2', 'ring-offset-2', 'ring-secondary-800', 'dark:ring-secondary-100')
        btn.setAttribute('aria-pressed', 'true')
      } else {
        // Bouton INACTIF : on retire l'anneau
        btn.classList.remove('ring-2', 'ring-offset-2', 'ring-secondary-800', 'dark:ring-secondary-100')
        btn.setAttribute('aria-pressed', 'false')
      }
    })
  }
}

