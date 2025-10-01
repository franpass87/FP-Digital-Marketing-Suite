# Proposte di miglioria

Di seguito dieci ulteriori interventi suggeriti per aumentare stabilità, sicurezza e osservabilità del plugin.

1. **Queue di invio email con retry esponenziale**  
   Sostituire l'invio sincrono dei report con una coda persistente che supporti backoff esponenziale e dead-letter queue per evitare perdite di email quando l'SMTP restituisce errori temporanei.

2. **Metriche Prometheus per i job pianificati**  
   Esporre contatori e tempi di esecuzione dei cron tramite endpoint dedicato, così da integrare facilmente il monitoraggio con Prometheus/Grafana e individuare ritardi negli invii dei report.

3. **Policy CSP per l'area admin**  
   Applicare una Content Security Policy restrittiva sulle pagine React/JS del plugin, limitando le origini script/style a WordPress e al plugin per mitigare attacchi XSS.

4. **Rotazione automatica delle chiavi di cifratura**  
   Introdurre un meccanismo di key rotation per `Security::encrypt()`/`decrypt()` con versionamento delle chiavi, così da poter rigenerare i segreti senza perdere le credenziali salvate.

5. **Test end-to-end dei connettori principali**  
   Aggiungere suite Cypress/Playwright che simulano l'onboarding dei data source principali (Google Ads, Meta, CSV) per prevenire regressioni nei flow critici di importazione dati.

6. **Validazione schemi JSON per i payload REST**  
   Definire schemi (es. con `justinrainbow/json-schema`) per validare i payload REST in ingresso, garantendo che le richieste anomale vengano rifiutate con errori coerenti.

7. **Gestione centralizzata delle notifiche admin**  
   Creare un servizio di `AdminNotices` che deduplica e persiste le notifiche tra redirect, evitando code duplicate e assicurando che gli avvisi critici vengano mostrati dopo i POST.

8. **Cache layer per i report PDF ricorrenti**  
   Conservare gli artefatti generati per preset ripetuti (es. ultimo mese) e rigenerarli solo quando i dati sottostanti cambiano, riducendo il carico su generatori PDF e database.

9. **Analisi statica PHP (PHPStan/Psalm) in CI**  
   Integrare uno step di analisi statica nel workflow CI per intercettare tip mismatch e accessi a proprietà non definite prima del deploy.

10. **Audit trail per modifiche alle impostazioni**  
   Registrare in tabella dedicata chi e quando modifica credenziali e parametri sensibili, così da supportare requisiti di compliance e facilitare il debugging.
