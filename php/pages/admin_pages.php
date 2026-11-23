<?php
/** 학과 담당자 화면. 모든 조회·수정은 담당자 학과(dept_id)로 범위를 제한한다. */
defined('APP_ROOT') || exit;

function admin_dept_name()
{
    static $name = null;
    if ($name === null) {
        $row = q_one(Q_DEPT_NAME, ['dept' => my_dept()]);
        $name = $row ? $row['dept_name'] : my_dept();
    }
    return $name;
}

function check_student($sid)
{
    $row = $sid === '' ? null : q_one(Q_STUDENT_DEPT, ['sid' => $sid]);
    if (!$row || $row['dept_id'] !== my_dept()) {
        abort_page(404);
    }
}

// ---------------------------------------------------------------- 학생 목록·상세
function student_filters()
{
    return [
        'all' => ['전체', function ($r) { return true; }],
        'ok' => ['졸업 가능', function ($r) { return (bool)$r['graduatable']; }],
        'short' => ['영역·과목군 미달', function ($r) { return $r['short_area_count'] + $r['short_group_count'] > 0; }],
        'missing' => ['필수과목 미이수', function ($r) { return $r['missing_required_count'] > 0; }],
        'senior' => ['4학년 이상 미충족', function ($r) { return $r['grade_level'] >= 4 && !$r['graduatable']; }],
        'noreq' => ['요건 미등록', function ($r) { return $r['req_id'] === null; }],
    ];
}

function student_rows()
{
    $q = trim(get_param('q'));
    $f = get_param('f', 'all');
    $filters = student_filters();
    if (!isset($filters[$f])) {
        $f = 'all';
    }
    $rows = q_all(Q_STUDENT_LIST, ['dept' => my_dept(), 'q' => $q]);
    $counts = [];
    foreach ($filters as $k => [$_, $fn]) {
        $counts[$k] = count(array_filter($rows, $fn));
    }
    return [array_values(array_filter($rows, $filters[$f][1])), $q, $f, $counts, $filters];
}

function page_admin_students()
{
    require_role('admin');
    [$rows, $q, $f, $counts, $filters] = student_rows();
    render('admin/students', compact('rows', 'q', 'f', 'counts', 'filters'));
}

/** 현재 검색·필터 조건의 학생 진단 결과를 엑셀에서 바로 열리는 CSV(UTF-8 BOM)로 내려 준다. */
function page_admin_students_csv()
{
    require_role('admin');
    [$rows] = student_rows();
    $name = 'students_' . my_dept() . '_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $name);
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['학번', '이름', '입학연도', '학년', '적용요건', '이수학점', '졸업학점', '남은학점', '누적평점',
        '설계학점', '부족 영역', '부족 과목군', '미이수 필수', '판정']);
    foreach ($rows as $r) {
        $req = $r['req_id'] === null ? '-' : $r['req_from_year'] . '~' . ($r['req_to_year'] ?? '');
        $verdict = $r['req_id'] === null ? '요건 미등록' : ($r['graduatable'] ? '졸업 가능' : '미충족');
        fputcsv($out, [$r['student_id'], $r['name'], $r['admission_year'], $r['grade_level'], $req, $r['earned_total'],
            $r['required_total'] ?? '', $r['required_total'] ? $r['remaining_total'] : '',
            $r['cum_gpa'] !== null ? gpa($r['cum_gpa']) : '', $r['earned_design'],
            $r['short_area_count'], $r['short_group_count'], $r['missing_required_count'], $verdict]);
    }
    fclose($out);
    exit;
}

function page_admin_student()
{
    require_role('admin');
    $sid = get_param('id');
    check_student($sid);
    render('admin/student_detail', ['r' => load_report($sid)]);
}

function page_admin_student_report()
{
    require_role('admin');
    $sid = get_param('id');
    check_student($sid);
    render_report(load_report($sid));
}

