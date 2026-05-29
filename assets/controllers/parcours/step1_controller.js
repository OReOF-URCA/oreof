/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/parcours/step1_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 15/03/2023 21:06
 */

import { Controller } from '@hotwired/stimulus'
import { saveData } from '../../js/saveData'
import { updateEtatOnglet } from '../../js/updateEtatOnglet'
import { calculEtatStep } from '../../js/calculEtatStep'
import trixEditor from '../../js/trixEditor'
import { visit } from '@hotwired/turbo'

export default class extends Controller {
    static targets = [
        'content',
    ]

    static values = {
        url: String,
    }

    connect() {
        document.getElementById('parcours_step1_contenuFormation').addEventListener('trix-blur', this.saveContenu.bind(this))
        document.getElementById('parcours_step1_objectifsParcours').addEventListener('trix-blur', this.saveObjectifsParcours.bind(this))
        document.getElementById('parcours_step1_resultatsAttendus').addEventListener('trix-blur', this.saveResultats.bind(this))
        document.getElementById('parcours_step1_rythmeFormationTexte').addEventListener('trix-blur', this.saveRythme.bind(this))
        document.getElementById('parcours_step1_motsCles').addEventListener('trix-blur', this.saveMotsCles.bind(this))
    }

    etatStep(event) {
        calculEtatStep(this.urlValue, 1, event, 'parcours')
    }

    saveContenu() {
        this._save({
            field: 'contenuFormation',
            action: 'textarea',
            value: trixEditor('parcours_step1_contenuFormation'),
        })
    }

    saveObjectifsParcours() {
        this._save({
            field: 'objectifsParcours',
            action: 'textarea',
            value: trixEditor('parcours_step1_objectifsParcours'),
        })
    }

    saveResultats() {
        this._save({
            field: 'resultatsAttendus',
            action: 'textarea',
            value: trixEditor('parcours_step1_resultatsAttendus'),
        })
    }

    saveMotsCles () {
        this._save({
            field: 'motsCles',
            action: 'textarea',
            value: trixEditor('parcours_step1_motsCles'),
        })
    }

    changeRythme(event) {
        this._save({
            field: 'rythmeFormation',
            action: 'rythmeFormation',
            value: event.target.value,
        })
    }

    changeLocalisation(event) {
        this._save({
            field: 'localisation',
            action: 'localisation',
            value: event.target.value,
        })
    }

    saveRespParcours(event) {
        this._save({
            action: 'respParcours',
            value: event.target.value,
        })
    }

    saveCoRespParcours(event) {
        this._save({
            action: 'coRespParcours',
            value: event.target.value,
        })
    }

    saveRythme() {
        this._save({
            field: 'rythmeFormationTexte',
            action: 'textarea',
            value: trixEditor('parcours_step1_rythmeFormationTexte'),
        })
    }

    async _save(options) {
        await saveData(this.urlValue, options).then(async () => {
            await updateEtatOnglet(this.urlValue, 'onglet1', 'parcours')
        })
    }
}