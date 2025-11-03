/**
 * KPI Cards Tooltips
 * 
 * Aggiunge tooltips informativi alle card delle metriche in Overview
 */
(function() {
    'use strict';

    // Dizionario descrizioni metriche
    const metricDescriptions = {
        // Google Analytics 4
        'users': {
            title: 'Utenti',
            description: 'Numero totale di utenti unici che hanno visitato il sito nel periodo. Un utente viene contato una sola volta anche se visita più volte.',
            formula: 'Utenti unici identificati da User ID o Client ID',
            goodValue: 'In crescita rispetto al periodo precedente',
            category: 'GA4'
        },
        'sessions': {
            title: 'Sessioni',
            description: 'Numero totale di sessioni avviate sul sito. Una sessione è un gruppo di interazioni che avviene in un determinato arco temporale.',
            formula: 'Conta delle sessioni uniche iniziate',
            goodValue: 'Maggiore di 1.5 sessioni per utente indica engagement alto',
            category: 'GA4'
        },
        'pageviews': {
            title: 'Visualizzazioni Pagina',
            description: 'Numero totale di pagine visualizzate. Include visualizzazioni ripetute della stessa pagina dallo stesso utente.',
            formula: 'Totale page_view events',
            goodValue: '3-5 pagine per sessione indica buona esplorazione',
            category: 'GA4'
        },
        'events': {
            title: 'Eventi',
            description: 'Totale degli eventi tracciati (clic, scroll, video views, form submit, etc). Ogni interazione significativa viene registrata come evento.',
            formula: 'Somma di tutti gli eventi custom e automatici',
            goodValue: 'Alto numero indica engagement elevato',
            category: 'GA4'
        },
        'new_users': {
            title: 'Nuovi Utenti',
            description: 'Utenti che hanno visitato il sito per la prima volta nel periodo selezionato.',
            formula: 'Utenti con first_visit event nel periodo',
            goodValue: '30-50% del totale utenti indica buona acquisizione',
            category: 'GA4'
        },
        'total_users': {
            title: 'Utenti Totali',
            description: 'Numero totale di utenti attivi nel periodo, includendo sia nuovi che di ritorno.',
            formula: 'Utenti unici totali (nuovi + ritorno)',
            goodValue: 'Crescita costante mese su mese',
            category: 'GA4'
        },
        
        // Google Search Console
        'gsc_clicks': {
            title: 'Clic da Ricerca',
            description: 'Numero di volte che gli utenti hanno cliccato sul tuo sito nei risultati di ricerca Google.',
            formula: 'Click totali da Google Search',
            goodValue: 'CTR > 3% è sopra la media',
            category: 'Search Console'
        },
        'gsc_impressions': {
            title: 'Impressioni da Ricerca',
            description: 'Quante volte il tuo sito è apparso nei risultati di ricerca Google, anche se non cliccato.',
            formula: 'Impressioni totali su Google Search',
            goodValue: 'Alto volume + alto CTR = buona visibilità',
            category: 'Search Console'
        },
        'ctr': {
            title: 'Click-Through Rate (%)',
            description: 'Percentuale di utenti che hanno cliccato sul tuo sito dopo averlo visto nei risultati di ricerca.',
            formula: '(Clic / Impressioni) × 100',
            goodValue: 'Media settore: 2-5% | Ottimo: >5%',
            category: 'Search Console'
        },
        'position': {
            title: 'Posizione Media',
            description: 'Posizione media del tuo sito nei risultati di ricerca Google per le query del periodo.',
            formula: 'Media ponderata delle posizioni per query',
            goodValue: 'Posizione 1-3: eccellente | 4-10: buono | >10: da migliorare',
            category: 'Search Console'
        },
        
        // Google Ads
        'google_clicks': {
            title: 'Clic Google Ads',
            description: 'Numero di clic sui tuoi annunci Google Ads nel periodo.',
            formula: 'Totale clic sugli annunci',
            goodValue: 'CTR >2% indica annunci rilevanti',
            category: 'Google Ads'
        },
        'google_impressions': {
            title: 'Impressioni Google Ads',
            description: 'Quante volte i tuoi annunci Google sono stati mostrati agli utenti.',
            formula: 'Totale impressioni annunci',
            goodValue: 'Alto volume con CTR buono',
            category: 'Google Ads'
        },
        'google_cost': {
            title: 'Costo Google Ads',
            description: 'Spesa totale per le campagne Google Ads nel periodo.',
            formula: 'Somma dei costi di tutti i clic',
            goodValue: 'CPA (costo per acquisizione) in linea con LTV cliente',
            category: 'Google Ads'
        },
        'google_conversions': {
            title: 'Conversioni Google Ads',
            description: 'Azioni di valore completate dagli utenti dopo aver cliccato su un annuncio (acquisti, lead, etc).',
            formula: 'Totale conversioni tracciate',
            goodValue: 'Tasso conversione >3% è eccellente',
            category: 'Google Ads'
        },
        
        // Meta Ads
        'meta_clicks': {
            title: 'Clic Meta Ads',
            description: 'Numero di clic sui tuoi annunci Facebook e Instagram.',
            formula: 'Link clicks + altri clic',
            goodValue: 'CTR >1.5% è sopra la media per Meta',
            category: 'Meta Ads'
        },
        'meta_impressions': {
            title: 'Impressioni Meta Ads',
            description: 'Quante volte i tuoi annunci sono stati visualizzati su Facebook e Instagram.',
            formula: 'Totale impressioni (può includere duplicati)',
            goodValue: 'Frequency <3 evita annunci ripetitivi',
            category: 'Meta Ads'
        },
        'meta_cost': {
            title: 'Costo Meta Ads',
            description: 'Spesa totale per le campagne pubblicitarie su Facebook e Instagram.',
            formula: 'Somma spesa campagne Meta',
            goodValue: 'CPM < €10 e CPC < €1 sono buoni valori',
            category: 'Meta Ads'
        },
        'meta_conversions': {
            title: 'Conversioni Meta Ads',
            description: 'Azioni di valore completate da utenti provenienti dagli annunci Meta.',
            formula: 'Totale conversioni tracciate dal pixel Meta',
            goodValue: 'ROAS (Return on Ad Spend) >3x è ottimo',
            category: 'Meta Ads'
        },
        'meta_revenue': {
            title: 'Fatturato Meta Ads',
            description: 'Ricavi generati da utenti provenienti dalle campagne Meta (ecommerce).',
            formula: 'Somma valore conversioni',
            goodValue: 'ROAS >2x significa profittabilità',
            category: 'Meta Ads'
        },
        
        // Revenue Generale
        'revenue': {
            title: 'Fatturato Totale',
            description: 'Ricavi totali generati da tutte le sorgenti di traffico nel periodo.',
            formula: 'Somma di tutte le transazioni completate',
            goodValue: 'Crescita month-over-month costante',
            category: 'Revenue'
        },
        
        // Generiche
        'clicks': {
            title: 'Clic Totali',
            description: 'Numero totale di clic da tutte le sorgenti (organico + ads).',
            formula: 'Somma clic da tutte le piattaforme',
            goodValue: 'Dipende dalla strategia di acquisizione',
            category: 'Generale'
        },
        'impressions': {
            title: 'Impressioni Totali',
            description: 'Numero totale di impressioni da tutte le sorgenti.',
            formula: 'Somma impressioni da tutte le piattaforme',
            goodValue: 'Combinato con CTR alto indica buona visibilità',
            category: 'Generale'
        },
        'cost': {
            title: 'Costo Totale Advertising',
            description: 'Spesa pubblicitaria totale su tutte le piattaforme.',
            formula: 'Somma costi Google Ads + Meta Ads + altre',
            goodValue: 'ROI >2x significa che guadagni €2 per ogni €1 speso',
            category: 'Generale'
        },
        'conversions': {
            title: 'Conversioni Totali',
            description: 'Numero totale di conversioni da tutte le sorgenti.',
            formula: 'Somma conversioni da tutte le piattaforme',
            goodValue: 'Tasso conversione >2% è buono per la maggior parte dei settori',
            category: 'Generale'
        }
    };

    /**
     * Initialize KPI tooltips
     */
    function initKPITooltips() {
        // Wait for KPI cards to be rendered
        const kpiCards = document.querySelectorAll('.fpdms-kpi-card');
        
        if (kpiCards.length === 0) {
            // Retry after delay if cards aren't loaded yet
            setTimeout(initKPITooltips, 500);
            return;
        }

        kpiCards.forEach(card => {
            const metric = card.getAttribute('data-metric');
            
            if (!metric || !metricDescriptions[metric]) {
                return;
            }

            addTooltipToCard(card, metric);
        });
    }

    /**
     * Add tooltip to a KPI card
     */
    function addTooltipToCard(card, metric) {
        const info = metricDescriptions[metric];
        
        // Add info icon to label
        const label = card.querySelector('.fpdms-kpi-label');
        if (!label) return;

        const icon = document.createElement('span');
        icon.className = 'fpdms-kpi-info-icon dashicons dashicons-info-outline';
        icon.setAttribute('data-tooltip-metric', metric);
        label.appendChild(icon);

        // Create tooltip element
        const tooltip = createTooltipElement(info);
        card.appendChild(tooltip);

        // Show/hide on hover
        icon.addEventListener('mouseenter', () => {
            tooltip.classList.add('is-visible');
        });

        icon.addEventListener('mouseleave', () => {
            tooltip.classList.remove('is-visible');
        });

        // Also show/hide on click for mobile
        icon.addEventListener('click', (e) => {
            e.stopPropagation();
            tooltip.classList.toggle('is-visible');
        });

        // Hide when clicking outside
        document.addEventListener('click', (e) => {
            if (!card.contains(e.target)) {
                tooltip.classList.remove('is-visible');
            }
        });
    }

    /**
     * Create tooltip DOM element
     */
    function createTooltipElement(info) {
        const tooltip = document.createElement('div');
        tooltip.className = 'fpdms-kpi-tooltip';
        
        tooltip.innerHTML = `
            <div class="fpdms-kpi-tooltip-header">
                <strong>${info.title}</strong>
                <span class="fpdms-kpi-tooltip-category">${info.category}</span>
            </div>
            <div class="fpdms-kpi-tooltip-body">
                <p>${info.description}</p>
                ${info.formula ? `<div class="fpdms-kpi-tooltip-formula"><strong>Formula:</strong> ${info.formula}</div>` : ''}
                ${info.goodValue ? `<div class="fpdms-kpi-tooltip-tip">💡 <strong>Valore ottimale:</strong> ${info.goodValue}</div>` : ''}
            </div>
        `;

        return tooltip;
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initKPITooltips);
    } else {
        initKPITooltips();
    }

    // Re-initialize when Overview data refreshes
    document.addEventListener('fpdms-overview-updated', initKPITooltips);

    // Add CSS
    const style = document.createElement('style');
    style.textContent = `
        .fpdms-kpi-info-icon {
            margin-left: 6px;
            font-size: 14px !important;
            width: 14px !important;
            height: 14px !important;
            color: #9ca3af !important;
            cursor: help;
            transition: color 0.2s, transform 0.2s;
            vertical-align: middle;
        }

        .fpdms-kpi-info-icon:hover {
            color: #667eea !important;
            transform: scale(1.1);
        }

        .fpdms-kpi-card {
            position: relative;
        }

        .fpdms-kpi-tooltip {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 8px;
            background: #1f2937;
            color: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
            min-width: 300px;
            max-width: 100%;
        }

        .fpdms-kpi-tooltip.is-visible {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
            pointer-events: auto;
        }

        .fpdms-kpi-tooltip-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .fpdms-kpi-tooltip-header strong {
            font-size: 15px;
            font-weight: 600;
        }

        .fpdms-kpi-tooltip-category {
            font-size: 11px;
            padding: 3px 8px;
            background: rgba(102, 126, 234, 0.3);
            border-radius: 12px;
            font-weight: 600;
        }

        .fpdms-kpi-tooltip-body {
            font-size: 13px;
            line-height: 1.6;
        }

        .fpdms-kpi-tooltip-body p {
            margin: 0 0 12px 0;
            color: #e5e7eb;
        }

        .fpdms-kpi-tooltip-formula {
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
            margin-bottom: 8px;
            font-size: 12px;
        }

        .fpdms-kpi-tooltip-formula strong {
            color: #93c5fd;
        }

        .fpdms-kpi-tooltip-tip {
            padding: 8px 12px;
            background: rgba(16, 185, 129, 0.1);
            border-left: 3px solid #10b981;
            border-radius: 6px;
            font-size: 12px;
        }

        .fpdms-kpi-tooltip-tip strong {
            color: #6ee7b7;
        }

        /* Arrow */
        .fpdms-kpi-tooltip::before {
            content: "";
            position: absolute;
            bottom: 100%;
            left: 24px;
            border: 8px solid transparent;
            border-bottom-color: #1f2937;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .fpdms-kpi-tooltip {
                min-width: 280px;
                font-size: 12px;
                padding: 12px;
            }

            .fpdms-kpi-tooltip-header strong {
                font-size: 14px;
            }
        }
    `;
    document.head.appendChild(style);

})();

