import { Controller } from '@hotwired/stimulus'

/**
 * Contrôleur Stimulus pour gérer les champs de configuration JSON.
 * Permet de valider, formater et éditer du JSON de manière accessible.
 * Inclut un mode wizard pour une création intuitive.
 */
export default class extends Controller {
  static targets = ['textarea', 'validation', 'wizardMode', 'jsonMode', 'wizardContainer']
  static values = { value: Object }

  connect () {
    this.validate()
    this.currentMode = 'json' // Mode par défaut
  }

  /**
   * Bascule vers le mode wizard
   */
  switchToWizard () {
    this.currentMode = 'wizard'
    this.jsonModeTarget.classList.add('hidden')
    this.wizardModeTarget.classList.remove('hidden')
    this.loadWizardFromJson()
  }

  /**
   * Bascule vers le mode JSON
   */
  switchToJson () {
    this.currentMode = 'json'
    this.wizardModeTarget.classList.add('hidden')
    this.jsonModeTarget.classList.remove('hidden')
    this.updateJsonFromWizard()
  }

  /**
   * Charge le wizard à partir du JSON existant
   */
  loadWizardFromJson () {
    const value = this.textareaTarget.value.trim()
    let data = {}

    if (value) {
      try {
        data = JSON.parse(value)
      } catch (error) {
        console.error('Erreur lors du parsing du JSON:', error)
        return
      }
    }

    // Vider le conteneur
    this.wizardContainerTarget.innerHTML = ''

    // Créer une ligne pour chaque paire clé-valeur
    Object.entries(data).forEach(([key, value]) => {
      this.addWizardRow(key, value)
    })

    // Si vide, ajouter une ligne vide pour commencer
    if (Object.keys(data).length === 0) {
      this.addWizardRow('', '')
    }
  }

  /**
   * Met à jour le JSON à partir du wizard
   */
  updateJsonFromWizard () {
    const rows = this.wizardContainerTarget.querySelectorAll('[data-wizard-row]')
    const data = {}

    rows.forEach(row => {
      const keyInput = row.querySelector('[data-wizard-key]')
      const valueInput = row.querySelector('[data-wizard-value]')
      const typeSelect = row.querySelector('[data-wizard-type]')

      const key = keyInput.value.trim()
      if (key) {
        let value = valueInput.value
        const type = typeSelect.value

        // Convertir selon le type
        switch (type) {
          case 'number':
            value = Number(value)
            break
          case 'boolean':
            value = value === 'true' || value === '1'
            break
          case 'array':
            try {
              value = JSON.parse(value)
            } catch {
              value = value.split(',').map(v => v.trim())
            }
            break
          case 'object':
            try {
              value = JSON.parse(value)
            } catch {
              value = {}
            }
            break
          default: // string
            value = String(value)
        }

        data[key] = value
      }
    })

    this.textareaTarget.value = JSON.stringify(data, null, 2)
    this.validate()
  }

  /**
   * Ajoute une nouvelle ligne dans le wizard
   */
  addWizardRow (key = '', value = '', type = 'string') {
    const row = document.createElement('div')
    row.setAttribute('data-wizard-row', '')
    row.className = 'grid grid-cols-12 gap-2 items-start p-3 bg-gray-50 rounded-md'

    // Déterminer le type automatiquement si c'est un chargement
    if (value !== '') {
      if (typeof value === 'number') {
        type = 'number'
      } else if (typeof value === 'boolean') {
        type = 'boolean'
        value = value ? 'true' : 'false'
      } else if (Array.isArray(value)) {
        type = 'array'
        value = JSON.stringify(value)
      } else if (typeof value === 'object') {
        type = 'object'
        value = JSON.stringify(value)
      }
    }

    row.innerHTML = `
            <div class="col-span-3">
                <input type="text" 
                    data-wizard-key 
                    value="${this.escapeHtml(key)}"
                    placeholder="clé"
                    class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary focus:border-primary">
            </div>
            <div class="col-span-2">
                <select data-wizard-type 
                    data-action="change->json-config#onTypeChange"
                    class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary focus:border-primary">
                    <option value="string" ${type === 'string' ? 'selected' : ''}>Texte</option>
                    <option value="number" ${type === 'number' ? 'selected' : ''}>Nombre</option>
                    <option value="boolean" ${type === 'boolean' ? 'selected' : ''}>Booléen</option>
                    <option value="array" ${type === 'array' ? 'selected' : ''}>Tableau</option>
                    <option value="object" ${type === 'object' ? 'selected' : ''}>Objet</option>
                </select>
            </div>
            <div class="col-span-6">
                ${this.getValueInput(type, value)}
            </div>
            <div class="col-span-1 flex justify-end">
                <button type="button" 
                    data-action="click->json-config#removeWizardRow"
                    class="text-red-600 hover:text-red-800 p-1">
                    <i class="fas fa-trash text-sm"></i>
                </button>
            </div>
        `

    this.wizardContainerTarget.appendChild(row)
  }

