#!/bin/bash
# macOS: 더블클릭으로 실행
cd "$(dirname "$0")"
python3 -m pip install -q -r requirements.txt || exit 1
python3 init_db.py --if-needed || exit 1
(sleep 3; open http://127.0.0.1:5000) &
python3 run.py
