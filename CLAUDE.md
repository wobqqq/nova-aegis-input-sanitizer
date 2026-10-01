# CLAUDE.md

@AGENTS.md

## Skills and hooks

- Skills in `.claude/skills/`: `aegis-security` (read it for any change to what a request can do or what gets through), `package-upgrades` (anything that reaches an installed application), `package-testing`, `nova-development`, `testing-best-practices`, `laravel-best-practices`.
- A changed PHP file is formatted by the `PostToolUse` hook in `.claude/settings.json`; still run `make ready` before you say a change is done, and report its result.
- The core lives in the sibling repository `../nova-aegis` and is installed from it through the `path` repository. Never change it from here: a change to the core is a pull request on the core.
- `laravel/nova` is the test double in `stubs/nova`, a verbatim copy of the core's: a new Nova API is added in the core first, with the real signature (see `package-testing`), then copied here.
- Never push to `main`: work on a branch and open a pull request (see *Git workflow* in AGENTS.md). Write everything in English.
