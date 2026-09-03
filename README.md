# BeautyDrops

Catalogo vetrina (non e-commerce) per lo shop BeautyDrops. Backend PHP 8 +
PostgreSQL, deploy come container Docker su [Koyeb](https://www.koyeb.com/)
con deploy automatico ad ogni push su `main`.

## Stack

- PHP 8.3 + Apache (immagine `php:8.3-apache`)
- PostgreSQL (PDO `pdo_pgsql`)
- JavaScript vanilla, CSS puro, AOS.js via CDN per lo scroll-reveal

## Deploy su Koyeb

### 1. Crea il database PostgreSQL

Su Koyeb: **Database Service → PostgreSQL** (oppure usa un Postgres esterno,
es. Neon/Supabase). Al termine, apri il servizio database e copia il
**connection string** (`DATABASE_URL`), del tipo:

```
postgresql://utente:password@host:5432/nome_database
```

### 2. Crea il Web Service dalla repository GitHub

1. **Koyeb → Create Service → GitHub**, seleziona questa repository.
2. Branch: **`main`**.
3. Builder: **Dockerfile** (Koyeb rileva automaticamente il `Dockerfile` nella
   root del progetto).
4. Porta: il container espone la **porta 80** (Apache) — imposta `80` come
   porta del servizio in Koyeb, protocollo HTTP.
5. Variabili d'ambiente (Environment secrets/variables):
   - `DATABASE_URL` → il connection string copiato al punto 1
   - `DB_SSLMODE` → `require` (default già usato se non impostata)
   - `ADMIN_EMAIL` → email del futuro account admin
   - `ADMIN_PASSWORD` → password del futuro account admin (solo per
     l'esecuzione una tantum dello script di creazione admin, vedi sotto —
     non resta salvata in chiaro da nessuna parte dopo l'esecuzione)
6. Deploy.

Ogni nuovo push sul branch `main` (compreso il primo) avvia automaticamente
un nuovo build e deploy dell'immagine Docker: non serve alcuna azione manuale
successiva su Koyeb.

### 3. Inizializza lo schema del database

Con un client `psql` (o l'editor SQL del provider Postgres), esegui una volta
il contenuto di [`schema.sql`](schema.sql) sul database creato al punto 1:

```bash
psql "$DATABASE_URL" -f schema.sql
```

Lo script è idempotente (usa `CREATE TABLE IF NOT EXISTS` / `CREATE INDEX IF
NOT EXISTS`): può essere rieseguito in sicurezza senza perdere dati già
presenti.

### 4. Crea l'account amministratore

Lo schema **non** crea più un admin di default. Dopo il primo deploy, esegui
una volta lo script `scripts/create-admin.php` con le stesse variabili
d'ambiente configurate su Koyeb (`DATABASE_URL`, `ADMIN_EMAIL`,
`ADMIN_PASSWORD`) — ad esempio da un one-off job/console Koyeb, oppure in
locale puntando allo stesso database:

```bash
DATABASE_URL="postgresql://..." ADMIN_EMAIL="admin@tuodominio.it" ADMIN_PASSWORD="una-password-robusta" \
  php scripts/create-admin.php
```

Lo script crea l'admin se non esiste, oppure aggiorna la password se
l'email esiste già (`ON CONFLICT (email) DO UPDATE`). Non stampa mai la
password, solo un messaggio di successo o un errore generico.

### 5. Verifica

1. Apri il dominio pubblico generato da Koyeb (**Koyeb → il servizio →
   Public domain**): la homepage del catalogo deve caricarsi correttamente.
2. Vai su `/admin/login.php` ed effettua l'accesso con le credenziali create
   al punto 4.

## Persistenza delle immagini caricate — limite importante

Koyeb esegue il servizio in un container **stateless**: il filesystem locale
non è garantito persistente tra un deploy e l'altro (un nuovo deploy può
ricreare il container da zero). Questo riguarda **solo** le immagini
caricate dal pannello admin (`admin/product-form.php`), salvate in
`assets/images/products/`:

- l'upload via form **continua a funzionare** normalmente all'interno dello
  stesso container in esecuzione;
- ma un nuovo deploy (ogni push su `main`) sovrascrive il filesystem del
  container con il contenuto della repository: le immagini caricate da
  admin **dopo** l'ultimo deploy andrebbero perse al deploy successivo.

Le immagini del **catalogo iniziale** (231 prodotti importati da
`data/catalog-products.json`) non sono invece a rischio: sono file versionati
nella repository sotto `assets/images/catalog/`, quindi vengono ricreate ad
ogni deploy insieme al codice. Verificato: tutti i 231 percorsi immagine nel
catalogo puntano a file effettivamente presenti nella repository (nessuna
immagine mancante).

Lo stesso limite vale per i PDF dei preventivi generati dal carrello,
salvati in `assets/orders/`.

**Sviluppo successivo consigliato**: spostare gli upload (immagini prodotto
e PDF ordini) su un object storage esterno persistente — es. **Cloudinary**,
oppure S3/Backblaze B2/Koyeb volumes se disponibili — così da sopravvivere
ai redeploy. Non è stato implementato in questa fase (nessuna dipendenza
Cloudinary era già presente nel progetto).

## Sviluppo locale

Richiede PHP 8+ con estensione `pdo_pgsql` e un database PostgreSQL
raggiungibile (locale o remoto).

```bash
# Copia .env.example in .env e compila i valori, oppure esporta le variabili
# direttamente nella shell prima di avviare il server:
export DATABASE_URL="postgresql://utente:password@localhost:5432/beautydrops"

psql "$DATABASE_URL" -f schema.sql
ADMIN_EMAIL="admin@example.com" ADMIN_PASSWORD="cambia-questa-password" php scripts/create-admin.php

php -S localhost:8000
```

Assicurati che `assets/images/products/` e `assets/orders/` siano scrivibili
dal processo PHP locale.

## Configurazione del database (`includes/db.php`)

La connessione legge, in ordine di priorità:

1. `DATABASE_URL` (formato `postgresql://utente:password@host:porta/database`,
   con eventuale `?sslmode=...` in coda) — usata da Koyeb e dalla maggior
   parte dei provider Postgres gestiti;
2. in fallback, le variabili separate `DB_HOST`, `DB_PORT` (default `5432`),
   `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_SSLMODE` (default `require`).

Se nessuna delle due configurazioni è presente, l'app mostra un errore
generico (nessuna password o dettaglio di connessione viene mai esposto a
schermo o loggato lato client).

## Export statico

Nella storia della repository era presente uno script
`scripts/export-static.php` (per un export statico su Netlify) ed una
directory `dist/`: entrambi sono già stati rimossi in un commit precedente a
questa migrazione ("Remove static Netlify export") e non sono quindi presenti
nel branch corrente. Su Koyeb il progetto va servito **dinamico** (PHP +
Postgres) tramite il `Dockerfile` di questo repository, non come export
statico.

## Note sulla migrazione da MySQL a PostgreSQL

- `includes/db.php`: PDO ora usa il driver `pgsql` invece di `mysql`, con
  parsing di `DATABASE_URL` via `parse_url()`/`urldecode()` e fallback alle
  variabili separate. Rimossa l'opzione `PDO::ATTR_EMULATE_PREPARES => false`
  (non necessaria/rilevante con `pgsql`, che usa prepared statement nativi).
- `schema.sql`: sintassi convertita a PostgreSQL (`GENERATED BY DEFAULT AS
  IDENTITY`, `CHECK` al posto di `ENUM`, `JSONB`, `BOOLEAN`, `NUMERIC`,
  trigger `set_products_updated_at()` al posto di `ON UPDATE
  CURRENT_TIMESTAMP`). Rimosso l'admin di default: va creato con
  `scripts/create-admin.php`.
- Query applicative aggiornate: `ORDER BY RAND()` → `ORDER BY RANDOM()`
  (`product.php`), `active = 1` → `active = TRUE` (`index.php`,
  `includes/functions.php`), `PDO::lastInsertId()` → `INSERT ... RETURNING
  id` + `fetchColumn()` (`admin/offer-form.php`, `order-submit.php` — il
  driver `pgsql` non garantisce `lastInsertId()` senza indicare
  esplicitamente la sequence).
- Tutte le altre query erano già scritte con prepared statement compatibili
  con PostgreSQL (nessuna concatenazione di input utente nelle query).
