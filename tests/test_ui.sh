#!/usr/bin/env bash
# Offline regression tests. Every HTTP request is intercepted below.
cd "$(dirname "${BASH_SOURCE[0]}")/.." || exit 1
source ./KuzFollow.sh
GITHUB_USER=fixture
GITHUB_TOKEN=fixture-only-not-a-secret
sleep() { :; }
curl() {
    local url=${!#}
    case ${SCENARIO:-ok} in
        transport) return 22 ;;
        invalid) printf '{"message":"unavailable"}'; return 0 ;;
        malformed) printf 'not-json'; return 0 ;;
        second_page_failure) [[ $url == *'page=2' ]] && return 22 ;;
    esac
    case "$url" in
        */user/following/*) printf '%s' "${HTTP_STATUS:-204}" ;;
        *'page=2') printf '[]' ;;
        */followers\?*) printf '[{"login":"mutual"},{"login":"fan"}]' ;;
        */following\?*) printf '[{"login":"mutual"},{"login":"other"}]' ;;
        */repos\?*) printf '[{"name":"demo"}]' ;;
        */events/public\?*) printf '[]' ;;
        */users/fixture) printf '{"name":"Fixture User","public_repos":1}' ;;
        *) printf 'Unexpected fixture request\n' >&2; return 99 ;;
    esac
}

if [[ ${1:-} == --menu ]]; then
    init_ui
    unfollowed_count=1 not_followed_back_count=1
    unfollowed_users=(other) not_followed_back_users=(fan)
    mass_follow() { printf 'MOCK_FOLLOW:%s\n' "$*"; }
    mass_unfollow() { printf 'MOCK_UNFOLLOW:%s\n' "$*"; }
    action_menu
    exit
fi
if [[ ${1:-} == --color ]]; then
    init_ui
    show_header
    exit
fi

passed=0
fail() { printf 'FAIL: %s\n' "$1" >&2; exit 1; }
pass() { passed=$((passed + 1)); printf 'PASS: %s\n' "$1"; }
contains() { [[ $1 == *"$2"* ]] || fail "$3"; }
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
bash -n KuzFollow.sh && bash -n tests/test_ui.sh || fail syntax
pass 'Bash syntax'
output=$(bash KuzFollow.sh --help) || fail help
contains "$output" 'Usage:' help
pass 'Help without credentials or requests'
if env -u GITHUB_USER -u GITHUB_TOKEN bash KuzFollow.sh >"$work/config" 2>&1; then fail config; fi
pass 'Missing configuration fails before HTTP'
if bash KuzFollow.sh --unknown >"$work/unknown" 2>&1; then fail arguments; fi
pass 'Unknown argument rejected'
output=$(main) || fail report
contains "$output" 'Mutual connections     1' mutual
contains "$output" 'Not following you back (1)' nonreciprocal
contains "$output" 'Followers to follow back (1)' followers
contains "$output" 'Report only:' readonly
[[ $output != *$'\033'* ]] || fail 'ANSI in report'
contains "$output" 'FOLLOWERS (FIRST 10)' 'honest follower labels'
[[ $output != *'Joined GitHub'* && $output != *"$GITHUB_TOKEN"* ]] || fail disclosure
pass 'Offline full report, counts, lists, no ANSI or token'
for SCENARIO in invalid malformed transport second_page_failure; do
    if get_all_users followers >"$work/error" 2>/dev/null; then fail "$SCENARIO"; fi
    if main >"$work/report-error" 2>/dev/null; then fail "main $SCENARIO"; fi
    [[ $(<"$work/report-error") != *'Actions'* ]] || fail 'actions after failure'
    pass "No actions after API failure: $SCENARIO"
done
SCENARIO=ok
output=$(get_all_users followers) || fail pagination
[[ $output == $'mutual\nfan' ]] || fail pagination
pass 'Pagination and login extraction'
HTTP_STATUS=204
follow_user fan && unfollow_user other || fail success
HTTP_STATUS=403
if follow_user fan || unfollow_user other; then fail 'HTTP rejection'; fi
pass 'Follow/unfollow success and rejection (mock HTTP)'
output=$(mass_follow fan)
contains "$output" 'FAILED TO FOLLOW:' summary
contains "$output" '1' failures
pass 'Mass action failure summary'
output=$(NO_COLOR=1 COLUMNS=32 bash tests/test_ui.sh --color)
[[ $output != *$'\033'* ]] || fail NO_COLOR
[[ $output != *'███████╗'* ]] || fail 'wide logo in narrow terminal'
rule=$(COLUMNS=32 ui_rule)
[[ ${#rule} == 32 ]] || fail width
rule=$(COLUMNS=bogus ui_rule)
[[ ${#rule} == 72 ]] || fail fallback
output=$(NO_COLOR=1 COLUMNS=100 bash tests/test_ui.sh --color)
contains "$output" '███████╗ ██████╗' 'restored ASCII logo'
pass 'ASCII logo, NO_COLOR and terminal widths'
output=$(show_accounts Empty)
contains "$output" 'None.' empty
pass 'Empty list state'

command -v script >/dev/null || fail 'PTY tests require util-linux script'
# script supplies a real terminal to the tested menu; input remains synthetic.
printf 'invalid\n3\n1\nno\nq\n' | timeout 10 script -qefc 'bash tests/test_ui.sh --menu' "$work/menu" >/dev/null || fail menu
output=$(<"$work/menu")
contains "$output" 'Invalid choice.' invalid
contains "$output" 'Not following you back (1)' review
contains "$output" 'Cancelled.' cancel
contains "$output" 'No changes made.' quit
[[ $output != *'MOCK_FOLLOW:'* && $output != *'MOCK_UNFOLLOW:'* ]] || fail 'cancel mutation'
pass 'Interactive retry, review, cancellation and quit'
printf '2\nYES\n' | timeout 10 script -qefc 'bash tests/test_ui.sh --menu' "$work/follow" >/dev/null || fail follow
contains "$(<"$work/follow")" 'MOCK_FOLLOW:fan' follow
pass 'Interactive follow confirmation and correct target'
printf '1\nYES\n' | timeout 10 script -qefc 'bash tests/test_ui.sh --menu' "$work/unfollow" >/dev/null || fail unfollow
contains "$(<"$work/unfollow")" 'MOCK_UNFOLLOW:other' unfollow
pass 'Interactive unfollow confirmation and correct target'
printf '\004' | timeout 10 script -qefc 'bash tests/test_ui.sh --menu' "$work/eof" >/dev/null || fail eof
pass 'End of input exits cleanly'
env -u NO_COLOR TERM=xterm timeout 10 script -qefc 'bash tests/test_ui.sh --color' "$work/color" >/dev/null || fail color
[[ $(<"$work/color") == *$'\033'* ]] || fail 'terminal colors'
TERM=xterm NO_COLOR=1 timeout 10 script -qefc 'bash tests/test_ui.sh --color' "$work/no-color" >/dev/null || fail no-color
[[ $(<"$work/no-color") != *$'\033'* ]] || fail 'NO_COLOR in terminal'
pass 'Real terminal color and NO_COLOR rendering'
printf '\nAll %s checks passed. No live HTTP requests.\n' "$passed"
