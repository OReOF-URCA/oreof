import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static values = {
    url: String,
    confirm: String,
  }

  async generate(event) {
    event.preventDefault()
    event.stopPropagation()

    const confirmMsg = this.confirmValue || 'Générer une nouvelle version JSON ?'
    if (!window.confirm(confirmMsg)) {
      return
    }

    const button = event.currentTarget
    const icon = button.querySelector('svg')
    
    button.disabled = true
    button.classList.add('opacity-60', 'pointer-events-none')
    if (icon) {
      icon.classList.add('animate-spin')
    }

    try {
      const response = await fetch(this.urlValue, {
        method: 'POST',
        headers: {
          'Accept': 'text/vnd.turbo-stream.html, application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      })

      if (response.ok) {
        const html = await response.text()
        if (window.Turbo && typeof window.Turbo.renderStreamMessage === 'function') {
          window.Turbo.renderStreamMessage(html)
        }

        // Reload the DataTable row in-place without resetting page
        this.reloadDataTable(button)
      } else {
        const errorText = await response.text()
        console.error('Erreur génération versioning:', errorText)
      }
    } catch (error) {
      console.error('Erreur génération versioning:', error)
    } finally {
      button.disabled = false
      button.classList.remove('opacity-60', 'pointer-events-none')
      if (icon) {
        icon.classList.remove('animate-spin')
      }
    }
  }

  reloadDataTable(element) {
    const tableEl = element.closest('.dt-container')?.querySelector('table') || element.closest('table') || document.querySelector('.dt-container table')
    if (!tableEl) return

    // 1. Try Stimulus ux-datatables controller
    if (window.Stimulus) {
      const controller = window.Stimulus.getControllerForElementAndIdentifier(tableEl, 'pentiminax--ux-datatables--datatable')
      if (controller?.table?.ajax) {
        controller.table.ajax.reload(null, false)
        return
      }
    }

    // 2. Try window.DataTable (DataTables v2)
    if (window.DataTable) {
      try {
        const dt = window.DataTable.tables({ api: true, visible: true })
        if (dt && typeof dt.ajax?.reload === 'function') {
          dt.ajax.reload(null, false)
          return
        }
      } catch {
        // ignore
      }
    }

    // 3. Try jQuery DataTable
    if (window.$ && typeof window.$(tableEl).DataTable === 'function') {
      try {
        window.$(tableEl).DataTable().ajax.reload(null, false)
      } catch {
        // ignore
      }
    }
  }
}
