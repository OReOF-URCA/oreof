/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/parcours/bcc_controller.js
 * @author davidannebicque
 * @project oreof
 */
import { Controller } from '@hotwired/stimulus'
import callOut from '../../js/callOut'
import JsonResponse from '../../js/JsonResponse'

export default class extends Controller {
    static targets = ['section', 'tabButton']
  static values = {
    urlUpdateComptence: String,
  }

    switchTab(event) {
        const clickedTab = event.currentTarget.closest('.app-tab') || event.currentTarget
        const currentSemester = clickedTab.dataset.semester
        const tabs = this.hasTabButtonTargets ? this.tabButtonTargets : this.element.querySelectorAll('.app-tab')

        tabs.forEach((btn) => {
            const isCurrent = (btn === clickedTab || (currentSemester && btn.dataset.semester === currentSemester))
            if (isCurrent) {
                btn.classList.add('app-tab-active', 'active')
                btn.setAttribute('aria-selected', 'true')
                btn.setAttribute('tabindex', '0')
            } else {
                btn.classList.remove('app-tab-active', 'active')
                btn.setAttribute('aria-selected', 'false')
                btn.setAttribute('tabindex', '-1')
            }
        })
    }

    filterSemester(event) {
        const sem = event.currentTarget.dataset.semester
        if (this.hasTabButtonTargets) {
            this.tabButtonTargets.forEach((btn) => {
                if (btn === event.currentTarget) {
                    btn.classList.add('bg-primary-600', 'text-white', 'shadow-sm')
                    btn.classList.remove('bg-surface', 'text-secondary-700', 'hover:bg-secondary-100', 'dark:bg-secondary-800', 'dark:text-secondary-200')
                } else {
                    btn.classList.remove('bg-primary-600', 'text-white', 'shadow-sm')
                    btn.classList.add('bg-surface', 'text-secondary-700', 'hover:bg-secondary-100', 'dark:bg-secondary-800', 'dark:text-secondary-200')
                }
            })
        }

        if (this.hasSectionTargets) {
            this.sectionTargets.forEach((sec) => {
                if (sem === 'all' || sec.dataset.semester === sem) {
                    sec.classList.remove('hidden')
                } else {
                    sec.classList.add('hidden')
                }
            })
        }
    }

  changeBcc(event) {
      const bccId = event.params.id
      const isChecked = event.currentTarget.checked
      const competenceCheckboxes = this.element.querySelectorAll(`.bcc_${bccId}`)
      competenceCheckboxes.forEach((checkbox) => {
          checkbox.checked = isChecked
      })
  }

    changeCompetence(event) {
        const bccId = event.params.bccId
        const bccCheckbox = this.element.querySelector(`#bcc_${bccId}`)
        const competenceCheckboxes = this.element.querySelectorAll(`.bcc_${bccId}`)
        if (bccCheckbox && competenceCheckboxes.length > 0) {
            const anyChecked = Array.from(competenceCheckboxes).some((cb) => cb.checked)
            bccCheckbox.checked = anyChecked
    }
  }

    toggleAll(event) {
        event.preventDefault()
        const checkboxes = this.element.querySelectorAll('input[type="checkbox"]')
        const allChecked = Array.from(checkboxes).every((cb) => cb.checked)
        checkboxes.forEach((cb) => {
            cb.checked = !allChecked
        })
    }

  sauvegardeFormModal(event) {
    event.preventDefault()

    const form = this.element.getElementsByTagName('form')[0]
    fetch(form.action, {
      method: form.method,
      body: new URLSearchParams(new FormData(form)),
    })
      .then((response) => response.json())
      .then(async () => {
        callOut('Sauvegarde effectuée', 'success')
        this.dispatch('modalClose')
      })
  }

  async updateCompetence(event) {
      const isChecked = event.currentTarget.checked
      const compId = event.params.competence
      const ecId = event.params.ec
      const semOrdre = event.currentTarget.dataset.semester

      // Mise à jour optimiste des compteurs dans le DOM
      if (semOrdre && compId && ecId) {
          this.updateCounters(semOrdre, compId, ecId, isChecked)
      }

    const body = new FormData()
      body.append('competence', compId)
      body.append('ec', ecId)
      body.append('checked', isChecked)

      try {
          const response = await fetch(this.urlUpdateComptenceValue, {
              method: 'POST',
              body,
          })
      JsonResponse(response)
      } catch (e) {
          callOut('Erreur lors de la mise à jour', 'danger')
      }
  }

    updateCounters(semOrdre, compId, ecId, isChecked) {
        const delta = isChecked ? 1 : -1

        // Compteur ligne compétence
        const compTotalEl = this.element.querySelector(`#total_comp_${semOrdre}_${compId}`)
        if (compTotalEl) {
            const current = parseInt(compTotalEl.textContent.trim(), 10) || 0
            const next = Math.max(0, current + delta)
            compTotalEl.textContent = next
            if (next === 0) {
                compTotalEl.className = 'inline-flex items-center justify-center min-w-6 px-1.5 py-0.5 rounded-full text-[11px] font-bold bg-danger-100 text-danger-700 dark:bg-danger-900/40 dark:text-danger-300'
            } else {
                compTotalEl.className = 'inline-flex items-center justify-center min-w-6 px-1.5 py-0.5 rounded-full text-[11px] font-bold bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300'
            }
        }

        // Compteur colonne EC
        const ecTotalEl = this.element.querySelector(`#total_ec_${semOrdre}_${ecId}`)
        if (ecTotalEl) {
            const current = parseInt(ecTotalEl.textContent.trim(), 10) || 0
            const next = Math.max(0, current + delta)
            ecTotalEl.textContent = next
            if (next === 0) {
                ecTotalEl.className = 'inline-flex items-center justify-center min-w-6 px-1.5 py-0.5 rounded-full text-[11px] font-bold bg-warning-100 text-warning-800 dark:bg-warning-900/40 dark:text-warning-300'
            } else {
                ecTotalEl.className = 'inline-flex items-center justify-center min-w-6 px-1.5 py-0.5 rounded-full text-[11px] font-bold bg-secondary-100 text-secondary-800 dark:bg-secondary-700 dark:text-secondary-200'
            }
        }
  }
}