// ---------------------------------------------------------------- 학과 통계
function page_admin_stats()
{
    require_role('admin');
    $p = ['dept' => my_dept(), 'q' => ''];
    $rows = q_all(Q_STUDENT_LIST, $p);
    $with_req = array_filter($rows, function ($r) { return $r['req_id'] !== null; });
    $gpas = array_values(array_filter(array_column($rows, 'cum_gpa'), function ($v) { return $v !== null; }));
    $seniors = array_filter($with_req, function ($r) { return $r['grade_level'] >= 4; });
    $kpi = [
        'students' => count($rows),
        'graduatable' => count(array_filter($with_req, function ($r) { return $r['graduatable']; })),
        'seniors' => count($seniors),
        'senior_short' => count(array_filter($seniors, function ($r) { return !$r['graduatable']; })),
        'avg_gpa' => $gpas ? round(array_sum($gpas) / count($gpas), 2) : null,
        'any_sch' => q_one(Q_ANY_SCHOLARSHIP, ['dept' => my_dept()])['n'],
        'noreq' => count($rows) - count($with_req),
    ];
    // 남은 학점 분포 (0, 1~12, 13~30, 31~60, 61+)
    $remaining = [];
    foreach ([['충족', 0, 0], ['1~12', 1, 12], ['13~30', 13, 30], ['31~60', 31, 60], ['61 이상', 61, 999]] as [$label, $lo, $hi]) {
        $remaining[] = [$label, count(array_filter($with_req, function ($r) use ($lo, $hi) {
            return $r['remaining_total'] >= $lo && $r['remaining_total'] <= $hi;
        }))];
    }
    $by_year = [];
    foreach ($rows as $r) {
        $y = &$by_year[$r['admission_year']];
        $y = $y ?? ['n' => 0, 'ok' => 0, 'gpa' => []];
        $y['n']++;
        $y['ok'] += $r['graduatable'] ? 1 : 0;
        if ($r['cum_gpa'] !== null) {
            $y['gpa'][] = $r['cum_gpa'];
        }
        unset($y);
    }
    ksort($by_year);
    $cohorts = [];
    foreach ($by_year as $year => $v) {
        $cohorts[] = ['year' => $year, 'n' => $v['n'], 'ok' => $v['ok'],
            'gpa' => $v['gpa'] ? round(array_sum($v['gpa']) / count($v['gpa']), 2) : null];
    }
    $d = ['dept' => my_dept()];
    render('admin/stats', [
        'kpi' => $kpi, 'remaining' => $remaining, 'cohorts' => $cohorts,
        'areas' => q_all(Q_AREA_SHORTAGE, $p), 'groups' => q_all(Q_GROUP_SHORTAGE, $p),
        'sch' => q_all(Q_SCHOLARSHIP_COUNTS, $d), 'hist' => q_all(Q_LAST_GPA_HISTOGRAM, $d),
        'trend' => q_all(Q_DEPT_TERM_TREND, $d),
    ]);
}

