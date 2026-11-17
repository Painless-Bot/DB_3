/* 사이트 공통 스크립트 (외부 라이브러리 없음) */
(function () {
  "use strict";
  var GP = { "A+": 4.5, "A0": 4.0, "B+": 3.5, "B0": 3.0, "C+": 2.5, "C0": 2.0, "D+": 1.5, "D0": 1.0, "F": 0 };

  // 표 머리글 클릭 정렬: <table class="sortable"> 의 <th data-sort> (값은 td 의 data-v 또는 글자)
  document.querySelectorAll("table.sortable").forEach(function (table) {
    table.querySelectorAll("th[data-sort]").forEach(function (th, _i) {
      th.classList.add("sort-th");
      th.addEventListener("click", function () {
        var idx = Array.prototype.indexOf.call(th.parentNode.children, th);
        var num = th.dataset.sort === "num";
        var dir = th.dataset.dir === "asc" ? "desc" : "asc";
        table.querySelectorAll("th").forEach(function (x) { delete x.dataset.dir; });
        th.dataset.dir = dir;
        var body = table.tBodies[0];
        var rows = Array.prototype.slice.call(body.rows).filter(function (r) { return r.cells.length > 1; });
        rows.sort(function (a, b) {
          var x = a.cells[idx], y = b.cells[idx];
          var vx = x.dataset.v !== undefined ? x.dataset.v : x.textContent.trim();
          var vy = y.dataset.v !== undefined ? y.dataset.v : y.textContent.trim();
          var c = num ? (parseFloat(vx) || 0) - (parseFloat(vy) || 0) : vx.localeCompare(vy, "ko");
          return dir === "asc" ? c : -c;
        });
        rows.forEach(function (r) { body.appendChild(r); });
      });
    });
  });

  // 검색 칸으로 목록 거르기: <input data-filter="CSS 선택자"> → 대상의 data-name 으로 비교
  document.querySelectorAll("input[data-filter]").forEach(function (inp) {
    inp.addEventListener("input", function () {
      var q = inp.value.trim().toLowerCase().replace(/\s+/g, "");
      document.querySelectorAll(inp.dataset.filter).forEach(function (el) {
        var name = (el.dataset.name || el.textContent).toLowerCase().replace(/\s+/g, "");
        el.style.display = !q || name.indexOf(q) >= 0 ? "" : "none";
        var det = el.closest("details");
        if (q && det && name.indexOf(q) >= 0) det.open = true;
      });
    });
  });

  // 과목 선택 목록: 선택 과목 수·학점, (수강 계획이면) 예상 학기 평점을 바로 계산
  var form = document.getElementById("plan-form");
  if (form) {
    var update = function () {
      var n = 0, cr = 0, pts = 0, gcr = 0;
      form.querySelectorAll("input[name=course]").forEach(function (cb) {
        var row = cb.closest(".plan-row");
        if (row) row.classList.toggle("on", cb.checked);
        if (!cb.checked) return;
        var c = parseInt(cb.dataset.credits, 10) || 0;
        n += 1; cr += c;
        var sel = row && row.querySelector("select.grade-pick");
        if (sel && GP.hasOwnProperty(sel.value)) { pts += GP[sel.value] * c; gcr += c; }
      });
      var set = function (id, v) { var el = document.getElementById(id); if (el) el.textContent = v; };
      set("pick-count", n);
      set("pick-credits", cr);
      set("pick-gpa", gcr ? (Math.round(pts / gcr * 100 + 1e-9) / 100).toFixed(2) : "-");
      var warn = document.getElementById("pick-warn");
      if (warn) {
        warn.textContent = cr > 21 ? "한 학기 최대 신청학점(보통 19~21학점)을 넘습니다."
          : (cr > 0 && cr < 15 ? "장학금은 직전 학기 15학점 이상 취득이 기준입니다." : "");
      }
    };
    form.addEventListener("change", update);
    update();
    var res = document.getElementById("result");
    if (res && window.innerWidth < 992) res.scrollIntoView({ behavior: "smooth" });
  }
})();