  /**
   * Retourne l'input approprié selon le type
   */
  getValueInput (type, value) {
    switch (type) {
      case 'boolean':
        return `
                    <select data-wizard-value 
                        class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="true" ${value === 'true' || value === true ? 'selected' : ''}>Vrai</option>
                        <option value="false" ${value === 'false' || value === false ? 'selected' : ''}>Faux</option>
                    </select>
                `
      case 'number':
        return `
                    <input type="number" 
                        data-wizard-value 
                        value="${this.escapeHtml(value)}"
                        placeholder="123"
                        class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary focus:border-primary">
                `
      case 'array':
      case 'object':
        return `
                    <input type="text" 
                        data-wizard-value 
                        value="${this.escapeHtml(value)}"
                        placeholder='${type === 'array' ? '["item1", "item2"]' : '{"clé": "valeur"}'}'
                        class="w-full px-2 py-1.5 text-sm font-mono border border-gray-300 rounded focus:ring-2 focus:ring-primary focus:border-primary">
                `
      default: // string
        return `
                    <input type="text" 
                        data-wizard-value 
                        value="${this.escapeHtml(value)}"
                        placeholder="valeur"
                        class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary focus:border-primary">
                `
    }
  }

  /**
   * Gère le changement de type dans le wizard
   */
  onTypeChange (event) {
    const select = event.target
    const row = select.closest('[data-wizard-row]')
    const valueContainer = row.querySelector('.col-span-6')
    const currentValue = row.querySelector('[data-wizard-value]').value

    valueContainer.innerHTML = this.getValueInput(select.value, currentValue)
  }

  /**
   * Supprime une ligne du wizard
   */
  removeWizardRow (event) {
    const row = event.target.closest('[data-wizard-row]')
    row.remove()

    // Si plus de lignes, en ajouter une vide
    if (this.wizardContainerTarget.children.length === 0) {
      this.addWizardRow('', '')
    }
  }

  /**
   * Ajoute une nouvelle ligne vide dans le wizard
   */
  addNewWizardRow () {
    this.addWizardRow('', '')
  }

  /**
   * Valide le JSON saisi
   */
  validate () {
    const textarea = this.textareaTarget
    const validation = this.validationTarget
    const value = textarea.value.trim()

    if (!value) {
      this.hideValidation()
      return true
    }

    try {
      JSON.parse(value)
      this.showSuccess('✓ JSON valide')
      textarea.classList.remove('border-red-500')
      textarea.classList.add('border-green-500')
      return true
    } catch (error) {
      this.showError(`✗ JSON invalide : ${error.message}`)
      textarea.classList.remove('border-green-500')
      textarea.classList.add('border-red-500')
      return false
    }
  }

  /**
   * Formate le JSON de manière lisible
   */
  format () {
    const textarea = this.textareaTarget
    const value = textarea.value.trim()

    if (!value) {
      return
    }

    try {
      const parsed = JSON.parse(value)
      textarea.value = JSON.stringify(parsed, null, 2)
      this.showSuccess('✓ JSON formaté')
      textarea.classList.remove('border-red-500')
      textarea.classList.add('border-green-500')
    } catch (error) {
      this.showError(`✗ Impossible de formater : ${error.message}`)
    }
  }

  /**
   * Vide le champ
   */
  clear () {
    if (confirm('Êtes-vous sûr de vouloir effacer toute la configuration ?')) {
      this.textareaTarget.value = ''
      this.hideValidation()
      this.textareaTarget.classList.remove('border-red-500', 'border-green-500')

      if (this.currentMode === 'wizard') {
        this.wizardContainerTarget.innerHTML = ''
        this.addWizardRow('', '')
      }
    }
  }

  /**
   * Affiche un message de succès
   */
  showSuccess (message) {
    const validation = this.validationTarget
    validation.textContent = message
    validation.className = 'mt-1 text-sm text-green-600'
    validation.classList.remove('hidden')
  }

  /**
   * Affiche un message d'erreur
   */
  showError (message) {
    const validation = this.validationTarget
    validation.textContent = message
    validation.className = 'mt-1 text-sm text-red-600'
    validation.classList.remove('hidden')
  }

  /**
   * Cache le message de validation
   */
  hideValidation () {
    this.validationTarget.classList.add('hidden')
  }

  /**
   * Échappe les caractères HTML
   */
  escapeHtml (text) {
    const div = document.createElement('div')
    div.textContent = text
    return div.innerHTML
  }
}
