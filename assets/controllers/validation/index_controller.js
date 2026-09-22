/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/centre_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 16/02/2023 08:50
 */

import { Controller } from '@hotwired/stimulus'
import callOut from '../../js/callOut'
import JsonResponse from '../../js/JsonResponse'
import updateUrl from '../../js/updateUrl'

export default class extends Controller {
  static values = {
    urlListe: String,
  }

  static targets = ['liste', 'action']

  connect() {
    this._updateListe()
  }

  async changeListe() {
    await this._updateListe()
  }

  async _updateListe() {
    const composante = document.getElementById('composante').value
    const typeValidation = document.getElementById('type_validation').value

    updateUrl({
      composante,
      typeValidation,
    })

    // mettre à jour l'URL pour ajouter ces paramètres, retirer si vide

    if (composante !== '' && typeValidation !== '') {
      const body = new URLSearchParams({
        composante,
        typeValidation,
      })

      const response = await fetch(`${this.urlListeValue}?${body.toString()}`)
      this.listeTarget.innerHTML = await response.text()
    }
  }

  async valider(event) {
    await this._valideChoix(event)
  }

  async refuser(event) {
    await this._valideChoix(event)
  }

  async reserver(event) {
    await this._valideChoix(event)
  }

  async _valideChoix(event) {
    const liste = document.querySelectorAll('.check-all:checked')
    if (liste.length === 0) {
      this.actionTarget.innerHTML = ''
      callOut('Veuillez sélectionner au moins un parcours', 'danger')
    } else {
      const parcours = []
      liste.forEach((item) => {
        parcours.push(item.value)
      })

      const body = new URLSearchParams({
        parcours,
      })

      this.actionTarget.innerHTML = ''
      const reponse = await fetch(`${event.params.url}?${body.toString()}`)
      this.actionTarget.innerHTML = await reponse.text()
    }
  }

  async valide_rf(event) {
    const liste = document.querySelectorAll('.check-all:checked')
    if (liste.length === 0) {
      callOut('Veuillez sélectionner au moins une demande', 'danger')
    } else {
      const demandes = []
      liste.forEach((item) => {
        demandes.push(item.value)
      })

      // si le champ avec un id date existe, récupérer la valeur
      const date = document.getElementById('date')
      let dateCfvu = null
      if (date !== null) {
        dateCfvu = date.value
      }

      const body = new FormData()
      body.append('demandes', demandes)
      body.append('date', dateCfvu)

      await fetch(`${event.params.url}`, {
        method: 'POST',
        body,
      }).then((response) => response.json())
        .then((data) => {
          if (data.success) {
            callOut('Demandes validées', 'success')
            window.location.reload()
          } else {
            callOut(data.message, 'danger')
          }
        })
    }
  }

  async submitChangeRfLot(event) {
    event.preventDefault()

    const form = event.currentTarget
    const response = await fetch(form.action, {
      method: form.method,
      body: new FormData(form),
      headers: {
        Accept: 'application/json',
      },
    })
    const data = await response.json()

    if (data.success) {
      callOut(data.message || 'Demandes traitées', 'success')
      window.location.reload()
      return
    }

    callOut(data.message || 'Aucune demande n’a été traitée', 'danger')
  }

  async valide_fiche(event) {
    const liste = document.querySelectorAll('.check-all:checked')
    if (liste.length === 0) {
      callOut('Veuillez sélectionner au moins une fiche EC/matière', 'danger')
    } else {
      const fiches = []
      liste.forEach((item) => {
        fiches.push(item.value)
      })

      const body = new URLSearchParams({
        fiches,
      })

      this.actionTarget.innerHTML = ''
      const reponse = await fetch(`${event.params.url}?${body.toString()}`)
      this.actionTarget.innerHTML = await reponse.text()

      // await fetch(`${event.params.url}?${body.toString()}`).then((response) => {
      //   if (response.status === 200) {
      //     callOut('Fiches validées', 'success')
      //     window.location.reload()
      //   } else {
      //     callOut('Une erreur est survenue', 'danger')
      //   }
      // })
    }
  }

  async valide_ouverture(event) {
    const liste = document.querySelectorAll('.check-all:checked')
    if (liste.length === 0) {
      callOut('Veuillez sélectionner au moins un parcours', 'danger')
    } else {
      const parcours = []
      liste.forEach((item) => {
        parcours.push(item.value)
      })

      const body = new URLSearchParams({
        parcours,
      })

      this.actionTarget.innerHTML = ''
      const reponse = await fetch(`${event.params.url}?${body.toString()}`)
      this.actionTarget.innerHTML = await reponse.text()

      // await fetch(`${event.params.url}?${body.toString()}`).then((response) => {
      //   if (response.status === 200) {
      //     callOut('Fiches validées', 'success')
      //     window.location.reload()
      //   } else {
      //     callOut('Une erreur est survenue', 'danger')
      //   }
      // })
    }
  }
}
