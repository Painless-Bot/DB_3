<?php
/**
 * 클래스룸 성적표 붙여넣기 / CSV 를 읽어 ENROLLMENT 행으로 바꾼다. (app/importer.py 를 PHP 로 옮긴 것)
 *
 * 클래스룸 성적 조회 화면의 표를 드래그해서 복사하면 칸이 탭으로 구분된 텍스트가 된다.
 * 표 모양을 정확히 알 수 없으므로 다음을 모두 받아들인다.
 *   - 구분자: 탭, 쉼표(CSV), 두 칸 이상 공백
 *   - 머리글: 년도/연도/학년도, 학기, 학수번호/과목코드, 과목명/교과목명, 이수구분, 학점, 등급/성적
 *     머리글 순서는 상관없고, 머리글 줄이 없으면 값 모양으로 칸을 추정한다.
 *   - "2024학년도 1학기" 처럼 학기가 표 위 제목 줄에만 있는 형식
 */
defined('APP_ROOT') || exit;

const IMP_HEADER_ALIASES = [
    'year' => ['년도', '연도', '학년도', '수강년도', '수강연도', '이수년도', 'year'],
    'term' => ['학기', '수강학기', '이수학기', 'term', 'semester'],
    'course_id' => ['학수번호', '과목코드', '교과목번호', '과목번호', '코드', 'code'],
    'course_name' => ['과목명', '교과목명', '교과목', '과목', '강좌명', 'name'],
    'area' => ['이수구분', '구분', '이수영역', '영역', 'area'],
    'credits' => ['학점', '이수학점', 'credit', 'credits'],
    'grade' => ['등급', '성적', '평가', 'grade'],
];

const IMP_GRADE_MAP = ['A+' => 'A+', 'A0' => 'A0', 'A' => 'A0', 'B+' => 'B+', 'B0' => 'B0', 'B' => 'B0',
    'C+' => 'C+', 'C0' => 'C0', 'C' => 'C0', 'D+' => 'D+', 'D0' => 'D0', 'D' => 'D0',
    'F' => 'F', 'P' => 'P', 'NP' => 'NP', 'S' => 'P', 'U' => 'NP', 'PASS' => 'P', 'FAIL' => 'NP'];

const IMP_TERM_WORDS = ['1' => 1, '2' => 2, '1학기' => 1, '2학기' => 2, '여름' => 3, '하계' => 3, '여름학기' => 3,
    '하계계절' => 3, '겨울' => 4, '동계' => 4, '겨울학기' => 4, '동계계절' => 4];

// 이수구분 글자 → AREA 코드 (앞에서부터 먼저 맞는 것)
const IMP_AREA_RULES = [
    ['전필', 'MR'], ['전공필수', 'MR'], ['전선', 'ME'], ['전공선택', 'ME'],
    ['전기', 'EN'], ['전공기초', 'EN'], ['전공', 'ME'],
    ['msc수학', 'MM'], ['수학', 'MM'], ['msc과학', 'MP'], ['과학', 'MP'], ['msc전산', 'MC'], ['전산', 'MC'],
    ['msc', 'MS'],
    ['특성화', 'SL'], ['전공기초영어', 'EN'], ['기초영어', 'EN'],
    ['기초교양', 'BL'], ['교필', 'BL'], ['기교', 'BL'], ['핵심', 'CL'], ['핵교', 'CL'],
    ['일반교양', 'GL'], ['일교', 'GL'], ['교선', 'GL'], ['교양', 'GL'],
    ['일선', 'FE'], ['일반선택', 'FE'], ['자유', 'FE'],
];

