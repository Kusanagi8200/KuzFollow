# KuzFollow — GitHub connections in Bash

KuzFollow analyzes your GitHub followers and following, shows non-reciprocal
connections, and offers explicitly confirmed follow/unfollow actions.
The terminal application and its tests are written only in Bash. It uses the
existing `curl` and `jq` utilities; no additional application language is required.

## Terminal design

- Compact header instead of a large ASCII banner.
- Aligned overview and connection counts, including mutual connections.
- Separate target lists with counts and explicit empty states.
- Menu returns after reviewing lists, invalid input, or cancelling an action.
- `YES` is required for each batch operation; `q`, `4`, or Enter exits the menu.
- Decorative separators use `COLUMNS` (maximum 72 columns).
- Colors are disabled when output is redirected, `TERM=dumb`, or `NO_COLOR` is set.
- When input or output is not a terminal, only a report is produced: no actions.

Example (fictional data):

```text
KuzFollow | GitHub connections
Analyze your network, then choose an action.

Overview
------------------------------------------------------------------------
  Account                example
  Name                   Example User
  Public repositories    12

Connections
------------------------------------------------------------------------
  Following              20
  Followers              18
  Mutual connections     15
  Not following back     5
  To follow back         3

Actions
------------------------------------------------------------------------
  [1] Unfollow non-reciprocal accounts (5)
  [2] Follow back followers (3)
  [3] Review account lists
  [4/q] Finish without changes
```

The full report also lists up to 15 public repositories, up to 10 public activity
events, and the first 10 followers returned by the API. Follower dates are not
shown: that endpoint does not supply account creation or follow dates.

## Requirements and installation

Requires **Bash 4 or newer**, `curl`, `jq`, and standard system utilities
(`head`, `date`, `sleep`). On macOS, use a newer Bash instead of the system Bash 3.
GNU `date` formats event timestamps; otherwise the original timestamp is shown.

```bash
git clone https://github.com/Kusanagi8200/KuzFollow.git
cd KuzFollow
bash --version
command -v curl
command -v jq
bash KuzFollow.sh --help
```

The repository is private, so cloning requires an authorized GitHub session.
On Debian/Ubuntu, install missing dependencies with `sudo apt install bash curl jq`.

## Configuration and usage

Set the username and token through environment variables. The token must belong
to the account whose relationships you intend to change. Use a token authorized
to follow users (for a classic PAT, `user:follow`).

Enter the token silently so it does not appear in command history:

```bash
export GITHUB_USER="your_username"
read -r -s -p 'GitHub token: ' GITHUB_TOKEN
printf '\n'
export GITHUB_TOKEN
bash KuzFollow.sh
unset GITHUB_TOKEN
```

Never put a real token in the script, README, screenshots, or test fixtures.
Do not run the application with shell tracing (`bash -x`).

```bash
# Plain terminal output
NO_COLOR=1 bash KuzFollow.sh

# Read-only report (no menu actions, no ANSI color codes)
bash KuzFollow.sh > report.txt

# Offline help
bash KuzFollow.sh --help
```

`GITHUB_USER` and `GITHUB_TOKEN` are required for analysis. `PER_PAGE=100` remains
the API page size in the script. No configuration file is necessary.

## Actions and error handling

Review the two account lists before choosing `[1]` (unfollow) or `[2]` (follow).
Only an exact `YES` confirms the batch. Any other answer cancels and returns to
the menu. After a batch, rerun the script to refresh the counts.

Relationship retrieval stops on HTTP/transport errors, invalid JSON, or invalid
list data, including failure on a later page. No action menu is offered after
such a failure. Check connectivity, token permissions, and API availability,
then rerun. Do not print the token when troubleshooting.

Batch requests have a one-second delay and success/failure summaries. There are
**no automatic retries or automatic rate-limit recovery**. Some optional profile,
repository, or activity information can be unavailable; this is not proof that
an account has no data. The script does not store follower history.

## Offline regression tests

```bash
bash -n KuzFollow.sh
bash -n tests/test_ui.sh
bash tests/test_ui.sh
```

The Bash test suite replaces HTTP requests with fixtures. It checks counts,
pagination, API failures, read-only reports, follow/unfollow response handling,
empty lists, terminal widths, colors, menu navigation, cancellation, confirmation,
and end-of-input behavior. Interactive checks use `script` (util-linux) and
`timeout` on Linux. No real account is followed or unfollowed during these tests.

## Repository structure

- `KuzFollow.sh`: standalone Bash terminal application.
- `tests/test_ui.sh`: offline Bash regression tests.
- `public/`, `src/`, `data/`: pre-existing separate web interface and its data.
  They are not required by the Bash application and were not migrated or modified
  as part of this terminal redesign.

Local timestamped backups are kept outside the application directory before
editing; they are not published with the source changes.
