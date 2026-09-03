<?php
declare(strict_types=1);

$catalogPath = __DIR__ . '/../data/catalog-products.json';
$catalog = json_decode((string) file_get_contents($catalogPath), true, 512, JSON_THROW_ON_ERROR);

function product_description(array $product): string
{
    $brand = $product['brand'];
    $name = trim($product['name']);
    $searchable = mb_strtolower($brand . ' ' . $name, 'UTF-8');
    $label = $brand . ' ' . $name;

    if (str_contains($searchable, 'phone case') || str_contains($searchable, 'pouch')) {
        return "$label è una custodia protettiva per smartphone, pensata per tenere il telefono al sicuro e sempre a portata di mano. Scegli il formato compatibile e inserisci il dispositivo nella custodia prima di usarlo.";
    }
    if (str_contains($searchable, 'sticker') || str_contains($searchable, 'paper box') || str_contains($searchable, 'paper bag') || str_contains($searchable, 'paper (') || $name === 'paper' || $name === 'card') {
        return "$label è un accessorio o packaging da collezione del brand, utile per completare un regalo o custodire i prodotti. Usalo come confezione, elemento decorativo o ricordo, secondo il formato.";
    }
    if (str_contains($searchable, 'makeup bag') || str_contains($searchable, 'towel') || str_contains($searchable, 'hairband') || $name === 'mirror') {
        return "$label è un accessorio pratico per la routine beauty e per organizzare i prodotti fuori casa. Utilizzalo per riporre il makeup e gli strumenti oppure, nel caso dello specchio, per controllare l'applicazione in modo preciso.";
    }
    if (str_contains($searchable, 'hair oil')) {
        return "$label è un olio leggero per capelli che aiuta a nutrire le lunghezze e a rendere la chioma più lucida e disciplinata. Scalda una piccola quantità tra le mani e applicala su lunghezze e punte asciutte o umide, evitando le radici.";
    }
    if (str_contains($searchable, 'hair perfume')) {
        return "$label è una fragranza pensata per profumare delicatamente i capelli. Vaporizzala a distanza sulle lunghezze, senza appesantire la radice, e riapplicala quando desideri rinfrescare il profumo.";
    }
    if (str_contains($searchable, 'perfume mist') || str_contains($searchable, 'perfume set')) {
        return "$label è una fragranza spray da corpo, disponibile anche in formato set per provare o regalare più profumi. Vaporizzala sulla pelle a qualche centimetro di distanza; evita occhi e tessuti delicati.";
    }
    if (str_contains($searchable, 'cleanser') || str_contains($searchable, 'face wash') || str_contains($searchable, 'makeup remover')) {
        return "$label è un detergente o struccante per rimuovere impurità e residui di makeup. Massaggialo sulla pelle, con occhi chiusi se indicato per il viso, poi risciacqua o rimuovi con un dischetto secondo la texture.";
    }
    if (str_contains($searchable, 'mask')) {
        return "$label è una maschera per una pausa di trattamento nella routine viso. Applicane uno strato uniforme sulla pelle pulita, lascia agire per il tempo indicato sulla confezione e rimuovi delicatamente.";
    }
    if (str_contains($searchable, 'serum') || str_contains($searchable, 'fluid') || str_contains($searchable, 'glazing milk') || str_contains($searchable, 'mist')) {
        return "$label è un trattamento skincare da inserire nella routine quotidiana per preparare e idratare la pelle. Dopo la detersione applica una piccola quantità su viso e collo, poi completa con la crema; durante il giorno usa anche la protezione solare.";
    }
    if (str_contains($searchable, 'cream') || str_contains($searchable, 'water cream') || str_contains($searchable, 'barrier')) {
        return "$label è una crema viso pensata per idratare e sostenere il comfort della pelle. Applicane una piccola quantità su viso e collo dopo i trattamenti più leggeri, mattina e sera o quando la pelle ne sente bisogno.";
    }
    if (str_contains($searchable, 'primer') || str_contains($searchable, 'uv primer')) {
        return "$label è una base che prepara la pelle al makeup, aiutando a uniformare la superficie e a far aderire meglio i prodotti successivi. Stendine uno strato sottile dopo la skincare; se contiene SPF, non sostituisce necessariamente una protezione solare dedicata.";
    }
    if (str_contains($searchable, 'setting spray') || str_contains($searchable, 'fixing')) {
        return "$label è uno spray fissante da usare per completare il trucco e aiutare il makeup a mantenersi più ordinato. Agita, chiudi gli occhi e vaporizza da circa 20-30 cm sul viso già truccato, lasciando asciugare senza toccare.";
    }
    if (str_contains($searchable, 'foundation') || str_contains($searchable, 'flawless filter') || str_contains($searchable, 'cushion')) {
        return "$label è un prodotto per uniformare l'incarnato e modulare la coprenza, da leggera a più completa secondo la quantità. Applica poco prodotto dal centro del viso verso l'esterno e sfuma con dita, spugna o pennello.";
    }
    if (str_contains($searchable, 'concealer') || str_contains($searchable, 'corrector')) {
        return "$label è un correttore per attenuare occhiaie, rossori e piccole discromie. Applica una quantità contenuta solo dove serve e sfuma i bordi con il dito, una spugna o un pennello; aggiungi prodotto gradualmente.";
    }
    if (str_contains($searchable, 'powder') || str_contains($searchable, 'bake')) {
        return "$label è una cipria per fissare il makeup e ridurre la lucidità, soprattutto nella zona T. Preleva poco prodotto con puff o pennello e applicalo premendo o sfiorando le aree che vuoi mantenere più opache.";
    }
    if (str_contains($searchable, 'blush') || str_contains($searchable, 'cheek')) {
        return "$label è un blush per dare colore e dimensione alle guance e rendere il viso più fresco. Applica una piccola quantità sulle guance e sfuma verso le tempie; la formula liquida o cremosa va lavorata prima che si fissi.";
    }
    if (str_contains($searchable, 'bronzer') || str_contains($searchable, 'contour')) {
        return "$label è un prodotto per aggiungere calore o definire i volumi del viso. Stendilo con mano leggera su zigomi, tempie e punti naturalmente esposti alla luce, poi sfuma bene per evitare stacchi netti.";
    }
    if (str_contains($searchable, 'highlighter') || str_contains($searchable, 'highlight') || str_contains($searchable, 'glow')) {
        return "$label è un illuminante per valorizzare i punti luce del viso con un effetto luminoso modulabile. Applicane poco su zigomi, arco di Cupido e dorso del naso, quindi sfuma i bordi per un risultato naturale.";
    }
    if (str_contains($searchable, 'mascara')) {
        return "$label è un mascara per definire, incurvare o dare volume alle ciglia. Appoggia lo scovolino alla base e pettina verso le punte con movimenti leggeri; lascia asciugare tra una passata e l'altra per aumentare l'intensità.";
    }
    if (str_contains($searchable, 'eyeshadow') || str_contains($searchable, 'palette') || str_contains($searchable, 'eye palette')) {
        return "$label è una palette occhi con più tonalità da usare singolarmente o insieme per creare look naturali o più intensi. Applica le polveri con un pennello asciutto, sfumando dal colore più chiaro al più profondo.";
    }
    if (str_contains($searchable, 'liner')) {
        return "$label è un prodotto per definire il contorno delle labbra e rendere il makeup più preciso e duraturo. Disegna prima il bordo con tratti brevi, poi riempi leggermente l'interno o abbinalo a un gloss o rossetto.";
    }
    if (str_contains($searchable, 'lipstick') || str_contains($searchable, 'lip stick') || str_contains($searchable, 'lip balm') || str_contains($searchable, 'lip glow')) {
        return "$label è un prodotto labbra che combina colore e una sensazione confortevole, con un risultato da naturale a più definito in base alle passate. Applicalo direttamente o con un pennello, partendo dal centro delle labbra verso gli angoli.";
    }
    if (str_contains($searchable, 'gloss') || str_contains($searchable, 'lip oil') || str_contains($searchable, 'jelly oil') || str_contains($searchable, 'lip bath')) {
        return "$label è un prodotto labbra dalla finitura lucida, da solo o sopra una matita o un rossetto. Stendilo dal centro verso i bordi senza eccedere; riapplica durante la giornata quando desideri ravvivare brillantezza e comfort.";
    }
    if (str_contains($searchable, 'set') || str_contains($searchable, 'duo') || str_contains($searchable, 'trio') || str_contains($searchable, 'kit') || str_contains($searchable, '3 in 1') || str_contains($searchable, '2 in 1')) {
        return "$label è un set di prodotti coordinati per creare una routine o un look completo con più articoli dello stesso brand. Usa ogni prodotto secondo la sua funzione, iniziando dalle formule skincare e terminando con il makeup.";
    }

    return "$label è un prodotto beauty pensato per completare la routine quotidiana. Applica una piccola quantità sulla zona interessata e sfuma o massaggia fino a ottenere il risultato desiderato, seguendo sempre le indicazioni riportate sulla confezione.";
}

foreach ($catalog as &$product) {
    $product['description'] = product_description($product);
}
unset($product);

file_put_contents(
    $catalogPath,
    json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
);

echo 'Descrizioni aggiornate: ' . count($catalog) . PHP_EOL;