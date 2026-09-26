<?php /** @var string $page @var array $pageParams @var string $appName */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($appName) ?> — Flippy Football</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="broadcast">
<header class="topbar">
    <a href="/" class="brand">
        <span class="brand-flag"></span>
        <span class="brand-name"><?= htmlspecialchars($appName) ?></span>
    </a>
    <nav class="topnav">
        <?php if (!empty($pageParams['season_id'])): ?>
            <a href="/season/<?= htmlspecialchars($pageParams['season_id']) ?>">Season</a>
            <a href="/season/<?= htmlspecialchars($pageParams['season_id']) ?>/playoffs">Playoffs</a>
        <?php endif; ?>
    </nav>
</header>
<main class="stage">
<?php
  if ($page === 'notfound') {
      echo '<div class="card center"><h1>404 — Off-Sides</h1><p>Play doesn\'t exist. <a href="/">Back to Home</a></p></div>';
  } else {
      include __DIR__ . "/$page.php";
  }
?>
</main>
<footer class="footer">
    <span>Flippy Football — a tabletop NFL sim. Ratings for entertainment only.</span>
</footer>
</body>
</html>
