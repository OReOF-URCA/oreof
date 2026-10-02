import { Controller } from '@hotwired/stimulus'
import callOut from '../js/callOut'

export default class extends Controller {
  static values = {
    url: String
  }

  static targets = ['status']

  connect() {
    this.timeout = null
    this.abortController = null
    this.fadeTimeout = null
    this.previousValues = {}

    // Bind events
    this.element.addEventListener('input', this.onInput.bind(this))
    this.element.addEventListener('change', this.onChange.bind(this))
    this.element.addEventListener('submit', this.onSubmit.bind(this))
    this.element.addEventListener('focusin', this.onFocusIn.bind(this))

    // Initial status - hidden
    this.setStatus('saved', 'Modifications enregistrées')
  }

  disconnect() {
    if (this.timeout) clearTimeout(this.timeout)
    if (this.abortController) this.abortController.abort()
    if (this.fadeTimeout) clearTimeout(this.fadeTimeout)
  }

  onFocusIn(event) {
    if (event.target.tagName === 'SELECT') {
      this.previousValues[event.target.name] = event.target.value
    }
  }

  updateRegimeBadgesForContainer(container) {
    if (!container) return
    const badgesContainer = container.querySelector('[id$="_regime_badges"]')
    if (!badgesContainer) return

    const checkedInputs = Array.from(container.querySelectorAll('input[name$="_regimeInscription[]"]:checked'))
    
    const badgesHtml = checkedInputs.map(input => {
      const val = input.value
      const lower = val.toLowerCase()
      let colorClasses = 'border border-info-300 bg-info-50 text-info-700 dark:border-info-700 dark:bg-info-900/30 dark:text-info-300'
      let icon = '<svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M251.76,88.94l-120-64a8,8,0,0,0-7.52,0l-120,64a8,8,0,0,0,0,14.12L32,117.87v48.42a15.91,15.91,0,0,0,4.06,10.65C49.16,191.53,78.51,216,128,216s78.84-24.47,91.94-39.06A15.91,15.91,0,0,0,224,166.29V117.87l27.76-14.81a8,8,0,0,0,0-14.12ZM128,41.42l94.84,50.58L128,142.58,33.16,92ZM208,166.29c-11.27,12.51-36.87,33.71-80,33.71s-68.73-21.2-80-33.71V126.4l76.24,40.66a8,8,0,0,0,7.52,0L208,126.4Z"/></svg>'

      if (lower.includes('apprentissage') || lower.includes('alternance')) {
        colorClasses = 'border border-warning-300 bg-warning-50 text-warning-700 dark:border-warning-700 dark:bg-warning-900/30 dark:text-warning-300'
        icon = '<svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M216,56H176V48a24,24,0,0,0-24-24H104A24,24,0,0,0,80,48v8H40A16,16,0,0,0,24,72V200a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V72A16,16,0,0,0,216,56ZM96,48a8,8,0,0,1,8-8h48a8,8,0,0,1,8,8v8H96ZM216,72v32H40V72ZM40,200V120H216v80Z"/></svg>'
      } else if (lower.includes('continue') || lower.includes('contrat')) {
        colorClasses = 'border border-secondary-300 bg-secondary-100 text-secondary-700 dark:border-secondary-600 dark:bg-secondary-800 dark:text-secondary-300'
        icon = '<svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M224,48H32A16,16,0,0,0,16,64V176a16,16,0,0,0,16,16H80v16a8,8,0,0,0,16,0V192h64v16a8,8,0,0,0,16,0V192h48a16,16,0,0,0,16-16V64A16,16,0,0,0,224,48ZM224,176H32V64H224V176Z"/></svg>'
      } else if (lower.includes('initiale')) {
        colorClasses = 'border border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
        icon = '<svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm-8-120a8,8,0,0,1,8-8h24a8,8,0,0,1,0,16H136v40a8,8,0,0,1-16,0Z"/></svg>'
      }

      return `<span class="inline-flex items-center font-semibold rounded-full px-2 py-0.5 text-xs gap-1 ${colorClasses}">
        ${icon}
        <span>${val}</span>
      </span>`
    }).join('')

    badgesContainer.innerHTML = badgesHtml
  }

