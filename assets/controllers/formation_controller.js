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
        const mentionTexte = document.getElementById('formation_ses_mentionTexte');
        if (mentionTexte) {
            mentionTexte.disabled = false;
        }
    }

    changeDomaine(event) {
        this._updateListeMention(event.target.value);
    }

    changeMention(event) {
        const val = event.target ? event.target.value : '';
        const mentionTexte = document.getElementById('formation_ses_mentionTexte');
        if (mentionTexte && val !== 'autre' && val !== '') {
            mentionTexte.value = '';
        }
    }

    changeMentionTexte(event) {
        if (event.target.value.trim() !== '') {
            const mention = document.getElementById('formation_ses_mention');
            if (mention) {
                const ts = mention.tomselect;
                if (ts) {
                    ts.setValue('autre');
                } else {
                    mention.value = 'autre';
                }
            }
        }
    }

    async _updateListeMention(domaine) {
        if (!this.urlValue) return;

        let url;
        if (this.urlValue.includes('?')) {
            url = `${this.urlValue}&domaine=${domaine}`;
        } else {
            url = `${this.urlValue}?domaine=${domaine}`;
        }

        await fetch(url)
            .then((response) => response.json())
            .then((data) => {
                const { mentions, selectedMention } = data;
                const selectMention = document.getElementById('formation_ses_mention');

                if (!selectMention) return;

                const currentVal = selectMention.value;
                const ts = selectMention.tomselect;
                if (ts) {
                    ts.clear();
                    ts.clearOptions();
                }

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

                const targetValue = (selectedMention != null && selectedMention !== '') ? selectedMention : (currentVal || '');
                selectMention.value = targetValue;

                // Sync TomSelect if it exists
                if (ts) {
                    ts.sync();
                    ts.setValue(targetValue);
                }
            });
    }
}