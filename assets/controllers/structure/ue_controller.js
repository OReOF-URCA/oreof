/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/structure/ue_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/03/2023 16:12
 */

import { Controller } from '@hotwired/stimulus'
import { Modal } from 'bootstrap'
import callOut from '../../js/callOut'
import updateUrl from '../../js/updateUrl'

export default class extends Controller {
  static targets = ['detail']

  static values = {
    ue: Number,
    parcours: Number,
    url: String,
    urlDetail: String,
    semestreAffiche: String,
    ueAffichee: String,
  }

  async connect() {
    if (parseInt(this.ueAfficheeValue, 10) === this.ueValue) {
      const btn = document.getElementById(`btn_ue_detail_${this.ueValue}`)
      const response = await fetch(this.urlDetailValue)
      this.detailTarget.innerHTML = await response.text()
      btn.dataset.state = 'open'
      btn.firstElementChild.classList.remove('fa-caret-right')
      btn.firstElementChild.classList.add('fa-caret-down')
      document.getElementById(`detail_ue_${this.ueValue}_${this.parcoursValue}`).classList.remove('d-none')
    }
  }

  async deplacerUe(event) {
    event.preventDefault()
    const { url } = event.params
    await fetch(url).then(() => {
      callOut('UE déplacée', 'success')
      this.dispatch('refreshListe')
    })
  }

  detail(event) {
    if (event.target.dataset.state === 'open') {
      document.getElementById(`detail_ue_${event.params.ue}_${event.params.parcours}`).classList.add('d-none')
      this.detailTarget.innerHTML = ''
      event.target.dataset.state = 'close'
      event.target.firstElementChild.classList.add('fa-caret-right')
      event.target.firstElementChild.classList.remove('fa-caret-down')
      updateUrl({ ue: event.params.ue }, 'remove')
    } else {
      this._listeEc(event)
      event.target.dataset.state = 'open'
      event.target.firstElementChild.classList.remove('fa-caret-right')
      event.target.firstElementChild.classList.add('fa-caret-down')
      updateUrl({ ue: event.params.ue })
    }
  }

  async refreshListeEc(event) { // l'event pourrait emetre le numéro d'UE et de parcours
    if (event.detail.ue === this.ueValue && event.detail.parcours === this.parcoursValue) {
      await this._listeEc(event)
      // mise à jour des ECTS de l'UE et du Semestre
      const response = await fetch(this.urlValue)
      const data = await response.json()
      const ectsUe = document.getElementById(`ects_ue_${event.detail.ue}_${event.detail.parcours}`)
      const ectsSemestre = document.getElementById(`ects_semestre_${data.idSemestre}_${event.detail.parcours}`)
      if (data.ue > 0 && data.ue < 30) {
        // todo: vérifier les valeurs avec le type de diplôme (6 pour les L par exemple)
        ectsUe.innerHTML = `<span class="badge bg-success me-2">${data.ue} ECTS</span>`
      } else {
        ectsUe.innerHTML = `<span class="badge bg-danger me-2">${data.ue} ECTS</span>`
      }

      if (data.semestre === 30) {
        ectsSemestre.innerHTML = `<span class="badge bg-success me-2">${data.semestre} ECTS</span>`
      } else {
        ectsSemestre.innerHTML = `<span class="badge bg-danger me-2">${data.semestre} ECTS</span>`
      }
    }
  }

  async _listeEc(event) {
    const response = await fetch(event.params.url)
    this.detailTarget.innerHTML = await response.text()
    document.getElementById(`detail_ue_${event.params.ue}_${event.params.parcours}`).classList.remove('d-none')
  }

  delete(event) {
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
        this.dispatch('refreshListe')
      })
    })
  }
}
