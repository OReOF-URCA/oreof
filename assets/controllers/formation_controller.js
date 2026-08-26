/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/formation_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/08/2026 12:45
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static values = {
        urlUser: String,
        url: String,
    }

    connect() {
        const typeDiplomeEl = document.getElementById('formation_ses_typeDiplome');
        const domaineEl = document.getElementById('formation_ses_domaine');
        
        if (!typeDiplomeEl || !domaineEl) {
            return;
        }

        this._updateListeMention(typeDiplomeEl.value, domaineEl.value).then(() => {
            const mention = document.getElementById('formation_ses_mention');
            const mentionTexte = document.getElementById('formation_ses_mentionTexte');

            if (mention && mentionTexte) {
                mentionTexte.disabled = mention.value !== 'autre' && mention.value.trim() !== 'null';
            }
        });
    }

    changeInscriptionRNCP(event) {
        const inscriptionRNCP = event.target.value;
        const codeRNCP = document.getElementById('formation_ses_codeRNCP');
        if (codeRNCP) {
            codeRNCP.disabled = parseInt(inscriptionRNCP, 10) !== 1;
        }
    }

    changeTypeDiplome(event) {
        const domaineEl = document.getElementById('formation_ses_domaine');
        if (domaineEl) {
            this._updateListeMention(event.target.value, domaineEl.value);
        }
    }

    changeDomaine(event) {
        const typeDiplomeEl = document.getElementById('formation_ses_typeDiplome');
        if (typeDiplomeEl) {
            this._updateListeMention(typeDiplomeEl.value, event.target.value);
        }
    }

    changeMention(event) {
        const mentionTexte = document.getElementById('formation_ses_mentionTexte');
        if (mentionTexte) {
            mentionTexte.disabled = event.target.value !== 'autre' && event.target.value.trim() !== 'null';
        }
    }

    changeMentionTexte(event) {
        if (event.target.value.trim() !== '') {
            const mention = document.getElementById('formation_ses_mention');
            if (mention) {
                mention.value = 'autre';
                const ts = mention.tomselect;
                if (ts) {
                    ts.sync();
                    ts.setValue('autre');
                }
            }
        }
    }

    async _updateListeMention(typeDiplome, domaine) {
        if (!this.urlValue) return;

        let url;
        if (this.urlValue.includes('?')) {
            url = `${this.urlValue}&typeDiplome=${typeDiplome}&domaine=${domaine}`;
        } else {
            url = `${this.urlValue}?typeDiplome=${typeDiplome}&domaine=${domaine}`;
        }

        await fetch(url)
            .then((response) => response.json())
            .then((data) => {
                const { mentions } = data;
                const { selectedMention } = data;
                const selectMention = document.getElementById('formation_ses_mention');

                if (!selectMention) return;

                selectMention.innerHTML = '';

                // Add default choices if needed
                const defaultOpt = document.createElement('option');
                defaultOpt.value = '';
                defaultOpt.text = 'Choisir une mention';
                selectMention.add(defaultOpt, null);

                mentions.forEach((mention) => {
                    const opt = document.createElement('option');
                    opt.value = mention.id;
                    opt.text = mention.libelle;
                    selectMention.add(opt, null);
                });

                const autreOpt = document.createElement('option');
                autreOpt.value = 'autre';
                autreOpt.text = 'Autre mention';
                selectMention.add(autreOpt, null);

                const targetValue = selectedMention == null ? '' : selectedMention;
                selectMention.value = targetValue;

                // Sync TomSelect if it exists
                const ts = selectMention.tomselect;
                if (ts) {
                    ts.sync();
                    ts.setValue(targetValue);
                }
            });
    }
}