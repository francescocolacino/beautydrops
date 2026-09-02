# BeautyDrops — Specifica Tecnica di Progetto (v2)

> Documento da incollare come primo messaggio a Claude Code, oppure da salvare nel repo come `CLAUDE.md` / `PROJECT_SPEC.md` prima di iniziare.
> **v2**: stack passato a HTML/PHP/JS/MySQL, aggiunto il sistema "Offerte" (bundle di prodotti con prezzo unico) e chiarito il funzionamento dei bollini.

## 🎯 Obiettivo

Sito web **vetrina/catalogo** (NON e-commerce con carrello o pagamenti) per lo shop **BeautyDrops**. Il link verrà condiviso direttamente con i clienti, che potranno sfogliare i prodotti in vendita divisi per categoria e brand, oltre alle eventuali offerte speciali del momento. Solo il titolare (admin) può aggiungere, modificare o eliminare prodotti e offerte tramite un'area riservata.

**Fuori scope (esplicitamente da NON implementare per ora):**
- Carrello, checkout, pagamenti online
- Registrazione/login per utenti pubblici
- Multi-lingua

---

## 🧱 Stack tecnologico

- **HTML5 + CSS3 + JavaScript (vanilla)** per il frontend
- **PHP 8+** per il backend (niente framework pesanti: struttura semplice e leggibile, coerente con la richiesta di un progetto facile da mantenere)
- **MySQL / MariaDB** come database (standard su qualunque hosting condiviso)
- **Autenticazione admin**: sessioni PHP native (`$_SESSION`), password salvata con `password_hash()` / verificata con `password_verify()` — un solo account admin, niente registrazione pubblica
- **Upload immagini prodotto**: upload nativo PHP (`move_uploaded_file()`), file salvati in `/assets/images/products/`, il percorso viene salvato nel DB
- **Animazioni**: CSS puro (transizioni, keyframes) + libreria leggera via CDN per lo scroll-reveal (es. AOS.js), niente framework JS pesanti

**Vantaggio pratico**: questo stack gira su qualsiasi hosting condiviso economico con PHP e MySQL (es. hosting con cPanel), molto più semplice da pubblicare e condividere via link rispetto a soluzioni con build step/hosting serverless.

### Struttura cartelle consigliata

```
/beautydrops
├── index.php                  → Homepage pubblica
├── category.php                → Pagina categoria (?slug=cosmetici|elettronica|abbigliamento)
├── config.php                  → Credenziali DB (non versionare)
├── schema.sql                  → Script creazione tabelle
├── /includes
│   ├── db.php                  → Connessione PDO
│   ├── auth.php                 → Controllo sessione admin (richiesto in ogni pagina /admin)
│   └── functions.php            → Funzioni condivise (badge, formattazione prezzi, calcolo sconto offerte)
├── /admin
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php            → Elenco prodotti, modifica/elimina
│   ├── product-form.php          → Form aggiungi/modifica prodotto
│   ├── offers.php                → Elenco offerte, modifica/elimina
│   └── offer-form.php             → Form crea/modifica offerta
└── /assets
    ├── /css
    ├── /js
    └── /images/products
```

---

## 🗂 Struttura del sito (pubblico)

### Header (su tutte le pagine pubbliche)
- Logo + wordmark "**BeautyDrops**"
- Navbar con 3 voci di categoria: **Cosmetici** · **Elettronica & Audio** · **Abbigliamento, Scarpe & Borse**
- Pulsante discreto in alto a destra: **"Accesso Admin"** → `/admin/login.php`

### Homepage (`index.php`)
1. Hero con logo/nome brand e tagline breve
2. 3 card grandi cliccabili, una per categoria
3. **Sezione "Super Offerte"** (nuova): card per ogni offerta attiva — vedi sezione dedicata sotto

### Pagine categoria (`category.php?slug=...`)
- Prodotti raggruppati per brand (sezioni ordinate alfabeticamente)
- Ricerca/filtro opzionale per brand o nome prodotto
- Ogni prodotto incluso in un'offerta attiva mostra anche qui il bollino "In offerta"

