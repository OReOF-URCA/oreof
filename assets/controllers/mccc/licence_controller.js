/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/base_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 08/03/2023 14:53
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['zone', 'zoneErreur']

  static values = {
    url: String,
    typeMccc: String,
    afficheMccc: Boolean,
  }

  connect () {
    if (this.typeMcccValue !== null && this.afficheMcccValue === true) {
      this._loadTypeMccc(this.typeMcccValue).then(() => {
        this._verifyTypeEpreuveCt()
        this._verifyTypeEpreuveEt()
      })
    }
  }

  updateForm () {
    if (this.typeMcccValue !== null && this.afficheMcccValue === true) {
      this._loadTypeMccc(this.typeMcccValue)
    }
  }

  _verifyTypeEpreuveEt () {
    document.querySelectorAll('.typeEpreuveSelectEt').forEach((element) => {
      const name = element.getAttribute('name')
      this._changeTypeEpreuveEt(name)
    })
  }

  _verifyTypeEpreuveCt () {
    document.querySelectorAll('.typeEpreuveSelectCt').forEach((element) => {
      const name = element.getAttribute('name')
      this._changeTypeEpreuveCt(name)
    })
  }

  changeType (event) {
    if (confirm('Attention, vous allez perdre les données saisies. Êtes-vous sûr ?')) {
      this._loadTypeMccc(event.target.value)
    }
  }

  changeTypeEpreuveCt (event) {
    const name = event.target.getAttribute('name')
    this._changeTypeEpreuveCt(name)
  }

  _changeTypeEpreuveCt (name) {
    const numEpreuve = name.substr(name.lastIndexOf('_') + 1)
    const option1 = document.querySelector(`#typeEpreuve_s1_${numEpreuve} option:checked`)
    document.getElementById(`duree_s1_${numEpreuve}`).disabled = !(parseInt(option1.dataset.hasduree, 10) === 1)

    if (document.getElementById(`duree_s1_${numEpreuve}`).disabled === true) {
      document.getElementById(`duree_s1_${numEpreuve}`).value = ''
    }
  }

  changeTypeEpreuveEt (event) {
    const name = event.target.getAttribute('name')
    this._changeTypeEpreuveEt(name)
  }

  _changeTypeEpreuveEt (name) {
    const numEpreuve = name.substr(name.lastIndexOf('_') + 1)
    const option1 = document.querySelector(`#typeEpreuve_s2_${numEpreuve} option:checked`)
    document.getElementById(`duree_s2_${numEpreuve}`).disabled = !(parseInt(option1.dataset.hasduree, 10) === 1)

    if (document.getElementById(`duree_s2_${numEpreuve}`).disabled === true) {
      document.getElementById(`duree_s2_${numEpreuve}`).value = ''
    }
  }

  async _loadTypeMccc (typeMccc) {
    const params = new URLSearchParams()
    params.append('type', typeMccc)

    const response = await fetch(`${this.urlValue}?${params.toString()}`)
    this.zoneTarget.innerHTML = await response.text()
    // une fois chargé vérifier ccHasSecondeSession
    const ccHasSecondeSession = document.getElementById('ccHasSecondeSession')
    //configurer selon la valeur du checkbox, afficher ou masquer la zone des épreuves de seconde session
    if (ccHasSecondeSession !== null) {
      this.ccHasSecondeSession({ target: ccHasSecondeSession })
    }

  }

  saveDataCcCt () {
    // todo: gérer les cas multiples sur les secondes session... Revoir affichage/masquage
    // on vérifie que le pourcentage est bien de 100
    const total = parseFloat(document.getElementById('pourcentage_s1_cc').value) + parseFloat(document.getElementById('pourcentage_s1_et').value)

    if (total !== 100) {
      this.zoneErreurTarget.classList.remove('d-none')
      this.zoneErreurTarget.innerHTML = 'Le pourcentage doit être de 100%'
    } else {
      this.zoneErreurTarget.classList.add('d-none')
      this.zoneErreurTarget.innerHTML = ''
    }
  }

  // saveDataCt() {
  //   const option1 = document.querySelector('#typeEpreuve_s1_et1 option:checked')
  //   document.getElementById('duree_s1_et1').disabled = !(parseInt(option1.dataset.hasduree, 10) === 1)
  //
  //   if (document.getElementById('duree_s1_et1').disabled === true) {
  //     document.getElementById('duree_s1_et1').value = ''
  //   }
  //
  //   const option2 = document.querySelector('#typeEpreuve_s2_et1 option:checked')
  //   document.getElementById('duree_s2_et1').disabled = !(parseInt(option2.dataset.hasduree, 10) === 1)
  //
  //   if (document.getElementById('duree_s2_et1').disabled === true) {
  //     document.getElementById('duree_s2_et1').value = ''
  //   }
  // }

  // saveDataCc(event) {
  //   const nomChamp = event.target.getAttribute('name')
  //   // récuperer la partie après le dernier _
  //   const numEpreuve = nomChamp.substr(nomChamp.lastIndexOf('_') + 1)
  //
  //   const option = document.querySelector(`#typeEpreuve_s2_${numEpreuve} option:checked`)
  //   document.getElementById(`duree_s2_${numEpreuve}`).disabled = !(parseInt(option.dataset.hasduree, 10) === 1)
  //
  //   if (document.getElementById(`duree_s2_${numEpreuve}`).disabled === true) {
  //     document.getElementById(`duree_s2_${numEpreuve}`).value = ''
  //   }
  // }

  saveDataCci () {
    let total = 0
    const erreurs = []
    document.querySelectorAll('.pourcentage').forEach((element) => {
      total += parseFloat(element.value)
      if (element.value >= 50) {
        erreurs.push('Le pourcentage d\'une épreuve ne doit pas dépasser 50%')
      }
    })

    if (total !== 100) {
      erreurs.push('Le pourcentage doit être de 100%')
    }

    if (erreurs.length > 0) {
      this.zoneErreurTarget.classList.remove('d-none')
      this.zoneErreurTarget.innerHTML = erreurs.join('<br>')
    } else {
      this.zoneErreurTarget.classList.add('d-none')
      this.zoneErreurTarget.innerHTML = ''
    }
  }

  addEpreuveCci (event) {
    event.preventDefault()
    // ajouter un nouveau champs pour une nouvelle épreuve
    const div = document.createElement('div')
    const nbEpreuves = document.querySelectorAll('.epreuve').length
    const numEp = nbEpreuves + 1
    div.classList.add('row')
    div.classList.add('epreuve')
    div.innerHTML = ` <div class="col-4">

            <strong>Epreuve N°${numEp}</strong>
        </div>
        <div class="col-6">
            <label for="pourcentage_s${numEp}_cc">
                Pourcentage
                <i class="fal fa-question-circle ms-1"
                   data-controller="tooltip"
                   data-tooltip-placement-value="bottom"
                   aria-label="{{ 'mccc_licence.helps.cc.pourcentage_s1_et.help'|trans({}, 'help') }}"
                   data-bs-original-title="{{ 'mccc_licence.helps.cc.pourcentage_s1_et.help'|trans({}, 'help') }}"></i>
            </label>
            <div class="input-group">
                <input type="text" class="form-control pourcentage"
                       id="pourcentage_s${numEp}_cc"
                       name="pourcentage[${numEp}]"
                       data-action="change@mccc--licence#saveDataCci"
                       value=""
                >
                <span class="input-group-text">%</span>
            </div>
        </div>
        <div class="col-2">
        &nbsp;<br>
        <button type="button" class="btn btn-danger btn-sm" data-action="click->mccc--licence#removeEpreuveCci">
            <i class="fas fa-trash"></i>
        </button>
        </div>
`
    document.getElementById('epreuve_cci').appendChild(div)
  }

  addEpreuveSecondeSession (event) {
    event.preventDefault()
    // ajouter un nouveau champs pour une nouvelle épreuve
    const div = document.createElement('div')
    const nbEpreuves = document.querySelectorAll('.epreuve_s2_ct').length
    const numEp = nbEpreuves + 1
    div.classList.add('row')
    div.classList.add('epreuve_s2_ct')

    // récupérer le contenu de la première épreuve, et le dupliquer
    // const epreuve1 = document.querySelector('.epreuve_s2_ct')
    // let html = epreuve1.innerHTML
    // html = html.replace(/ct1/g, `ct${numEp}`)
    const html = document.querySelector('.epreuve_s2_ct').cloneNode(true)
    this._renumberMcccFormFields(html, /_ct1/, `_ct${numEp}`, true)

    // ajouter le numéro de l'epreuve dans le texte
    // parcours tous les éléments da epreuve_et et numéroter le texte

    html.innerHTML += `
        <div class="col-8">&nbsp;</div>
        <div class="col-4 d-grid mt-2">
        <button type="button" class="btn btn-danger btn-sm d-block" data-action="click->mccc--licence#removeEpreuveCt">
            <i class="fas fa-trash"></i>
        </button>
        </div>
    </div>`
    div.innerHTML = html.innerHTML
    document.getElementById('epreuves_s2_ct').appendChild(div)

    let index = 1
    document.querySelectorAll('.epreuve_s2_ct').forEach((element, indexLoop) => {
      // const htmlTitre = element.innerHTML
      // element.innerHTML = htmlTitre.replace(/Session N°[0-9]/g, `Session N°${index}`)
      this._renameEpreuveTitle(element, /Session N°[0-9]/, `Session N°${index}`)

      // On remet à zéro la nouvelle épreuve (dernière de la liste)
      if (indexLoop === nbEpreuves) {
        element.querySelector('input[id^="pourcentage"]').value = ''
        element.querySelector('select[id^="typeEpreuve"]').selectedIndex = 0
        element.querySelector('input[id^="duree"]').value = ''
        element.querySelector('textarea[id^="justification"]').required = false
        element.querySelector('textarea[id^="justification"]').value = ''
      }

      if (numEp > 1) {
        element.querySelector('input').disabled = false
      } else {
        element.querySelector('input').disabled = true
      }
      index++
    })

    this._verifyTypeEpreuveEt()
  }

  addEpreuveCt (event) {
    event.preventDefault()
    // ajouter un nouveau champs pour une nouvelle épreuve

    const nbEpreuves = document.querySelectorAll('.epreuve_ct')
    const numEp = nbEpreuves.length + 1
    const tab = []
    let idx = 1
    // sauvegarde des données des champs
    nbEpreuves.forEach(() => {
      tab[idx] = []
      tab[idx][`pourcentage_s1_ct${idx}`] = document.getElementById(`pourcentage_s1_ct${idx}`).value
      tab[idx][`typeEpreuve_s1_ct${idx}`] = document.getElementById(`typeEpreuve_s1_ct${idx}`).value
      tab[idx][`duree_s1_ct${idx}`] = document.getElementById(`duree_s1_ct${idx}`).value
      idx++
    })

    // récupérer le contenu de la première épreuve, et le dupliquer
    const html = document.querySelector('.epreuve_ct').cloneNode(true)
    // html.innerHTML = html.innerHTML.replace(/ct1/g, `ct${numEp}`)
    this._renumberMcccFormFields(html, /_ct1/, `_ct${numEp}`, true)

    // Initialisation de la nouvelle épreuve
    // À ce moment là, l'index 'idx' a la bonne valeur
    // car incrémenté en fin de boucle précédente
    const newEpreuve = []
    newEpreuve[`pourcentage_s1_ct${idx}`] = ''
    newEpreuve[`typeEpreuve_s1_ct${idx}`] = ''
    newEpreuve[`duree_s1_ct${idx}`] = ''
    tab[idx] = newEpreuve

    // ajouter le numéro de l'epreuve dans le texte
    // parcours tous les éléments da epreuve_et et numéroter le texte

    html.innerHTML += `
        <div class="col-8">&nbsp;</div>
        <div class="col-4 d-grid mt-2">
        <button type="button" class="btn btn-danger btn-sm d-block" data-action="click->mccc--licence#removeEpreuveCtS1">
            <i class="fas fa-trash"></i>
        </button>
        </div>
    </div>`

    const div = document.createElement('div')
    div.classList.add('row')
    div.classList.add('epreuve_ct')
    div.innerHTML = html.innerHTML
    document.getElementById('epreuves_ct').appendChild(div)

    let index = 1
    document.querySelectorAll('.epreuve_ct').forEach((element) => {
      // const htmlTitre = element.innerHTML
      // element.innerHTML = htmlTitre.replace(/Contrôle terminal N°[0-9]/g, `Contrôle terminal N°${index}`)
      this._renameEpreuveTitle(element, /Contrôle terminal N°[0-9]/, `Contrôle terminal N°${index}`)
      document.getElementById(`pourcentage_s1_ct${index}`).value = tab[index][`pourcentage_s1_ct${index}`]
      document.getElementById(`typeEpreuve_s1_ct${index}`).value = tab[index][`typeEpreuve_s1_ct${index}`]
      document.getElementById(`duree_s1_ct${index}`).value = tab[index][`duree_s1_ct${index}`]

      // if (numEp > 1) {
      //   element.querySelector('input').disabled = false
      // } else {
      //   element.querySelector('input').disabled = true
      // }
      index++
    })

    this._verifyTypeEpreuveCt()
  }

  addEpreuveCCTp (event) {
    event.preventDefault()
    // ajouter un nouveau champs pour une nouvelle épreuve

    const nbEpreuves = document.querySelectorAll('.epreuve_ct')
    const numEp = nbEpreuves.length + 1
    const tab = []
    let idx = 1
    // sauvegarde des données des champs
    nbEpreuves.forEach(() => {
      tab[idx] = []
      tab[idx][`pourcentage_s1_ct${idx}`] = document.getElementById(`pourcentage_s1_ct${idx}`).value
      idx++
    })

    // récupérer le contenu de la première épreuve, et le dupliquer
    const html = document.querySelector('.epreuve_ct').cloneNode(true)
    // html.innerHTML = html.innerHTML.replace(/ct1/g, `ct${numEp}`)
    this._renumberMcccTPFormFields(html, /_ct1/, `_ct${numEp}`, true)

    // Initialisation de la nouvelle épreuve
    // À ce moment là, l'index 'idx' a la bonne valeur
    // car incrémenté en fin de boucle précédente
    const newEpreuve = []
    newEpreuve[`pourcentage_s1_ct${idx}`] = ''
    tab[idx] = newEpreuve

    // ajouter le numéro de l'epreuve dans le texte
    // parcours tous les éléments da epreuve_et et numéroter le texte

    html.innerHTML += `
        <div class="col-8">&nbsp;</div>
        <div class="col-4 d-grid mt-2">
        <button type="button" class="btn btn-danger btn-sm d-block" data-action="click->mccc--licence#removeEpreuveCCTPS1">
            <i class="fas fa-trash"></i>
        </button>
        </div>`

    const div = document.createElement('div')
    div.classList.add('row')
    div.classList.add('epreuve_ct')
    div.innerHTML = html.innerHTML
    document.getElementById('epreuves_ct').appendChild(div)

    let index = 1
    document.querySelectorAll('.epreuve_ct').forEach((element) => {
      // const htmlTitre = element.innerHTML
      // element.innerHTML = htmlTitre.replace(/Contrôle terminal N°[0-9]/g, `Contrôle terminal N°${index}`)
      this._renameEpreuveTitle(element, /Contrôle TP N°[0-9]/, `Contrôle TP N°${index}`)
      document.getElementById(`pourcentage_s1_ct${index}`).value = tab[index][`pourcentage_s1_ct${index}`]
      index++
    })

    this._verifyTypeEpreuveCt()
  }

  addEpreuveCcAutresDiplomes (event) {
    event.preventDefault()
    // ajouter un nouveau champs pour une nouvelle épreuve

    const nbEpreuves = document.querySelectorAll('.epreuve_cc_autre')
    const numEp = nbEpreuves.length + 1
    const tab = []
    let idx = 1
    // sauvegarde des données des champs
    nbEpreuves.forEach(() => {
      tab[idx] = []
      tab[idx][`pourcentage_s1_cc${idx}`] = document.getElementById(`pourcentage_s1_cc${idx}`).value
      idx++
    })

    // récupérer le contenu de la première épreuve, et le dupliquer
    const html = document.querySelector('.epreuve_cc_autre').cloneNode(true)
    html.innerHTML = html.innerHTML.replace(/cc1/g, `cc${numEp}`)
    // ajouter le numéro de l'epreuve dans le texte
    // parcours tous les éléments da epreuve_et et numéroter le texte

    html.innerHTML += `
        <div class="col-8">&nbsp;</div>
        <div class="col-4 d-grid mt-2">
        <button type="button" class="btn btn-danger btn-sm d-block" data-action="click->mccc--licence#removeEpreuveCcAutre">
            <i class="fas fa-trash"></i>
        </button>
        </div>`

    const div = document.createElement('div')
    div.classList.add('row')
    div.classList.add('epreuve_cc_autre')
    div.innerHTML = html.innerHTML
    document.getElementById('epreuves_cc_autre').appendChild(div)

    let index = 1
    document.querySelectorAll('.epreuve_cc_autre').forEach((element) => {
      const htmlTitre = element.innerHTML
      element.innerHTML = htmlTitre.replace(/Contrôle continu N°[0-9]/g, `Contrôle continu N°${index}`)
      document.getElementById(`pourcentage_s1_cc${index}`).value = tab[index][`pourcentage_s1_cc${index}`]
      index++
    })
  }

  removeEpreuveCtS1 (event) {
    event.preventDefault()
    const div = event.target.closest('.epreuve_ct')
    div.remove()

    // renuméroter les épreuves
    let numEp = 1
    document.querySelectorAll('.epreuve_ct').forEach((element) => {
      // let html = element.innerHTML
      // html = html.replace(/Contrôle terminal N°[0-9]/g, `Contrôle terminal N°${numEp}`)
      // html = html.replace(/ct[0-9]/g, `ct${numEp}`)
      // element.innerHTML = html

      this._renameEpreuveTitle(element, /Contrôle terminal N°[0-9]/, `Contrôle terminal N°${numEp}`)
      this._renumberMcccFormFields(element, /_ct[0-9]/, `_ct${numEp}`)
      numEp++
    })
  }

  removeEpreuveCCTPS1 (event) {
    event.preventDefault()
    const div = event.target.closest('.epreuve_ct')
    div.remove()

    // renuméroter les épreuves
    let numEp = 1
    document.querySelectorAll('.epreuve_ct').forEach((element) => {

      this._renameEpreuveTitle(element, /Contrôle TP N°[0-9]/, `Contrôle TP N°${numEp}`)
      this._renumberMcccTPFormFields(element, /_ct[0-9]/, `_ct${numEp}`)
      numEp++
    })
  }

  removeEpreuveCt (event) {
    event.preventDefault()
    const div = event.target.closest('.epreuve_s2_ct')
    div.remove()

    // renuméroter les épreuves
    let numEp = 1
    document.querySelectorAll('.epreuve_s2_ct').forEach((element) => {
      // let html = element.innerHTML
      // html = html.replace(/Examen 2ᵉ Session N°[0-9]/g, `Examen 2ᵉ Session N°${numEp}`)
      // html = html.replace(/et[0-9]/g, `et${numEp}`)
      // element.innerHTML = html

      this._renameEpreuveTitle(element, /Examen 2ᵉ Session N°[0-9]/, `Examen 2ᵉ Session N°${numEp}`)
      this._renumberMcccFormFields(element, /_ct[0-9]/, `_ct${numEp}`)
      numEp++
    })
  }

  removeEpreuveCcAutre (event) {
    event.preventDefault()
    const div = event.target.closest('.epreuve_cc_autre')
    div.remove()

    // renuméroter les épreuves
    let numEp = 1
    document.querySelectorAll('.epreuve_cc_autre').forEach((element) => {
      let html = element.innerHTML
      html = html.replace(/Contrôle continu N°[0-9]/g, `Contrôle continu N°${numEp}`)
      html = html.replace(/cc[0-9]/g, `cc${numEp}`)
      element.innerHTML = html
      numEp++
    })
  }

  removeEpreuveCci (event) {
    event.preventDefault()
    const div = event.target.closest('.epreuve')
    div.remove()

    // renuméroter les épreuves
    let numEp = 1
    document.querySelectorAll('.epreuve').forEach((element) => {
      element.querySelector('strong').innerHTML = `Epreuve N°${numEp}`
      element.querySelector('input').setAttribute('id', `pourcentage_s${numEp}_cc`)
      element.querySelector('input').setAttribute('name', `pourcentage[${numEp}]`)
      numEp++
    })
  }

  ccHasTp (event) {
    document.getElementById('cc_has_tp_pourcentage').disabled = !event.target.checked
    document.getElementById('ccHasTpBlock').classList.remove(event.target.checked ? 'd-none' : 'd-block')
    document.getElementById('ccHasTpBlock').classList.add(event.target.checked ? 'd-block' : 'd-none')
  }

  ccHasSecondeSession (event) {
    document.getElementById('epreuves_s2_cc_tp').classList.remove(event.target.checked ? 'd-none' : 'd-block')
    document.getElementById('epreuves_s2_cc_tp').classList.add(event.target.checked ? 'd-block' : 'd-none')
  }

  /**
   * Renumérote les éléments d'une épreuve
   * en modifiant leurs attributs HTML
   * id, name, for
   */
  _renumberMcccFormFields (epreuveElement, selector, newValue, withReset = false) {
    // Pourcentage
    const labelPourcentage = epreuveElement.querySelector('label[for^="pourcentage"]')
    const pourcentage = epreuveElement.querySelector('input[id^="pourcentage"]')
    // Type d'épreuve
    const labelTypeEpreuve = epreuveElement.querySelector('label[for^="typeEpreuve"]')
    const typeEpreuve = epreuveElement.querySelector('select[id^="typeEpreuve"]')
    // Durée
    const labelDuree = epreuveElement.querySelector('label[for^="duree"]')
    const duree = epreuveElement.querySelector('input[id^="duree"]')
    // Justification
    const textJustification = epreuveElement.querySelector('textarea[id^="justification"]')
    // Contrôleur Stimulus
    const justificationController = epreuveElement.querySelector('div[data-controller="mccc-with-justification"]')
    const nameJustifController = 'mcccWithJustificationTextAreaFormNameValue'
    const hasJustificationController = 'mcccWithJustificationHasJustificationValue'
    const justificationTextController = 'mcccWithJustificationJustificationTextValue'

    // Modification du contrôleur Stimulus
    justificationController.dataset[nameJustifController] = this._replaceHTMLAttributeValue(
      justificationController.dataset[nameJustifController],
      selector,
      newValue,
    )

    if (withReset) {
      justificationController.dataset[hasJustificationController] = 'false'
      justificationController.dataset[justificationTextController] = ''
      epreuveElement.querySelector('div[data-mccc-with-justification-target="displayDiv"]').classList.add('d-none')
    }

    const elements = [
      labelPourcentage,
      pourcentage,
      labelTypeEpreuve,
      typeEpreuve,
      labelDuree,
      duree,
      textJustification,
    ]
    elements.forEach((attr) => {
      if (attr) {
        ['htmlFor', 'id', 'name'].forEach((property) => {
          if (attr[property] !== undefined) {
            attr[property] = this._replaceHTMLAttributeValue(attr[property], selector, newValue)
          }
        })
      }
    })
  }

  _renumberMcccTPFormFields (epreuveElement, selector, newValue, withReset = false) {
    // Pourcentage
    const labelPourcentage = epreuveElement.querySelector('label[for^="pourcentage"]')
    const pourcentage = epreuveElement.querySelector('input[id^="pourcentage"]')

    const elements = [
      labelPourcentage,
      pourcentage,
    ]
    elements.forEach((attr) => {
      if (attr) {
        ['htmlFor', 'id', 'name'].forEach((property) => {
          if (attr[property] !== undefined) {
            attr[property] = this._replaceHTMLAttributeValue(attr[property], selector, newValue)
          }
        })
      }
    })
  }

  _renameEpreuveTitle (epreuveElement, selector, newValue) {
    epreuveElement.querySelector(':first-child strong').innerHTML = epreuveElement.querySelector(':first-child strong')
      .innerHTML.replace(selector, newValue)
  }

  _replaceHTMLAttributeValue (attribute, selector, newValue) {
    return attribute.replace(selector, newValue)
  }
}
