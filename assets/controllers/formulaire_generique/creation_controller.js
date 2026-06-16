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
        'rythmeSelect',
        'rythmeTexteBloc',
        'multiParcoursCheckBloc',
        'multiParcoursCheck',
        'parcoursTitle',
        'parcoursDetailsBloc',
        'parcoursBaseBloc',
        'dureeInput',
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
        this.toggleRythmeTexte()
        // Par défaut (aucune formation détectée), on est en mode « formation neuve ».
        this._applyParcoursMode('new')

        // Au retour via le cache d'historique (flèche « précédent »), le JS n'est pas
        // rejoué : on revalide la mention sélectionnée pour restaurer l'état (message
        // « formation existante » + champ caché), ce qui évite de recréer un doublon.
        this._pageShowHandler = (event) => this._onPageShow(event)
        window.addEventListener('pageshow', this._pageShowHandler)
    }

    disconnect() {
        if (this._pageShowHandler) {
            window.removeEventListener('pageshow', this._pageShowHandler)
        }
    }

    _onPageShow(event) {
        if (!event.persisted) return
        const mentionId = this.hasMentionSelectTarget ? this.mentionSelectTarget.value : ''
        if (mentionId) {
            this.mentionExistanteTarget.value = mentionId
            this._checkFormation(mentionId)
        }
    }

    // ── Rythme de formation ─────────────────────────────────────────────────────

    // Le champ texte libre n'apparaît que si l'option « Autre » est choisie.
    toggleRythmeTexte() {
        if (!this.hasRythmeSelectTarget || !this.hasRythmeTexteBlocTarget) return
        const option = this.rythmeSelectTarget.selectedOptions[0]
        const isAutre = option && option.textContent.trim().toLowerCase() === 'autre'
        this.rythmeTexteBlocTarget.style.display = isAutre ? 'block' : 'none'
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
            this._applyParcoursMode('new')
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
        const domaineTs = this.mentionDomaineInputTarget.tomselect
        if (domaineTs) {
            domaineTs.clear()
        } else {
            Array.from(this.mentionDomaineInputTarget.options).forEach((o) => { o.selected = false })
        }
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

        const domaineTs = this.mentionDomaineInputTarget.tomselect
        const domaineIds = (domaineTs
            ? domaineTs.getValue()
            : Array.from(this.mentionDomaineInputTarget.selectedOptions).map((o) => o.value))
            .filter((v) => v)
        if (domaineIds.length === 0) {
            this._showMentionError('Veuillez sélectionner au moins un domaine.')
            return
        }

        const body = new FormData()
        body.append('_token', this.mentionCsrfInputTarget.value)
        body.append('typeDiplomeId', this.mentionTypeDiplomeInputTarget.value)
        body.append('libelle', libelle)
        body.append('sigle', this.mentionSigleInputTarget.value.trim())
        domaineIds.forEach((id) => body.append('domaineIds[]', id))
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
                const ts = this.mentionSelectTarget.tomselect
                if (ts) {
                    ts.setValue(String(data.id))
                } else {
                    this.mentionSelectTarget.value = data.id
                }
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
            // Formation existante (a déjà un parcours) : il faut cocher la case pour en ajouter un.
            this._applyParcoursMode('existing')
        } else {
            this.formationExistanteTarget.value = ''
            this._showFormationBloc()
            this._hideFormationInfo()
            // Formation neuve : rythme + durée visibles, bouton facultatif pour nommer le parcours.
            this._applyParcoursMode('new')
        }
    }

    // ── Parcours : affichage dynamique ────────────────────────────────────────

    // Applique le mode d'affichage de la section « Parcours ».
    // - 'new'      : formation neuve → case « plusieurs parcours » visible (décochée par
    //                défaut → cadrage « formation »).
    // - 'existing' : formation existante → pas de case, cadrage « parcours » d'office.
    _applyParcoursMode(mode) {
        if (mode === 'existing') {
            this._hide(this.multiParcoursCheckBlocTarget)
            this._applyParcoursFraming('parcours')
        } else {
            this._show(this.multiParcoursCheckBlocTarget)
            if (this.hasMultiParcoursCheckTarget) this.multiParcoursCheckTarget.checked = false
            this._applyParcoursFraming('formation')
        }
    }

    // Case « Cette formation aura plusieurs parcours » (formation neuve uniquement).
    toggleMultiParcours(event) {
        this._applyParcoursFraming(event.target.checked ? 'parcours' : 'formation')
    }

    // Bascule le vocabulaire des champs.
    // - 'formation' (mono) : pas de titre « Parcours », pas de libellé/responsable, labels « … de formation ».
    // - 'parcours' (multi / existante) : titre « Parcours », libellé + responsable, labels « … du parcours ».
    _applyParcoursFraming(framing) {
        if (framing === 'parcours') {
            this._show(this.parcoursTitleTarget)
            this._show(this.parcoursDetailsBlocTarget)
            this._setRythmeLabel('Rythme de formation du parcours')
            this._setDureeLabel('Durée du parcours')
        } else {
            this._hide(this.parcoursTitleTarget)
            this._hide(this.parcoursDetailsBlocTarget)
            this._setRythmeLabel('Rythme de formation')
            this._setDureeLabel('Durée de formation')
        }
    }

    _setRythmeLabel(text) {
        this._setLabelFor(this.hasRythmeSelectTarget ? this.rythmeSelectTarget : null, text)
    }

    _setDureeLabel(text) {
        this._setLabelFor(this.hasDureeInputTarget ? this.dureeInputTarget : null, text)
    }

    _setLabelFor(field, text) {
        if (!field) return
        const label = this.element.querySelector(`label[for="${field.id}"]`)
        if (label) label.textContent = text
    }

    _show(el) {
        if (el) el.style.display = ''
    }

    _hide(el) {
        if (el) el.style.display = 'none'
    }

    // ── Privé : mentions ──────────────────────────────────────────────────────

    async _loadMentions(typeDiplomeId) {
        const url = this.mentionsUrlValue.replace('__id__', typeDiplomeId)
        const response = await fetch(url)
        const mentions = await response.json()

        if (!this.hasMentionSelectTarget) return
        const select = this.mentionSelectTarget

        select.innerHTML = '<option value="">Choisissez un intitulé de formation</option>'
        mentions.forEach(({ id, libelle }) => {
            const opt = document.createElement('option')
            opt.value = id
            opt.textContent = libelle
            select.appendChild(opt)
        })

        const ts = select.tomselect
        if (ts) {
            ts.sync()
            ts.setValue('')
        }
    }

    _clearMentions() {
        if (this.hasMentionSelectTarget) {
            const select = this.mentionSelectTarget
            select.innerHTML = '<option value="">Choisissez d\'abord un type de diplôme</option>'
            const ts = select.tomselect
            if (ts) {
                ts.sync()
                ts.setValue('')
            }
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
        const ts = this.mentionSelectTarget.tomselect
        if (ts) ts.sync()
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
