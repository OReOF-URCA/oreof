/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/offre_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 28/10/2025 09:56
 */

import { Controller } from '@hotwired/stimulus'
import callOut from '../js/callOut'
import JsonResponse from '../js/JsonResponse'

export default class extends Controller {
  static values = {
    url: String,
  }

  scanParcours () {
    const rows = this.element.querySelectorAll('[data-parcours-open]')
    rows.forEach(row => {
      const openValue = row.dataset.parcoursOpen
      const open = openValue === 'OUVERT'

      const parcoursId = row.dataset.parcoursId
      if (parcoursId) {
        const extraBlocks = this.element.querySelectorAll(`[data-parcours-id="${parcoursId}"]`)
        extraBlocks.forEach(block => {
          if (block === row) return
          const extraControls = block.querySelectorAll('input[type="checkbox"], input[type="radio"], select, input:not([type]), input[type="text"], button')
          extraControls.forEach(el => {
            el.disabled = !open
            if (!open) {
              el.setAttribute('aria-disabled', 'true')
            } else {
              el.removeAttribute('aria-disabled')
            }
          })
        })
      }
    })
  }

  scanFormation () {
    const rows = this.element.querySelectorAll('[data-formation-open]')
    rows.forEach(row => {
      const openValue = row.dataset.formationOpen
      const open = openValue === 'OUVERT'

      const parcoursId = row.dataset.formationId
      if (parcoursId) {
        const extraBlocks = this.element.querySelectorAll(`[data-formation-id="${parcoursId}"]`)
        extraBlocks.forEach(block => {
          if (block === row) return
          const extraControls = block.querySelectorAll('input[type="checkbox"], input[type="radio"], select, input:not([type]), input[type="text"], button')
          extraControls.forEach(el => {
            el.disabled = !open
            if (!open) {
              el.setAttribute('aria-disabled', 'true')
            } else {
              el.removeAttribute('aria-disabled')
            }
          })
        })
      }
    })
  }

  async changeOuverture (event) {
    const body = {
      method: 'POST',
      body: JSON.stringify({
        value: event.target.value,
        id: event.params.id,
        action: 'changeOuverture'
      }),
    }

    await fetch(this.urlValue, body).then((response) => JsonResponse(response))

    //si id commence par parcours_
    if (event.params.id.startsWith('parcours_')) {
      const tr = event.target.closest('tr')
      if (tr) {
        tr.dataset.parcoursOpen = event.target.value
      }
      this.scanParcours()
    }

    if (event.params.id.startsWith('formation_')) {
      const tr = event.target.closest('tr')
      if (tr) {
        tr.dataset.formationOpen = event.target.value
      }
      this.scanFormation()
    }
  }

  async changeCapacite (event) {
    const body = {
      method: 'POST',
      body: JSON.stringify({
        id: event.params.id,
        value: event.target.value,
        action: 'changeCapacite'
      }),
    }

    await fetch(this.urlValue, body).then((response) => JsonResponse(response))
  }

  async changeCapaciteParcours (event) {
    const body = {
      method: 'POST',
      body: JSON.stringify({
        id: event.params.id,
        value: event.target.value,
        action: 'changeCapaciteParcours'
      }),
    }

    await fetch(this.urlValue, body).then((response) => JsonResponse(response))
  }

  async changeCapaciteMention (event) {
    const body = {
      method: 'POST',
      body: JSON.stringify({
        id: event.params.id,
        value: event.target.value,
        action: 'changeCapaciteMention'
      }),
    }

    await fetch(this.urlValue, body).then((response) => JsonResponse(response))
  }

  async changeOuvertureAnnee (event) {
    const body = {
      method: 'POST',
      body: JSON.stringify({
        id: event.params.id,
        action: 'changeOuvertureAnnee'
      }),
    }

    await fetch(this.urlValue, body).then((response) => JsonResponse(response))

    const tr = event.target.closest('tr')
    if (tr) {
      const open = event.target.checked
      const controls = tr.querySelectorAll('input[type="checkbox"], input[type="radio"], select, input:not([type]), input[type="text"], button')
      controls.forEach(el => {
        if (el === event.target) return
        el.disabled = !open
        if (!open) {
          el.setAttribute('aria-disabled', 'true')
        } else {
          el.removeAttribute('aria-disabled')
        }
      })
    }
  }

  async changeRecrutementAnnee (event) {
    const body = {
      method: 'POST',
      body: JSON.stringify({
        id: event.params.id,
        action: 'changeRecrutementAnnee'
      }),
    }

    await fetch(this.urlValue, body).then((response) => JsonResponse(response))
  }

  async changeHasCapacite (event) {
    const body = {
      method: 'POST',
      body: JSON.stringify({
        id: event.params.id,
        action: 'changeHasCapacite'
      }),
    }

    await fetch(this.urlValue, body).then((response) => JsonResponse(response))

    const tr = event.target.closest('tr')
    if (tr) {
      const open = event.target.checked
      const name = event.target.name
      const capaciteFieldName = name.replace('has_', '')
      const capaciteField = tr.querySelector(`[id="${capaciteFieldName}"]`)

      if (capaciteField) {
        capaciteField.disabled = open
        if (open) {
          capaciteField.setAttribute('aria-disabled', 'true')
        } else {
          capaciteField.removeAttribute('aria-disabled')
        }
      }
    }
  }

  ouvreAnnee (event) {
    const idParcours = event.params.parcours
    const trs = document.querySelectorAll(`tr.parc_${idParcours}`)
    trs.forEach(tr => {
      //si la classe d-none présente, retirer, sinon ajouter
      if (tr.classList.contains('d-none')) {
        tr.classList.remove('d-none')
      } else {
        tr.classList.add('d-none')
      }
    })
  }

}
