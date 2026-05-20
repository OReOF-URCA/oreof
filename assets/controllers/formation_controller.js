/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/formation_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 23/02/2023 16:43
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static values = {
        urlUser: String,
    }

    connect() {
        this._updateListeMention(document.getElementById('formation_ses_typeDiplome').value, document.getElementById('formation_ses_domaine').value).then(() => {
            const mention = document.getElementById('formation_ses_mention')
            const mentionTexte = document.getElementById('formation_ses_mentionTexte')

            mentionTexte.disabled = mention.value !== 'autre' && mention.value.trim() !== 'null';

            // if (mentionTexte.value.trim() !== '') {
            //   mention.value = 'autre'
            // }
        })
        // this._updateListePersonnel(document.getElementById('formation_ses_composantePorteuse').value).then(() => {
        //   // if (document.getElementById('formation_ses_responsableMention').value !== 'null' && document.getElementById('formation_ses_responsableMention').value !== '') {
        //   //   this._updatePersonnel(document.getElementById('formation_ses_responsableMention').value)
        //   // }
        // })
    }

    changeInscriptionRNCP(event) {
        const inscriptionRNCP = event.target.value
        if (parseInt(inscriptionRNCP, 10) === 1) {
            document.getElementById('formation_ses_codeRNCP').disabled = false
        } else {
            document.getElementById('formation_ses_codeRNCP').disabled = true
        }
    }

    // changeComposante(event) {
    //   this._updateListePersonnel(event.target.value)
    // }

    changeTypeDiplome(event) {
        this._updateListeMention(event.target.value, document.getElementById('formation_ses_domaine').value)
    }

    changeDomaine(event) {
        this._updateListeMention(document.getElementById('formation_ses_typeDiplome').value, event.target.value)
    }


    changeMention(event) {
        if (event.target.value === 'autre' || event.target.value.trim() === 'null') {
            document.getElementById('formation_ses_mentionTexte').disabled = false
        } else {
            document.getElementById('formation_ses_mentionTexte').disabled = true
        }
    }

    changeMentionTexte(event) {
        if (event.target.value.trim() !== '') {
            document.getElementById('formation_ses_mention').value = 'autre'
        }
    }

    // async _updateListePersonnel(composante) {
    //   await fetch(`${this.urlListePersonnelValue}?composante=${composante}`).then((response) => response.json()).then(
    //     (data) => {
    //       const selectPersonnels = document.getElementById('formation_ses_responsableMention')
    //       selectPersonnels.innerHTML = ''
    //
    //       let option = document.createElement('option')
    //       option.value = null
    //       option.text = ''
    //       selectPersonnels.add(option)
    //
    //       data.forEach((personnel) => {
    //         option = document.createElement('option')
    //         option.value = personnel.id
    //         option.text = personnel.libelle
    //         selectPersonnels.add(option, null)
    //       })
    //     },
    //   )
    // }

    async _updateListeMention(typeDiplome, domaine) {
        let url
        if (this.urlValue.includes('?')) {
            url = `${this.urlValue}&typeDiplome=${typeDiplome}&domaine=${domaine}`
        } else {
            url = `${this.urlValue}?typeDiplome=${typeDiplome}&domaine=${domaine}`


        }
        await fetch(url).then((response) => response.json()).then(
            (data) => {
                const { mentions } = data
                const { selectedMention } = data
                const selectMention = document.getElementById('formation_ses_mention')

                // suppression des options du select selectMention
                selectMention.innerHTML = ''

                mentions.forEach((mention) => {
                    const opt = document.createElement('option');
                    opt.value = mention.id;
                    opt.text = mention.libelle;
                    selectMention.add(opt, null);
                })

                selectMention.value = selectedMention == null ? '' : selectedMention
            },
        )
    }
}