// 전문교양 영역 번호 → 졸업요건 과목군 (4영역 예술과디자인, 5영역 제2외국어와한문)
const IMP_AREA_NO_GROUP = [4 => 'ART_DESIGN', 5 => 'LANG_HANMUN'];
const IMP_LANG_NAME = '/(프랑스어|독일어|일본어|중국어|스페인어|러시아어|이탈리아어|라틴어|아랍어|베트남어|한문)/u';
// 과목명 뒤 '*sw(심화)' 같은 SW/데이터활용역량 표기 → 인정되는 과목군
const IMP_SWD_TAG = '/\s*\*?\s*sw\s*\(\s*(심화|기초|소양)\s*\)\s*/iu';
const IMP_SWD_GROUPS = ['심화' => ['SWD_1_ADV', 'SWD_2_BASIC', 'SWD_3_TOTAL'],
    '기초' => ['SWD_2_BASIC', 'SWD_3_TOTAL'], '소양' => ['SWD_3_TOTAL']];
// 클래스룸 졸업요건조회 아래 요약표의 합계 줄 (검증용)
const IMP_SUMMARY_KEYS = ['취득학점' => 'credits', '평점' => 'gpa', '전공평점' => 'major_gpa'];

const IMP_SEM_TITLE = '/(20\d\d)\s*(?:학년도|년도|년)?\s*(?:\d\s*학년\s*)?[-.\/]?\s*(1|2|여름|겨울|하계|동계)\s*(?:학기|계절)?/u';
const IMP_GRADE_RE = '/^(A\+|A0|A|B\+|B0|B|C\+|C0|C|D\+|D0|D|F|P|NP|S|U)$/i';

// 클래스룸 성적표에는 이수구분 칸이 없다. 홍익대 학수번호 앞자리로 이수구분을 짐작한다.
const IMP_CODE_AREA = [['001', 'BL'], ['002', 'GL'], ['004', 'CL'], ['007', 'EN'], ['008', 'SL'],
    ['0121', 'MP'], ['0122', 'MM'], ['0123', 'MC'], ['1018', 'MC']];

function imp_norm($s)
{
    return mb_strtolower(preg_replace('/\s+/u', '', (string)$s));
}

/** 과목명 표기와 이수구분 글자로 알 수 있는 과목군 목록 */
function imp_course_groups($name, $area_text)
{
    $groups = [];
    if (preg_match(IMP_SWD_TAG, (string)$name, $m)) {
        $groups = array_merge($groups, IMP_SWD_GROUPS[$m[1]]);
    }
    $a = preg_match('/^(?:일교|핵교)(\d)$/u', imp_norm($area_text), $am);
    if ($a && isset(IMP_AREA_NO_GROUP[(int)$am[1]])) {
        $groups[] = IMP_AREA_NO_GROUP[(int)$am[1]];
    }
    if (!$a && preg_match(IMP_LANG_NAME, (string)$name) && !preg_match('/전공|전필|전선/u', (string)$area_text)) {
        $groups[] = 'LANG_HANMUN';
    }
    return $groups;
}

function imp_clean_name($name)
{
    return trim(preg_replace(IMP_SWD_TAG, '', (string)$name));
}

function imp_area_code($text)
{
    $t = imp_norm($text);
    foreach (IMP_AREA_RULES as [$key, $code]) {
        if ($t !== '' && mb_strpos($t, $key) !== false) {
            return $code;
        }
    }
    return null;
}

/** 반환: [area_code, 짐작 설명] */
function imp_area_from_code($code)
{
    if (!$code || !ctype_digit($code)) {
        return [null, null];
    }
    foreach (IMP_CODE_AREA as [$prefix, $area]) {
        if (strpos($code, $prefix) === 0) {
            return [$area, '학수번호로 이수구분을 짐작함'];
        }
    }
    if ($code[0] !== '0') {
        return ['ME', '전공 과목으로 보고 전공선택으로 넣음'];
    }
    return [null, null];
}

function imp_split_line($line, $delim)
{
    if ($delim === ',') {
        return str_getcsv($line);
    }
    if ($delim === "\t") {
        return array_map('trim', explode("\t", $line));
    }
    return array_map('trim', preg_split('/\s{2,}/u', trim($line)));
}

function imp_guess_delim(array $lines)
{
    $first = array_slice($lines, 0, 20);
    $sample = implode("\n", $first);
    if (strpos($sample, "\t") !== false) {
        return "\t";
    }
    if (substr_count($sample, ',') >= count($first)) {
        return ',';
    }
    return '  ';
}

