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

  onInput(event) {
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
    const name = event.target.name
    const value = event.target.value

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
      if (value === 'NON_OUVERTURE') {
        const confirmClose = confirm("Êtes-vous sûr de vouloir fermer ce parcours ? Cela fermera toutes ses années et réinitialisera leurs capacités à 0.")
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
        throw new Error('Network response was not ok')
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
