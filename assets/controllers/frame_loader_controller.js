import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.showGlobal = this.showGlobal.bind(this);
        this.showFrame = this.showFrame.bind(this);
        this.forceClearAll = this.forceClearAll.bind(this);

        // --- 1. DÉCLENCHEURS D'APPARITION ---
        document.addEventListener('turbo:visit', this.showGlobal);
        document.addEventListener('turbo:submit-start', this.showGlobal);
        document.addEventListener('turbo:before-fetch-request', this.showFrame);
        document.addEventListener('app:show-loader', this.showGlobal);

        // --- 2. DÉCLENCHEURS DE SUPPRESSION (La méthode "Terre brûlée") ---
        // On ne cherche plus à savoir "qui" a fini. Si un de ces événements survient, on nettoie tout.
        this.endEvents = [
            'turbo:load',               // Fin d'une navigation globale
            'turbo:render',             // Fin d'un rendu global
            'turbo:frame-render',       // Fin du rendu d'un frame
            'turbo:frame-load',         // Fin du chargement d'un frame complet
            'turbo:submit-end',         // Fin d'une soumission de formulaire (même en erreur 422)
            'turbo:before-cache',       // CRITIQUE : Nettoie l'écran juste avant que Turbo ne le mette en cache
            'turbo:frame-missing',      // Erreur : le frame cible n'existe pas dans la réponse
            'turbo:fetch-request-error',// Erreur réseau / 500
            'turbo:fetch-offline-error',// Coupure internet
            'app:hide-loader'           // Fin de ton fetch manuel
        ];

        this.endEvents.forEach(event => document.addEventListener(event, this.forceClearAll));
    }

    disconnect() {
        document.removeEventListener('turbo:visit', this.showGlobal);
        document.removeEventListener('turbo:submit-start', this.showGlobal);
        document.removeEventListener('turbo:before-fetch-request', this.showFrame);
        document.removeEventListener('app:show-loader', this.showGlobal);

        this.endEvents.forEach(event => document.removeEventListener(event, this.forceClearAll));
    }

    showGlobal() {
        this.addSpinner(document.body, true);
    }

    showFrame(event) {
        const element = event.target;
        if (element && element.tagName === 'TURBO-FRAME') {
            this.addSpinner(element, false);
        }
    }

    // Fonction unique et impitoyable de nettoyage
    forceClearAll() {
        document.querySelectorAll('.turbo-spinner-overlay').forEach(el => el.remove());
    }

    addSpinner(element, isGlobal = false) {
        const template = document.getElementById('global-spinner-template');
        if (!template) return;

        // Anti-doublon strict
        if (isGlobal && document.querySelector('body > .turbo-spinner-overlay.fixed')) return;
        if (!isGlobal && element.querySelector(':scope > .turbo-spinner-overlay')) return;

        // Si le composant est vide (hauteur < 10px), on force le mode global pour qu'il soit visible
        const isElementEmpty = !isGlobal && element.clientHeight < 10;
        const forceGlobal = isGlobal || isElementEmpty;

        const clone = template.content.cloneNode(true);
        const overlay = clone.querySelector('.turbo-spinner-overlay');

        if (forceGlobal) {
            overlay.classList.remove('absolute', 'w-full', 'h-full');
            overlay.classList.add('fixed', 'w-screen', 'h-screen');
            document.body.appendChild(clone);
        } else {
            if (getComputedStyle(element).position === 'static') {
                element.style.position = 'relative';
            }
            element.appendChild(clone);
        }
    }
}