import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static values = {
        logosUrl: String,
        deleteLogoUrl: String,
        uploadLogoUrl: String,
    }

    static targets = ['fileInput']

    async uploadLogo(event) {
        if (!this.hasFileInputTarget) {
            console.error('File input not found')
            return
        }

        const files = this.fileInputTarget.files
        if (!files || files.length === 0) {
            this._showErrors(['Veuillez sélectionner un fichier.'])
            return
        }

        const existingLogos = this.element.querySelectorAll('turbo-frame img')
        if (existingLogos.length >= 2) {
            this._showErrors(['Le nombre maximum de logos (2) est atteint. Veuillez en supprimer un avant d\'en ajouter un nouveau.'])
            this.fileInputTarget.value = ''
            return
        }

        const data = new FormData()
        data.append('logo[]', files[0]) // 2 logos max

        const fileInput = this.fileInputTarget
        fileInput.value = ''

        try {
            const response = await fetch(this.uploadLogoUrlValue, {
                method: 'POST',
                body: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            const json = await response.json()

            if (json.success) {
                await this._refreshLogos()
                const errContainer = this.element.querySelector('#logo-upload-errors')
                if (errContainer) errContainer.innerHTML = ''
            } else {
                this._showErrors(json.errors ?? ['Une erreur est survenue lors de l\'upload.'])
            }
        } catch (err) {
            console.error('Upload failed:', err)
        }
    }

    async deleteLogo(event) {
        event.preventDefault()
        event.stopPropagation()

        if (!confirm('Êtes-vous sûr de vouloir supprimer ce logo ?')) return

        const filename = event.currentTarget.dataset.filename
        const logoItem = event.currentTarget.closest('.logo-container')

        try {
            const response = await fetch(this.deleteLogoUrlValue, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ filename })
            })
            const json = await response.json()

            if (json.success) {
                logoItem?.remove()
            } else {
                console.error(json.error)
            }
        } catch (err) {
            console.error('Delete failed:', err)
        }
    }

    async _refreshLogos() {
        const frame = this.element.querySelector('turbo-frame')
        if (!frame) return
        frame.src = ''
        frame.src = this.logosUrlValue
    }

    _showErrors(errors) {
        const container = this.element.querySelector('#logo-upload-errors')
        if (!container) return
        container.innerHTML = errors.map(e =>
            `<div class="alert alert-danger py-1 px-2 mb-1" style="font-size: 13px;">${e}</div>`
        ).join('')
        setTimeout(() => container.innerHTML = '', 5000)
    }
}