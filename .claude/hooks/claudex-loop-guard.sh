#!/usr/bin/env bash
# Claudex loop guard (ADR-041) — PreToolUse hook, active only when CLAUDEX_LOOP=1
# (set by the coreone RC unit). Soft second layer behind the GitHub ruleset on main.
# In loop sessions it denies:
#   - git push to anything other than: git push [-u|-q] origin [HEAD:]claudex/<name>
#   - git commit while on main/master
#   - ssh/scp/sftp/rsync/lftp (the loop never deploys itself, never touches Prod)
#   - any access to the sensor secrets (~/.config/claudex, ~/.cache/claudex)
[ "${CLAUDEX_LOOP:-}" = "1" ] || exit 0

input=$(cat)
tool=$(printf '%s' "$input" | jq -r '.tool_name // empty')

deny() {
  jq -cn --arg r "Claudex-Loop-Guard: $1" \
    '{hookSpecificOutput:{hookEventName:"PreToolUse",permissionDecision:"deny",permissionDecisionReason:$r}}'
  exit 0
}
SECRET_MSG="Sensor-Zugangsdaten sind tabu — nur über buehne-login / buehne-wait / axe-check nutzen."

case "$tool" in
  Read|Edit|Write|MultiEdit|Grep|Glob|NotebookEdit)
    p=$(printf '%s' "$input" | jq -r '.tool_input.file_path // .tool_input.path // .tool_input.notebook_path // empty')
    case "$p" in *.config/claudex*|*.cache/claudex*) deny "$SECRET_MSG" ;; esac
    exit 0 ;;
  Bash) ;;
  *) exit 0 ;;
esac

cmd=$(printf '%s' "$input" | jq -r '.tool_input.command // empty')

case "$cmd" in *.config/claudex*|*.cache/claudex*|*buehne.env*) deny "$SECRET_MSG" ;; esac

if printf '%s' "$cmd" | grep -qE '(^|[;&|(`[:space:]])(ssh|scp|sftp|rsync|lftp)([[:space:]]|$)'; then
  deny "Der Loop deployt nie selbst und berührt nie Prod — kein ssh/scp/sftp/rsync. Deploy nur per Push auf claudex/** (Actions)."
fi

GIT='git([[:space:]]+-C[[:space:]]+[^[:space:]]+)?[[:space:]]+'
if printf '%s' "$cmd" | grep -qE "${GIT}push"; then
  bad=$(printf '%s' "$cmd" | grep -oE "${GIT}push[^;&|]*" |
    grep -vE "^${GIT}push([[:space:]]+(-u|--set-upstream|-q|--quiet))*[[:space:]]+origin[[:space:]]+(HEAD:)?(refs/heads/)?claudex/[A-Za-z0-9._/-]+[[:space:]]*$")
  [ -n "$bad" ] && deny "Push nur als 'git push -u origin HEAD:claudex/<thema>' — main/master, --all, --mirror, --force, Tags und andere Ziele sind im Loop gesperrt."
fi

if printf '%s' "$cmd" | grep -qE "${GIT}commit"; then
  br=$(git -C "${CLAUDE_PROJECT_DIR:-.}" rev-parse --abbrev-ref HEAD 2>/dev/null)
  case "$br" in main|master) deny "Commit auf $br ist im Loop gesperrt — auf claudex/<thema> arbeiten." ;; esac
fi
exit 0
