#!/usr/bin/env bash
cd "$(dirname "$0")" || exit 1

git status -s
read -r -p "커밋 메시지 (빈칸이면 자동 생성): " msg
if [ -z "$msg" ]; then
  msg="Update changed files ($(date '+%Y-%m-%d %H:%M:%S'))"
fi

git add .
git commit -m "$msg" || exit 1
git pull --rebase origin master || exit 1
git push origin master