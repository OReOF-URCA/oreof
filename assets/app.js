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
import './styles/app.css'
import './styles/_timeline.scss'

import 'datatables.net-dt/css/dataTables.dataTables.min.css'
import '@pentiminax/ux-datatables/dist/styles/datatables-base-style.css'
import '@pentiminax/ux-datatables/dist/styles/datatables-tailwind-theme.css'

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

// Track active DataTables when connected via Stimulus
document.addEventListener('pentiminax--ux-datatables--datatable:connect', (event) => {
  if (event.target && event.detail?.table) {
    event.target._uxDataTable = event.detail.table
  }
})
document.addEventListener('@pentiminax/ux-datatables/datatable:connect', (event) => {
  if (event.target && event.detail?.table) {
    event.target._uxDataTable = event.detail.table
  }
})

export const reloadAllDataTables = () => {
  const tables = document.querySelectorAll(
    'table.dataTable, table[data-controller*="pentiminax--ux-datatables--datatable"], .dt-container table, table[id]'
  )

  let reloaded = false

  tables.forEach((tableEl) => {
    // 1. Direct cached instance from connect event
    if (tableEl._uxDataTable) {
      if (typeof tableEl._uxDataTable.ajax?.reload === 'function') {
        tableEl._uxDataTable.ajax.reload(null, false)
        reloaded = true
        return
      }
      if (typeof tableEl._uxDataTable.draw === 'function') {
        tableEl._uxDataTable.draw(false)
        reloaded = true
        return
      }
    }

    // 2. Via Stimulus controller instance
    if (window.Stimulus) {
      try {
        const controller = window.Stimulus.getControllerForElementAndIdentifier(
          tableEl,
          'pentiminax--ux-datatables--datatable'
        )
        if (controller?.table) {
          if (typeof controller.table.ajax?.reload === 'function') {
            controller.table.ajax.reload(null, false)
            reloaded = true
            return
          }
          if (typeof controller.table.draw === 'function') {
            controller.table.draw(false)
            reloaded = true
            return
          }
        }
      } catch (e) {}
    }

    // 3. Via global DataTable API
    if (window.DataTable) {
      try {
        if (window.DataTable.isDataTable?.(tableEl)) {
          const api = new window.DataTable.Api(tableEl)
          if (typeof api.ajax?.reload === 'function') {
            api.ajax.reload(null, false)
            reloaded = true
            return
          }
          if (typeof api.draw === 'function') {
            api.draw(false)
            reloaded = true
            return
          }
        }
      } catch (e) {}
    }

    // 4. Via jQuery DataTable plugin
    if (window.$ && typeof window.$.fn?.dataTable === 'object') {
      try {
        if (window.$.fn.dataTable.isDataTable(tableEl)) {
          const dt = window.$(tableEl).DataTable()
          if (typeof dt.ajax?.reload === 'function') {
            dt.ajax.reload(null, false)
            reloaded = true
            return
          }
          if (typeof dt.draw === 'function') {
            dt.draw(false)
            reloaded = true
            return
          }
        }
      } catch (e) {}
    }
  })

  // 5. Global DataTable.tables fallback
  if (!reloaded && window.DataTable && typeof window.DataTable.tables === 'function') {
    try {
      const allTables = window.DataTable.tables({ api: true })
      if (typeof allTables.ajax?.reload === 'function') {
        allTables.ajax.reload(null, false)
      } else if (typeof allTables.draw === 'function') {
        allTables.draw(false)
      }
    } catch (e) {}
  }

  // 6. Legacy datatable LiveComponent fallback
  const legacyEl = document.querySelector('.datatable-wrapper')
  if (legacyEl && legacyEl.__component) {
    legacyEl.__component.render()
  }
}

window.reloadAllDataTables = reloadAllDataTables

window.addEventListener('datatable:reload', () => {
  reloadAllDataTables()
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
