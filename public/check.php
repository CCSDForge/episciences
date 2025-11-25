<?php

$file = "/css/main.css";
$lang = "fr";

if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $acceptLangs = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
    preg_match_all('/([a-z-]{2,8})(?:-[a-z]{2})?(?:;q=([0-9.]+))?/i', $acceptLangs, $matches);

    if (isset($matches[1])) {
        $languagePriorities = [];
        foreach ($matches[1] as $key => $lang) {
            $priority = $matches[2][$key] ?? 1.0;
            if ($priority === '') {
                $priority = 1.0;
            }
            $languagePriorities[$lang] = (float)$priority;
        }

        $isFrenchAccepted = isset($languagePriorities['fr']);
        $isEnglishAccepted = isset($languagePriorities['en']);

        // Comparer les priorités entre le français et l'anglais
        if ($isFrenchAccepted && $isEnglishAccepted) {
            if ($languagePriorities['fr'] > $languagePriorities['en']) {
                $lang = "fr";
            } elseif ($languagePriorities['fr'] < $languagePriorities['en']) {
                $lang = "en";
            } else {
                $lang = "fr";
            }
        } elseif ($isFrenchAccepted) {
            $lang = "fr";
        }
    }
}

setcookie("lang", $lang, time() + 3600, "/");

function translate($key): string
{
    global $lang;
    $translation = [
            'fr' => [
                    'search-epi-oa' => 'Recherche - Episcience',
                    'access-content' => 'Accéder directement au contenu',
                    'cant-handle-request' => 'Désolé, nous ne pouvons pas traiter votre demande',
                    'cant-handle-request-text' => "Il semble que votre requête ait été identifiée comme provenant d’un robot.<br/>Si ce n’est pas le cas, veuillez nous excuser pour la gêne occasionnée. <br /><br />Vous pouvez relancer la recherche en <strong>actualisant la page</strong> ou en <strong>revenant en arrière</strong> dans votre navigateur."
            ],
            'en' => [
                    'search-epi-oa' => 'Search - Episcience',
                    'access-content' => 'Access content directly',
                    'cant-handle-request' => 'Sorry, we are unable to process your request',
                    'cant-handle-request-text' => "It seems that your request has been identified as coming from a robot.<br/>If this is not the case, please accept our apologies for the inconvenience.<br /><br />You can try your search again by <strong>refreshing the page</strong> or by <strong>going back</strong> in your browser."
            ]
    ];

    return $translation[$lang][$key] ?? $key;
}

?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex">
    <style>

        .centered-text {
            background: #fff;
            padding: 2em 3em;
            border-radius: 10px;
            box-shadow: 0 3px 12px #0002;
            color: #222;
            text-align: center;
            font-size: 1.4rem;
            font-family: Arial, Verdana, sans-serif;
            max-width: 90vw;
            max-height: 90vh;
        }


    </style>
    <title>Episciences</title>
    <link href="//cdn.mathjax.org" rel="dns-prefetch">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
    <link rel="shortcut icon" href="/favicon.ico">
    <meta name="msapplication-TileColor" content="#2b5797">
    <meta name="theme-color" content="#ffffff">

    <link rel="stylesheet" href="<?= $file ?>"/>
    <title><?= translate('search-epi-oa') ?></title>
</head>
<body>

<div class="visually-hidden-focusable">
    <nav class="fr-container" role="navigation" aria-label="Accès rapide">
        <ul>
            <li><a href="#skip-link"><?= translate('access-content') ?></a></li>
        </ul>
    </nav>
</div>

<nav class="navbar navbar-expand-lg header navbar-dark" aria-label="Menu">
    <!-- Navbar pour taille mobile -->
    <a class="navbar-brand logo-episciences" href="//www.episciences.org">
        <img alt='Logo' src="/img/episciences.svg" height="40" title="Episciences"/>
    </a>
</nav>


<main id="skip-link">
    <h1 style="text-align:center;margin-top:2em;margin-bottom:2em"><?= translate('cant-handle-request') ?></h1>
    <div class="centered-text">
        <p style="font-size: 16px;"><?= translate('cant-handle-request-text') ?></p>
    </div>
</main>
</body>
</html>

