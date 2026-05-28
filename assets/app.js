/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/app.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 14/03/2023 22:21
 */

window.da = {
  loader: document.getElementById('loader'),
}

Object.defineProperty(window.da, 'loaderStimulus', {
  get() {
    const template = document.getElementById('global-loader-stimulus')
    if (template) {
      return template.innerHTML.trim()
    }

    return `
      <div class="flex justify-center py-6">
        <div class="inline-flex items-center justify-center animate-spin h-14 w-14 text-secondary" role="status" aria-live="polite">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-full w-full" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9" opacity="0.2"></circle>
            <path d="M21 12a9 9 0 0 0-9-9"></path>
          </svg>
          <span class="sr-only">Chargement...</span>
        </div>
      </div>
    `
  },
})

import * as bootstrap from 'bootstrap'
import 'trix'
import 'trix/dist/trix.css'

import callOut from './js/callOut'
// import './styles/legacy.scss';
import './styles/app.css'
import './styles/_timeline.scss'

import './bootstrap'


import './js/base/init'
import './js/toggle'

document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(
  el => bootstrap.Tooltip.getOrCreateInstance(el)
)

const initBootstrapTooltips = (root = document) => {
  const tooltipElements = root.querySelectorAll('[data-bs-toggle="tooltip"]')
  tooltipElements.forEach((el) => {
    bootstrap.Tooltip.getOrCreateInstance(el)
  })
}

document.addEventListener('turbo:load', () => initBootstrapTooltips(document))
document.addEventListener('turbo:render', () => initBootstrapTooltips(document))
document.addEventListener('turbo:frame-load', (event) => {
  initBootstrapTooltips(event.target)
})


window.addEventListener('load', () => { // le dom est chargé
  const savedTheme = localStorage.getItem('oreof-theme')
  if (savedTheme === 'dark' || savedTheme === 'light') {
    document.documentElement.setAttribute('data-theme', savedTheme)
  }

  const savedColorTheme = localStorage.getItem('oreof-color-theme');
  if (savedColorTheme && savedColorTheme !== 'normal') {
    document.documentElement.setAttribute('data-color-theme', savedColorTheme);
  }

  const toastQueue = Array.isArray(window.toasts) ? window.toasts : []
  // toast
  toastQueue.forEach((toast) => {
    callOut(toast.text, toast.type)
  })

  document.addEventListener('trix-before-initialize', () => {
  })
})