/** 머리글 줄이면 [필드 => 칸 번호] 를 돌려준다 */
function imp_find_header(array $cells)
{
    $found = [];
    foreach ($cells as $i => $c) {
        $n = imp_norm($c);
        foreach (IMP_HEADER_ALIASES as $field => $names) {
            if (isset($found[$field])) {
                continue;
            }
            foreach ($names as $a) {
                if ($n === imp_norm($a)) {
                    $found[$field] = $i;
                    break 2;
                }
            }
        }
    }
    // 과목명 + 학점 + 등급이 있으면 머리글로 본다
    if (isset($found['course_name'], $found['credits'], $found['grade'])) {
        return $found;
    }
    return null;
}

/** 머리글이 없을 때 값 모양으로 칸을 추정한다 */
function imp_guess_columns(array $cells)
{
    $cols = [];
    $texts = [];
    foreach ($cells as $i => $c) {
        $v = trim($c);
        if ($v === '') {
            continue;
        }
        if (!isset($cols['grade']) && preg_match(IMP_GRADE_RE, $v)) {
            $cols['grade'] = $i;
        } elseif (!isset($cols['year']) && preg_match('/^20\d\d$/', $v)) {
            $cols['year'] = $i;
        } elseif (!isset($cols['term']) && isset($cols['year']) && isset(IMP_TERM_WORDS[imp_norm($v)])) {
            $cols['term'] = $i;
        } elseif (!isset($cols['course_id']) && preg_match('/^[0-9A-Z]{5,8}$/', $v)) {
            $cols['course_id'] = $i;
        } elseif (!isset($cols['credits']) && preg_match('/^[0-6](\.0)?$/', $v)) {
            $cols['credits'] = $i;
        } elseif (preg_match('/[가-힣A-Za-z]/u', $v)) {
            $texts[] = [$i, $v];
        }
    }
    // 짧고 이수구분처럼 생긴 칸 = 이수구분, 나머지 중 가장 긴 칸 = 과목명
    foreach ($texts as [$i, $v]) {
        if (mb_strlen($v) <= 6 && imp_area_code($v) && !isset($cols['area']) && count($texts) > 1) {
            $cols['area'] = $i;
        }
    }
    $best = null;
    foreach ($texts as [$i, $v]) {
        if (isset($cols['area']) && $i === $cols['area']) {
            continue;
        }
        if ($best === null || mb_strlen($v) > mb_strlen($best[1])) {
            $best = [$i, $v];
        }
    }
    if ($best) {
        $cols['course_name'] = $best[0];
    }
    if (isset($cols['course_name'], $cols['grade'])) {
        return $cols;
    }
    return null;
}

function imp_term_word($w)
{
    return IMP_TERM_WORDS[$w] ?? null;
}

/**
 * 반환: [rows, problems, summary]. summary 는 클래스룸 요약표의 취득학점·평점(있을 때).
 * rows 는 [year, term, course_id, course_name, groups, area_text, area_code, credits, grade, line] 배열.
 */
