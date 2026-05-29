import { Controller } from '@hotwired/stimulus'
import { Modal } from 'bootstrap'

export default class extends Controller {
    static targets = [
        'mentionSelect',
        'mentionExistante',
        'formationBloc',
        'formationExistante',
        'formationInfo',
        'mentionModalBtn',
        'mentionTypeDiplomeInput',
        'mentionCsrfInput',
        'mentionLibelleInput',
        'mentionSigleInput',
        'mentionDomaineInput',
        'mentionCodeApogeeInput',
        'mentionModalError',
    ]

    static values = {
        mentionsUrl: String,
        formationUrl: String,
        mentionNewUrl: String,
    }

    #mentionModal = null

    connect() {
        const typeDiplomeSelect = this.element.querySelector('[data-action*="changeTypeDiplome"]')
        if (typeDiplomeSelect?.value) {
            this._loadMentions(typeDiplomeSelect.value)
            this._enableMentionBtn(true)
        }
    }

    // ── Type diplôme ──────────────────────────────────────────────────────────

    async changeTypeDiplome(event) {
        const id = event.target.value
        if (!id) {
            this._clearMentions()
            this._enableMentionBtn(false)
            return
        }
        await this._loadMentions(id)
        this._enableMentionBtn(true)
    }

    // ── Mention select ────────────────────────────────────────────────────────

    async changeMention(event) {
        const value = event.target.value
        if (!value) {
            this.mentionExistanteTarget.value = ''
            this.formationExistanteTarget.value = ''
            this._hideFormationBloc()
            this._hideFormationInfo()
            return
        }
        this.mentionExistanteTarget.value = value
        await this._checkFormation(value)
    }

    // ── Mention modal ─────────────────────────────────────────────────────────

    openMentionModal(event) {
        event.preventDefault()
        const typeDiplomeSelect = this.element.querySelector('[data-action*="changeTypeDiplome"]')
        this.mentionTypeDiplomeInputTarget.value = typeDiplomeSelect?.value ?? ''
        this.mentionLibelleInputTarget.value = ''
        this.mentionSigleInputTarget.value = ''
        this.mentionDomaineInputTarget.value = ''
        this.mentionCodeApogeeInputTarget.value = ''
        this._hideMentionError()

        const el = this.element.querySelector('#mentionCreationModal')
        this.#mentionModal = new Modal(el)
        this.#mentionModal.show()
    }

    async saveMention(event) {
        event.preventDefault()
        this._hideMentionError()

        const libelle = this.mentionLibelleInputTarget.value.trim()
        if (!libelle) {
            this._showMentionError('Le libellé est obligatoire.')
            return
        }

        const body = new FormData()
        body.append('_token', this.mentionCsrfInputTarget.value)
        body.append('typeDiplomeId', this.mentionTypeDiplomeInputTarget.value)
        body.append('libelle', libelle)
        body.append('sigle', this.mentionSigleInputTarget.value.trim())
        body.append('domaineId', this.mentionDomaineInputTarget.value)
        body.append('codeApogee', this.mentionCodeApogeeInputTarget.value.trim())

        try {
            const response = await fetch(this.mentionNewUrlValue, {
                method: 'POST',
                body,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
            const data = await response.json()

            if (data.success) {
                this._addMentionOption(data.id, data.libelle)
                this.mentionSelectTarget.value = data.id
                this.mentionExistanteTarget.value = data.id
                await this._checkFormation(data.id)
                this.#mentionModal?.hide()
            } else {
                this._showMentionError(data.error ?? 'Une erreur est survenue.')
            }
        } catch {
            this._showMentionError('Erreur de connexion.')
        }
    }

    // ── Formation ─────────────────────────────────────────────────────────────

    async _checkFormation(mentionId) {
        const typeDiplomeSelect = this.element.querySelector('[data-action*="changeTypeDiplome"]')
        const typeDiplomeId = typeDiplomeSelect?.value
        if (!typeDiplomeId) return

        const url = this.formationUrlValue
            .replace('__mention__', mentionId)
            .replace('__typeDiplome__', typeDiplomeId)

        const response = await fetch(url)
        const formation = await response.json()

        if (formation !== null) {
            this.formationExistanteTarget.value = formation.id
            this._hideFormationBloc()
            this._showFormationInfo(`Formation existante : <strong>${formation.display}</strong>. Le parcours sera ajouté à cette formation.`)
        } else {
            this.formationExistanteTarget.value = ''
            this._showFormationBloc()
            this._hideFormationInfo()
        }
    }

    // ── Privé : mentions ──────────────────────────────────────────────────────

    async _loadMentions(typeDiplomeId) {
        const url = this.mentionsUrlValue.replace('__id__', typeDiplomeId)
        const response = await fetch(url)
        const mentions = await response.json()

        if (!this.hasMentionSelectTarget) return
        const select = this.mentionSelectTarget
        select.innerHTML = '<option value="">Choisissez une mention</option>'
        mentions.forEach(({ id, libelle }) => {
            const opt = document.createElement('option')
            opt.value = id
            opt.textContent = libelle
            select.appendChild(opt)
        })
    }

    _clearMentions() {
        if (this.hasMentionSelectTarget) {
            this.mentionSelectTarget.innerHTML = '<option value="">Choisissez d\'abord un type de diplôme</option>'
        }
        this.mentionExistanteTarget.value = ''
        this.formationExistanteTarget.value = ''
        this._hideFormationBloc()
        this._hideFormationInfo()
    }

    _addMentionOption(id, libelle) {
        const opt = document.createElement('option')
        opt.value = id
        opt.textContent = libelle
        this.mentionSelectTarget.appendChild(opt)
    }

    _enableMentionBtn(enabled) {
        if (this.hasMentionModalBtnTarget) {
            this.mentionModalBtnTarget.disabled = !enabled
        }
    }

    // ── Privé : formation bloc ────────────────────────────────────────────────

    _showFormationBloc() {
        if (this.hasFormationBlocTarget) this.formationBlocTarget.style.display = 'block'
    }

    _hideFormationBloc() {
        if (this.hasFormationBlocTarget) this.formationBlocTarget.style.display = 'none'
    }

    _showFormationInfo(html) {
        if (this.hasFormationInfoTarget) {
            this.formationInfoTarget.innerHTML = `<div class="alert alert-info">${html}</div>`
        }
    }

    _hideFormationInfo() {
        if (this.hasFormationInfoTarget) this.formationInfoTarget.innerHTML = ''
    }

    // ── Privé : erreur modale ─────────────────────────────────────────────────

    _showMentionError(msg) {
        if (this.hasMentionModalErrorTarget) {
            this.mentionModalErrorTarget.textContent = msg
            this.mentionModalErrorTarget.classList.remove('d-none')
        }
    }

    _hideMentionError() {
        if (this.hasMentionModalErrorTarget) {
            this.mentionModalErrorTarget.classList.add('d-none')
        }
    }
}