### Card prodotto (vista pubblica)
- Immagine, brand, nome prodotto
- Varianti/colori disponibili (chip/tag)
- I 3 bollini di stato — sempre visibili al pubblico
- Prezzo (se impostato), altrimenti nessun prezzo mostrato

---

## 🏷 Sistema dei bollini (badge di stato)

I tre bollini sono **indipendenti tra loro e possono comparire insieme** sullo stesso prodotto (es. un prodotto può essere contemporaneamente "Disponibile" e "In offerta"). I primi due comunicano soprattutto la **velocità di consegna** al cliente.

| Bollino | Colore | Cosa comunica al cliente | Condizione |
|---|---|---|---|
| **Disponibile** | Verde | Pronto, consegna rapida | `stock_quantity >= 1` |
| **Ordinabile** | Blu/Azzurro | Va ordinato, tempi più lunghi | `orderable_quantity >= 1` |
| **In offerta** | Rosso/Oro | Prezzo speciale (singolo o in bundle) | il prodotto è incluso in almeno un'**Offerta** attiva |

Le quantità sono impostate dall'admin nel form prodotto; i bollini "Disponibile"/"Ordinabile" compaiono automaticamente quando la quantità è ≥1, senza bisogno di un toggle separato.

---

## 🎁 Sistema Offerte (bundle di prodotti)

Permette all'admin di raggruppare **uno o più prodotti** (anche uno solo) sotto un'offerta con **nome proprio** e **un prezzo unico finale**, diverso dalla somma dei prezzi dei singoli prodotti.

