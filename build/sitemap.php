<?php

/**
 * sitemap.php
 * Supabaseから各くじのmax roundを取得し、動的にsitemap.xmlを生成する
 * heteml public_html/ 直下に設置
 */

// 環境変数（~/env/.env_chatorahack_lottery から読み込む）
$envFile = '/home/users/1/gen17/env/.env_chatorahack_lottery';
if (file_exists($envFile)) {
  foreach (file($envFile) as $line) {
    $line = str_replace(["\r\n", "\r", "\n"], '', $line);
    $line = trim($line);
    if (!$line || str_starts_with($line, '#')) continue;
    if (strpos($line, '=') === false) continue;
    [$key, $val] = explode('=', $line, 2);
    putenv(trim($key) . '=' . trim($val, " \t\"'"));
  }
}

$SUPABASE_URL      = getenv('REACT_APP_SUPABASE_CHATORANOTE_URL');
$SUPABASE_ANON_KEY = getenv('REACT_APP_SUPABASE_CHATORANOTE_ANON_KEY');

// くじ設定（lotteryConfig.jsと対応）
$LOTTERY_CONFIG = [
  'lt6'  => ['table' => 'lottery_numlot_lt6', 'path' => '/loto6',    'statsPath' => '/loto6/stats'],
  'lt7'  => ['table' => 'lottery_numlot_lt7', 'path' => '/loto7',    'statsPath' => '/loto7/stats'],
  'mlt'  => ['table' => 'lottery_numlot_mlt', 'path' => '/miniloto', 'statsPath' => '/miniloto/stats'],
  'nm4'  => ['table' => 'lottery_numlot_nm4', 'path' => '/numbers4', 'statsPath' => '/numbers4/stats'],
  'nm3'  => ['table' => 'lottery_numlot_nm3', 'path' => '/numbers3', 'statsPath' => '/numbers3/stats'],
  'bg5'  => ['table' => 'lottery_numlot_bg5', 'path' => '/bingo5',   'statsPath' => '/bingo5/stats'],
  'qcc'  => ['table' => 'lottery_numlot_qcc', 'path' => '/qoochan',  'statsPath' => '/qoochan/stats'],
];

// ベースURL
$BASE_URL = 'https://lottery.chatorahack.com';

// キャッシュ設定（1時間）
$CACHE_FILE = '/home/users/1/gen17/cache/chatorahack_sitemap_cache.xml';
$CACHE_TTL  = 3600;

// キャッシュが有効であれば返す
if (file_exists($CACHE_FILE) && (time() - filemtime($CACHE_FILE)) < $CACHE_TTL) {
  header('Content-Type: application/xml; charset=utf-8');
  readfile($CACHE_FILE);
  exit;
}

/**
 * Supabase REST APIでmax roundを取得
 */
function fetchMaxRound($supabaseUrl, $anonKey, $schema, $table)
{
  $url = $supabaseUrl . '/rest/v1/' . $table . '?select=round&order=round.desc&limit=1';
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'apikey: ' . $anonKey,
      'Authorization: Bearer ' . $anonKey,
      'Accept-Profile: ' . $schema,
    ],
    CURLOPT_TIMEOUT => 10,
  ]);
  $res  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($code !== 200 || !$res) return null;
  $data = json_decode($res, true);
  return isset($data[0]['round']) ? (int)$data[0]['round'] : null;
}

// 静的URL一覧
$staticUrls = [
  ['loc' => $BASE_URL . '/',          'priority' => '1.0', 'changefreq' => 'daily'],
  ['loc' => $BASE_URL . '/about',     'priority' => '0.3', 'changefreq' => 'monthly'],
  ['loc' => $BASE_URL . '/privacy',   'priority' => '0.3', 'changefreq' => 'monthly'],
  ['loc' => $BASE_URL . '/terms',     'priority' => '0.3', 'changefreq' => 'monthly'],
  ['loc' => $BASE_URL . '/news',      'priority' => '0.4', 'changefreq' => 'weekly'],
  ['loc' => $BASE_URL . '/changelog', 'priority' => '0.3', 'changefreq' => 'monthly'],
];

// くじ別の静的URL（一覧・統計）
foreach ($LOTTERY_CONFIG as $key => $cfg) {
  $staticUrls[] = ['loc' => $BASE_URL . $cfg['path'],      'priority' => '0.9', 'changefreq' => 'daily'];
  $staticUrls[] = ['loc' => $BASE_URL . $cfg['statsPath'], 'priority' => '0.7', 'changefreq' => 'weekly'];
}

// 動的URL（詳細ページ）
$dynamicUrls = [];
foreach ($LOTTERY_CONFIG as $key => $cfg) {
  $maxRound = fetchMaxRound($SUPABASE_URL, $SUPABASE_ANON_KEY, 'chatorahack', $cfg['table']);
  if (!$maxRound) continue;
  for ($r = $maxRound; $r >= 1; $r--) {
    $dynamicUrls[] = [
      'loc'        => $BASE_URL . $cfg['path'] . '/detail/' . $r,
      'priority'   => ($r === $maxRound) ? '0.8' : '0.5',
      'changefreq' => 'never',
    ];
  }
}

// XML生成
$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach (array_merge($staticUrls, $dynamicUrls) as $url) {
  $xml .= '  <url>' . "\n";
  $xml .= '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1) . '</loc>' . "\n";
  $xml .= '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n";
  $xml .= '    <priority>' . $url['priority'] . '</priority>' . "\n";
  $xml .= '  </url>' . "\n";
}

$xml .= '</urlset>';

// キャッシュに保存
file_put_contents($CACHE_FILE, $xml);

// 出力
header('Content-Type: application/xml; charset=utf-8');
echo $xml;
