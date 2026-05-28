import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        // 1. Interception automatique des Turbo Frames (Attribut "busy")
        this.observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'busy') {
                    const frame = mutation.target;
                    if (frame.tagName === 'TURBO-FRAME') {
                        if (frame.hasAttribute('busy')) {
                            this.addSpinner(frame);
                        } else {
                            this.removeSpinner(frame);
                        }
                    }
                }
            });
        });

        this.observer.observe(this.element, {
            attributes: true,
            subtree: true,
            attributeFilter: ['busy']
        });

        // Liaison du contexte pour les écouteurs d'événements
        this.showGlobalLoader = this.showGlobalLoader.bind(this);
        this.hideGlobalLoader = this.hideGlobalLoader.bind(this);

        // 2. Interception des navigations globales (Turbo Drive) et Formulaires
        document.addEventListener('turbo:visit', this.showGlobalLoader);
        document.addEventListener('turbo:submit-start', this.showGlobalLoader);
        document.addEventListener('turbo:load', this.hideGlobalLoader);
        document.addEventListener('turbo:submit-end', this.hideGlobalLoader);

        // 3. Interception des requêtes Fetch/Ajax manuelles (via événements personnalisés)
        document.addEventListener('app:show-loader', this.showGlobalLoader);
        document.addEventListener('app:hide-loader', this.hideGlobalLoader);
    }

    disconnect() {
        if (this.observer) this.observer.disconnect();

        // Nettoyage des événements Turbo
        document.removeEventListener('turbo:visit', this.showGlobalLoader);
        document.removeEventListener('turbo:submit-start', this.showGlobalLoader);
        document.removeEventListener('turbo:load', this.hideGlobalLoader);
        document.removeEventListener('turbo:submit-end', this.hideGlobalLoader);

        // Nettoyage des événements personnalisés
        document.removeEventListener('app:show-loader', this.showGlobalLoader);
        document.removeEventListener('app:hide-loader', this.hideGlobalLoader);
    }

    showGlobalLoader() {
        this.addSpinner(document.body, true);
    }

    hideGlobalLoader() {
        this.removeSpinner(document.body, true);
    }

    addSpinner(element, isGlobal = false) {
        const template = document.getElementById('global-spinner-template');
        if (!template) return;

        // Anti-doublon : on ne remet pas de loader s'il y en a déjà un
        if (element.querySelector(':scope > .turbo-spinner-overlay')) return;

        // Détection critique : si le frame fait moins de 10px de haut (ex: modal vide),
        // on force l'affichage en plein écran pour qu'il soit bien visible.
        const isElementEmpty = element.clientHeight < 10;
        const forceGlobal = isGlobal || isElementEmpty;

        const clone = template.content.cloneNode(true);
        const overlay = clone.querySelector('.turbo-spinner-overlay');

        if (forceGlobal) {
            // Mode "Plein écran" (Modals vides, requêtes globales ou appels fetch manuels)
            overlay.classList.remove('absolute', 'w-full', 'h-full');
            overlay.classList.add('fixed', 'w-screen', 'h-screen');
            // On l'attache toujours au body pour éviter les problèmes de z-index ou de débordement
            document.body.appendChild(clone);

            // On identifie l'overlay pour pouvoir le supprimer spécifiquement plus tard
            if (!isGlobal && element.id) {
                overlay.setAttribute('data-frame-id', element.id);
            }
        } else {
            // Mode "Local" (Contenu ciblé, ex: rechargement d'un tableau spécifique)
            if (getComputedStyle(element).position === 'static') {
                element.style.position = 'relative';
            }
            element.appendChild(clone);
        }
    }

    removeSpinner(element, isGlobal = false) {
        if (isGlobal) {
            // Suppression des loaders globaux
            document.querySelectorAll('.turbo-spinner-overlay.fixed').forEach(el => el.remove());
        } else {
            // Suppression du loader local
            const localOverlay = element.querySelector(':scope > .turbo-spinner-overlay.absolute');
            if (localOverlay) localOverlay.remove();

            // Si c'était un frame vide passé en global, on le cherche par son ID et on le supprime
            if (element.id) {
                const detachedGlobalOverlay = document.querySelector(`.turbo-spinner-overlay[data-frame-id="${element.id}"]`);
                if (detachedGlobalOverlay) detachedGlobalOverlay.remove();
            }
        }
    }
}