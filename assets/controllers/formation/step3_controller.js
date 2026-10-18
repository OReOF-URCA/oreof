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
import { updateEtatOnglet } from '../../js/updateEtatOnglet'
import { calculEtatStep } from '../../js/calculEtatStep'
import trixEditor from '../../js/trixEditor'

export default class extends Controller {
  static targets = [
    'liste',
  ]

  static values = {
    url: String,
    urlListeParcours: String,
    urlGenereStructure: String,
    urlReload: String,
    hasParcours: Boolean,
  }

  connect () {
    // document.getElementById('form_objectifsFormation').addEventListener('trix-blur', this.saveObjectifsFormation.bind(this))
    if (this.hasParcoursValue === true) {
      this._refreshListe()
    }
  }

  saveObjectifsFormation () {
    this._save({
      field: 'objectifsFormation',
      action: 'textarea',
      value: trixEditor('form_objectifsFormation'),
    })
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

  changeSemestre (event) {
    this._save({
      action: 'structureSemestres',
      value: event.target.value,
      semestre: event.params.semestre,
    })
  }

  // changeSemestreDebut (event) {
  //   const sem = parseInt(event.target.dataset.semestredebut, 10)
  //
  //   if (sem !== event.target.value) {
  //     if (confirm('Voulez-vous vraiment modifier le semestre de début des parcours ? Cela  va modifier la structure de votre parcours/formation et peut effacer les semestres caduques/devenus inutiles.')) {
  //       this._save({
  //         action: 'semestreDebut',
  //         value: event.target.value,
  //       })
  //       event.target.dataset.semestredebut = event.target.value
  //     }
  //   }
  // }

  changeHasParcours (event) {
    const data = event.target.value

    // si des parcours sont là, warning...

    if (parseInt(data, 10) === 1) {
      document.getElementById('liste_Parcours').classList.remove('d-none')
      document.getElementById('bloc_pas_parcours').classList.add('d-none')
      this._save({
        field: 'hasParcours',
        action: 'yesNo',
        value: event.target.value,
      })

      this._refreshListe()
    } else {
      if (confirm('Cela va transformer votre parcours existant en formation sans parcours. Voulez-vous continuer ?')) {
        document.getElementById('liste_Parcours').classList.add('d-none')
        document.getElementById('bloc_pas_parcours').classList.remove('d-none')
        this._save({
          field: 'hasParcours',
          action: 'yesNo',
          value: event.target.value,
        })

        this._refreshListe()
      } else {
        // annulation : remettre l'autre radio comme cochée (cas binaire)
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
    if (confirm('Voulez-vous vraiment recopier générer la structure de la formation ? ')) {
      const result = await saveData(this.urlGenereStructureValue)
      if (result === false || result?.success === false || result?.error?.length) return
      callOut('Structure générée.', 'success')
      // todo: afficher le lien pour afficher le parcours par défaut
      // rediriger vers le parcours par défaut
      window.location.href = `${this.urlReloadValue} ? step = 3`
    }
  }

  etatStep (event) {
    calculEtatStep(this.urlValue, 3, event, 'formation')
  }

  async _save (options) {
    return saveData(this.urlValue, options).then(async (data) => {
      if (data === false || data?.success === false || data?.error?.length) return false
      await updateEtatOnglet(this.urlValue, 'onglet3', 'formation')
      return data
    })
  }
}
