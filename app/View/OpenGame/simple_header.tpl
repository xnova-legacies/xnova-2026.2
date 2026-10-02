<!DOCTYPE html>
<html lang="{lang}" data-bs-theme="{theme}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark light">
<title>{title}</title>
<link rel="shortcut icon" href="/favicon.ico">
{-style-}
{-meta-}
<script>
(function () {
    try {
        var theme = localStorage.getItem('xnova-theme');
        if (theme === 'light' || theme === 'dark') {
            document.documentElement.setAttribute('data-bs-theme', theme);
        }
    } catch (e) { /* stockage indisponible */ }
})();
</script>
<script type="text/javascript" src="/scripts/overlib.js"></script>
</head>
{-body-}
{-script-}
 