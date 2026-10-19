/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/notification_liste_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 11/03/2023 12:13
 */

import { Controller } from '@hotwired/stimulus'
import callOut from '../js/callOut'

export default class extends Controller {
  static values = {
    url: String,
  }

  async change(event) {
    const body = {
      method: 'POST',
      body: JSON.stringify({
        value: event.target.checked,
        idProfil: event.params.profil,
        workflow: event.params.workflow,
      }),
    }

    const input = event.target
    const previous = !input.checked
    input.disabled = true
    try {
      const response = await fetch(this.urlValue, body)
      const data = await response.json()
      if (!response.ok || data !== true) {
        input.checked = previous
        callOut('La préférence n’a pas été enregistrée. La valeur précédente est rétablie.', 'danger')
        return
      }
      callOut('Sauvegarde effectuée', 'success')
    } catch {
      input.checked = previous
      callOut('Impossible de confirmer la sauvegarde. Rechargez les préférences pour vérifier leur état.', 'danger')
    } finally {
      input.disabled = false
    }
  }
}
