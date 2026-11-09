from decimal import Decimal


def register_filters(app):
    @app.template_filter("gpa")
    def fmt_gpa(v):
        if v is None:
            return "-"
        return f"{Decimal(v):.2f}"

    @app.template_filter("term")
    def fmt_term(t):
        return {1: "1학기", 2: "2학기", 3: "여름학기", 4: "겨울학기"}.get(t, f"{t}학기")

    @app.template_filter("pct")
    def pct(earned, required):
        if not required:
            return 100
        return min(100, round(100 * (earned or 0) / required))

    @app.template_filter("years")
    def fmt_years(req):
        """졸업요건의 입학연도 구간: 2019~2021학번 / 2022학번~"""
        if not req:
            return "-"
        f, t = req["from_year"], req["to_year"]
        if t is None:
            return f"{f}학번 이후"
        if f == t:
            return f"{f}학번"
        return f"{f}~{t}학번"
