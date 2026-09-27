import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['submitButton', 'spinner', 'simulationBox'];

    changeSource(event) {
        const select = event.target;
        const sourceId = select.value;
        if (sourceId) {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('source_id', sourceId);
            window.location.href = currentUrl.toString();
        }
    }

    startExecution(event) {
        if (!confirm("Êtes-vous sûr de vouloir lancer la duplication de la campagne ? Cette action va créer une nouvelle campagne et copier l'ensemble de l'offre de formation.")) {
            event.preventDefault();
            return;
        }

        if (this.hasSpinnerTarget) {
            this.spinnerTarget.classList.remove('hidden');
        }
        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = true;
            this.submitButtonTarget.classList.add('opacity-75', 'cursor-not-allowed');
        }
    }
}
