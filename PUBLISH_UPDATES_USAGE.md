# Publishing NXP Easy Forms (Joomla)

Publishes the release ZIP and Joomla `updates.xml` manifest to `updates.nexusplugins.com`.

## Quick Commands

```bash
# Build and publish in one step
./make-release.sh --publish

# Publish an already-built release (skip build)
./make-release.sh --publish-only

# Preview what would happen (no upload)
../publish-updates.sh --repo com_nxpeasyforms --dry-run

# Publish directly (bypassing make-release.sh)
../publish-updates.sh --repo com_nxpeasyforms
```

## What Gets Uploaded

| File | Remote location |
|------|-----------------|
| `pkg_nxpeasyforms_{VERSION}.zip` | `.../joomla/packages/pkg_nxpeasyforms/builds/` |
| `updates.xml` | `.../joomla/packages/pkg_nxpeasyforms/updates.xml` |

The generated `updates.xml` includes element `pkg_nxpeasyforms`, type `package`, SHA-256 + SHA-384 checksums, and targets Joomla 5.x and 6.x.

## Version Detection

The version is read automatically from:
`administrator/components/com_nxpeasyforms/nxpeasyforms.xml`

Override with: `../publish-updates.sh --repo com_nxpeasyforms --version 1.1.0`

## Full Reference

See [PUBLISH_UPDATES_USAGE.md](../PUBLISH_UPDATES_USAGE.md) in the repo root.
