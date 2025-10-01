# Fix Changelog

| ID | File | Line | Severity | Fix summary | Commit |
| --- | --- | --- | --- | --- | --- |
| ISSUE-001 | src/Http/Routes.php | 42 | High | Allow GET fallback on tick REST route to match documented cron URL | fix(functional): allow GET fallback on tick route (ISSUE-001) |
| ISSUE-003 | Multiple (Assembler + CSV connectors) | 34 | High | Drop out-of-range CSV rows before aggregation to keep period totals accurate | fix(functional): filter CSV metrics by period (ISSUE-003) |
| ISSUE-002 | src/Support/Security.php | 24 | Medium | Add AES-256-GCM fallback and explicit failure when no encryption backend is present | fix(security): harden encryption fallback (ISSUE-002) |
