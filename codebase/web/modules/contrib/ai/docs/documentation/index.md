# Documentation (How To)

## Where do I find this documentation
In the Gitlab [repository](https://git.drupalcode.org/project/ai) there is a docs repository. All documentation you see on this page have been built from that.

This means that if you want an offline version of this documentation, this is where you can find it.

## How do I change this documentation
You can simply create an issue, under the [AI Issue queue](https://www.drupal.org/project/issues/ai?categories=All). Then you do a MR with your changes and if they make sense we will merge them and they will show up.

For exact instructions see [Contribute Documentation](../contribute/documentation.md).

## How do I test the changes locally
The documentation uses `mkdocs` and the `material` theme, so you can install with:

`pip install mkdocs mkdocs-material`

Then you can run `mkdocs serve` in the root directory of the AI module and it will be available under `http://localhost:8000` by default.

## My changes only apply for specific versions
Just make the MR to latest version it applied to it, and they you can tag the issue as "Backport to version x.x.x" and the maintainer that merges it will make sure it shows up on all the different documentations.

## What should go into this documentation
In general its quite broad - of course anything that affects AI and its submodules, but also big stroke documentation for Providers and AI Agents.

If you contribute to an Provider and want to promote it or write installation instructions, feel free to push it under the providers directory.

## How do you switch the default version (for Maintainers)
This can only be done by maintainers that has the right to push to the repo. When you have decided that we for instance have a stable release or near stable release of a version and want to make that the default when you visit https://project.pages.drupalcode.org/ai/ these are the following things you need to to.

In this case we do 3.1.x for instance.

1. Get mkdocs and mike - `pip install mike mkdocs mkdocs-material`
2. Checkout that version in git - `git checkout 3.1.x`
3. Change in mkdocs.yml the canonical_version, this is for SEO reasons. Make sure to push this also.
4. Run `mike alias 3.1.x latest -u` to set 3.1.x to the latest.
5. Run `mike deploy 3.1.x --push` to push the alias changes.
