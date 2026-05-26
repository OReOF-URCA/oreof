import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = [
        'mentionSelect',
        'mentionCreationBloc',
        'nouvelleMentionToggle',
        'mentionExistante',
        'formationBloc',
        'formationExistante',
        'formationInfo'
    ]

    static values = {
        mentionsUrl: String,
        formationUrl: String,
    }

    connect() {
        // If a typeDiplome is already selected on page load (e.g. form re-render after error), load its mentions
        const typeDiplomeSelect = this.element.querySelector('[data-action*="changeTypeDiplome"]')
        if (typeDiplomeSelect && typeDiplomeSelect.value) {
            this._loadMentions(typeDiplomeSelect.value)
        }
        // Hide inline creation bloc by default
        this._hideMentionCreation()
    }

    async changeTypeDiplome(event) {
        const typeDiplomeId = event.target.value
        if (!typeDiplomeId) {
            this._clearMentions()
            return
        }
        await this._loadMentions(typeDiplomeId)
    }

    showMentionCreation(event) {
        event.preventDefault()
        this.mentionCreationBlocTarget.style.display = 'block'
        // Clear the mention select so they don't submit both
        if (this.hasMentionSelectTarget) {
            this.mentionSelectTarget.value = ''
            this.mentionSelectTarget.disabled = true
        }
    }

    hideMentionCreation(event) {
        event.preventDefault()
        this._hideMentionCreation()
    }

    _hideMentionCreation() {
        if (this.hasMentionCreationBlocTarget) {
            this.mentionCreationBlocTarget.style.display = 'none'
        }
        if (this.hasMentionSelectTarget) {
            this.mentionSelectTarget.disabled = false
            if (this.mentionSelectTarget.value === '__new__' || this.mentionSelectTarget.disabled) {
                this.mentionSelectTarget.selectedIndex = 0 // évite un bug qui empêche de sélectionner la création de mention
            }
        }
    }

    async _loadMentions(typeDiplomeId) {
        const url = this.mentionsUrlValue.replace('__id__', typeDiplomeId)
        const response = await fetch(url)
        const mentions = await response.json()

        if (!this.hasMentionSelectTarget) return

        const select = this.mentionSelectTarget
        select.innerHTML = '<option value="">Choisissez une mention</option>'

        mentions.forEach(mention => {
            const option = document.createElement('option')
            option.value = mention.id
            option.textContent = mention.libelle
            select.appendChild(option)
        })

        const createOption = document.createElement('option')
        createOption.value = '__new__'
        createOption.textContent = '+ Créer une nouvelle mention'
        select.appendChild(createOption)
    }

    async changeMention(e) {
        if (e.target.value === '__new__') {
            this.mentionCreationBlocTarget.style.display = 'block'
            this.mentionSelectTarget.disabled = true
            this.mentionExistanteTarget.value = ''
            this._showFormationFields()
            if (this.hasFormationInfoTarget) {
                this.formationInfoTarget.style.display = 'none'
            }
        } else if (e.target.value !== '') {
            this._hideMentionCreation()
            this.mentionExistanteTarget.value = e.target.value
            await this._checkFormation(e.target.value)
        } else {
            this._hideMentionCreation()
            this._hideFormationFields()
            this.formationExistanteTarget.value = ''
            if (this.hasFormationInfoTarget) {
                this.formationInfoTarget.style.display = 'none'
            }
        }
    }

    _clearMentions() {
        if (this.hasMentionSelectTarget) {
            this.mentionSelectTarget.innerHTML = '<option value="">Choisissez d\'abord un type de diplôme</option>'
        }
        this._hideMentionCreation()
    }

    async _checkFormation(mentionId) {
        if (!mentionId) return  // ← guard against empty mentionId

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
            this._hideFormationFields()
            if (this.hasFormationInfoTarget) {
                this.formationInfoTarget.style.display = 'block'
                this.formationInfoTarget.innerHTML = `<div class="alert alert-info">Formation existante : <strong>${formation.display}</strong>. Le parcours sera ajouté à cette formation.</div>`
            }
        } else {
            this.formationExistanteTarget.value = ''
            this._showFormationFields()
            if (this.hasFormationInfoTarget) {
                this.formationInfoTarget.style.display = 'none'
            }
        }
    }

    _showFormationFields() {
        if (this.hasFormationBlocTarget) {
            this.formationBlocTarget.style.display = 'block'
        }
    }

    _hideFormationFields() {
        if (this.hasFormationBlocTarget) {
            this.formationBlocTarget.style.display = 'none'
        }
    }
}