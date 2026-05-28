/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/composante/gestion_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 24/11/2023 15:32
 */

import { Controller } from '@hotwired/stimulus'
import callOut from '../../js/callOut'

export default class extends Controller {
  connect () {
    // Afficher le nom du fichier PDF sélectionné dans le label drag-and-drop
    const fileInput = this.element.querySelector('input[type="file"]')
    if (fileInput) {
      fileInput.addEventListener('change', (e) => {
        const label = fileInput.closest('label')
        if (!label) return
        const span = label.querySelector('span')
        if (e.target.files.length > 0) {
          const name = e.target.files[0].name
          if (span) span.textContent = name
          label.classList.add('border-primary-400', 'bg-primary-50/50')
          label.classList.remove('border-primary-200')
        } else {
          if (span) span.textContent = 'Déposer ou sélectionner un PDF'
          label.classList.remove('border-primary-400', 'bg-primary-50/50')
        }
      })
    }
  }

  valideConseil() {
    const liste = document.querySelectorAll('.check-all:checked')
    if (liste.length === 0) {
      callOut('Veuillez sélectionner au moins une formation', 'danger')
      return
    }

    const dateInput = this.element.querySelector('input[name="date"]')
    if (!dateInput || !dateInput.value) {
      callOut('Veuillez renseigner la date du conseil', 'danger')
      return
    }

    const formations = []
    liste.forEach((item) => formations.push(item.value))

    // TODO: soumettre via fetch ou FormData vers la route appropriée
    callOut(`${formations.length} formation(s) sélectionnée(s) — en attente d'implémentation du endpoint`, 'info')
  }
}
