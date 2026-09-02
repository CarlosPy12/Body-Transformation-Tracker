# Kinetica

PWA PHP 8/MySQL per monitorare peso, composizione corporea, GLP-1, obiettivi, allenamenti, passi da Google Drive, calendario e notifiche push. Tutta la UI e i messaggi sono in italiano.

## Stack

- PHP 8.x, PDO, sessioni PHP, CSRF, `password_hash()` / `password_verify()`.
- MySQL 8.x o MariaDB compatibile come unica fonte autoritativa.
- HTML, CSS, JavaScript vanilla, Chart.js, Service Worker, Web Push.
- Cron PHP HostGator per passi e notifiche.
- Google Drive API con Service Account per Health Sync.

## Setup HostGator

1. Crea un database MySQL e un utente con permessi sul database.
2. Carica il repository sul server, con document root puntata a `public/`.
3. Esegui `composer install --no-dev --optimize-autoloader`.
4. Copia `.env.example` in `.env` fuori da `public/` e compila i valori reali.
5. Importa `database/migrations/001_initial_schema.sql` nel database, poi `database/migrations/002_event_reminders.sql` se stai aggiornando un'installazione esistente.
6. Imposta PHP 8.x e HTTPS obbligatorio.
7. Assicurati che `storage/logs` e `storage/uploads` siano scrivibili dal processo PHP.
8. Configura `FIRST_ADMIN_EMAIL`, `FIRST_ADMIN_PASSWORD`, `FIRST_ADMIN_NAME`, poi esegui `php database/seeds/create_super_admin.php`.
9. Dopo la creazione admin, rimuovi la password dal `.env` o sostituiscila.
10. Crea le chiavi VAPID e configura `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`.
11. Crea un Google Cloud Project, abilita Drive API, crea un Service Account e salva il JSON fuori da `public/`.
12. Condividi la cartella Health Sync con l'email del Service Account.
13. Inserisci `GOOGLE_DRIVE_STEPS_FOLDER_ID` e `GOOGLE_SERVICE_ACCOUNT_JSON_PATH` nel `.env`.
14. Configura cron cPanel:
    - `*/10 * * * * /usr/local/bin/php /home/utente/app/cron/sync_steps.php`
    - `0 1 * * * /usr/local/bin/php /home/utente/app/cron/sync_steps.php --date=yesterday`
    - `*/5 * * * * /usr/local/bin/php /home/utente/app/cron/send_notifications.php`
15. Apri il dominio, fai login e installa la PWA su Android dal browser.

## Valori reali da fornire

- Credenziali MySQL: host, database, utente, password.
- URL pubblico HTTPS in `APP_URL`.
- Email, nome e password temporanea del primo `super_admin`.
- Chiavi VAPID.
- Path del JSON Service Account Google.
- Folder ID della cartella Google Drive di Health Sync.
- URL della repository GitHub come remote `origin`.

## Test

Esegui:

```bash
php tests/run.php
```

I test coprono parser bilancia, decimali italiani, NULL, deduplica, parser passi, esclusione aggregati, authorization e transizione GLP-1.

## Deploy Automatico HostGator

La workflow `.github/workflows/deploy-hostgator.yml` aggiorna HostGator a ogni push su `main` entrando via SSH e facendo pull della repo clonata.

Configura questi GitHub Secrets in `Settings -> Secrets and variables -> Actions`:

```text
HOSTGATOR_SSH_HOST=hostname SSH HostGator
HOSTGATOR_SSH_PORT=22
HOSTGATOR_SSH_USER=utente cPanel/SSH
HOSTGATOR_SSH_PRIVATE_KEY=chiave privata SSH autorizzata su HostGator
HOSTGATOR_DEPLOY_PATH=/home2/b9g6c7m1/kinetica-repo
HOSTGATOR_SSH_KNOWN_HOSTS=opzionale, output di ssh-keyscan
```

La workflow esegue:

```bash
git fetch origin main
git reset --hard origin/main
composer install --no-dev --optimize-autoloader --no-interaction
```

Se Composer non e disponibile sul server, la workflow accetta `vendor/autoload.php` gia presente e continua. In quel caso aggiorna manualmente `vendor/` quando cambiano le dipendenze.

## Import CSV bilancia

Il parser cerca l'header `Data;Ora;kg;IMC;...` anche se non si trova nella prima riga. I decimali italiani vengono convertiti per MySQL, i campi vuoti restano `NULL`, e la deduplica usa `UNIQUE(user_id, measurement_hash)`.

Se il CSV esportato dalla bilancia contiene tutto lo storico, l'import resta append-only: per ogni utente vengono analizzate e importate solo le righe con data successiva all'ultima misurazione gia presente. Le date vecchie o gia consolidate vengono saltate e non modificano il database.

Le anomalie sono warning basati sullo storico recente dell'utente e sulla soglia relativa configurabile `ANOMALY_RELATIVE_THRESHOLD`.

## Share Target Android

Il manifest espone la PWA come destinazione di condivisione per CSV. I file ricevuti vengono salvati in `storage/uploads`, validati e poi aperti nel flusso di preview import dopo il login.

## Google Drive

Il cron `cron/sync_steps.php` processa solo file con nome:

```regex
^Passi \d{4}\.\d{2}\.\d{2} (Huawei Health|Health Connect)\.csv$
```