function imp_parse($text)
{
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", (string)$text));
    $nonblank = array_values(array_filter($lines, function ($l) { return trim($l) !== ''; }));
    if (!$nonblank) {
        return [[], ['붙여 넣은 내용이 없습니다.'], []];
    }
    $delim = imp_guess_delim($nonblank);
    $header = null;
    $cur_year = $cur_term = null;
    $rows = [];
    $problems = [];
    $summary = [];
    $in_summary = false;

    foreach ($lines as $idx => $line) {
        $no = $idx + 1;
        if (trim($line) === '') {
            continue;
        }
        $cells = imp_split_line($line, $delim);
        if (mb_strpos($line, '이수구분별') !== false) {   // 졸업요건조회 맨 아래 요약표: 합계만 읽는다
            $in_summary = true;
        }
        if ($in_summary) {
            $vals = array_values(array_filter($cells, function ($c) { return $c !== ''; }));
            if (count($vals) >= 2 && isset(IMP_SUMMARY_KEYS[imp_norm($vals[0])])
                && preg_match('/^\d+(\.\d+)?/', $vals[1], $num)) {
                $key = IMP_SUMMARY_KEYS[imp_norm($vals[0])];
                if (!isset($summary[$key])) {
                    $summary[$key] = (float)$num[0];
                }
            }
            continue;
        }
        $h = imp_find_header($cells);
        if ($h) {
            $header = $h;
            continue;
        }
        // "2024학년도 1학기" 같은 제목 줄
        $filled = count(array_filter($cells, function ($c) { return $c !== ''; }));
        if (preg_match(IMP_SEM_TITLE, $line, $m) && $filled <= 3) {
            $cur_year = (int)$m[1];
            $cur_term = imp_term_word($m[2]) ?? imp_term_word($m[2] . '학기');
            continue;
        }
        $cols = $header ?: imp_guess_columns($cells);
        if (!$cols) {
            continue;   // 합계 줄, 안내 문구 등
        }
        $get = function ($field) use ($cols, $cells) {
            $i = $cols[$field] ?? null;
            return ($i !== null && $i < count($cells)) ? trim($cells[$i]) : '';
        };

        $grade_raw = str_replace(' ', '', strtoupper($get('grade')));
        $grade = IMP_GRADE_MAP[$grade_raw] ?? null;
        $name = $get('course_name');
        if ($name === '' || !$grade) {
            if ($name !== '' && $grade_raw !== '' && !preg_match('/합계|평균|계$/u', $name)) {
                $problems[] = "{$no}번째 줄: 성적 '" . $get('grade') . "' 을(를) 읽지 못해 건너뜁니다.";
            }
            continue;
        }

        $year_raw = $get('year');
        $term_raw = $get('term');
        if (preg_match('/20\d\d/', $year_raw, $ym) || preg_match('/20\d\d/', $term_raw, $ym)) {
            $year = (int)$ym[0];
        } else {
            $year = $cur_year;
        }
        $tm = trim(str_replace((string)($year ?: ''), '', imp_norm($term_raw)), '-./');
        $term = $tm !== '' ? (imp_term_word($tm) ?? imp_term_word(str_replace('학기', '', $tm))) : null;
        if ($term === null) {
            $term = preg_match(IMP_SEM_TITLE, $year_raw . ' ' . $term_raw, $m2) ? imp_term_word($m2[2]) : $cur_term;
        }
        if (!$year || !$term) {
            $problems[] = "{$no}번째 줄 '{$name}': 년도/학기를 알 수 없어 건너뜁니다.";
            continue;
        }

        $cr_raw = $get('credits');
        $credits = is_numeric($cr_raw) ? (int)floor((float)$cr_raw) : null;

        $cid = strtoupper($get('course_id'));
        $rows[] = [
            'year' => $year, 'term' => $term,
            'course_id' => $cid !== '' ? $cid : null,
            'course_name' => imp_clean_name($name),
            'groups' => imp_course_groups($name, $get('area')),
            'area_text' => $get('area'),
            'area_code' => imp_area_code($get('area')),
            'credits' => $credits,
            'grade' => $grade,
            'line' => $no,
        ];
    }
    if (!$rows && !$problems) {
        $problems[] = '성적 행을 찾지 못했습니다. 표의 머리글(과목명, 학점, 등급 등)까지 함께 복사해 주세요.';
    }
    return [$rows, $problems, $summary];
}

/** 업로드한 CSV 를 텍스트로 읽는다. 엑셀에서 저장한 CSV(cp949)도 받는다. */
function imp_read_upload($tmp_path)
{
    $raw = (string)file_get_contents($tmp_path);
    if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        $raw = substr($raw, 3);
    }
    if (mb_check_encoding($raw, 'UTF-8')) {
        return $raw;
    }
    $conv = @mb_convert_encoding($raw, 'UTF-8', 'UHC');   // UHC = CP949 (엑셀 한글 CSV)
    if ($conv !== false && mb_check_encoding($conv, 'UTF-8')) {
        return $conv;
    }
    throw new InvalidArgumentException('파일 인코딩을 읽을 수 없습니다. CSV(UTF-8) 로 저장해 주세요.');
}
