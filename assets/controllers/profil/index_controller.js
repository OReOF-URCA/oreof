/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/profil/index_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/05/2025 16:32
 */

import { Controller } from '@hotwired/stimulus'
import updateUrl from '../../js/updateUrl'
import JsonResponse from '../../js/JsonResponse'

export default class extends Controller {
  static values = {
    urlListe: String,
    urlChangeDroit: String,
    profil: String,
  }

  static targets = ['liste']

  connect () {
    console.log(this.profilValue)
    if (this.profilValue !== '') {
      this._updateListe()
    }
  }

  changeProfil() {
    this._updateListe()
  }

  async changeDroit(event) {
    console.log(event)
    const body = {
      droit: event.params.droit,
      ressource: event.params.ressource,
      profil: event.params.profilId,
    }

    const response = await fetch(this.urlChangeDroitValue, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(body),
    })
    JsonResponse(response)
  }

  async _updateListe() {
    const profil = document.getElementById('profil').value

    updateUrl({
      profil,
    })

    if (profil !== '') {
      const body = new URLSearchParams({
        profil,
      })
      this.listeTarget.innerHTML = ''
      const response = await fetch(`${this.urlListeValue}?${body.toString()}`)
      this.listeTarget.innerHTML = await response.text()
    }
  }
}
