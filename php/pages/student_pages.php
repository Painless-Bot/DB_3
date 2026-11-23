<?php
/** 학생 화면: 대시보드, 성적, 졸업요건, 장학금, 성적 가져오기, 수강 계획, 진단서 */
defined('APP_ROOT') || exit;

function page_dashboard()
{
    require_role('student');
    render('student/dashboard', ['r' => load_report(my_student())]);
}

function page_grades()
{
    require_role('student');
    render('student/grades', ['r' => load_report(my_student())]);
}

function page_graduation()
{
    require_role('student');
    render('student/graduation', ['r' => load_report(my_student())]);
}

function page_scholarships()
{
    require_role('student');
    render('student/scholarships', ['r' => load_report(my_student())]);
}

function page_report()
{
    require_role('student');
    render_report(load_report(my_student()));
}

/** 인쇄용 진단서 (레이아웃 없이 한 장) */
function render_report(array $r)
{
    $back = $_SESSION['role'] === 'admin' ? url('admin/student', ['id' => $r['info']['student_id']]) : url('me');
    include APP_ROOT . '/views/report.php';
    exit;
}

// ---------------------------------------------------------------- 성적 가져오기
/** 과목명 비교용: 공백·괄호 표기 차이, 'sw심화' 접두어를 무시한다 */
function name_key($name)
{
    $n = mb_strtolower(preg_replace('/\s+/u', '', (string)$name));
    $n = preg_replace('/[(\[]?sw심화[)\]]?/u', '', $n);
    return str_replace(['（', '）'], ['(', ')'], $n);
}

const TERM_ORDER = [1 => 1, 3 => 2, 2 => 3, 4 => 4];

/** 붙여 넣은 행을 COURSE 와 맞춘다. 없는 과목은 새로 만들 과목으로 표시한다. 반환: [rows, problems] */
function resolve_rows(array $rows, $dept_id)
{
    $by_id = [];
    $by_name = [];
    foreach (q_all(Q_ALL_COURSES) as $c) {
        $c['new'] = false;
        $by_id[$c['course_id']] = $c;
        $by_name[name_key($c['course_name'])] = $c;
    }
    $area_names = [];
    foreach (q_all(Q_AREAS) as $a) {
        $area_names[$a['area_code']] = $a['area_name'];
    }
    $out = [];
    $problems = [];
    foreach ($rows as $r) {
        $c = ($r['course_id'] && isset($by_id[$r['course_id']])) ? $by_id[$r['course_id']] : null;
        if (!$c) {
            $c = $by_name[name_key($r['course_name'])] ?? null;
        }
        if ($c) {
            $item = array_merge($r, ['course_id' => $c['course_id'], 'course_name' => $c['course_name'],
                'credits' => $c['credits'], 'area_code' => $c['area_code'], 'new' => $c['new'],
                'dept_id' => $c['dept_id'] ?? null]);
            if ($r['credits'] !== null && $r['credits'] != $c['credits']) {
                $item['warn'] = "붙여 넣은 학점 {$r['credits']} 대신 등록된 학점 {$c['credits']} 사용";
            }
        } else {
            if ($r['credits'] === null) {
                $problems[] = "{$r['line']}번째 줄 '{$r['course_name']}': 처음 보는 과목인데 학점이 없어 건너뜁니다.";
                continue;
            }
            $cid = $r['course_id'] ?: ('U' . strtoupper(substr(md5(name_key($r['course_name'])), 0, 7)));
            $area = $r['area_code'];
            $how = null;
            if (!$area) {
                [$area, $how] = imp_area_from_code($r['course_id']);
            }
            if (!$area) {
                [$area, $how] = ['FE', '이수구분을 알 수 없어 일반선택으로 넣음'];
            }
            $item = array_merge($r, ['course_id' => substr($cid, 0, 10), 'area_code' => $area, 'new' => true,
                'dept_id' => in_array($area, ['MR', 'ME'], true) ? $dept_id : null]);
            if ($how) {
                $item['warn'] = $how;
            }
            $by_name[name_key($r['course_name'])] = $item;   // 같은 새 과목이 두 번 나오면 하나로
            $by_id[$item['course_id']] = $item;
        }
        $item['area_name'] = $area_names[$item['area_code']] ?? $item['area_code'];
        $out[$item['course_id'] . '|' . $item['year'] . '|' . $item['term']] = $item;
    }
    $out = array_values($out);
    usort($out, function ($a, $b) {
        return [$a['year'], TERM_ORDER[$a['term']], $a['course_name']] <=> [$b['year'], TERM_ORDER[$b['term']], $b['course_name']];
    });
    return [$out, $problems];
}

