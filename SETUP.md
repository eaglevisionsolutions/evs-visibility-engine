# Setup

## 1. Place this folder
Unzip/move this folder to: `~/Projects/evs-visibility-engine`

## 2. Init git + first commit
```bash
cd ~/Projects/evs-visibility-engine
git init
git add .
git commit -m "Initial scaffold: CLAUDE.md, docs, folder structure"
```

## 3. Create the GitHub repo (personal account, private)
Requires the GitHub CLI (`gh`) authenticated — run `gh auth login` first if
you haven't. If you hit auth issues, flip `--private` to `--public` and
create it via github.com/new instead.

```bash
gh repo create evs-visibility-engine --private --source=. --remote=origin --push
```

## 4. Confirm branch strategy
`main` is the working branch through Phase 1. From Phase 2 onward, cut a
release branch per `docs/phase-roadmap.md`:

```bash
git checkout -b release/v2-content-engine
```

Open a PR back into `main` when a phase is demo-able; tag the merge commit
(`git tag v0.2 && git push --tags`).

## 5. Start Claude Code
```bash
cd ~/Projects/evs-visibility-engine
claude
```
Claude Code will pick up `CLAUDE.md` automatically. First message to send:

> Read CLAUDE.md, docs/phase-roadmap.md, and docs/db-schema-outline.md.
> Let's build Phase 1 (Core) as scoped in the roadmap: project skeleton,
> env config loader, PDO BaseModel with tenant isolation, JWT auth,
> accounts/users/sites migrations, Stripe billing skeleton, and the jobs
> table + worker polling loop. Propose the folder structure first before
> writing code, then build it migration by migration.
