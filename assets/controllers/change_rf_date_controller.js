import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['input', 'preview', 'anneeText', 'detailText']
  static values = {
    currentAnnee: Number
  }

  connect () {
    this.update()

    this.form = this.element.querySelector('form') || this.element.closest('form')
    if (this.form) {
      this.submitHandler = (e) => this.onSubmit(e)
      this.form.addEventListener('submit', this.submitHandler)
    }
  }

  disconnect () {
    if (this.form && this.submitHandler) {
      this.form.removeEventListener('submit', this.submitHandler)
    }
  }

  getSubmitButton () {
    return document.querySelector('button[type="submit"][form="modal_form"]') ||
           this.element.querySelector('button[type="submit"]')
  }

  setSubmitButtonState (isValid) {
    const btn = this.getSubmitButton()
    if (btn) {
      btn.disabled = !isValid
      if (!isValid) {
        btn.classList.add('opacity-50', 'cursor-not-allowed')
      } else {
        btn.classList.remove('opacity-50', 'cursor-not-allowed')
      }
    }
  }

  onSubmit (e) {
    const val = this.inputTarget ? this.inputTarget.value : null
    if (!val || val.trim() === '') {
      e.preventDefault()
      e.stopPropagation()
      this.setSubmitButtonState(false)
      if (this.hasPreviewTarget) {
        this.previewTarget.classList.add('hidden')
      }
      return false
    }
  }

  update () {
    const val = this.inputTarget ? this.inputTarget.value : null
    if (!val || val.trim() === '') {
      this.setSubmitButtonState(false)
      if (this.hasPreviewTarget) {
        this.previewTarget.classList.add('hidden')
      }
      return
    }

    const parts = val.split('-')
    if (parts.length !== 3) {
      this.setSubmitButtonState(false)
      if (this.hasPreviewTarget) {
        this.previewTarget.classList.add('hidden')
      }
      return
    }

    const year = parseInt(parts[0], 10)
    const month = parseInt(parts[1], 10)

    if (isNaN(year) || isNaN(month)) {
      this.setSubmitButtonState(false)
      if (this.hasPreviewTarget) {
        this.previewTarget.classList.add('hidden')
      }
      return
    }

    this.setSubmitButtonState(true)

    const startYear = month >= 9 ? year : year - 1
    const endYear = startYear + 1
    const anneeLibelle = `${startYear}-${endYear}`

    if (this.hasAnneeTextTarget) {
      this.anneeTextTarget.textContent = anneeLibelle
    }

    if (this.hasDetailTextTarget) {
      if (this.hasCurrentAnneeValue && this.currentAnneeValue > 0) {
        if (startYear === this.currentAnneeValue) {
          this.detailTextTarget.textContent = `Année en cours (${anneeLibelle}) : le changement s'appliquera à la formation de cette année et sera propagé aux années suivantes.`
        } else if (startYear > this.currentAnneeValue) {
          this.detailTextTarget.textContent = `Année future (${anneeLibelle}) : le changement prendra effet à partir de la rentrée ${startYear} (formation ${anneeLibelle} et suivantes).`
        } else {
          this.detailTextTarget.textContent = `Année antérieure (${anneeLibelle}) : le changement sera rétroactif et s'appliquera depuis l'année ${anneeLibelle} jusqu'à l'année en cours et suivantes.`
        }
      } else {
        this.detailTextTarget.textContent = `Année universitaire du 01/09/${startYear} au 31/08/${endYear}. Le changement sera propagé à partir de cette année.`
      }
    }

    if (this.hasPreviewTarget) {
      this.previewTarget.classList.remove('hidden')
    }
  }
}

