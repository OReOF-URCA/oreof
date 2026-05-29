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

        // Permettre de spécifier l'élément cible
        document.addEventListener('app:show-loader-in', (event) => {
            const element = event.detail?.element;
            if (element) {
                this.addSpinner(element, false);
            }
        });

        // --- CAPTURE fetch() GLOBALEMENT ---
        this.interceptFetch();

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

        this.endEvents.forEach(event => {
            document.addEventListener(event, this.forceClearAll);
        });
        
        // Nettoyer au bout de 10 secondes max (sécurité anti-blocage)
        setInterval(() => {
            const spinners = document.querySelectorAll('.turbo-spinner-overlay');
            if (spinners.length > 0) {
                console.warn('⚠️ Spinner(s) encore présent(s) après 10s - forcé à disparaitre');
                this.forceClearAll();
            }
        }, 10000);
    }

    interceptFetch() {
        const originalFetch = window.fetch;

        window.fetch = (...args) => {
            // Trouver le conteneur cible intelligemment
            const container = this.findSmartContainer();

            // Afficher le spinner au bon endroit
            if (container && container !== document.body) {
                this.addSpinner(container, false);
            } else {
                document.dispatchEvent(new CustomEvent('app:show-loader'));
            }

            return originalFetch(...args)
                .then(response => {
                    // NE PAS cacher le spinner ici - laisser l'appelant décider
                    // car le traitement des données peut prendre du temps
                    return response;
                })
                .catch(error => {
                    // En cas d'ERREUR réseau, cacher le spinner immédiatement
                    document.dispatchEvent(new CustomEvent('app:hide-loader'));
                    throw error;
                });
        };
    }

    findSmartContainer() {
        // 1. Si c'est dans une modal ouverte, utiliser la modal
        const openModal = document.querySelector('[role="dialog"]:not(.hidden), .modal:not(.hidden), #imageManagerModal:not(.hidden)');
        if (openModal) {
            const modalBody = openModal.querySelector('.modal-body, [class*="modal-body"], .bg-surface');
            return modalBody || openModal;
        }

        // 2. Si c'est dans un turbo-frame, utiliser le frame
        const frame = document.querySelector('turbo-frame');
        if (frame && frame.offsetHeight > 10) {
            return frame;
        }

        // 3. Si c'est dans une div avec un ID spécifique de conteneur (uploadFormContainer, etc)
        const caller = new Error().stack;
        const formContainer = document.querySelector('[id*="Container"], [id*="container"], [id*="Wrapper"], [id*="wrapper"]');
        if (formContainer && formContainer.offsetHeight > 10) {
            return formContainer;
        }

        // 4. Sinon, affichage global
        return null;
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
            // Nettoyer les anciens spinners avant d'en ajouter un nouveau
            this.forceClearAll();

            // Ajouter le spinner dans le frame
            this.addSpinner(element, false);

            // IMPORTANT : Ajouter un listener une fois pour nettoyer quand le frame finit
            const handler = () => {
                this.forceClearAll();
                element.removeEventListener('turbo:frame-load', handler);
                element.removeEventListener('turbo:frame-render', handler);
            };

            element.addEventListener('turbo:frame-load', handler);
            element.addEventListener('turbo:frame-render', handler);
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