// ---------------------------------------------------------------- 성적 수정 (FR-05)
function page_admin_grade_edit()
{
    require_role('admin');
    $id = to_int(get_param('id'));
    $e = $id === null ? null : q_one(Q_ENROLLMENT_ONE, ['id' => $id]);
    if (!$e || $e['dept_id'] !== my_dept()) {
        abort_page(404);
    }
    $e['student_id'] = trim($e['student_id']);
    $grades = q_all(Q_GRADE_SCALE);

    if (is_post()) {
        $new = post('grade');
        if (!in_array($new, array_column($grades, 'grade'), true)) {
            flash('등급을 선택하세요.', 'danger');
            redirect(current_url());
        }
        $sid = $e['student_id'];
        $sem = ['sid' => $sid, 'yr' => $e['year'], 'tm' => $e['term']];
        $before = q_one(Q_SEMESTER_ONE, $sem);
        $before_sch = array_column(q_all(Q_ELIGIBLE_NAMES, ['sid' => $sid]), 'sch_name');
        $before_grad = q_one(Q_GRAD_FLAG, ['sid' => $sid]);

        q_exec(Q_UPDATE_GRADE, ['grade' => $new, 'id' => $id]);   // 트리거가 SEMESTER_RECORD 재계산
        db_commit();

        $after = q_one(Q_SEMESTER_ONE, $sem);
        $after_sch = array_column(q_all(Q_ELIGIBLE_NAMES, ['sid' => $sid]), 'sch_name');
        $after_grad = q_one(Q_GRAD_FLAG, ['sid' => $sid]);
        $msg = "{$e['course_name']} 성적을 {$e['grade']} → {$new}(으)로 바꿨습니다. "
            . "{$e['year']}년 {$e['term']}학기 평점 " . gpa($before['gpa'] ?? null) . ' → ' . gpa($after['gpa'] ?? null)
            . ', 취득학점 ' . ($before['earned_credits'] ?? 0) . ' → ' . ($after['earned_credits'] ?? 0) . '.';
        if ($before_grad['remaining_total'] != $after_grad['remaining_total']) {
            $msg .= " 남은 졸업학점 {$before_grad['remaining_total']} → {$after_grad['remaining_total']}.";
        }
        if ((bool)$before_grad['graduatable'] !== (bool)$after_grad['graduatable']) {
            $msg .= ' 졸업 판정: ' . ($after_grad['graduatable'] ? '졸업 가능으로 바뀜.' : '미충족으로 바뀜.');
        }
        $gained = array_diff($after_sch, $before_sch);
        $lost = array_diff($before_sch, $after_sch);
        sort($gained);
        sort($lost);
        if ($gained) {
            $msg .= ' 새로 신청 가능: ' . implode(', ', $gained) . '.';
        }
        if ($lost) {
            $msg .= ' 신청 불가로 바뀜: ' . implode(', ', $lost) . '.';
        }
        flash($msg, 'success');
        redirect(url('admin/student', ['id' => $sid]));
    }
    render('admin/grade_edit', ['e' => $e, 'grades' => $grades]);
}

// ---------------------------------------------------------------- 졸업요건 관리 (FR-06)
const OVERLAP_MSG = '입학연도 구간이 이 학과의 다른 졸업요건과 겹칩니다.';

function is_overlap(DbError $e)
{
    return strpos($e->getMessage(), 'GRAD_REQ_OVERLAP') !== false;
}

/** 반환: [from_year, to_year] 또는 잘못된 값이면 null */
function read_years()
{
    $from = to_int(post('from_year'));
    $raw_to = trim(post('to_year'));
    $to = $raw_to === '' ? null : to_int($raw_to);
    if ($from === null || ($raw_to !== '' && $to === null) || ($to !== null && $to < $from)) {
        return null;
    }
    return [$from, $to];
}

function req_years_label($f, $t)
{
    return $t ? "{$f}~{$t}학번" : "{$f}학번 이후";
}

function page_admin_grad_reqs()
{
    require_role('admin');
    if (is_post()) {
        $years = read_years();
        if (!$years) {
            flash("입학연도 구간을 확인하세요. 끝 연도는 비워 두면 '이후 계속'입니다.", 'danger');
            redirect(url('admin/grad-reqs'));
        }
        try {
            $req_id = q_exec(Q_GRAD_REQ_INSERT, ['dept' => my_dept(), 'from_year' => $years[0], 'to_year' => $years[1]],
                'new_id');   // 트리거가 구간 겹침 검사
            db_commit();
        } catch (DbError $e) {
            db_rollback();
            if (is_overlap($e)) {
                flash(OVERLAP_MSG, 'warning');
                redirect(url('admin/grad-reqs'));
            }
            throw $e;
        }
        flash('졸업요건을 만들었습니다. 영역별 학점, 과목군, 필수과목을 입력하세요.', 'success');
        redirect(url('admin/grad-req', ['id' => $req_id]));
    }
    render('admin/grad_reqs', ['rows' => q_all(Q_GRAD_REQ_LIST, ['dept' => my_dept()])]);
}

