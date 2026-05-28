import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        // Liaison du contexte pour les écouteurs d'événements
        this.showGlobal = this.showGlobal.bind(this);
        this.hideGlobal = this.hideGlobal.bind(this);
        this.showFrame = this.showFrame.bind(this);
        this.hideFrame = this.hideFrame.bind(this);

        // --- 1. Événements Globaux (Turbo Drive : changement de page, formulaires) ---
        document.addEventListener('turbo:visit', this.showGlobal);
        document.addEventListener('turbo:submit-start', this.showGlobal);
        document.addEventListener('turbo:load', this.hideGlobal);
        document.addEventListener('turbo:submit-end', this.hideGlobal);
        document.addEventListener('turbo:render', this.hideGlobal); // Sécurité supplémentaire

        // Custom events pour tes anciens scripts (fetch manuels)
        document.addEventListener('app:show-loader', this.showGlobal);
        document.addEventListener('app:hide-loader', this.hideGlobal);

        // --- 2. Événements Locaux (Turbo Frames : Modals, Tableaux, etc.) ---
        document.addEventListener('turbo:before-fetch-request', this.showFrame);
        document.addEventListener('turbo:frame-render', this.hideFrame);
        document.addEventListener('turbo:frame-load', this.hideFrame);

        // --- 3. Les filets de sécurité critiques ---
        // S'il manque un frame cible ou qu'il y a une erreur serveur (500/404), on cache tout !
        document.addEventListener('turbo:frame-missing', this.hideFrame);
        document.addEventListener('turbo:fetch-request-error', this.hideFrame);
        document.addEventListener('turbo:fetch-offline-error', this.hideGlobal);
    }

    disconnect() {
        // Nettoyage des événements
        document.removeEventListener('turbo:visit', this.showGlobal);
        document.removeEventListener('turbo:submit-start', this.showGlobal);
        document.removeEventListener('turbo:load', this.hideGlobal);
        document.removeEventListener('turbo:submit-end', this.hideGlobal);
        document.removeEventListener('turbo:render', this.hideGlobal);

        document.removeEventListener('app:show-loader', this.showGlobal);
        document.removeEventListener('app:hide-loader', this.hideGlobal);

        document.removeEventListener('turbo:before-fetch-request', this.showFrame);
        document.removeEventListener('turbo:frame-render', this.hideFrame);
        document.removeEventListener('turbo:frame-load', this.hideFrame);

        document.removeEventListener('turbo:frame-missing', this.hideFrame);
        document.removeEventListener('turbo:fetch-request-error', this.hideFrame);
        document.removeEventListener('turbo:fetch-offline-error', this.hideGlobal);
    }

    showGlobal() {
        this.addSpinner(document.body, true);
    }

    hideGlobal() {
        this.removeSpinner(document.body, true);

        // Filet de sécurité ultime : suppression en force de tout loader persistant
        document.querySelectorAll('.turbo-spinner-overlay.fixed').forEach(el => el.remove());
    }

    showFrame(event) {
        const element = event.target;
        // On s'assure que l'événement vient bien d'un Turbo Frame
        if (element && element.tagName === 'TURBO-FRAME') {
            this.addSpinner(element, false);
        }
    }

    hideFrame(event) {
        const element = event.target;
        if (element && element.tagName === 'TURBO-FRAME') {
            this.removeSpinner(element, false);
        }
    }

    addSpinner(element, isGlobal = false) {
        const template = document.getElementById('global-spinner-template');
        if (!template) return;

        // Anti-doublon global et local
        if (isGlobal && document.querySelector('body > .turbo-spinner-overlay.fixed:not([data-frame-id])')) return;
        if (!isGlobal && element.querySelector(':scope > .turbo-spinner-overlay')) return;

        const isElementEmpty = !isGlobal && element.clientHeight < 10;
        const forceGlobal = isGlobal || isElementEmpty;

        const clone = template.content.cloneNode(true);
        const overlay = clone.querySelector('.turbo-spinner-overlay');

        if (forceGlobal) {
            overlay.classList.remove('absolute', 'w-full', 'h-full');
            overlay.classList.add('fixed', 'w-screen', 'h-screen');
            document.body.appendChild(clone);

            if (!isGlobal && element.id) {
                overlay.setAttribute('data-frame-id', element.id);
            }
        } else {
            if (getComputedStyle(element).position === 'static') {
                element.style.position = 'relative';
            }
            element.appendChild(clone);
        }
    }

    removeSpinner(element, isGlobal = false) {
        if (isGlobal) {
            document.querySelectorAll('body > .turbo-spinner-overlay.fixed:not([data-frame-id])').forEach(el => el.remove());
        } else {
            // Suppression d'un loader local ciblé
            const localOverlay = element.querySelector(':scope > .turbo-spinner-overlay.absolute');
            if (localOverlay) localOverlay.remove();

            // Si le frame était vide (modale) et a généré un loader plein écran, on l'enlève via son ID
            if (element.id) {
                const detachedGlobalOverlay = document.querySelector(`body > .turbo-spinner-overlay[data-frame-id="${element.id}"]`);
                if (detachedGlobalOverlay) detachedGlobalOverlay.remove();
            }
        }
    }
}