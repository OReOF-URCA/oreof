/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/ec/mutualise_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 08/03/2023 09:25
 */

import { Controller } from '@hotwired/stimulus'
import callOut from '../../js/callOut'
import JsonResponse from '../../js/JsonResponse';
import selectInvalid from '../../js/selectInvalid'

export default class extends Controller {
  static values = {
    url: String,
  }

  valideDupliquer(event) {
    event.preventDefault()
    const { value } = document.getElementById('changer')
    const position = document.getElementById('position').value
    // récupérer la valeur de la radio "dupliquer"
    const dupliquer = document.querySelector('input[name="option"]:checked').value;

    if (value !== '' && position !== '') {
      if (confirm('Voulez-vous vraiment dupliquer ce semestre ?')) {
        const body = {
          method: 'POST',
          body: JSON.stringify({
            destination: value,
            position,
            dupliquer,
          }),
        }
        fetch(this.urlValue, body).then((response) => {
          JsonResponse(response)
          this.dispatch('modalHide')
        })
      }
    } else {
      if (value === '') {
        callOut('Vous devez choisir un parcours dans la liste', 'warning')
        selectInvalid('changer', true)
      }

      if (position === '') {
        callOut('Vous devez choisir une position pour le semestre dans la liste', 'warning')
        selectInvalid('position', true)
      }
    }
  }

  initSemestre(event) {
    const body = {
      method: 'POST',
      body: JSON.stringify({
        position: event.params.ordre,
        action: 'init',
      }),
    }
    fetch(this.urlValue, body).then((response) => {
      JsonResponse(response)
      this.dispatch('modalHide')
    })
  }

  semestreNonDispense(event) {
    const body = {
      method: 'POST',
      body: JSON.stringify({
        position: event.params.ordre,
        action: 'nonDispense',
      }),
    }
    fetch(this.urlValue, body).then((response) => {
      JsonResponse(response)
      this.dispatch('modalHide')
    })
  }

  modificationOuverture(event) {
    const body = {
      method: 'POST',
      body: JSON.stringify({
        check: event.target.checked,
      }),
    }
    fetch(this.urlValue, body).then((response) => {
      JsonResponse(response)
    })
  }

  valideDeplacer(event) {
    event.preventDefault()
    const { value } = document.getElementById('changer')
    const position = document.getElementById('position').value
    if (value !== '' && position !== '') {
      if (confirm('Voulez-vous vraiment déplacer ce semestre ? Si un semestre est déjà présent à la position choisir, il sera supprimé.')) {
        const body = {
          method: 'POST',
          body: JSON.stringify({
            destination: value,
            position,
          }),
        }
        fetch(this.urlValue, body).then((response) => {
          JsonResponse(response)
          this.dispatch('modalHide')
        })
      }
    } else {
      if (value === '') {
        callOut('Vous devez choisir un parcours dans la liste', 'warning')
        selectInvalid('changer', true)
      }

      if (position === '') {
        callOut('Vous devez choisir une position pour le semestre dans la liste', 'warning')
        selectInvalid('position', true)
      }
    }
  }

  changePosition(event) {
    if (event.target.value !== '') {
      selectInvalid('position', false)
    }
  }

  changeParcours(event) {
    if (event.target.value !== '') {
      selectInvalid('changer', false)
    }
  }
}
