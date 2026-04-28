/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/profil/user_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/05/2025 16:32
 */

import { Controller } from '@hotwired/stimulus'
import callOut from '../../js/callOut'
import { Modal } from 'bootstrap'

export default class extends Controller {
  static targets = ['configProfil', 'liste']

  static values = {
    urlConfigProfil: String,
    urlListe: String,
    urlAdd: String,
  }

  connect() {
    this._updateListe()
  }

  async _updateListe() {
    this.listeTarget.innerHTML = 'Chargement...'
    fetch(this.urlListeValue)
      .then((response) => response.text())
      .then((html) => {
        this.listeTarget.innerHTML = html
      })
  }

  delete (event) {
    console.log('coucou delete')
    event.preventDefault()
    const { url } = event.params
    const { csrf } = event.params
    let modal = new Modal(document.getElementById('modal-delete'))
    modal.show()
    document.getElementById('btn-confirm-supprimer').addEventListener('click', async () => {
      const body = {
        method: 'DELETE',
        body: JSON.stringify({
          csrf,
        }),
      }
      modal = null
      await fetch(url, body).then(() => {
        callOut('Suppression effectuée', 'success')
        this._updateListe()
      })
    })
  }

  addProfil(event) {
    event.preventDefault()

    const centreType = document.getElementById('typeCentre').value
    const centreId = document.getElementById('selectListe').value
    const role = document.getElementById('droits').value

    if (role === '' || (centreType !== 'cg_etablissement' && centreId === '')) {
      callOut('Veuillez sélectionner un centre et un rôle', 'error')
    } else {
      this._updateCentre(centreType, centreId, role, false)
    }
  }

  _updateCentre(centreType, centreId, role, force = false) {
    fetch(this.urlAddValue, {
      method: 'POST',
      body: JSON.stringify({
        centreType,
        centreId,
        role,
        force,
      }),
    })
      .then((response) => response.json())
      .then((json) => {
        if (json.success) {
          callOut('Centre ajouté', 'success')
          this._updateListe()
        } else if (json.error === 'already_exist') {
          if (confirm('Le centre est déjà associé à un autre utilisateur, voulez-vous le remplacer ?')) {
            this._updateCentre(centreType, centreId, role, true)
          }
        } else {
          callOut(json.error, 'error')
        }
      })
  }

  async changeProfil(event) {
    // récupère le type de profil et affiche la liste pour les choix complémentaires dans la target
    const profilId = event.target.value
    const body = {
      method: 'POST',
      body: JSON.stringify({
        profilId,
      }),
    }
    this.configProfilTarget.innerHTML = window.da.loaderStimulus
    const response = await fetch(this.urlConfigProfilValue, body)
    this.configProfilTarget.innerHTML = await response.text()
  }
}
