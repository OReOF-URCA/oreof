import {Controller} from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['container', 'button', 'menu', 'chevron']
    static values = {
        threshold: {type: Number, default: 200}
    }

    connect() {
        this.handleScroll = this.onScroll.bind(this)
        this.handleClickOutside = this.onClickOutside.bind(this)
        this.handleKeyDown = this.onKeyDown.bind(this)

        window.addEventListener('scroll', this.handleScroll, {passive: true})
        document.addEventListener('click', this.handleClickOutside)
        document.addEventListener('keydown', this.handleKeyDown)

        // Vérifie la position initiale de scroll
        this.onScroll()
    }

    disconnect() {
        window.removeEventListener('scroll', this.handleScroll)
        document.removeEventListener('click', this.handleClickOutside)
        document.removeEventListener('keydown', this.handleKeyDown)
    }

    onScroll() {
        if (!this.hasContainerTarget) return

        const isScrolled = window.scrollY > this.thresholdValue

        if (isScrolled) {
            this.containerTarget.classList.remove('opacity-0', 'translate-y-4', 'pointer-events-none')
            this.containerTarget.classList.add('opacity-100', 'translate-y-0', 'pointer-events-auto')
        } else {
            // Masquer si on est en haut et que le menu n'est pas ouvert
            if (!this.isOpen()) {
                this.containerTarget.classList.remove('opacity-100', 'translate-y-0', 'pointer-events-auto')
                this.containerTarget.classList.add('opacity-0', 'translate-y-4', 'pointer-events-none')
            }
        }
    }

    toggle(event) {
        if (event) {
            event.preventDefault()
            event.stopPropagation()
        }

        if (this.isOpen()) {
            this.close()
        } else {
            this.open()
        }
    }

    open() {
        if (!this.hasMenuTarget) return

        this.menuTarget.classList.remove('hidden')
        this.buttonTarget?.setAttribute('aria-expanded', 'true')
        this.chevronTarget?.classList.add('rotate-180')
    }

    close() {
        if (!this.hasMenuTarget) return

        this.menuTarget.classList.add('hidden')
        this.buttonTarget?.setAttribute('aria-expanded', 'false')
        this.chevronTarget?.classList.remove('rotate-180')

        // Si on est en haut, réappliquer le masquage du container
        if (window.scrollY <= this.thresholdValue && this.hasContainerTarget) {
            this.containerTarget.classList.remove('opacity-100', 'translate-y-0', 'pointer-events-auto')
            this.containerTarget.classList.add('opacity-0', 'translate-y-4', 'pointer-events-none')
        }
    }

    isOpen() {
        return this.hasMenuTarget && !this.menuTarget.classList.contains('hidden')
    }

    onClickOutside(event) {
        if (!this.isOpen()) return

        if (!this.element.contains(event.target)) {
            this.close()
        }
    }

    onKeyDown(event) {
        if (event.key === 'Escape' && this.isOpen()) {
            event.preventDefault()
            this.close()
            this.buttonTarget?.focus()
        }
    }

    scrollToTop(event) {
        if (event) {
            event.preventDefault()
        }
        window.scrollTo({top: 0, behavior: 'smooth'})
    }
}
