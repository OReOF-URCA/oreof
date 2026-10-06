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
    urlSemestre: String,
  }

  valideDupliquer(event) {
    event.preventDefault()
    const { value } = document.getElementById('changer')
    const position = document.getElementById('position').value
    const dupliquer = document.querySelector('input[name="option"]:checked').value;

    if (value !== '' && position !== '') {
      if (confirm('Voulez-vous vraiment dupliquer cette UE ?')) {
        const body = {
          method: 'POST',
          body: JSON.stringify({
            destination: value,
            parcours: document.getElementById('parcours').value,
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
        callOut('Vous devez choisir un semestre dans la liste', 'warning')
        selectInvalid('changer', true)
      }

      if (position === '') {
        callOut('Vous devez choisir une position pour l\'UE dans la liste', 'warning')
        selectInvalid('position', true)
      }
    }
  }

  valideDeplacer(event) {
    event.preventDefault()
    const { value } = document.getElementById('changer')
    const position = document.getElementById('position').value
    if (value !== '' && position !== '') {
      if (confirm('Voulez-vous vraiment déplacer cette UE ?')) {
        const body = {
          method: 'POST',
          body: JSON.stringify({
            destination: value,
            position,
            parcours: document.getElementById('parcours').value,
          }),
        }
        fetch(this.urlValue, body).then((response) => {
          JsonResponse(response)
          this.dispatch('modalHide')
        })
      }
    } else {
      if (value === '') {
        callOut('Vous devez choisir un semestre dans la liste', 'warning')
        selectInvalid('changer', true)
      }

      if (position === '') {
        callOut('Vous devez choisir une position pour l\'UE dans la liste', 'warning')
        selectInvalid('position', true)
      }
    }
  }

  changePosition(event) {
    if (event.target.value !== '') {
      selectInvalid('position', false)
    }
  }

  async changeParcours(event) {
    if (event.target.value !== '') {
      selectInvalid('changer', false)
    }
    // update liste des semestres
    await fetch(`${this.urlSemestreValue}?parcours=${event.target.value}`).then((response) => response.json()).then(
      (data) => {
        const select = document.getElementById('changer')
        const ts = select.tomselect
        if (ts) {
          // Tom Select : le placeholder (option vide initiale) est conservé par clearOptions()
          ts.clear(true)
          ts.clearOptions()
          ts.addOptions(data.map((semestre) => ({ value: String(semestre.id), text: semestre.libelle })))
          ts.refreshOptions(false)
          return
        }
        while (select.options.length > 0) {
          select.remove(0)
        }
        let option = document.createElement('option')
        option.value = ''
        option.text = 'Choisir dans la liste le semestre'
        select.add(option, null)

        data.forEach((semestre) => {
          option = document.createElement('option')
          option.value = semestre.id
          option.text = semestre.libelle
          select.add(option, null)
        })
      },
    )
  }
}
