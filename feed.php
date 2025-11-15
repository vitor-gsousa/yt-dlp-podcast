<?php
// podcasts/feed.php – gera RSS a partir da sub‑pasta indicada
header('Content-Type: text/xml; charset=utf-8');

// -------------------------------------------------
// 1. Receber o nome da sub‑pasta (ex.: ?feed=yt-ciencia)
// -------------------------------------------------
$feedFolder = $_GET['feed'] ?? '';
$feedFolder = trim($feedFolder, "/");

// -------------------------------------------------
// 2. Caminho físico da pasta dentro de /podcasts
// -------------------------------------------------
$basePath = __DIR__ . '/' . $feedFolder;   // pasta raiz do feed
if ($feedFolder === '' || !is_dir($basePath)) {
    echo <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<error>Folder not found</error>
XML;
    exit;
}

// -------------------------------------------------
// 3. Construir a URL base (sem a query string)
// -------------------------------------------------
$baseUrl = sprintf('http://%s/podcasts/', $_SERVER['HTTP_HOST']);
$baseUrl .= $feedFolder . '/';   // ex.: http://localhost/podcasts/yt-ciencia/

$podImg = "podcasts.jpg";

/* ---------- Título dinâmico ----------
   subpasta = "yt-ciencia" → "YT - Ciencia"
   subpasta = "rn-up"      → "RN - Up"
--------------------------------------- */
function gerarTitulo($folder)
{
    // dividir no primeiro hífen
    $parts = explode('-', $folder, 2);

    // prefixo em maiúsculas (se não houver hífen, usa tudo)
    $prefix = strtoupper($parts[0]);

    // resto (se existir) → substituir -/_ por espaço e capitalizar
    $rest = '';
    if (isset($parts[1])) {
        $rest = str_replace(['-', '_'], ' ', $parts[1]);   // "ciencia" ou "up"
        $rest = ucwords(strtolower($rest));                // "Ciencia" ou "Up"
    }

    // montar título
    return $rest === '' ? $prefix : $prefix . ' - ' . $rest;
}

$feedTitle = gerarTitulo($feedFolder);   // título a usar no RSS
/* -------------------------------------- */

$output  = '<rss xmlns:content="http://purl.org/rss/1.0/modules/content/" '
        . 'xmlns:wfw="http://wellformedweb.org/CommentAPI/" '
        . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
        . 'xmlns:atom="http://www.w3.org/2005/Atom" '
        . 'xmlns:sy="http://purl.org/rss/1.0/modules/syndication/" '
        . 'xmlns:slash="http://purl.org/rss/1.0/modules/slash/" '
        . 'xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" '
        . 'xmlns:rawvoice="http://www.rawvoice.com/rawvoiceRssModule/" version="2.0">';
$output .= '<channel>';
$output .= '<title>' . htmlspecialchars($feedTitle) . '</title>';
$output .= '<description>Descrição do feed.</description>';
$output .= '<link>' . $baseUrl . '</link>';
$output .= '<image>';
$output .= '<url>' . $baseUrl . $podImg . '</url>';
$output .= '<title>' . htmlspecialchars($feedTitle) . '</title>';
$output .= '<link>' . $baseUrl . '</link>';
$output .= '</image>';
$output .= '<itunes:image href="' . $baseUrl . $podImg . '"/>';

// -------------------------------------------------
// 4. Ler os ficheiros *.info.json dentro de data/
// -------------------------------------------------
$dataPath = $basePath . '/data';
$files = glob($dataPath . "/*.info.json");
usort($files, fn($a, $b) => filemtime($b) - filectime($a));

foreach ($files as $file) {
    $json_a = json_decode(file_get_contents($file), true);
    if (!$json_a) {
        continue;               // JSON inválido
    }

    $id = $json_a['id'];

    /* ---------- 1. Tentar ficheiro local ---------- */
    $localM4a = $dataPath . "/$id.m4a";
    $localMp3 = $dataPath . "/$id.mp3";

    $audioFile = '';
    $link      = '';

    if (file_exists($localM4a)) {
        $audioFile = $localM4a;
        $link      = $baseUrl . "data/$id.m4a";
    } elseif (file_exists($localMp3)) {
        $audioFile = $localMp3;
        $link      = $baseUrl . "data/$id.mp3";
    }

    /* ---------- 2. Se não houver ficheiro local, usar link externo ---------- */
    if ($audioFile === '' && !empty($json_a['link'])) {
        $link = $json_a['link'];          // URL externa fornecida no JSON
        // Não há ficheiro local, mas ainda precisamos de um tamanho (length) para o <enclosure>.
        // Se o JSON contiver a chave 'filesize' usamos esse valor; caso contrário deixamos 0.
        $json_a['filesize'] = $json_a['filesize'] ?? 0;
    }

    /* ---------- 3. Se ainda não houver link válido, ignorar o item ---------- */
    if (empty($link)) {
        // Nenhum áudio disponível – pula este registro
        continue;
    }

    /* ---------- 4. Data de publicação ----------
       Usa a data do ficheiro local se existir; caso contrário tenta a data do JSON.
    */
    if ($audioFile !== '') {
        $pubDate = date("D, d M Y H:i:s \G\M\T", filectime($audioFile));
    } elseif (!empty($json_a['upload_date'])) {
        // Assume que upload_date vem no formato Ymd (ex.: 20230815)
        $pubDate = DateTime::createFromFormat('Ymd', $json_a['upload_date'])
                         ->format('D, d M Y H:i:s \G\M\T');
    } else {
        // fallback: data actual
        $pubDate = gmdate('D, d M Y H:i:s \G\M\T');
    }

    /* ---------- 5. Selecionar imagem ----------
       Mantém a lógica anterior (jpg > webp > padrão)
    */
    $image = $baseUrl . $podImg; // padrão
    $jpgPath   = $dataPath . "/$id.jpg";
    $webpPath  = $dataPath . "/$id.webp";

    if (file_exists($jpgPath)) {
        $image = $baseUrl . "data/$id.jpg";
    } elseif (file_exists($webpPath)) {
        $image = $baseUrl . "data/$id.webp";
    }

    /* ---------- 6. Montar o item RSS ---------- */
	// Verificar se a chave 'channel' está presente
	if (isset($json_a['channel']) && $json_a['channel'] !== '') {
    	$itemTitle = $json_a['channel'] . ' ▶️ ' . $json_a['title'];
	} else {
	    $itemTitle = $json_a['title'];
	}
    $output .= '<item>';
    // Escapar para XML e acrescentar ao output
	$output .= '<title>' . htmlspecialchars($itemTitle) . '</title>';
    $output .= '<description>' . htmlspecialchars($json_a['description']) . '</description>';
    $output .= '<link>' . $link . '</link>';
    $output .= '<guid>' . $link . '</guid>';
    $output .= '<enclosure url="' . $link . '" length="' . $json_a['filesize'] . '" type="audio/m4a"/>';
    $output .= '<pubDate>' . $pubDate . '</pubDate>';
    $output .= '<itunes:duration>' . $json_a['duration'] . '</itunes:duration>';
    $output .= '<itunes:image href="' . $image . '" />';
    $output .= '</item>';
}

$output .= '</channel></rss>';

echo $output;
?>