### Lato Admin (`admin/offers.php` + `admin/offer-form.php`)
- Elenco offerte esistenti: nome, prodotti inclusi, prezzo offerta, stato attivo/disattivo, azioni modifica/elimina
- Form crea/modifica offerta:
  - **Nome offerta** (es. "Rhode Lip Bundle", "Super Offerta Estate")
  - **Selezione prodotti**: multi-select/checkbox con ricerca tra i prodotti esistenti (anche di categorie diverse tra loro)
  - **Prezzo unico offerta** (prezzo totale del pacchetto)
  - **Attiva/disattiva** (per nascondere l'offerta dal sito senza eliminarla)
- La percentuale di sconto **non si inserisce manualmente**: viene calcolata automaticamente confrontando il prezzo offerta con la somma dei prezzi dei prodotti inclusi (solo se tutti hanno un prezzo impostato — altrimenti si mostra solo il prezzo offerta, senza sconto calcolato)

### Lato pubblico
- Sezione **"Super Offerte"** in homepage, sotto le card delle categorie
- Ogni offerta è una card con: nome offerta, miniature + nomi dei prodotti inclusi, prezzo totale originale barrato (quando calcolabile), prezzo offerta in evidenza, percentuale di risparmio
- Nessun upload immagine dedicato per l'offerta: le card riusano le immagini già caricate dei prodotti inclusi

---

## 🗃 Modello dati (schema MySQL)

```sql
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category ENUM('cosmetici','elettronica','abbigliamento') NOT NULL,
  brand VARCHAR(100) NOT NULL,
  name VARCHAR(150) NOT NULL,
  variants JSON NULL,              -- es. ["Rosso","Blu","Taglia M"] (fallback: TEXT separato da virgole se l'hosting non supporta JSON)
  image_path VARCHAR(255),
  stock_quantity INT NOT NULL DEFAULT 0,      -- >=1 -> bollino "Disponibile"
  orderable_quantity INT NOT NULL DEFAULT 0,  -- >=1 -> bollino "Ordinabile"
  price DECIMAL(10,2) NULL,
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL
);

CREATE TABLE offers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  offer_price DECIMAL(10,2) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE offer_products (
  offer_id INT NOT NULL,
  product_id INT NOT NULL,
  PRIMARY KEY (offer_id, product_id),
  FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);
```

---

## 🔐 Area Admin

### Login (`admin/login.php`)
- Email + password, singolo account, credenziali hashate in tabella `admins`
- Nessuna pagina di registrazione pubblica

### Dashboard admin (`admin/dashboard.php`)
- Protetta da `includes/auth.php` (redirect a login se sessione non valida)
- Elenco prodotti (filtrabile per categoria) con **Modifica**/**Elimina**
- Form prodotto: categoria, brand, nome, varianti/colori, immagine, quantità magazzino, quantità ordinabile, prezzo (opzionale), descrizione (opzionale)
- Link alla sezione **Gestione Offerte** (vedi sopra)
- Pulsanti Modifica/Elimina visibili **solo** da autenticati (mai in vista pubblica)

---

## 🎨 Design e animazioni

**Estetica**: beauty elegante — palette pastello (rosa/nude) con accento oro, molto white space, tipografia moderna via Google Fonts (es. *Playfair Display* per i titoli, *Poppins* o *Inter* per i testi). Mobile-first: il link verrà aperto soprattutto da smartphone.

**Animazioni consigliate** (leggere, coerenti con uno stack vanilla JS):
- Hover su card prodotto/offerta: leggero sollevamento (`transform: translateY` + ombra) e zoom morbido sull'immagine
- Scroll-reveal: gli elementi appaiono con un fade-in/slide-up mentre si scrolla (libreria **AOS.js** via CDN, oppure Intersection Observer scritto a mano — entrambe leggere)
- Micro-animazione (pulse discreto) sul bollino "In offerta" per attirare l'attenzione senza essere invasivo
- Transizioni morbide su bottoni/link (colore, ombra) invece di cambi istantanei
- Se le card "Super Offerte" aprono un dettaglio, farlo con un modale animato (fade + scale in apertura) invece di una nuova pagina

Il sito deve restare leggero e veloce: preferire CSS puro + JS vanilla + eventuali micro-librerie via CDN, evitando dipendenze pesanti.

---

## 🚀 Deployment

- Qualsiasi hosting con **PHP 8+** e **MySQL/MariaDB** (la maggior parte degli hosting condivisi economici li supporta di default, spesso con pannello cPanel)
- Caricamento file via FTP/File Manager del pannello di hosting, oppure Git se l'hosting lo supporta
- Eseguire `schema.sql` per creare le tabelle al primo deploy
- Attivare HTTPS (spesso disponibile gratis via Let's Encrypt dal pannello hosting)
- Proteggere la cartella `/admin` anche a livello server (facoltativo, extra sicurezza) oltre al controllo sessione PHP

---

## ✅ Checklist funzionalità

- [x] Homepage con logo BeautyDrops, navbar 3 categorie e sezione Super Offerte
- [x] Pagine categoria con prodotti raggruppati per brand
- [x] Card prodotto pubblica con i 3 bollini (indipendenti tra loro) e prezzo se presente
- [x] Pulsante "Accesso Admin" in header
- [x] Login admin sicuro (singolo account, PHP session)
- [x] Dashboard admin con CRUD completo prodotti
- [x] Sezione admin "Gestione Offerte": crea/modifica/elimina bundle con prezzo unico
- [x] Bollino "In offerta" calcolato automaticamente in base alle offerte attive
- [x] Upload immagine prodotto
- [x] Animazioni hover/scroll-reveal su card prodotto e offerta
- [x] Design responsive mobile-first
- [ ] Deploy su hosting PHP/MySQL con link pubblico funzionante

---

## 📋 Setup locale / primo deploy

1. Creare un database MySQL/MariaDB ed eseguire `schema.sql`
2. Copiare `config.php` e impostare host/nome DB/utente/password reali
3. L'admin di default creato da `schema.sql` è `admin@beautydrops.it` / `beautydrops123` — **cambiare la password al primo accesso** (non c'è ancora una UI per farlo: aggiornare `password_hash` in tabella `admins` con `password_hash('nuova_password', PASSWORD_DEFAULT)`)
4. Verificare che `/assets/images/products/` sia scrivibile dal webserver
5. Avvio rapido in locale: `php -S localhost:8000` dalla root del progetto (richiede comunque MySQL raggiungibile)
