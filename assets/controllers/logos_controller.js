import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static values = {
        logosUrl: String,
        deleteLogoUrl: String,
    }

    async uploadLogo(event) {
        const form = document.getElementById('logo-upload-form')
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
            } else {
                json.errors?.forEach(err => console.error(err))
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
        const frame = document.querySelector('turbo-frame#logos-container')
        frame.src = this.logosUrlValue
        await frame.reload()
    }
}