  syncTroncCommun(target) {
    if (!target) return
    const container = target.closest('[data-annee-ordre]')
    if (!container || container.dataset.isTroncCommun !== '1') return

    const ordre = container.dataset.anneeOrdre
    const tcField = target.dataset.tcField
    if (!ordre || !tcField) return

    const otherContainers = this.element.querySelectorAll(`[data-annee-ordre="${ordre}"][data-is-tronc-commun="1"]`)
    otherContainers.forEach(otherContainer => {
      if (otherContainer === container) return
      const otherInput = otherContainer.querySelector(`[data-tc-field="${tcField}"]`)
      if (otherInput) {
        if (target.type === 'checkbox') {
          otherInput.checked = target.checked
        } else {
          otherInput.value = target.value
        }
      }
      if (tcField.startsWith('regime_')) {
        this.updateRegimeBadgesForContainer(otherContainer)
      }
    })
  }

  onInput(event) {
    this.syncTroncCommun(event.target)

    if (event.target.name && event.target.name.startsWith('annee_') && event.target.name.endsWith('_capaciteAccueil')) {
      const anneeId = event.target.name.split('_')[1]
      const capValEl = this.element.querySelector(`#annee_${anneeId}_capacite_val`)
      if (capValEl) {
        capValEl.textContent = event.target.value || '0'
      }
    }

    // Only debounce text and number fields
    if (event.target.type === 'number' || event.target.type === 'text') {
      this.setStatus('typing', 'Modifications en cours...')
      clearTimeout(this.timeout)
      this.timeout = setTimeout(() => {
        this.save()
      }, 700)
    }
  }

  onChange(event) {
    this.syncTroncCommun(event.target)

    const name = event.target.name
    const value = event.target.value

    if (name && name.includes('regimeInscription')) {
      const container = event.target.closest('[data-annee-ordre]')
      if (container) {
        this.updateRegimeBadgesForContainer(container)
      }
    }

    // 1. If it's a year opening select (annee_{id}_isOuvert)
    if (name && name.startsWith('annee_') && name.endsWith('_isOuvert')) {
      if (value === '0') {
        const confirmClose = confirm("Êtes-vous sûr de vouloir fermer cette année ? Cela réinitialisera ses capacités à 0.")
        if (confirmClose) {
          const anneeId = name.split('_')[1]
          
          const capInput = this.element.querySelector(`input[name="annee_${anneeId}_capaciteAccueil"]`)
          if (capInput) capInput.value = '0'

          const activeCheckboxes = this.element.querySelectorAll(`input[type="checkbox"][name^="annee_${anneeId}_plateforme_"][name$="_active"]`)
          activeCheckboxes.forEach(cb => cb.checked = false)

          const capInputs = this.element.querySelectorAll(`input[type="number"][name^="annee_${anneeId}_plateforme_"]`)
          capInputs.forEach(input => input.value = '0')

          this.previousValues[name] = value

          clearTimeout(this.timeout)
          this.save()
        } else {
          const prev = this.previousValues[name] || '1'
          event.target.value = prev
        }
        return
      }
    }

    // 2. If it's a parcours status select (parcours_{id}_reconduction)
    if (name && name.startsWith('parcours_') && name.endsWith('_reconduction')) {
      if (value === 'NON_OUVERTURE' || value === 'FERMETURE_DEFINITIVE') {
        const message = value === 'FERMETURE_DEFINITIVE'
          ? "Êtes-vous sûr de vouloir fermer définitivement ce parcours ? Cela fermera toutes ses années et réinitialisera leurs capacités à 0."
          : "Êtes-vous sûr de passer ce parcours en non ouvert ? Cela fermera toutes ses années et réinitialisera leurs capacités à 0."
        const confirmClose = confirm(message)
        if (confirmClose) {
          const parentCard = event.target.closest('[data-parcours-id]')
          if (parentCard) {
            const yearSelects = parentCard.querySelectorAll(`select[name^="annee_"][name$="_isOuvert"]`)
            yearSelects.forEach(select => {
              select.value = '0'
              const anneeId = select.name.split('_')[1]
              
              const capInput = this.element.querySelector(`input[name="annee_${anneeId}_capaciteAccueil"]`)
              if (capInput) capInput.value = '0'

              const activeCheckboxes = this.element.querySelectorAll(`input[type="checkbox"][name^="annee_${anneeId}_plateforme_"][name$="_active"]`)
              activeCheckboxes.forEach(cb => cb.checked = false)

              const capInputs = this.element.querySelectorAll(`input[type="number"][name^="annee_${anneeId}_plateforme_"]`)
              capInputs.forEach(input => input.value = '0')
            })
          }

          this.previousValues[name] = value

          clearTimeout(this.timeout)
          this.save()
        } else {
          const prev = this.previousValues[name] || 'OUVERT'
          event.target.value = prev
        }
        return
      }
    }

    // 3. For check boxes and other selects
    if (event.target.type === 'checkbox' || event.target.tagName === 'SELECT') {
      if (name) {
        this.previousValues[name] = value
      }
      clearTimeout(this.timeout)
      this.save()
    }
  }

