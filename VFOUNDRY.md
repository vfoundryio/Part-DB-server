# vFoundry's fork of Part-DB

`vfoundryio/Part-DB-server` is upstream [Part-DB](https://github.com/Part-DB/Part-DB-server) plus vFoundry's own
patches (for example the additive MCP tools Lower Decks uses). The patches are not sent upstream, so vFoundry's
Part-DB instance runs an image built from this fork: `ghcr.io/vfoundryio/part-db`. This file covers how that
works. Everything else in the repository is upstream's and is documented upstream.

## Branches

| Branch | What it is |
|---|---|
| `vfoundry-vX.Y.Z` | Upstream's tag `vX.Y.Z` plus the vFoundry commits. The newest one is the default branch: PRs target it, and every push to it publishes an image. Older ones are frozen and stay as rollback points. |
| `master` | An untouched mirror of upstream. Never commit or merge vFoundry work into it. |

The vFoundry commits on a branch are `git log vX.Y.Z..origin/vfoundry-vX.Y.Z`. Keep them few and self-contained.
Each one is replayed by hand at every upgrade.

## Upgrading to a new upstream release

Replay the vFoundry commits onto the new tag with a rebase, in a PR, so CI tests the result before anything is
published. The example moves from 2.20.4 to 2.21.0:

1. Fetch the new tag and publish it, unchanged, as the new base branch:
   ```bash
   git fetch upstream --tags
   git push origin v2.21.0:refs/heads/vfoundry-v2.21.0
   ```
   Nothing runs on that push. The branch holds none of vFoundry's workflows yet, and upstream's are disabled,
   unless the new release adds a workflow file (see "Upstream's workflows" below).
2. Rebase the vFoundry commits onto it on a work branch, then open a PR into `vfoundry-v2.21.0`:
   ```bash
   git switch -c upgrade/v2.21.0 origin/vfoundry-v2.20.4
   git rebase --onto v2.21.0 v2.20.4
   ```
   The rebase drops the old branch's merge commits and keeps the patches linear. If upstream changed something
   a patch touches, resolve the conflict, then re-run the patch's own tests.
3. CI on the PR runs PHPUnit, PHPStan and a full image build. Merging publishes `2.21.0-vfoundry.1`.
4. A repo admin makes `vfoundry-v2.21.0` the default branch.
5. Deploy it: see "Deploying" below. Check the upstream release notes for migrations and for changes to the
   image's PHP version.

## CI (`.github/workflows/vfoundry-ci.yml`)

It runs on every PR and on every push to a `vfoundry-v*` branch:

- **PHPUnit**: PHP 8.4 (the image's PHP), on SQLite, with upstream's own sequence of setup steps.
- **PHPStan**: level 5, from `phpstan.dist.neon`.
- **Image build**: builds the `Dockerfile` without pushing it. PRs only.
- **`ci-gate`**: the single required check. It passes when every job above succeeded or was skipped.

Jobs run on GitHub-hosted runners. The fork is public, so anyone can open a PR against it, and a PR job on a
self-hosted runner would run their code on vFoundry's own machines. Hosted runners are free for a public
repository.
Keep the repository public: the image is AGPL-3.0 software, and this fork is where its source is published.

## Image (`.github/workflows/vfoundry-image.yml`)

Every push to a `vfoundry-v*` branch builds the root `Dockerfile` for `linux/amd64` and pushes:

- `ghcr.io/vfoundryio/part-db:X.Y.Z-vfoundry.N`, where `X.Y.Z` comes from the `VERSION` file and `N` counts
  publishes of that upstream version;
- `ghcr.io/vfoundryio/part-db:sha-<short sha>`.

It then tags the commit `vX.Y.Z-vfoundry.N` in git. Those git tags are where `N` comes from. Re-running the
workflow for a commit that is already tagged re-publishes under the same `N`. The run's summary prints the full
`image:tag@sha256:…` reference.

## Deploying

vFoundry's deployment configuration, which lives outside this repository, pins the image by digest. To deploy
a build, copy the `image:tag@sha256:…` reference from the image run's summary into it.

## Upstream's workflows

Upstream's files in `.github/workflows/` (`tests.yml`, `static_analysis.yml`, `docker_build.yml`, …) are left
byte-identical to upstream, so rebases never conflict on them. They are **disabled** in the repository's Actions
settings, so they never run. Untouched, they would run on every branch, and the Docker ones would try to push to
Docker Hub with secrets this fork does not have.

Disabling is per file path. A workflow file that is new to the repository starts **enabled**, and it runs on the
first push that matches its triggers, which for upstream's is usually the push of the new base branch in step 1
of an upgrade. Before that push, look for new files with `git diff --stat vOLD vNEW -- .github/workflows`. After
it, cancel any run they started and disable them:

```bash
gh workflow list --all -R vfoundryio/Part-DB-server
gh workflow disable <file>.yml -R vfoundryio/Part-DB-server
```

Every workflow except the two `vfoundry-*.yml` files should show as `disabled_manually`.
