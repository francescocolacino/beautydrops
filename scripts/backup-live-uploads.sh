#!/bin/sh
# Salva in assets/images/uploads-backup/ le foto caricate dall'admin sul sito
# live che non sono ancora nel backup.
#
# Su Railway il filesystem del container è effimero: a ogni deploy la cartella
# assets/images/products/ si svuota e scripts/restore-uploads.php la ripopola
# solo con i file presenti nel backup versionato. Una foto caricata dopo
# l'ultimo backup va quindi persa al deploy successivo, se non viene salvata
# prima. Questo script va eseguito (e il risultato committato) PRIMA di ogni
# push su main: l'hook pre-push lo lancia in automatico e blocca il push se
# ha scaricato foto nuove, così si possono committare insieme al resto.
#
# Uso: sh scripts/backup-live-uploads.sh
# Esce con 1 se ha scaricato foto nuove (da committare), 0 altrimenti.

BASE_URL="${BEAUTYDROPS_URL:-https://beautydrops-production.up.railway.app}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BACKUP_DIR="$ROOT/assets/images/uploads-backup"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

mkdir -p "$BACKUP_DIR"

# Pagine da cui raccogliere i percorsi: home, categorie e ogni scheda prodotto
# (le foto di galleria compaiono solo nella scheda).
for slug in cosmetici elettronica abbigliamento; do
  curl -sf "$BASE_URL/category.php?slug=$slug"
done > "$WORK/pages.html"
curl -sf "$BASE_URL/" >> "$WORK/pages.html"

if [ ! -s "$WORK/pages.html" ]; then
  echo "backup-live-uploads: sito non raggiungibile ($BASE_URL), backup non verificato." >&2
  exit 1
fi

grep -o 'product\.php?id=[0-9]*' "$WORK/pages.html" | grep -o '[0-9]*$' | sort -un > "$WORK/ids.txt"
xargs -P 8 -I{} curl -sf "$BASE_URL/product.php?id={}" < "$WORK/ids.txt" >> "$WORK/pages.html"

grep -o '/assets/images/products/[A-Za-z0-9._-]*' "$WORK/pages.html" \
  | sed 's#^/assets/images/products/##' | sort -u > "$WORK/live.txt"

downloaded=0
missing=0
while read -r name; do
  [ -z "$name" ] && continue
  [ -f "$BACKUP_DIR/$name" ] && continue
  if curl -sf -o "$WORK/$name" "$BASE_URL/assets/images/products/$name" && [ -s "$WORK/$name" ]; then
    mv "$WORK/$name" "$BACKUP_DIR/$name"
    echo "  salvata: $name"
    downloaded=$((downloaded + 1))
  else
    echo "  NON recuperabile dal sito (già persa?): $name" >&2
    missing=$((missing + 1))
  fi
done < "$WORK/live.txt"

echo "backup-live-uploads: $(wc -l < "$WORK/live.txt" | tr -d ' ') foto caricate sul sito, $downloaded nuove salvate, $missing non recuperabili."

if [ "$downloaded" -gt 0 ]; then
  echo "Committa assets/images/uploads-backup/ prima di fare il push, altrimenti il deploy le cancella." >&2
  exit 1
fi
exit 0
