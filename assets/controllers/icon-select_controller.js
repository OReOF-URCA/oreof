/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/assets/controllers/icon-select_controller.js
 * @author davidannebicque
 * @project oreofv2
 */

import { Controller } from '@hotwired/stimulus'
import TomSelect from 'tom-select'

export default class extends Controller {
  static values = {
    icons: Object
  }

  connect () {
    this.initTomSelect()
  }

  disconnect () {
    if (this.tomSelect) {
      this.tomSelect.destroy()
    }
  }

  initTomSelect () {
    if (this.element.tomselect) {
      this.element.tomselect.destroy()
    }

    const icons = this.iconsValue || {}

    this.tomSelect = new TomSelect(this.element, {
      maxItems: 1,
      allowEmptyOption: true,
      render: {
        option: (data, escape) => {
          const iconSvg = icons[data.value] || ''
          if (!data.value) {
            return `<div class="py-1.5 px-2 text-secondary-400 italic">${escape(data.text)}</div>`
          }
          return `<div class="flex items-center gap-2.5 py-1.5 px-2">
            <span class="w-5 h-5 flex items-center justify-center shrink-0 text-primary">${iconSvg}</span>
            <span class="font-medium text-text">${escape(data.text)}</span>
          </div>`
        },
        item: (data, escape) => {
          const iconSvg = icons[data.value] || ''
          if (!data.value) {
            return `<div class="text-secondary-400 italic">${escape(data.text)}</div>`
          }
          return `<div class="flex items-center gap-2">
            <span class="w-4 h-4 flex items-center justify-center shrink-0 text-primary">${iconSvg}</span>
            <span class="font-medium text-text">${escape(data.text)}</span>
          </div>`
        }
      }
    })

    this.tomSelect.on('change', (value) => {
      const card = this.element.closest('[data-form-collection-target="field"]')
      if (card) {
        const iconContainer = card.querySelector('[data-step-icon]')
        if (iconContainer && icons[value]) {
          iconContainer.innerHTML = icons[value]
        }
      }
    })
  }
}