Questo esclude i riepiloghi settimanali, mensili e continui di 30 giorni. Somma la colonna `Passi` e fa UPSERT in `daily_steps`, mantenendo una sola riga per `user_id + step_date`.

Per evitare import storici imposta nel `.env`:

```env
STEPS_SYNC_START_DATE=2026-09-01
```

Se `STEPS_SYNC_START_DATE` non e configurata, il cron processa solo file dalla data corrente in avanti.

Consiglio operativo: Health Sync aggiorna il CSV al massimo ogni 10 minuti, quindi il cron passi ha senso ogni 10 minuti. Se lo fai piu spesso non ottieni dati piu freschi, ma aumenti solo le chiamate a Drive.

Per chiudere bene il giorno precedente aggiungi anche un cron notturno:

```cron
0 1 * * * /usr/local/bin/php /home2/b9g6c7m1/kinetica-repo/cron/sync_steps.php --date=yesterday
```

Questo alle 01:00 processa solo il file giornaliero di ieri, per esempio il 2 settembre rilegge `Passi 2026.09.01 ...csv`, somma i passi e aggiorna la riga `daily_steps` gia esistente.

## Notifiche Push

Genera le chiavi VAPID una sola volta:

```bash
/usr/local/bin/php /home2/b9g6c7m1/kinetica-repo/tools/generate_vapid_keys.php mailto:tua-email@example.com
```

Incolla l'output nel `.env` come `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY` e `VAPID_PRIVATE_KEY`.

Per i promemoria evento per evento importa una sola volta:

```bash
mysql -u USER -p DATABASE < database/migrations/002_event_reminders.sql
```

Poi in cPanel configura il cron notifiche ogni 5 minuti:

```cron
*/5 * * * * /usr/local/bin/php /home2/b9g6c7m1/kinetica-repo/cron/send_notifications.php
```

Ogni iniezione e ogni allenamento possono avere `Notifica` e `Ripeti`: il cron invia solo dentro la finestra scelta e deduplica gli avvisi gia mandati.

## Import storico passi da CSV

Per importare passi storici manualmente prepara un CSV con due colonne:

```csv
data,passi
2026-08-01,8542
2026-08-02,10120
31/08/2026,7800
```

Sono accettati separatori `,` o `;`, date `YYYY-MM-DD`, `DD/MM/YYYY`, `DD-MM-YYYY`, `YYYY.MM.DD`, `DD.MM.YYYY`, e passi con separatore migliaia tipo `8.542`.

Carica il file fuori da `public/`, per esempio in `storage/uploads/passi_storici.csv`, poi esegui:

```bash
php database/seeds/import_steps_csv.php --user-id=1 --file=/home2/b9g6c7m1/kinetica-repo/storage/uploads/passi_storici.csv
```

Per controllare il file senza scrivere nel database:

```bash
php database/seeds/import_steps_csv.php --user-id=1 --file=/home2/b9g6c7m1/kinetica-repo/storage/uploads/passi_storici.csv --dry-run
```

Lo script aggiorna una sola riga per giorno in `daily_steps`, quindi se rilanci lo stesso import corregge i valori esistenti invece di duplicarli.

## Import storico completo bilancia + MJ

Per importare lo storico principale usa un CSV con le colonne dell'export bilancia piu le colonne finali `Dose MJ` e `MJ Mg`. La colonna `Passi` viene ignorata da questo script.

Header atteso:

```csv
Data;Ora;kg;IMC;Massa grassa;Acqua;Muscoli;Ossa;Braccio sinistro Massa grassa;Braccio sinistro Muscoli;Braccio destro Massa grassa;Braccio destro Muscoli;Gamba sinistra Massa grassa;Gamba sinistra Muscoli;Gamba destra Massa grassa;Gamba destra Muscoli;Tronco Massa grassa;Tronco Muscoli;Età metabolica;Battito;Grasso viscerale;Passi;Dose MJ;MJ Mg
```

Regole:

- `Passi` viene saltato.
- `Dose MJ = 1` importa una iniezione completata nel giorno/ora della riga.
- `MJ Mg` diventa sia dose pianificata sia dose somministrata.
- Se `Dose MJ = 0` e `MJ Mg` e vuoto/zero, non viene creata nessuna iniezione.
- Le misurazioni sono deduplicate con lo stesso hash dell'import bilancia.
- Le iniezioni identiche per utente, farmaco, data/ora e dose non vengono duplicate.

Prova senza scrivere:

```bash
php database/seeds/import_history_csv.php --user-id=1 --file=/home2/b9g6c7m1/kinetica-repo/storage/uploads/storico_completo.csv --medication=Mounjaro --dry-run
```

Import reale:

```bash
php database/seeds/import_history_csv.php --user-id=1 --file=/home2/b9g6c7m1/kinetica-repo/storage/uploads/storico_completo.csv --medication=Mounjaro
```

## Sicurezza

- Nessun secret e nessuna password nel repository.
- Cookie `HttpOnly`, `SameSite=Lax`, `Secure` quando HTTPS e configurazione produzione sono attivi.
- Prepared statements PDO.
- Rate limiting login.
- Controlli server-side su `user_id`.
- Upload CSV fuori dalla web root.
