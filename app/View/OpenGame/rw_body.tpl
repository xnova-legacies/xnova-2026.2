<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark light">
<title>{rw_title}</title>
<link rel="shortcut icon" href="/favicon.ico">
<link rel="stylesheet" type="text/css" href="{rw_css}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/css/styles.css">
<link rel="stylesheet" href="/css/bootstrap-compat.css">
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
</head>
<body class="xnova-body">
<main class="xnova-page xnova-page-report">{rw_report}</main>
<script src="/scripts/theme.js" defer></script>
</body>
</html>
