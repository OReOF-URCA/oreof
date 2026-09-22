import { Controller } from '@hotwired/stimulus';
import cytoscape from 'cytoscape';
import dagre from 'cytoscape-dagre';

if (!cytoscape._dagreRegistered) {
    cytoscape.use(dagre);
    cytoscape._dagreRegistered = true;
}

export default class extends Controller {
    static targets = [
        'cy',
        'drawer',
        'drawerTitle',
        'drawerSubtitle',
        'drawerBadge',
        'drawerContent',
        'searchInput',
        'tabButton',
        'tabContent',
        'activeFilterBadge',
        'statsCount',
    ];

    static values = {
        data: Object,
    };

    connect() {
        this.currentLayoutDirection = 'LR';
        this.currentFilter = 'all';
        this.selectedElement = null;

        this.initCytoscape();
        this.setupEventListeners();
    }

    disconnect() {
        if (this.cy) {
            this.cy.destroy();
            this.cy = null;
        }
    }

    initCytoscape() {
        if (!this.hasCyTarget || !this.dataValue) return;

        const isDark = document.documentElement.classList.contains('dark');
        const elements = this.dataValue.cytoscapeElements || { nodes: [], edges: [] };

        const nodeLabelColor = isDark ? '#f8fafc' : '#0f172a';
        const nodeBg = isDark ? '#1e293b' : '#ffffff';
        const edgeColorDefault = isDark ? '#64748b' : '#94a3b8';
        const edgeLabelBg = isDark ? '#0f172a' : '#f8fafc';
        const edgeLabelColor = isDark ? '#cbd5e1' : '#475569';

        this.cy = cytoscape({
            container: this.cyTarget,
            elements: elements,
            boxSelectionEnabled: false,
            autounselectify: false,
            wheelSensitivity: 0.3,
            minZoom: 0.2,
            maxZoom: 2.5,
            style: [
                {
                    selector: 'node',
                    style: {
                        'shape': 'round-rectangle',
                        'background-color': nodeBg,
                        'border-width': 3,
                        'border-color': 'data(borderColor)',
                        'border-opacity': 0.9,
                        'label': 'data(label)',
                        'color': nodeLabelColor,
                        'font-size': '12px',
                        'font-family': 'Inter, system-ui, -apple-system, sans-serif',
                        'font-weight': 600,
                        'text-valign': 'center',
                        'text-halign': 'center',
                        'text-wrap': 'wrap',
                        'text-max-width': '130px',
                        'width': '160px',
                        'height': '60px',
                        'padding': '10px',
                        'text-margin-y': 0,
                        'transition-property': 'background-color, border-color, opacity, border-width',
                        'transition-duration': '0.2s',
                    },
                },
                {
                    selector: 'node[?isInitial]',
                    style: {
                        'border-style': 'double',
                        'border-width': 5,
                    },
                },
                {
                    selector: 'node[count > 0]',
                    style: {
                        'shadow-blur': 12,
                        'shadow-color': 'data(borderColor)',
                        'shadow-opacity': 0.4,
                    },
                },
                {
                    selector: 'edge',
                    style: {
                        'width': 2.5,
                        'line-color': 'data(lineColor)',
                        'line-style': 'data(lineStyle)',
                        'target-arrow-color': 'data(lineColor)',
                        'target-arrow-shape': 'triangle',
                        'arrow-scale': 1.2,
                        'curve-style': 'bezier',
                        'label': 'data(label)',
                        'font-size': '10px',
                        'font-weight': 500,
                        'color': edgeLabelColor,
                        'text-background-color': edgeLabelBg,
                        'text-background-opacity': 0.9,
                        'text-background-padding': '3px',
                        'text-background-shape': 'roundrectangle',
                        'text-rotation': 'autorotate',
                        'opacity': 0.85,
                        'transition-property': 'width, opacity, line-color, target-arrow-color',
                        'transition-duration': '0.2s',
                    },
                },
                {
                    selector: 'node.highlighted',
                    style: {
                        'border-width': 5,
                        'shadow-blur': 20,
                        'shadow-color': 'data(borderColor)',
                        'shadow-opacity': 0.8,
                        'opacity': 1,
                    },
                },
                {
                    selector: 'edge.highlighted',
                    style: {
                        'width': 4,
                        'opacity': 1,
                        'arrow-scale': 1.5,
                        'z-index': 99,
                    },
                },
                {
                    selector: '.dimmed',
                    style: {
                        'opacity': 0.15,
                    },
                },
            ],
            layout: this.getLayoutConfig(),
        });

        // Clic sur un nœud
        this.cy.on('tap', 'node', (evt) => {
            const node = evt.target;
            this.handleNodeClick(node);
        });

        // Clic sur une arête
        this.cy.on('tap', 'edge', (evt) => {
            const edge = evt.target;
            this.handleEdgeClick(edge);
        });

        // Clic sur le fond
        this.cy.on('tap', (evt) => {
            if (evt.target === this.cy) {
                this.resetHighlights();
                this.closeDrawer();
            }
        });
    }

