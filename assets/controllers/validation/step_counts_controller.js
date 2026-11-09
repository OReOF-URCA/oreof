import { Controller } from '@hotwired/stimulus'

// Rafraîchit les décomptes par étape (`data-step-count`) après un traitement en lot,
// sans recharger la page ni refermer les listes ouvertes.
export default class extends Controller {
  async refresh () {
    try {
      const response = await fetch(window.location.href, { headers: { Accept: 'text/html' } })
      if (!response.ok) {
        return
      }

      const doc = new window.DOMParser().parseFromString(await response.text(), 'text/html')
      this.element.querySelectorAll('[data-step-count]').forEach((current) => {
        const fresh = doc.querySelector(`[data-step-count="${current.dataset.stepCount}"]`)
        if (fresh) {
          current.innerHTML = fresh.innerHTML
        }
      })
    } catch {
      // Le décompte sera mis à jour au prochain chargement de la page.
    }
  }
}
