## What and why

<!-- What does this change, and why is it needed? Link the ticket if there is one. -->

## How to test

<!-- Steps a reviewer can follow on a fresh `ddev setup`. -->

1.

## Checklist

- [ ] `ddev composer lint` passes
- [ ] New or changed blocks follow [docs/block-standards.md](../docs/block-standards.md)
- [ ] Checked with the keyboard only, and `scripts/a11y.sh` passes
- [ ] ACF field group changes are committed as JSON in `northwind-core/acf-json`
- [ ] No secrets, licence keys or client names in the diff
- [ ] Docs updated (README, editor guide or deploy guide) if behaviour changed
