<?php
declare(strict_types=1);

/**
 * ====== 路径配置（按你的环境改）======
 * 不要使用 __DIR__
 */
$CSS_PATH = "public/style.css";

/**
 * 固定日记文件列表（手动维护）
 * 例如放在 databases/diaries 下就写完整路径：
 * "databases/diaries/2026-04-28.md"
 */
$DIARY_FILES = [
    "diaries/1.md",
    "diaries/2.md",
    // "diaries/2026-04-30.md",
];

$query = trim($_GET['q'] ?? '');

/** 解析 front matter（--- ... ---）和正文 */
function parseFrontMatter(string $raw): array
{
    $meta = [];
    $body = $raw;

    if (preg_match('/^---\s*\R(.*?)\R---\s*\R?/s', $raw, $m)) {
        $front = trim($m[1]);
        $body = substr($raw, strlen($m[0]));

        $lines = preg_split('/\R/', $front);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;

            if (preg_match('/^([A-Za-z0-9_\-]+)\s*:\s*(.*)$/', $line, $mm)) {
                $key = trim($mm[1]);
                $val = trim($mm[2]);

                if (
                    (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))
                ) {
                    $val = substr($val, 1, -1);
                }
                $meta[$key] = $val;
            }
        }
    }

    return [$meta, $body];
}

/** 零依赖 Markdown -> HTML（简版） */
function markdownToHtml(string $md): string
{
    $md = str_replace(["\r\n", "\r"], "\n", $md);
    $lines = explode("\n", $md);

    $html = '';
    $inUl = false;
    $inOl = false;
    $inCode = false;

    foreach ($lines as $line) {
        $trim = trim($line);

        if (str_starts_with($trim, "```")) {
            if (!$inCode) { $html .= "<pre><code>"; $inCode = true; }
            else { $html .= "</code></pre>"; $inCode = false; }
            continue;
        }

        if ($inCode) {
            $html .= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "\n";
            continue;
        }

        if ($trim === '') {
            if ($inUl) { $html .= "</ul>"; $inUl = false; }
            if ($inOl) { $html .= "</ol>"; $inOl = false; }
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.*)$/u', $trim, $m)) {
            if ($inUl) { $html .= "</ul>"; $inUl = false; }
            if ($inOl) { $html .= "</ol>"; $inOl = false; }
            $lv = strlen($m[1]);
            $t = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
            $html .= "<h{$lv}>{$t}</h{$lv}>";
            continue;
        }

        if (preg_match('/^[-*]\s+(.*)$/u', $trim, $m)) {
            if ($inOl) { $html .= "</ol>"; $inOl = false; }
            if (!$inUl) { $html .= "<ul>"; $inUl = true; }

            $t = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            $t = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $t);
            $t = preg_replace('/\*(.+?)\*/u', '<em>$1</em>', $t);
            $html .= "<li>{$t}</li>";
            continue;
        }

        if (preg_match('/^\d+\.\s+(.*)$/u', $trim, $m)) {
            if ($inUl) { $html .= "</ul>"; $inUl = false; }
            if (!$inOl) { $html .= "<ol>"; $inOl = true; }

            $t = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            $t = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $t);
            $t = preg_replace('/\*(.+?)\*/u', '<em>$1</em>', $t);
            $html .= "<li>{$t}</li>";
            continue;
        }

        if ($inUl) { $html .= "</ul>"; $inUl = false; }
        if ($inOl) { $html .= "</ol>"; $inOl = false; }

        $t = htmlspecialchars($trim, ENT_QUOTES, 'UTF-8');
        $t = preg_replace('/`(.+?)`/u', '<code>$1</code>', $t);
        $t = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $t);
        $t = preg_replace('/\*(.+?)\*/u', '<em>$1</em>', $t);
        $t = preg_replace('/$$(.+?)$$$(.+?)$/u', '<a href="$2" target="_blank" rel="noopener">$1</a>', $t);
        $html .= "<p>{$t}</p>";
    }

    if ($inUl) $html .= "</ul>";
    if ($inOl) $html .= "</ol>";
    if ($inCode) $html .= "</code></pre>";

    return $html;
}

function htmlToText(string $html): string
{
    $text = strip_tags($html);
    $text = preg_replace('/\s+/u', ' ', $text ?? '');
    return trim((string)$text);
}

function formatDate(string|int|null $date): string
{
    if ($date === null || $date === '') return '-';
    $n = (int)$date;
    if ($n <= 0) return '-';
    if ($n > 9999999999) $n = (int)floor($n / 1000); // 毫秒 -> 秒
    return date('Y-m-d H:i:s', $n);
}

$diaries = [];

/** 不扫描目录，直接按固定文件列表读取 */
foreach ($DIARY_FILES as $filePath) {
    $raw = @file_get_contents($filePath);
    if ($raw === false) continue; // 文件不存在就跳过

    [$meta, $markdown] = parseFrontMatter($raw);

    $title = (string)($meta['title'] ?? pathinfo($filePath, PATHINFO_FILENAME));
    $date = (string)($meta['date'] ?? '');
    $weather = (string)($meta['weather'] ?? '-');

    $bodyHtml = markdownToHtml($markdown);
    $bodyText = mb_strtolower(htmlToText($bodyHtml), 'UTF-8');

    $diaries[] = [
        'title' => $title,
        'date' => $date,
        'weather' => $weather,
        'bodyHtml' => $bodyHtml,
        'bodyText' => $bodyText
    ];
}

// 按日期倒序
usort($diaries, function ($a, $b) {
    return ((int)$b['date']) <=> ((int)$a['date']);
});

// 搜索（标题 + 正文）
if ($query !== '') {
    $q = mb_strtolower($query, 'UTF-8');
    $diaries = array_values(array_filter($diaries, function ($d) use ($q) {
        $title = mb_strtolower((string)$d['title'], 'UTF-8');
        return str_contains($title, $q) || str_contains((string)$d['bodyText'], $q);
    }));
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>我的日记</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($CSS_PATH, ENT_QUOTES, 'UTF-8'); ?>" />
</head>
<body>
<div class="bg"></div>
<main class="container">
    <header class="site-header">
        <h1>我的日记</h1>
        <p>记录平凡日常里的小确幸</p>
        <form class="search-wrap" method="get" action="">
            <input
                type="text"
                name="q"
                placeholder="搜索标题或正文关键词..."
                value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>"
            />
        </form>
    </header>

    <section class="diary-list">
        <?php if (count($diaries) === 0): ?>
            <div class="empty">
                <?php echo $query !== ''
                    ? '未找到与“' . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . '”相关的日记'
                    : '暂无日记内容'; ?>
            </div>
        <?php else: ?>
            <?php foreach ($diaries as $d): ?>
                <article class="diary-card">
                    <h2 class="diary-title"><?php echo htmlspecialchars($d['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <div class="meta">
                        <span>📅 日期：<?php echo htmlspecialchars(formatDate($d['date']), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span>⛅ 天气：<?php echo htmlspecialchars($d['weather'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="body"><?php echo $d['bodyHtml']; ?></div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
