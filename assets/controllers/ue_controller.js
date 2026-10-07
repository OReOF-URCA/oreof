/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/ue_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/03/2023 11:22
 */

import { Controller } from '@hotwired/stimulus'
import { useDebounce } from 'stimulus-use'

export default class extends Controller {
  static debounces = ['changeNatureUeEcTexte', 'changeTypeUeTexte']
  static targets = ['natureSection']

  connect () {
    useDebounce(this)
  }

  ajoutTypeUe (event) {
    event.preventDefault()
    document.getElementById('typeUeTexte').classList.remove('d-none')
  }

  changeTypeUeTexte (event) {
    event.preventDefault()
    document.getElementById('ue_typeUe').disabled = event.currentTarget.value.length > 0
  }

  changeNatureUe (event) {
    //todo: remettre warning si changement et les risques
    const val = (event && event.target && event.target.value) ? event.target.value : document.querySelector('input[name="ue[natureUeEc]"]:checked')?.value
    this.natureSectionTargets.forEach(el => {
      const matches = (el.dataset.nature === 'nature_' + val)
      // toggle visibility
      el.classList.toggle('hidden', !matches)

      // find form controls inside the section
      const controls = el.querySelectorAll('input, select, textarea')
      controls.forEach((ctrl) => {
        try {
          if (matches) {
            // enable and mark required
            ctrl.removeAttribute('disabled')
            // set required only for meaningful inputs (ignore buttons)
            if (['INPUT', 'SELECT', 'TEXTAREA'].includes(ctrl.tagName)) {
              // avoid setting required on hidden or readonly controls
              if (ctrl.type !== 'hidden' && !ctrl.readOnly && ctrl.name !== 'element_constitutif[ficheMatiere][new]') {
                ctrl.required = true
                ctrl.setAttribute('aria-required', 'true')
              }
            }
          } else {
            // disable and remove required
            ctrl.setAttribute('disabled', 'disabled')
            if (['INPUT', 'SELECT', 'TEXTAREA'].includes(ctrl.tagName)) {
              ctrl.required = false
              ctrl.removeAttribute('aria-required')
            }
          }
        } catch {
          // ignore control manipulation errors
        }
      })
    })
  }
}
