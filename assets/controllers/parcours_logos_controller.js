import {Controller} from '@hotwired/stimulus'

export default class extends Controller {
    static values = {
        logosUrl: String,
        uploadLogoUrl: String,
        updateLogoUrl: String,
        deleteLogoUrl: String,
    }

    static targets = ['fileInput', 'urlInput', 'errors', 'submitBtn']

    async uploadLogo(event) {
        if (event) {
            event.preventDefault()
            event.stopPropagation()
        }

        if (!this.hasFileInputTarget) {
            console.error('File input target not found')
            return
        }

        const files = this.fileInputTarget.files
        if (!files || files.length === 0) {
            this._showErrors(['Veuillez sélectionner un fichier image.'])
            return
        }

        const file = files[0]
        const fileName = (file.name || '').toLowerCase()
        const isExtValid = fileName.endsWith('.png') || fileName.endsWith('.jpg') || fileName.endsWith('.jpeg')
        const isMimeValid = !file.type || file.type === 'image/png' || file.type === 'image/jpeg'

        if (!isExtValid || !isMimeValid) {
            this._showErrors(['Format de fichier non autorisé. Formats acceptés : PNG et JPEG uniquement.'])
            return
        }

        const data = new FormData()
        data.append('logo', file)

        if (this.hasUrlInputTarget && this.urlInputTarget.value.trim() !== '') {
            data.append('url', this.urlInputTarget.value.trim())
        }

        const originalBtnText = this.hasSubmitBtnTarget ? this.submitBtnTarget.innerHTML : null
        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.disabled = true
            this.submitBtnTarget.classList.add('opacity-70', 'cursor-not-allowed')
        }

        try {
            const response = await fetch(this.uploadLogoUrlValue, {
                method: 'POST',
                body: data,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })

            const json = await response.json()

            if (json.success) {
                this.fileInputTarget.value = ''
                if (this.hasUrlInputTarget) {
                    this.urlInputTarget.value = ''
                }
                this._clearErrors()
                await this.refreshFrame()
            } else {
                const errors = json.errors ?? (json.error ? [json.error] : ['Une erreur est survenue lors du téléversement.'])
                this._showErrors(errors)
            }
        } catch (err) {
            console.error('Upload failed:', err)
            this._showErrors(['Impossible de téléverser le logo. Veuillez vérifier votre connexion.'])
        } finally {
            if (this.hasSubmitBtnTarget && originalBtnText !== null) {
                this.submitBtnTarget.disabled = false
                this.submitBtnTarget.classList.remove('opacity-70', 'cursor-not-allowed')
            }
        }
    }

    async updateUrl(event) {
        if (event) {
            event.stopPropagation()
        }

        const input = event.currentTarget || event.target
        const filename = input.dataset.filename
        const url = input.value.trim()

        if (!filename) {
            return
        }

        const statusEl = this.element.querySelector(`[data-status-for="${CSS.escape(filename)}"]`)
        if (statusEl) {
            statusEl.textContent = 'Enregistrement...'
            statusEl.classList.remove('hidden', 'text-success-600', 'text-danger-600')
            statusEl.classList.add('text-secondary-400')
        }

        try {
            const response = await fetch(this.updateLogoUrlValue, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({filename, url})
            })

            const json = await response.json()

            if (json.success) {
                if (statusEl) {
                    statusEl.textContent = 'Enregistré'
                    statusEl.classList.remove('text-secondary-400', 'text-danger-600')
                    statusEl.classList.add('text-success-600')
                    setTimeout(() => {
                        statusEl.classList.add('hidden')
                    }, 2500)
                }

                // Mettre à jour l'icône de test du lien si elle existe
                const linkTestEl = this.element.querySelector(`[data-link-test-for="${CSS.escape(filename)}"]`)
                if (linkTestEl) {
                    if (json.url) {
                        linkTestEl.href = json.url
                        linkTestEl.title = `Ouvrir le lien web : ${json.url}`
                        linkTestEl.classList.remove('hidden')
                    } else {
                        linkTestEl.classList.add('hidden')
                    }
                }
            } else {
                if (statusEl) {
                    statusEl.textContent = json.errors?.[0] || json.error || 'Erreur'
                    statusEl.classList.remove('text-secondary-400', 'text-success-600')
                    statusEl.classList.add('text-danger-600')
                }
            }
        } catch (err) {
            console.error('Update URL failed:', err)
            if (statusEl) {
                statusEl.textContent = 'Erreur réseau'
                statusEl.classList.remove('text-secondary-400', 'text-success-600')
                statusEl.classList.add('text-danger-600')
            }
        }
    }

    saveUrlClick(event) {
        event.preventDefault()
        event.stopPropagation()

        const filename = event.currentTarget.dataset.filename
        if (!filename) return

        const input = this.element.querySelector(`input[data-filename="${CSS.escape(filename)}"]`)
        if (input) {
            this.updateUrl({
                currentTarget: input, target: input, stopPropagation: () => {
                }
            })
        }
    }

    async deleteLogo(event) {
        event.preventDefault()
        event.stopPropagation()

        if (!confirm('Êtes-vous sûr de vouloir supprimer ce logo ?')) {
            return
        }

        const filename = event.currentTarget.dataset.filename
        const cardEl = event.currentTarget.closest('[data-logo-card]')

        if (!filename) {
            return
        }

        try {
            const response = await fetch(this.deleteLogoUrlValue, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({filename})
            })

            const json = await response.json()

            if (json.success) {
                if (cardEl) {
                    cardEl.style.transition = 'opacity 0.2s ease, transform 0.2s ease'
                    cardEl.style.opacity = '0'
                    cardEl.style.transform = 'scale(0.95)'
                    setTimeout(() => {
                        cardEl.remove()
                        const remaining = this.element.querySelectorAll('[data-logo-card]')
                        if (remaining.length === 0) {
                            this.refreshFrame()
                        }
                    }, 200)
                } else {
                    await this.refreshFrame()
                }
            } else {
                alert(json.error || 'Impossible de supprimer ce logo.')
            }
        } catch (err) {
            console.error('Delete logo failed:', err)
            alert('Une erreur réseau est survenue lors de la suppression du logo.')
        }
    }

    async refreshFrame() {
        const frame = this.element.querySelector('turbo-frame#parcours-logos-frame') || document.getElementById('parcours-logos-frame')
        if (!frame) {
            return
        }

        frame.src = ''
        frame.src = this.logosUrlValue
    }

    _showErrors(errors) {
        const container = this.hasErrorsTarget ? this.errorsTarget : this.element.querySelector('#parcours-logo-upload-errors')
        if (!container) return

        container.innerHTML = errors.map(e =>
            `<div class="text-xs text-danger-600 bg-danger-50 dark:bg-danger-950/40 p-2 rounded-lg mt-1 border border-danger-200 dark:border-danger-800">${e}</div>`
        ).join('')

        setTimeout(() => {
            this._clearErrors()
        }, 6000)
    }

    _clearErrors() {
        const container = this.hasErrorsTarget ? this.errorsTarget : this.element.querySelector('#parcours-logo-upload-errors')
        if (container) {
            container.innerHTML = ''
        }
    }
}
