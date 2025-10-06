#!/bin/bash

# FP Digital Marketing Suite - Cron Runner
# Esegue lo scheduler con gestione lock per evitare sovrapposizioni

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
APP_DIR="$(dirname "$SCRIPT_DIR")"
LOG_DIR="$APP_DIR/storage/logs"
LOCK_FILE="/tmp/fpdms-scheduler.lock"

# Assicura che log directory esista
mkdir -p "$LOG_DIR"

# Funzione di cleanup
cleanup() {
    rm -f "$LOCK_FILE"
}
trap cleanup EXIT

# Check se già in esecuzione
if [ -f "$LOCK_FILE" ]; then
    PID=$(cat "$LOCK_FILE")
    
    # Verifica se il processo è davvero attivo
    if ps -p "$PID" > /dev/null 2>&1; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') [INFO] Scheduler già in esecuzione (PID: $PID), skip" >> "$LOG_DIR/scheduler.log"
        exit 0
    else
        # Lock file vecchio, rimuovi
        rm -f "$LOCK_FILE"
    fi
fi

# Crea lock file con PID corrente
echo $$ > "$LOCK_FILE"

# Timestamp inizio
echo "$(date '+%Y-%m-%d %H:%M:%S') [INFO] Starting scheduler run" >> "$LOG_DIR/scheduler.log"

# Esegui scheduler
cd "$APP_DIR"
php cli.php schedule:run >> "$LOG_DIR/scheduler.log" 2>&1
EXIT_CODE=$?

# Timestamp fine
echo "$(date '+%Y-%m-%d %H:%M:%S') [INFO] Scheduler run completed (exit code: $EXIT_CODE)" >> "$LOG_DIR/scheduler.log"

# Cleanup automatico tramite trap
exit $EXIT_CODE