/** 미리보기용 합계: 재수강은 가장 최근 성적 1건만, P/NP 는 평점에서 뺀다 (DB 뷰와 같은 규칙) */
function import_totals(array $rows)
{
    $gp = ['A+' => 4.5, 'A0' => 4.0, 'B+' => 3.5, 'B0' => 3.0, 'C+' => 2.5, 'C0' => 2.0, 'D+' => 1.5, 'D0' => 1.0, 'F' => 0.0];
    $sorted = $rows;
    usort($sorted, function ($a, $b) {
        return [$a['year'], TERM_ORDER[$a['term']]] <=> [$b['year'], TERM_ORDER[$b['term']]];
    });
    $latest = [];
    foreach ($sorted as $r) {
        $latest[$r['course_id']] = $r;
    }
    $credits = 0;
    $pts = 0;
    $crs = 0;
    foreach ($latest as $r) {
        if (!in_array($r['grade'], ['F', 'NP'], true)) {
            $credits += $r['credits'];
        }
        if (isset($gp[$r['grade']])) {
            $pts += $gp[$r['grade']] * $r['credits'];
            $crs += $r['credits'];
        }
    }
    return ['credits' => $credits, 'gpa' => $crs ? round($pts / $crs + 1e-9, 2) : null,
            'retakes' => count($rows) - count($latest)];
}

function page_import()
{
    require_role('student');
    require_once APP_ROOT . '/lib/importer.php';
    $sid = my_student();
    $info = q_one(Q_STUDENT_INFO, ['sid' => $sid]);
    $current = q_one(Q_ENROLLMENT_COUNT, ['sid' => $sid])['n'];
    $text = post('text');
    if (is_post() && !empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        try {
            $text = imp_read_upload($_FILES['file']['tmp_name']);
        } catch (InvalidArgumentException $e) {
            flash($e->getMessage(), 'danger');
            redirect(url('me/import'));
        }
    }
    if (!is_post() || trim($text) === '') {
        if (is_post()) {
            flash('붙여 넣은 내용이나 CSV 파일이 없습니다.', 'warning');
        }
        render('student/import', ['info' => $info, 'text' => '', 'rows' => null, 'problems' => [], 'current' => $current]);
    }

    [$rows, $problems, $summary] = imp_parse($text);
    [$rows, $more] = resolve_rows($rows, $info['dept_id']);
    $problems = array_merge($problems, $more);
    $check = import_totals($rows);
    $mode = post('mode', 'replace') === 'merge' ? 'merge' : 'replace';

    if (post('action') !== 'save' || !$rows) {
        render('student/import', compact('info', 'text', 'rows', 'problems', 'summary', 'check', 'mode', 'current'));
    }

    // 저장: 새 과목 등록 + 성적 반영을 한 트랜잭션으로 (중간에 실패하면 ROLLBACK)
    try {
        if ($mode === 'replace') {
            q_exec(Q_ENROLLMENT_DELETE_ALL, ['sid' => $sid]);   // 트리거가 학기 기록도 지운다
        }
        foreach ($rows as $r) {
            if ($r['new'] && !q_one(Q_COURSE_EXISTS, ['cid' => $r['course_id']])) {
                q_exec(Q_COURSE_INSERT, ['cid' => $r['course_id'], 'name' => mb_substr($r['course_name'], 0, 60),
                    'credits' => $r['credits'], 'area' => $r['area_code'], 'dept' => $r['dept_id'] ?? null]);
            }
            foreach ($r['groups'] ?? [] as $g) {
                q_exec(Q_COURSE_GROUP_ADD, ['cid' => $r['course_id'], 'grp' => $g]);
            }
            q_exec(Q_ENROLLMENT_UPSERT, ['sid' => $sid, 'cid' => $r['course_id'], 'yr' => $r['year'],
                'tm' => $r['term'], 'grade' => $r['grade']]);
        }
        db_commit();
    } catch (DbError $e) {
        db_rollback();
        throw $e;
    }
    $new_courses = count(array_filter($rows, function ($r) { return $r['new']; }));
    flash('성적 ' . count($rows) . '건을 저장했습니다' . ($new_courses ? " (새 과목 {$new_courses}개 등록)" : '')
        . '. 졸업요건과 장학금 판정이 바로 다시 계산되었습니다.', 'success');
    redirect(url('me'));
}

