import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static values = {
        logosUrl: String,
        deleteLogoUrl: String,
    }

    async uploadLogo(event) {
        const form = this.element.querySelector('form')
        if (!form) {
            console.error('Logo upload form not found')
            return
        }
        const data = new FormData(form)

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            const json = await response.json()

            if (json.success) {
                await this._refreshLogos()
                form.reset()
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
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce logo ?')) {
            return
        }

        const filename = event.currentTarget.dataset.filename

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
                await this._refreshLogos()
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
        frame.src = this.logosUrlValue
        await frame.reload()
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