/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/check_all_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 07/08/2023 08:38
 */
import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static values = {
    idCheckAll: {
      type: String,
      default: 'check-all',
    },
    classCheckAll: {
      type: String,
      default: '.check-all',
    },
  }

  connect() {
    const total = document.getElementById(this.idCheckAllValue)
    if (!total) return

    const checkboxes = document.querySelectorAll(this.classCheckAllValue)
    if (checkboxes.length === 0) {
      this._updateCountersById(this.idCheckAllValue, 'Aucun élément à sélectionner')
      total.disabled = true
      return
    }

    this.updateCount(this.classCheckAllValue, this.idCheckAllValue)
  }

  checkAll(event) {
    // soit event.params.classCheck si existe et non null sinon this.classCheckAllValue
    const classCheckAll = event.params.classcheckall ? `.${event.params.classcheckall}` : this.classCheckAllValue
    const idCheckAll = event.params.idcheckall ? event.params.idcheckall : this.idCheckAllValue

    const checkboxes = document.querySelectorAll(classCheckAll)
    checkboxes.forEach((checkbox) => {
      if (checkbox.disabled === false) {
        checkbox.checked = event.target.checked
      }
    })
    this.updateCount(classCheckAll, idCheckAll)
  }

  check(event) {
    const classCheckAll = event.params.classcheckall ? `.${event.params.classcheckall}` : this.classCheckAllValue
    const idCheckAll = event.params.idcheckall ? event.params.idcheckall : this.idCheckAllValue

    this.updateCount(classCheckAll, idCheckAll)
    const checkAll = document.getElementById(idCheckAll)
    if (event.target.checked) {
      const checkboxes = document.querySelectorAll(classCheckAll)
      let checked = true
      checkboxes.forEach((checkbox) => {
        if (checkbox.disabled === false) {
          if (!checkbox.checked) {
            checked = false
          }
        }
      })
      checkAll.checked = checked
    } else {
      checkAll.checked = false
    }
  }

  updateCount(classCheckAll, idCheckAll) {
    const checkboxes = document.querySelectorAll(classCheckAll)
    let checked = 0
    let totalChecked = checkboxes.length

    checkboxes.forEach((checkbox) => {
      if (checkbox.checked) {
        checked += 1
      }
      if (checkbox.disabled === true) {
        totalChecked -= 1
      }
    })

    this._updateCountersById(idCheckAll, ` ${checked} / ${totalChecked} élément(s) sélectionné(s)`)
    window.dispatchEvent(new CustomEvent('check-all:changed', {
      detail: {
        idCheckAll,
        classCheckAll,
        checked,
        totalChecked,
      },
    }))
  }

  _updateCountersById (idCheckAll, text) {
    // Nouveau mode: synchronise tous les compteurs marqués pour un même check-all.
    const syncedCounters = document.querySelectorAll(`[data-check-all-counter="${idCheckAll}"]`)
    if (syncedCounters.length > 0) {
      syncedCounters.forEach((counter) => {
        counter.innerText = text
      })
      return
    }

    // Fallback legacy: ancien comportement (compteur frère du checkbox maître).
    const total = document.getElementById(idCheckAll)
    if (total && total.parentNode.querySelector('.total')) {
      total.parentNode.querySelector('.total').innerText = text
    }
  }
}