function page_admin_grad_req_edit()
{
    require_role('admin');
    $req_id = to_int(get_param('id'));
    $req = $req_id === null ? null : q_one(Q_GRAD_REQ_ONE, ['id' => $req_id, 'dept' => my_dept()]);
    if (!$req) {
        abort_page(404);
    }
    $areas = q_all(Q_GRAD_REQ_AREAS, ['id' => $req_id]);
    $groups = q_all(Q_GRAD_REQ_GROUPS, ['id' => $req_id, 'dept' => my_dept()]);
    $self = url('admin/grad-req', ['id' => $req_id]);

    if (is_post()) {
        if (post('action') === 'delete') {
            q_exec(Q_GRAD_REQ_DELETE, ['id' => $req_id, 'dept' => my_dept()]);   // 영역·과목군 연결은 ON DELETE CASCADE
            db_commit();
            flash(req_years_label($req['from_year'], $req['to_year']) . ' 졸업요건을 삭제했습니다.', 'success');
            redirect(url('admin/grad-reqs'));
        }
        $years = read_years();
        $total = to_int(post('total_credits'));
        $design_raw = trim(post('min_design_credits'));
        $design = $design_raw === '' ? 0 : to_int($design_raw);
        $raw_gpa = trim(post('min_gpa'));
        $min_gpa = $raw_gpa === '' ? null : (is_numeric($raw_gpa) ? (float)$raw_gpa : false);
        $area_vals = [];
        $group_vals = [];
        $bad = !$years || $total === null || $design === null || $min_gpa === false;
        foreach ($areas as $a) {
            $raw = trim(post('area_' . $a['area_code']));
            if ($raw !== '') {
                $v = to_int($raw);
                $bad = $bad || $v === null;
                $area_vals[$a['area_code']] = $v;
            }
        }
        foreach ($groups as $g) {
            $raw = trim(post('group_' . $g['group_code']));
            if ($g['rule_type'] === 'ALL') {
                if ($raw !== '') {
                    $group_vals[$g['group_code']] = null;   // ALL 과목군: 연결만 (기준 학점 없음)
                }
            } elseif ($raw !== '') {
                $v = to_int($raw);
                $bad = $bad || $v === null;
                if ($v > 0) {
                    $group_vals[$g['group_code']] = $v;
                }
            }
        }
        if ($bad) {
            flash('연도·학점·평점을 숫자로 입력하세요.', 'danger');
            redirect($self);
        }
        if (($min_gpa !== null && ($min_gpa < 0 || $min_gpa > 4.5)) || $total <= 0 || $design < 0) {
            flash('총학점은 1 이상, 최소평점은 0~4.5 사이여야 합니다.', 'danger');
            redirect($self);
        }
        $note = mb_substr(trim(post('note')), 0, 500);

        // 요건 한 건의 헤더·영역·과목군 연결을 한 트랜잭션으로 교체
        try {
            q_exec(Q_GRAD_REQ_UPDATE, ['from_year' => $years[0], 'to_year' => $years[1], 'total' => $total,
                'min_gpa' => $min_gpa, 'design' => $design, 'note' => $note, 'id' => $req_id]);
            q_exec(Q_GRAD_REQ_AREA_DELETE, ['id' => $req_id]);
            foreach ($area_vals as $code => $credits) {
                q_exec(Q_GRAD_REQ_AREA_INSERT, ['id' => $req_id, 'code' => $code, 'credits' => $credits]);
            }
            q_exec(Q_GRAD_REQ_GROUP_DELETE, ['id' => $req_id]);
            foreach ($group_vals as $code => $credits) {
                q_exec(Q_GRAD_REQ_GROUP_INSERT, ['id' => $req_id, 'code' => $code, 'credits' => $credits]);
            }
            db_commit();
        } catch (DbError $e) {
            db_rollback();
            if (is_overlap($e)) {
                flash(OVERLAP_MSG, 'warning');
                redirect($self);
            }
            throw $e;
        }
        flash(req_years_label($years[0], $years[1]) . ' 졸업요건을 저장했습니다.', 'success');
        redirect(url('admin/grad-reqs'));
    }
    render('admin/grad_req_edit', compact('req', 'areas', 'groups'));
}

