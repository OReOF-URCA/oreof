import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['input', 'counter', 'current', 'limitBadge']

  static values = {
    max: Number
  }

  connect () {
    this.boundKeydown = this.onKeydown.bind(this)
    this.boundInput = this.onInput.bind(this)
    this.boundPaste = this.onPaste.bind(this)
    this.boundTrixChange = this.onTrixChange.bind(this)
    this.boundTrixBeforePaste = this.onTrixBeforePaste.bind(this)

    const el = this.inputElement
    if (!el) return

    if (this.isTrix) {
      this.setupTrix(el)
    } else {
      this.setupTextarea(el)
    }

    this.updateDisplay()
  }

  disconnect () {
    const el = this.inputElement
    if (!el) return

    if (this.isTrix) {
      el.removeEventListener('keydown', this.boundKeydown)
      el.removeEventListener('trix-change', this.boundTrixChange)
      el.removeEventListener('trix-before-paste', this.boundTrixBeforePaste)
    } else {
      el.removeEventListener('keydown', this.boundKeydown)
      el.removeEventListener('input', this.boundInput)
      el.removeEventListener('paste', this.boundPaste)
    }
  }

  get inputElement () {
    if (this.hasInputTarget) {
      return this.inputTarget
    }
    return this.element.querySelector('textarea, trix-editor')
  }

  get isTrix () {
    return this.inputElement?.tagName?.toLowerCase() === 'trix-editor'
  }

  setupTextarea (textarea) {
    if (this.hasMaxValue && this.maxValue > 0) {
      textarea.setAttribute('maxlength', this.maxValue)
    }
    textarea.addEventListener('keydown', this.boundKeydown)
    textarea.addEventListener('input', this.boundInput)
    textarea.addEventListener('paste', this.boundPaste)
  }

  setupTrix (trix) {
    if (trix.editor) {
      this.initTrixEvents(trix)
    } else {
      trix.addEventListener('trix-initialize', () => {
        this.initTrixEvents(trix)
      }, { once: true })
    }
  }

  initTrixEvents (trix) {
    trix.addEventListener('keydown', this.boundKeydown)
    trix.addEventListener('trix-change', this.boundTrixChange)
    trix.addEventListener('trix-before-paste', this.boundTrixBeforePaste)
    this.updateDisplay()
  }

  get currentLength () {
    const el = this.inputElement
    if (!el) return 0

    if (this.isTrix) {
      // Mesure la taille du HTML réel exporté/sauvegardé (html compris pour contrainte export XML)
      const inputId = el.getAttribute('input')
      const hiddenInput = inputId ? document.getElementById(inputId) : this.element.querySelector('input[type="hidden"]')
      if (hiddenInput && typeof hiddenInput.value === 'string') {
        return hiddenInput.value.length
      }
      return el.value ? el.value.length : 0
    }

    return el.value ? el.value.length : 0
  }

  onKeydown (event) {
    if (!this.hasMaxValue || this.maxValue <= 0) return

    // Permettre les raccourcis avec touches modificatrices (Ctrl+A, Ctrl+C, Ctrl+Z, etc.)
    if (event.ctrlKey || event.metaKey || event.altKey) {
      return
    }

    // Permettre les touches de navigation et d'effacement
    const allowedKeys = [
      'Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown',
      'Home', 'End', 'PageUp', 'PageDown', 'Tab', 'Escape'
    ]
    if (allowedKeys.includes(event.key)) {
      return
    }

    if (this.isTrix) {
      const editor = this.inputElement?.editor
      if (!editor) return
      const [start, end] = editor.getSelectedRange() || [0, 0]
      const hasSelection = end > start
      const current = this.currentLength

      if (!hasSelection && current >= this.maxValue) {
        event.preventDefault()
      }
    } else {
      const el = this.inputElement
      const selectionLength = Math.max(0, el.selectionEnd - el.selectionStart)
      const current = el.value.length

      if (current - selectionLength >= this.maxValue) {
        event.preventDefault()
      }
    }
  }

  onInput () {
    if (this.hasMaxValue && this.maxValue > 0) {
      const el = this.inputElement
      if (el && el.value.length > this.maxValue) {
        el.value = el.value.slice(0, this.maxValue)
      }
    }
    this.updateDisplay()
  }

  onPaste (event) {
    if (!this.hasMaxValue || this.maxValue <= 0) return

    const el = this.inputElement
    if (!el) return

    const pasteText = (event.clipboardData || window.clipboardData)?.getData('text') || ''
    const selectionLength = Math.max(0, el.selectionEnd - el.selectionStart)
    const available = this.maxValue - (el.value.length - selectionLength)

    if (available <= 0) {
      event.preventDefault()
      return
    }

    if (pasteText.length > available) {
      event.preventDefault()
      const start = el.selectionStart
      const end = el.selectionEnd
      const truncated = pasteText.slice(0, available)
      el.value = el.value.slice(0, start) + truncated + el.value.slice(end)
      el.setSelectionRange(start + truncated.length, start + truncated.length)
      el.dispatchEvent(new Event('input', { bubbles: true }))
    }
  }

  onTrixChange () {
    if (this.hasMaxValue && this.maxValue > 0) {
      const editor = this.inputElement?.editor
      if (editor) {
        const current = this.currentLength
        if (current > this.maxValue) {
          if (typeof editor.undo === 'function') {
            editor.undo()
          }
        }
      }
    }
    this.updateDisplay()
  }

  onTrixBeforePaste (event) {
    if (!this.hasMaxValue || this.maxValue <= 0) return

    const editor = this.inputElement?.editor
    if (!editor) return

    const current = this.currentLength
    const available = this.maxValue - current

    if (available <= 0) {
      event.preventDefault()
      return
    }

    const pasteString = event.paste?.string || ''
    if (pasteString.length > available) {
      event.preventDefault()
      const truncated = pasteString.slice(0, available)
      editor.insertString(truncated)
    }
  }

  updateDisplay () {
    if (!this.hasMaxValue || this.maxValue <= 0) return

    const current = this.currentLength
    const max = this.maxValue
    const isLimitReached = current >= max
    const isWarning = current >= max * 0.9 && !isLimitReached

    if (this.hasCurrentTarget) {
      this.currentTarget.textContent = current.toString()
    }

    if (this.hasLimitBadgeTarget) {
      if (isLimitReached) {
        this.limitBadgeTarget.classList.remove('hidden')
      } else {
        this.limitBadgeTarget.classList.add('hidden')
      }
    }

    if (this.hasCounterTarget) {
      if (isLimitReached) {
        this.counterTarget.classList.remove('text-secondary-500', 'dark:text-secondary-400', 'text-warning-600', 'dark:text-warning-400')
        this.counterTarget.classList.add('text-danger-600', 'dark:text-danger-400', 'font-bold')
      } else if (isWarning) {
        this.counterTarget.classList.remove('text-secondary-500', 'dark:text-secondary-400', 'text-danger-600', 'dark:text-danger-400', 'font-bold')
        this.counterTarget.classList.add('text-warning-600', 'dark:text-warning-400', 'font-semibold')
      } else {
        this.counterTarget.classList.remove('text-danger-600', 'dark:text-danger-400', 'text-warning-600', 'dark:text-warning-400', 'font-bold', 'font-semibold')
        this.counterTarget.classList.add('text-secondary-500', 'dark:text-secondary-400')
      }
    }
  }
}

