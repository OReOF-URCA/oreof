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

  connect() {
    //récupérer la valeur checked dans mccc[typeMccc]
    const selectedTypeMccc = document.querySelector(`input[name="mccc[typeMccc]"]:checked`)
    const choix = selectedTypeMccc ? selectedTypeMccc.value : null
    if (choix !== null) {
      this._loadTypeMccc(choix).then(() => {
        this._verifyTypeEpreuveCt()
        this._verifyTypeEpreuveEt()
      })
    }
  }

  updateForm() {
    if (this.typeMcccValue !== null && this.afficheMcccValue === true) {
      this._loadTypeMccc(this.typeMcccValue)
    }
  }

  updateType (event) {
    this._loadTypeMccc(event.target.value)
  }

  _verifyTypeEpreuveEt() {
    document.querySelectorAll('.typeEpreuveSelectEt').forEach((element) => {
      const name = element.getAttribute('name')
      this._changeTypeEpreuveEt(name)
    })
  }

  _verifyTypeEpreuveCt() {
    document.querySelectorAll('.typeEpreuveSelectCt').forEach((element) => {
      const name = element.getAttribute('name')
      this._changeTypeEpreuveCt(name)
    })
  }

  changeType(event) {
    if (confirm('Attention, vous allez perdre les données saisies. Êtes-vous sûr ?')) {
      this._loadTypeMccc(event.target.value)
    }
  }

  changeTypeEpreuveCt(event) {
    const name = event.target.getAttribute('name')
    this._changeTypeEpreuveCt(name)
  }

  _changeTypeEpreuveCt(name) {
    const numEpreuve = name.substr(name.lastIndexOf('_') + 1)
    const option1 = document.querySelector(`#typeEpreuve_s1_${numEpreuve} option:checked`)
    document.getElementById(`duree_s1_${numEpreuve}`).disabled = !(parseInt(option1.dataset.hasduree, 10) === 1)

    if (document.getElementById(`duree_s1_${numEpreuve}`).disabled === true) {
      document.getElementById(`duree_s1_${numEpreuve}`).value = ''
    }
  }

  changeTypeEpreuveEt(event) {
    const name = event.target.getAttribute('name')
    this._changeTypeEpreuveEt(name)
  }

  _changeTypeEpreuveEt(name) {
    const numEpreuve = name.substr(name.lastIndexOf('_') + 1)
    const option1 = document.querySelector(`#typeEpreuve_s2_${numEpreuve} option:checked`)
    document.getElementById(`duree_s2_${numEpreuve}`).disabled = !(parseInt(option1.dataset.hasduree, 10) === 1)

    if (document.getElementById(`duree_s2_${numEpreuve}`).disabled === true) {
      document.getElementById(`duree_s2_${numEpreuve}`).value = ''
    }
  }

  async _loadTypeMccc(typeMccc) {
    const params = new URLSearchParams()
    params.append('type', typeMccc)

    const response = await fetch(`${this.urlValue}?${params.toString()}`)
    this.zoneTarget.innerHTML = await response.text()
  }

  saveDataCcCt() {
    const pourcentageCc = parseFloat(document.getElementById('pourcentage_s1_cc')?.value ?? '0')
    const pourcentageEtChamp = document.getElementById('pourcentage_s1_et')
    const pourcentageEt = pourcentageEtChamp
      ? parseFloat(pourcentageEtChamp.value ?? '0')
      : parseFloat(document.querySelector('input[id^="pourcentage_s1_ct"]')?.value ?? '0')

    const total = (Number.isNaN(pourcentageCc) ? 0 : pourcentageCc) + (Number.isNaN(pourcentageEt) ? 0 : pourcentageEt)

    if (total !== 100) {
      this._showErreur('Le pourcentage doit être de 100%')
      return
    }

    this._hideErreur()
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

  saveDataCci() {
    let total = 0
    const erreurs = []
    document.querySelectorAll('.pourcentage').forEach((element) => {
      const value = parseFloat(element.value)
      if (!Number.isNaN(value)) {
        total += value
      }
      if (value >= 50) {
        erreurs.push('Le pourcentage d\'une épreuve ne doit pas dépasser 50%')
      }
    })

    if (total !== 100) {
      erreurs.push('Le pourcentage doit être de 100%')
    }

    if (erreurs.length > 0) {
      this._showErreur(erreurs.join('<br>'))
      return
    }

    this._hideErreur()
  }

  addEpreuveCci(event) {
    event.preventDefault()
    // ajouter un nouveau champs pour une nouvelle épreuve
    const div = document.createElement('div')
    const nbEpreuves = document.querySelectorAll('.epreuve').length
    const numEp = nbEpreuves + 1
    div.className = 'epreuve grid grid-cols-1 gap-3 rounded-lg border border-secondary-200 bg-white p-4 md:grid-cols-12'
    div.innerHTML = `
      <div class="md:col-span-4">
        <strong class="text-secondary-800">Epreuve N°${numEp}</strong>
      </div>
      <div class="md:col-span-6">
        <label for="pourcentage_s${numEp}_cc" class="mb-1 block text-sm font-medium text-secondary-700">Pourcentage</label>
        <div class="flex rounded-lg border border-secondary-300 bg-white">
          <input type="text" class="pourcentage synchro-mccc block w-full rounded-l-lg border-0 bg-transparent px-3 py-2 text-sm text-secondary-900"
                 id="pourcentage_s${numEp}_cc"
                 name="pourcentage[${numEp}]"
                 data-action="change->mccc--licence#saveDataCci"
                 value="">
          <span class="inline-flex items-center rounded-r-lg border-l border-secondary-300 bg-secondary-50 px-3 text-sm text-secondary-600">%</span>
        </div>
      </div>
      <div class="md:col-span-2 md:pt-6">
        <button type="button"
                class="synchro-mccc inline-flex h-9 w-9 items-center justify-center rounded-md bg-red-600 text-white transition-colors hover:bg-red-700"
                data-action="click->mccc--licence#removeEpreuveCci">
          <i class="fas fa-trash"></i>
        </button>
      </div>
    `
    document.getElementById('epreuve_cci').appendChild(div)
  }

  addEpreuveSecondeSession(event) {
    event.preventDefault()
    // ajouter un nouveau champs pour une nouvelle épreuve
    const div = document.createElement('div')
    const nbEpreuves = document.querySelectorAll('.epreuve_s2_ct').length
    const numEp = nbEpreuves + 1
    div.className = 'epreuve_s2_ct grid grid-cols-1 gap-3 rounded-lg border border-secondary-200 bg-white p-4 md:grid-cols-12'

    // récupérer le contenu de la première épreuve, et le dupliquer
    // const epreuve1 = document.querySelector('.epreuve_s2_ct')
    // let html = epreuve1.innerHTML
    // html = html.replace(/ct1/g, `ct${numEp}`)
    const html = document.querySelector('.epreuve_s2_ct').cloneNode(true);
    this._renumberMcccFormFields(html, /_ct1/, `_ct${numEp}`, true);

    // ajouter le numéro de l'epreuve dans le texte
    // parcours tous les éléments da epreuve_et et numéroter le texte

    html.innerHTML += `
      <div class="hidden md:col-span-8 md:block"></div>
      <div class="md:col-span-4 md:pt-2">
        <button type="button"
                class="synchro-mccc inline-flex h-9 w-9 items-center justify-center rounded-md bg-red-600 text-white transition-colors hover:bg-red-700"
                data-action="click->mccc--licence#removeEpreuveCt">
          <i class="fas fa-trash"></i>
        </button>
      </div>`
    div.innerHTML = html.innerHTML
    document.getElementById('epreuves_s2_ct').appendChild(div)

    let index = 1
    document.querySelectorAll('.epreuve_s2_ct').forEach((element, indexLoop) => {
      // const htmlTitre = element.innerHTML
      // element.innerHTML = htmlTitre.replace(/Session N°[0-9]/g, `Session N°${index}`)
      this._renameEpreuveTitle(element, /Session N°[0-9]/, `Session N°${index}`);

      // On remet à zéro la nouvelle épreuve (dernière de la liste)
      if (indexLoop === nbEpreuves) {
        element.querySelector('input[id^="pourcentage"]').value = ''
        element.querySelector('select[id^="typeEpreuve"]').selectedIndex = 0;
        element.querySelector('input[id^="duree"]').value = ''
        element.querySelector('textarea[id^="justification"]').required = false;
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

  addEpreuveCt(event) {
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
    this._renumberMcccFormFields(html, /_ct1/, `_ct${numEp}`, true);

    // Initialisation de la nouvelle épreuve
    // À ce moment là, l'index 'idx' a la bonne valeur
    // car incrémenté en fin de boucle précédente
    const newEpreuve = []
    newEpreuve[`pourcentage_s1_ct${idx}`] = ''
    newEpreuve[`typeEpreuve_s1_ct${idx}`] = ''
    newEpreuve[`duree_s1_ct${idx}`] = ''
    tab[idx] = newEpreuve;

    // ajouter le numéro de l'epreuve dans le texte
    // parcours tous les éléments da epreuve_et et numéroter le texte

    html.innerHTML += `
      <div class="hidden md:col-span-8 md:block"></div>
      <div class="md:col-span-4 md:pt-2">
        <button type="button"
                class="synchro-mccc inline-flex h-9 w-9 items-center justify-center rounded-md bg-red-600 text-white transition-colors hover:bg-red-700"
                data-action="click->mccc--licence#removeEpreuveCtS1">
          <i class="fas fa-trash"></i>
        </button>
      </div>`

    const div = document.createElement('div')
    div.className = 'epreuve_ct grid grid-cols-1 gap-3 rounded-lg border border-secondary-200 bg-white p-4 md:grid-cols-12'
    div.innerHTML = html.innerHTML
    document.getElementById('epreuves_ct').appendChild(div)

    let index = 1
    document.querySelectorAll('.epreuve_ct').forEach((element) => {
      // const htmlTitre = element.innerHTML
      // element.innerHTML = htmlTitre.replace(/Contrôle terminal N°[0-9]/g, `Contrôle terminal N°${index}`)
      this._renameEpreuveTitle(element, /Contrôle terminal N°[0-9]/, `Contrôle terminal N°${index}`);
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

  addEpreuveCcAutresDiplomes(event) {
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
      <div class="hidden md:col-span-8 md:block"></div>
      <div class="md:col-span-4 md:pt-2">
        <button type="button"
                class="synchro-mccc inline-flex h-9 w-9 items-center justify-center rounded-md bg-red-600 text-white transition-colors hover:bg-red-700"
                data-action="click->mccc--licence#removeEpreuveCcAutre">
          <i class="fas fa-trash"></i>
        </button>
      </div>`

    const div = document.createElement('div')
    div.className = 'epreuve_cc_autre grid grid-cols-1 gap-3 rounded-lg border border-secondary-200 bg-white p-4 md:grid-cols-12'
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

  removeEpreuveCtS1(event) {
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

      this._renameEpreuveTitle(element, /Contrôle terminal N°[0-9]/, `Contrôle terminal N°${numEp}`);
      this._renumberMcccFormFields(element, /_ct[0-9]/, `_ct${numEp}`);
      numEp++
    })
  }

  removeEpreuveCt(event) {
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

      this._renameEpreuveTitle(element, /Examen 2ᵉ Session N°[0-9]/, `Examen 2ᵉ Session N°${numEp}`);
      this._renumberMcccFormFields(element, /_ct[0-9]/, `_ct${numEp}`);
      numEp++
    })
  }

  removeEpreuveCcAutre(event) {
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

  removeEpreuveCci(event) {
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

  ccHasTp(event) {
    document.getElementById('cc_has_tp_pourcentage').disabled = !event.target.checked
    const target = document.getElementById('ccHasTpBlock')
    target.classList.remove(event.target.checked ? 'd-none' : 'd-block')
    target.classList.add(event.target.checked ? 'd-block' : 'd-none')
    target.classList.toggle('hidden', !event.target.checked)
  }

  /**
   * Renumérote les éléments d'une épreuve
   * en modifiant leurs attributs HTML
   * id, name, for
   */
  _renumberMcccFormFields(epreuveElement, selector, newValue, withReset = false) {
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
    );

    if (withReset) {
      justificationController.dataset[hasJustificationController] = 'false'
      justificationController.dataset[justificationTextController] = ''
      const justifDiv = epreuveElement.querySelector('div[data-mccc-with-justification-target="displayDiv"]')
      justifDiv.classList.add('d-none')
      justifDiv.classList.add('hidden')
    }

    [
      labelPourcentage,
      pourcentage,
      labelTypeEpreuve,
      typeEpreuve,
      labelDuree,
      duree,
      textJustification,
    ].forEach((attr) => {
      ['htmlFor', 'id', 'name'].forEach((property) => {
        if (attr[property] !== undefined) {
          attr[property] = this._replaceHTMLAttributeValue(attr[property], selector, newValue)
        }
      })
    });
  }

  _renameEpreuveTitle(epreuveElement, selector, newValue) {
    epreuveElement.querySelector(':first-child strong').innerHTML = epreuveElement.querySelector(':first-child strong')
      .innerHTML.replace(selector, newValue);
  }

  _replaceHTMLAttributeValue(attribute, selector, newValue) {
    return attribute.replace(selector, newValue);
  }

  _showErreur (message) {
    this.zoneErreurTarget.classList.remove('d-none')
    this.zoneErreurTarget.classList.remove('hidden')
    this.zoneErreurTarget.innerHTML = message
  }

  _hideErreur () {
    this.zoneErreurTarget.classList.add('d-none')
    this.zoneErreurTarget.classList.add('hidden')
    this.zoneErreurTarget.innerHTML = ''
  }
}
