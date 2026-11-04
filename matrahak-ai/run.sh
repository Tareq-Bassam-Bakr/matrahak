#!/usr/bin/env bash
# تشغيل خدمة الذكاء الاصطناعي على Linux / macOS
set -e
cd "$(dirname "$0")"
if [ ! -x .venv/bin/python ]; then
  echo "[1/3] Creating virtual environment .venv ..."
  python3 -m venv .venv
fi
echo "[2/3] Installing requirements ..."
.venv/bin/python -m pip install --disable-pip-version-check -q -r requirements.txt
[ -f .env ] || cp .env.example .env
echo "[3/3] Starting Matrahak AI service on http://127.0.0.1:8001  (API docs: /docs)"
exec .venv/bin/python -m uvicorn app.main:app --host 127.0.0.1 --port 8001