    getLayoutConfig() {
        return {
            name: 'dagre',
            rankDir: this.currentLayoutDirection,
            nodeSep: 60,
            rankSep: 110,
            edgeSep: 30,
            animate: true,
            animationDuration: 400,
            fit: true,
            padding: 40,
        };
    }

    setupEventListeners() {
        // Écouteur pour adapter le thème clair/sombre si togglé dynamiquement
        const observer = new MutationObserver(() => {
            if (this.cy) {
                const isDark = document.documentElement.classList.contains('dark');
                this.cy.style()
                    .selector('node')
                    .style({
                        'background-color': isDark ? '#1e293b' : '#ffffff',
                        'color': isDark ? '#f8fafc' : '#0f172a',
                    })
                    .selector('edge')
                    .style({
                        'color': isDark ? '#cbd5e1' : '#475569',
                        'text-background-color': isDark ? '#0f172a' : '#f8fafc',
                    })
                    .update();
            }
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    }

    handleNodeClick(node) {
        this.resetHighlights();
        this.selectedElement = node;

        const raw = node.data('raw');
        node.addClass('highlighted');

        // Mettre en évidence le voisinage immédiat (arêtes et nœuds connectés)
        const connectedEdges = node.connectedEdges();
        const connectedNodes = connectedEdges.connectedNodes();

        this.cy.elements().difference(node.union(connectedEdges).union(connectedNodes)).addClass('dimmed');
        connectedEdges.addClass('highlighted');

        this.displayNodeInDrawer(raw, node.data());
        this.openDrawer();
    }

    handleEdgeClick(edge) {
        this.resetHighlights();
        this.selectedElement = edge;

        const raw = edge.data('raw');
        edge.addClass('highlighted');

        const sourceNode = edge.source();
        const targetNode = edge.target();
        sourceNode.addClass('highlighted');
        targetNode.addClass('highlighted');

        this.cy.elements().difference(edge.union(sourceNode).union(targetNode)).addClass('dimmed');

        this.displayEdgeInDrawer(raw, edge.data(), sourceNode.data('label'), targetNode.data('label'));
        this.openDrawer();
    }

    resetHighlights() {
        if (!this.cy) return;
        this.cy.elements().removeClass('highlighted dimmed');
        this.selectedElement = null;
    }

    // Gestion du Drawer latéral
    displayNodeInDrawer(raw, data) {
        this.drawerTitleTarget.textContent = data.label;
        this.drawerSubtitleTarget.textContent = `Code état : ${data.name}`;

        // Badge couleur
        this.drawerBadgeTarget.className = `inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ${raw.tailwindBg} ${raw.tailwindText} border ${raw.tailwindBorder}`;
        this.drawerBadgeTarget.innerHTML = `<i class="ph ${data.icon || 'ph-circle'} mr-1.5"></i> ${raw.color.toUpperCase()}`;

        // Comptage entités en base
        const countHtml = data.count > 0
            ? `<div class="p-4 mb-4 rounded-xl bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 flex items-center justify-between">
                <div>
                    <span class="text-xs font-medium text-primary-600 dark:text-primary-400 uppercase tracking-wider block">Actuellement dans cet état</span>
                    <span class="text-2xl font-bold text-primary-900 dark:text-primary-100">${data.count} enregistrement(s)</span>
                </div>
                <span class="flex h-3 w-3 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-primary-500"></span>
                </span>
               </div>`
            : `<div class="p-3 mb-4 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-xs text-slate-500">
                0 enregistrement actuellement à cette étape.
               </div>`;

        // Flags
        const flagsHtml = `
            <div class="grid grid-cols-2 gap-2 mb-4">
                <div class="p-2.5 rounded-lg bg-surface border border-secondary-200 dark:border-secondary-700">
                    <span class="text-[11px] text-slate-500 block">Processus actif</span>
                    <span class="text-xs font-semibold ${raw.process ? 'text-emerald-600' : 'text-slate-400'}">
                        ${raw.process ? '✓ Inclus (process: true)' : '✗ Non (process: false)'}
                    </span>
                </div>
                <div class="p-2.5 rounded-lg bg-surface border border-secondary-200 dark:border-secondary-700">
                    <span class="text-[11px] text-slate-500 block">Présent Timeline</span>
                    <span class="text-xs font-semibold ${raw.isTimeline ? 'text-indigo-600' : 'text-slate-400'}">
                        ${raw.isTimeline ? '✓ Étape clé (timeline)' : '✗ Étape intermédiaire'}
                    </span>
                </div>
            </div>
        `;

        // Transitions entrantes
        const incomingHtml = raw.incomingTransitions && raw.incomingTransitions.length > 0
            ? raw.incomingTransitions.map(t => `
                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-xs flex items-center justify-between">
                    <div>
                        <span class="font-medium text-slate-900 dark:text-slate-100 block">${t.title}</span>
                        <span class="text-[11px] text-slate-500">Depuis : ${t.from.join(', ')}</span>
                    </div>
                    <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">${t.type}</span>
                </div>
            `).join('')
            : '<p class="text-xs text-slate-400 italic">Aucune transition entrante (état initial).</p>';

        // Transitions sortantes
        const outgoingHtml = raw.outgoingTransitions && raw.outgoingTransitions.length > 0
            ? raw.outgoingTransitions.map(t => `
                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-xs flex items-center justify-between">
                    <div>
                        <span class="font-medium text-slate-900 dark:text-slate-100 block">${t.title}</span>
                        <span class="text-[11px] text-slate-500">Vers : ${t.to.join(', ')}</span>
                    </div>
                    <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">${t.type}</span>
                </div>
            `).join('')
            : '<p class="text-xs text-slate-400 italic">Aucune transition sortante (état terminal).</p>';

        this.drawerContentTarget.innerHTML = `
            ${countHtml}
            ${flagsHtml}

            <div class="space-y-4">
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2 flex items-center">
                        <i class="ph ph-arrow-fat-line-down mr-1.5 text-emerald-500"></i> Transitions entrantes (${raw.incomingTransitions?.length || 0})
                    </h4>
                    <div class="space-y-2">${incomingHtml}</div>
                </div>

                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2 flex items-center">
                        <i class="ph ph-arrow-fat-line-up mr-1.5 text-indigo-500"></i> Transitions sortantes (${raw.outgoingTransitions?.length || 0})
                    </h4>
                    <div class="space-y-2">${outgoingHtml}</div>
                </div>
            </div>
        `;
    }

    displayEdgeInDrawer(raw, data, sourceLabel, targetLabel) {
        this.drawerTitleTarget.textContent = data.label;
        this.drawerSubtitleTarget.textContent = `Transition : ${data.name}`;

        this.drawerBadgeTarget.className = `inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold uppercase ${raw.badgeClass || 'bg-slate-100 text-slate-700'}`;
        this.drawerBadgeTarget.innerHTML = `<i class="ph ${raw.buttonIcon || 'ph-arrow-right'} mr-1.5"></i> ${raw.type}`;

        // Parcours Source ➔ Cible
        const pathHtml = `
            <div class="p-3.5 mb-4 rounded-xl bg-surface border border-secondary-200 dark:border-secondary-700 space-y-2">
                <div class="flex items-center text-xs">
                    <span class="w-16 text-slate-400 font-medium">Origine :</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded">${sourceLabel}</span>
                </div>
                <div class="flex items-center text-xs">
                    <span class="w-16 text-slate-400 font-medium">Destination :</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded">${targetLabel}</span>
                </div>
            </div>
        `;

        // Formulaire modal requis
        const formHtml = raw.formShortName
            ? `<div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 mb-3">
                <span class="text-[11px] font-semibold text-amber-700 dark:text-amber-300 block uppercase tracking-wider mb-1">
                    <i class="ph ph-textbox mr-1"></i> Formulaire modal requis
                </span>
                <span class="text-xs font-mono font-bold text-amber-900 dark:text-amber-100">${raw.formShortName}</span>
                ${raw.formFields && raw.formFields.length > 0 ? `<span class="text-[11px] text-amber-600 dark:text-amber-400 block mt-1">Champs : ${raw.formFields.join(', ')}</span>` : ''}
               </div>`
            : `<div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-xs text-slate-500 mb-3">
                Action directe sans formulaire modal requis.
               </div>`;

        // Destinataires d'emails
        const recipientsHtml = raw.recipients && raw.recipients.length > 0
            ? `<div class="p-3 rounded-lg bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 mb-3">
                <span class="text-[11px] font-semibold text-sky-700 dark:text-sky-300 block uppercase tracking-wider mb-1.5">
                    <i class="ph ph-envelope-simple mr-1"></i> Destinataires notifiés par email
                </span>
                <div class="flex flex-wrap gap-1.5">
                    ${raw.recipients.map(r => `<span class="px-2 py-0.5 rounded bg-sky-200 dark:bg-sky-800 text-sky-900 dark:text-sky-100 text-xs font-bold">${r}</span>`).join('')}
                </div>
               </div>`
            : '';

        // Bouton & UI config
        const buttonConfigHtml = `
            <div class="p-3 rounded-lg bg-surface border border-secondary-200 dark:border-secondary-700 mb-3 space-y-1.5 text-xs">
                <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Configuration Bouton UI</div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Classe CSS :</span>
                    <span class="font-mono text-slate-700 dark:text-slate-300 font-semibold">${raw.buttonClass || 'btn-primary'}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Icône :</span>
                    <span class="font-mono text-slate-700 dark:text-slate-300 font-semibold">${raw.buttonIcon || 'ph-play'}</span>
                </div>
                ${raw.validationStep ? `<div class="flex items-center justify-between"><span class="text-slate-500">Validation Step :</span><span class="font-mono text-indigo-600 font-bold">${raw.validationStep}</span></div>` : ''}
            </div>
        `;

        this.drawerContentTarget.innerHTML = `
            ${pathHtml}
            ${formHtml}
            ${recipientsHtml}
            ${buttonConfigHtml}
        `;
    }

    openDrawer() {
        if (this.hasDrawerTarget) {
            this.drawerTarget.classList.remove('translate-x-full');
            this.drawerTarget.classList.add('translate-x-0');
        }
    }

    closeDrawer() {
        if (this.hasDrawerTarget) {
            this.drawerTarget.classList.add('translate-x-full');
            this.drawerTarget.classList.remove('translate-x-0');
        }
    }

    // Contrôles UI du Graphe
    zoomIn() {
        if (!this.cy) return;
        this.cy.zoom({
            level: this.cy.zoom() * 1.25,
            renderedPosition: { x: this.cy.width() / 2, y: this.cy.height() / 2 },
        });
    }

    zoomOut() {
        if (!this.cy) return;
        this.cy.zoom({
            level: this.cy.zoom() * 0.8,
            renderedPosition: { x: this.cy.width() / 2, y: this.cy.height() / 2 },
        });
    }

    fit() {
        if (!this.cy) return;
        this.cy.fit(undefined, 40);
    }

    toggleOrientation() {
        this.currentLayoutDirection = this.currentLayoutDirection === 'LR' ? 'TB' : 'LR';
        if (!this.cy) return;
        const layout = this.cy.layout(this.getLayoutConfig());
        layout.run();
    }

    // Filtrage dynamique des flux
    filterFlux(event) {
        const type = event.currentTarget.dataset.filterType;
        this.currentFilter = type;

        if (this.hasActiveFilterBadgeTarget) {
            this.activeFilterBadgeTarget.textContent = event.currentTarget.dataset.filterLabel || type;
        }

        if (!this.cy) return;

        if (type === 'all') {
            this.cy.elements().removeClass('dimmed').show();
        } else if (type === 'nominal') {
            // Uniquement les transitions 'valider' et 'action'
            this.cy.edges().forEach(edge => {
                const eType = edge.data('type');
                if (eType === 'valider' || eType === 'action') {
                    edge.show();
                } else {
                    edge.hide();
                }
            });
        } else if (type === 'exception') {
            // Réserves et refus
            this.cy.edges().forEach(edge => {
                const eType = edge.data('type');
                if (eType === 'reserver' || eType === 'refuser') {
                    edge.show();
                } else {
                    edge.hide();
                }
            });
        } else if (type === 'reopen') {
            // Réouvertures
            this.cy.edges().forEach(edge => {
                const eType = edge.data('type');
                if (eType === 'reouvrir') {
                    edge.show();
                } else {
                    edge.hide();
                }
            });
        }

        this.fit();
    }

    // Recherche d'un état
    searchNode(event) {
        const query = event.target.value.toLowerCase().trim();
        if (!this.cy) return;

        if (!query) {
            this.resetHighlights();
            return;
        }

        const match = this.cy.nodes().filter(node => {
            const label = (node.data('label') || '').toLowerCase();
            const name = (node.data('name') || '').toLowerCase();
            return label.includes(query) || name.includes(query);
        });

        if (match.length > 0) {
            this.cy.elements().addClass('dimmed');
            match.removeClass('dimmed').addClass('highlighted');
            this.cy.animate({
                center: { eles: match.first() },
                zoom: 1.2,
            }, { duration: 300 });

            this.handleNodeClick(match.first());
        }
    }

    // Gestion des Onglets (Carte, Matrice, Pipeline)
    switchTab(event) {
        const selectedTab = event.currentTarget.dataset.tab;

        this.tabButtonTargets.forEach(btn => {
            if (btn.dataset.tab === selectedTab) {
                btn.classList.add('border-primary', 'text-primary', 'font-bold');
                btn.classList.remove('border-transparent', 'text-secondary-500', 'font-normal');
            } else {
                btn.classList.remove('border-primary', 'text-primary', 'font-bold');
                btn.classList.add('border-transparent', 'text-secondary-500', 'font-normal');
            }
        });

        this.tabContentTargets.forEach(content => {
            if (content.dataset.tabContent === selectedTab) {
                content.classList.remove('hidden');
            } else {
                content.classList.add('hidden');
            }
        });

        if (selectedTab === 'graph' && this.cy) {
            setTimeout(() => {
                this.cy.resize();
                this.fit();
            }, 50);
        }
    }
}
