<?php
/** 한 학생의 진단 결과 전체. 학생 화면과 담당자 학생 상세가 같이 쓴다. */
defined('APP_ROOT') || exit;

function load_report($sid)
{
    $p = ['sid' => $sid];
    $semesters = q_all(Q_SEMESTER_RECORDS, $p);
    $by_sem = [];
    foreach (q_all(Q_ENROLLMENTS, $p) as $e) {
        $by_sem[$e['year'] . '-' . $e['term']][] = $e;
    }
    $scholarships = q_all(Q_SCHOLARSHIPS, $p);
    $regular = array_values(array_filter($semesters, function ($s) { return in_array($s['term'], [1, 2]); }));
    $groups = q_all(Q_GRAD_GROUP_STATUS, $p);
    $sem_courses = [];
    foreach (array_reverse($semesters) as $s) {
        $sem_courses[] = [$s, $by_sem[$s['year'] . '-' . $s['term']] ?? []];
    }
    return [
        'info' => q_one(Q_STUDENT_INFO, $p),
        'status' => q_one(Q_GRAD_STATUS, $p),
        'req' => q_one(Q_GRAD_REQ_INFO, $p),
        'areas' => q_all(Q_GRAD_AREA_STATUS, $p),
        'groups' => array_values(array_filter($groups, function ($g) { return $g['rule_type'] === 'CREDITS'; })),
        'required_groups' => array_values(array_filter($groups, function ($g) { return $g['rule_type'] === 'ALL'; })),
        'earned_by_area' => q_all(Q_EARNED_BY_AREA, $p),
        'missing' => q_all(Q_MISSING_REQUIRED, $p),
        'scholarships' => $scholarships,
        'eligible_count' => count(array_filter($scholarships, function ($s) { return $s['eligible']; })),
        'semesters' => $semesters,
        'last_sem' => $regular ? end($regular) : null,
        'sem_courses' => $sem_courses,
        'suggestions' => group_suggestions(q_all(Q_SUGGESTIONS, $p)),
    ];
}

/** V_COURSE_SUGGESTION 결과를 과목 단위로 묶는다 (한 과목이 채우는 요건 목록 + 가장 높은 우선순위). */
function group_suggestions(array $rows)
{
    $out = [];
    foreach ($rows as $r) {
        $id = $r['course_id'];
        if (!isset($out[$id])) {
            $out[$id] = $r + ['reasons' => []];
        }
        if (!in_array($r['reason_name'], $out[$id]['reasons'], true)) {
            $out[$id]['reasons'][] = $r['reason_name'];
        }
        $out[$id]['priority'] = min($out[$id]['priority'], $r['priority']);
    }
    $out = array_values($out);
    usort($out, function ($a, $b) {
        return [$a['priority'], -count($a['reasons']), $a['course_name']]
            <=> [$b['priority'], -count($b['reasons']), $b['course_name']];
    });
    return $out;
}

/** 졸업 판정 미충족 사유 목록 (요약 카드·진단서에서 사용) */
function grad_reasons(array $r)
{
    $st = $r['status'];
    $reasons = [];
    if ($st['remaining_total']) {
        $reasons[] = "총학점 {$st['remaining_total']}학점 부족";
    }
    if ($st['earned_design'] < $st['min_design_credits']) {
        $reasons[] = '설계학점 ' . ($st['min_design_credits'] - $st['earned_design']) . '학점 부족';
    }
    foreach ($r['areas'] as $a) {
        if ($a['remaining_credits']) {
            $reasons[] = "{$a['area_name']} {$a['remaining_credits']}학점";
        }
    }
    foreach ($r['groups'] as $g) {
        if ($g['remaining_credits']) {
            $reasons[] = $g['group_name'] . ($g['min_credits'] == 1 ? ' 미이수' : " {$g['remaining_credits']}학점");
        }
    }
    foreach ($r['missing'] as $m) {
        $reasons[] = "{$m['course_name']} 미이수";
    }
    if (!$st['gpa_ok']) {
        $reasons[] = '평점 ' . gpa($st['min_gpa']) . ' 미만';
    }
    return $reasons;
}
