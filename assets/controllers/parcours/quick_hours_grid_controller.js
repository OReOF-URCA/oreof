import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = [
    'ecRow',
    'inputCell',
    'ecTotal',
    'ecDelta',
    'ecStatus',
    'ueTotal',
    'ueDelta',
    'ueEcts',
    'semestreTotal',
    'semestreDelta',
    'semestreEcts',
    'anneeTotal',
    'anneeDelta',
    'anneeEcts',
    'stockCmPres',
    'stockTdPres',
    'stockTpPres',
    'stockTePres',
    'stockCmDist',
    'stockTdDist',
    'stockTpDist',
    'stockTotalGlobal',
    'stockBadge',
    'stockTargetBalance',
  ]

  static values = {
    saveUrlTemplate: String,
    parcoursId: Number,
  }

  connect () {
    this.saveTimers = {}
    this.abortControllers = {}
    this.targetOffset = parseFloat(sessionStorage.getItem(`oreof_stock_offset_${this.parcoursIdValue}`) || '0')
    this.recalculateAll()
  }

  /* -------------------------------------------------------------
   * Saisie et calculs en cascade
   * ----------------------------------------------------------- */
  onInput (event) {
    const input = event.target
    const row = input.closest('[data-parcours--quick-hours-grid-target="ecRow"]')
    if (!row) return

    this.recalculateRow(row)
    this.recalculateAll()
    this.queueSave(row)
  }

  recalculateRow (row) {
    const isSansHeure = row.querySelector('.js-sans-heure-checkbox')?.checked ?? false

    const parseNum = (selector) => {
      const el = row.querySelector(selector)
      if (!el) return 0
      const v = parseFloat(el.value)
      return isNaN(v) ? 0 : Math.max(0, v)
    }

    const ects = parseNum('[data-field="ects"]')
    const cmPres = isSansHeure ? 0 : parseNum('[data-field="cmPres"]')
    const tdPres = isSansHeure ? 0 : parseNum('[data-field="tdPres"]')
    const tpPres = isSansHeure ? 0 : parseNum('[data-field="tpPres"]')
    const tePres = isSansHeure ? 0 : parseNum('[data-field="tePres"]')

    const cmDist = isSansHeure ? 0 : parseNum('[data-field="cmDist"]')
    const tdDist = isSansHeure ? 0 : parseNum('[data-field="tdDist"]')
    const tpDist = isSansHeure ? 0 : parseNum('[data-field="tpDist"]')

    const totalPres = cmPres + tdPres + tpPres
    const totalDist = cmDist + tdDist + tpDist
    const totalGlobal = totalPres + totalDist

    const refGlobal = parseFloat(row.dataset.refGlobal || '0')
    const deltaGlobal = totalGlobal - refGlobal

    // Update row DOM
    const totalEl = row.querySelector('[data-parcours--quick-hours-grid-target="ecTotal"]')
    if (totalEl) {
      totalEl.textContent = `${this.formatHours(totalGlobal)}h`
    }

    const deltaEl = row.querySelector('[data-parcours--quick-hours-grid-target="ecDelta"]')
    if (deltaEl) {
      this.renderDeltaBadge(deltaEl, deltaGlobal)
    }

    row.dataset.currentEcts = ects
    row.dataset.currentCmPres = cmPres
    row.dataset.currentTdPres = tdPres
    row.dataset.currentTpPres = tpPres
    row.dataset.currentTePres = tePres
    row.dataset.currentCmDist = cmDist
    row.dataset.currentTdDist = tdDist
    row.dataset.currentTpDist = tpDist
    row.dataset.currentTotalGlobal = totalGlobal
    row.dataset.currentDeltaGlobal = deltaGlobal
  }

  recalculateAll () {
    let globalCmPres = 0
    let globalTdPres = 0
    let globalTpPres = 0
    let globalTePres = 0
    let globalCmDist = 0
    let globalTdDist = 0
    let globalTpDist = 0

    let globalRefCmPres = 0
    let globalRefTdPres = 0
    let globalRefTpPres = 0
    let globalRefTePres = 0
    let globalRefCmDist = 0
    let globalRefTdDist = 0
    let globalRefTpDist = 0

    let globalTotal = 0
    let globalRefTotal = 0

    // Grouping by UE, Semestre, Annee
    const ueSums = {}
    const semSums = {}
    const anneeSums = {}

    this.ecRowTargets.forEach(row => {
      const ueId = row.dataset.ueId
      const semId = row.dataset.semestreId
      const anneeId = row.dataset.anneeId

      const ects = parseFloat(row.dataset.currentEcts || row.querySelector('[data-field="ects"]')?.value || '0')
      const cmP = parseFloat(row.dataset.currentCmPres || row.querySelector('[data-field="cmPres"]')?.value || '0')
      const tdP = parseFloat(row.dataset.currentTdPres || row.querySelector('[data-field="tdPres"]')?.value || '0')
      const tpP = parseFloat(row.dataset.currentTpPres || row.querySelector('[data-field="tpPres"]')?.value || '0')
      const teP = parseFloat(row.dataset.currentTePres || row.querySelector('[data-field="tePres"]')?.value || '0')
      const cmD = parseFloat(row.dataset.currentCmDist || row.querySelector('[data-field="cmDist"]')?.value || '0')
      const tdD = parseFloat(row.dataset.currentTdDist || row.querySelector('[data-field="tdDist"]')?.value || '0')
      const tpD = parseFloat(row.dataset.currentTpDist || row.querySelector('[data-field="tpDist"]')?.value || '0')

      const refCmP = parseFloat(row.dataset.refCmPres || '0')
      const refTdP = parseFloat(row.dataset.refTdPres || '0')
      const refTpP = parseFloat(row.dataset.refTpPres || '0')
      const refTeP = parseFloat(row.dataset.refTePres || '0')
      const refCmD = parseFloat(row.dataset.refCmDist || '0')
      const refTdD = parseFloat(row.dataset.refTdDist || '0')
      const refTpD = parseFloat(row.dataset.refTpDist || '0')

      const curTotal = cmP + tdP + tpP + cmD + tdD + tpD
      const refTotal = parseFloat(row.dataset.refGlobal || '0')

      globalCmPres += cmP
      globalTdPres += tdP
      globalTpPres += tpP
      globalTePres += teP
      globalCmDist += cmD
      globalTdDist += tdD
      globalTpDist += tpD

      globalRefCmPres += refCmP
      globalRefTdPres += refTdP
      globalRefTpPres += refTpP
      globalRefTePres += refTeP
      globalRefCmDist += refCmD
      globalRefTdDist += refTdD
      globalRefTpDist += refTpD

      globalTotal += curTotal
      globalRefTotal += refTotal

      // UE
      if (!ueSums[ueId]) ueSums[ueId] = { total: 0, ref: 0, ects: 0 }
      ueSums[ueId].total += curTotal
      ueSums[ueId].ref += refTotal
      ueSums[ueId].ects += ects

      // Semestre
      if (!semSums[semId]) semSums[semId] = { total: 0, ref: 0, ects: 0 }
      semSums[semId].total += curTotal
      semSums[semId].ref += refTotal
      semSums[semId].ects += ects

      // Annee
      if (!anneeSums[anneeId]) anneeSums[anneeId] = { total: 0, ref: 0, ects: 0 }
      anneeSums[anneeId].total += curTotal
      anneeSums[anneeId].ref += refTotal
      anneeSums[anneeId].ects += ects
    })

    // Update UE summary badges
    this.ueTotalTargets.forEach(el => {
      const ueId = el.dataset.ueId
      if (ueSums[ueId]) {
        el.textContent = `${this.formatHours(ueSums[ueId].total)}h`
      }
    })
    this.ueDeltaTargets.forEach(el => {
      const ueId = el.dataset.ueId
      if (ueSums[ueId]) {
        const delta = ueSums[ueId].total - ueSums[ueId].ref
        this.renderDeltaBadge(el, delta)
      }
    })
    this.ueEctsTargets.forEach(el => {
      const ueId = el.dataset.ueId
      if (ueSums[ueId]) {
        el.textContent = `${this.formatHours(ueSums[ueId].ects)} ECTS`
      }
    })

    // Update Semestre summary badges
    this.semestreTotalTargets.forEach(el => {
      const semId = el.dataset.semestreId
      if (semSums[semId]) {
        el.textContent = `${this.formatHours(semSums[semId].total)}h`
      }
    })
    this.semestreDeltaTargets.forEach(el => {
      const semId = el.dataset.semestreId
      if (semSums[semId]) {
        const delta = semSums[semId].total - semSums[semId].ref
        this.renderDeltaBadge(el, delta)
      }
    })
    this.semestreEctsTargets.forEach(el => {
      const semId = el.dataset.semestreId
      if (semSums[semId]) {
        const ects = Math.round(semSums[semId].ects * 10) / 10
        if (ects === 30) {
          el.className = 'px-2.5 py-1 bg-emerald-50 text-emerald-800 border border-emerald-300 rounded-md font-bold shadow-xs flex items-center gap-1.5'
          el.innerHTML = `<i class="fal fa-check text-emerald-600"></i> ${this.formatHours(ects)} / 30 ECTS`
        } else if (ects < 30) {
          el.className = 'px-2.5 py-1 bg-amber-50 text-amber-800 border border-amber-300 rounded-md font-bold shadow-xs flex items-center gap-1.5'
          el.innerHTML = `<i class="fal fa-exclamation-triangle text-amber-600"></i> ${this.formatHours(ects)} / 30 ECTS <span class="text-[10px] font-medium opacity-80">(-${this.formatHours(30 - ects)})</span>`
        } else {
          el.className = 'px-2.5 py-1 bg-blue-50 text-blue-800 border border-blue-300 rounded-md font-bold shadow-xs flex items-center gap-1.5'
          el.innerHTML = `<i class="fal fa-exclamation-circle text-blue-600"></i> ${this.formatHours(ects)} / 30 ECTS <span class="text-[10px] font-medium opacity-80">(+${this.formatHours(ects - 30)})</span>`
        }
      }
    })

    // Update Annee summary badges
    this.anneeTotalTargets.forEach(el => {
      const anneeId = el.dataset.anneeId
      if (anneeSums[anneeId]) {
        el.textContent = `${this.formatHours(anneeSums[anneeId].total)}h`
      }
    })
    this.anneeDeltaTargets.forEach(el => {
      const anneeId = el.dataset.anneeId
      if (anneeSums[anneeId]) {
        const delta = anneeSums[anneeId].total - anneeSums[anneeId].ref
        this.renderDeltaBadge(el, delta)
      }
    })
    this.anneeEctsTargets.forEach(el => {
      const anneeId = el.dataset.anneeId
      if (anneeSums[anneeId]) {
        const ects = Math.round(anneeSums[anneeId].ects * 10) / 10
        if (ects === 60) {
          el.className = 'px-2.5 py-1 bg-emerald-50 text-emerald-800 border border-emerald-300 rounded-md font-bold shadow-xs flex items-center gap-1.5'
          el.innerHTML = `<i class="fal fa-check text-emerald-600"></i> ${this.formatHours(ects)} / 60 ECTS`
        } else if (ects < 60) {
          el.className = 'px-2.5 py-1 bg-amber-50 text-amber-800 border border-amber-300 rounded-md font-bold shadow-xs flex items-center gap-1.5'
          el.innerHTML = `<i class="fal fa-exclamation-triangle text-amber-600"></i> ${this.formatHours(ects)} / 60 ECTS <span class="text-[10px] font-medium opacity-80">(-${this.formatHours(60 - ects)})</span>`
        } else {
          el.className = 'px-2.5 py-1 bg-blue-50 text-blue-800 border border-blue-300 rounded-md font-bold shadow-xs flex items-center gap-1.5'
          el.innerHTML = `<i class="fal fa-exclamation-circle text-blue-600"></i> ${this.formatHours(ects)} / 60 ECTS <span class="text-[10px] font-medium opacity-80">(+${this.formatHours(ects - 60)})</span>`
        }
      }
    })

    // Update Global Stock / Delta Badges in Sticky Top Bar
    const deltaCmPres = globalCmPres - globalRefCmPres
    const deltaTdPres = globalTdPres - globalRefTdPres
    const deltaTpPres = globalTpPres - globalRefTpPres
    const deltaTePres = globalTePres - globalRefTePres
    const deltaCmDist = globalCmDist - globalRefCmDist
    const deltaTdDist = globalTdDist - globalRefTdDist
    const deltaTpDist = globalTpDist - globalRefTpDist
    const deltaTotal = globalTotal - globalRefTotal

    if (this.hasStockCmPresTarget) this.renderDeltaBadge(this.stockCmPresTarget, deltaCmPres, 'CM Prés')
    if (this.hasStockTdPresTarget) this.renderDeltaBadge(this.stockTdPresTarget, deltaTdPres, 'TD Prés')
    if (this.hasStockTpPresTarget) this.renderDeltaBadge(this.stockTpPresTarget, deltaTpPres, 'TP Prés')
    if (this.hasStockTePresTarget) this.renderDeltaBadge(this.stockTePresTarget, deltaTePres, 'TE')
    if (this.hasStockCmDistTarget) this.renderDeltaBadge(this.stockCmDistTarget, deltaCmDist, 'CM Dist')
    if (this.hasStockTdDistTarget) this.renderDeltaBadge(this.stockTdDistTarget, deltaTdDist, 'TD Dist')
    if (this.hasStockTpDistTarget) this.renderDeltaBadge(this.stockTpDistTarget, deltaTpDist, 'TP Dist')
    if (this.hasStockTotalGlobalTarget) this.renderDeltaBadge(this.stockTotalGlobalTarget, deltaTotal, 'Total')

    // Stock buffer / Target balance
    const netBalance = deltaTotal - this.targetOffset
    if (this.hasStockTargetBalanceTarget) {
      const sign = netBalance > 0 ? '+' : ''
      this.stockTargetBalanceTarget.textContent = `${sign}${this.formatHours(netBalance)}h`
      this.stockTargetBalanceTarget.className = `font-black text-sm px-2 py-0.5 rounded ${
        netBalance === 0
          ? 'bg-emerald-100 text-emerald-800'
          : netBalance > 0
          ? 'bg-amber-100 text-amber-800'
          : 'bg-blue-100 text-blue-800'
      }`
    }
  }

  /* -------------------------------------------------------------
   * Gestion du repère de stock / Buffer
   * ----------------------------------------------------------- */
  setTargetReference (event) {
    let globalTotal = 0
    let globalRefTotal = 0

    this.ecRowTargets.forEach(row => {
      const curTotal = parseFloat(row.dataset.currentTotalGlobal || '0')
      const refTotal = parseFloat(row.dataset.refGlobal || '0')
      globalTotal += curTotal
      globalRefTotal += refTotal
    })

    const currentDelta = globalTotal - globalRefTotal
    this.targetOffset = currentDelta
    sessionStorage.setItem(`oreof_stock_offset_${this.parcoursIdValue}`, String(currentDelta))
    this.recalculateAll()
  }

  resetTargetReference (event) {
    this.targetOffset = 0
    sessionStorage.removeItem(`oreof_stock_offset_${this.parcoursIdValue}`)
    this.recalculateAll()
  }

  /* -------------------------------------------------------------
   * Sauvegarde Asynchrone (Live Save)
   * ----------------------------------------------------------- */
  queueSave (row) {
    const ecId = row.dataset.ecId
    const statusEl = row.querySelector('[data-parcours--quick-hours-grid-target="ecStatus"]')

    if (statusEl) {
      statusEl.innerHTML = '<i class="fal fa-spinner-third fa-spin text-amber-500"></i>'
    }

    clearTimeout(this.saveTimers[ecId])
    this.saveTimers[ecId] = setTimeout(() => {
      this.performSave(row)
    }, 600)
  }

  async performSave (row) {
    const ecId = row.dataset.ecId
    const statusEl = row.querySelector('[data-parcours--quick-hours-grid-target="ecStatus"]')

    if (this.abortControllers[ecId]) {
      this.abortControllers[ecId].abort()
    }
    this.abortControllers[ecId] = new AbortController()

    const isSansHeure = row.querySelector('.js-sans-heure-checkbox')?.checked ?? false
    const specCheckbox = row.querySelector('.js-heures-specifiques-checkbox')
    const heuresSpecifiques = specCheckbox ? specCheckbox.checked : false

    const parseNum = (selector) => {
      const el = row.querySelector(selector)
      if (!el) return 0
      const v = parseFloat(el.value)
      return isNaN(v) ? 0 : Math.max(0, v)
    }

    const payload = {
      ects: parseNum('[data-field="ects"]'),
      cmPres: isSansHeure ? 0 : parseNum('[data-field="cmPres"]'),
      tdPres: isSansHeure ? 0 : parseNum('[data-field="tdPres"]'),
      tpPres: isSansHeure ? 0 : parseNum('[data-field="tpPres"]'),
      tePres: isSansHeure ? 0 : parseNum('[data-field="tePres"]'),
      cmDist: isSansHeure ? 0 : parseNum('[data-field="cmDist"]'),
      tdDist: isSansHeure ? 0 : parseNum('[data-field="tdDist"]'),
      tpDist: isSansHeure ? 0 : parseNum('[data-field="tpDist"]'),
      sansHeure: isSansHeure,
      heuresSpecifiques: heuresSpecifiques,
    }

    const url = this.saveUrlTemplateValue.replace('__EC_ID__', ecId)

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
        signal: this.abortControllers[ecId].signal,
      })

      if (!response.ok) throw new Error('Erreur lors de la sauvegarde')

      const data = await response.json()
      if (data.success && statusEl) {
        statusEl.innerHTML = '<i class="fal fa-check text-emerald-500 animate-pulse"></i>'
        setTimeout(() => {
          if (statusEl.innerHTML.includes('fa-check')) {
            statusEl.innerHTML = '<i class="fal fa-circle-check text-secondary-300 text-xs"></i>'
          }
        }, 1500)
      }
    } catch (err) {
      if (err.name !== 'AbortError') {
        if (statusEl) {
          statusEl.innerHTML = '<i class="fal fa-exclamation-triangle text-rose-500" title="Erreur de sauvegarde"></i>'
        }
      }
    }
  }

  /* -------------------------------------------------------------
   * Bascules rapides (Sans heure / Heures spécifiques)
   * ----------------------------------------------------------- */
  toggleSansHeure (event) {
    const checkbox = event.target
    const row = checkbox.closest('[data-parcours--quick-hours-grid-target="ecRow"]')
    if (!row) return

    const hourInputs = row.querySelectorAll('input[type="number"]:not([data-field="ects"])')
    hourInputs.forEach(inp => {
      inp.disabled = checkbox.checked
      if (checkbox.checked) inp.classList.add('opacity-40', 'bg-secondary-100')
      else inp.classList.remove('opacity-40', 'bg-secondary-100')
    })

    this.recalculateRow(row)
    this.recalculateAll()
    this.queueSave(row)
  }

  toggleHeuresSpecifiques (event) {
    const checkbox = event.target
    const row = checkbox.closest('[data-parcours--quick-hours-grid-target="ecRow"]')
    if (!row) return

    const hourInputs = row.querySelectorAll('input[type="number"]:not([data-field="ects"])')
    hourInputs.forEach(inp => {
      inp.disabled = !checkbox.checked
    })

    this.recalculateRow(row)
    this.recalculateAll()
    this.queueSave(row)
  }

  /* -------------------------------------------------------------
   * Navigation Clavier (Excel-like)
   * ----------------------------------------------------------- */
  onKeyDown (event) {
    const input = event.target
    if (input.tagName !== 'INPUT' || input.type !== 'number') return

    const row = input.closest('[data-parcours--quick-hours-grid-target="ecRow"]')
    if (!row) return

    const field = input.dataset.field

    if (event.key === 'ArrowDown' || event.key === 'Enter') {
      event.preventDefault()
      const nextRow = this.getNextRow(row, 1)
      if (nextRow) {
        const nextInput = nextRow.querySelector(`[data-field="${field}"]:not([disabled])`)
        if (nextInput) {
          nextInput.focus()
          nextInput.select()
        }
      }
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      const prevRow = this.getNextRow(row, -1)
      if (prevRow) {
        const prevInput = prevRow.querySelector(`[data-field="${field}"]:not([disabled])`)
        if (prevInput) {
          prevInput.focus()
          prevInput.select()
        }
      }
    } else if (event.key === 'ArrowRight' && input.selectionEnd === input.value.length) {
      const rowInputs = [...row.querySelectorAll('input[type="number"]:not([disabled])')]
      const idx = rowInputs.indexOf(input)
      if (idx !== -1 && idx < rowInputs.length - 1) {
        event.preventDefault()
        rowInputs[idx + 1].focus()
        rowInputs[idx + 1].select()
      }
    } else if (event.key === 'ArrowLeft' && input.selectionStart === 0) {
      const rowInputs = [...row.querySelectorAll('input[type="number"]:not([disabled])')]
      const idx = rowInputs.indexOf(input)
      if (idx > 0) {
        event.preventDefault()
        rowInputs[idx - 1].focus()
        rowInputs[idx - 1].select()
      }
    }
  }

  getNextRow (currentRow, direction) {
    const allRows = this.ecRowTargets
    const currentIndex = allRows.indexOf(currentRow)
    if (currentIndex === -1) return null

    const targetIndex = currentIndex + direction
    if (targetIndex >= 0 && targetIndex < allRows.length) {
      return allRows[targetIndex]
    }
    return null
  }

  /* -------------------------------------------------------------
   * Changement de version de référence
   * ----------------------------------------------------------- */
  changeReference (event) {
    const select = event.target
    const val = select.value
    const url = new URL(window.location.href)
    if (val) {
      url.searchParams.set('ref', val)
    } else {
      url.searchParams.delete('ref')
    }
    window.location.href = url.toString()
  }

  /* -------------------------------------------------------------
   * Utilitaires de formatage
   * ----------------------------------------------------------- */
  formatHours (val) {
    if (val === null || val === undefined || isNaN(val)) return '0'
    const rounded = Math.round(val * 10) / 10
    return rounded % 1 === 0 ? rounded.toString() : rounded.toFixed(1)
  }

  renderDeltaBadge (element, delta, label = '') {
    const rounded = Math.round(delta * 10) / 10
    const prefix = label ? `${label}: ` : ''

    if (rounded === 0) {
      element.innerHTML = `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-secondary-100 text-secondary-600">${prefix}0h</span>`
    } else if (rounded > 0) {
      element.innerHTML = `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">${prefix}+${this.formatHours(rounded)}h</span>`
    } else {
      element.innerHTML = `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800">${prefix}${this.formatHours(rounded)}h</span>`
    }
  }
}
