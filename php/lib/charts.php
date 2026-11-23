<?php
/** 서버에서 그리는 SVG 차트 (외부 라이브러리 없이 동작). 함수는 HTML 문자열을 돌려준다. */
defined('APP_ROOT') || exit;

function f1($v)
{
    return number_format((float)$v, 1, '.', '');
}

/** 학기별 평점 추이 + 취득학점 막대. $sems = SEMESTER_RECORD 행 목록 */
function chart_gpa_trend(array $sems, $height = 230, $label_terms = true)
{
    $n = count($sems);
    if ($n === 0) {
        return '<div class="chart-empty">수강 이력이 없습니다.</div>';
    }
    $W = 680; $H = $height; $L = 34; $R = 12; $T = 22; $B = 30;
    $pw = $W - $L - $R; $ph = $H - $T - $B;
    $step = $pw / $n;
    $gmin = 1.5; $gmax = 4.5;
    $season = [1 => '1', 2 => '2', 3 => '여름', 4 => '겨울'];
    $o = '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="학기별 평점 추이">';
    foreach ([2.0, 2.5, 3.0, 3.5, 4.0, 4.5] as $gv) {
        $y = $T + $ph - ($gv - $gmin) / ($gmax - $gmin) * $ph;
        $o .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . f1($y) . '" y2="' . f1($y) . '" class="grid"/>';
        $o .= '<text x="' . ($L - 6) . '" y="' . f1($y + 4) . '" class="axis" text-anchor="end">' . f1($gv) . '</text>';
    }
    $pts = [];
    foreach (array_values($sems) as $i => $s) {
        $cx = $L + $step * $i + $step / 2;
        $bh = min($s['earned_credits'], 24) / 24 * $ph * 0.55;
        $bw = min($step * 0.64, 32);
        $o .= '<rect x="' . f1($cx - $bw / 2) . '" y="' . f1($T + $ph - $bh) . '" width="' . f1($bw) . '" height="' . f1($bh)
            . '" rx="4" class="bar' . (in_array($s['term'], [3, 4]) ? ' seasonal' : '') . '">'
            . '<title>' . h($s['year'] . '년 ' . term_label($s['term']) . ' 취득 ' . $s['earned_credits'] . '학점') . '</title></rect>';
        if ($label_terms) {
            $o .= '<text x="' . f1($cx) . '" y="' . ($H - 10) . '" class="axis" text-anchor="middle">'
                . ($s['year'] % 100) . '-' . $season[$s['term']] . '</text>';
        }
        if ($s['gpa'] !== null) {
            $y = $T + $ph - (min(max((float)$s['gpa'], $gmin), $gmax) - $gmin) / ($gmax - $gmin) * $ph;
            $pts[] = [$cx, $y, $s];
        }
    }
    $d = '';
    foreach ($pts as [$cx, $y]) {
        $d .= ($d === '' ? 'M' : ' L') . f1($cx) . ',' . f1($y);
    }
    $o .= '<path d="' . $d . '" class="line"/>';
    foreach ($pts as [$cx, $y, $s]) {
        $o .= '<circle cx="' . f1($cx) . '" cy="' . f1($y) . '" r="4.5" class="dot"><title>'
            . h($s['year'] . '년 ' . term_label($s['term']) . ' 평점 ' . gpa($s['gpa'])) . '</title></circle>';
        if ($n <= 12) {
            $o .= '<text x="' . f1($cx) . '" y="' . f1($y - 10) . '" class="val" text-anchor="middle">' . gpa($s['gpa']) . '</text>';
        }
    }
    $o .= '</svg>';
    $o .= '<div class="chart-legend"><span class="lg-line"></span>학기 평점 <span class="lg-bar"></span>취득학점 <span class="lg-bar seasonal"></span>계절학기</div>';
    return $o;
}

/** 가로 막대 목록. $items = [[label, value, sub], ...] */
function chart_hbars(array $items, $max_value = null, $unit = '', $tone = '')
{
    $mx = $max_value ?: ($items ? max(array_column($items, 1)) : 1) ?: 1;
    $o = '<div class="hbars ' . h($tone) . '">';
    if (!$items) {
        $o .= '<div class="chart-empty">해당 학생이 없습니다.</div>';
    }
    foreach ($items as [$label, $value, $sub]) {
        $o .= '<div class="hbar"><div class="hbar-label">' . h($label) . ($sub ? ' <span class="text-muted">' . h($sub) . '</span>' : '')
            . '</div><div class="hbar-track"><span style="width: ' . ($mx ? round(100 * $value / $mx, 1) : 0) . '%"></span></div>'
            . '<div class="hbar-val">' . h($value . $unit) . '</div></div>';
    }
    return $o . '</div>';
}

/** 세로 막대 (히스토그램). $items = [[label, value], ...] */
function chart_vbars(array $items)
{
    $mx = $items ? max(array_column($items, 1)) : 1;
    $o = '<div class="vbars">';
    foreach ($items as [$label, $value]) {
        $o .= '<div class="vbar"><div class="vbar-col"><span style="height: ' . ($mx ? round(100 * $value / $mx, 1) : 0)
            . '%"><b>' . h($value) . '</b></span></div><div class="vbar-label">' . h($label) . '</div></div>';
    }
    return $o . '</div>';
}

/** 원형 진행률. $pct 0~100 */
function chart_ring($pct, $label, $sub = '', $ok = false, $size = 120)
{
    $r = 46;
    $c = 2 * 3.14159 * $r;
    return '<div class="ring-wrap"><svg class="ring' . ($ok ? ' ok' : '') . '" viewBox="0 0 120 120" width="' . $size . '" height="' . $size
        . '" role="img" aria-label="' . h($label . ' ' . $pct . '%') . '">'
        . '<circle cx="60" cy="60" r="' . $r . '" class="ring-bg"/>'
        . '<circle cx="60" cy="60" r="' . $r . '" class="ring-fg" stroke-dasharray="' . f1($c * $pct / 100) . ' ' . f1($c)
        . '" transform="rotate(-90 60 60)"/>'
        . '<text x="60" y="58" text-anchor="middle" class="ring-num">' . (int)$pct . '%</text>'
        . '<text x="60" y="78" text-anchor="middle" class="ring-sub">' . h($sub) . '</text>'
        . '</svg><div class="ring-label">' . h($label) . '</div></div>';
}
