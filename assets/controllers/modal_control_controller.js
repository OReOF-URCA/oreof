import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  connect() {
    // 1. Fermer la modale Turbo
    window.dispatchEvent(new CustomEvent('modal:close'))

    // 2. Fermer toute modale de confirmation de suppression
    document.querySelectorAll('[data-delete-confirm-target="modal"]').forEach((modal) => {
      modal.classList.add('hidden')
    })
    document.documentElement.classList.remove('overflow-hidden')

    // 3. Recharger toutes les datatables
    if (typeof window.reloadAllDataTables === 'function') {
      window.reloadAllDataTables()
    } else {
      window.dispatchEvent(new CustomEvent('datatable:reload'))
    }

    // 4. Nettoyer l'élément de contrôle après exécution
    setTimeout(() => {
      if (this.element && this.element.parentNode) {
        this.element.remove()
      }
    }, 150)
  }
}