// ---------------------------------------------------------------- 과목군 관리 (필수과목 포함)
function page_admin_groups()
{
    require_role('admin');
    if (is_post()) {
        $code = strtoupper(trim(post('group_code')));
        $name = mb_substr(trim(post('group_name')), 0, 50);
        $rule = in_array(post('rule_type'), ['CREDITS', 'ALL'], true) ? post('rule_type') : 'CREDITS';
        if (!preg_match('/^[A-Z0-9_]{2,20}$/', $code) || $name === '') {
            flash('코드는 영문 대문자·숫자·밑줄 2~20자, 이름은 필수입니다.', 'danger');
            redirect(url('admin/groups'));
        }
        if (q_one(Q_GROUP_EXISTS, ['code' => $code])) {
            flash("{$code} 코드는 이미 있습니다.", 'warning');
            redirect(url('admin/groups'));
        }
        q_exec(Q_GROUP_INSERT, ['code' => $code, 'name' => $name, 'rule' => $rule, 'dept' => my_dept(),
            'descr' => mb_substr(trim(post('description')), 0, 300)]);
        db_commit();
        flash("과목군 '{$name}'을(를) 만들었습니다. 과목을 고르세요.", 'success');
        redirect(url('admin/group', ['code' => $code]));
    }
    render('admin/groups', ['rows' => q_all(Q_GROUP_LIST, ['dept' => my_dept()])]);
}

function page_admin_group_edit()
{
    require_role('admin');
    $code = get_param('code');
    $g = $code === '' ? null : q_one(Q_GROUP_ONE, ['code' => $code, 'dept' => my_dept()]);
    if (!$g) {
        abort_page(404);
    }
    $editable = $g['dept_id'] === my_dept();
    if (is_post()) {
        if (!$editable) {
            abort_page(403);
        }
        if (post('action') === 'delete') {
            q_exec(Q_GROUP_DELETE, ['code' => $code, 'dept' => my_dept()]);   // 구성 과목·요건 연결은 ON DELETE CASCADE
            db_commit();
            flash("과목군 '{$g['group_name']}'을(를) 삭제했습니다.", 'success');
            redirect(url('admin/groups'));
        }
        $name = mb_substr(trim(post('group_name')), 0, 50);
        if ($name === '') {
            flash('이름을 입력하세요.', 'danger');
            redirect(current_url());
        }
        $course_ids = [];
        foreach (array_unique((array)($_POST['course'] ?? [])) as $c) {
            if (is_string($c) && q_one(Q_ALLOWED_COURSE, ['cid' => $c, 'dept' => my_dept()])) {
                $course_ids[] = $c;
            }
        }
        try {   // 이름·설명·구성 과목을 한 트랜잭션으로 교체
            q_exec(Q_GROUP_UPDATE, ['name' => $name, 'descr' => mb_substr(trim(post('description')), 0, 300), 'code' => $code]);
            q_exec(Q_MEMBER_DELETE, ['code' => $code]);
            foreach ($course_ids as $cid) {
                q_exec(Q_MEMBER_INSERT, ['code' => $code, 'cid' => $cid]);
            }
            db_commit();
        } catch (DbError $e) {
            db_rollback();
            throw $e;
        }
        flash("'{$name}' 과목군을 저장했습니다 (" . count($course_ids) . '과목). 연결된 졸업요건 판정에 바로 반영됩니다.', 'success');
        redirect(url('admin/groups'));
    }
    render('admin/group_edit', [
        'g' => $g, 'editable' => $editable,
        'courses' => q_all(Q_GROUP_COURSE_CHOICES, ['code' => $code, 'dept' => my_dept()]),
        'used_by' => q_all(Q_GROUP_USED_BY, ['code' => $code]),
    ]);
}

