/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/formation/step3_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 15/03/2023 21:28
 */

import { Controller } from '@hotwired/stimulus'
import { saveData } from '../../js/saveData'
import callOut from '../../js/callOut'

export default class extends Controller {
  static targets = [
    'liste',
  ]

  static values = {
    urlListeParcours: String,
    urlGenereStructure: String,
    urlReload: String,
    hasParcours: Boolean,
  }

  connect () {
    if (this.hasParcoursValue === true) {
      this._refreshListe()
    }
  }

  async dupliquerParcours (event) {
    event.preventDefault()
    if (confirm('Voulez-vous vraiment dupliquer ce parcours et toutes les informations associées ?')) {
      const { url } = event.params
      await fetch(url).then(() => {
        callOut('Duplication effectuée', 'success')
        this._refreshListe()
      })
    }
  }

  async deleteParcours (event) {
    event.preventDefault()
    if (confirm('Voulez-vous vraiment supprimer ce parcours et toutes les informations associées ?')) {
      const { url } = event.params
      const { csrf } = event.params
      const body = {
        method: 'DELETE',
        body: JSON.stringify({
          csrf,
        }),
      }
      await fetch(url, body).then(() => {
        callOut('Suppression effectuée', 'success')
        this._refreshListe()
      })
    }
  }

  async _refreshListe () {
    this.listeTarget.innerHTML = window.da.loaderStimulus
    const response = await fetch(this.urlListeParcoursValue)
    this.listeTarget.innerHTML = await response.text()
  }

  changeHasParcours (event) {
    const data = event.target.value

    if (parseInt(data, 10) === 1) {
      document.getElementById('liste_Parcours')?.classList.remove('d-none')
      document.getElementById('bloc_pas_parcours')?.classList.add('d-none')
      this._refreshListe()
    } else {
      if (confirm('Cela va transformer votre parcours existant en formation sans parcours. Voulez-vous continuer ?')) {
        document.getElementById('liste_Parcours')?.classList.add('d-none')
        document.getElementById('bloc_pas_parcours')?.classList.remove('d-none')
        this._refreshListe()
      } else {
        // annulation : remettre l'autre radio comme cochée (cas binaire) et empêcher la sauvegarde
        event.preventDefault()
        event.stopImmediatePropagation()
        const name = event.target.name
        const radios = Array.from(document.querySelectorAll(`input[name="${name}"]`))
        const previous = radios.find(r => r.value !== event.target.value) || radios[0]
        if (previous) {
          previous.checked = true
          event.target.checked = false
        }
      }
    }
  }

  async genereStructurePasParcours () {
    if (confirm('Voulez-vous vraiment générer la structure de la formation ? ')) {
      const result = await saveData(this.urlGenereStructureValue)
      if (result === false || result?.success === false || result?.error?.length) return
      callOut('Structure générée.', 'success')
      window.location.href = `${this.urlReloadValue}?step=3`
    }
  }
}

