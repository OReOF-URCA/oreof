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

        const data = new FormData()
        for (const file of files) {
            data.append('logo[]', file)
        }

        try {
            const response = await fetch(this.uploadLogoUrlValue, {
                method: 'POST',
                body: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            const json = await response.json()

            if (json.success) {
                await this._refreshLogos()
                this.fileInputTarget.value = ''
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
        if (!frame) {
            console.error('Turbo frame not found')
            return
        }
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