// ---------------------------------------------------------------- 장학금 관리 (FR-06)
/** 반환: 바인드 배열, 잘못된 입력이면 null */
function read_scholarship_form()
{
    $opt = function ($key) {
        $raw = trim(post($key));
        if ($raw === '') {
            return null;
        }
        $v = to_int($raw);
        return $v === null ? false : $v;
    };
    $name = trim(post('sch_name'));
    $category = in_array(post('category'), ['성적우수', '가계지원', '학과'], true) ? post('category') : '성적우수';
    $gpa_raw = trim(post('min_gpa'));
    $min_gpa = is_numeric($gpa_raw) ? (float)$gpa_raw : null;
    $min_credits = to_int(post('min_credits'));
    $max_income = $opt('max_income');
    $volunteer = $opt('min_volunteer_hours');
    $exempt = $opt('volunteer_exempt_level');
    if ($name === '' || $min_gpa === null || $min_gpa < 0 || $min_gpa > 4.5 || $min_credits === null || $min_credits < 0
        || $max_income === false || $volunteer === false || $exempt === false
        || ($max_income !== null && ($max_income < 0 || $max_income > 10))
        || ($volunteer !== null && $volunteer < 0)) {
        return null;
    }
    return [
        'sch_name' => mb_substr($name, 0, 50), 'category' => $category,
        'amount_desc' => mb_substr(trim(post('amount_desc')), 0, 30),
        'min_gpa' => $min_gpa, 'min_credits' => $min_credits, 'max_income' => $max_income,
        'min_volunteer_hours' => $volunteer, 'volunteer_exempt_level' => $exempt,
        'dept_id' => post('scope') === 'dept' ? my_dept() : null,
    ];
}

const SCH_INPUT_MSG = '입력값을 확인하세요. 평점 0~4.5, 소득분위 0~10(비우면 무관)입니다.';

function page_admin_scholarships()
{
    require_role('admin');
    if (is_post()) {
        $vals = read_scholarship_form();
        if (!$vals) {
            flash(SCH_INPUT_MSG, 'danger');
            redirect(url('admin/scholarships'));
        }
        q_exec(Q_SCHOLARSHIP_INSERT, $vals);
        db_commit();
        flash("{$vals['sch_name']}을(를) 등록했습니다.", 'success');
        redirect(url('admin/scholarships'));
    }
    render('admin/scholarships', ['rows' => q_all(Q_SCHOLARSHIP_LIST, ['dept' => my_dept()]), 'sch' => null]);
}

function page_admin_scholarship_edit()
{
    require_role('admin');
    $id = to_int(get_param('id'));
    $sch = $id === null ? null : q_one(Q_SCHOLARSHIP_ONE, ['id' => $id, 'dept' => my_dept()]);
    if (!$sch) {
        abort_page(404);
    }
    if (is_post()) {
        if (post('action') === 'delete') {
            q_exec(Q_SCHOLARSHIP_DELETE, ['id' => $id]);
            db_commit();
            flash("{$sch['sch_name']}을(를) 삭제했습니다.", 'success');
            redirect(url('admin/scholarships'));
        }
        $vals = read_scholarship_form();
        if (!$vals) {
            flash(SCH_INPUT_MSG, 'danger');
            redirect(current_url());
        }
        q_exec(Q_SCHOLARSHIP_UPDATE, $vals + ['id' => $id]);
        db_commit();
        flash("{$vals['sch_name']}을(를) 수정했습니다.", 'success');
        redirect(url('admin/scholarships'));
    }
    render('admin/scholarships', ['rows' => q_all(Q_SCHOLARSHIP_LIST, ['dept' => my_dept()]), 'sch' => $sch]);
}
