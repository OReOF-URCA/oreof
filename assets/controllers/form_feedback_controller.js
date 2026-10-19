import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  connect () {
    this.pending = new Map()
    this.start = this.onStart.bind(this)
    this.end = this.onEnd.bind(this)
    document.addEventListener('turbo:submit-start', this.start)
    document.addEventListener('turbo:submit-end', this.end)
  }

  disconnect () {
    document.removeEventListener('turbo:submit-start', this.start)
    document.removeEventListener('turbo:submit-end', this.end)
    for (const form of this.pending.keys()) this.restore(form)
  }

  onStart (event) {
    const form = event.target
    if (!(form instanceof HTMLFormElement) || this.pending.has(form)) return
    this.pending.set(form, form.getAttribute('aria-busy'))
    form.setAttribute('aria-busy', 'true')
    form.querySelector('[data-form-feedback]')?.remove()
  }

  restore (form) {
    if (!this.pending.has(form)) return
    const previous = this.pending.get(form)
    if (previous === null) form.removeAttribute('aria-busy')
    else form.setAttribute('aria-busy', previous)
    this.pending.delete(form)
  }

  onEnd (event) {
    const form = event.target
    if (!this.pending.has(form)) return
    this.restore(form)
    const response = event.detail?.fetchResponse
    // Rendered validation summaries already provide actionable feedback (including HTTP 200 legacy streams).
    requestAnimationFrame(() => {
      const scope = form.closest('#modal_body') || (form.isConnected ? form : null)
      if (!scope?.isConnected || scope.querySelector('[data-form-error-summary]')) return
      if (event.detail?.success) return // Success wording belongs to the server's explicit confirmation.
      const status = response?.statusCode
      const message = status === 422 ? 'Le formulaire contient des erreurs. Vérifiez les champs et les indications affichées.'
        : status === 403 ? 'Vous n’avez pas les droits nécessaires pour cette action. Votre saisie est conservée.'
          : status === 401 ? 'Votre session a expiré. Reconnectez-vous avant de reprendre votre saisie.'
            : 'Impossible de confirmer l’enregistrement. Vérifiez votre connexion et l’état des données avant de renvoyer le formulaire.'
      const feedback = document.createElement('div')
      feedback.dataset.formFeedback = ''
      feedback.dataset.formErrorSummary = ''
      feedback.setAttribute('data-controller', 'form-errors')
      feedback.setAttribute('tabindex', '-1')
      feedback.setAttribute('role', 'region')
      feedback.setAttribute('aria-label', 'Erreur de traitement du formulaire')
      feedback.className = 'mb-4 rounded-xl border border-danger-400 bg-danger-50 p-4 text-danger-900 dark:border-danger-600 dark:bg-danger-950 dark:text-danger-100'
      feedback.textContent = message
      scope.prepend(feedback)
    })
  }
}
