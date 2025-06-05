# Novicell Drupal Premium

A headless Drupal 11 based professional CMS solution by Novicell, designed for enterprise-level websites and digital experiences.

The full stack is:

- Drupal 11 (Backend)
- PHP 8.2+ (with Composer 2)
- Nginx
- Redis
- MariaDB
- Node.js (Frontend in separate repository)

## Related Repositories

This repository contains the backend Drupal code. Related repositories:

- Frontend application: [DPB-frontend](#) - Contains the decoupled frontend application
- Infrastructure: [DPB-infrastructure](#) - Contains infrastructure as code

For a better understanding of the project's staging and production environments, please see the [Deployment](#deployment) section.

## Development

You can run this project in a local environment using DDEV (recommended for quick setup).

### Getting Started

These instructions will get you a copy of the project up and running on your local machine for development, demo, and testing purposes.

#### DDEV

DDEV sets up the project and necessary backing services.

##### Benefits

DDEV is fast, very reliable, open source, and we have a lot of experience using it during development at Novicell.

##### Requirements

On your host machine install the following:

- [Git](https://git-scm.com/)
- [Docker](https://ddev.readthedocs.io/en/stable/users/docker_installation/)
- [DDEV](https://ddev.readthedocs.io/en/stable/)

1. Clone the project repository
   ```sh
   git clone git@github.com:novicell-php/DPB-drupal.premium11.git
   ```
2. Enter the newly created project directory
   ```sh
   cd DPB-drupal.premium11
   ```
3. Create local `.env` file from `.env.dist`:
   ```sh
   cp .env.dist .env
   ```
   _**Note: Insert values or reach out if there are any issues with the default DDEV setup.**_
4. Now run project with DDEV:
   ```sh
   ddev start
   ```
5. Install dependencies with Composer
   ```sh
   ddev composer install
   ```
6. Import the latest database using DDEV:
   ```sh
   ddev import-db --src=premium.sql.gz
   ```
   Project can be reached at [https://dpb-drupal-premium11.ddev.site](https://dpb-drupal-premium11.ddev.site)
7. List entire DDEV project:
   ```sh
    ddev describe
    ```

#### DDEV commands to fix your project

You have to run these commands if you have issues with your project:

1. Update your local branch:
    ```sh
    git pull
    ```
2. Install latest modules and themes:
   ```sh
   ddev composer install
   ```
3. Update database:
    ```sh
    ddev drush updb -y
    ```
4. Run config import:
    ```sh
    ddev drush cim -y
    ```
5. Run cache rebuild:
    ```sh
    ddev drush cr
    ```

If the project is still broken after running these commands, you can try to run:

```sh
ddev restart
```

If the project is still broken after running these commands, you can try to reimport the database:

```sh
ddev import-db --src=premium.sql.gz
```

### Composer

New functionality is often added through contributed, premium, or custom modules. These are installed and maintained using Composer, which is a dependency manager for PHP. This documentation on [Composer](https://getcomposer.org/doc/01-basic-usage.md) will get you up to speed and managing dependencies in no time.

**_Note: While developing new features or structures we do not wish to update our Composer dependencies unless this is necessary. This means not running `composer update` if this is not part of the task at hand._**

#### Quick commands list

To add a dependency to a project:

   ```sh
   ddev composer require drupal/example_contrib_module
   ```

To install all dependencies from composer.json or composer.lock:

   ```sh
   ddev composer install
   ```

Changes to dependencies are written to `composer.json` and `composer.lock` in the root of our project, from where they can be committed and pushed remotely.

### Drupal configuration during development

While developing new features or structure in Drupal it is important to export the configuration of the feature you are working on. You can always check the status of the configuration synchronization using Drush:

   ```sh
   ddev drush config:status
   ```

If you have changes to the configuration use Drush to export these:

   ```sh
   ddev drush cex -y
   ```

The configuration will be written to `.yml` files in `/config/sync`, from where they can be committed and pushed.

_**Note: When exporting config do a check on language switches (they do unfortunately happen) and overwriting customized config (like standard Drupal mails changed through core/updb).**_

## Git strategy & collaboration

We use Git in order to ease collaboration and create versioning of our code. Furthermore, we aim to improve the overall quality during development by keeping our git strategy lean and without complexity. This in order to get a dynamic workflow that suits the size of the project and developer team.

### Branch naming convention and commit message

The branch naming convention follows the standard Novicell approach:

* Branch name should include issue tag (example: `DPB-50`):
   ```
   DPB-50
   ```

By including the issue tag in the commit messages and branch name we get the benefit of the GitHub/Jira integration.

### Rebase in developer workflow

Developers work differently but here is an example of a workflow using Git for this project.

Pull the latest changes of the main branch - _**Note: use `--rebase` flag to align our local main's history with the remote**_:

   ```sh
   git pull --rebase origin main
   ```

Create a feature branch before starting new work:

   ```sh
   git switch -c DPB-50
   ```

Make some changes and commit often in the feature branch:

   ```sh
   git add .
   ```

   ```sh
   git commit -m "DPB-50: My awesome comment"
   ```

Periodically rebase your work onto main branch - in case new features have been merged to main.
First off we update our local main branch:

   ```sh
   git checkout main
   ```

   ```sh
   git pull --rebase origin main
   ```

Include the latest commits of our local main branch, and get them into our local feature branch.

   ```sh
   git checkout DPB-50
   ```

   ```sh
   git rebase main
   ```

_**Note: During a rebase you might have to deal with conflicts, this is expected and unavoidable if there are changes**_

Now our branch is up-to-date. We build and test locally again after which we can push to our remote:

   ```sh
   git push origin DPB-50 --force
   ```

_**Note: new commits are added to the branch and by using the `--force` or `-f` flag we allow git to overwrite the history of the remote branch. Forgetting this step will lead to a Git error as the branch histories of local and remote differ. Do not `git pull` even if prompted for it - your current feature branch will include all changes from main and your own changes - being both ahead and behind main.**_

For release branches, create a branch from main with the naming convention `release/*`:

   ```sh
   git checkout main
   git switch -c release/1.0.0
   ```

## Deployment

This project uses GitHub Actions workflows to automate deployment to Platform.sh environments:

1. Pushing to a `release/*` branch triggers deployment to the staging environment
2. Pushing to the `main` branch triggers deployment to the production environment

The workflow defined in `.github/workflows/trigger-umbrella.yml`:

1. Triggers on push to `release/*` or `main` branches
2. Determines the target environment based on the branch
   - `main` → production
   - `release/*` → staging
3. Sends a repository dispatch event to the Platform.sh repository with:
   - The target environment
   - The commit hash to deploy
   - Submodule information

To manually trigger a deployment:

```sh
git push origin release/[release-name]  # For staging
git push origin main                    # For production
```

The workflow automatically notifies the Platform.sh repository, which then pulls the latest changes and deploys them to the appropriate environment.



## Best practices for collaboration

Git and collaboration on a big team can be confusing at times, and we have gathered a few best practices to ensure good collaboration:

* Merges come from Pull Requests (PR), which invites team members to engage - main branch is protected.
* Merge squash your multiple changes in short-lived branches - improved git history.
* Rebasing main against your short-lived branch to keep it up to date is best.
* Feature branches should be short-lived - this fits our delivery model and tasks broken down to maximum two working days.
* Keep commit messages as concise as possible, while still making sense and add the case number as the first thing.
* NEVER commit secrets of any kind to the repository — EVER.
* With Composer use the `--sort-packages` flag, which is a nice feature, though it should be a default on composer 2.
* Let us keep the repository clean and delete old feature branches.

### Deploy bumper
This has been done 1 times
