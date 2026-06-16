import { Controller } from '@hotwired/stimulus'
import { saveData } from '../js/saveData'

export default class extends Controller {
    static values = {
        uploadUrl: String,
        deleteUrl: String,
        saveUrl: String,
    }

    static targets = ['fileInput', 'error', 'submit']

    // Case « j'ai terminé » : vérifie côté serveur la présence du PDF (étape « maquette »).
    async etatStep(event) {
        const checked = event.target.checked
        const data = await saveData(this.saveUrlValue, {
            action: 'etatStep',
            value: 'maquette',
            isChecked: checked,
        })

        const previousError = document.getElementById('alert-error')
        if (previousError) previousError.remove()

        const alert = document.getElementById('alertEtatStructure')

        if (data === true) {
            alert.classList.toggle('alert-success', checked)
            alert.classList.toggle('alert-warning', !checked)
            this._setBadge(checked ? 'complete' : 'en-cours')
        } else {
            event.target.checked = false
            let liste = '<ul>'
            ;(data.error || []).forEach((err) => { liste += `<li>${err}</li>` })
            liste += '</ul>'
            alert.insertAdjacentHTML('beforeend',
                `<div class="alert alert-danger border-2 d-flex align-items-center mt-2" role="alert" id="alert-error">
                    <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-3"></span></div>
                    <p class="mb-0 flex-1">${liste}</p>
                </div>`)
        }
    }

    _setBadge(state) {
        const badge = document.getElementById('parcours_ongletmaquette')
        if (!badge) return
        badge.classList.remove('state-complete', 'state-en-cours', 'state-vide')
        badge.classList.add(`state-${state}`)
    }

    async upload(event) {
        event.preventDefault()
        this._hideError()

        const file = this.hasFileInputTarget ? this.fileInputTarget.files[0] : null
        if (!file) {
            this._showError('Veuillez sélectionner un fichier PDF.')
            return
        }

        const body = new FormData()
        body.append('file', file)
        await this._send(this.uploadUrlValue, body)
    }

    async delete(event) {
        event.preventDefault()
        this._hideError()

        if (!window.confirm('Supprimer la maquette de ce parcours ?')) return
        await this._send(this.deleteUrlValue, new FormData())
    }

    async _send(url, body) {
        if (this.hasSubmitTarget) this.submitTarget.disabled = true
        try {
            const response = await fetch(url, {
                method: 'POST',
                body,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
            const data = await response.json().catch(() => ({}))

            if (response.ok) {
                // Recharge l'onglet courant du wizard pour refléter le nouvel état.
                window.dispatchEvent(new Event('base:refreshStep'))
            } else {
                this._showError(data.message ?? 'Une erreur est survenue.')
            }
        } catch {
            this._showError('Erreur de connexion.')
        } finally {
            if (this.hasSubmitTarget) this.submitTarget.disabled = false
        }
    }

    _showError(msg) {
        if (this.hasErrorTarget) {
            this.errorTarget.textContent = msg
            this.errorTarget.classList.remove('d-none')
        }
    }

    _hideError() {
        if (this.hasErrorTarget) this.errorTarget.classList.add('d-none')
    }
}