// ---------------------------------------------------------------- 수강 계획 시뮬레이터
function next_regular_term($sid, $admission_year)
{
    $last = q_one(Q_LAST_TERM, ['sid' => $sid]);
    if (!$last) {
        return [$admission_year, 1];
    }
    // 마지막 학기 → 다음 정규학기 (연도 증가, 학기)
    $next = [1 => [0, 2], 3 => [0, 2], 2 => [1, 1], 4 => [0, 1]];
    [$dy, $t] = $next[$last['term']];
    return [$last['year'] + $dy, $t];
}

/** 판정 뷰 결과 한 묶음. 가상 수강을 넣은 상태와 넣기 전 상태를 비교하는 데 쓴다. */
function plan_snapshot($sid, $year, $term)
{
    $p = ['sid' => $sid];
    $key = function (array $rows, $k) {
        $o = [];
        foreach ($rows as $r) {
            $o[$r[$k]] = $r;
        }
        return $o;
    };
    return [
        'status' => q_one(Q_GRAD_STATUS, $p),
        'areas' => $key(q_all(Q_GRAD_AREA_STATUS, $p), 'area_code'),
        'groups' => $key(q_all(Q_GRAD_GROUP_STATUS, $p), 'group_code'),
        'missing' => q_all(Q_MISSING_REQUIRED, $p),
        'sch' => $key(q_all(Q_SCHOLARSHIPS, $p), 'sch_id'),
        'sem' => q_one(Q_SEMESTER_ONE, ['sid' => $sid, 'yr' => $year, 'tm' => $term]),
    ];
}

function page_plan()
{
    require_role('student');
    $sid = my_student();
    $info = q_one(Q_STUDENT_INFO, ['sid' => $sid]);
    [$ny, $nt] = next_regular_term($sid, $info['admission_year']);
    $year = to_int($_REQUEST['year'] ?? '') ?: $ny;
    $term = to_int($_REQUEST['term'] ?? '') ?: $nt;
    if (!in_array($term, [1, 2, 3, 4], true) || $year < 2000 || $year > 2100) {
        [$year, $term] = [$ny, $nt];
    }
    $report = load_report($sid);
    $candidates = q_all(Q_PLAN_CANDIDATES, ['sid' => $sid, 'dept' => $info['dept_id']]);
    $cand = [];
    foreach ($candidates as $c) {
        $cand[$c['course_id']] = $c;
    }
    $grades = array_column(q_all(Q_GRADE_SCALE), 'grade');
    $chosen = array_values(array_filter((array)($_POST['course'] ?? []), function ($c) use ($cand) {
        return is_string($c) && isset($cand[$c]);
    }));
    $picks = [];
    foreach ($chosen as $c) {
        $g = post('g_' . $c);
        $picks[$c] = in_array($g, $grades, true) ? $g : 'A0';
    }

    $result = null;
    if (is_post() && $picks) {
        $before = plan_snapshot($sid, $year, $term);
        try {
            // 같은 트랜잭션 안에서 가상 수강을 넣으면 트리거·뷰가 실제와 똑같이 다시 판정한다
            foreach ($picks as $cid => $grade) {
                q_exec(Q_PLAN_INSERT, ['sid' => $sid, 'cid' => $cid, 'yr' => $year, 'tm' => $term, 'grade' => $grade]);
            }
            $after = plan_snapshot($sid, $year, $term);
        } catch (DbError $e) {
            db_rollback();
            if ($e->ora_code === 1) {   // ORA-00001 UNIQUE 위반: 같은 학기에 이미 들은 과목
                flash('이미 같은 학기에 들은 과목이 있어 계산하지 못했습니다. 학기를 바꿔 보세요.', 'warning');
                redirect(url('me/plan'));
            }
            throw $e;
        }
        db_rollback();   // 무엇을 했든 DB 에는 남기지 않는다
        $credits = 0;
        foreach ($picks as $cid => $_) {
            $credits += $cand[$cid]['credits'];
        }
        $result = ['before' => $before, 'after' => $after, 'credits' => $credits];
    } elseif (is_post()) {
        flash('계획에 넣을 과목을 하나 이상 고르세요.', 'warning');
    }

    $suggested = [];
    foreach ($report['suggestions'] as $s) {
        $suggested[$s['course_id']] = $s;
    }
    render('student/plan', ['r' => $report, 'candidates' => $candidates, 'suggested' => $suggested, 'cand' => $cand,
        'grades' => $grades, 'picks' => $picks, 'year' => $year, 'term' => $term, 'result' => $result]);
}
