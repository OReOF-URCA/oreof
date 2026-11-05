/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/parcours/step3_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 14:28
 */

import { Controller } from '@hotwired/stimulus'
import { Modal } from 'bootstrap'
import callOut from '../../js/callOut'
import { saveData } from '../../js/saveData'
import { updateEtatOnglet } from '../../js/updateEtatOnglet'
import { calculEtatStep } from '../../js/calculEtatStep'

export default class extends Controller {
  static targets = [
    'liste',
    'bcctransverse',
  ]

  static values = {
    url: String,
    urlListe: String,
    mutualise: { type: Boolean, default: false },
  }

  etatStep(event) {
    calculEtatStep(this.urlValue, 3, event, 'parcours')
  }

  connect() {
    this._updateListe()
  }

  delete(event) {
    event.preventDefault()
    const { url } = event.params
    console.log(url)
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
        // todo: trouver le moyen de supprimer l'event sinon l'ancien reste actif
      })
    })
  }

  async deplacerBcc(event) {
    event.preventDefault()
    const { url } = event.params
    await fetch(url).then(() => {
      callOut('Bloc de compétence déplacé', 'success')
      this._updateListe()
    })
  }

  async deplacerCc(event) {
    event.preventDefault()
    const { url } = event.params
    await fetch(url).then(() => {
      callOut('Compétence déplacée', 'success')
      this._updateListe()
    })
  }

  async duplicate(event) {
    event.preventDefault()
    const { url } = event.params
    await fetch(url).then(() => {
      callOut('Duplication effectuée', 'success')
      this._updateListe()
    })
  }

  refreshListe() {
    this._updateListe()
  }

  async recopieBcc() {
    const elt = document.getElementById('parcoursSource')
    const nameParcours = elt.options[elt.selectedIndex].text
    if (confirm(`Voulez-vous vraiment recopier les BCC du parcours "${nameParcours}" ? `)) {
      const result = await this._save({
        action: 'recopieBcc',
        value: document.getElementById('parcoursSource').value,
      })
      if (result === false) return
      callOut('Recopie effectuée.', 'success')
      this._updateListe()
    }
  }

  resetBcc() {
    if (confirm('Voulez-vous vraiment supprimer les BCC et les compétences du parcours ? ')) {
      this._save({
        action: 'resetBcc',
      }).then((result) => {
        if (result === false) return
        callOut('Réinitialisation des BCC effectuée.', 'success')
        this._updateListe()
      })
    }
  }

  async _updateListe() {
    this.listeTarget.innerHTML = window.da.loaderStimulus
    const response = await fetch(this.urlListeValue)
    if (document.getElementById('alertEtatStructure') && document.getElementById('alertEtatStructure').querySelector('.alert-danger')) {
      document.getElementById('alertEtatStructure').querySelector('.alert-danger').remove()
    }
    if (!this.mutualiseValue) {
      await updateEtatOnglet(this.urlValue, 'onglet3', 'parcours')
    }

    this.listeTarget.innerHTML = await response.text()
  }

  async _save(options) {
    return saveData(this.urlValue, options).then(async (data) => {
      if (data === false || data?.success === false || data?.error?.length) return false
      await updateEtatOnglet(this.urlValue, 'onglet3', 'parcours')
      return data
    })
  }
}