  ouvrirAnnee(event) {
    const anneeId = event.params.annee || event.currentTarget.dataset.autosaveCapacitesAnneeParam
    if (!anneeId) return

    const input = this.element.querySelector(`[name="annee_${anneeId}_isOuvert"]`)
    if (input) {
      input.value = '1'
      if (input.tagName === 'SELECT') {
        input.dispatchEvent(new Event('change', { bubbles: true }))
      } else {
        this.save()
      }
    }
  }

  async onSubmit(event) {
    event.preventDefault()
    clearTimeout(this.timeout)
    await this.save(true)
  }

  async save(isManual = false) {
    this.setStatus('saving', 'Enregistrement en cours...')

    if (this.abortController) {
      this.abortController.abort()
    }
    this.abortController = new AbortController()

    const formData = new FormData(this.element)

    try {
      const response = await fetch(this.urlValue, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'text/vnd.turbo-stream.html, application/json'
        },
        body: formData,
        signal: this.abortController.signal
      })

      if (!response.ok) {
        const message = await this.errorMessage(response)
        this.setStatus('error', message)
        callOut(message, 'danger')
        return
      }

      const contentType = response.headers.get('content-type') || ''
      if (contentType.includes('text/vnd.turbo-stream.html')) {
        const html = await response.text()
        const { renderStreamMessage } = await import('@hotwired/turbo')
        renderStreamMessage(html)
        
        this.setStatus('saved', 'Brouillon enregistré automatiquement')
        if (isManual) {
          callOut('Brouillon enregistré avec succès.', 'success')
        }
      } else {
        const data = await response.json()
        if (data.success) {
          this.setStatus('saved', 'Brouillon enregistré automatiquement')
          if (isManual) {
            callOut(data.message || 'Brouillon enregistré avec succès.', 'success')
          }
        } else {
          this.setStatus('error', data.message || 'Erreur lors de l\'enregistrement.')
          if (isManual) {
            callOut(data.message || 'Erreur lors de l\'enregistrement.', 'error')
          }
        }
      }
    } catch (error) {
      if (error.name === 'AbortError') {
        // Ignored since it was cancelled by a newer request
        return
      }
      console.error('Autosave error:', error)
      this.setStatus('error', 'Erreur de connexion.')
      callOut('Impossible de sauvegarder le brouillon. Vérifiez votre connexion.', 'danger')
    }
  }

  // Message lisible pour une réponse en erreur : celui du serveur s'il est fourni (JSON), sinon selon le code HTTP.
  async errorMessage(response) {
    try {
      const data = await response.clone().json()
      if (data && data.message) return data.message
    } catch {
      // réponse non JSON (page d'erreur HTML)
    }

    if (response.status === 403) return 'Vous n\'avez pas les droits pour modifier cette offre.'
    if (response.status === 400 || response.status === 419) return 'Votre session a expiré : rechargez la page puis recommencez.'

    return 'Erreur serveur : les dernières modifications n\'ont pas été enregistrées. Réessayez.'
  }

  setStatus(state, message) {
    if (!this.hasStatusTarget) return

    const target = this.statusTarget
    target.classList.remove('opacity-0')
    target.classList.add('opacity-100')

    // Reset classes
    target.className = 'text-sm font-semibold flex items-center gap-1.5 transition-all duration-300'

    let iconHtml = ''
    switch (state) {
      case 'saved':
        target.classList.add('text-green-600')
        iconHtml = `
          <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
          </svg>
        `
        // Auto-fade status after 3 seconds to keep UI clean
        clearTimeout(this.fadeTimeout)
        this.fadeTimeout = setTimeout(() => {
          target.classList.remove('opacity-100')
          target.classList.add('opacity-0')
        }, 3000)
        break

      case 'saving':
        target.classList.add('text-indigo-600')
        iconHtml = `
          <svg class="w-4 h-4 text-indigo-500 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        `
        break

      case 'typing':
        target.classList.add('text-slate-500')
        iconHtml = `
          <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
          </svg>
        `
        break

      case 'error':
        target.classList.add('text-rose-600')
        iconHtml = `
          <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
          </svg>
        `
        break
    }

    target.innerHTML = `${iconHtml}<span>${message}</span>`
  }
}
