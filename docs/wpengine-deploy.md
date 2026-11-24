# Deploying to WP Engine

How code gets from this repository to WP Engine, how to set it up the first time, and what to do when something goes wrong.

## How it works

WP Engine runs WordPress core and owns `wp-config.php`. Only `wp-content` is ours to deploy. Bedrock keeps `wp-content` in `web/app` and Composer's packages in `vendor/` at the project root, so every deploy builds a flat artifact first. This follows WP Engine's own guide, [Deploying a Composer-based WordPress site to WP Engine](https://wpengine.com/builders/deploying-composer-based-wordpress-site-to-wp-engine/).

```
push to develop / staging / main
  -> GitHub Actions: scripts/build-wpengine.sh
       build/wp-content/
         mu-plugins/   00-composer-autoload.php, bedrock-autoloader.php, northwind-core, ...
         plugins/      advanced-custom-fields-pro, redirection
         themes/       northwind
         vendor/       Composer autoloader and libraries
  -> wpengine/github-action-wpe-site-deploy@v4 (rsync over SSH)
  -> WP Engine page and CDN cache cleared
  -> result posted to Slack or Teams (optional)
```

What the build does (`scripts/build-wpengine.sh`):

1. Installs the locked production dependencies (`--no-dev`) with Composer's paths rewritten to the WP Engine layout. Composer then generates an autoloader whose paths are correct on the server.
2. Copies the code this repository owns: the `northwind-core` must-use plugin and the `northwind` theme.
3. Adds `mu-plugins/00-composer-autoload.php`, which loads `wp-content/vendor/autoload.php`. WP Engine's `wp-config.php` does not load it, and must-use plugins load in alphabetical order, so this runs first.
4. Checks PHP syntax and that the autoloader can find the project's classes.

You can run it locally with `ddev exec composer build:wpengine` and inspect `build/wp-content`.

| Branch | GitHub environment | WP Engine environment | Purpose |
| --- | --- | --- | --- |
| `develop` | `development` | Development | Integration: everything merged so far, for the team |
| `staging` | `staging` | Staging | Release candidate, for client review and sign-off |
| `main` | `production` | Production | The live site |

Feature branches are never deployed; they only run the checks. A manual run of the Deploy workflow from any other branch does nothing.

## What is and is not deployed

The deploy uses rsync with `--delete`, so `wp-content` on the server matches the artifact. That is safe only because the workflow sets `REMOTE_PATH: wp-content/` exactly: with that value the action adds its own protective excludes, so these are never touched:

- `wp-content/uploads` (media is not in Git and is never deployed)
- WP Engine's must-use plugins and drop-ins (`object-cache.php` and similar)
- the cache and upgrade directories

Do not change `REMOTE_PATH`, even to an equivalent spelling such as `wp-content`. Without the exact value the protective excludes are skipped and `--delete` would remove uploads.

The database is never deployed. Content, settings and plugin activation are managed in each environment's admin.

## First-time setup

### 1. A deploy user and SSH key

Use a dedicated WP Engine portal user for deploys, so the key is not tied to a person.

```sh
ssh-keygen -t ed25519 -N "" -C "github-deploy" -f wpengine_deploy
```

- Add `wpengine_deploy.pub` to that user at **my.wpengine.com > Profile > SSH keys**.
- Keep `wpengine_deploy` (the private key) for step 2, then delete both files from your machine.

### 2. GitHub environments

In the repository, **Settings > Environments**, create `development`, `staging` and `production`. In each:

| Kind | Name | Value |
| --- | --- | --- |
| Variable | `WPE_ENV` | The WP Engine environment name, for example `northwinddemodev`, `northwinddemostg` or `northwinddemo` |
| Secret | `WPE_SSHG_KEY_PRIVATE` | Contents of the private key from step 1 |

Recommended: limit each environment's deployment branches to its own branch (`develop`, `staging`, `main`), and add a required reviewer to `production`.

Until both values are present in an environment, the Deploy workflow finishes with a notice and does nothing for that branch. Environments are independent, so you can start with production only and add the others later. This is why the public demo repository shows skipped deploys.

The repository also needs the `ACF_PRO_KEY` Actions secret, which the build uses to download ACF Pro.

### 3. Deploy notifications (optional)

Add a repository secret **`DEPLOY_WEBHOOK_URL`** (**Settings > Secrets and variables > Actions**) to post the result of every deploy to a channel: environment, commit, author, result and a link to the run. Failed and cancelled deploys are posted too. Without the secret, no notification is sent; deploys skipped because WP Engine is not configured are never posted.

- **Slack:** create an app with an [incoming webhook](https://api.slack.com/messaging/webhooks) for the channel and use its `https://hooks.slack.com/...` URL.
- **Microsoft Teams:** in the channel, add the Workflows template **Post to a channel when a webhook request is received** and use the URL it gives you. Any URL that is not a Slack URL is sent an Adaptive Card in the format that template expects.

A broken or expired webhook shows as a warning on the Notify step but never fails the deploy. To preview the message, run `scripts/notify-deploy.sh --dry-run` with the variables listed at the top of the script.

### 4. ACF Pro license on the server

Locally the key comes from `.env`. On WP Engine, either activate the license once under **ACF > Updates** in each environment's admin, or ask WP Engine support to add `define( 'ACF_PRO_LICENSE', '...' );` to `wp-config.php`. Do not commit the key.

### 5. Block direct access to `wp-content/vendor`

The Composer libraries are PHP files that are not meant to be requested directly. WP Engine does not honour `.htaccess` ([announcement](https://wpengine.com/support/htaccess-deprecation/)), so add a rule in the portal instead: **Environment > Web Rules > Access rules**, deny requests whose path starts with `/wp-content/vendor/`. Add it to each environment.

### 6. First deploy

1. Push to `develop`. Watch **Actions > Deploy**.
2. `--delete` removes the default themes that came with the new install, so in the development admin activate the **Northwind** theme, then **Advanced Custom Fields PRO** and **Redirection**.
3. Run the Redirection setup in **Tools > Redirection** when prompted.
4. Import redirects and seed content if wanted, over SSH: `wp northwind redirects import <file> --dry-run`, then without `--dry-run`. The CSV is not deployed, so copy it up first or paste it into the Redirection import screen.
5. Repeat for staging and production as the code is promoted (see below).

## Day-to-day

1. Merge feature branches into `develop` by pull request. Development deploys.
2. When ready for client review, open a pull request from `develop` to `staging` and merge it. Staging deploys.
3. When staging is approved, open a pull request from `staging` to `main` and merge it. Production deploys.

Deploys of the same branch queue rather than cancel each other, so a deploy is never cut off halfway.

## Environment type

WP Engine does not set `WP_ENVIRONMENT_TYPE` per environment, and `is_wpe()` is true on all of them. Code in this repository therefore treats an unknown environment as production (for example, the ACF field group editor is hidden). To get the field editor on development or staging, ask WP Engine support to define `WP_ENVIRONMENT_TYPE` as `development` or `staging` in that environment's `wp-config.php`.

## Copying production to staging or development

Use the portal's **Copy environment** ([guide](https://wpengine.com/support/copy-site/)).

- Choose a **custom** copy with the **database only**. A file copy is fully destructive and also copies `wp-config.php`, which would overwrite the destination's settings.
- WP Engine searches and replaces the domain in the database for you, and backs up the destination before and after.
- If you did copy files, rerun the last Deploy run for that branch to put its code back.
- Portal redirects, SSL certificates and Web Rules are not copied. Check the `wp-content/vendor` rule is in place on the destination.

## Rollback

- **Code:** revert the merge commit on the branch (`git revert -m 1 <sha>`), open a pull request, merge it. The deploy puts the previous code back. You can also rerun an earlier successful Deploy run from the Actions tab.
- **Database or files:** restore a backup point in the portal (**Backup points**). WP Engine takes daily backups and one before every Copy environment.

## Troubleshooting

| Symptom | Likely cause |
| --- | --- |
| Deploy shows "WP Engine is not configured" | `WPE_ENV` or `WPE_SSHG_KEY_PRIVATE` missing in that GitHub environment |
| Build fails at "Installing production dependencies" with 401 | `ACF_PRO_KEY` secret missing or expired |
| SSH authentication fails | Public key not added to the deploy user, or the user lost access to the environment |
| Deploy times out | WP Engine's SSH gateway closes sessions after 10 minutes; rerun, the second rsync is incremental |
| Site shows "Northwind Core is inactive" | `00-composer-autoload.php` or `wp-content/vendor` missing; check the artifact in a local build |
| No message in Slack or Teams | `DEPLOY_WEBHOOK_URL` missing, or the Notify step shows the webhook's error (expired URL, Teams workflow turned off) |
| Old content after a deploy | The action clears page and CDN cache only; clear object cache from the portal if needed |
